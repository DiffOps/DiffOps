<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PrState;
use App\Models\AiDecision;
use App\Models\PullRequest;
use App\Models\PullRequestFile;
use App\Models\RiskAssessment;
use App\Services\Analysis\Chunk;
use App\Services\Analysis\DiffSanitizer;
use App\Services\Analysis\HeuristicAuditor;
use App\Services\OpenRouter\AiCallResult;
use App\Services\AuditLogService;
use App\Services\OpenRouter\OpenRouterService;
use App\Services\Risk\RiskAssessmentBuilder;
use App\Services\Risk\RiskScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Analyzes an ingested pull request: heuristic audit, AI cross-check and
 * risk assessment merge. Runs after ProcessIncursionJob has persisted the
 * diff files, so the audit always sees the current snapshot.
 */
class AnalyzeIncursionJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $payload,
        public ?string $deliveryId = null,
    ) {}

    /**
     * Analyze the pull request for every organization that registered the
     * repository.
     */
    public function handle(): void
    {
        $repoId = $this->payload['repository']['id'] ?? null;

        if (! is_int($repoId) && ! is_numeric($repoId)) {
            return;
        }

        $prNumber = (int) ($this->payload['number'] ?? 0);

        if ($prNumber <= 0) {
            return;
        }

        $headSha = (string) ($this->payload['pull_request']['head']['sha'] ?? '');

        $pullRequests = PullRequest::with('organization')
            ->where('github_repo_id', (int) $repoId)
            ->where('github_pr_number', $prNumber)
            ->get();

        foreach ($pullRequests as $pullRequest) {
            $this->analyze($pullRequest, $headSha);
        }
    }

    private function analyze(PullRequest $pullRequest, string $headSha): void
    {
        // Early-return A: closed/merged PRs are never analyzed again.
        if ($pullRequest->state !== PrState::Open) {
            return;
        }

        // Early-return B: the head sha was already assessed.
        $alreadyAssessed = RiskAssessment::where('pull_request_id', $pullRequest->id)
            ->where('head_sha', $headSha)
            ->exists();

        if ($alreadyAssessed) {
            return;
        }

        $fileRows = $pullRequest->files()
            ->get(['file_path', 'raw_patch'])
            ->map(static fn (PullRequestFile $file): array => [
                'file_path' => (string) $file->file_path,
                'raw_patch' => (string) $file->raw_patch,
            ])
            ->all();

        if ($fileRows === []) {
            return;
        }

        $report = app(HeuristicAuditor::class)->audit($fileRows);

        $this->persistSensitiveFlags($pullRequest, $report->sensitivePaths);

        $chunks = app(DiffSanitizer::class)->sanitize($fileRows, $report);

        $aiResults = $this->askAi($chunks);

        $startedAt = hrtime(true);

        $assessmentData = app(RiskAssessmentBuilder::class)->build($report, $aiResults);
        $assessmentData['execution_time_ms'] = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        DB::transaction(function () use ($pullRequest, $headSha, $assessmentData, $aiResults): void {
            $assessment = RiskAssessment::updateOrCreate(
                ['pull_request_id' => $pullRequest->id, 'head_sha' => $headSha],
                $assessmentData,
            );

            foreach ($aiResults as $result) {
                AiDecision::create([
                    'risk_assessment_id' => $assessment->id,
                    'model_used' => $result->model_used,
                    'attempt' => $result->attempt,
                    'validity' => $result->validity,
                    'raw_response' => $result->raw_response,
                    'ai_signals' => $result->parsed,
                    'prompt_tokens' => $result->tokens['prompt_tokens'] ?? null,
                    'completion_tokens' => $result->tokens['completion_tokens'] ?? null,
                    'total_tokens' => $result->tokens['total_tokens'] ?? null,
                    'latency_ms' => $result->latency_ms,
                ]);
            }

            app(RiskScoringService::class)->updateFingerprint(
                $pullRequest->organization,
                $pullRequest,
                $assessment,
            );
        });

        // Combat History: record the analysis result (system-initiated; user may be null if auth removed).
        $assessment = RiskAssessment::where('pull_request_id', $pullRequest->id)
            ->where('head_sha', $headSha)
            ->first();

        if ($assessment) {
            app(AuditLogService::class)->log(
                action: 'analysis.completed',
                entityType: 'risk_assessment',
                userId: null,
                entityId: (string) $assessment->id,
                payload: [
                    'verdict' => $assessment->verdict,
                    'defcon_level' => $assessment->defcon_level,
                    'security_score' => $assessment->security_score,
                    'is_degraded' => $assessment->is_degraded,
                ],
            );
        }
    }

    /**
     * @param  list<string>  $sensitivePaths
     */
    private function persistSensitiveFlags(PullRequest $pullRequest, array $sensitivePaths): void
    {
        PullRequestFile::where('pull_request_id', $pullRequest->id)
            ->update(['is_sensitive' => false]);

        if ($sensitivePaths !== []) {
            PullRequestFile::where('pull_request_id', $pullRequest->id)
                ->whereIn('file_path', $sensitivePaths)
                ->update(['is_sensitive' => true]);
        }
    }

    /**
     * Cross-check the sanitized chunks with the LLM, one HTTP call per
     * attempt (append-only evidence). Degrades to an empty list when the
     * provider is not configured.
     *
     * @param  array<int, Chunk>  $chunks
     * @return list<AiCallResult>
     */
    private function askAi(array $chunks): array
    {
        $apiKey = config('services.openrouter.api_key');

        if ($apiKey === null || $chunks === []) {
            return [];
        }

        $results = [];

        foreach ($chunks as $index => $chunk) {
            $results = [
                ...$results,
                ...app(OpenRouterService::class)->call($chunk, $index + 1, count($chunks)),
            ];
        }

        return $results;
    }
}

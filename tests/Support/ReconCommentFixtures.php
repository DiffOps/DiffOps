<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\RiskAssessment;

/**
 * Fixtures for F1 Recon Comment tests: wires an organization's repository,
 * pull request and risk assessment together so the job/controller can be
 * exercised end to end without a real GitHub connection.
 */
final class ReconCommentFixtures
{
    /**
     * @return array{Repository, PullRequest, RiskAssessment}
     */
    public static function scenario(
        Organization $org,
        array $repoOverrides = [],
        array $prOverrides = [],
        array $assessmentOverrides = []
    ): array {
        $repo = Repository::create(array_merge([
            'organization_id' => $org->id,
            'github_repo_id' => 555001,
            'full_name' => 'alpha/web',
            'comment_on_pr' => true,
            'installation_id' => 99887766,
        ], $repoOverrides));

        $pr = PullRequest::create(array_merge([
            'organization_id' => $org->id,
            'github_repo_id' => $repo->github_repo_id,
            'repo_full_name' => $repo->full_name,
            'github_pr_number' => 77,
            'title' => 'Recon PR',
            'author_username' => 'recondev',
            'state' => 'open',
            'is_draft' => false,
        ], $prOverrides));

        $assessment = RiskAssessment::create(array_merge([
            'pull_request_id' => $pr->id,
            'head_sha' => str_repeat('b', 40),
            'verdict' => 'hostile',
            'defcon_level' => 1,
            'security_score' => 92,
            'risk_level' => 'high',
            'summary' => 'Recon assessment summary',
            'compliance_checks' => [
                [
                    'category' => 'secret-leak',
                    'severity' => 'critical',
                    'file_path' => 'config/secrets.php',
                    'description' => 'Hardcoded API key',
                ],
            ],
        ], $assessmentOverrides));

        return [$repo, $pr, $assessment];
    }
}

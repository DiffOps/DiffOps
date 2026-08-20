<?php

declare(strict_types=1);

use App\Services\Analysis\HeuristicAuditor;
use App\Services\Analysis\HeuristicReport;
use Tests\Support\DiffFixtures;

function auditorFile(array $overrides = []): array
{
    return array_merge([
        'file_path' => 'app/Example.php',
        'raw_patch' => "@@ -1,3 +1,4 @@\n+echo 'hi';\n",
        'is_binary' => false,
        'status' => 'modified',
    ], $overrides);
}

function auditFiles(array $files): HeuristicReport
{
    return app(HeuristicAuditor::class)->audit($files);
}

it('flags an AWS access key on an added line', function (): void {
    $report = auditFiles([DiffFixtures::awsKeyInConfig()]);

    expect($report->findings)->toHaveCount(1)
        ->and($report->findings[0]['category'])->toBe('secret')
        ->and($report->findings[0]['severity'])->toBe('critical')
        ->and($report->findings[0]['file_path'])->toBe('config/aws.php');
});

it('ignores secrets on removed lines', function (): void {
    $report = auditFiles([auditorFile([
        'file_path' => 'config/aws.php',
        'raw_patch' => "@@ -1 +1 @@\n-        'key' => 'AKIAIOSFODNN7EXAMPLE',\n",
    ])]);

    expect($report->findings)->toBe([]);
});

it('flags a github personal access token', function (): void {
    $token = 'ghp_'.str_repeat('a', 36);

    $report = auditFiles([auditorFile([
        'raw_patch' => "@@ -0,0 +1,2 @@\n+    \$token = '{$token}';\n",
    ])]);

    expect($report->findings)->toHaveCount(1)
        ->and($report->findings[0]['category'])->toBe('secret');
});

it('flags a fine-grained github access token', function (): void {
    $token = 'github_pat_'.str_repeat('b', 22).'_XYZ';

    $report = auditFiles([auditorFile([
        'raw_patch' => "@@ -0,0 +1,2 @@\n+    \$token = '{$token}';\n",
    ])]);

    expect($report->findings)->toHaveCount(1)
        ->and($report->findings[0]['category'])->toBe('secret');
});

it('flags a private key block', function (): void {
    $report = auditFiles([DiffFixtures::privateKeyPem()]);

    expect($report->findings)->toHaveCount(2)
        ->and(array_column($report->findings, 'category'))->toContain('secret')
        ->and(array_column($report->findings, 'category'))->toContain('sensitive_file');
});

it('flags an openai-style sk token', function (): void {
    $report = auditFiles([auditorFile([
        'raw_patch' => "@@ -0,0 +1,2 @@\n+    \$key = \"sk-1234567890abcdef\";\n",
    ])]);

    expect($report->findings)->toHaveCount(1)
        ->and($report->findings[0]['category'])->toBe('secret');
});

it('flags an api key assignment', function (): void {
    $report = auditFiles([DiffFixtures::exposedDotEnvSecret()]);

    expect(array_column($report->findings, 'category'))->toContain('secret');
});

it('flags an embedded jwt token', function (): void {
    $report = auditFiles([DiffFixtures::embeddedJwt()]);

    expect(array_column($report->findings, 'category'))->toContain('secret');
});

it('does not flag benign code', function (): void {
    $report = auditFiles([DiffFixtures::benignPhpDiff()]);

    expect($report->findings)->toBe([])
        ->and($report->score)->toBe(0);
});

it('flags a composer dependency downgrade', function (): void {
    $report = auditFiles([DiffFixtures::composerDowngrade()]);

    expect($report->findings)->toHaveCount(1)
        ->and($report->findings[0]['category'])->toBe('downgrade')
        ->and($report->findings[0]['severity'])->toBe('medium');
});

it('flags a package-lock dependency downgrade', function (): void {
    $report = auditFiles([DiffFixtures::packageLockDowngrade()]);

    expect($report->findings)->toHaveCount(1)
        ->and($report->findings[0]['category'])->toBe('downgrade')
        ->and($report->findings[0]['file_path'])->toBe('package-lock.json');
});

it('does not flag a dependency upgrade', function (): void {
    $report = auditFiles([auditorFile([
        'file_path' => 'composer.json',
        'raw_patch' => "@@ -10,7 +10,7 @@\n-        \"acme/lib\": \"^1.9.0\",\n+        \"acme/lib\": \"^2.0.1\",\n",
    ])]);

    expect($report->findings)->toBe([]);
});

it('flags sensitive file paths like dotenv, pem and credentials', function (): void {
    $report = auditFiles([
        DiffFixtures::exposedDotEnvSecret(),
        DiffFixtures::privateKeyPem(),
        DiffFixtures::sensitiveCredentialsJson(),
    ]);

    $sensitive = array_column(
        array_filter($report->findings, static fn (array $f): bool => $f['category'] === 'sensitive_file'),
        'file_path',
    );

    expect($sensitive)->toBe(['.env', 'deploy/id_rsa.pem', 'config/credentials.json']);
});

it('flags eval, exec and shell_exec danger signals', function (): void {
    $report = auditFiles([DiffFixtures::evalDanger()]);

    expect(array_column($report->findings, 'category'))->toContain('eval')
        ->and($report->findings[array_key_first(array_filter($report->findings, static fn (array $f): bool => $f['category'] === 'eval'))]['severity'])->toBe('high');
});

it('flags curl piped to sh and chmod 777', function (): void {
    $report = auditFiles([DiffFixtures::curlPipeSh(), DiffFixtures::chmod777()]);

    expect(array_column($report->findings, 'category'))->toContain('shell');
});

it('does not flag benign words that contain exec or eval', function (): void {
    $report = auditFiles([auditorFile([
        'raw_patch' => "@@ -0,0 +1,3 @@\n+    \$evaluator = new Evaluator();\n+    \$this->execute('ls');\n+    \$executor = new Executor();\n",
    ])]);

    expect($report->findings)->toBe([]);
});

it('scores the weighted sum of findings capped at a hundred', function (): void {
    $secret = DiffFixtures::awsKeyInConfig();
    $downgrade = DiffFixtures::composerDowngrade();
    $sensitive = DiffFixtures::sensitiveCredentialsJson();
    $eval = DiffFixtures::evalDanger();
    $shell = DiffFixtures::curlPipeSh();

    expect(auditFiles([$secret])->score)->toBe(40)
        ->and(auditFiles([$secret, $downgrade])->score)->toBe(65)
        ->and(auditFiles([$secret, $downgrade, $sensitive, $eval, $shell])->score)->toBe(100)
        ->and(auditFiles([$secret, $downgrade, $sensitive, $eval, $shell, DiffFixtures::chmod777()])->score)->toBe(100);
});

it('never leaks the raw secret value into the findings', function (): void {
    $report = auditFiles([DiffFixtures::awsKeyInConfig()]);

    $serialized = json_encode($report->findings);

    expect($serialized)->not->toContain('AKIAIOSFODNN7EXAMPLE');
});

it('collects the sensitive paths of the pull request', function (): void {
    $report = auditFiles([
        DiffFixtures::awsKeyInConfig(),
        DiffFixtures::exposedDotEnvSecret(),
        DiffFixtures::benignPhpDiff(),
    ]);

    expect($report->sensitivePaths)->toBe(['config/aws.php', '.env']);
});

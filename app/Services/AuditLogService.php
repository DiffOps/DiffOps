<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;

/**
 * Append-only Combat History (audit_logs) writer.
 *
 * Centralises all writes to the audit_logs table so that:
 *  - the payload is always sanitised before reaching the database
 *    (no secret ever persists in audit_logs, see sanitize()).
 *  - the rest of the codebase depends on a single service, not on the
 *    AuditLog model directly (easier to swap or instrument).
 */
final class AuditLogService
{
    /**
     * Patterns that mark a payload KEY as secret. Case-insensitive; matched
     * by full word (boundary-aware). Values under any of these keys are
     * replaced with '[REDACTED]' before persistence.
     */
    private const SECRET_KEY_PATTERNS = [
        'token',
        'secret',
        'key',
        'password',
        'authorization',
        'access_token',
        'api_key',
        'private_key',
        'client_secret',
        'passwd',
    ];

    /**
     * Patterns that mark a payload VALUE as a leaked credential. Matched
     * anywhere in the string via preg_match. The whole value is replaced
     * with '[REDACTED]' regardless of the surrounding key.
     */
    private const SECRET_VALUE_PATTERNS = [
        '#\bghp_[A-Za-z0-9]{6,}\b#',
        '#\bsk-[A-Za-z0-9]{6,}\b#',
        '#\beyJ[A-Za-z0-9_=/+.-]{6,}#',
        '#\bAKIA[0-9A-Z]{12,}\b#',
        '#\bxox[baprs]-[A-Za-z0-9-]{6,}\b#',
    ];

    /**
     * Persist a Combat History row.
     *
     * @param  string  $action  verb.action (e.g. 'webhook.received')
     * @param  string  $entityType  short entity class name (e.g. 'risk_assessment')
     * @param  ?int  $userId  actor user id (null = system / anonymous)
     * @param  ?string  $entityId  uuid of the affected entity (or null)
     * @param  array<string, mixed>  $payload  free-form context (sanitised)
     */
    public function log(
        string $action,
        string $entityType,
        ?int $userId = null,
        ?string $entityId = null,
        array $payload = []
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $this->sanitize($payload),
        ]);
    }

    /**
     * Replace secret keys and known credential value patterns with
     * '[REDACTED]'. Recurses into nested arrays. Preserves int/bool/null.
     *
     * Exposed as public for direct unit testing.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitize(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && $this->isSecretKey($key)) {
                $result[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->sanitize($value);

                continue;
            }

            if (is_string($value) && $this->isSecretValue($value)) {
                $result[$key] = '[REDACTED]';

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function isSecretKey(string $key): bool
    {
        $needle = strtolower($key);

        foreach (self::SECRET_KEY_PATTERNS as $pattern) {
            if (str_contains($needle, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isSecretValue(string $value): bool
    {
        foreach (self::SECRET_VALUE_PATTERNS as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}

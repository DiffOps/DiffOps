<?php

use App\Http\Middleware\EnsureOrganization;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ValidateGitHubSignature;
use App\Http\Middleware\VerifySupabaseJwt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Atrás de proxy/túnel (Cloud Shell Preview, ngrok, Cloudflare): o esquema
        // https chega via X-Forwarded-Proto; sem isso os assets saem http:// e
        // navegadores bloqueiam por mixed-content (tela branca do Inertia).
        $middleware->trustProxies(at: '*');

        // O cookie de sessão web carrega um JWT assinado pelo Supabase (integridade
        // própria); criptografia de cookie do framework fica desligada para este
        // nome, mantendo o comportamento determinístico em todos os ambientes.
        $middleware->encryptCookies(except: ['diffops_session', 'diffops_org']);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'verify.supabase.jwt' => VerifySupabaseJwt::class,
            'ensure.organization' => EnsureOrganization::class,
            'validate.github.signature' => ValidateGitHubSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

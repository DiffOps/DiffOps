<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Fixtures específicas para os testes de rate limit do OpenRouterService.
 *
 * Classe estática (sem funções globais) para evitar "Cannot redeclare" quando
 * o Pest compartilha o mesmo processo entre arquivos de teste.
 */
final class OpenRouterRateLimitFixtures
{
    /**
     * Envelope válido (verdict "clear" => AiDecisionValidity::Valid).
     */
    public static function validCompletion(): array
    {
        return OpenRouterFixtures::completion(OpenRouterFixtures::clearJson());
    }

    /**
     * Resposta 503 de provider sobrecarregado (free tier do OpenRouter).
     */
    public static function overloadedResponse(): array
    {
        return ['error' => ['message' => 'Provider is overloaded.']];
    }
}

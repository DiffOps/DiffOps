<?php

declare(strict_types=1);

namespace App\Services\OpenRouter\RateLimit;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

/**
 * Token bucket de taxa por modelo para o OpenRouter (free tier).
 *
 * Mantém o saldo de requisições permitidas por modelo em um store de cache
 * (Redis em produção, array em testes) com janela de refill de $windowSeconds.
 * O read-modify-write é serializado via Cache::lock quando o store implementa
 * LockProvider; stores sem lock (ex.: array) ignoram o lock, mantendo o
 * comportamento determinístico em testes.
 */
class TokenBucket
{
    /**
     * @param  string  $model  identificador do modelo (ex.: deepseek/deepseek-chat:free)
     * @param  int  $capacity  capacidade máxima de requisições por janela
     * @param  int  $windowSeconds  janela de refill em segundos (default 60)
     * @param  ?string  $store  nome do store de cache (null = default)
     */
    public function __construct(
        private readonly string $model,
        private readonly int $capacity,
        private readonly int $windowSeconds = 60,
        private readonly ?string $store = null,
    ) {}

    /**
     * Tenta consumir $costo tokens. Retorna true se havia saldo suficiente.
     */
    public function consumir(int $costo = 1): bool
    {
        return $this->comLock(function () use ($costo): bool {
            $state = $this->lerEstado();
            $this->repor($state);

            if ($state['tokens'] < $costo) {
                return false;
            }

            $state['tokens'] -= $costo;
            $this->gravarEstado($state);

            return true;
        });
    }

    /**
     * Saldo de tokens disponível no momento (após refill computado em memória).
     */
    public function saldoDisponivel(): int
    {
        $state = $this->lerEstado();
        $this->repor($state);

        return $state['tokens'];
    }

    /**
     * Segundos até que ao menos um token esteja disponível (0 se já houver).
     */
    public function segundosParaDisponibilidade(): int
    {
        $state = $this->lerEstado();
        $this->repor($state);

        if ($state['tokens'] >= 1) {
            return 0;
        }

        $elapsed = now()->timestamp - $state['updated_at'];

        return $elapsed >= $this->windowSeconds ? 0 : ($this->windowSeconds - $elapsed);
    }

    public function chave(): string
    {
        return "openrouter:ratelimit:{$this->model}";
    }

    /**
     * @return array{tokens: int, updated_at: int}
     */
    private function lerEstado(): array
    {
        $stored = $this->cache()->get($this->chave());

        if (is_array($stored) && isset($stored['tokens'], $stored['updated_at'])) {
            return $stored;
        }

        return ['tokens' => $this->capacity, 'updated_at' => now()->timestamp];
    }

    /**
     * Repõe o saldo para a capacidade quando a janela transcorreu.
     *
     * @param  array{tokens: int, updated_at: int}  $state
     */
    private function repor(array &$state): void
    {
        $elapsed = now()->timestamp - $state['updated_at'];

        if ($elapsed < $this->windowSeconds) {
            return;
        }

        $state['tokens'] = $this->capacity <= 0 ? 0 : $this->capacity;
        $state['updated_at'] = now()->timestamp;
    }

    /**
     * @param  array{tokens: int, updated_at: int}  $state
     */
    private function gravarEstado(array $state): void
    {
        $this->cache()->put($this->chave(), $state, $this->windowSeconds * 2);
    }

    /**
     * Serializa o read-modify-write com lock apenas em stores com LockProvider.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function comLock(callable $callback)
    {
        $repo = $this->cache();

        if ($repo->getStore() instanceof LockProvider) {
            return $repo->lock($this->chave(), 1)->block(1, $callback);
        }

        return $callback();
    }

    private function cache()
    {
        return $this->store === null ? Cache::store() : Cache::store($this->store);
    }
}

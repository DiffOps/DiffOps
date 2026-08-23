<?php

declare(strict_types=1);

it('emits host-relative vite asset urls', function (): void {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('/build/assets/')
        ->and($html)->not->toMatch('#https?://[^"]+/build/#');
});

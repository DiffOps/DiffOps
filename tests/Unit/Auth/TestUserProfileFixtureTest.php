<?php

use Tests\Support\TestJwtSigner;
use Tests\Support\TestUserProfileFixture;

it('uses the signer subject as the payload id', function (): void {
    expect(TestUserProfileFixture::payload()['id'])->toBe(TestJwtSigner::SUB);
});

it('exposes the github identity with its identity data', function (): void {
    $identity = TestUserProfileFixture::payload()['identities'][0];

    expect($identity['provider'])->toBe('github')
        ->and($identity['identity_data']['user_name'])->toBe(TestUserProfileFixture::GITHUB_USERNAME)
        ->and($identity['email'])->toBe(TestUserProfileFixture::EMAIL);
});

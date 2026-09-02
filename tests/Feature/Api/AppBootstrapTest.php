<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

final class AppBootstrapTest extends TestCase
{
    public function test_web_and_mobile_clients_receive_shared_configuration(): void
    {
        $this->getJson('/api/v1/app/bootstrap')
            ->assertOk()
            ->assertHeader('X-Request-ID')
            ->assertJsonPath('data.application.name', 'Karossy Travels')
            ->assertJsonPath('data.application.default_currency', 'NGN')
            ->assertJsonPath('data.features.flights', true)
            ->assertJsonCount(7, 'data.services')
            ->assertJsonPath('meta.api_version', 'v1');
    }
}

<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

final class ServiceCatalogTest extends TestCase
{
    public function test_it_lists_the_seven_karossy_services(): void
    {
        $this->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.0.key', 'flights');
    }
}

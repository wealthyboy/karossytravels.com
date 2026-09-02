<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AnalyticsEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_a_structured_analytics_event(): void
    {
        $this->postJson('/api/v1/analytics/events', [
            'event' => 'service_viewed',
            'service' => 'visas',
            'funnel_step' => 'landing',
            'session_id' => (string) Str::uuid(),
            'properties' => ['country' => 'GB'],
        ])->assertAccepted()->assertJsonPath('data.accepted', true);

        $this->assertDatabaseHas('analytics_events', [
            'event' => 'service_viewed',
            'service' => 'visas',
        ]);
    }
}

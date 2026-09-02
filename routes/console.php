<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Travel\TravelApi\TravelApiClient;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('travel-api:authenticate {--force : Discard the cached token and request a new one}', function (TravelApiClient $travelApi): int {
    $result = $travelApi->authenticate((bool) $this->option('force'));
    $this->info('Travel API authentication succeeded.');
    $this->line('Cached token expires: '.($result['expires_at'] ?? 'managed by configured bearer token'));

    return self::SUCCESS;
})->purpose('Authenticate with the private travel API and cache the access token securely');

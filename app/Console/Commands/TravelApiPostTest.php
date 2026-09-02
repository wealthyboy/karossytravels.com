<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Travel\TravelApi\TravelApiClient;

final class TravelApiPostTest extends Command
{
    protected $signature = 'travel-api:post-test {payloadFile}';
    protected $description = 'Post a test booking payload to the private travel API and print the response.';

    public function handle(TravelApiClient $travelApi): int
    {
        $file = $this->argument('payloadFile');
        if (! file_exists($file)) {
            $this->error('Payload file not found: '.$file);
            return self::FAILURE;
        }

        $json = file_get_contents($file);
        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            $this->error('Payload file does not contain valid JSON.');
            return self::FAILURE;
        }

        $this->info('Posting payload to the private travel API...');
        try {
            $response = $travelApi->post((string) config('services.travel.travel_api.booking_create_path'), $payload);
            $this->info('Response:');
            $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Request failed: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}

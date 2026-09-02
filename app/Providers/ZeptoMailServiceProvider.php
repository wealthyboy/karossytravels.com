<?php

namespace App\Providers;

use App\Mail\ZeptoMailTransport;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;

final class ZeptoMailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(MailManager::class)->extend('zeptomail', function (array $config): ZeptoMailTransport {
            return new ZeptoMailTransport(
                (string) ($config['key'] ?? ''),
                (string) ($config['endpoint'] ?? 'https://api.zeptomail.com/v1.1/email'),
            );
        });
    }
}

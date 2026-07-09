<?php

declare(strict_types=1);

namespace Zeevx\SendByteTransport;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Zeevx\SendByteTransport\Exceptions\SendByteTransportException;

class SendByteTransportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('sendbyte', function (array $config = []) {
            $apiKey = $config['key'] ?? config('services.sendbyte.key');

            if ($apiKey === null || $apiKey === '') {
                throw SendByteTransportException::missingApiKey();
            }

            return new SendByteTransport(
                apiKey: (string) $apiKey,
                baseUrl: rtrim((string) ($config['base_url'] ?? 'https://api.sendbyte.africa'), '/'),
                timeout: (int) ($config['timeout'] ?? 30),
            );
        });
    }
}

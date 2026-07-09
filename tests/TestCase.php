<?php

declare(strict_types=1);

namespace Zeevx\SendByteTransport\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Zeevx\SendByteTransport\SendByteTransportServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SendByteTransportServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mail.default', 'sendbyte');
        $app['config']->set('mail.mailers.sendbyte', [
            'transport' => 'sendbyte',
            'key' => 'sk_test_key',
        ]);
        $app['config']->set('mail.from', ['address' => 'receipts@acme.test', 'name' => 'Acme']);
    }
}

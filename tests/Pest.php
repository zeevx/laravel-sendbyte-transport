<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Zeevx\SendByteTransport\Tests\TestCase;

uses(TestCase::class)->in('Feature');

function fakeSendByte(array $response = ['id' => 'em_01test', 'status' => 'queued'], int $status = 201): void
{
    Http::fake([
        '*/v1/emails' => Http::response($response, $status),
    ]);
}

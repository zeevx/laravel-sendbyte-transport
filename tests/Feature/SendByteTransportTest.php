<?php

declare(strict_types=1);

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Header\TagHeader;
use Zeevx\SendByteTransport\SendByteTransport;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Exception\TransportException;
use Zeevx\SendByteTransport\Exceptions\SendByteTransportException;

class ReceiptMail extends Mailable
{
    public function build(): self
    {
        return $this->subject('Your receipt')->html('<p>Payment received.</p>');
    }
}

it('sends a mailable through the sendbyte transport', function () {
    fakeSendByte();

    Mail::to('amaka@halo.test')->send(new ReceiptMail);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.sendbyte.africa/v1/emails'
            && $request->hasHeader('Authorization', 'Bearer sk_test_key')
            && $request['from'] === 'Acme <receipts@acme.test>'
            && $request['to'] === ['amaka@halo.test']
            && $request['subject'] === 'Your receipt'
            && $request['html'] === '<p>Payment received.</p>'
            && ! array_key_exists('text', $request->data());
    });
});

it('sends a plain-text body', function () {
    fakeSendByte();

    Mail::raw('Plain hello', function ($message) {
        $message->to('amaka@halo.test')->subject('Plain');
    });

    Http::assertSent(fn ($r) => $r['text'] === 'Plain hello'
        && ! array_key_exists('html', $r->data()));
});

it('maps cc, bcc and reply-to', function () {
    fakeSendByte();

    Mail::raw('Hello', function ($message) {
        $message->to('to@halo.test')
            ->cc('cc@halo.test')
            ->bcc('bcc@halo.test')
            ->replyTo('support@acme.test')
            ->subject('Copies');
    });

    Http::assertSent(fn ($r) => $r['cc'] === ['cc@halo.test']
        && $r['bcc'] === ['bcc@halo.test']
        && $r['reply_to'] === 'support@acme.test');
});

it('base64-encodes attachments', function () {
    fakeSendByte();

    Mail::raw('See attached', function ($message) {
        $message->to('to@halo.test')
            ->subject('Invoice')
            ->attachData('%PDF-fake', 'invoice.pdf', ['mime' => 'application/pdf']);
    });

    Http::assertSent(fn ($r) => $r['attachments'] === [[
        'filename' => 'invoice.pdf',
        'content' => base64_encode('%PDF-fake'),
        'content_type' => 'application/pdf',
    ]]);
});

it('maps tags, metadata, custom headers and the idempotency key', function () {
    fakeSendByte();

    Mail::raw('Hello', function ($message) {
        $message->to('to@halo.test')->subject('Tagged');

        $headers = $message->getSymfonyMessage()->getHeaders();
        $headers->add(new TagHeader('receipt'));
        $headers->add(new TagHeader('payment'));
        $headers->add(new MetadataHeader('order_id', '4421'));
        $headers->addTextHeader('X-Campaign-Id', 'q3-receipts');
        $headers->addTextHeader(SendByteTransport::IDEMPOTENCY_HEADER, 'order-4421-receipt');
    });

    Http::assertSent(function ($r) {
        return $r['tags'] === ['receipt', 'payment']
            && $r['idempotency_key'] === 'order-4421-receipt'
            && $r['headers']['X-Campaign-Id'] === 'q3-receipts'
            && $r['headers']['X-Metadata-order_id'] === '4421'
            && ! array_key_exists(SendByteTransport::IDEMPOTENCY_HEADER, $r['headers']);
    });
});

it('does not leak standard mime headers into the headers map', function () {
    fakeSendByte();

    Mail::to('to@halo.test')->send(new ReceiptMail);

    Http::assertSent(fn ($r) => ! array_key_exists('headers', $r->data()));
});

it('records the sendbyte email id as the sent message id', function () {
    fakeSendByte(['id' => 'em_01abc', 'status' => 'queued']);

    $sent = Mail::to('amaka@halo.test')->send(new ReceiptMail);

    expect($sent->getMessageId())->toBe('em_01abc');
});

it('throws a transport exception when the api rejects the send', function () {
    fakeSendByte(['code' => 'domain_not_verified', 'message' => 'Verify your domain first.'], 403);

    Mail::to('amaka@halo.test')->send(new ReceiptMail);
})->throws(TransportException::class, 'Verify your domain first.');

it('throws when no api key is configured', function () {
    config()->set('mail.mailers.sendbyte', ['transport' => 'sendbyte']);
    config()->set('services.sendbyte.key', null);

    Mail::to('amaka@halo.test')->send(new ReceiptMail);
})->throws(SendByteTransportException::class);

it('falls back to the services.sendbyte.key config', function () {
    fakeSendByte();
    config()->set('mail.mailers.sendbyte', ['transport' => 'sendbyte']);
    config()->set('services.sendbyte.key', 'sk_live_services');

    Mail::to('amaka@halo.test')->send(new ReceiptMail);

    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk_live_services'));
});

it('honours a per-mailer key and base url override', function () {
    Http::fake(['*' => Http::response(['id' => 'em_01x'], 201)]);
    config()->set('mail.mailers.sendbyte', [
        'transport' => 'sendbyte',
        'key' => 'sk_test_mailer',
        'base_url' => 'https://eu.sendbyte.test',
    ]);

    Mail::to('amaka@halo.test')->send(new ReceiptMail);

    Http::assertSent(fn ($r) => $r->url() === 'https://eu.sendbyte.test/v1/emails'
        && $r->hasHeader('Authorization', 'Bearer sk_test_mailer'));
});

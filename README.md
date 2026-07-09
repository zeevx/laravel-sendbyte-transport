# Laravel SendByte Transport

A [SendByte](https://sendbyte.africa) mail transport for Laravel. Set `MAIL_MAILER=sendbyte` and your existing Mailables, Notifications, and `Mail::` calls deliver through SendByte's transactional email API, no code changes required.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/zeevx/laravel-sendbyte-transport.svg?style=flat-square)](https://packagist.org/packages/zeevx/laravel-sendbyte-transport)
[![Total Downloads](https://img.shields.io/packagist/dt/zeevx/laravel-sendbyte-transport.svg?style=flat-square)](https://packagist.org/packages/zeevx/laravel-sendbyte-transport)
[![License](https://img.shields.io/packagist/l/zeevx/laravel-sendbyte-transport.svg?style=flat-square)](LICENSE.md)

## Requirements

- PHP 8.1 through 8.4
- Laravel 9 through 13

## Installation

```bash
composer require zeevx/laravel-sendbyte-transport
```

Add a `sendbyte` mailer to `config/mail.php`:

```php
'mailers' => [
    // ...
    'sendbyte' => [
        'transport' => 'sendbyte',
        'key' => env('SENDBYTE_API_KEY'),
    ],
],
```

Then point your `.env` at it:

```dotenv
MAIL_MAILER=sendbyte
SENDBYTE_API_KEY=sk_live_your_key
MAIL_FROM_ADDRESS=receipts@yourdomain.com
MAIL_FROM_NAME="Your App"
```

If you prefer keeping credentials with your other third-party services, omit `key` from the mailer and set it in `config/services.php` instead:

```php
'sendbyte' => [
    'key' => env('SENDBYTE_API_KEY'),
],
```

The mailer accepts two optional settings:

```php
'sendbyte' => [
    'transport' => 'sendbyte',
    'key' => env('SENDBYTE_API_KEY'),
    'base_url' => env('SENDBYTE_BASE_URL', 'https://api.sendbyte.africa'),
    'timeout' => 30,
],
```

Your sending domain must be verified in your SendByte dashboard before live sends. Use an `sk_test_` sandbox key to exercise the full pipeline without delivering to real inboxes.

## Usage

Send mail exactly as you always do:

```php
Mail::to($user)->send(new OrderShipped($order));

$user->notify(new InvoicePaid($invoice));
```

Everything a Mailable carries maps onto SendByte's send API: HTML and plain-text bodies, `cc`, `bcc`, `reply-to`, attachments (base64-encoded, up to 10), and custom headers.

### Tags

Laravel's message tags become SendByte tags (up to 10), filterable in the dashboard and the List Emails endpoint:

```php
use Illuminate\Mail\Mailables\Envelope;

public function envelope(): Envelope
{
    return new Envelope(
        subject: 'Your receipt',
        tags: ['receipt', 'payment'],
    );
}
```

Message metadata is delivered as `X-Metadata-*` headers.

### Idempotent sends

SendByte deduplicates sends that share an idempotency key within 24 hours. Set one per message via the `X-SendByte-Idempotency-Key` header; the transport hoists it into the API's `idempotency_key` field:

```php
public function headers(): Headers
{
    return new Headers(text: [
        'X-SendByte-Idempotency-Key' => 'order-4421-receipt',
    ]);
}
```

### Message IDs

SendByte assigns the final `Message-ID` at delivery. The transport records the API's email id (`em_...`) as the sent message id, so you can correlate sends with dashboard entries and webhook events:

```php
$sent = Mail::to($user)->send(new OrderShipped($order));

$sent->getMessageId(); // "em_01j..."
```

### Failures

A rejected send (unverified domain, suppressed recipient, rate limit, validation error) throws a `Symfony\Component\Mailer\Exception\TransportException` whose message includes SendByte's error detail.

## Limits

Imposed by the SendByte API:

- Maximum 50 recipients per message
- Maximum 10 attachments and 10 tags per message
- `Message-ID` cannot be overridden; SendByte assigns it at delivery

## Testing your integration

The transport sends through Laravel's HTTP client, so `Http::fake()` works, and `Mail::fake()` behaves as usual since the transport is never invoked.

Run the package test suite (powered by [Pest](https://pestphp.com)):

```bash
composer test
composer lint
```

## Security

If you discover any security-related issues, please email adamsohiani@gmail.com
instead of using the issue tracker.

## Credits

- [Paul Adams](https://github.com/zeevx)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more information.

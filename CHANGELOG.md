# Changelog

All notable changes to `laravel-sendbyte-transport` will be documented in this file.

## 1.0.0 - 2026-07-09

Initial release.

- `sendbyte` mail transport for Laravel 9 through 13: set `MAIL_MAILER=sendbyte`
  and existing Mailables, Notifications and `Mail::` calls deliver through
  SendByte's `POST /v1/emails` API.
- Configured entirely through `config/mail.php` (`mail.mailers.sendbyte`),
  with a `services.sendbyte.key` fallback for the API key. Optional `base_url`
  and `timeout` settings.
- Maps HTML and plain-text bodies, `cc`, `bcc`, `reply-to`, base64-encoded
  attachments, and custom headers onto the API payload.
- Laravel message tags become SendByte `tags`; message metadata is sent as
  `X-Metadata-*` headers.
- An `X-SendByte-Idempotency-Key` header is hoisted into the API's
  `idempotency_key` field for safe retries.
- The SendByte email id is recorded as the sent message id.
- Failed sends throw `Symfony\Component\Mailer\Exception\TransportException`
  with SendByte's error message.

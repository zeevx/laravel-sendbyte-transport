<?php

declare(strict_types=1);

namespace Zeevx\SendByteTransport;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Exception\TransportException;

class SendByteTransport extends AbstractTransport
{
    /**
     * Custom header that is hoisted into the API's idempotency_key field.
     */
    public const IDEMPOTENCY_HEADER = 'X-SendByte-Idempotency-Key';

    /**
     * MIME headers that are represented by their own payload fields and must
     * not be duplicated in the custom headers map.
     */
    protected const STANDARD_HEADERS = [
        'from', 'to', 'cc', 'bcc', 'subject', 'reply-to', 'sender',
        'content-type', 'mime-version', 'date', 'message-id', 'return-path',
    ];

    public function __construct(
        protected string $apiKey,
        protected string $baseUrl,
        protected int $timeout
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $response = Http::timeout($this->timeout)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->post($this->baseUrl.'/v1/emails', $this->payload($email));

        if ($response->failed()) {
            throw new TransportException(
                'Request to the SendByte API failed: '
                .($response->json('message') ?? $response->body()),
                $response->status()
            );
        }

        $id = $response->json('id');

        if (is_string($id) && $id !== '') {
            $message->setMessageId($id);
        }
    }

    /**
     * Map the MIME email onto the POST /v1/emails request body.
     *
     * @return array<string, mixed>
     */
    protected function payload(Email $email): array
    {
        $payload = [
            'from' => $this->stringifySender($email->getFrom()[0]),
            'to' => $this->stringifyAddresses($email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        if ($email->getHtmlBody() !== null) {
            $payload['html'] = (string) $email->getHtmlBody();
        }

        if ($email->getTextBody() !== null) {
            $payload['text'] = (string) $email->getTextBody();
        }

        if ($email->getCc() !== []) {
            $payload['cc'] = $this->stringifyAddresses($email->getCc());
        }

        if ($email->getBcc() !== []) {
            $payload['bcc'] = $this->stringifyAddresses($email->getBcc());
        }

        if ($email->getReplyTo() !== []) {
            $payload['reply_to'] = $email->getReplyTo()[0]->getAddress();
        }

        return array_merge(
            $payload,
            $this->headerFields($email),
            $this->attachmentFields($email)
        );
    }

    /**
     * Collect custom headers, tags, metadata and the idempotency key.
     *
     * @return array<string, mixed>
     */
    protected function headerFields(Email $email): array
    {
        $fields = [];
        $headers = [];
        $tags = [];

        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();

                continue;
            }

            if ($header instanceof MetadataHeader) {
                $headers['X-Metadata-'.$header->getKey()] = $header->getValue();

                continue;
            }

            $name = $header->getName();

            if (in_array(strtolower($name), self::STANDARD_HEADERS, true)) {
                continue;
            }

            if (strcasecmp($name, self::IDEMPOTENCY_HEADER) === 0) {
                $fields['idempotency_key'] = $header->getBodyAsString();

                continue;
            }

            $headers[$name] = $header->getBodyAsString();
        }

        if ($headers !== []) {
            $fields['headers'] = $headers;
        }

        if ($tags !== []) {
            $fields['tags'] = $tags;
        }

        return $fields;
    }

    /**
     * Base64-encode the attachments the way the API expects.
     *
     * @return array<string, mixed>
     */
    protected function attachmentFields(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                'filename' => $attachment->getFilename() ?? 'attachment',
                'content' => base64_encode($attachment->getBody()),
                'content_type' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
            ];
        }

        return $attachments === [] ? [] : ['attachments' => $attachments];
    }

    /**
     * Format the sender as "Name <email>", keeping the display name when set.
     */
    protected function stringifySender(Address $address): string
    {
        return $address->getName() !== ''
            ? $address->getName().' <'.$address->getAddress().'>'
            : $address->getAddress();
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, string>
     */
    protected function stringifyAddresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $address->getAddress(), $addresses);
    }

    public function __toString(): string
    {
        return 'sendbyte';
    }
}

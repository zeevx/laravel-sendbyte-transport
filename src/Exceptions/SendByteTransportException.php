<?php

declare(strict_types=1);

namespace Zeevx\SendByteTransport\Exceptions;

use InvalidArgumentException;

class SendByteTransportException extends InvalidArgumentException
{
    public static function missingApiKey(): self
    {
        return new self(
            'No SendByte API key provided. Set the "key" option on the sendbyte '
            .'mailer in config/mail.php or set services.sendbyte.key.'
        );
    }
}

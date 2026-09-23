<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Источник Единого хаба временно недоступен. GM должен увидеть явный признак
 * неполных данных, а не «нули» вместо заявок и статистики.
 */
class HubSourceUnavailableException extends RuntimeException
{
    public function __construct(public readonly string $source, string $message = '')
    {
        parent::__construct($message !== ''
            ? $message
            : sprintf('Источник %s временно недоступен — данные неполные.', $source));
    }

    public function errorCode(): string
    {
        return 'source_unavailable';
    }
}

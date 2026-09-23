<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Конфликт действия GM над заявкой хаба (KP-Lead): финальный статус,
 * недопустимый переход или сбой записи в источник.
 */
class HubOrderConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?string $source = 'kp_lead',
        public readonly ?string $action = null,
    ) {
        parent::__construct($message);
    }

    public static function alreadyFinal(string $source = 'kp_lead'): self
    {
        return new self(
            'order_already_final',
            'Заявка уже закрыта или в финальном статусе.',
            $source,
        );
    }

    public static function invalidTransition(string $from, string $to, string $source = 'kp_lead'): self
    {
        return new self(
            'invalid_status_transition',
            sprintf('Нельзя перевести заявку из «%s» в «%s».', $from, $to),
            $source,
        );
    }

    public static function writeFailed(string $detail, string $source = 'kp_lead', ?string $action = null): self
    {
        return new self(
            'source_write_failed',
            $detail !== '' ? $detail : 'Не удалось записать статус в источник KP-Lead.',
            $source,
            $action,
        );
    }

    public static function writeNotConfigured(string $source = 'kp_lead'): self
    {
        return new self(
            'source_write_not_configured',
            'Запись в KP-Lead через Единое окно не настроена.',
            $source,
        );
    }
}

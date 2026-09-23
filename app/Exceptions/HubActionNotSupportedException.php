<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Действие GM над заявкой внешнего источника (KP-Lead), для которого в Lead Control
 * пока нет адаптера записи. Отдаём понятную 409, а не 500: заявку нельзя молча
 * провести через LC-обработчик, как будто она своя.
 */
class HubActionNotSupportedException extends RuntimeException
{
    public function __construct(
        public readonly string $source,
        public readonly string $action,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($source, $action));
    }

    public static function defaultMessage(string $source, string $action): string
    {
        return sprintf(
            'Действие «%s» по заявке источника %s выполняется в Едином окне: Lead Control пока не пишет в этот источник.',
            $action,
            $source
        );
    }

    public function errorCode(): string
    {
        return 'source_action_not_supported';
    }
}

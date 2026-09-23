<?php

namespace App\Desk\Adapters;

use App\Desk\Contracts\CrmAdapter;
use App\Models\CrmConnection;
use RuntimeException;

/**
 * Заглушка CRM2 (kp-lead-centre). Реализация HTTP-парсинга — следующий этап.
 */
class Crm2HttpAdapter implements CrmAdapter
{
    public function __construct(
        protected CrmConnection $connection
    ) {}

    public function authenticate(): void
    {
        throw new RuntimeException('CRM2 адаптер ещё не подключён (тестовая версия: только CRM1)');
    }

    public function fetchOrders(array $filters = []): array
    {
        $this->authenticate();

        return [];
    }

    public function fetchOrder(string $externalId): ?array
    {
        $this->authenticate();

        return null;
    }

    public function updateOrder(string $externalId, array $data): void
    {
        $this->authenticate();
    }

    public function closeOrder(string $externalId, array $documents = []): void
    {
        $this->authenticate();
    }

    public function getStatuses(): array
    {
        return [];
    }

    public function getCities(): array
    {
        return [];
    }
}

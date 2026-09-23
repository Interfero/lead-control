<?php

namespace App\Desk\Contracts;

/**
 * Унифицированный адаптер внешней CRM для Единого окна.
 *
 * @phpstan-type DeskOrderPayload array{
 *   external_id: string,
 *   city_id: int|null,
 *   raw_status: string,
 *   client_name: ?string,
 *   phone: ?string,
 *   address: ?string,
 *   description: ?string,
 *   master_name: ?string,
 *   total_amount: ?int,
 *   paid_amount: ?int,
 *   parts_amount: ?int,
 *   created_at_local: ?string,
 *   call_at_local: ?string,
 *   timezone: ?string,
 *   updated_at_local: ?string,
 *   order_type: ?string,
 *   comments: ?string,
 *   documents: array,
 *   priority: int,
 *   row_highlight: ?string,
 *   hash: string
 * }
 */
interface CrmAdapter
{
    public function authenticate(): void;

    /**
     * @param  array{updated_after?: ?\DateTimeInterface, full?: bool}  $filters
     * @return list<DeskOrderPayload>
     */
    public function fetchOrders(array $filters = []): array;

    /**
     * @return DeskOrderPayload|null
     */
    public function fetchOrder(string $externalId): ?array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateOrder(string $externalId, array $data): void;

    /**
     * @param  array<string, mixed>  $documents
     */
    public function closeOrder(string $externalId, array $documents = []): void;

    /**
     * @return array<string, string> code => label
     */
    public function getStatuses(): array;

    /**
     * @return array<int, string> city_id => name
     */
    public function getCities(): array;
}

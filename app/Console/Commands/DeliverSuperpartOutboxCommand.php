<?php

namespace App\Console\Commands;

use App\Services\SuperpartOutboxDeliverer;
use Illuminate\Console\Command;

class DeliverSuperpartOutboxCommand extends Command
{
    protected $signature = 'superpart:deliver-outbox {--limit=80}';

    protected $description = 'Доставить pending события superpart_outbox в SuperPart';

    public function handle(SuperpartOutboxDeliverer $deliverer): int
    {
        $stats = $deliverer->deliverPending((int) $this->option('limit'));
        $this->info('sent='.$stats['sent'].' failed='.$stats['failed']);

        return self::SUCCESS;
    }
}

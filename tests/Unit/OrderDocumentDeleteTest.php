<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderDocumentDeleteTest extends TestCase
{
    public function test_photo_is_deletable_after_order_close(): void
    {
        $order = new Order;
        $doc = new Document;

        $this->assertTrue($doc->isDeletableOnOrder($order));
    }
}

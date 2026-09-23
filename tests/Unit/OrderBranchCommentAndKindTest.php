<?php

namespace Tests\Unit;

use App\Models\Order;
use Tests\TestCase;

class OrderBranchCommentAndKindTest extends TestCase
{
    public function test_order_kind_maps_crm_new_to_first(): void
    {
        $order = new Order(['order_type' => 'new']);
        $this->assertSame('first', $order->orderKind());

        $order->order_type = 'warranty';
        $this->assertSame('warranty', $order->orderKind());

        $order->order_type = 'repeat';
        $this->assertSame('repeat', $order->orderKind());
    }

    public function test_append_and_extract_branch_comment_blocks(): void
    {
        $order = new Order(['city_adds' => 'Служебная пометка КЦ']);
        $order->appendBranchCommentBlock(Order::BRANCH_COMMENT_SD_HEADING, 'Нужна сохранка');
        $order->appendBranchCommentBlock(Order::BRANCH_COMMENT_CLOSE_HEADING, 'Клиент доволен');

        $this->assertSame('Нужна сохранка', $order->extractBranchCommentBlock(Order::BRANCH_COMMENT_SD_HEADING));
        $this->assertSame('Клиент доволен', $order->extractBranchCommentBlock(Order::BRANCH_COMMENT_CLOSE_HEADING));
        $this->assertStringContainsString('Служебная пометка КЦ', (string) $order->city_adds);
        $this->assertStringContainsString(Order::BRANCH_COMMENT_SD_HEADING, (string) $order->city_adds);
    }
}

<?php

namespace Tests\Unit;

use App\Services\DirectorSalaryService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DirectorSalaryServiceTest extends TestCase
{
    private DirectorSalaryService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DirectorSalaryService;
    }

    #[DataProvider('rateCases')]
    public function test_rate_and_commission(int $base, int $expectedRate, int $expectedCommission): void
    {
        $this->assertSame($expectedRate, $this->svc->ratePercentForBase($base));
        $this->assertSame($expectedCommission, $this->svc->commissionFromBase($base));
    }

    public static function rateCases(): array
    {
        return [
            'T2 100000 → 15%' => [100_000, 15, 15_000],
            'T3 100001 → 20%' => [100_001, 20, 20_000],
            'T4 250000 → 20%' => [250_000, 20, 50_000],
            'T5 250001 → 25%' => [250_001, 25, 62_500],
            'T6 350000 → 25%' => [350_000, 25, 87_500],
            'T7 400000 → 25%' => [400_000, 25, 100_000],
            'T8 NN example commission' => [237_650, 20, 47_530],
            'T26 half-up 250002' => [250_002, 25, 62_501],
            'T27 half-up 100003' => [100_003, 20, 20_001],
            'T28 two ops 5+5' => [10, 15, 2],
            'T29 200001' => [200_001, 20, 40_000],
            'T17 zero' => [0, 15, 0],
        ];
    }

    public function test_satellite_rate_10_percent(): void
    {
        $this->assertSame(10, $this->svc->ratePercentForBase(50_000, true));
        $this->assertSame(5_000, $this->svc->commissionFromBase(50_000, null, true));
        $this->assertSame(10_000, $this->svc->commissionFromBase(100_000, null, true));
        // материнский город при той же базе — 15%
        $this->assertSame(15_000, $this->svc->commissionFromBase(100_000, null, false));
    }

    public function test_nn_and_satellite_examples(): void
    {
        // НН: 100к → 15к + оклад 50к = 65к доступно
        $nnCommission = $this->svc->commissionFromBase(100_000, null, false);
        $this->assertSame(15_000, $nnCommission);
        $this->assertSame(65_000, $nnCommission + 50_000);

        // Дзержинск: 50к → 5к (оклад выключен)
        $this->assertSame(5_000, $this->svc->commissionFromBase(50_000, null, true));
    }

    public function test_nn_control_example_accrued(): void
    {
        $commission = $this->svc->commissionFromBase(237_650);
        $accrued = $commission + 50_000;
        $this->assertSame(97_530, $accrued);
        $this->assertSame(67_530, $accrued - 30_000);
    }

    public function test_format_money_no_kopecks(): void
    {
        $this->assertSame('97 530 ₽', $this->svc->formatMoney(97_530));
        $this->assertSame('0 ₽', $this->svc->formatMoney(0));
    }
}

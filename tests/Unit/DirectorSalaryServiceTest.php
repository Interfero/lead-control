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
            'T4 200000 → 20%' => [200_000, 20, 40_000],
            'T5 200001 → 25%' => [200_001, 25, 50_000],
            'T5b 250000 → 25%' => [250_000, 25, 62_500],
            'T6 350000 → 25%' => [350_000, 25, 87_500],
            'T7 400000 → 25%' => [400_000, 25, 100_000],
            'T8 NN example commission' => [237_650, 25, 59_413],
            'T26 half-up 250002' => [250_002, 25, 62_501],
            'T27 half-up 100003' => [100_003, 20, 20_001],
            'T28 two ops 5+5' => [10, 15, 2],
                        'T17 zero' => [0, 15, 0],
        ];
    }

    public function test_pool_rate_applies_to_each_city_own_incas(): void
    {
        // Курск 200к + Железногорск 60к = котёл 260к → 25%
        $rate = $this->svc->ratePercentForBase(260_000);
        $this->assertSame(25, $rate);
        $this->assertSame(50_000, $this->svc->commissionFromBase(200_000, $rate));
        $this->assertSame(15_000, $this->svc->commissionFromBase(60_000, $rate));
    }

    public function test_nn_control_example_accrued(): void
    {
        $commission = $this->svc->commissionFromBase(237_650);
        $accrued = $commission + 50_000;
        $this->assertSame(109_413, $accrued);
        $this->assertSame(79_413, $accrued - 30_000);
    }

    public function test_format_money_no_kopecks(): void
    {
        $this->assertSame('97 530 ₽', $this->svc->formatMoney(97_530));
        $this->assertSame('0 ₽', $this->svc->formatMoney(0));
    }
}

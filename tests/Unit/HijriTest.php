<?php

namespace Tests\Unit;

use App\Support\Hijri;
use PHPUnit\Framework\TestCase;

class HijriTest extends TestCase
{
    public function test_converts_gregorian_to_umm_al_qura_and_back(): void
    {
        $this->assertSame([1448, 5, 4], Hijri::fromGregorian('2026-10-15'));
        $this->assertSame('2026-10-15', Hijri::toGregorian(1448, 5, 4)->toDateString());
        $this->assertSame('1448/05/04', Hijri::toString('2026-10-15'));
        $this->assertSame('2026-10-15', Hijri::parse('1448/05/04')->toDateString());
        $this->assertSame([1447, 9, 1], Hijri::fromGregorian('2026-02-18')); // 1 Ramadan 1447
    }

    public function test_adds_hijri_months_across_year_boundary(): void
    {
        $this->assertSame('1449/05/04', Hijri::toString(Hijri::addMonths('2026-10-15', 12)));
        $this->assertSame('1449/02/04', Hijri::toString(Hijri::addMonths('2026-10-15', 9)));
    }

    public function test_clamps_day_when_target_month_is_shorter(): void
    {
        $this->assertSame('1448/06/29', Hijri::toString(Hijri::addMonths(Hijri::toGregorian(1448, 5, 30), 1)));
    }

    public function test_formats_in_arabic(): void
    {
        $this->assertStringContainsString('جمادى الأولى', Hijri::format('2026-10-15'));
    }
}

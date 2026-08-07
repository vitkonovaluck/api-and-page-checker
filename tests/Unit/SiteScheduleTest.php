<?php

namespace Tests\Unit;

use App\Models\Site;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SiteScheduleTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_slot_start_aligns_to_fifteen_minute_boundaries(): void
    {
        $site = new Site(['schedule_interval' => '15m']);

        $this->assertTrue(
            $site->currentScheduleSlotStart(Carbon::parse('2026-08-07 10:00:30'))
                ->equalTo(Carbon::parse('2026-08-07 10:00:00'))
        );
        $this->assertTrue(
            $site->currentScheduleSlotStart(Carbon::parse('2026-08-07 10:14:59'))
                ->equalTo(Carbon::parse('2026-08-07 10:00:00'))
        );
        $this->assertTrue(
            $site->currentScheduleSlotStart(Carbon::parse('2026-08-07 10:15:00'))
                ->equalTo(Carbon::parse('2026-08-07 10:15:00'))
        );
        $this->assertTrue(
            $site->currentScheduleSlotStart(Carbon::parse('2026-08-07 10:44:10'))
                ->equalTo(Carbon::parse('2026-08-07 10:30:00'))
        );
    }

    public function test_slot_start_aligns_hourly_and_daily_intervals(): void
    {
        $hourly = new Site(['schedule_interval' => '1h']);
        $sixHourly = new Site(['schedule_interval' => '6h']);
        $daily = new Site(['schedule_interval' => '1d']);

        $this->assertTrue(
            $hourly->currentScheduleSlotStart(Carbon::parse('2026-08-07 10:37:00'))
                ->equalTo(Carbon::parse('2026-08-07 10:00:00'))
        );
        $this->assertTrue(
            $sixHourly->currentScheduleSlotStart(Carbon::parse('2026-08-07 14:20:00'))
                ->equalTo(Carbon::parse('2026-08-07 12:00:00'))
        );
        $this->assertTrue(
            $daily->currentScheduleSlotStart(Carbon::parse('2026-08-07 23:59:00'))
                ->equalTo(Carbon::parse('2026-08-07 00:00:00'))
        );
    }

    public function test_never_run_site_is_due_only_on_slot_boundary(): void
    {
        $site = new Site([
            'schedule_enabled' => true,
            'schedule_interval' => '15m',
            'schedule_last_run_at' => null,
        ]);

        $this->assertFalse($site->isDueForScheduledCheck(Carbon::parse('2026-08-07 10:07:00')));
        $this->assertTrue($site->isDueForScheduledCheck(Carbon::parse('2026-08-07 10:15:00')));
        $this->assertTrue($site->isDueForScheduledCheck(Carbon::parse('2026-08-07 10:15:42')));
    }

    public function test_site_is_due_once_per_new_slot_including_catch_up(): void
    {
        $site = new Site([
            'schedule_enabled' => true,
            'schedule_interval' => '15m',
            'schedule_last_run_at' => Carbon::parse('2026-08-07 10:00:05'),
        ]);

        $this->assertFalse($site->isDueForScheduledCheck(Carbon::parse('2026-08-07 10:14:00')));
        $this->assertTrue($site->isDueForScheduledCheck(Carbon::parse('2026-08-07 10:15:00')));
        $this->assertTrue($site->isDueForScheduledCheck(Carbon::parse('2026-08-07 10:22:00')));
    }
}

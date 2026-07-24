<?php

namespace Tests\Unit\Erp;

use App\Models\ErpSyncRun;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DispatchDailyErpSyncsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_uses_a_rolling_lookback_for_incremental_daily_syncs(): void
    {
        Bus::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-24 10:00:00', 'Europe/Rome'));

        $this->artisan('erp:dispatch-daily-syncs')
            ->assertExitCode(0);

        $runs = ErpSyncRun::query()->orderBy('id')->get()->keyBy('command_key');

        $this->assertSame('2026-07-17', $runs['products']->params_json['--since'] ?? null);
        $this->assertSame('2026-07-17', $runs['media']->params_json['--since'] ?? null);
        $this->assertSame('2026-07-17', $runs['customers']->params_json['--since'] ?? null);
        $this->assertArrayNotHasKey('--since', $runs['product_attribute_values']->params_json);
        $this->assertArrayNotHasKey('--since', $runs['product_comparisons']->params_json);
    }

    public function test_explicit_since_overrides_lookback_days(): void
    {
        Bus::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-24 10:00:00', 'Europe/Rome'));

        $this->artisan('erp:dispatch-daily-syncs --since=2026-07-01 --lookback-days=3')
            ->assertExitCode(0);

        $runs = ErpSyncRun::query()->get()->keyBy('command_key');

        $this->assertSame('2026-07-01', $runs['products']->params_json['--since'] ?? null);
        $this->assertSame('2026-07-01', $runs['media']->params_json['--since'] ?? null);
        $this->assertArrayNotHasKey('--since', $runs['product_attribute_values']->params_json);
    }
}

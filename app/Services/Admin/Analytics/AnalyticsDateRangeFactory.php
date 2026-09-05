<?php

namespace App\Services\Admin\Analytics;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class AnalyticsDateRangeFactory
{
    public const PRESETS = [
        'today' => 'Oggi',
        'yesterday' => 'Ieri',
        'last_7_days' => 'Ultimi 7 giorni',
        'last_30_days' => 'Ultimi 30 giorni',
        'current_month' => 'Mese corrente',
        'current_year' => 'Anno corrente',
        'custom' => 'Personalizzato',
    ];

    public function fromRequest(Request $request): AnalyticsDateRange
    {
        $preset = (string) $request->query('period', 'last_30_days');
        $preset = array_key_exists($preset, self::PRESETS) ? $preset : 'last_30_days';
        $now = now('Europe/Rome');

        [$start, $end] = match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'current_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'current_year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            'custom' => $this->customRange($request, $now),
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $days = (int) $start->diffInDays($end) + 1;
        $previousEnd = $start->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subDays($days - 1)->startOfDay();

        return new AnalyticsDateRange(
            preset: $preset,
            start: $start,
            end: $end,
            previousStart: $previousStart,
            previousEnd: $previousEnd,
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function customRange(Request $request, Carbon $now): array
    {
        $start = $this->parseDate((string) $request->query('date_from', ''), $now->copy()->subDays(29));
        $end = $this->parseDate((string) $request->query('date_to', ''), $now);

        return [$start->startOfDay(), $end->endOfDay()];
    }

    private function parseDate(string $value, Carbon $fallback): Carbon
    {
        try {
            return filled($value)
                ? Carbon::createFromFormat('Y-m-d', $value, 'Europe/Rome')
                : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}

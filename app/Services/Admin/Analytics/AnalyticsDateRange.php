<?php

namespace App\Services\Admin\Analytics;

use Illuminate\Support\Carbon;

final class AnalyticsDateRange
{
    public function __construct(
        public readonly string $preset,
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly ?Carbon $previousStart = null,
        public readonly ?Carbon $previousEnd = null,
    ) {}

    public function label(): string
    {
        return $this->start->isSameDay($this->end)
            ? $this->start->format('d/m/Y')
            : $this->start->format('d/m/Y').' - '.$this->end->format('d/m/Y');
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    public function chartGranularity(): string
    {
        return match (true) {
            $this->days() <= 45 => 'day',
            $this->days() <= 180 => 'week',
            default => 'month',
        };
    }

    public function hasPreviousPeriod(): bool
    {
        return $this->previousStart !== null && $this->previousEnd !== null;
    }
}

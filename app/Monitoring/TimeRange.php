<?php

namespace App\Monitoring;

/**
 * A period of recent history shown on a service's overview.
 */
enum TimeRange: string
{
    case Day = '24h';
    case Week = '7d';
    case Month = '30d';

    /**
     * Get the human readable label for the range.
     */
    public function label(): string
    {
        return match ($this) {
            self::Day => 'Last 24 hours',
            self::Week => 'Last 7 days',
            self::Month => 'Last 30 days',
        };
    }

    /**
     * Get the length of the range in seconds.
     */
    public function seconds(): int
    {
        return match ($this) {
            self::Day => 86_400,
            self::Week => 7 * 86_400,
            self::Month => 30 * 86_400,
        };
    }

    /**
     * Get the width of each point on the range's charts in seconds, giving 84-120 points.
     */
    public function bucketSeconds(): int
    {
        return match ($this) {
            self::Day => 15 * 60,
            self::Week => 2 * 3600,
            self::Month => 6 * 3600,
        };
    }

    /**
     * Get every range as options for the UI.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $range): array => [
            'value' => $range->value,
            'label' => $range->label(),
        ], self::cases());
    }
}

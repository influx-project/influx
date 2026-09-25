<?php

namespace App\Enums;

enum ServiceImportance: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Get the human readable label for the importance level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    /**
     * Get the level's position from least (1) to most (4) important, used for sorting.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Normal => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    /**
     * Get every level as options for the UI.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $importance): array => [
            'value' => $importance->value,
            'label' => $importance->label(),
        ], self::cases());
    }
}

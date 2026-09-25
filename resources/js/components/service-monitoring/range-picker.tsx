import { router } from '@inertiajs/react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { TimeRangeOption, TimeRangeValue } from '@/types';

const shortLabels: Record<TimeRangeValue, string> = {
    '24h': '24h',
    '7d': '7d',
    '30d': '30d',
};

/**
 * Switches the period a tab's numbers and charts cover.
 */
export function RangePicker({
    value,
    options,
    only = ['range', 'overview', 'status'],
}: {
    value: TimeRangeValue;
    options: TimeRangeOption[];
    /** The props to reload for the new range. */
    only?: string[];
}) {
    return (
        <ToggleGroup
            type="single"
            variant="outline"
            size="sm"
            value={value}
            onValueChange={(range) => {
                if (range && range !== value) {
                    router.reload({
                        data: { range },
                        only,
                    });
                }
            }}
            aria-label="Time range"
        >
            {options.map((option) => (
                <ToggleGroupItem
                    key={option.value}
                    value={option.value}
                    aria-label={option.label}
                    className="px-3"
                >
                    {shortLabels[option.value]}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}

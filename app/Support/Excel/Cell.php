<?php

namespace App\Support\Excel;

use Illuminate\Support\Carbon;

/**
 * Cleans raw Excel cell text into typed values.
 * Handles the mixed formats in the client's sheets:
 * "11.09.2020", "29/08/2022", "up to 31.01.2028", Excel serial numbers, "NA", "Not applied".
 */
class Cell
{
    private const EMPTY_MARKERS = ['', 'na', 'n/a', 'nil', '-', '--'];

    public static function text(?string $value): ?string
    {
        $value = trim(preg_replace('/[ \t]+/', ' ', (string) $value));

        return $value === '' ? null : $value;
    }

    /** Multi-line text cell, newlines kept, extra spaces removed. */
    public static function multiline(?string $value): ?string
    {
        $lines = array_filter(array_map(
            fn ($line) => trim(preg_replace('/[ \t]+/', ' ', $line)),
            preg_split('/\R/', (string) $value)
        ), fn ($line) => $line !== '');

        return $lines ? implode("\n", $lines) : null;
    }

    public static function isEmpty(?string $value): bool
    {
        return in_array(strtolower(trim((string) $value)), self::EMPTY_MARKERS, true);
    }

    public static function number(?string $value): ?float
    {
        if (self::isEmpty($value)) {
            return null;
        }

        $clean = str_replace([',', ' '], '', trim((string) $value));

        return is_numeric($clean) ? round((float) $clean, 4) : null;
    }

    public static function integer(?string $value): ?int
    {
        $number = self::number($value);

        return $number === null ? null : (int) round($number);
    }

    /** First valid date found in the text, or null. */
    public static function date(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        if (self::isEmpty($value)) {
            return null;
        }

        // Excel stores some dates as serial numbers (days since 1899-12-30)
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->startOfDay();
        }

        if (preg_match('/(\d{1,2})\s*[.\/-]\s*(\d{1,2})\s*[.\/-]\s*(\d{4})/', $value, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];

            if (checkdate($month, $day, $year)) {
                return Carbon::create($year, $month, $day)->startOfDay();
            }
        }

        return null;
    }

    /** True when the cell has text but no readable date (e.g. "31.0.2023" or "Not applied"). */
    public static function hasUnreadableDate(?string $value): bool
    {
        return ! self::isEmpty($value) && self::date($value) === null;
    }
}

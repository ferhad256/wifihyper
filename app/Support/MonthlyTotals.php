<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Month-by-month totals, without tying the query to MySQL.
 *
 * The dashboards grouped with DATE_FORMAT(created_at, "%Y-%m"), which is
 * MySQL-only. That is not merely a portability nicety here: it meant
 * /dashboard and /admin threw on sqlite, so the two most important pages in
 * the product could not be covered by a feature test at all - CI runs on
 * sqlite. Every attempt to test them died on "no such function: DATE_FORMAT".
 */
class MonthlyTotals
{
    /**
     * The driver's own way of formatting a date column as YYYY-MM.
     */
    public static function monthExpression(string $column = 'created_at'): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            'sqlsrv' => "FORMAT({$column}, 'yyyy-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * The driver's own way of truncating a datetime column to a date.
     */
    public static function dayExpression(string $column = 'created_at'): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "date({$column})",
            'pgsql' => "{$column}::date",
            'sqlsrv' => "CAST({$column} AS date)",
            default => "DATE({$column})",
        };
    }

    /**
     * Sum a column per month for one calendar year, with every month present.
     *
     * Missing months come back as 0 rather than being absent, so a chart has
     * twelve points whether or not there were sales in February.
     *
     * @param  EloquentBuilder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     * @return Collection<string, float> keyed YYYY-MM, in calendar order
     */
    public static function sumByMonth(
        EloquentBuilder|QueryBuilder $query,
        string $sumColumn,
        int $year,
        string $dateColumn = 'created_at',
    ): Collection {
        $expression = self::monthExpression($dateColumn);

        $rows = $query
            ->whereYear($dateColumn, $year)
            ->selectRaw("{$expression} as month, SUM({$sumColumn}) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        return self::everyMonthOf($year)
            ->mapWithKeys(fn (string $month): array => [
                $month => (float) ($rows[$month] ?? 0),
            ]);
    }

    /**
     * Count rows per month for one calendar year, with every month present.
     *
     * @param  EloquentBuilder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     * @return Collection<string, int>
     */
    public static function countByMonth(
        EloquentBuilder|QueryBuilder $query,
        int $year,
        string $dateColumn = 'created_at',
    ): Collection {
        $expression = self::monthExpression($dateColumn);

        $rows = $query
            ->whereYear($dateColumn, $year)
            ->selectRaw("{$expression} as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        return self::everyMonthOf($year)
            ->mapWithKeys(fn (string $month): array => [
                $month => (int) ($rows[$month] ?? 0),
            ]);
    }

    /**
     * @return Collection<int, string> ['2026-01', ... '2026-12']
     */
    public static function everyMonthOf(int $year): Collection
    {
        return collect(range(1, 12))
            ->map(fn (int $month): string => sprintf('%04d-%02d', $year, $month));
    }

    /**
     * Short month labels for a chart axis: Jan, Feb, ...
     *
     * @param  Collection<string, mixed>  $byMonth
     * @return array<int, string>
     */
    public static function labels(Collection $byMonth): array
    {
        return $byMonth->keys()
            ->map(fn (string $month): string => date('M', strtotime($month . '-01')))
            ->all();
    }
}

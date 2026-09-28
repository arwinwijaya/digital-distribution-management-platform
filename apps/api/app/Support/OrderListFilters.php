<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Validate, normalize, and apply the additive filters for the admin order list.
 */
class OrderListFilters
{
    private const ALLOWED_QUERY_KEYS = [
        'limit', 'cursor', 'sort', 'order', 'outlet_id', 'status', 'start', 'end',
    ];

    private const CANONICAL_STATUSES = ['New', 'Confirmed', 'Delivered', 'Partially Paid'];

    private const MAX_FILTER_RANGE_DAYS = 90;

    /**
     * @return array{errors: array<string,string>, filters: array<string,mixed>}
     */
    public function resolve(Request $request): array
    {
        $errors = $this->unknownQueryErrors($request);
        $filters = [];

        $this->validateOutletId($request, $errors, $filters);
        $this->validateStatus($request, $errors, $filters);
        $this->validateDates($request, $errors, $filters);

        return ['errors' => $errors, 'filters' => $filters];
    }

    /**
     * @param array{outlet_id?:int,statuses?:array<string>,start?:string,end?:string} $filters
     */
    public function apply(Builder $query, array $filters): void
    {
        if (isset($filters['outlet_id'])) {
            $query->where('outlet_id', $filters['outlet_id']);
        }

        if (isset($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }

        if (isset($filters['start'])) {
            $query->where('created_at', '>=', $filters['start'].' 00:00:00');
        }

        if (isset($filters['end'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end'])->endOfDay());
        }
    }

    /** @return array<string,string> */
    private function unknownQueryErrors(Request $request): array
    {
        $errors = [];
        foreach (array_keys($request->query()) as $key) {
            if (! in_array($key, self::ALLOWED_QUERY_KEYS, true)) {
                $errors[$key] = 'Unknown query parameter';
            }
        }

        return $errors;
    }

    /** @param array<string,string> $errors @param array<string,mixed> $filters */
    private function validateOutletId(Request $request, array &$errors, array &$filters): void
    {
        if ($request->query('outlet_id') === null) {
            return;
        }

        $raw = ListQuery::scalarString($request, 'outlet_id', '');
        if (! preg_match('/^[1-9][0-9]*$/', $raw)) {
            $errors['outlet_id'] = 'Must be a positive integer';
            return;
        }

        $filters['outlet_id'] = (int) $raw;
    }

    /** @param array<string,string> $errors @param array<string,mixed> $filters */
    private function validateStatus(Request $request, array &$errors, array &$filters): void
    {
        if ($request->query('status') === null) {
            return;
        }

        $statuses = $this->parseStatuses(ListQuery::scalarString($request, 'status', ''));
        if ($statuses === null) {
            $errors['status'] = 'Status must be one of: New,Confirmed,Delivered,Partially Paid';
            return;
        }

        $filters['statuses'] = $statuses;
    }

    /** @param array<string,string> $errors @param array<string,mixed> $filters */
    private function validateDates(Request $request, array &$errors, array &$filters): void
    {
        $start = $this->queryDate($request, 'start');
        $end = $this->queryDate($request, 'end');
        $startDate = $start === null ? null : $this->parseDate($start);
        $endDate = $end === null ? null : $this->parseDate($end);

        if ($start !== null && $startDate === false) {
            $errors['start'] = 'Must be a valid date in YYYY-MM-DD format';
        }
        if ($end !== null && $endDate === false) {
            $errors['end'] = 'Must be a valid date in YYYY-MM-DD format';
        }

        if (is_string($startDate) && is_string($endDate)) {
            $this->validateDateRange($startDate, $endDate, $errors);
        }
        if (is_string($startDate)) {
            $filters['start'] = $startDate;
        }
        if (is_string($endDate)) {
            $filters['end'] = $endDate;
        }
    }

    private function queryDate(Request $request, string $key): ?string
    {
        return $request->query($key) === null ? null : ListQuery::scalarString($request, $key, '');
    }

    /** @param array<string,string> $errors */
    private function validateDateRange(string $start, string $end, array &$errors): void
    {
        if ($start > $end) {
            $errors['start'] = 'Start date must be before or equal to end date';
            return;
        }

        $diffDays = (new \DateTimeImmutable($start))->diff(new \DateTimeImmutable($end))->days;
        if ($diffDays + 1 > self::MAX_FILTER_RANGE_DAYS) {
            $errors['end'] = 'Date range must not exceed 90 days';
        }
    }

    private function parseStatuses(string $raw): ?array
    {
        $parts = explode(',', $raw);
        if (in_array('', $parts, true)) {
            return null;
        }
        foreach ($parts as $part) {
            if (! in_array($part, self::CANONICAL_STATUSES, true)) {
                return null;
            }
        }

        return array_values(array_unique($parts));
    }

    private function parseDate(string $value): string|bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$year, $month, $day] = explode('-', $value);

        return checkdate((int) $month, (int) $day, (int) $year) ? $value : false;
    }
}

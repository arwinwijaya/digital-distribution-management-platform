<?php

namespace App\Services;

use App\Models\DataPipelineRun;
use App\Models\InvoiceReminder;
use App\Models\OperationalEvent;
use App\Models\WhatsAppMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class OperationalIssueService
{
    private const SECRET_KEYS = [
        'phone',
        'token',
        'password',
        'credential',
        'secret',
        'authorization',
        'bearer',
        'provider_message_id',
        'raw',
        'payload',
        'body',
        'card',
        'payment',
    ];

    public function list(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 25)));

        $source = $filters['source'] ?? null;
        $status = $filters['status'] ?? null;
        $severity = $filters['severity'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $correlationId = $filters['correlation_id'] ?? null;

        $all = $this->collectAll($source, $status, $severity, $from, $to, $correlationId);

        $total = count($all);

        if ($correlationId !== null) {
            usort($all, fn ($a, $b) => strcmp((string) $a['occurred_at'], (string) $b['occurred_at']));
        } else {
            usort($all, fn ($a, $b) => strcmp((string) $b['occurred_at'], (string) $a['occurred_at']));
        }

        $offset = ($page - 1) * $limit;
        $pageItems = array_slice($all, $offset, $limit);
        $hasMore = ($offset + count($pageItems)) < $total;

        return [
            'data' => array_values($pageItems),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => $hasMore,
            ],
        ];
    }

    public function findById(int $id): ?array
    {
        foreach ($this->collectAll() as $issue) {
            if ((int) $issue['id'] === $id) {
                $detail = $this->redactedDetail($issue);
                $issue['detail'] = $detail;

                return $issue;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectAll(?string $source = null, ?string $status = null, ?string $severity = null, ?string $from = null, ?string $to = null, ?string $correlationId = null): array
    {
        $issues = [];

        $fromTs = $from !== null ? Carbon::parse($from) : null;
        $toTs = $to !== null ? Carbon::parse($to) : null;

        if ($source === null || $source === 'whatsapp') {
            foreach ($this->whatsappIssues() as $issue) {
                if ($correlationId !== null && ($issue['correlation_id'] ?? null) !== $correlationId) {
                    continue;
                }
                if (! $this->passesFilters($issue, $status, $severity, $fromTs, $toTs)) {
                    continue;
                }
                $issues[] = $issue;
            }
        }

        if ($source === null || $source === 'reminder') {
            foreach ($this->reminderIssues() as $issue) {
                if ($correlationId !== null && ($issue['correlation_id'] ?? null) !== $correlationId) {
                    continue;
                }
                if (! $this->passesFilters($issue, $status, $severity, $fromTs, $toTs)) {
                    continue;
                }
                $issues[] = $issue;
            }
        }

        if ($source === null || $source === 'pipeline') {
            foreach ($this->pipelineIssues() as $issue) {
                if ($correlationId !== null && ($issue['correlation_id'] ?? null) !== $correlationId) {
                    continue;
                }
                if (! $this->passesFilters($issue, $status, $severity, $fromTs, $toTs)) {
                    continue;
                }
                $issues[] = $issue;
            }
        }

        if ($source === null || $source === 'operational_event') {
            foreach ($this->operationalEventIssues() as $issue) {
                if ($correlationId !== null && ($issue['correlation_id'] ?? null) !== $correlationId) {
                    continue;
                }
                if (! $this->passesFilters($issue, $status, $severity, $fromTs, $toTs)) {
                    continue;
                }
                $issues[] = $issue;
            }
        }

        return $issues;
    }

    private function passesFilters(array $issue, ?string $status, ?string $severity, ?Carbon $from, ?Carbon $to): bool
    {
        if ($status !== null && $issue['status'] !== $status) {
            return false;
        }
        if ($severity !== null && $issue['severity'] !== $severity) {
            return false;
        }
        $occurred = isset($issue['occurred_at']) ? Carbon::parse($issue['occurred_at']) : null;
        if ($from !== null && $occurred !== null && $occurred->lt($from)) {
            return false;
        }
        if ($to !== null && $occurred !== null && $occurred->gt($to)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function whatsappIssues(): array
    {
        $rows = WhatsAppMessage::query()
            ->whereIn('status', ['failed', 'pending', 'sent', 'sending'])
            ->whereIn('direction', ['outbound', 'inbound'])
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $issues = [];
        foreach ($rows as $row) {
            $isFailed = $row->status === 'failed';
            $issues[] = [
                'id' => (int) $row->id,
                'source' => 'whatsapp',
                'reference' => 'WhatsAppMessage#'.$row->id,
                'status' => (string) $row->status,
                'severity' => $isFailed ? 'warning' : 'info',
                'attempts' => (int) ($row->attempts ?? 0),
                'occurred_at' => optional($row->updated_at ?? $row->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                'error_class' => $row->error !== null ? 'WhatsappFailure' : null,
                'correlation_id' => null,
                'next_action' => $isFailed ? 'retry using /api/whatsapp/messages/'.$row->id.'/retry' : 'no_action',
            ];
        }

        return $issues;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reminderIssues(): array
    {
        $rows = InvoiceReminder::query()
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $issues = [];
        foreach ($rows as $row) {
            $isFailed = $row->status === InvoiceReminder::FAILED;
            $issues[] = [
                'id' => (int) $row->id + 100000,
                'source' => 'reminder',
                'reference' => 'InvoiceReminder#'.$row->id,
                'status' => (string) $row->status,
                'severity' => $isFailed ? 'warning' : 'info',
                'attempts' => (int) ($row->attempts ?? 0),
                'occurred_at' => optional($row->failed_at ?? $row->sent_at ?? $row->updated_at ?? $row->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                'error_class' => $row->last_error !== null ? 'ReminderFailure' : null,
                'correlation_id' => null,
                'next_action' => $isFailed ? 'inspect invoice reminder and check WhatsApp delivery' : 'no_action',
            ];
        }

        return $issues;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pipelineIssues(): array
    {
        $rows = DataPipelineRun::query()
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $issues = [];
        foreach ($rows as $row) {
            $isFailed = $row->status === 'failed';
            $issues[] = [
                'id' => (int) $row->id + 200000,
                'source' => 'pipeline',
                'reference' => 'DataPipelineRun#'.$row->id.' ('.$row->run_uuid.')',
                'status' => (string) $row->status,
                'severity' => $isFailed ? 'critical' : 'info',
                'attempts' => 1,
                'occurred_at' => optional($row->updated_at ?? $row->created_at)?->toIso8601String() ?? now()->toIso8601String(),
                'error_class' => $row->error_message !== null ? 'PipelineFailure' : null,
                'correlation_id' => null,
                'next_action' => $isFailed ? 'inspect lineage and rerun data:pipeline' : 'no_action',
            ];
        }

        return $issues;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function operationalEventIssues(): array
    {
        $rows = OperationalEvent::query()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $issues = [];
        foreach ($rows as $row) {
            $occurredAt = $row->occurred_at instanceof Carbon ? $row->occurred_at : Carbon::parse((string) $row->occurred_at);
            $isFailure = $row->outcome === OperationalEvent::OUTCOME_FAILURE;
            $resolved = $isFailure ? 'failed' : 'info';
            $issues[] = [
                'id' => (int) $row->id + 300000,
                'source' => 'operational_event',
                'reference' => 'OperationalEvent#'.$row->id.' '.$row->route,
                'status' => $resolved,
                'severity' => $isFailure ? 'info' : 'info',
                'attempts' => 1,
                'occurred_at' => $occurredAt->toIso8601String(),
                'error_class' => $row->error_class,
                'correlation_id' => $row->correlation_id,
                'next_action' => $isFailure ? 'inspect route '.$row->route : 'no_action',
            ];
        }

        return $issues;
    }

    private function redactedDetail(array $issue): array
    {
        $detail = [
            'source' => $issue['source'],
            'reference' => $issue['reference'],
            'status' => $issue['status'],
            'severity' => $issue['severity'],
            'attempts' => $issue['attempts'],
            'occurred_at' => $issue['occurred_at'],
            'error_class' => $issue['error_class'],
            'correlation_id' => $issue['correlation_id'],
            'next_action' => $issue['next_action'],
        ];

        return $this->redact($detail);
    }

    private function redact(array $payload): array
    {
        $redacted = [];
        foreach ($payload as $key => $value) {
            $lower = strtolower((string) $key);
            $isSecret = false;
            foreach (self::SECRET_KEYS as $secret) {
                if (str_contains($lower, $secret)) {
                    $isSecret = true;
                    break;
                }
            }
            if ($isSecret) {
                $redacted[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redact($value);
            } elseif (is_string($value) && str_contains(strtolower($value), 'token')) {
                $redacted[$key] = '[REDACTED]';
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }
}

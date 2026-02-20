<?php

namespace App\Jobs;

use App\Models\FieldSubmission;
use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class BulkAssignFieldAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public array $submissionIds,
        public int $agentId,
        public int $assignedByUserId,
        public string $trackingId = '',
    ) {}

    public function handle(): void
    {
        $total = count($this->submissionIds);
        $this->updateProgress('processing', 0, $total, 'Processing assignments...');

        $user = User::find($this->assignedByUserId);
        $totalUpdated = 0;
        $processed = 0;
        $chunkSize = 200;

        foreach (array_chunk($this->submissionIds, $chunkSize) as $chunk) {
            $updated = FieldSubmission::query()
                ->whereIn('id', $chunk)
                ->when($user, fn ($q) => $q->visibleTo($user))
                ->update(['field_executive_id' => $this->agentId]);

            $totalUpdated += $updated;
            $processed += count($chunk);
            $this->updateProgress('processing', $processed, $total, "Processed {$processed} of {$total}...");
        }

        try {
            SystemAuditLog::record(
                'field_submission.bulk_assigned',
                null,
                [
                    'submission_ids' => $this->submissionIds,
                    'assigned_to' => $this->agentId,
                    'count' => $totalUpdated,
                    'queued' => true,
                ],
                $this->assignedByUserId,
                'field_submission'
            );
        } catch (\Throwable $e) {
            report($e);
        }

        $this->updateProgress('completed', $total, $total, "Successfully assigned {$totalUpdated} submission(s).");
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress('failed', 0, count($this->submissionIds), 'Assignment failed: ' . $exception->getMessage());
        report($exception);
    }

    private function updateProgress(string $status, int $processed, int $total, string $message): void
    {
        if (! $this->trackingId) {
            return;
        }

        Cache::put("bulk_assign:{$this->trackingId}", [
            'status' => $status,
            'total' => $total,
            'processed' => $processed,
            'percent' => $total > 0 ? (int) round(($processed / $total) * 100) : 0,
            'message' => $message,
        ], now()->addMinutes(30));
    }
}

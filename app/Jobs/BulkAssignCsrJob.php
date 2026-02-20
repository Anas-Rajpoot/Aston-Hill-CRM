<?php

namespace App\Jobs;

use App\Models\CustomerSupportSubmission;
use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class BulkAssignCsrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public array $submissionIds,
        public int $csrId,
        public int $assignedByUserId,
        public string $trackingId = '',
    ) {}

    public function handle(): void
    {
        $total = count($this->submissionIds);
        $this->updateProgress('processing', 0, $total, 'Processing assignments...');

        $user = User::find($this->assignedByUserId);
        $csrUser = User::find($this->csrId);
        $csrName = $csrUser?->name;

        $totalUpdated = 0;
        $processed = 0;
        $chunkSize = 200;

        foreach (array_chunk($this->submissionIds, $chunkSize) as $chunk) {
            $updated = CustomerSupportSubmission::query()
                ->whereIn('id', $chunk)
                ->when($user, fn ($q) => $q->visibleTo($user))
                ->update([
                    'csr_id' => $this->csrId,
                    'csr_name' => $csrName,
                ]);

            $totalUpdated += $updated;
            $processed += count($chunk);
            $this->updateProgress('processing', $processed, $total, "Processed {$processed} of {$total}...");
        }

        try {
            SystemAuditLog::record(
                'customer_support.bulk_assigned',
                null,
                [
                    'submission_ids' => $this->submissionIds,
                    'assigned_to' => $this->csrId,
                    'count' => $totalUpdated,
                    'queued' => true,
                ],
                $this->assignedByUserId,
                'customer_support'
            );
        } catch (\Throwable $e) {
            report($e);
        }

        $this->updateProgress('completed', $total, $total, "Successfully assigned {$totalUpdated} submission(s).");
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress('failed', 0, count($this->submissionIds), 'Assignment failed: '.$exception->getMessage());
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

<?php

namespace App\Jobs;

use App\Models\EscalationLevel;
use App\Models\EscalationLog;
use App\Models\LeadSubmission;
use App\Models\FieldSubmission;
use App\Models\VasRequestSubmission;
use App\Models\CustomerSupportSubmission;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\SlaRule;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Checks all active SLA rules and fires escalation notifications
 * for submissions that have breached their SLA duration.
 *
 * Scheduled: every 5 minutes via console.php
 *
 * Flow per SLA rule:
 *   1) Load active escalation levels (ordered by level)
 *   2) Query breached submissions (status not final, age > sla_duration_minutes)
 *   3) For each breached submission × escalation level:
 *      a) Check if this (module, record, level) was already sent → skip
 *      b) Resolve recipient email from EscalationLevel->resolveRecipients()
 *      c) Send email via Mail::raw()
 *      d) Log to escalation_logs + notification_logs
 */
class CheckEscalationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300; // 5 min max

    /**
     * Map SLA module_key → [Model class, non-final statuses to query].
     */
    private function moduleConfig(): array
    {
        return [
            'lead_submissions' => [
                'model'    => LeadSubmission::class,
                'statuses' => ['submitted', 'pending_for_ata', 'pending_for_finance', 'pending_from_sales'],
                'date_col' => 'submitted_at',
            ],
            'lead_resubmissions' => [
                'model'    => LeadSubmission::class,
                'statuses' => ['submitted', 'pending_for_ata', 'pending_for_finance', 'pending_from_sales'],
                'date_col' => 'submitted_at',
                'extra'    => fn ($q) => $q->where('submission_type', 'resubmission'),
            ],
            'field_submissions' => [
                'model'    => FieldSubmission::class,
                'statuses' => ['submitted', 'assigned', 'in_progress'],
                'date_col' => 'created_at',
            ],
            'vas_requests' => [
                'model'    => VasRequestSubmission::class,
                'statuses' => ['submitted', 'pending', 'in_progress'],
                'date_col' => 'created_at',
            ],
            'customer_support_requests' => [
                'model'    => CustomerSupportSubmission::class,
                'statuses' => ['submitted', 'open', 'in_progress'],
                'date_col' => 'created_at',
            ],
        ];
    }

    public function handle(): void
    {
        // Gate: if SLA alerts channel is disabled, do nothing
        if (! NotificationService::isSlaAlertsEnabled()) {
            Log::info('CheckEscalationJob: SLA alerts channel is disabled. Skipping.');
            return;
        }

        $escalationLevels = EscalationLevel::activeLevels();
        if ($escalationLevels->isEmpty()) {
            Log::info('CheckEscalationJob: No active escalation levels. Skipping.');
            return;
        }

        $slaRules = SlaRule::cached()->where('is_active', true);
        $emailCfg = NotificationSetting::emailConfig();
        $moduleConfig = $this->moduleConfig();

        foreach ($slaRules as $rule) {
            $config = $moduleConfig[$rule->module_key] ?? null;
            if (! $config) {
                continue; // module not mapped to a model
            }

            $this->processModule($rule, $config, $escalationLevels, $emailCfg);
        }
    }

    private function processModule(
        SlaRule $rule,
        array $config,
        $escalationLevels,
        array $emailCfg,
    ): void {
        $modelClass = $config['model'];
        $dateCol    = $config['date_col'];
        $breachThreshold = now()->subMinutes($rule->sla_duration_minutes);

        // Query submissions that have breached SLA
        $query = $modelClass::query()
            ->whereIn('status', $config['statuses'])
            ->whereNotNull($dateCol)
            ->where($dateCol, '<=', $breachThreshold);

        // Apply extra scope if defined (e.g., resubmission filter)
        if (isset($config['extra']) && is_callable($config['extra'])) {
            ($config['extra'])($query);
        }

        // Chunk to avoid memory issues
        $query->chunk(100, function ($submissions) use ($rule, $escalationLevels, $emailCfg) {
            foreach ($submissions as $submission) {
                $this->processSubmission($rule, $submission, $escalationLevels, $emailCfg);
            }
        });
    }

    private function processSubmission(
        SlaRule $rule,
        $submission,
        $escalationLevels,
        array $emailCfg,
    ): void {
        foreach ($escalationLevels as $level) {
            // Skip if already sent for this (module, record, level)
            if (EscalationLog::alreadySent($rule->module_key, $submission->id, $level->level)) {
                continue;
            }

            $recipients = $level->resolveRecipients($submission);

            if (empty($recipients)) {
                // Log skipped — no recipient resolved
                EscalationLog::create([
                    'module_key'       => $rule->module_key,
                    'record_id'        => $submission->id,
                    'escalation_level' => $level->level,
                    'sent_to'          => '—',
                    'recipient_type'   => $level->recipient_type,
                    'status'           => 'skipped',
                    'error'            => 'No recipient resolved for ' . $level->recipient_label,
                    'sent_at'          => now(),
                ]);
                continue;
            }

            foreach (array_unique($recipients) as $email) {
                $this->sendEscalation($rule, $submission, $level, $email, $emailCfg);
            }
        }
    }

    private function sendEscalation(
        SlaRule $rule,
        $submission,
        EscalationLevel $level,
        string $toEmail,
        array $emailCfg,
    ): void {
        $subject = sprintf(
            'SLA Breach Alert (Level %d) — %s #%d',
            $level->level,
            $rule->module_name,
            $submission->id,
        );

        $body = sprintf(
            "SLA Breach Notification\n\n" .
            "Module: %s\n" .
            "Record #: %d\n" .
            "SLA Duration: %s\n" .
            "Escalation Level: %d (%s)\n" .
            "Recipient: %s\n\n" .
            "This submission has exceeded its SLA duration. Please take immediate action.\n\n" .
            "— %s Notification System",
            $rule->module_name,
            $submission->id,
            SlaRule::minutesToHuman($rule->sla_duration_minutes),
            $level->level,
            $level->recipient_label,
            $toEmail,
            config('app.name', 'Aston Hill CRM'),
        );

        $status = 'sent';
        $error  = null;

        try {
            Mail::raw($body, function ($mail) use ($emailCfg, $toEmail, $subject) {
                $mail->from($emailCfg['from'], config('app.name', 'Aston Hill CRM'))
                     ->to($toEmail)
                     ->subject($subject);

                if (! empty($emailCfg['cc'])) {
                    $mail->cc($emailCfg['cc']);
                }
                if (! empty($emailCfg['bcc'])) {
                    $mail->bcc($emailCfg['bcc']);
                }
            });

            Log::info("CheckEscalationJob: Sent Level {$level->level} escalation to {$toEmail} for {$rule->module_key} #{$submission->id}");
        } catch (\Throwable $e) {
            $status = 'failed';
            $error  = $e->getMessage();
            Log::error("CheckEscalationJob: Failed Level {$level->level} to {$toEmail}: {$error}");
        }

        // Record in escalation_logs (prevents re-send)
        EscalationLog::create([
            'module_key'       => $rule->module_key,
            'record_id'        => $submission->id,
            'escalation_level' => $level->level,
            'sent_to'          => $toEmail,
            'recipient_type'   => $level->recipient_type,
            'status'           => $status,
            'error'            => $error,
            'sent_at'          => now(),
        ]);

        // Also record in notification_logs for the unified log view
        try {
            NotificationLog::create([
                'trigger_key' => 'sla_escalation_level_' . $level->level,
                'channel'     => 'email',
                'module'      => $rule->module_key,
                'sent_to'     => $toEmail,
                'status'      => $status,
                'error'       => $error,
            ]);
        } catch (\Throwable $e) {
            // Don't break escalation if log fails
        }
    }
}

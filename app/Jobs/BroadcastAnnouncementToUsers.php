<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\NewAnnouncementNotification;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastAnnouncementToUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $announcementId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $startedAt = now();
        $sentFollowups = 0;
        $sentNotes = 0;

        try {
            EmailFollowUp::whereNotNull('remind_at')
                ->whereNull('reminded_at')
                ->where('remind_at', '<=', now())
                ->with('user:id')
                ->chunk(200, function ($rows) use (&$sentFollowups) {
                    foreach ($rows as $r) {
                        if ($r->user) {
                            $r->user->notify(new EmailFollowupAlert($r->id, $r->subject ?? 'Follow up'));
                            $r->update(['reminded_at' => now()]);
                            $sentFollowups++;
                        }
                    }
                });

            PersonalNote::whereNotNull('remind_at')
                ->whereNull('reminded_at')
                ->where('remind_at', '<=', now())
                ->with('user:id')
                ->chunk(200, function ($rows) use (&$sentNotes) {
                    foreach ($rows as $r) {
                        if ($r->user) {
                            $r->user->notify(new PersonalNoteAlert($r->id, $r->title ?? 'Note'));
                            $r->update(['reminded_at' => now()]);
                            $sentNotes++;
                        }
                    }
                });

            Log::info('notifications:dispatch-due success', [
                'sent_followups' => $sentFollowups,
                'sent_notes' => $sentNotes,
                'duration_seconds' => $startedAt->diffInSeconds(now()),
            ]);

            $this->info("Done. Followups: {$sentFollowups}, Notes: {$sentNotes}");
            return self::SUCCESS;

        } catch (\Throwable $e) {
            Log::error('notifications:dispatch-due FAILED', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 2000),
            ]);

            $this->error('Failed: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}

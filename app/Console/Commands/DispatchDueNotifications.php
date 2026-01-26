<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailFollowUp;
use App\Models\User;
use App\Notifications\UserAlertNotification;
use App\Models\PersonalNote;
use App\Notifications\EmailFollowupAlert;
use App\Notifications\PersonalNoteAlert;

class DispatchDueNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:dispatch-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch due notifications for followups and personal notes';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        EmailFollowUp::whereNotNull('remind_at')
            ->whereNull('reminded_at')
            ->where('remind_at', '<=', now())
            ->with('user')
            ->chunk(200, function ($rows) {
                foreach ($rows as $r) {
                    if ($r->user) {
                        $r->user->notify(new EmailFollowupAlert($r->id, $r->subject ?? 'Follow up'));
                        $r->update(['reminded_at' => now()]);
                    }
                }
            });

            PersonalNote::whereNotNull('remind_at')
            ->whereNull('reminded_at')
            ->where('remind_at', '<=', now())
            ->with('user')
            ->chunk(200, function ($rows) {
                foreach ($rows as $r) {
                    if ($r->user) {
                        $r->user->notify(new PersonalNoteAlert($r->id, $r->title ?? 'Note'));
                        $r->update(['reminded_at' => now()]);
                    }
                }
            });

        // return self::SUCCESS;

        // $followUps = EmailFollowUp::query()
        //     ->whereDate('email_date', today())
        //     ->whereNull('notified_at')
        //     ->with('creator:id')
        //     ->get();

        // foreach ($followUps as $followUp) {
        //     $user = User::find($followUp->created_by);

        //     if (!$user) {
        //         continue;
        //     }

        //     $user->notify(
        //         new UserAlertNotification(
        //             'You have an email follow-up scheduled today.',
        //             'follow_up'
        //         )
        //     );

        //     // mark as notified
        //     $followUp->update([
        //         'notified_at' => now(),
        //     ]);
        // }

        $this->info('Email follow-up notifications sent.');
    }
}

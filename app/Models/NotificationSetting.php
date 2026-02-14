<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class NotificationSetting extends Model
{
    public const CACHE_KEY = 'notification_config';
    public const CACHE_TTL = 600;

    protected $fillable = [
        'default_sender_email', 'cc_emails', 'bcc_emails',
        'enable_email', 'enable_web', 'enable_sms', 'enable_sla_alerts',
        'updated_by',
    ];

    protected $casts = [
        'cc_emails'        => 'array',
        'bcc_emails'       => 'array',
        'enable_email'     => 'boolean',
        'enable_web'       => 'boolean',
        'enable_sms'       => 'boolean',
        'enable_sla_alerts'=> 'boolean',
    ];

    public static function singleton(): self
    {
        return self::firstOrCreate(['id' => 1], [
            'default_sender_email' => 'order@astonhill.ae',
            'cc_emails' => [],
            'bcc_emails' => [],
        ]);
    }

    /**
     * Return cached email dispatch config.
     * Use in any Mailable / Notification to apply global from/cc/bcc.
     *
     * Usage:
     *   $cfg = NotificationSetting::emailConfig();
     *   Mail::to($recipient)
     *       ->cc($cfg['cc'])
     *       ->bcc($cfg['bcc'])
     *       ->send(new SomeMail()->from($cfg['from']));
     */
    public static function emailConfig(): array
    {
        return Cache::remember(self::CACHE_KEY . '_email', self::CACHE_TTL, function () {
            $s = self::singleton();
            return [
                'from' => $s->default_sender_email ?: config('mail.from.address', 'order@astonhill.ae'),
                'cc'   => array_filter((array) ($s->cc_emails ?? [])),
                'bcc'  => array_filter((array) ($s->bcc_emails ?? [])),
            ];
        });
    }

    public static function clearConfigCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY . '_email');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

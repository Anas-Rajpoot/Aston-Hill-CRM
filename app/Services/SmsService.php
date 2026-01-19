<?php

namespace App\Services;

class SmsService
{
    /**
     * Send an SMS to the given phone number.
     *
     * @param string $phone
     * @param string $message
     * @return bool
     */
    public static function send(string $phone, string $message): bool
    {
        
        // For testing locally, just log
        \Log::info("SMS to {$phone}: {$message}");

        return true;
    }
}

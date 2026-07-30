<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioService
{
    protected $twilio;

    public function __construct()
    {
        $this->twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.auth_token')
        );
    }

    public function sendSms(string $to, string $message): bool
    {
        try {
            $this->twilio->messages->create($to, [
                'from' => config('services.twilio.phone_number'),
                'body' => $message,
            ]);
            return true;
        } catch (\Exception $e) {
            // Puedes agregar logs aquí si lo necesitas
            Log::error('Error al enviar SMS: ' . $e->getMessage());
            return false;
        }
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendCodeCliente extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $code;
    public $isNewCode;

    public function __construct($name, $code, $isNewCode = false)
    {
        $this->name = $name;
        $this->code = $code;
        $this->isNewCode = $isNewCode;
    }

    public function build()
    {
        return $this->subject('Código de verificación - TRUELOVE')
            ->view('emails.verification-cliente')
            ->with([
                'name' => $this->name,
                'code' => $this->code,
                'isNewCode' => $this->isNewCode,
            ]);
    }
}

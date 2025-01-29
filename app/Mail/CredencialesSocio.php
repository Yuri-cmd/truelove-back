<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CredencialesSocio extends Mailable
{
    use Queueable, SerializesModels;

    public $username;
    public $password;
    public $registrationId;

    public function __construct($username, $password, $registrationId)
    {
        $this->username = $username;
        $this->password = $password;
        $this->registrationId = $registrationId;
    }

    public function build()
    {
        return $this->view('emails.credenciales-socio')
                    ->subject('Tus credenciales de acceso - TRUELOVE');
    }
}
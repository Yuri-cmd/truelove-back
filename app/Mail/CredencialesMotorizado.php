<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CredencialesMotorizado extends Mailable
{
    // aqui van las propiedades de la clase
    use Queueable, SerializesModels;

    // public $username;
    public $password;

    public function __construct($username, $password)
    {
        // $this->username = $username;
        $this->password = $password;
    }

    public function build()
    {
        return $this->view('emails.credenciales-motorizado')
                    ->subject('Tus credenciales de acceso');
    }
}
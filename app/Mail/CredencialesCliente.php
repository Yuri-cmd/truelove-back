<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CredencialesCliente extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $documento;

    public function __construct($email, $documento)
    {
        $this->email = $email;
        $this->documento = $documento;
    }

    public function build()
    {
        return $this->view('emails.credenciales-clientes')
                    ->subject('Tus credenciales de acceso - TRUELOVE');
    }
}

<?php

namespace App\Models\mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Usuario y contraseña del CRM al crear el usuario o al cambiarle la contraseña o el alias (UserController).
class SendMailCredencialesUsuario extends Mailable
{
    use Queueable, SerializesModels;

    public $object;
    public $asunto;

    public function __construct($object)
    {
        $this->object = $object;

        $this->asunto = $this->object->asunto ?? '';
    }

    public function build()
    {
        return $this->subject($this->asunto)->view('mail.send_credenciales_usuario');
    }
}

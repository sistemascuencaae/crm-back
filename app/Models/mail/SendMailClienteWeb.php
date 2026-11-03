<?php

namespace App\Models\mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Correos a los clientes de la tienda web (ClienteWebService): confirmar el correo, restablecer la contraseña,
// confirmar un correo nuevo y los avisos de contraseña o correo cambiados. $object->tipo elige el asunto y la vista.
class SendMailClienteWeb extends Mailable
{
    use Queueable, SerializesModels;

    private const CORREOS = [
        'confirmar_correo' => ['asunto' => 'Confirma tu correo', 'vista' => 'mail.cliente_confirmar_correo'],
        'restablecer'      => ['asunto' => 'Restablece tu contraseña', 'vista' => 'mail.cliente_restablecer'],
        'cambiar_correo'   => ['asunto' => 'Confirma tu nuevo correo', 'vista' => 'mail.cliente_cambiar_correo'],
        'aviso_contrasena' => ['asunto' => 'Tu contraseña se cambió', 'vista' => 'mail.cliente_aviso'],
        'aviso_correo'     => ['asunto' => 'El correo de tu cuenta cambió', 'vista' => 'mail.cliente_aviso'],
    ];

    public $object;

    public function __construct($object)
    {
        $this->object = $object;
    }

    public function build()
    {
        $correo = self::CORREOS[$this->object->tipo];

        return $this->subject($correo['asunto'])->view($correo['vista']);
    }
}

<?php

namespace App\Models\mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendMail extends Mailable
{
    use Queueable, SerializesModels;

    public $object;

    public function __construct($object) // Recibe cualquier objeto por ejemplo un Pedido para poder acceder en la vista ($object->campo_que_queremos_mostrar)
    {
        $this->object = $object;
    }

    public function build()
    {
        return $this->subject("PROFORMA DE COMPRA")->view('mail.send_email');
    }

    // public function build()
    // {
    //     return $this->view('mail.send_email')
    //         ->subject('Asunto del correo')
    //         ->with(['mensaje' => 'Contenido del correo electrónico']);
    // }

}
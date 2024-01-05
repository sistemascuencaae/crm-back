<?php

namespace App\Models\mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class sendMailLinkEnrolamiento extends Mailable
{
    use Queueable, SerializesModels;

    public $object;
    public $asunto;

    public function __construct($object) // Recibe cualquier objeto por ejemplo un Pedido para poder acceder en la vista ($object->campo_que_queremos_mostrar)
    {
        $this->object = $object;
        //

        $this->asunto = $this->object->asunto;
    }

    public function build()
    {
        return $this->subject($this->asunto)->view('mail.send_linkEnrolamiento');
    }

    // public function build()
    // {
    //     return $this->view('mail.send_email')
    //         ->subject('Asunto del correo')
    //         ->with(['mensaje' => 'Contenido del correo electrónico']);
    // }

}
<?php

namespace App\Models\mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendMail extends Mailable
{
    use Queueable, SerializesModels;

    public $tablero;

    public function __construct($tablero)
    {
        $this->tablero = $tablero;
    }

    public function build()
    {
        return $this->subject("ECOMMERCE DETALLADO DE COMPRA")->view('mail.send_email');
    }

    // public function build()
    // {
    //     return $this->view('mail.send_email')
    //         ->subject('Asunto del correo')
    //         ->with(['mensaje' => 'Contenido del correo electrónico']);
    // }

}
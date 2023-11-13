<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Models\mail\SendMail;
use Exception;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function send_email($email, $object)
    {
        try {
            // $email = "juanjgsj@gmail.com";
            Mail::to($email)->send(new SendMail($object));
            // return "Correo electrónico enviado correctamente a " . $email;
        } catch (Exception $e) {
            return "Error al enviar el correo: " . $e->getMessage();
        }
    }

}

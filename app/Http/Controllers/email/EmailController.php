<?php

namespace App\Http\Controllers\email;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\mail\SendMailTerminosCondiciones;
use Exception;
use Illuminate\Support\Facades\Mail;

// Migrado de ecommerce-back: solo el correo que usa el formulario institucional (/formulario)
class EmailController extends Controller
{

    // Correo para el cliente que solita credito desde la pagina web o desde el formulario institucional
    public function sendEmailTerminosCondiciones($email, $object)
    {
        try {
            // Mail::to($email)->send(new SendMailTerminosCondiciones($object));

            // smtp es el correo default que se utliza para cotizaciones@almespana.com.ec
            Mail::mailer('smtp')
                ->to($email)
                ->send(
                    (new SendMailTerminosCondiciones($object))
                        ->from(
                            config('mail.from.address'),
                            config('mail.from.name')
                        )
                );

            return response()->json(RespuestaApi::returnResultado('success', 'Correo electrónico enviado correctamente a ' . $email, ''));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al enviar el correo', $e->getMessage()));
        }
    }

}

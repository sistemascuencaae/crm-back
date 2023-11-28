<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\mail\CorreoElectronico;
use App\Models\mail\SendMail;
use App\Models\mail\sendMailCambioFase;
use App\Models\mail\sendMailLinkEnrolamiento;
use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;


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

    public function send_emailCambioFase(Request $request)
    {
        try {
            // $email = "juanjgsj@gmail.com";

            $email = $request->input('email');
            $object = (object) [
                'asunto' => $request->input('asunto'),
                // 'tipo' => $request->input('tipo'),
                'titulo' => $request->input('titulo'),
                'empresa' => $request->input('empresa'),
                'atentamente' => $request->input('atentamente'),
                'motivo' => $request->input('motivo'),
                'mensaje_final' => $request->input('mensaje_final'),
                'texto' => $request->input('texto'),
            ];

            Mail::to($email)->send(new sendMailCambioFase($object));
            return "Correo electrónico enviado correctamente a " . $email;
        } catch (Exception $e) {
            return "Error al enviar el correo: " . $e->getMessage();
        }
    }

    public function send_emailCambioFaseAutomatico($email, $caso_id, $nombre_fase)
    {
        try {
            // $email = "juanjgsj@gmail.com";

            $object = CorreoElectronico::where('tipo', 'Enrolamiento')->first();

            $textoReemplazado = str_replace('<caso_id>', $caso_id, $object->texto);
            $textoReemplazado = str_replace('<nombre_fase>', $nombre_fase, $textoReemplazado);

            // echo $textoReemplazado;
            $object->texto = $textoReemplazado;
            Mail::to($email)->send(new sendMailCambioFase($object));

            return "Correo electrónico enviado correctamente a " . $email;
        } catch (Exception $e) {
            return "Error al enviar el correo: " . $e->getMessage();
        }
    }

    public function send_emailLinkEnrolamiento(Request $request)
    {
        try {
            // $email = "juanjgsj@gmail.com";

            // $email = $request->input('email');
            $object = (object) [
                'email' => $request->input('email'),
                'asunto' => $request->input('asunto'),
                'link' => $request->input('link'),
            ];

            Mail::to($object->email)->send(new sendMailLinkEnrolamiento($object));

            return response()->json(RespuestaApi::returnResultado('success', "Correo electrónico enviado correctamente a " . $object->email, ''));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}

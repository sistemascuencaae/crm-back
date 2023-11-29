<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\mail\Email;
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

    // proforma
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

    public function listEmailByFaseId($fase_id)
    {
        try {
            $data = Email::where('fase_id', $fase_id)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addEmail(Request $request)
    {
        try {
            $data = Email::create($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editEmail(Request $request, $id)
    {
        try {
            $data = Email::findOrFail($id);

            $data->update($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function send_emailCambioFase($email, $caso_id, $nombre_fase, $fase_id, $nombre_cliente)
    {
        try {
            // $email = "juanjgsj@gmail.com";

            $object = Email::where('fase_id', $fase_id)->first();

            if (!$object) {
                return response()->json(RespuestaApi::returnResultado('error', 'No existe un correo electrónico relacionado con esta fase', ''));
            } else {

                $textoReemplazado = str_replace('<nombre_cliente>', $nombre_cliente, $object->cuerpo);
                $textoReemplazado = str_replace('<caso_id>', $caso_id, $textoReemplazado);
                $textoReemplazado = str_replace('<nombre_fase>', $nombre_fase, $textoReemplazado);

                // echo $textoReemplazado;
                $object->cuerpo = $textoReemplazado;
                Mail::to($email)->send(new sendMailCambioFase($object));

                return response()->json(RespuestaApi::returnResultado('success', "Correo electrónico enviado correctamente a " . $email, $object));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    // public function send_emailCambioFase1(Request $request)
    // {
    //     try {
    //         // $email = "juanjgsj@gmail.com";

    //         $email = $request->input('email');
    //         $object = (object) [
    //             'asunto' => $request->input('asunto'),
    //             'cuerpo' => $request->input('cuerpo'),
    //         ];

    //         Mail::to($email)->send(new sendMailCambioFase($object));

    //         return response()->json(RespuestaApi::returnResultado('success', "Correo electrónico enviado correctamente a " . $email, ''));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }


}

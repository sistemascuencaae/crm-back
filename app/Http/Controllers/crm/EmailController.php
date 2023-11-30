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
use Illuminate\Support\Facades\DB;

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
            $casoId = $request->input('casoId');
            $cliId = $request->input('cliId');
            $reqCasoId = $request->input('reqCasoId');

            $validEnrolCli = DB::select('SELECT * from crm.temp_enrolamiento_cliente
             where cli_id = ? and req_caso_id = ? and  caso_id = ?', [$cliId, $reqCasoId, $casoId]);

            if (!$validEnrolCli) {
                $dataTempEnro = DB::insert(
                    'INSERT INTO crm.temp_enrolamiento_cliente
                (cli_id, req_caso_id, caso_id)
                VALUES(?, ?, ?);',
                    [$cliId, $reqCasoId, $casoId]
                );
            }



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

    public function send_emailCambioFase($caso_id, $nombre_fase, $fase_id, $nombre_cliente)
    {
        try {
            // $email = "juanjgsj@gmail.com";
            $object = Email::where('fase_id', $fase_id)->first();
            $result = DB::select("
            SELECT cli.email, em.emails, em.email_cliente
            FROM crm.caso ca
            INNER JOIN crm.cliente cli ON cli.id = ca.cliente_id
            LEFT JOIN crm.email em ON em.fase_id = ca.fas_id
            WHERE ca.id = :casoId", ['casoId' => $caso_id]);
            if (count($result) == 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'No existe un correo electrónico relacionado con esta fase', ''));
            } else {

                // Encontrar todas las palabras entre <>
                preg_match_all('/<([^>]*)>/', $object->cuerpo, $matches);

                // Obtener todas las coincidencias encontradas
                $palabrasEntreCorchetes = $matches[1];

                // Lista de palabras clave
                $palabrasClave = ['nombre_cliente', 'caso_id', 'nombre_fase'];

                // Realiza los reemplazos
                foreach ($palabrasEntreCorchetes as $palabra) {
                    // Verificar si la palabra clave está presente en la lista de palabras clave
                    if (in_array($palabra, $palabrasClave)) {
                        // Realizar el reemplazo con el valor correspondiente
                        $textoReemplazado = str_replace('<nombre_cliente>', $nombre_cliente, $object->cuerpo);
                        $textoReemplazado = str_replace('<caso_id>', $caso_id, $textoReemplazado);
                        $textoReemplazado = str_replace('<nombre_fase>', $nombre_fase, $textoReemplazado);

                        $object->cuerpo = $textoReemplazado;
                    } else {
                        // Si la palabra clave no está presente, lo reemplazo con un espacio en blanco
                        $object->cuerpo = str_replace("<$palabra>", ' ', $object->cuerpo);
                    }
                }
                $row = $result[0];
                // Obtener los correos separados por comas
                $emailsArray = explode(',', $row->emails);
                // Limpiar espacios en blanco alrededor de los correos
                $emailsArray = array_map('trim', $emailsArray);
                // Añadir el correo adicional si el campo es true
                if ($row->email_cliente === true) {
                    $emailsArray[] = $row->email;
                }
                // Puedes devolver el array de correos aquí o hacer cualquier otra cosa con él
                if(count($emailsArray) > 0){
                    Mail::to($emailsArray)->send(new sendMailCambioFase($object));
                }else{
                    return response()->json(RespuestaApi::returnResultado('error', 'No existen correos para enviar.', ''));
                }

                return response()->json(RespuestaApi::returnResultado('success', "Correos enviados correctamente", $object));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}

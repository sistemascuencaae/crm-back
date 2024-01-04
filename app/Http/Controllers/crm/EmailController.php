<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Fase;
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

    public function send_emailCambioFase($caso_id, $fase_id)
    {
        try {
            $object = Email::where('fase_id', $fase_id)->first();

            $fase = Fase::find($fase_id);
            $result = DB::selectOne("SELECT distinct  cli.email, em.emails, em.email_cliente, em.auto, cli.nombre_comercial from crm.caso ca
            inner join crm.cliente cli on cli.id = ca.cliente_id
            inner join crm.requerimientos_caso rc on rc.caso_id = ca.id
            inner join crm.fase fa on fa.id = rc.fas_id
            inner join crm.email em on em.fase_id = fa.id
            where fa.id = :faseId and ca.id = :casoId", [
                'casoId' => $caso_id,
                'faseId' => $fase_id
            ]);

            if ($result == null) {
                $emailsSendCli = DB::selectOne("SELECT  em.emails, em.email_cliente, em.auto from crm.fase fa
                inner join crm.email em on em.fase_id = fa.id
                where fa.id = :faseId", ['faseId' => $fase_id]);

                // echo json_encode($emailsSendCli);

                if ($emailsSendCli) {
                    $datosCliente = DB::selectOne("SELECT cli.email, cli.nombre_comercial from crm.caso ca
                inner join crm.cliente cli on cli.id = ca.cliente_id
                where ca.id = :casoId", ['casoId' => $caso_id]);

                    $result = (object) [
                        "email" => $datosCliente->email,
                        "emails" => $emailsSendCli->auto == true ? $emailsSendCli->emails : "",
                        "email_cliente" => $emailsSendCli->email_cliente,
                        "nombre_comercial" => $datosCliente->nombre_comercial,
                        "auto" => $emailsSendCli->auto
                    ];
                } else {
                    return response()->json(RespuestaApi::returnResultado('error', 'No existe un correo electrónico relacionado con esta fase', ''));
                }
            }

            if (!$result || !$object) {
                return response()->json(RespuestaApi::returnResultado('error', 'No existe un correo electrónico relacionado con esta fase', ''));
            } else {

                // Encontrar todas las palabras entre { }
                preg_match_all('/{([^>]*)}/', $object->cuerpo, $matches);

                // Obtener todas las coincidencias encontradas
                $palabrasEntreCorchetes = $matches[1];

                // Lista de palabras clave
                $palabrasClave = ['nombre_cliente', 'caso_id', 'nombre_fase'];

                // Copia la cadena original
                $cuerpoOriginal = $object->cuerpo;

                // Inicializa la cadena modificada con la original
                $textoReemplazado = $cuerpoOriginal;

                // Realiza los reemplazos
                foreach ($palabrasEntreCorchetes as $palabra) {
                    // Verificar si la palabra clave está presente en la lista de palabras clave
                    if (in_array($palabra, $palabrasClave)) {
                        // Realizar el reemplazo con el valor correspondiente
                        $textoReemplazado = str_replace('{nombre_cliente}', $result->nombre_comercial, $textoReemplazado);
                        $textoReemplazado = str_replace('{caso_id}', $caso_id, $textoReemplazado);
                        $textoReemplazado = str_replace('{nombre_fase}', $fase->nombre, $textoReemplazado);
                    }
                    // else {
                    //     // Si la palabra clave no está presente, lo reemplazo con un espacio en blanco
                    //     $textoReemplazado = str_replace("{$palabra}", ' ', $textoReemplazado);
                    // }
                }

                // Asigna la cadena completa con los estilos originales a $object->cuerpo
                $object->cuerpo = $textoReemplazado;

                $row = $result;
                // Obtener los correos separados por comas
                if ($row->auto == true) {
                    $emailsArray = explode(',', $row->emails);
                } else {
                    $emailsArray = [];
                }

                // Limpiar espacios en blanco alrededor de los correos
                $emailsArray = array_map('trim', $emailsArray);
                // Añadir el correo adicional si el campo es true
                if ($row->email_cliente === true) {
                    $emailsArray[] = $row->email;
                }
                // Puedes devolver el array de correos aquí o hacer cualquier otra cosa con él
                if (count($emailsArray) > 0) {
                    Mail::to($emailsArray)->send(new sendMailCambioFase($object));

                } else {
                    return response()->json(RespuestaApi::returnResultado('error', 'No existen correos para enviar.', ''));
                }

                return response()->json(RespuestaApi::returnResultado('success', "Correos enviados correctamente", $object));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}

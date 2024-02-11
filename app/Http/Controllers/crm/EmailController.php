<?php

namespace App\Http\Controllers\crm;

use App\Events\ReasignarCasoEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\credito\RobotCasoController;
use App\Http\Controllers\openceo\PedidoMovilController;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Caso;
use App\Models\crm\Fase;
use App\Models\mail\Email;
use App\Models\mail\SendMail;
use App\Models\mail\sendMailCambioFase;
use App\Models\mail\SendMailComite;
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
        $log = new Funciones();
        try {
            // $email = "juanjgsj@gmail.com";
            Mail::to($email)->send(new SendMail($object));
            // return "Correo electrónico enviado correctamente a " . $email;
            $log->logInfo(EmailController::class, 'Correo electrónico enviado correctamente a ' . $email);

        } catch (Exception $e) {
            $log->logError(EmailController::class, 'Error al enviar el correo electrónico a ' . $email, $e);

            return "Error al enviar el correo: " . $e->getMessage();
        }
    }

    public function send_emailLinkEnrolamiento(Request $request)
    {
        $log = new Funciones();
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

            $log->logInfo(EmailController::class, 'Correo electrónico enviado correctamente a ' . $object->email);

            return response()->json(RespuestaApi::returnResultado('success', "Correo electrónico enviado correctamente a " . $object->email, ''));
        } catch (Exception $e) {
            $log->logError(EmailController::class, 'Error al enviar el correo electrónico a ' . $object->email, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listEmailByFaseId($fase_id)
    {
        $log = new Funciones();
        try {
            $data = Email::where('fase_id', $fase_id)->first();

            $log->logInfo(EmailController::class, 'Se listo con exito el correo electrónico de la fase con el ID: ' . $fase_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(EmailController::class, 'Error al listar el correo electrónico de la fase con el ID: ' . $fase_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addEmail(Request $request)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request) {

                $respuesta = Email::create($request->all());

                return $respuesta;
            });

            $log->logInfo(EmailController::class, 'Se guardo con exito el correo electrónico');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(EmailController::class, 'Error al guardar el correo electrónico', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editEmail(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $email = Email::findOrFail($id);

                $email->update($request->all());

                return $email;
            });

            $log->logInfo(EmailController::class, 'Se actualizo con exito el correo electrónico con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(EmailController::class, 'Error al actualizar el correo electrónico con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function send_emailCambioFase($caso_id, $fase_id)
    {
        $log = new Funciones();
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
                    $log->logError(EmailController::class, 'No existe un correo electrónico relacionado con esta fase');

                    return response()->json(RespuestaApi::returnResultado('error', 'No existe un correo electrónico relacionado con esta fase', ''));
                }
            }

            if (!$result || !$object) {
                $log->logError(EmailController::class, 'No existe un correo electrónico relacionado con esta fase');

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
                    $log->logError(EmailController::class, 'No existen correos para enviar.');

                    return response()->json(RespuestaApi::returnResultado('error', 'No existen correos para enviar.', ''));
                }

                $log->logInfo(EmailController::class, 'Correos enviados correctamente');

                return response()->json(RespuestaApi::returnResultado('success', "Correos enviados correctamente", $object));
            }
        } catch (Exception $e) {
            $log->logError(EmailController::class, 'Error al enviar el correo electrónico en el cambio de fase', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function send_emailComite($estadoFormId, $caso_id, $tableroActualId)
    {
        $log = new Funciones();
        try {

            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($log, $estadoFormId, $caso_id, $tableroActualId, &$error, &$exitoso) {

                $caso = Caso::find($caso_id);

                if ($caso) { // Si existe el caso
                    if ($caso->cpp_id) {

                        $pedidoMovil = null;

                        $emails = ['juanjgsj@gmail.com', 'juan.simbana@almespana.com.ec'];
                        // $emails = 'juanjgsj@gmail.com';

                        $pedidoMovilController = new PedidoMovilController();
                        $pedidoMovil = $pedidoMovilController->getPedidoById($caso->cpp_id);
                        $pedidoMovil = $pedidoMovil->getData()->data; // obtendo directamente la data y no todo el objeto returnResultado

                        // aqui envio la variable banMostrarVistaCreditoAprobado con true o cualquier otro valor, para que se muestre la vista cuando den click en el enlace o link
                        $urlEndPoint = 'http://192.168.1.105:8009/api/crm/robot/reasignarCaso/' . $estadoFormId . '/' . $caso_id . '/' . $tableroActualId . '/' . $banMostrarVistaCreditoAprobado = true; // aqui hay que armar el link de ENdPoint que va a mover y cambiar de estado y dueño al caso

                        // Todos los datos que vamos a enviar en el correo
                        $object = (object) [
                            'emails' => $emails,
                            'asunto' => 'Caso para aprobación de crédito',
                            'link' => $urlEndPoint,
                            'data' => $pedidoMovil,
                            'caso' => $caso,
                        ];

                        // Enviar el correo a los destinatarios especificados en el array de correos electrónicos
                        foreach ($object->emails as $correo) {
                            Mail::to($correo)->send(new SendMailComite($object));
                        }

                        $log->logInfo(EmailController::class, 'Correo electrónico enviado correctamente al comité');

                        $exitoso = 'Correo electrónico enviado correctamente al comité';
                        return null;

                    } else {

                        $log->logError(EmailController::class, 'No existe un pedido en el caso #' . $caso_id);

                        $error = 'No existe un pedido en el caso #' . $caso_id;
                        return null;
                    }

                } else {
                    $log->logError(EmailController::class, 'No existe el caso #' . $caso_id);

                    $error = 'No existe el caso #' . $caso_id;
                    return null;
                }

            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                return response()->json(RespuestaApi::returnResultado('success', $exitoso, ''));
            }

        } catch (Exception $e) {
            $log->logError(EmailController::class, 'Error al enviar el correo electrónico al comité', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}

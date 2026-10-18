<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Caso;
use App\Models\crm\Fase;
use App\Models\crm\RequerimientoCaso;
use App\Models\crm\Requerimientos;
use App\Models\mail\Email;
use App\Models\mail\sendMailCambioFase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Exception;

class EmailDinamicoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Campos estáticos disponibles de la tabla crm.caso
     */
    private function getCasoPlaceholders()
    {
        return [
            ['clave' => '{caso_id}', 'campo' => 'id', 'descripcion' => 'ID del caso'],
            ['clave' => '{caso_nombre}', 'campo' => 'nombre', 'descripcion' => 'Nombre del caso'],
            ['clave' => '{caso_descripcion}', 'campo' => 'descripcion', 'descripcion' => 'Descripción del caso'],
            ['clave' => '{caso_estado}', 'campo' => 'estado', 'relacion' => 'estadodos.nombre', 'descripcion' => 'Estado actual'],
            ['clave' => '{caso_prioridad}', 'campo' => 'prioridad', 'descripcion' => 'Prioridad'],
            ['clave' => '{caso_fecha_inicio}', 'campo' => 'fecha_inicio', 'descripcion' => 'Fecha de inicio'],
            ['clave' => '{caso_fecha_vencimiento}', 'campo' => 'fecha_vencimiento', 'descripcion' => 'Fecha de vencimiento'],
            ['clave' => '{caso_identificacion}', 'campo' => 'identificacion', 'descripcion' => 'Identificación'],
            ['clave' => '{caso_cliente}', 'campo' => 'cliente', 'descripcion' => 'Nombre del cliente del caso'],
            ['clave' => '{caso_email}', 'campo' => 'email', 'descripcion' => 'Email del caso'],
            ['clave' => '{caso_estado_civil}', 'campo' => 'estado_civil', 'descripcion' => 'Estado civil'],
            ['clave' => '{caso_sexo}', 'campo' => 'sexo', 'descripcion' => 'Sexo'],
            ['clave' => '{caso_pais}', 'campo' => 'pais', 'descripcion' => 'País'],
            ['clave' => '{caso_provincia}', 'campo' => 'provincia', 'descripcion' => 'Provincia'],
            ['clave' => '{caso_canton}', 'campo' => 'canton', 'descripcion' => 'Cantón'],
            ['clave' => '{caso_parroquia}', 'campo' => 'parroquia', 'descripcion' => 'Parroquia'],
            ['clave' => '{caso_direccion}', 'campo' => 'direccion', 'descripcion' => 'Dirección'],
            ['clave' => '{caso_telefono_domicilio}', 'campo' => 'telefono_domicilio', 'descripcion' => 'Teléfono domicilio'],
            ['clave' => '{caso_celulares}', 'campo' => 'celulares', 'descripcion' => 'Celulares'],
        ];
    }

    /**
     * Obtener palabras clave disponibles para una fase (requerimientos predefinidos + caso)
     * GET /getPlaceholdersByFase/{fase_id}
     */
    public function getPlaceholdersByFase($fase_id)
    {
        $log = new Funciones();
        try {
            // Obtener el tab_id de la fase
            $fase = Fase::find($fase_id);

            if (!$fase) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontró la fase', ''));
            }

            // Requerimientos predefinidos de esta fase (nombres únicos, sin duplicados por tipo_caso)
            $reqPredefinidos = Requerimientos::where('fase_id', $fase_id)
                ->where('estado', true)
                ->select('nombre', 'tipo')
                ->orderBy('orden')
                ->get()
                ->unique('nombre');

            $placeholdersReq = [];
            foreach ($reqPredefinidos as $req) {
                $placeholdersReq[] = [
                    'clave' => '{req:' . $req->nombre . '}',
                    'descripcion' => 'Requerimiento: ' . $req->nombre,
                    'tipo' => $req->tipo,
                    'categoria' => 'requerimiento',
                ];
            }
            $placeholdersReq = array_values($placeholdersReq);

            // Placeholders del caso (estáticos)
            $placeholdersCaso = [];
            foreach ($this->getCasoPlaceholders() as $p) {
                $placeholdersCaso[] = [
                    'clave' => $p['clave'],
                    'descripcion' => $p['descripcion'],
                    'tipo' => 'texto',
                    'categoria' => 'caso',
                ];
            }

            $data = [
                'caso' => $placeholdersCaso,
                'requerimientos' => $placeholdersReq,
            ];

            $log->logInfo(EmailDinamicoController::class, 'Se listaron los placeholders de la fase ID: ' . $fase_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Placeholders obtenidos', $data));
        } catch (Exception $e) {
            $log->logError(EmailDinamicoController::class, 'Error al obtener placeholders de la fase', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * Enviar correo dinámico con reemplazo de {req:...} y {caso_*}
     * Es igual a send_emailCambioFase pero con palabras clave dinámicas
     * POST /sendEmailDinamico/{caso_id}/{fase_id}
     */
    public function sendEmailDinamico($caso_id, $fase_id)
    {
        $log = new Funciones();
        try {
            $object = Email::where('fase_id', $fase_id)->first();

            $fase = Fase::find($fase_id);
            $caso = Caso::with('estadodos')->find($caso_id);

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
                    $log->logError(EmailDinamicoController::class, 'No existe un correo electrónico relacionado con esta fase');

                    return response()->json(RespuestaApi::returnResultado('error', 'No existe un correo electrónico relacionado con esta fase', ''));
                }
            }

            if (!$result || !$object) {
                $log->logError(EmailDinamicoController::class, 'No existe un correo electrónico relacionado con esta fase');

                return response()->json(RespuestaApi::returnResultado('error', 'No existe un correo electrónico relacionado con esta fase', ''));
            } else {

                // --- REEMPLAZO DE PLACEHOLDERS EN EL CUERPO ---
                $textoReemplazado = $object->cuerpo;

                // 1. Reemplazar placeholders originales (compatibilidad)
                $textoReemplazado = str_replace('{nombre_cliente}', $result->nombre_comercial, $textoReemplazado);
                $textoReemplazado = str_replace('{caso_id}', $caso_id, $textoReemplazado);
                $textoReemplazado = str_replace('{nombre_fase}', $fase->nombre, $textoReemplazado);

                // 2. Reemplazar placeholders de caso {caso_*}
                if ($caso) {
                    $casoPlaceholders = $this->getCasoPlaceholders();
                    foreach ($casoPlaceholders as $p) {
                        if (isset($p['relacion'])) {
                            $partes = explode('.', $p['relacion']);
                            $valor = $caso;
                            foreach ($partes as $parte) {
                                $valor = $valor?->{$parte};
                            }
                            $valor = $valor ?? '';
                        } else {
                            $valor = $caso->{$p['campo']} ?? '';
                        }
                        $log->logInfo(EmailDinamicoController::class, 'Placeholder: ' . $p['clave'] . ' => Valor: [' . $valor . ']');
                        $textoReemplazado = str_replace($p['clave'], $valor, $textoReemplazado);
                    }
                    $log->logInfo(EmailDinamicoController::class, 'Cuerpo despues de reemplazo caso: ' . $textoReemplazado);
                }

                // 3. Reemplazar placeholders de requerimientos {req:titulo}
                preg_match_all('/\{req:([^}]+)\}/', $textoReemplazado, $matchesReq);

                if (!empty($matchesReq[1])) {
                    $reqCaso = RequerimientoCaso::where('caso_id', $caso_id)->get();

                    foreach ($matchesReq[1] as $tituloReq) {
                        $req = $reqCaso->firstWhere('titulo', $tituloReq);

                        if ($req) {
                            $valor = $this->obtenerValorRequerimiento($req);
                        } else {
                            $valor = '';
                        }

                        $textoReemplazado = str_replace('{req:' . $tituloReq . '}', $valor, $textoReemplazado);
                    }
                }

                // Asignar el cuerpo reemplazado
                $object->cuerpo = $textoReemplazado;

                // --- CONSTRUIR LISTA DE DESTINATARIOS (igual que send_emailCambioFase) ---
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
                    $log->logError(EmailDinamicoController::class, 'No existen correos para enviar.');

                    return response()->json(RespuestaApi::returnResultado('error', 'No existen correos para enviar.', ''));
                }

                $log->logInfo(EmailDinamicoController::class, 'Correos enviados correctamente');

                return response()->json(RespuestaApi::returnResultado('success', "Correos enviados correctamente", $object));
            }
        } catch (Exception $e) {
            $log->logError(EmailDinamicoController::class, 'Error al enviar el correo electrónico en el cambio de fase', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    /**
     * Obtiene el valor de un requerimiento según su tipo_campo
     */
    private function obtenerValorRequerimiento($req)
    {
        switch ($req->tipo_campo) {
            case 'fecha':
                return $req->valor_date ?? '';
            case 'numero':
            case 'entero':
                return $req->valor_int ?? '';
            case 'decimal':
            case 'moneda':
                return $req->valor_decimal ?? '';
            case 'checkbox':
            case 'boolean':
                return $req->valor_boolean ? 'Sí' : 'No';
            case 'archivo':
            case 'pegar imagen':
                // valor_varchar tiene el path del archivo, valor guarda el titulo (no sirve)
                return $req->valor_varchar ?? '';
            case 'lista':
                // El valor seleccionado se guarda en valor_varchar via update($request->all())
                return $req->valor_varchar ?? $req->valor ?? '';
            default:
                // Para texto y cualquier otro tipo
                return $req->valor_varchar ?? $req->valor ?? $req->valor_int ?? $req->valor_decimal ?? $req->valor_date ?? '';
        }
    }
}

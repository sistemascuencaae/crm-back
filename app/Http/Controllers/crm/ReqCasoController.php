<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\credito\solicitudCreditoController;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\Audits;
use App\Models\crm\credito\SolicitudCredito;
use App\Models\crm\Galeria;
use App\Models\crm\RequerimientoCaso;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReqCasoController extends Controller
{
    // tipo archivo test
    public function editReqTipoFile(Request $request)
    {
        $log = new Funciones();

        $reqCaso = $request->input('reqCaso');
        $inputReq = json_decode($reqCaso);

        $tipoArchivo = $request->input('tipoArchivo');
        $requerimiento = RequerimientoCaso::where('id', $inputReq->id)->first();
        if (!$requerimiento) {
            return response()->json(RespuestaApi::returnResultado('error', 'El requerimiento no existe.', $inputReq->id));
        }
        try {
            $path = '';

            $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'NAS')
                ->first();

            if ($tipoArchivo == 'imagen_file') {
                if ($request->hasFile("imagen_file")) {
                    // $path = Storage::putFile("galerias", $request->file("imagen_file"));

                    $imagen = $request->file("imagen_file");
                    // $titulo = $imagen->getClientOriginalName();
                    $titulo = str_replace(' ', '-', $imagen->getClientOriginalName()); // Reemplazar espacios por -
                    $marcaTiempo = now()->format('YmdHis'); // Fecha con hora y segundos para no sobrescribir archivos previos

                    if ($parametro->nas == true) {
                        $path = Storage::disk('nas')->putFileAs("casos/" . $inputReq->caso_id . "/galerias", $imagen, $inputReq->caso_id . '-' . $marcaTiempo . '-' . $titulo);
                    } else {
                        $path = Storage::disk('local')->putFileAs("casos/" . $inputReq->caso_id . "/galerias", $imagen, $inputReq->caso_id . '-' . $marcaTiempo . '-' . $titulo);
                    }

                }
                $requerimiento->esimagen = true;
            }
            if ($tipoArchivo == 'imagen_file') {

                $galeria = Galeria::find($requerimiento->galerias_id);


                if ($galeria) {

                    // Obtener el old_values (valor antiguo)
                    $audit = new Audits();
                    $valorAntiguo = $galeria;
                    $audit->old_values = json_encode($valorAntiguo);

                    $galeria->update([
                        "titulo" => $requerimiento->titulo,
                        "descripcion" => $requerimiento->descripcion, //'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id.': '."\n". $requerimiento->descripcion,//$requerimiento->descripcion ? $requerimiento->descripcion : 'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id,
                        "imagen" => $path,
                        "caso_id" => $inputReq->caso_id,
                        "tipo_gal_id" => 8, // Tipo Requerimiento es el id 8
                        "sc_id" => 0,
                        "tab_id" => $requerimiento->tab_id,
                        "acc_publico" => $requerimiento->acc_publico,
                    ]);



                    // START Bloque de código que genera un registro de auditoría manualmente
                    $audit->user_id = Auth::id();
                    $audit->event = 'updated';
                    $audit->auditable_type = Galeria::class;
                    $audit->auditable_id = $galeria->id;
                    $audit->user_type = User::class;
                    $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                    $audit->url = $request->fullUrl();
                    // Establecer old_values y new_values
                    $audit->new_values = json_encode($galeria);
                    $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                    $audit->accion = 'editGaleriaReq';
                    $audit->caso_id = $galeria->caso_id;
                    $audit->save();
                    // END Auditoria

                } else {

                    $newGaleria = new Galeria();
                    $newGaleria->titulo = $requerimiento->titulo;
                    $newGaleria->descripcion = $requerimiento->descripcion; //'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id.': '. "\n". $requerimiento->descripcion;
                    $newGaleria->imagen = $path;
                    $newGaleria->caso_id = $inputReq->caso_id;
                    $newGaleria->tipo_gal_id = 8;
                    $newGaleria->sc_id = 0;
                    $newGaleria->tab_id = $requerimiento->tab_id;
                    $newGaleria->acc_publico = $requerimiento->acc_publico;

                    $newGaleria->save();
                    $requerimiento->galerias_id = $newGaleria->id;

                    // START Bloque de código que genera un registro de auditoría manualmente
                    $audit = new Audits();
                    $audit->user_id = Auth::id();
                    $audit->event = 'created';
                    $audit->auditable_type = Galeria::class;
                    $audit->auditable_id = $newGaleria->id;
                    $audit->user_type = User::class;
                    $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                    $audit->url = $request->fullUrl();
                    // Establecer old_values y new_values
                    $audit->old_values = json_encode($newGaleria);
                    $audit->new_values = json_encode([]);
                    $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                    $audit->accion = 'addGaleriaReq';
                    $audit->caso_id = $newGaleria->caso_id;
                    $audit->save();
                    // END Auditoria

                }
            }

            if ($tipoArchivo == 'archivo_file') {
                if ($request->hasFile("archivo_file")) {

                    $file = $request->file("archivo_file");
                    // $titulo = $file->getClientOriginalName();
                    $titulo = str_replace(' ', '-', $file->getClientOriginalName()); // Reemplazar espacios por -
                    $marcaTiempo = now()->format('YmdHis'); // Fecha con hora y segundos para no sobrescribir archivos previos

                    if ($parametro->nas == true) {
                        $path = Storage::disk('nas')->putFileAs("casos/" . $inputReq->caso_id . "/archivos", $file, $inputReq->caso_id . '-' . $marcaTiempo . '-' . $titulo); // guarda en el nas con el nombre original del archivo
                    } else {
                        $path = Storage::disk('local')->putFileAs("casos/" . $inputReq->caso_id . "/archivos", $file, $inputReq->caso_id . '-' . $marcaTiempo . '-' . $titulo);
                    }
                }
                $requerimiento->esimagen = false;

                $archivo = Archivo::find($requerimiento->archivos_id);

                if ($archivo) {

                    // Obtener el old_values (valor antiguo)
                    $audit = new Audits();
                    $valorAntiguo = $archivo;
                    $audit->old_values = json_encode($valorAntiguo);

                    $archivo->update([
                        "titulo" => $requerimiento->titulo,
                        "observacion" => $requerimiento->descripcion ? $requerimiento->descripcion : 'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id,
                        "archivo" => $path,
                        "caso_id" => $inputReq->caso_id,
                        "tipo" => 'Requerimiento',
                        "tab_id" => $requerimiento->tab_id,
                        "acc_publico" => $requerimiento->acc_publico,
                    ]);

                    // START Bloque de código que genera un registro de auditoría manualmente
                    $audit->user_id = Auth::id();
                    $audit->event = 'updated';
                    $audit->auditable_type = Archivo::class;
                    $audit->auditable_id = $archivo->id;
                    $audit->user_type = User::class;
                    $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                    $audit->url = $request->fullUrl();
                    // Establecer old_values y new_values
                    $audit->new_values = json_encode($archivo);
                    $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                    $audit->accion = 'editArchivoReq';
                    $audit->caso_id = $archivo->caso_id;
                    $audit->save();
                    // END Auditoria

                } else {
                    $newArchivo = new Archivo();
                    $newArchivo->titulo = $requerimiento->titulo;
                    $newArchivo->observacion = $requerimiento->descripcion ? $requerimiento->descripcion : 'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id;
                    $newArchivo->archivo = $path;
                    $newArchivo->caso_id = $inputReq->caso_id;
                    $newArchivo->tipo = 'Requerimiento';
                    $newArchivo->tab_id = $requerimiento->tab_id;
                    $newArchivo->acc_publico = $requerimiento->acc_publico;
                    $newArchivo->save();
                    $requerimiento->archivos_id = $newArchivo->id;

                    // START Bloque de código que genera un registro de auditoría manualmente
                    $audit = new Audits();
                    $audit->user_id = Auth::id();
                    $audit->event = 'created';
                    $audit->auditable_type = Archivo::class;
                    $audit->auditable_id = $newArchivo->id;
                    $audit->user_type = User::class;
                    $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                    $audit->url = $request->fullUrl();
                    // Establecer old_values y new_values
                    $audit->old_values = json_encode($newArchivo);
                    $audit->new_values = json_encode([]);
                    $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                    $audit->accion = 'addArchivoReq';
                    $audit->caso_id = $newArchivo->caso_id;
                    $audit->save();
                    // END Auditoria
                }
            }

            if ($inputReq->valor_int) {
                $requerimiento->valor_int = $inputReq->valor_int;
            }
            //print ($reqCaso['valor_int']);
            $requerimiento->valor_varchar = $path;
            $requerimiento->valor = $requerimiento->titulo;
            $requerimiento->marcado = true;
            $requerimiento->save();

            $requerimientos = RequerimientoCaso::where('id', $requerimiento->id)
                ->orderBy('id', 'asc')
                ->first();

            $log->logInfo(ReqCasoController::class, 'Se actualizo con exito el requerimiento, con el ID: ' . $inputReq->id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $requerimientos));
        } catch (Exception $e) {
            $log->logError(ReqCasoController::class, 'Error al actualizar el requerimiento, con el ID: ' . $inputReq->id, $e);

            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function edit(Request $request)
    {
        $log = new Funciones();

        $id = $request->input('id');
        try {
            $requerimiento = RequerimientoCaso::find($id);

            if ($requerimiento) {
                DB::transaction(function () use ($requerimiento, $request) {
                    if (($requerimiento->tipo_campo == 'archivo' || $requerimiento->tipo_campo == 'pegar imagen') && $requerimiento->galerias_id != null) {
                        $galeria = Galeria::find($requerimiento->galerias_id);
                        $galeria->update([
                            "descripcion" => $request->input('descripcion'),
                        ]);
                    }
                    if (($requerimiento->tipo_campo == 'archivo' || $requerimiento->tipo_campo == 'pegar imagen') && $requerimiento->archivos_id != null) {
                        $archivo = Archivo::find($requerimiento->archivos_id);
                        $archivo->update([
                            "observacion" => $request->input('descripcion'),
                        ]);
                    }

                    $audit = new Audits();
                    $valorAntiguo = $requerimiento;
                    $audit->old_values = json_encode($valorAntiguo);
                    $requerimiento->update($request->all());
                    // START Bloque de código que genera un registro de auditoría manualmente
                    $audit->user_id = Auth::id();
                    $audit->event = 'updated';
                    $audit->auditable_type = RequerimientoCaso::class;
                    $audit->auditable_id = $requerimiento->id;
                    $audit->user_type = User::class;
                    $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                    $audit->url = $request->fullUrl();
                    // Establecer old_values y new_values
                    $audit->new_values = json_encode($requerimiento);
                    $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                    $audit->accion = 'editRequerimiento';
                    $audit->caso_id = $requerimiento->caso_id;
                    $audit->save();
                    // END Auditoria
                });

                $reqCaso = RequerimientoCaso::where('caso_id', $request->input('caso_id'))
                    ->orderBy('id', 'asc')
                    ->get();

                $log->logInfo(ReqCasoController::class, 'Se actualizo con exito el requerimiento, con el ID: ' . $id);

                return response()->json(RespuestaApi::returnResultado('success', 'Actualizado con exito', $reqCaso));
            } else {
                $log->logError(ReqCasoController::class, 'El requerimiento no existe, con el ID: ' . $id);

                return response()->json(RespuestaApi::returnResultado('error', 'El requerimiento no existe.', $requerimiento));
            }
        } catch (Exception $e) {
            $log->logError(ReqCasoController::class, 'Error al actualizar el requerimiento, con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), ''));
        }
    }

    public function listAll($casoId)
    {
        $log = new Funciones();
        try {
            $reqFase = DB::select('SELECT * FROM crm.requerimientos_predefinidos  where fase_id = ?', [$casoId]);
            $log->logInfo(ReqCasoController::class, 'Se listo correctamente los requerimientos predefinidos');
        } catch (\Throwable $e) {
            $log->logError(ReqCasoController::class, 'Error al listar los requerimientos predefinidos', $e);
        }

        // echo ('$reqFase: ' . json_encode($reqFase));

        // try {
        //     $data = RequerimientoCaso::where('caso_id',$casoId)->get();
        //     return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        // } catch (Exception $e) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        // }
    }

    public function list()
    {
    }

    public function listReqCasoId($casoId)
    {
        try {
            $data = RequerimientoCaso::where('caso_id', $casoId)->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function uploadReqArchivo($inputFormData)
    {
        $log = new Funciones();
        try {
            if ($inputFormData->hasFile('file')) {
                $file = $inputFormData->file('file');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->storeAs('archivos', $fileName); // Almacenar en el almacenamiento de Laravel

                $log->logInfo(ReqCasoController::class, 'Archivo cargado correctamente');

                return response()->json(['message' => 'File uploaded successfully']);
            }

            $log->logError(ReqCasoController::class, 'No se ha subido ningún archivo');

            return response()->json(['message' => 'No file uploaded'], 400);
        } catch (\Throwable $e) {
            $log->logError(ReqCasoController::class, 'Error al cargar el archivo', $e);
        }
    }

    public function addSolicitudCreditoReqCaso(Request $request)
    {
        $log = new Funciones();
        try {
            $casoId = $request->input('caso_id');
            $archivo = $request->input('valor_varchar');

            $solicitudCreditoController = new solicitudCreditoController();

            $solicitudCredito = $solicitudCreditoController->obtenerSolicitudCreditoActualizada($casoId);

            $reqCaso = RequerimientoCaso::find($request->input('id'));
            $reqCaso->marcado = $request->input('marcado');
            $reqCaso->valor_int = $solicitudCredito->id;
            $reqCaso->valor = $archivo;
            $reqCaso->valor_varchar = $archivo;
            $reqCaso->save();

            $requerimientosCaso = RequerimientoCaso::where('caso_id', $casoId)
                ->orderBy('id', 'asc')
                ->get();

            $data = (object) [
                "reqCaso" => $requerimientosCaso,
                "solicitudCredito" => $solicitudCredito
            ];

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'created';
            $audit->auditable_type = SolicitudCredito::class;
            $audit->auditable_id = $solicitudCredito->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($solicitudCredito);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            // $audit->accion = 'addSolicitudCreditoReqCaso';
            $audit->accion = 'addSolicitudCredito';
            $audit->caso_id = $solicitudCredito->caso_id;
            $audit->save();
            //END Auditoria

            $log->logInfo(ReqCasoController::class, 'Se creo con exito la solicitud de credito en el caso: #' . $casoId);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ReqCasoController::class, 'Error al crear la solicitud de cro en el caso: #' . $casoId, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listaReqCasoId($casoId)
    {
        $log = new Funciones();
        try {
            $reqs = RequerimientoCaso::where('caso_id', $casoId)
                ->orderBy('id', 'asc')
                ->orderBy('orden', 'asc')
                ->get();

            $log->logInfo(ReqCasoController::class, 'Se listo con exito los requerimientos del caso: #' . $casoId);

            return response()->json(RespuestaApi::returnResultado('success', 'Datos obtenidos con exito', $reqs));
        } catch (Exception $e) {
            $log->logError(ReqCasoController::class, 'Error al listar los requerimientos del caso: #' . $casoId, $e);

            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), ''));
        }
    }

    // Resuelve el usu_id del ERP del usuario logueado (por usu_alias); si no hay coincidencia, cae al
    // usu_id del agente (empleado) asignado al cliente. Mismo patrón que ClienteController::solicitudCredito.
    private function resolverUsuIdSolicitud($cliId)
    {
        $usuAlias = optional(auth('api')->user())->usu_alias;
        $usuId = null;
        if ($usuAlias) {
            $u = DB::selectOne('SELECT usu_id FROM usuario WHERE UPPER(usu_alias) = UPPER(?) LIMIT 1', [$usuAlias]);
            $usuId = $u->usu_id ?? null;
        }
        if (!$usuId) {
            $a = DB::selectOne(
                'SELECT emp.usu_id FROM cliente cli INNER JOIN empleado emp ON emp.emp_id = cli.emp_id WHERE cli.cli_id = ?',
                [$cliId]
            );
            $usuId = $a->usu_id ?? null;
        }
        return $usuId;
    }

    // Requerimiento "solicitud credito vendedor": tras guardar el cliente (a crédito) en el modal de edición,
    // genera la solicitud (crm.fn_cliente_solicitud_credito = datos para imprimir), guarda el snapshot en la
    // auditoría de impresiones del cliente (crm.fn_solicitud_credito_guardar -> id) y marca el requerimiento
    // (marcado=true, valor_int = id de la solicitud). Crea un registro 'editRequerimiento' en crm.audits para
    // que aparezca en la bitácora. Devuelve { reqCaso (lista del caso), solicitud (filas a imprimir), impresion_id }.
    public function solicitudCreditoVendedorGenerar(Request $request)
    {
        $log = new Funciones();
        try {
            $casoId = $request->input('caso_id');
            $cliId  = $request->input('cli_id');
            $reqId  = $request->input('id');

            $usuId = $this->resolverUsuIdSolicitud($cliId);

            $solicitud = DB::select('SELECT * FROM crm.fn_cliente_solicitud_credito(?, ?)', [$cliId, $usuId]);
            if (empty($solicitud)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Verifique que el cliente sea a CRÉDITO y tenga toda su información completa.', ''));
            }

            // Teléfonos ACTIVOS del cliente (principal + adicionales) para la tabla "4) TELÉFONOS".
            $telefonos = DB::select('SELECT * FROM crm.fn_cliente_solicitud_credito_telefonos(?)', [$cliId]);

            $impresionId = optional(DB::selectOne(
                'SELECT crm.fn_solicitud_credito_guardar(?, ?, ?, ?) AS impresion_id',
                [$cliId, $usuId, auth('api')->id(), $casoId]
            ))->impresion_id;

            // El reporte en vivo no trae caso ni id de impresión; se inyectan para que la impresión muestre
            // "N°: <caso_id> - <id solicitud>" en la esquina sup. derecha.
            foreach ($solicitud as $s) {
                $s->caso_id = $casoId;
                $s->solicitud_id = $impresionId;
            }

            $reqCaso = RequerimientoCaso::find($reqId);
            if (!$reqCaso) {
                return response()->json(RespuestaApi::returnResultado('error', 'El requerimiento no existe.', $reqId));
            }

            $audit = new Audits();
            $audit->old_values = json_encode($reqCaso);

            $reqCaso->marcado = true;
            $reqCaso->valor_int = $impresionId;
            $reqCaso->save();

            // Auditoría (bitácora). accion 'editRequerimiento' + tipo_campo 'solicitud credito vendedor':
            // la bitácora detecta el tipo y muestra solo el mensaje resumido (sin old/new values).
            $audit->user_id = Auth::id();
            $audit->event = 'updated';
            $audit->auditable_type = RequerimientoCaso::class;
            $audit->auditable_id = $reqCaso->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip();
            $audit->url = $request->fullUrl();
            $audit->new_values = json_encode($reqCaso);
            $audit->user_agent = $request->header('User-Agent');
            $audit->accion = 'editRequerimiento';
            $audit->caso_id = $casoId;
            $audit->save();

            $requerimientosCaso = RequerimientoCaso::where('caso_id', $casoId)->orderBy('id', 'asc')->get();

            $data = (object) [
                'reqCaso'      => $requerimientosCaso,
                'solicitud'    => $solicitud,
                'telefonos'    => $telefonos,
                'impresion_id' => $impresionId,
            ];

            $log->logInfo(ReqCasoController::class, 'Solicitud crédito vendedor generada en el caso #' . $casoId . ', impresion_id ' . $impresionId);

            return response()->json(RespuestaApi::returnResultado('success', 'Solicitud generada con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ReqCasoController::class, 'Error al generar la solicitud crédito vendedor', $e);

            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), ''));
        }
    }

    // Reimpresión: trae el snapshot guardado (crm.solicitudes_credito) por su id, en el mismo shape que el
    // historial de solicitudes (reusa crm.fn_cliente_solicitud_credito_listar_paginacion), para reimprimir SOLO
    // esa solicitud. Los jsonb (referencias/telefonos/direcciones) se decodifican para el front.
    public function solicitudCreditoVendedorReimprimir(Request $request)
    {
        $log = new Funciones();
        try {
            $solicitudId = (int) $request->input('solicitud_id');

            $snap = $this->obtenerSnapshotSolicitudVendedor($solicitudId);
            if (!$snap) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontró la solicitud guardada.', ''));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Solicitud obtenida', $snap));
        } catch (Exception $e) {
            $log->logError(ReqCasoController::class, 'Error al reimprimir la solicitud crédito vendedor', $e);

            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), ''));
        }
    }

    // Snapshot guardado de una solicitud (header + referencias/telefonos/direcciones ya decodificados).
    // Compartido por solicitudCreditoVendedorReimprimir y solicitudCreditoVendedorPdf.
    private function obtenerSnapshotSolicitudVendedor(int $solicitudId)
    {
        $cab = DB::selectOne('SELECT cli_id FROM crm.solicitudes_credito WHERE id = ?', [$solicitudId]);
        if (!$cab) {
            return null;
        }

        $regs = DB::select(
            'SELECT * FROM crm.fn_cliente_solicitud_credito_listar_paginacion(?, ?, ?, ?)',
            [$cab->cli_id, 1, 100000, null]
        );

        foreach ($regs as $r) {
            if ((int) $r->id === $solicitudId) {
                $r->referencias = json_decode($r->referencias);
                $r->telefonos   = json_decode($r->telefonos);
                $r->direcciones = json_decode($r->direcciones);

                return $r;
            }
        }

        return null;
    }

    // Estado del PDF guardado de una solicitud (ojito): devuelve { existe, ruta }. El PDF NO se genera
    // aquí: lo captura y sube el FRONT desde el componente real <app-print-solicitud-credito> (fuente
    // ÚNICA del diseño — si cambia el diseño, solo se toca el frontend). Si 'existe' es false, el front
    // lo regenera con el snapshot (solicitudCreditoVendedorReimprimir) y lo sube (GuardarPdf).
    public function solicitudCreditoVendedorPdf(Request $request)
    {
        $log = new Funciones();
        try {
            $solicitudId = (int) $request->input('solicitud_id');

            $cab = DB::selectOne('SELECT caso_id FROM crm.solicitudes_credito WHERE id = ?', [$solicitudId]);
            if (!$cab) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontró la solicitud guardada.', ''));
            }

            $ruta = $this->rutaPdfSolicitudVendedor($solicitudId, $cab->caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Estado del PDF', [
                'existe' => Storage::disk($this->discoArchivosCaso())->exists($ruta),
                'ruta'   => $ruta,
            ]));
        } catch (\Throwable $e) {
            $log->logError(ReqCasoController::class, 'Error al consultar el PDF de la solicitud crédito vendedor', $e);

            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), ''));
        }
    }

    // Recibe el PDF capturado por el front (html2pdf sobre el componente real) y lo guarda como
    // archivo del caso en casos/<caso_id>/archivos/ (mismo disco NAS/local que los adjuntos).
    // Sobrescribe si ya existía (mismo snapshot => mismo contenido). Devuelve la ruta relativa.
    public function solicitudCreditoVendedorGuardarPdf(Request $request)
    {
        $log = new Funciones();
        try {
            $solicitudId = (int) $request->input('solicitud_id');
            if (!$request->hasFile('pdf')) {
                return response()->json(RespuestaApi::returnResultado('error', 'No llegó el archivo PDF.', ''));
            }

            $cab = DB::selectOne('SELECT caso_id FROM crm.solicitudes_credito WHERE id = ?', [$solicitudId]);
            if (!$cab) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontró la solicitud guardada.', ''));
            }

            $ruta = $this->rutaPdfSolicitudVendedor($solicitudId, $cab->caso_id);
            Storage::disk($this->discoArchivosCaso())->put($ruta, file_get_contents($request->file('pdf')->getRealPath()));

            return response()->json(RespuestaApi::returnResultado('success', 'PDF guardado', $ruta));
        } catch (\Throwable $e) {
            $log->logError(ReqCasoController::class, 'Error al guardar el PDF de la solicitud crédito vendedor', $e);

            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), ''));
        }
    }

    // Ruta relativa del PDF de una solicitud dentro del storage de adjuntos del caso.
    private function rutaPdfSolicitudVendedor(int $solicitudId, $casoId): string
    {
        return ($casoId ? 'casos/' . $casoId . '/archivos/' : 'solicitudes-credito/')
            . 'solicitud-credito-' . $solicitudId . '.pdf';
    }

    // Mismo criterio NAS/local que los adjuntos del caso (editReqTipoFile): crm.parametro 'NAS'.
    private function discoArchivosCaso(): string
    {
        $parametro = DB::table('crm.parametro')->where('abreviacion', 'NAS')->first();

        return ($parametro && $parametro->nas == true) ? 'nas' : 'local';
    }
}

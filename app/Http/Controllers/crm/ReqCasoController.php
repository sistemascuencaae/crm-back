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
                    $titulo = $imagen->getClientOriginalName();

                    if ($parametro->nas == true) {
                        $path = Storage::disk('nas')->putFileAs("casos/" . $inputReq->caso_id . "/galerias", $imagen, $inputReq->caso_id . '-' . $titulo);
                    } else {
                        $path = Storage::disk('local')->putFileAs("casos/" . $inputReq->caso_id . "/galerias", $imagen, $inputReq->caso_id . '-' . $titulo);
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
                    $titulo = $file->getClientOriginalName();


                    if ($parametro->nas == true) {
                        $path = Storage::disk('nas')->putFileAs("casos/" . $inputReq->caso_id . "/archivos", $file, $inputReq->caso_id . '-' . $titulo); // guarda en el nas con el nombre original del archivo
                    } else {
                        $path = Storage::disk('local')->putFileAs("casos/" . $inputReq->caso_id . "/archivos", $file, $inputReq->caso_id . '-' . $titulo);
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
            $data = RequerimientoCaso::where('caso_id', $casoId)->orderBy('orden', 'asc')->get();
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
}

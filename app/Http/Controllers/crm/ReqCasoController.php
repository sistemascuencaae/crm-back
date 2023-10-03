<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\credito\solicitudCreditoController;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\Audits;
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
    // tipo archivo
    public function editReqTipoFile(Request $request)
    {
        $reqCaso = $request->input('reqCaso');
        $inputReq = json_decode($reqCaso);
        $tipoArchivo = $request->input('tipoArchivo');
        $requerimiento = RequerimientoCaso::where('id', $inputReq->id)->first();
        if (!$requerimiento) {
            return response()->json(RespuestaApi::returnResultado('error', 'El requerimiento no existe.', $inputReq->id));
        }
        try {
            $path = '';

            if ($tipoArchivo == 'imagen_file') {
                if ($request->hasFile("imagen_file")) {
                    $path = Storage::putFile("galerias", $request->file("imagen_file"));
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
                        "descripcion" => $requerimiento->descripcion ? $requerimiento->descripcion : 'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id,
                        "imagen" => $path,
                        "caso_id" => $inputReq->caso_id,
                        "tipo_gal_id" => 1,
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
                    $audit->save();
                    // END Auditoria

                } else {
                    $newGaleria = new Galeria();
                    $newGaleria->titulo = $requerimiento->titulo;
                    $newGaleria->descripcion = $requerimiento->descripcion ? $requerimiento->descripcion : 'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id;
                    $newGaleria->imagen = $path;
                    $newGaleria->caso_id = $inputReq->caso_id;
                    $newGaleria->tipo_gal_id = 1;
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
                    $audit->save();
                    // END Auditoria

                }
            }

            if ($tipoArchivo == 'archivo_file') {
                if ($request->hasFile("archivo_file")) {
                    $path = Storage::putFile("archivos", $request->file("archivo_file"));
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
                    $audit->save();
                    // END Auditoria

                } else {
                    $newArchivo = new Archivo();
                    $newArchivo->titulo = $requerimiento->titulo;
                    $newArchivo->observacion = $requerimiento->descripcion ? $requerimiento->descripcion : 'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id;
                    $newArchivo->archivo = $path;
                    $newArchivo->caso_id = $inputReq->caso_id;
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

            // $requerimientosCaso = RequerimientoCaso::where('caso_id', $inputReq->caso_id)
            //     ->orderBy('id', 'asc')
            //     ->orderBy('id', 'asc')
            //     ->get();


            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $requerimiento));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', $th->getMessage(), $th));
        }
    }

    public function edit(Request $request)
    {
        try {

            $id = $request->input('id');

            $requerimiento = RequerimientoCaso::find($id);

            // Obtener el old_values (valor antiguo)
            $audit = new Audits();
            $valorAntiguo = $requerimiento;
            $audit->old_values = json_encode($valorAntiguo);

            if ($requerimiento) {
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
                $audit->save();
                // END Auditoria

                $reqCaso = RequerimientoCaso::where('caso_id', $request->input('caso_id'))
                    ->orderBy('id', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                return response()->json(RespuestaApi::returnResultado('success', 'Actualizado con exito', $reqCaso));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'El requerimiento no existe.', $requerimiento));
            }
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', $th->getMessage(), ''));
        }
    }

    public function listAll($casoId)
    {


        $reqFase = DB::select('SELECT * FROM crm.requerimientos_predefinidos  where fase_id = ?', [$casoId]);

        echo ('$reqFase: ' . json_encode($reqFase));

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

    public function uploadReqArchivo($inputFormData)
    {
        if ($inputFormData->hasFile('file')) {
            $file = $inputFormData->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('archivos', $fileName); // Almacenar en el almacenamiento de Laravel

            return response()->json(['message' => 'File uploaded successfully']);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    public function addSolicitudCreditoReqCaso(Request $request)
    {
        try {

            $casoId = $request->input('caso_id');
            $archivo = $request->input('valor_varchar');


            $solicitudCreditoController = new solicitudCreditoController();

            $solicitudCredito = $solicitudCreditoController->obtenerSolicitudCreditoActualizada($casoId);

            $reqCaso = RequerimientoCaso::find($request->input('id'));
            $reqCaso->marcado = true;
            $reqCaso->valor_int = $solicitudCredito->id;
            $reqCaso->valor = $archivo;
            $reqCaso->valor_varchar = $archivo;
            $reqCaso->save();

            $requerimientosCaso = RequerimientoCaso::where('caso_id', $casoId)
                ->orderBy('id', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $data = (object)[
                "reqCaso" => $requerimientosCaso,
                "solicitudCredito" => $solicitudCredito
            ];






            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'created';
            $audit->auditable_type = solicitudCredito::class;
            $audit->auditable_id = $solicitudCredito->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($solicitudCredito);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'addSolicitudCreditoReqCaso';
            $audit->save();
            //END Auditoria

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listaReqCasoId($casoId)
    {
        try {
            $reqs = RequerimientoCaso::where('caso_id', $casoId)
                ->orderBy('id', 'asc')
                ->orderBy('orden', 'asc')
                ->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Datos obtenidos con exito', $reqs));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', $th->getMessage(), ''));
        }
    }
}

<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
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

            if ($tipoArchivo == 'archivo_file') {

                $galeria = Galeria::find($requerimiento->galerias_id);
                if ($galeria) {
                    $galeria->update([
                        "titulo" => $requerimiento->titulo,
                        "descripcion" => 'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id,
                        "imagen" => $path,
                        "caso_id" => $inputReq->caso_id,
                        "tipo_gal_id" => 1,
                    ]);
                } else {
                    $newGaleria = new Galeria();
                    $newGaleria->titulo = $requerimiento->titulo;
                    $newGaleria->descripcion = 'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id;
                    $newGaleria->imagen = $path;
                    $newGaleria->caso_id = $inputReq->caso_id;
                    $newGaleria->tipo_gal_id = 1;
                    $newGaleria->save();
                    $requerimiento->galerias_id = $newGaleria->id;
                }
            }

            if ($tipoArchivo == 'archivo_file') {
                if ($request->hasFile("archivo_file")) {
                    $path = Storage::putFile("archivos", $request->file("archivo_file"));
                }
                $requerimiento->esimagen = false;

                $archivo = Archivo::find($requerimiento->archivos_id);

                if ($archivo) {
                    $archivo->update([
                        "titulo" => $requerimiento->titulo,
                        "observacion" => 'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id,
                        "archivo" => $path,
                        "caso_id" => $inputReq->caso_id,
                    ]);
                } else {
                    $newArchivo = new Archivo();
                    $newArchivo->titulo = $requerimiento->titulo;
                    $newArchivo->observacion = 'Requerimiento numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id;
                    $newArchivo->archivo = $path;
                    $newArchivo->caso_id = $inputReq->caso_id;
                    $newArchivo->save();
                    $requerimiento->archivos_id = $newArchivo->id;
                }

            }
            $requerimiento->valor_varchar = $path;
            $requerimiento->valor = $requerimiento->titulo;
            $requerimiento->descripcion = 'Requerimiento caso numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id;
            $requerimiento->marcado = true;
            $requerimiento->save();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $requerimiento));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', $th->getMessage(), ''));
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

                $reqCaso = RequerimientoCaso::where('caso_id', $request->input('caso_id'))->get();

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
    public function add()
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
}
<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Galeria;
use App\Models\crm\RequerimientoCaso;
use Exception;
use Illuminate\Http\Request;
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
        //echo ('$tipoArchivo : '.json_encode($tipoArchivo ));

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
                if ($request->hasFile("archivo_file")) {
                    $path = Storage::putFile("archivos", $request->file("archivo_file"));
                }
                $requerimiento->esimagen = false;
            }
            $requerimiento->valor_varchar = $path;
            $requerimiento->descripcion = 'Requerimiento caso numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id;
            $requerimiento->marcado = true;
            $requerimiento->save();
            $galeria = new Galeria();
            $galeria->titulo = $requerimiento->titulo;
            $galeria->descripcion = 'Requerimiento caso numero: ' . $requerimiento->id . ', caso numero: ' . $requerimiento->caso_id;
            $galeria->imagen = $path;
            $galeria->caso_id = $inputReq->caso_id;
            $galeria->tipo_gal_id = 4;
            $galeria->save();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $requerimiento));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', $th->getMessage(), ''));
        }
    }

    public function edit(Request $request)
    {
        try {
            $id = $request->input('id');

            $requerimiento = RequerimientoCaso::where('id', $id)->first();
            if ($requerimiento) {
                $requerimiento->descripcion = $request->input('descripcion');
                $requerimiento->caso_id = $request->input('caso_id');
                $requerimiento->created_at = $request->input('created_at');
                $requerimiento->updated_at = $request->input('updated_at');
                $requerimiento->deleted_at = $request->input('deleted_at');
                $requerimiento->marcado = $request->input('marcado');
                $requerimiento->estado = $request->input('estado');
                $requerimiento->tipo_req_id = $request->input('tipo_req_id');
                $requerimiento->user_requiere_id = $request->input('user_requiere_id');
                $requerimiento->titulo = $request->input('titulo');
                $requerimiento->tipo_campo = $request->input('tipo_campo');
                $requerimiento->requerido = $request->input('requerido');
                $requerimiento->valor_date = $request->input('valor_date');
                $requerimiento->valor_int = $request->input('valor_int');
                $requerimiento->valor_boolean = $request->input('valor_boolean');
                $requerimiento->valor_varchar = $request->input('valor_varchar');
                $requerimiento->valor_decimal = $request->input('valor_decimal');
                $requerimiento->html_render = $request->input('html_render');
                $requerimiento->valor = $request->input('valor');
                $requerimiento->form_control_name = $request->input('form_control_name');
                $requerimiento->valor_multiple = $request->input('valor_multiple');
                $requerimiento->orden = $request->input('orden');
                $requerimiento->valor_lista = $request->input('valor_lista');
                $requerimiento->esimagen = $request->input('esimagen');
                $requerimiento->save();

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
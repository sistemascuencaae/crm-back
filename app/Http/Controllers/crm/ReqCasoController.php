<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\RequerimientoCaso;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReqCasoController extends Controller
{



    public function editReqTipoFile(Request $request){
        $validatedData = $request->validate([
            'file' => 'required|file|mimes:jpeg,png,pdf|max:2048', // Ejemplo de validación, ajusta según tus necesidades
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('galerias', $filename); // Almacenar el archivo en la carpeta 'uploads'

            return response()->json(['message' => 'Archivo subido correctamente']);
        }

        return response()->json(['message' => 'No se encontró ningún archivo para subir'], 400);
    }


    public function listAll($casoId){


        $reqFase = DB::select('SELECT * FROM crm.requerimientos_predefinidos  where fase_id = ?',[$casoId]);

        echo ('$reqFase: '.json_encode($reqFase));

        // try {
        //     $data = RequerimientoCaso::where('caso_id',$casoId)->get();
        //     return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        // } catch (Exception $e) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        // }
    }
    public function list(){

    }
    public function add(){

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

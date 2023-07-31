<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class ArchivoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addArchivo(Request $request)
    {
        try {
            $file = $request->file("archivo");
            $titulo = $file->getClientOriginalName();

            $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA archivos
            $request->request->add(["archivo" => $path]); //Aqui obtenemos la ruta del archivo en la que se encuentra

            Archivo::create([
                "titulo" => $titulo,
                "observacion" => $request->observacion,
                "archivo" => $path,
                "caso_id" => $request->caso_id
            ]);

            // $data = DB::select('select * from crm.archivos where caso_id =' . $request->caso_id);
            $archivos = Archivo::where('caso_id', $request->caso_id)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $archivos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listArchivoByCasoId($caso_id)
    {
        try {
            $archivos = Archivo::orderBy("id", "desc")->where('caso_id', $caso_id)->get();

            return response()->json(
                RespuestaApi::returnResultado(
                    'success',
                    'Se listo con éxito',
                    $archivos->map(function ($archivo) {
                        return [
                            "id" => $archivo->id,
                            "titulo" => $archivo->titulo,
                            "observacion" => $archivo->observacion,
                            "archivo" => $archivo->archivo,
                            "caso_id" => $archivo->caso_id
                        ];
                    }),
                )
            );
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function updateArchivo(Request $request, $id)
    {
        try {
            $archivo = Archivo::findOrFail($id);

            // $file = $request->file("archivo");
            // $titulo = $file->getClientOriginalName();
            // if ($request->hasFile("archivo")) {
            //     if ($archivo->archivo) { //Aqui eliminamos la imagen anterior
            //         Storage::delete($archivo->archivo); //Aqui pasa la rta de la imagen para eliminarlo
            //     }
            //     $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA CATEGORIAS
            //     $request->request->add(["archivo" => $path]); //Aqui obtenemos la nueva ruta de la imagen al request
            // }

            $archivo->update([
                // "titulo" => $titulo,
                "observacion" => $request->observacion,
                // "archivo" => $path,
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $archivo));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteArchivo($id)
    {
        try {
            $archivo = Archivo::findOrFail($id);

            $url = str_replace("storage", "public", $archivo->archivo); //Reemplazamos la palabra storage por public (ruta de nuestra img public/galerias/name_img)
            Storage::delete($url); //Mandamos a borrar la foto de nuestra carpeta storage

            $archivo->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $archivo));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }

    }
}
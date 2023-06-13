<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use Error;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class ArchivoController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function store(Request $request)
    {
        try {
            $file = $request->file("archivo");
            $titulo = $file->getClientOriginalName();

            $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA CATEGORIAS
            $request->request->add(["archivo" => $path]); //Aqui obtenemos la ruta de la imagen en la que se encuentra

            $archivo = Archivo::create([
                "titulo" => $titulo,
                "observacion" => $request->observacion,
                // Falta guaradara este campo
                "archivo" => $path,
            ]);

            // $galeria = Archivo::create($request->all());

            $data = DB::select('select * from crm.archivos');

            // return response()->json(["archivo" => $data,]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo los archivos con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function index(Request $request)
    {
        try {
            $archivos = Archivo::orderBy("id", "desc")->get();

            // return response()->json([
            //     "archivos" => $archivos->map(function ($archivo) {
            //         return [
            //             "id" => $archivo->id,
            //             "titulo" => $archivo->titulo,
            //             "observacion" => $archivo->observacion,
            //             "archivo" => $archivo->archivo,
            //         ];
            //     }),
            // ]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                "archivos" => $archivos->map(function ($archivo) {
                    return [
                        "id" => $archivo->id,
                        "titulo" => $archivo->titulo,
                        "observacion" => $archivo->observacion,
                        "archivo" => $archivo->archivo,
                    ];
                }),
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function edit(Request $request, $id)
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

            return response()->json(["archivo" => $archivo,]);
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function destroy($id)
    {
        try {
            $archivo = Archivo::findOrFail($id);

            $url = str_replace("storage", "public", $archivo->archivo); //Reemplazamos la palabra storage por public (ruta de nuestra img public/galerias/name_img)
            Storage::delete($url); //Mandamos a borrar la foto de nuestra carpeta storage

            $archivo->delete();

            // return response()->json(["message" => 200]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito el archivo', $archivo));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }

    }
}
<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Models\crm\Archivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use TheSeer\Tokenizer\Exception;
use Tymon\JWTAuth\Providers\JWT\Provider;

class ArchivoController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function store(Request $request)
    {
        $file = $request->file("archivo");
        $titulo = $file->getClientOriginalName();

        $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA CATEGORIAS
        $request->request->add(["archivo" => $path]); //Aqui obtenemos la ruta de la imagen en la que se encuentra

        $archivo = Archivo::create([
            "titulo" => $titulo,
            "archivo" => $path,
        ]);

        return response()->json(["archivo" => $archivo,]);
    }

    public function index(Request $request)
    {
        $archivos = Archivo::orderBy("id", "desc")->get();

        return response()->json([
            "archivos" => $archivos->map(function ($archivo) {
                return [
                    "id" => $archivo->id,
                    "titulo" => $archivo->titulo,
                    "archivo" => $archivo->archivo,
                ];
            }),
        ]);
    }

    public function edit(Request $request, $id)
    {
        $archivo = Archivo::findOrFail($id);

        $file = $request->file("archivo");
        $titulo = $file->getClientOriginalName();
        if ($request->hasFile("archivo")) {
            if ($archivo->archivo) { //Aqui eliminamos la imagen anterior
                Storage::delete($archivo->archivo); //Aqui pasa la rta de la imagen para eliminarlo
            }
            $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA CATEGORIAS
            $request->request->add(["archivo" => $path]); //Aqui obtenemos la nueva ruta de la imagen al request
        }

        $archivo->update([
            "titulo" => $titulo,
            "archivo" => $path,
        ]);

        return response()->json(["archivo" => $archivo,]);
    }

    public function destroy($id)
    {
        $archivo = Archivo::findOrFail($id);

        $url = str_replace("storage", "public", $archivo->archivo); //Reemplazamos la palabra storage por public (ruta de nuestra img public/galerias/name_img)
        Storage::delete($url); //Mandamos a borrar la foto de nuestra carpeta storage

        $archivo->delete();

        return response()->json(["message" => 200]);
    }
}
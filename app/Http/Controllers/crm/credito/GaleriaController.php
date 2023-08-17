<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Galeria;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GaleriaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addGaleria(Request $request)
    {
        try {
            if ($request->hasFile("imagen_file")) {
                $path = Storage::putFile("galerias", $request->file("imagen_file")); //se va a guardar dentro de la CARPETA CATEGORIAS
                $request->request->add(["imagen" => $path]); //Aqui obtenemos la ruta de la imagen en la que se encuentra
            }

            $galeria = Galeria::create($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $galeria));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listGaleriaByCasoId($caso_id)
    {
        try {
            $galerias = Galeria::orderBy("id", "asc")->where('caso_id', $caso_id)->get();

            // return response()->json([
            //     "imagenes" => $imagenes,]);

            // return response()->json([
            //     "imagenes" => $galerias->map(function ($galeria) {
            //         return [
            //             "id" => $galeria->id,
            //             "titulo" => $galeria->titulo,
            //             "descripcion" => $galeria->descripcion,
            //             // "imagen" => env("APP_URL") . "storage/app/public/" . $imagen->imagen,
            //             "imagen" => $galeria->imagen,
            //             "caso_id" => $galeria->caso_id,
            //             "tipo_gal_id" => $galeria->tipo_gal_id
            //         ];
            //     }),
            // ]);


            return response()->json(
                RespuestaApi::returnResultado(
                    'success',
                    'Se listo con éxito',
                    $galerias->map(function ($galeria) {
                        return [
                            "id" => $galeria->id,
                            "titulo" => $galeria->titulo,
                            "descripcion" => $galeria->descripcion,
                            "imagen" => $galeria->imagen,
                            "caso_id" => $galeria->caso_id,
                            "tipo_gal_id" => $galeria->tipo_gal_id,
                            "sc_id" => $galeria->sc_id,
                        ];
                    })
                )
            );
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }

    }

    public function updateGaleria(Request $request, $id)
    {
        try {
            $galeria = Galeria::findOrFail($id);

            if ($request->hasFile("imagen_file")) {
                if ($galeria->imagen) { //Aqui eliminamos la imagen anterior
                    Storage::delete($galeria->imagen); //Aqui pasa la rta de la imagen para eliminarlo
                }
                $path = Storage::putFile("galerias", $request->file("imagen_file")); //se va a guardar dentro de la CARPETA CATEGORIAS
                $request->request->add(["imagen" => $path]); //Aqui obtenemos la nueva ruta de la imagen al request
            }

            $galeria->update($request->all());

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $galeria));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteGaleria($id)
    {
        try {
            $galeria = Galeria::findOrFail($id);

            $url = str_replace("storage", "public", $galeria->imagen); //Reemplazamos la palabra storage por public (ruta de nuestra img public/galerias/name_img)
            Storage::delete($url); //Mandamos a borrar la foto de nuestra carpeta storage

            $galeria->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $galeria));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listGaleriaBySolicitudCreditoId($sc_id)
    {
        try {
            $ultimaFoto = Galeria::where('sc_id', $sc_id)->latest('id')->first();

            return response()->json(
                RespuestaApi::returnResultado(
                    'success',
                    'Se listo con éxito',
                    $ultimaFoto
                )
            );
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


}
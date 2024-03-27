<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\Galeria;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TutorialController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listTutoriales()
    {
        $log = new Funciones();
        try {
            $tutorialesGaleria = Galeria::where('tipo_gal_id', 11)->get();
            $tutorialesArchivo = Archivo::where('tipo', 'Tutorial')->get();

            $tutoriales = $tutorialesGaleria->merge($tutorialesArchivo);

            $log->logInfo(TutorialController::class, 'Se listo con exito los tutoriales');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tutoriales));
        } catch (Exception $e) {
            $log->logError(TutorialController::class, 'Error al listar los tutoriales', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addGaleriaTutorial(Request $request)
    {
        try {
            DB::transaction(function () use ($request) {

                $archivos = $request->file("archivos");
                // $archivosGuardados = [];

                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'NAS')
                    ->first();

                foreach ($archivos as $archivoData) {

                    // Fecha actual
                    $fechaActual = Carbon::now();

                    // Formatear la fecha en formato deseado
                    // $fechaFormateada = $fechaActual->format('Y-m-d H-i-s');

                    // Reemplazar los dos puntos por un guion medio (NO permite windows guardar con los : , por eso se le pone el - )
                    $fecha_actual = str_replace(':', '-', $fechaActual);

                    $nombreUnico = $archivoData->getClientOriginalName();
                    $extension = $archivoData->getClientOriginalExtension();

                    if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'mov', 'MOV', 'mkv', 'MKV'])) {

                        if ($parametro->nas == true) {
                            $path = Storage::disk('nas')->putFileAs("tutoriales/galerias", $archivoData, $fecha_actual . '-' . $nombreUnico);
                        } else {
                            $path = Storage::disk('local')->putFileAs("tutoriales/galerias", $archivoData, $fecha_actual . '-' . $nombreUnico);
                        }

                        $nuevaImagen = Galeria::create([
                            "titulo" => 'Tutorial - ' . $nombreUnico,
                            "descripcion" => 'Tutorial - ' . $nombreUnico,
                            "imagen" => $path,
                            // "caso_id" => $caso_id,
                            "tipo_gal_id" => 11, // 11 porque es tipo galeria 'Tutorial'
                            "sc_id" => 0,
                        ]);

                        // $archivosGuardados[] = $nuevaImagen;
                    } else {

                        if ($parametro->nas == true) {
                            $path = Storage::disk('nas')->putFileAs("tutoriales/archivos", $archivoData, $fecha_actual . '-' . $nombreUnico);
                        } else {
                            $path = Storage::disk('local')->putFileAs("tutoriales/archivos", $archivoData, $fecha_actual . '-' . $nombreUnico);
                        }

                        $nuevoArchivo = Archivo::create([
                            "titulo" => 'Tutorial - ' . $nombreUnico,
                            "observacion" => 'Tutorial - ' . $nombreUnico,
                            "archivo" => $path,
                            // "caso_id" => $caso_id,
                            "tipo" => 'Tutorial'
                        ]);

                        // $archivosGuardados[] = $nuevoArchivo;
                    }

                }

            });

            $tutorialesGaleria = Galeria::where('tipo_gal_id', 11)->get();
            $tutorialesArchivo = Archivo::where('tipo', 'Tutorial')->get();
            // return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $archivosGuardados));

            $tutoriales = $tutorialesGaleria->merge($tutorialesArchivo);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $tutoriales));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
            // return $e;
        }
    }

    public function editGaleriaTutorial(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $galeria = Galeria::find($id);

            $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'NAS')
                ->first();

            if ($request->hasFile("imagen_file")) {
                if ($galeria->imagen) {
                    if ($parametro->nas == true) {
                        // Eliminamos la imagen anterior del disco NAS
                        Storage::disk('nas')->delete($galeria->imagen);
                    } else {
                        // Eliminamos la imagen anterior del disco NAS
                        Storage::disk('local')->delete($galeria->imagen);
                    }
                }

                // Obtener el nuevo archivo de imagen y su nombre original
                $nuevaImagen = $request->file("imagen_file");
                $titulo = $nuevaImagen->getClientOriginalName();

                // Fecha actual
                $fechaActual = Carbon::now();

                // Formatear la fecha en formato deseado
                // $fechaFormateada = $fechaActual->format('Y-m-d H-i-s');

                // Reemplazar los dos puntos por un guion medio (NO permite windows guardar con los : , por eso se le pone el - )
                $fecha_actual = str_replace(':', '-', $fechaActual);

                if ($parametro->nas == true) {
                    // Guardar la nueva imagen en el disco NAS con su nombre original
                    // $path = Storage::disk('nas')->putFileAs("casos/" . $galeria->caso_id . "/galerias", $nuevaImagen, $galeria->caso_id . '-' . $fecha_actual . '-' . $titulo);
                    $path = Storage::disk('nas')->putFileAs("tutoriales/galerias", $nuevaImagen, $fecha_actual . '-' . $titulo);
                } else {
                    // Guardar la nueva imagen en el disco NAS con su nombre original
                    // $path = Storage::disk('local')->putFileAs("casos/" . $galeria->caso_id . "/galerias", $nuevaImagen, $galeria->caso_id . '-' . $fecha_actual . '-' . $titulo);
                    $path = Storage::disk('local')->putFileAs("tutoriales/galerias", $nuevaImagen, $fecha_actual . '-' . $titulo);
                }

                $request->request->add(["imagen" => $path]); // Obtener la nueva ruta de la imagen en la solicitud
            }

            $galeria->update($request->all());

            $log->logInfo(TutorialController::class, 'Se actualizo con exito la imagen, con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $galeria));
        } catch (Exception $e) {
            $log->logError(TutorialController::class, 'Error al actualizar la imagen, con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteGaleriaTutorial(Request $request, $id)
    {
        try {
            $galeria = Galeria::findOrFail($id);

            // $url = str_replace("storage", "public", $galeria->imagen); //Reemplazamos la palabra storage por public (ruta de nuestra img public/galerias/name_img)
            // Storage::delete($url); //Mandamos a borrar la foto de nuestra carpeta storage

            $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'NAS')
                ->first();

            if ($parametro->nas == true) {
                Storage::disk('nas')->delete($galeria->imagen); //Mandamos a borrar la foto de nuestra carpeta storage
            } else {
                Storage::disk('local')->delete($galeria->imagen); //Mandamos a borrar la foto de nuestra carpeta storage
            }

            $galeria->delete();

            $tutorialesGaleria = Galeria::where('tipo_gal_id', 11)->get();
            $tutorialesArchivo = Archivo::where('tipo', 'Tutorial')->get();
            // return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $archivosGuardados));

            $tutoriales = $tutorialesGaleria->merge($tutorialesArchivo);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $tutoriales));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}
<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\Audits;
use App\Models\crm\Galeria;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SoporteController extends Controller
{
    // Este metodo lo llamo en el caso, cuando se crea un caso desde SOPORTE
    public function addGaleriaArchivos($request, $caso_id)
    {
        try {
            DB::transaction(function () use ($request, $caso_id) {

                $archivos = $request->file("archivos");
                // $archivosGuardados = [];

                foreach ($archivos as $archivoData) {
                    $nombreUnico = $caso_id . '-' . $archivoData->getClientOriginalName();
                    $extension = $archivoData->getClientOriginalExtension();


                    if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])) {
                        $path = Storage::disk('nas')->putFileAs($caso_id . "/galerias", $archivoData, $nombreUnico);

                        $nuevaImagen = Galeria::create([
                            "titulo" => 'Imagen Soporte - ' . $caso_id,
                            "descripcion" => 'Imagen Soporte - ' . $caso_id,
                            "imagen" => $path,
                            "caso_id" => $caso_id,
                            "tipo_gal_id" => 7,
                            "sc_id" => 0,
                        ]);

                        // $archivosGuardados[] = $nuevaImagen;
                    } else {
                        $path = Storage::disk('nas')->putFileAs($caso_id . "/archivos", $archivoData, $nombreUnico);
                        $nuevoArchivo = Archivo::create([
                            "titulo" => $nombreUnico,
                            "observacion" => 'Archivo Soporte - ' . $caso_id,
                            "archivo" => $path,
                            "caso_id" => $caso_id,
                            "tipo" => 'Formulario de Soporte'
                        ]);

                        // $archivosGuardados[] = $nuevoArchivo;
                    }

                    $audit = new Audits();
                    $audit->user_id = Auth::id();
                    $audit->event = 'created';
                    $audit->auditable_type = (isset($nuevaImagen)) ? Galeria::class : Archivo::class;
                    $audit->auditable_id = (isset($nuevaImagen)) ? $nuevaImagen->id : $nuevoArchivo->id;
                    $audit->user_type = User::class;
                    $audit->ip_address = $request->ip();
                    $audit->url = $request->fullUrl();
                    $audit->old_values = json_encode((isset($nuevaImagen)) ? $nuevaImagen : $nuevoArchivo);
                    $audit->new_values = json_encode([]);
                    $audit->user_agent = $request->header('User-Agent');
                    $audit->accion = (isset($nuevaImagen)) ? 'addGaleria' : 'addArchivo';
                    $audit->save();
                }

            });

            // $archivos = Archivo::where('caso_id', $request->caso_id)->get();
            // return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $archivosGuardados));
            // return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', ''));

        } catch (Exception $e) {
            // return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
            return $e;
        }
    }

}
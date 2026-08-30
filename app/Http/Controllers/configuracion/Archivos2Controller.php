<?php

namespace App\Http\Controllers\configuracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\configuracion\Archivos2;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class Archivos2Controller extends Controller
{
    public function __construct()
    {
    }

    public function listAllArchivos2()
    {
        try {
            // este select es solo por ahora
            // $data = DB::select("SELECT * FROM crm.archivos2");
            $data = Archivos2::get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function addArchivos2(Request $request)
    {
        try {
            $archivos = DB::transaction(function () use ($request) {

                $archivos = $request->file("archivos"); // Acceder a los archivos utilizando la clave "archivos"
                $archivosGuardados = [];

                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'NAS')
                    ->first();

                foreach ($archivos as $archivoData) {

                    // Fecha actual para agregar a la imagen
                    $fechaActual = Carbon::now()->format('Y-m-d');
                    $fecha_actual = str_replace(':', '-', $fechaActual); // Reemplazar ':' para evitar problemas en los nombres de archivo

                    $nombreUnico = str_replace(' ', '-', $archivoData->getClientOriginalName()); // Obtener el nombre único del archivo / Reemplazar espacios por -

                    if ($parametro->nas == true) {
                        $path = Storage::disk('nas')->putFileAs("archivos/formularios", $archivoData, $fecha_actual . '-' . $nombreUnico); // Guardar el archivo
                    } else {
                        $path = Storage::disk('local')->putFileAs("archivos/formularios", $archivoData, $fecha_actual . '-' . $nombreUnico); // Guardar el archivo
                    }

                    $nuevoArchivo = Archivos2::create([
                        "titulo" => $nombreUnico,
                        "descripcion" => $request->input("descripcion")[0],
                        // Acceder a la observación de cada archivo
                        "url" => $path,
                        // "categoria" => 'Caso',
                    ]);

                    $archivosGuardados[] = $nuevoArchivo;
                }

                $data = Archivos2::orderBy('id', 'desc')->get();

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $archivos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteArchivos2($id)
    {
        $archivoPath = ''; // Initialize the variable
        $data = null; // Initialize $data to ensure it's available in catch block
    
        $parametro = DB::table('crm.parametro')
            ->where('abreviacion', 'NAS')
            ->first();
    
        try {
            // Start the transaction and assign the result to $data
            $data = DB::transaction(function () use ($id, &$archivoPath, $parametro) {
                $archivo = Archivos2::findOrFail($id);
    
                // Get the old values (old value)
                $valorAntiguo = $archivo;
    
                // Store the file path before attempting to delete it
                $archivoPath = $archivo->url;
    
                if ($parametro->nas == true) {
                    // Try to get the content of the file
                    $archivoNas = Storage::disk('nas')->get($archivoPath);
                } else {
                    // Try to get the content of the file
                    $archivoNas = Storage::disk('local')->get($archivoPath);
                }
    
                // Delete the file from the database
                $archivo->delete();
    
                return $archivo; // Return the deleted file record
            });
    
            // If the transaction is successful, delete the file permanently
            if ($parametro->nas == true) {
                Storage::disk('nas')->delete($archivoPath);
            } else {
                Storage::disk('local')->delete($archivoPath);
            }
    
            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $data));
        } catch (Exception $e) {
            // In case of error, restore the file from the temporary variable
            if (!empty($archivoPath)) {
                if ($parametro->nas == true) {
                    Storage::disk('nas')->put($archivoPath, $data);
                } else {
                    Storage::disk('local')->put($archivoPath, $data);
                }
            }
    
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
    

}

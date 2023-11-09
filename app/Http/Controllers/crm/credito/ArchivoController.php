<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\Audits;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;
use Log;

class ArchivoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addArrayArchivos(Request $request, $caso_id)
    {
        try {
            $archivos = $request->file("archivos"); // Acceder a los archivos utilizando la clave "archivos"
            $archivosGuardados = [];

            foreach ($archivos as $archivoData) {
                $nombreUnico = $caso_id . '-' . $archivoData->getClientOriginalName(); // Obtener el nombre único del archivo

                $path = Storage::disk('nas')->putFileAs($caso_id . "/archivos", $archivoData, $nombreUnico); // Guardar el archivo

                $nuevoArchivo = Archivo::create([
                    "titulo" => $nombreUnico,
                    "observacion" => $request->input("observaciones")[0],
                    // Acceder a la observación de cada archivo
                    "archivo" => $path,
                    "caso_id" => $caso_id
                ]);

                $archivosGuardados[] = $nuevoArchivo;
                // START Bloque de código que genera un registro de auditoría manualmente
                $audit = new Audits();
                $audit->user_id = Auth::id();
                $audit->event = 'created';
                $audit->auditable_type = Archivo::class;
                $audit->auditable_id = $nuevoArchivo->id;
                $audit->user_type = User::class;
                $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                $audit->url = $request->fullUrl();
                // Establecer old_values y new_values
                $audit->old_values = json_encode($nuevoArchivo);
                $audit->new_values = json_encode([]);
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                $audit->accion = 'addArchivo';
                $audit->save();
                // END Auditoria
            }

            $archivos = Archivo::where('caso_id', $request->caso_id)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardaron con éxito', $archivos));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function addArchivo(Request $request, $caso_id)
    {
        try {
            $file = $request->file("archivo");
            $titulo = $file->getClientOriginalName();

            // $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA archivos
            $path = Storage::disk('nas')->putFileAs($caso_id . "/archivos", $file, $caso_id . '-' . $titulo); // guarda en el nas con el nombre original del archivo

            $request->request->add(["archivo" => $path]); //Aqui obtenemos la ruta del archivo en la que se encuentra

            $archivo = Archivo::create([
                "titulo" => $caso_id . '-' . $titulo,
                "observacion" => $request->observacion,
                "archivo" => $path,
                "caso_id" => $request->caso_id
            ]);

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'created';
            $audit->auditable_type = Archivo::class;
            $audit->auditable_id = $archivo->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($archivo);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'addArchivo';
            $audit->save();
            // END Auditoria

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
                            "tipo" => $archivo->tipo,
                            "archivo" => $archivo->archivo,
                            "caso_id" => $archivo->caso_id,
                            "created_at" => $archivo->created_at,
                            "updated_at" => $archivo->updated_at,
                        ];
                    }),
                )
            );
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editArchivo(Request $request, $id)
    {
        try {
            $archivo = Archivo::findOrFail($id);

            // Obtener el old_values (valor antiguo)
            $valorAntiguo = $archivo->observacion;

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

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'updated';
            $audit->auditable_type = Archivo::class;
            $audit->auditable_id = $archivo->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode(['observacion' => $valorAntiguo]); // json_encode para convertir en string ese array
            $audit->new_values = json_encode(['observacion' => $archivo->observacion]); // json_encode para convertir en string ese array
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'editArchivo';
            $audit->save();
            // END Auditoria

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $archivo));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteArchivo(Request $request, $id)
    {
        try {
            $archivo = Archivo::findOrFail($id);
            // Obtener el old_values (valor antiguo)
            $valorAntiguo = $archivo;

            // $url = str_replace("storage", "public", $archivo->archivo); //Reemplazamos la palabra storage por public (ruta de nuestra img public/galerias/name_img)
            // Storage::delete($url); //Mandamos a borrar la foto de nuestra carpeta storage

            Storage::disk('nas')->delete($archivo->archivo); //Mandamos a borrar la foto de nuestra carpeta storage

            $archivo->delete();

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'deleted';
            $audit->auditable_type = Archivo::class;
            $audit->auditable_id = $archivo->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($valorAntiguo);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'deleteArchivo';
            $audit->save();
            // END Auditoria

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $archivo));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }

    }


    /////////////////////////////////////////////////  DOCUMENTOS EQUIFAX  /////////////////////////////////////////////////////////////////////

    // Lista todos los archivos que esten en la carpeta archivos_sin_firma del NAS
    public function listArchivosSinFirmaEquifaxByCasoId($caso_id)
    {
        try {
            $data = DB::transaction(function () use ($caso_id) {
                $folderPath = $caso_id . "/archivos_sin_firma"; // Ruta de la carpeta en tu NAS

                // Obtén los nombres de archivos del sistema de archivos (NAS)
                $archivosNAS = Storage::disk('nas')->files($folderPath);

                // Busca archivos en la base de datos que coincidan con los nombres de archivos en la carpeta NAS
                return Archivo::whereIn('archivo', $archivosNAS)->orderBy('archivo', 'ASC')->get();
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // Agrega archivos para mandar a firmar en la carpeta archivos_sin_firmar
    public function addArchivosEquifax(Request $request, $caso_id)
    {
        try {
            $data = DB::transaction(function () use ($request, $caso_id) {
                $archivos = $request->file("archivos"); // Acceder a los archivos utilizando la clave "archivos"
                $archivosGuardados = [];

                foreach ($archivos as $archivoData) {
                    $nombreBase = $caso_id . '-' . $archivoData->getClientOriginalName(); // Nombre base del archivo

                    $path = $caso_id . "/archivos_sin_firma";

                    $titulo = $nombreBase;

                    $i = 1;
                    while (Storage::disk('nas')->exists("$path/$titulo")) {
                        // Si el archivo con el mismo nombre ya existe, ajusta el nombre
                        $info = pathinfo($nombreBase);
                        $titulo = $info['filename'] . " ($i)." . $info['extension'];
                        $i++;
                    }

                    $path = Storage::disk('nas')->putFileAs($path, $archivoData, $titulo); // Guardar el archivo

                    $nuevoArchivo = Archivo::create([
                        "titulo" => $titulo,
                        "observacion" => $request->input("observaciones")[0],
                        "archivo" => $path,
                        "caso_id" => $caso_id
                    ]);

                    $archivosGuardados[] = $nuevoArchivo;
                }

                return $archivosGuardados;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editArchivosEquifax(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {
                $archivo = Archivo::findOrFail($id);

                if ($request->hasFile("archivo")) {
                    $file = $request->file("archivo");
                    $originalTitulo = $file->getClientOriginalName();
                    $nombreBase = $archivo->caso_id . '-' . $originalTitulo;

                    $path = $archivo->caso_id . "/archivos_sin_firma";

                    $titulo = $nombreBase;

                    $i = 1;
                    while (Storage::disk('nas')->exists("$path/$titulo")) {
                        // Si el archivo con el mismo nombre ya existe, ajusta el nombre
                        $info = pathinfo($nombreBase);
                        $titulo = $info['filename'] . " ($i)." . $info['extension'];
                        $i++;
                    }

                    $path = Storage::disk('nas')->putFileAs($path, $file, $titulo);

                    // Puedes eliminar el archivo anterior si es necesario
                    if ($archivo->archivo) {
                        Storage::disk('nas')->delete($archivo->archivo);
                    }

                    $archivo->update([
                        "titulo" => $titulo,
                        "observacion" => $request->input("observacion"),
                        "archivo" => $path,
                    ]);

                    return $archivo;
                } else {
                    return response()->json(RespuestaApi::returnResultado('error', 'Error', 'No se ha cargado un archivo.'));
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // Lista de documentos de equifax FIRMADOS

    public function listArchivosEquifaxFirmadosByCasoId($caso_id)
    {
        try {
            $data = DB::transaction(function () use ($caso_id) {
                return Archivo::orderBy("id", "desc")->where('caso_id', $caso_id)->where('tipo', 'equifax')->get();
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}

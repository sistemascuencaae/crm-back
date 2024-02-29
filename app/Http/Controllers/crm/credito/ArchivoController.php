<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\Audits;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class ArchivoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addArrayArchivos(Request $request, $caso_id)
    {
        $log = new Funciones();
        try {
            $archivos = DB::transaction(function () use ($request, $caso_id) {

                $archivos = $request->file("archivos"); // Acceder a los archivos utilizando la clave "archivos"
                $archivosGuardados = [];

                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'NAS')
                    ->first();

                foreach ($archivos as $archivoData) {
                    $nombreUnico = $caso_id . '-' . $archivoData->getClientOriginalName(); // Obtener el nombre único del archivo

                    if ($parametro->nas == true) {
                        $path = Storage::disk('nas')->putFileAs($caso_id . "/archivos", $archivoData, $nombreUnico); // Guardar el archivo
                    } else {
                        $path = Storage::disk('local')->putFileAs($caso_id . "/archivos", $archivoData, $nombreUnico); // Guardar el archivo
                    }

                    $nuevoArchivo = Archivo::create([
                        "titulo" => $nombreUnico,
                        "observacion" => $request->input("observaciones")[0],
                        // Acceder a la observación de cada archivo
                        "archivo" => $path,
                        "caso_id" => $caso_id,
                        "tipo" => 'Caso',
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
                    $audit->caso_id = $nuevoArchivo->caso_id;
                    $audit->save();
                    // END Auditoria
                }

                $data = Archivo::where('caso_id', $request->caso_id)->orderBy('id', 'desc')->get();

                // // Formatear las fechas
                // $data->transform(function ($item) {
                //     $item->formatted_updated_at = Carbon::parse($item->updated_at)->format('Y-m-d H:i:s');
                //     $item->formatted_created_at = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
                //     return $item;
                // });

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at'];
                // Utilizar la función map para transformar y obtener una nueva colección
                $data->map(function ($item) use ($dateFields) {
                    // $this->formatoFechaItem($item, $dateFields);
                    $funciones = new Funciones();
                    $funciones->formatoFechaItem($item, $dateFields);
                    return $item;
                });

                return $data;
            });

            $log->logInfo(ArchivoController::class, 'Se guardaron con exito los archivos en el caso: #' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $archivos));

        } catch (Exception $e) {
            $log->logError(ArchivoController::class, 'Error al guardar los archivos en el caso: #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error excepcion', $e));
        }
    }

    public function addArchivo(Request $request, $caso_id)
    {
        $log = new Funciones();
        try {
            $archivos = DB::transaction(function () use ($request, $caso_id) {

                $file = $request->file("archivo");
                $titulo = $file->getClientOriginalName();

                // $path = Storage::putFile("archivos", $request->file("archivo")); //se va a guardar dentro de la CARPETA archivos
                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'NAS')
                    ->first();

                if ($parametro->nas == true) {
                    $path = Storage::disk('nas')->putFileAs($caso_id . "/archivos", $file, $caso_id . '-' . $titulo); // guarda en el nas con el nombre original del archivo
                } else {
                    $path = Storage::disk('local')->putFileAs($caso_id . "/archivos", $file, $caso_id . '-' . $titulo); // guarda en el nas con el nombre original del archivo
                }

                $request->request->add(["archivo" => $path]); //Aqui obtenemos la ruta del archivo en la que se encuentra

                $archivo = Archivo::create([
                    "titulo" => $caso_id . '-' . $titulo,
                    "observacion" => $request->observacion,
                    "archivo" => $path,
                    "caso_id" => $request->caso_id,
                    "tipo" => 'Caso',
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
                $audit->caso_id = $archivo->caso_id;
                $audit->save();
                // END Auditoria

                // $data = DB::select('select * from crm.archivos where caso_id =' . $request->caso_id);
                $data = Archivo::where('caso_id', $request->caso_id)->orderBy('id', 'desc')->get();

                // // Formatear las fechas
                // $data->transform(function ($item) {
                //     $item->formatted_updated_at = Carbon::parse($item->updated_at)->format('Y-m-d H:i:s');
                //     $item->formatted_created_at = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
                //     return $item;
                // });

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at'];
                // Utilizar la función map para transformar y obtener una nueva colección
                $data->map(function ($item) use ($dateFields) {
                    // $this->formatoFechaItem($item, $dateFields);
                    $funciones = new Funciones();
                    $funciones->formatoFechaItem($item, $dateFields);
                    return $item;
                });

                return $data;
            });

            $log->logInfo(ArchivoController::class, 'Se guardo con exito el archivo en el caso: #' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $archivos));

        } catch (Exception $e) {
            $log->logError(ArchivoController::class, 'Error al guardar el archivo en el caso: #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listArchivoByCasoId($caso_id)
    {
        $log = new Funciones();
        try {
            $data = Archivo::orderBy("id", "desc")->where('caso_id', $caso_id)->get();

            // // Formatear las fechas
            // $data->transform(function ($item) {
            //     $item->formatted_updated_at = Carbon::parse($item->updated_at)->format('Y-m-d H:i:s');
            //     $item->formatted_created_at = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
            //     return $item;
            // });

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $data->map(function ($item) use ($dateFields) {
                // $this->formatoFechaItem($item, $dateFields);
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            // return response()->json(
            //     RespuestaApi::returnResultado(
            //         'success',
            //         'Se listo con éxito',
            //         $data->map(function ($archivo) {
            //             return [
            //                 "id" => $archivo->id,
            //                 "titulo" => $archivo->titulo,
            //                 "observacion" => $archivo->observacion,
            //                 "tipo" => $archivo->tipo,
            //                 "archivo" => $archivo->archivo,
            //                 "caso_id" => $archivo->caso_id,
            //                 "created_at" => $archivo->created_at,
            //                 "updated_at" => $archivo->updated_at,
            //             ];
            //         }),
            //     )
            // );

            $log->logInfo(ArchivoController::class, 'Se listo con exito los archivos del caso: #' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'El listo con éxito', $data));

        } catch (Exception $e) {
            $log->logError(ArchivoController::class, 'Error al listar los archivos del caso: #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editArchivo(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $resultado = DB::transaction(function () use ($request, $id) {

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
                $audit->caso_id = $archivo->caso_id;
                $audit->save();
                // END Auditoria

                // // Recargar el modelo para obtener las fechas actualizadas
                // $archivo->refresh();

                // // Formatear las fechas
                // $archivo->formatted_updated_at = Carbon::parse($archivo->updated_at)->format('Y-m-d H:i:s');
                // $archivo->formatted_created_at = Carbon::parse($archivo->created_at)->format('Y-m-d H:i:s');

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at'];
                $funciones = new Funciones();
                $funciones->formatoFechaItem($archivo, $dateFields);

                return $archivo;
            });

            $log->logInfo(ArchivoController::class, 'Se actualizo con exito el archivo con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $resultado));
        } catch (Exception $e) {
            $log->logError(ArchivoController::class, 'Error al actualizar el archivo con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteArchivo(Request $request, $id)
    {
        $archivoPath = ''; // Inicializar la variable
        $log = new Funciones();

        $parametro = DB::table('crm.parametro')
            ->where('abreviacion', 'NAS')
            ->first();

        try {

            $data = DB::transaction(function () use ($request, $id, &$archivoPath, $parametro) {
                $archivo = Archivo::findOrFail($id);

                // Obtener el old_values (valor antiguo)
                $valorAntiguo = $archivo;

                // Almacenar la ruta del archivo antes de intentar eliminarlo
                $archivoPath = $archivo->archivo;


                if ($parametro->nas == true) {
                    // Intentar obtener el contenido del archivo
                    $archivoNas = Storage::disk('nas')->get($archivoPath);
                } else {
                    // Intentar obtener el contenido del archivo
                    $archivoNas = Storage::disk('local')->get($archivoPath);
                }

                // Eliminar el archivo de la base de datos
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
                $audit->caso_id = $archivo->caso_id;
                $audit->save();
                // END Auditoria

                return $archivo; // Retornar el contenido del archivo eliminado
            });

            if ($parametro->nas == true) {
                // Si todo ha ido bien, eliminar definitivamente el archivo
                Storage::disk('nas')->delete($archivoPath);
            } else {
                // Si todo ha ido bien, eliminar definitivamente el archivo
                Storage::disk('local')->delete($archivoPath);
            }

            $log->logInfo(ArchivoController::class, 'Se elimino con exito el archivo con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $data));
        } catch (Exception $e) {

            $log->logError(ArchivoController::class, 'Error al eliminar el archivo con el ID: ' . $id, $e);

            // En caso de error, restaurar el archivo desde la variable temporal
            if (!empty($archivoPath)) {
                if ($parametro->nas == true) {
                    Storage::disk('nas')->put($archivoPath, $data);
                } else {
                    Storage::disk('local')->put($archivoPath, $data);
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }




    /////////////////////////////////////////////////  DOCUMENTOS EQUIFAX  /////////////////////////////////////////////////////////////////////

    // Lista todos los archivos que esten en la carpeta archivos_sin_firma del NAS
    public function listArchivosSinFirmaEquifaxByCasoId($caso_id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($caso_id) {
                $folderPath = $caso_id . "/archivos_sin_firma"; // Ruta de la carpeta en tu NAS

                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'NAS')
                    ->first();

                if ($parametro->nas == true) {
                    // Obtén los nombres de archivos del sistema de archivos (NAS)
                    $archivosNAS = Storage::disk('nas')->files($folderPath);
                } else {
                    // Obtén los nombres de archivos del sistema de archivos (NAS)
                    $archivosNAS = Storage::disk('local')->files($folderPath);
                }

                // Busca archivos en la base de datos que coincidan con los nombres de archivos en la carpeta NAS
                return Archivo::whereIn('archivo', $archivosNAS)->orderBy('archivo', 'ASC')->get();
            });

            $log->logInfo(ArchivoController::class, 'Se listo con exito los archivos sin firma de Equifax del caso: #' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ArchivoController::class, 'Error al listar archivos sin firma de Equifax del caso: #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // Agrega archivos para mandar a firmar en la carpeta archivos_sin_firmar
    public function addArchivosEquifax(Request $request, $caso_id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request, $caso_id) {
                $archivos = $request->file("archivos"); // Acceder a los archivos utilizando la clave "archivos"
                $archivosGuardados = [];

                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'NAS')
                    ->first();

                foreach ($archivos as $archivoData) {
                    $nombreBase = $caso_id . '-' . $archivoData->getClientOriginalName(); // Nombre base del archivo

                    $path = $caso_id . "/archivos_sin_firma";

                    $titulo = $nombreBase;

                    $i = 1;

                    if ($parametro->nas == true) {
                        while (Storage::disk('nas')->exists("$path/$titulo")) {
                            // Si el archivo con el mismo nombre ya existe, ajusta el nombre
                            $info = pathinfo($nombreBase);
                            $titulo = $info['filename'] . " ($i)." . $info['extension'];
                            $i++;
                        }

                        $path = Storage::disk('nas')->putFileAs($path, $archivoData, $titulo); // Guardar el archivo
                    } else {
                        while (Storage::disk('local')->exists("$path/$titulo")) {
                            // Si el archivo con el mismo nombre ya existe, ajusta el nombre
                            $info = pathinfo($nombreBase);
                            $titulo = $info['filename'] . " ($i)." . $info['extension'];
                            $i++;
                        }

                        $path = Storage::disk('local')->putFileAs($path, $archivoData, $titulo); // Guardar el archivo
                    }

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

            $log->logInfo(ArchivoController::class, 'Se creo con exito los archivos de Equifax en el caso: #' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));

        } catch (Exception $e) {
            $log->logError(ArchivoController::class, 'Error al crear los archivos de Equifax en el caso: #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editArchivosEquifax(Request $request, $id)
    {
        $log = new Funciones();
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

                    $parametro = DB::table('crm.parametro')
                        ->where('abreviacion', 'NAS')
                        ->first();

                    if ($parametro->nas == true) {
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
                    } else {
                        while (Storage::disk('local')->exists("$path/$titulo")) {
                            // Si el archivo con el mismo nombre ya existe, ajusta el nombre
                            $info = pathinfo($nombreBase);
                            $titulo = $info['filename'] . " ($i)." . $info['extension'];
                            $i++;
                        }

                        $path = Storage::disk('local')->putFileAs($path, $file, $titulo);

                        // Puedes eliminar el archivo anterior si es necesario
                        if ($archivo->archivo) {
                            Storage::disk('local')->delete($archivo->archivo);
                        }
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

            $log->logInfo(ArchivoController::class, 'Se actualizo con exito el archivo de Equifax con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ArchivoController::class, 'Error al actualizar el archivo de Equifax con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // Lista de documentos de equifax FIRMADOS

    public function listArchivosEquifaxFirmadosByCasoId($caso_id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($caso_id) {
                return Archivo::orderBy("id", "desc")->where('caso_id', $caso_id)->where('tipo', 'equifax')->get();
            });

            $log->logInfo(ArchivoController::class, 'Se listo con exito los archivos firmados de Equifax del caso: #' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(ArchivoController::class, 'Error al listar archivos firmados de Equifax del caso: #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}

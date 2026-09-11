<?php

namespace App\Http\Controllers\gestionClientes;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\gestionClientes\AdjuntoCliente;
use App\Models\gestionClientes\BitacoraGestionCliente;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdjuntoClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Listar adjuntos por cliente
     */
    public function listAdjuntosByCliente($clienteId)
    {
        try {
            $adjuntos = AdjuntoCliente::with(['tipoAdjunto'])
                ->where('cliente_id', $clienteId)
                ->orderBy('created_at', 'desc')
                ->get();


            // Especificar las propiedades que representan fechas
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $adjuntos->map(function ($item) use ($dateFields) {
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);

                // Formatear fechas de los detalles también
                if ($item->detalles) {
                    $item->detalles->map(function ($detalle) use ($dateFields, $funciones) {
                        $funciones->formatoFechaItem($detalle, $dateFields);
                        return $detalle;
                    });
                }

                return $item;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $adjuntos));
        } catch (Exception $e) {

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Listar adjuntos por actividad
     */
    public function listAdjuntosByActividad($actividadId)
    {
        $log = new Funciones();
        try {
            $adjuntos = AdjuntoCliente::with(['tipoAdjunto'])
                ->where('actividad_cliente_id', $actividadId)
                ->orderBy('created_at', 'desc')
                ->get();

            $log->logInfo(AdjuntoClienteController::class, 'Se listaron los adjuntos de la actividad exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $adjuntos));
        } catch (Exception $e) {
            $log->logError(AdjuntoClienteController::class, 'Error al listar los adjuntos de la actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()), 500);
        }
    }

    /**
     * Obtener un adjunto por ID
     */
    public function getAdjuntoById($id)
    {
        $log = new Funciones();
        try {
            $adjunto = AdjuntoCliente::with(['tipoAdjunto', 'cliente', 'actividad'])->findOrFail($id);

            $log->logInfo(AdjuntoClienteController::class, 'Se obtuvo el adjunto exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', $adjunto));
        } catch (Exception $e) {
            $log->logError(AdjuntoClienteController::class, 'Error al obtener el adjunto', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al obtener', $e->getMessage()), 500);
        }
    }

    /**
     * Subir un nuevo adjunto
     */
    public function addAdjunto(Request $request)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'tipo_adjunto_id' => 'required|integer',
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'archivo' => 'required|file',
                'cliente_id' => 'nullable|integer',
                'actividad_cliente_id' => 'nullable|integer'
            ]);

            // Validar que al menos uno de los dos esté presente
            if (!$request->has('cliente_id') && !$request->has('actividad_cliente_id')) {
                return response()->json(
                    RespuestaApi::returnResultado('error', 'Debe proporcionar cliente_id o actividad_cliente_id', null),
                    400
                );
            }

            $adjunto = DB::transaction(function () use ($request) {
                // Obtener parámetro de configuración NAS
                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'NAS')
                    ->first();

                $file = $request->file('archivo');
                // Reemplazar espacios por guiones en el nombre del archivo
                $nombreOriginal = str_replace(' ', '-', $file->getClientOriginalName());

                // Obtener la identificación del cliente
                $identificacionCliente = '';
                if ($request->cliente_id) {
                    // Adjunto directo del cliente
                    $cliente = DB::table('crm.clientes')
                        ->where('id', $request->cliente_id)
                        ->first();
                    $identificacionCliente = $cliente->identificacion;
                } else if ($request->actividad_cliente_id) {
                    // Adjunto de actividad - obtener cliente desde la actividad
                    $actividad = DB::table('crm.actividad_cliente')
                        ->where('id', $request->actividad_cliente_id)
                        ->first();
                    $cliente = DB::table('crm.clientes')
                        ->where('id', $actividad->cliente_id)
                        ->first();
                    $identificacionCliente = $cliente->identificacion;
                }

                // Nombre único del archivo con timestamp
                $nombreUnico = time() . '-' . $nombreOriginal;

                // Ruta de la carpeta usando identificación del cliente
                $rutaCarpeta = "gestion_clientes/{$identificacionCliente}/adjuntos";

                // Guardar el archivo según configuración NAS
                if ($parametro->nas == true) {
                    $path = Storage::disk('nas')->putFileAs($rutaCarpeta, $file, $nombreUnico);
                } else {
                    $path = Storage::disk('local')->putFileAs($rutaCarpeta, $file, $nombreUnico);
                }

                $adjunto = AdjuntoCliente::create([
                    'tipo_adjunto_id' => $request->tipo_adjunto_id,
                    'cliente_id' => $request->cliente_id,
                    'actividad_cliente_id' => $request->actividad_cliente_id,
                    'nombre' => $request->nombre,
                    'descripcion' => $request->descripcion,
                    'url' => $path
                ]);

                $adjunto->load(['tipoAdjunto', 'cliente', 'actividad']);

                // Registrar en bitácora solo si está relacionado con una actividad
                if ($adjunto->actividad_cliente_id) {
                    BitacoraGestionCliente::create([
                        'user_id' => auth()->id(),
                        'accion' => 'addAdjunto',
                        'valores_anteriores' => [],
                        'valores_nuevos' => $adjunto->toArray()
                    ]);
                }

                return $adjunto;
            });

            $log->logInfo(AdjuntoClienteController::class, 'Se subió el adjunto exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $adjunto));
        } catch (Exception $e) {
            $log->logError(AdjuntoClienteController::class, 'Error al subir el adjunto', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()));
        }
    }

    /**
     * Actualizar información de un adjunto
     */
    public function editAdjunto(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $request->validate([
                'tipo_adjunto_id' => 'required|integer',
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'archivo' => 'nullable|file|max:10240' // Archivo opcional al actualizar
            ]);

            $adjunto = DB::transaction(function () use ($request, $id) {
                $adjunto = AdjuntoCliente::findOrFail($id);
                $valoresAnteriores = $adjunto->load(['tipoAdjunto'])->toArray();

                // Obtener parámetro de configuración NAS
                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'NAS')
                    ->first();

                // Si se está subiendo un nuevo archivo
                if ($request->hasFile('archivo')) {
                    $file = $request->file('archivo');
                    $nombreOriginal = str_replace(' ', '-', $file->getClientOriginalName());

                    // Obtener la identificación del cliente
                    $identificacionCliente = '';
                    if ($adjunto->cliente_id) {
                        $cliente = DB::table('crm.clientes')
                            ->where('id', $adjunto->cliente_id)
                            ->first();
                        $identificacionCliente = $cliente->identificacion;
                    } else if ($adjunto->actividad_cliente_id) {
                        // Si es adjunto de actividad, obtener el cliente desde la actividad
                        $actividad = DB::table('crm.actividad_cliente')
                            ->where('id', $adjunto->actividad_cliente_id)
                            ->first();
                        $cliente = DB::table('crm.clientes')
                            ->where('id', $actividad->cliente_id)
                            ->first();
                        $identificacionCliente = $cliente->identificacion;
                    }

                    // Nombre único del archivo con timestamp
                    $nombreUnico = time() . '-' . $nombreOriginal;

                    // Ruta de la carpeta usando identificación del cliente
                    $rutaCarpeta = "gestion_clientes/{$identificacionCliente}/adjuntos";

                    // Eliminar el archivo anterior si existe
                    if ($adjunto->url) {
                        if ($parametro->nas == true) {
                            Storage::disk('nas')->delete($adjunto->url);
                        } else {
                            Storage::disk('local')->delete($adjunto->url);
                        }
                    }

                    // Guardar el nuevo archivo
                    if ($parametro->nas == true) {
                        $path = Storage::disk('nas')->putFileAs($rutaCarpeta, $file, $nombreUnico);
                    } else {
                        $path = Storage::disk('local')->putFileAs($rutaCarpeta, $file, $nombreUnico);
                    }

                    // Actualizar con el nuevo archivo
                    $adjunto->update([
                        'tipo_adjunto_id' => $request->tipo_adjunto_id,
                        'nombre' => $request->nombre,
                        'descripcion' => $request->descripcion,
                        'url' => $path
                    ]);
                } else {
                    // Si no se está subiendo archivo, solo actualizar datos
                    $adjunto->update([
                        'tipo_adjunto_id' => $request->tipo_adjunto_id,
                        'nombre' => $request->nombre,
                        'descripcion' => $request->descripcion
                    ]);
                }

                $adjunto->load(['tipoAdjunto', 'cliente', 'actividad']);

                // Registrar en bitácora solo si está relacionado con una actividad
                if ($adjunto->actividad_cliente_id) {
                    BitacoraGestionCliente::create([
                        'user_id' => auth()->id(),
                        'accion' => 'editAdjunto',
                        'valores_anteriores' => $valoresAnteriores,
                        'valores_nuevos' => $adjunto->toArray()
                    ]);
                }

                return $adjunto;
            });

            $log->logInfo(AdjuntoClienteController::class, 'Se actualizó el adjunto exitosamente');

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $adjunto));
        } catch (Exception $e) {
            $log->logError(AdjuntoClienteController::class, 'Error al actualizar el adjunto', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()));
        }
    }

    /**
     * Eliminar un adjunto
     */
    public function deleteAdjunto($id)
    {
        $archivoPath = ''; // Inicializar la variable
        $archivoData = null; // Para almacenar el contenido del archivo
        $log = new Funciones();

        // Obtener parámetro de configuración NAS
        $parametro = DB::table('crm.parametro')
            ->where('abreviacion', 'NAS')
            ->first();

        try {
            $adjunto = DB::transaction(function () use ($id, &$archivoPath, &$archivoData, $parametro) {
                $adjunto = AdjuntoCliente::findOrFail($id);
                $valoresAnteriores = $adjunto->load(['tipoAdjunto'])->toArray();

                // Almacenar la ruta del archivo antes de intentar eliminarlo
                $archivoPath = $adjunto->url;

                if ($archivoPath) {
                    if ($parametro->nas == true) {
                        // Intentar obtener el contenido del archivo para verificar que existe
                        $archivoData = Storage::disk('nas')->get($archivoPath);
                    } else {
                        // Intentar obtener el contenido del archivo para verificar que existe
                        $archivoData = Storage::disk('local')->get($archivoPath);
                    }
                }

                // Registrar en bitácora solo si está relacionado con una actividad
                if ($adjunto->actividad_cliente_id) {
                    BitacoraGestionCliente::create([
                        'user_id' => auth()->id(),
                        'accion' => 'deleteAdjunto',
                        'valores_anteriores' => $valoresAnteriores,
                        'valores_nuevos' => []
                    ]);
                }

                // Eliminar el registro de la base de datos
                $adjunto->delete();

                return $adjunto;
            });

            // Si todo ha ido bien, eliminar definitivamente el archivo físico
            if ($archivoPath) {
                if ($parametro->nas == true) {
                    Storage::disk('nas')->delete($archivoPath);
                } else {
                    Storage::disk('local')->delete($archivoPath);
                }
            }

            $log->logInfo(AdjuntoClienteController::class, 'Se eliminó el adjunto exitosamente con ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $adjunto));
        } catch (Exception $e) {
            $log->logError(AdjuntoClienteController::class, 'Error al eliminar el adjunto con ID: ' . $id, $e);

            // En caso de error, restaurar el archivo desde la variable temporal
            if (!empty($archivoPath) && $archivoData !== null) {
                if ($parametro->nas == true) {
                    Storage::disk('nas')->put($archivoPath, $archivoData);
                } else {
                    Storage::disk('local')->put($archivoPath, $archivoData);
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar', $e->getMessage()), 500);
        }
    }
}

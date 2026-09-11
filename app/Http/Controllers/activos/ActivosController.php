<?php

namespace App\Http\Controllers\activos;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\activos\Activo;
use App\Models\activos\Dacta;
use App\Models\activos\EstadoActivo;
use App\Models\activos\Localidad;
use App\Models\activos\Marca;
use App\Models\activos\TipoActivo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // ACTIVOS

    public function listAllActivos()
    {
        try {
            $data = Activo::selectRaw("*, (CASE WHEN estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")
                ->with('tipo_activo', 'marca', 'estado_activo', 'ultima_acta')->orderBy('id', 'asc')->get();

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $data->map(function ($item) use ($dateFields) {
                $funciones = new Funciones();
                // Formatear fechas del activo
                $funciones->formatoFechaItem($item, $dateFields);

                // Formatear fechas de la última acta si existe
                if ($item->ultima_acta) {
                    $funciones->formatoFechaItem($item->ultima_acta, $dateFields);
                }

                return $item;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function listActivos()
    {
        try {
            $data = Activo::with('tipo_activo', 'marca', 'estado_activo', 'ultima_acta')->where('estado', true)->orderBy('id', 'asc')->get();

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $data->map(function ($item) use ($dateFields) {
                $funciones = new Funciones();
                // Formatear fechas del activo
                $funciones->formatoFechaItem($item, $dateFields);

                // Formatear fechas de la última acta si existe
                if ($item->ultima_acta) {
                    $funciones->formatoFechaItem($item->ultima_acta, $dateFields);
                }

                return $item;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function addActivo(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {

                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'IVA')
                    ->first();

                if ($parametro) {
                    // Calcular IVA (redondeado a 2 decimales)
                    $iva = round($request->costo * ($parametro->valor / 100), 2);

                    // redondeado a 2 decimales
                    $total = round($request->costo + $iva, 2);

                    $activo = Activo::create(array_merge($request->all(), [
                        'iva' => $iva,
                        'total' => $total
                    ]));
                } else {
                    throw new Exception('No se encontró el parámetro IVA.');
                }

                $data = Activo::selectRaw("*, (CASE WHEN estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")
                    ->with('tipo_activo', 'marca', 'estado_activo', 'ultima_acta')->orderBy('id', 'asc')->get();

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

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error: ' . $e->getMessage(), null));
        }
    }

    public function editActivo(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($request, $id) {
                // Buscar el activo
                $activo = Activo::findOrFail($id);

                // Obtener el parámetro IVA
                $parametro = DB::table('crm.parametro')
                    ->where('abreviacion', 'IVA')
                    ->first();

                if ($parametro) {
                    // Calcular IVA y total (redondeado a 2 decimales)
                    $iva = round($request->costo * ($parametro->valor / 100), 2);
                    $total = round($request->costo + $iva, 2);

                    // Actualizar el activo con los nuevos valores
                    $activo->update(array_merge($request->all(), [
                        'iva' => $iva,
                        'total' => $total
                    ]));
                } else {
                    throw new Exception('No se encontró el parámetro IVA.');
                }

                // Retornar todos los activos con sus relaciones
                $data = Activo::selectRaw("*, (CASE WHEN estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")
                    ->with('tipo_activo', 'marca', 'estado_activo', 'ultima_acta')->orderBy('id', 'asc')->get();

                // Formatear fechas
                $dateFields = ['created_at', 'updated_at'];
                $data->map(function ($item) use ($dateFields) {
                    $funciones = new Funciones();
                    $funciones->formatoFechaItem($item, $dateFields);
                    return $item;
                });

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error: ' . $e->getMessage(), null));
        }
    }

    public function deleteActivo($id)
    {
        try {
            $data = Activo::findOrFail($id);

            $data->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function historyActivo($id_activo)
    {
        try {
            // Obtener todos los detalles de acta (dacta) del activo con sus relaciones
            $data = Dacta::with([
                'cacta.user',
                'cacta.localidad',
                'cacta.departamento'
            ])
                ->where('id_activo', $id_activo)
                ->orderBy('created_at', 'asc') // Ordenar por fecha de creación descendente (más reciente primero)
                ->get();

            // Formatear fechas
            $dateFields = ['created_at', 'updated_at'];
            $funciones = new Funciones();

            // Formatear fechas del activo
            $funciones->formatoFechaItem($data, $dateFields);

            // Formatear fechas de cada registro del historial
            $data->each(function ($item) use ($dateFields, $funciones) {
                if (isset($item['cacta'])) {
                    $funciones->formatoFechaItem($item['cacta'], $dateFields);
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error: ' . $e->getMessage(), null));
        }
    }

    public function listTipoActivos()
    {
        try {
            $data = TipoActivo::where('estado', true)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function listEstadoActivos()
    {
        try {
            $data = EstadoActivo::where('estado', true)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function listMarca()
    {
        try {
            $data = Marca::where('estado', true)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function listLocalidades()
    {
        try {
            $data = Localidad::where('estado', true)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }
}

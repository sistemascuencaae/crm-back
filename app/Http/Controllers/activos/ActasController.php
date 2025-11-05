<?php

namespace App\Http\Controllers\activos;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\activos\Cacta;
use App\Models\activos\Dacta;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listAllActas()
    {
        try {
            $data = Cacta::with([
                'user',
                'localidad',
                'departamento'
            ])->orderBy('numero', 'asc')->get();

            // Especificar las propiedades que representan fechas
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $data->map(function ($item) use ($dateFields) {
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

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function addActa(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
                // Validar que se envíen activos
                $activos = $request->input('activos', []);
                if (empty($activos)) {
                    throw new Exception('Debe enviar al menos un activo');
                }

                // Validar que no haya activos duplicados en la misma solicitud
                $activosUnicos = array_unique($activos);
                if (count($activosUnicos) !== count($activos)) {
                    throw new Exception('No puede enviar el mismo activo duplicado en la misma acta');
                }

                // CRÍTICO: Obtener el último número con lock para prevenir race conditions
                $ultimaCacta = DB::selectOne(
                    'SELECT numero FROM crm.cacta ORDER BY numero DESC LIMIT 1 FOR UPDATE'
                );
                $nuevoNumero = $ultimaCacta ? $ultimaCacta->numero + 1 : 1;

                // Crear la cabecera del acta (Cacta)
                $cacta = Cacta::create([
                    'id_user' => $request->input('id_user'),
                    'id_localidad' => $request->input('id_localidad'),
                    'id_departamento' => $request->input('id_departamento'),
                    'numero' => $nuevoNumero,
                    'recepcion_fisica_acta' => false
                ]);

                // Crear los detalles del acta (Dacta) para cada activo
                $secuencia = 1;
                foreach ($activos as $idActivo) {
                    Dacta::create([
                        'id_cacta' => $cacta->id,
                        'id_activo' => $idActivo,
                        'secuencia' => $secuencia
                    ]);
                    $secuencia++;
                }

                // Obtener todas las actas creadas con sus relaciones
                $data = Cacta::with([
                    'user',
                    'localidad',
                    'departamento'
                ])->orderBy('numero', 'asc')->get();

                // Especificar las propiedades que representan fechas
                $dateFields = ['created_at', 'updated_at'];
                // Utilizar la función map para transformar y obtener una nueva colección
                $data->map(function ($item) use ($dateFields) {
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

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error: ' . $e->getMessage(), null));
        }
    }

    public function getActivosByNumeroActa($numero)
    {
        try {
            $data = Cacta::with([
                'user',
                'localidad',
                'departamento',
                'detalles.activo.tipo_activo',
                'detalles.activo.marca',
                'detalles.activo.estado_activo'
            ])
                ->where('numero', $numero)
                ->first();

            if (!$data) {
                throw new Exception('No se encontró el acta con el número especificado');
            }

            // Formatear fechas
            $dateFields = ['created_at', 'updated_at'];
            $funciones = new Funciones();
            $funciones->formatoFechaItem($data, $dateFields);

            // Formatear fechas de los detalles también
            if ($data->detalles) {
                $data->detalles->map(function ($detalle) use ($dateFields, $funciones) {
                    $funciones->formatoFechaItem($detalle, $dateFields);
                    return $detalle;
                });
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function editActa(Request $request, $numero)
    {
        try {
            $data = DB::transaction(function () use ($request, $numero) {
                // Buscar la cabecera del acta con ese número
                $cacta = Cacta::where('numero', $numero)->lockForUpdate()->first();

                if (!$cacta) {
                    throw new Exception('No se encontró el acta con el número especificado');
                }

                // Obtener los activos enviados en la petición
                $activosNuevos = $request->input('activos', []);

                if (empty($activosNuevos)) {
                    throw new Exception('Debe enviar al menos un activo');
                }

                // Validar que no haya activos duplicados en la misma solicitud
                $activosUnicos = array_unique($activosNuevos);
                if (count($activosUnicos) !== count($activosNuevos)) {
                    throw new Exception('No puede enviar el mismo activo duplicado en la misma acta');
                }

                // Obtener los detalles actuales
                $detallesActuales = Dacta::where('id_cacta', $cacta->id)->get();
                $activosActuales = $detallesActuales->pluck('id_activo')->toArray();

                // Determinar qué activos eliminar (están en actuales pero no en nuevos)
                $activosAEliminar = array_diff($activosActuales, $activosNuevos);

                // Determinar qué activos agregar (están en nuevos pero no en actuales)
                $activosAAgregar = array_diff($activosNuevos, $activosActuales);

                // Eliminar los detalles de activos que ya no están
                if (!empty($activosAEliminar)) {
                    Dacta::where('id_cacta', $cacta->id)
                        ->whereIn('id_activo', $activosAEliminar)
                        ->delete();
                }

                // Actualizar la cabecera del acta
                $cacta->update([
                    'id_user' => $request->input('id_user'),
                    'id_localidad' => $request->input('id_localidad'),
                    'id_departamento' => $request->input('id_departamento'),
                ]);

                // Agregar nuevos detalles para los activos nuevos
                if (!empty($activosAAgregar)) {
                    // Obtener la última secuencia actual
                    $ultimaSecuencia = Dacta::where('id_cacta', $cacta->id)->max('secuencia') ?? 0;

                    foreach ($activosAAgregar as $idActivo) {
                        $ultimaSecuencia++;
                        Dacta::create([
                            'id_cacta' => $cacta->id,
                            'id_activo' => $idActivo,
                            'secuencia' => $ultimaSecuencia
                        ]);
                    }
                }

                // Reorganizar las secuencias para que sean consecutivas
                $detallesActualizados = Dacta::where('id_cacta', $cacta->id)
                    ->orderBy('secuencia', 'asc')
                    ->get();

                $secuencia = 1;
                foreach ($detallesActualizados as $detalle) {
                    $detalle->secuencia = $secuencia;
                    $detalle->save();
                    $secuencia++;
                }

                // Obtener todas las actas actualizadas con sus relaciones
                $data = Cacta::with([
                    'user',
                    'localidad',
                    'departamento'
                ])->orderBy('numero', 'asc')->get();

                // Formatear fechas
                $dateFields = ['created_at', 'updated_at'];
                $data->map(function ($item) use ($dateFields) {
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

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error: ' . $e->getMessage(), null));
        }
    }

    public function editRecepcionFisica(Request $request, $numero)
    {
        try {
            DB::transaction(function () use ($request, $numero) {
                $recepcion_fisica = $request->input('recepcion_fisica_acta');

                // Actualizar la cabecera del acta con el número especificado
                Cacta::where('numero', $numero)->update([
                    'recepcion_fisica_acta' => $recepcion_fisica
                ]);
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error: ' . $e->getMessage(), null));
        }
    }

    public function editRecibidoPor(Request $request, $numero)
    {
        try {
            DB::transaction(function () use ($request, $numero) {
                $recibido_por = $request->input('recibido_por');

                // Actualizar la cabecera del acta con el número especificado
                Cacta::where('numero', $numero)->update([
                    'recibido_por' => $recibido_por
                ]);
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error: ' . $e->getMessage(), null));
        }
    }
}

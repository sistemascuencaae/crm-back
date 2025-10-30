<?php

namespace App\Http\Controllers\activos;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\activos\Acta;
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
            $data = Acta::with('activo.tipo_activo','activo.marca','activo.estado_activo', 'user', 'localidad', 'departamento')->orderBy('numero', 'asc')->get();

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $data->map(function ($item) use ($dateFields) {
                // $this->formatoFechaItem($item, $dateFields);
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
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
                // En PostgreSQL, FOR UPDATE no funciona con MAX(), usamos ORDER BY + LIMIT + FOR UPDATE
                $ultimaActa = DB::selectOne(
                    'SELECT numero FROM crm.acta ORDER BY numero DESC LIMIT 1 FOR UPDATE'
                );
                $nuevoNumero = $ultimaActa ? $ultimaActa->numero + 1 : 1;

                // CRÍTICO: Validar que ningún activo ya esté en ESTA acta (mismo número)
                // Aunque la acta es nueva, validamos por si hay race condition
                $activosEnEstaActa = Acta::where('numero', $nuevoNumero)
                    ->whereIn('id_activo', $activos)
                    ->lockForUpdate()
                    ->pluck('id_activo')
                    ->toArray();

                if (!empty($activosEnEstaActa)) {
                    throw new Exception('Conflicto de concurrencia detectado. Por favor intente nuevamente.');
                }

                // Crear registros de acta para cada activo
                $secuencia = 1;
                $actasCreadas = [];

                foreach ($activos as $idActivo) {
                    $acta = Acta::create([
                        'id_activo' => $idActivo,
                        'id_user' => $request->input('id_user'),
                        'id_localidad' => $request->input('id_localidad'),
                        'id_departamento' => $request->input('id_departamento'),
                        'numero' => $nuevoNumero,
                        'secuencia' => $secuencia,
                        'recepcion_fisica_acta' => false
                    ]);

                    $actasCreadas[] = $acta->id;
                    $secuencia++;
                }

                // Obtener las actas creadas con sus relaciones
                $data = Acta::with('activo', 'user', 'localidad', 'departamento')->orderBy('numero', 'asc')->get();

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at'];
                // Utilizar la función map para transformar y obtener una nueva colección
                $data->map(function ($item) use ($dateFields) {
                    $funciones = new Funciones();
                    $funciones->formatoFechaItem($item, $dateFields);
                    return $item;
                });

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error: ' . $e->getMessage(), null));
        }
    }
}

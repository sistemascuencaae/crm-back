<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Cotizador > Tarjetas y contado: los planes de tarjeta de cada banco (ventas.parametros_tarjeta y sus
// reglas_tarjeta) y las formas de pago de contado (ventas.parametros_contado y sus reglas_contado).
// El negocio vive en ventas.fn_parametros_tarjeta_* y ventas.fn_parametros_contado_* (PLANES/COTIZADOR):
// este controller solo valida la forma, arma el jsonb con 'auditoria' y traduce la respuesta.
class TarjetasContadoController extends Controller
{
    private const DIAS = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Bancos activos de alma.banco, buscados y paginados, con cuantos planes activos tiene cada uno.
    public function listarBancosTarjeta(Request $request)
    {
        try {
            // Topes: tamanio 1..100 y pagina sin desbordar el OFFSET (integer) de la funcion.
            $pagina   = min(max((int) $request->query('pagina', 1), 1), 1000000);
            $tamanio  = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select(
                'SELECT * FROM ventas.fn_parametros_tarjeta_bancos_listar_paginacion(?, ?, ?)',
                [$pagina, $tamanio, $busqueda]
            );

            foreach ($registros as $r) {
                $r->planes_activos = (int) $r->planes_activos;
            }

            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'registros' => $registros,
                'total'     => (int) $total,
                'pagina'    => $pagina,
                'tamanio'   => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los bancos', $e->getMessage()));
        }
    }

    // El banco con sus planes y las reglas de cada plan.
    public function getPlanesTarjeta($ban_id)
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_parametros_tarjeta_buscar(?) AS resultado', [(int) $ban_id]);

            if (!$fila || !$fila->resultado) {
                return response()->json(RespuestaApi::returnResultado('error', 'El banco no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los planes del banco', $e->getMessage()));
        }
    }

    // Guarda los planes del banco (los nuevos y los cambiados, con sus reglas) en un solo paso.
    public function guardarPlanesTarjeta(Request $request, $ban_id)
    {
        try {
            $validator = Validator::make($request->all(), array_merge([
                'planes'                  => 'nullable|array',
                'planes.*'                => 'array',
                'planes.*.id'             => 'nullable|integer',
                'planes.*.nombre'         => 'nullable|string|max:255',
                'planes.*.cuotas'         => 'nullable|integer',
                'planes.*.recargo'        => 'nullable|numeric',
                'planes.*.interes'        => 'nullable|numeric',
                'planes.*.descuento'      => 'nullable|numeric',
                'planes.*.predeterminado' => 'nullable|boolean',
                'planes.*.activo'         => 'nullable|boolean',
            ], $this->reglasDeReglas('planes.*.reglas')));

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $planes = $request->input('planes');
            $payload = [
                'ban_id'    => (int) $ban_id,
                'planes'    => is_array($planes) ? array_map(function ($p) {
                    return [
                        'id'             => isset($p['id']) && $p['id'] !== '' ? (int) $p['id'] : null,
                        'nombre'         => $p['nombre'] ?? null,
                        'cuotas'         => $p['cuotas'] ?? null,
                        'recargo'        => $p['recargo'] ?? null,
                        'interes'        => $p['interes'] ?? null,
                        'descuento'      => $p['descuento'] ?? null,
                        'predeterminado' => filter_var($p['predeterminado'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'activo'         => $this->booleanoONulo($p['activo'] ?? null),
                        'reglas'         => $this->payloadReglas($p['reglas'] ?? null),
                    ];
                }, array_values($planes)) : null,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT ventas.fn_parametros_tarjeta_guardar(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'ban_id' => $respuesta['ban_id'] ?? (int) $ban_id,
                    'planes' => $respuesta['planes'] ?? [],
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron guardar los planes del banco', $e->getMessage()));
        }
    }

    // Las formas de pago de contado con sus reglas.
    public function getFormasPagoContado()
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_parametros_contado_buscar() AS resultado');

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las formas de pago de contado', $e->getMessage()));
        }
    }

    // Guarda las formas de pago (las nuevas y las cambiadas, con sus reglas) en un solo paso.
    public function guardarFormasPagoContado(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), array_merge([
                'formas'                  => 'nullable|array',
                'formas.*'                => 'array',
                'formas.*.id'             => 'nullable|integer',
                'formas.*.nombre'         => 'nullable|string|max:255',
                'formas.*.descuento'      => 'nullable|numeric',
                'formas.*.predeterminado' => 'nullable|boolean',
                'formas.*.activo'         => 'nullable|boolean',
            ], $this->reglasDeReglas('formas.*.reglas')));

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $formas = $request->input('formas');
            $payload = [
                'formas'    => is_array($formas) ? array_map(function ($f) {
                    return [
                        'id'             => isset($f['id']) && $f['id'] !== '' ? (int) $f['id'] : null,
                        'nombre'         => $f['nombre'] ?? null,
                        'descuento'      => $f['descuento'] ?? null,
                        'predeterminado' => filter_var($f['predeterminado'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'activo'         => $this->booleanoONulo($f['activo'] ?? null),
                        'reglas'         => $this->payloadReglas($f['reglas'] ?? null),
                    ];
                }, array_values($formas)) : null,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT ventas.fn_parametros_contado_guardar(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'formas' => $respuesta['formas'] ?? [],
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron guardar las formas de pago de contado', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------------

    // Solo los tipos de las reglas. Lo obligatorio y el negocio (con mensajes en espanol) lo valida la funcion.
    private function reglasDeReglas(string $prefijo): array
    {
        $reglas = [
            $prefijo                     => 'nullable|array',
            $prefijo . '.*'              => 'array',
            $prefijo . '.*.id'           => 'nullable|integer',
            $prefijo . '.*.orden'        => 'nullable|integer',
            $prefijo . '.*.fecha_inicio' => 'nullable|string',
            $prefijo . '.*.fecha_fin'    => 'nullable|string',
            $prefijo . '.*.descuento'    => 'nullable|numeric',
            $prefijo . '.*.activo'       => 'nullable|boolean',
        ];
        foreach (self::DIAS as $dia) {
            $reglas[$prefijo . '.*.' . $dia] = 'nullable|boolean';
        }
        return $reglas;
    }

    // Sin la clave (o null) las reglas no se tocan; con arreglo, sin id = nueva y con id = existente (la
    // funcion verifica que sea del plan). El dia que no viene cuenta como no marcado.
    private function payloadReglas($reglas): ?array
    {
        if (!is_array($reglas)) {
            return null;
        }

        return array_map(function ($r) {
            $regla = [
                'id'           => isset($r['id']) && $r['id'] !== '' ? (int) $r['id'] : null,
                'orden'        => $r['orden'] ?? null,
                'fecha_inicio' => $r['fecha_inicio'] ?? null,
                'fecha_fin'    => $r['fecha_fin'] ?? null,
                'descuento'    => $r['descuento'] ?? null,
                'activo'       => $this->booleanoONulo($r['activo'] ?? null),
            ];
            foreach (self::DIAS as $dia) {
                $regla[$dia] = filter_var($r[$dia] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            return $regla;
        }, array_values($reglas));
    }

    // null se queda null (la funcion pone el valor por defecto o conserva el actual).
    private function booleanoONulo($valor): ?bool
    {
        return ($valor === null || $valor === '') ? null : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    // Contexto forense que las funciones guardan en auditoria.logs_cambios.
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id'     => $u->id ?? null,
            'usuario_login'  => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'request_id'     => (string) Str::uuid(),
        ];
    }
}

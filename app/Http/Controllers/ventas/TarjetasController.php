<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Cotizador > Tarjetas y comisiones y Descuentos de plazos (25-oct): los bancos son los de Dynamo (public.banco,
// 26-oct) y el emisor de cada tarjeta tambien (public.emisor, 27-oct; antes, ventas.tarjeta_adquirente); las
// tarjetas, plazos, comisiones y descuentos son del cotizador (ventas.tarjeta_*). El negocio vive en ventas.fn_tarjeta_*
// (PLANES/COTIZADOR/produccion_tarjetas.sql): este controller solo valida la forma, arma el jsonb con 'auditoria' y
// traduce la respuesta.
class TarjetasController extends Controller
{
    // 27-oct: lo que se audita por banco (registro_id = ban_id): Tarjetas y comisiones y Descuentos de plazos.
    private const TABLAS_AUDITORIA = ['tarjeta_banco', 'tarjeta_plazo_descuento'];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Bancos activos de public.banco, buscados y paginados, con cuantas tarjetas activas tiene cada uno.
    public function listarBancosTarjetas(Request $request)
    {
        try {
            // Topes: tamanio 1..100 y pagina sin desbordar el OFFSET (integer) de la funcion.
            $pagina   = min(max((int) $request->query('pagina', 1), 1), 1000000);
            $tamanio  = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select(
                'SELECT * FROM ventas.fn_tarjeta_banco_listar_paginacion(?, ?, ?)',
                [$pagina, $tamanio, $busqueda]
            );

            foreach ($registros as $r) {
                $r->tarjetas_activas = (int) $r->tarjetas_activas;
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

    // Tipos de tarjeta y emisores (27-oct; antes, cuentas de liquidacion y adquirentes): los combos de las tarjetas.
    public function catalogosTarjetas()
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_tarjeta_catalogos_buscar() AS resultado');

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los catálogos de tarjetas', $e->getMessage()));
        }
    }

    // El banco con su comision y su descuento, sus tarjetas y los plazos de cada una, y sus avisos (26-oct: un
    // descuento cerca de su comision o el banco sin comision).
    public function getBancoTarjetas($ban_id)
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_tarjeta_banco_buscar(?) AS resultado, ventas.fn_tarjeta_descuento_avisos(?) AS avisos',
                [(int) $ban_id, (int) $ban_id]);

            if (!$fila || !$fila->resultado) {
                return response()->json(RespuestaApi::returnResultado('error', 'El banco no existe.', null));
            }

            $datos = json_decode($fila->resultado, true);
            $datos['avisos'] = json_decode($fila->avisos, true) ?? [];

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', $datos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las tarjetas del banco', $e->getMessage()));
        }
    }

    // Guarda el banco (comision y descuento) y sus tarjetas nuevas o cambiadas, con sus plazos, en un solo paso.
    public function guardarBancoTarjetas(Request $request, $ban_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'comision_banco'                    => 'nullable|numeric',
                'descuento_empresa'                 => 'nullable|numeric',
                'tarjetas'                          => 'nullable|array',
                'tarjetas.*'                        => 'array',
                'tarjetas.*.id'                     => 'nullable|integer',
                'tarjetas.*.nombre'                 => 'nullable|string|max:255',
                'tarjetas.*.emi_id'                 => 'nullable|integer',
                'tarjetas.*.comision_banco'         => 'nullable|numeric',
                'tarjetas.*.descuento_empresa'      => 'nullable|numeric',
                'tarjetas.*.activo'                 => 'nullable|boolean',
                'tarjetas.*.plazos'                 => 'nullable|array',
                'tarjetas.*.plazos.*'               => 'array',
                'tarjetas.*.plazos.*.id'            => 'nullable|integer',
                'tarjetas.*.plazos.*.tipo_tarjeta_id' => 'nullable|integer',
                'tarjetas.*.plazos.*.plazo'         => 'nullable|integer',
                'tarjetas.*.plazos.*.meses_gracia'  => 'nullable|integer',
                'tarjetas.*.plazos.*.comision_banco' => 'nullable|numeric',
                'tarjetas.*.plazos.*.activo'        => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $tarjetas = $request->input('tarjetas');
            $payload = [
                'ban_id'            => (int) $ban_id,
                'comision_banco'    => $this->numeroONulo($request->input('comision_banco')),
                'descuento_empresa' => $this->numeroONulo($request->input('descuento_empresa')),
                'tarjetas'          => is_array($tarjetas) ? array_map(function ($t) {
                    return [
                        'id'                    => $this->enteroONulo($t['id'] ?? null),
                        'nombre'                => $t['nombre'] ?? null,
                        'emi_id'                => $this->enteroONulo($t['emi_id'] ?? null),
                        'comision_banco'        => $this->numeroONulo($t['comision_banco'] ?? null),
                        'descuento_empresa'     => $this->numeroONulo($t['descuento_empresa'] ?? null),
                        'activo'                => $this->booleanoONulo($t['activo'] ?? null),
                        'plazos'                => $this->payloadPlazos($t['plazos'] ?? null),
                    ];
                }, array_values($tarjetas)) : [],
                'auditoria'         => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT ventas.fn_tarjeta_banco_guardar(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                $datos = $respuesta['banco'] ?? [];
                $datos['avisos'] = $respuesta['avisos'] ?? [];

                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], $datos));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron guardar las tarjetas del banco', $e->getMessage()));
        }
    }

    // 27-oct: los adquirentes ya no existen: el emisor de la tarjeta es public.emisor y se mantiene en
    // BancosEmisoresController.

    // Las tarjetas del banco con sus plazos: el default, el descuento fijo, las promociones y el descuento de hoy.
    public function getDescuentosPlazos($ban_id)
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_tarjeta_plazo_descuento_buscar(?) AS resultado', [(int) $ban_id]);

            if (!$fila || !$fila->resultado) {
                return response()->json(RespuestaApi::returnResultado('error', 'El banco no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los descuentos de los plazos', $e->getMessage()));
        }
    }

    // Guarda los descuentos de los plazos cambiados del banco (fijo y promociones) en un solo paso.
    public function guardarDescuentosPlazos(Request $request, $ban_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'plazos'                                   => 'nullable|array',
                'plazos.*'                                 => 'array',
                'plazos.*.tarjeta_plazo_id'                => 'nullable|integer',
                'plazos.*.fijo'                            => 'nullable|numeric',
                'plazos.*.promociones'                     => 'nullable|array',
                'plazos.*.promociones.*'                   => 'array',
                'plazos.*.promociones.*.id'                => 'nullable|integer',
                'plazos.*.promociones.*.descuento_empresa' => 'nullable|numeric',
                'plazos.*.promociones.*.fecha_inicio'      => 'nullable|string',
                'plazos.*.promociones.*.fecha_fin'         => 'nullable|string',
                'plazos.*.promociones.*.activo'            => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $plazos = $request->input('plazos');
            $payload = [
                'ban_id'    => (int) $ban_id,
                'plazos'    => is_array($plazos) ? array_map(function ($p) {
                    return [
                        'tarjeta_plazo_id' => $this->enteroONulo($p['tarjeta_plazo_id'] ?? null),
                        'fijo'             => $this->numeroONulo($p['fijo'] ?? null),
                        'promociones'      => $this->payloadPromociones($p['promociones'] ?? null),
                    ];
                }, array_values($plazos)) : [],
                'auditoria' => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT ventas.fn_tarjeta_plazo_descuento_guardar(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], $respuesta['descuentos'] ?? null));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron guardar los descuentos de los plazos', $e->getMessage()));
        }
    }

    // 27-oct: la auditoria de un banco (auditoria.logs_cambios), como la de Margen: lo guardado en Tarjetas y
    // comisiones (tabla tarjeta_banco) o en Descuentos de plazos (tarjeta_plazo_descuento); el resumen y el historial
    // paginado.
    public function tarjetasAuditoria(Request $request)
    {
        try {
            $banId    = (int) $request->query('ban_id', 0);
            $tabla    = (string) $request->query('tabla', '');
            $pagina   = min(max((int) $request->query('pagina', 1), 1), 1000000);
            $tamanio  = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($banId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Banco no válido', null));
            }
            if (!in_array($tabla, self::TABLAS_AUDITORIA, true)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Auditoría no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_tarjeta_auditoria_resumen(?, ?)', [$banId, $tabla]);
            $eventos = DB::select('SELECT * FROM ventas.fn_tarjeta_auditoria_listar_paginacion(?, ?, ?, ?, ?)',
                [$banId, $tabla, $pagina, $tamanio, $busqueda]);
            $total   = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total'   => (int) $total,
                'pagina'  => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del banco', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------------

    // Sin la clave (o null) los plazos no se tocan; con arreglo, sin id = nuevo y con id = existente (la funcion
    // verifica que sea de la tarjeta).
    private function payloadPlazos($plazos): ?array
    {
        if (!is_array($plazos)) {
            return null;
        }

        return array_map(function ($p) {
            return [
                'id'              => $this->enteroONulo($p['id'] ?? null),
                'tipo_tarjeta_id' => $this->enteroONulo($p['tipo_tarjeta_id'] ?? null),
                'plazo'           => $p['plazo'] ?? null,
                'meses_gracia'    => $p['meses_gracia'] ?? null,
                'comision_banco'  => $this->numeroONulo($p['comision_banco'] ?? null),
                'activo'          => $this->booleanoONulo($p['activo'] ?? null),
            ];
        }, array_values($plazos));
    }

    // Igual que los plazos: sin la clave, las promociones del plazo no se tocan.
    private function payloadPromociones($promociones): ?array
    {
        if (!is_array($promociones)) {
            return null;
        }

        return array_map(function ($r) {
            return [
                'id'                => $this->enteroONulo($r['id'] ?? null),
                'descuento_empresa' => $r['descuento_empresa'] ?? null,
                'fecha_inicio'      => $r['fecha_inicio'] ?? null,
                'fecha_fin'         => $r['fecha_fin'] ?? null,
                'activo'            => $this->booleanoONulo($r['activo'] ?? null),
            ];
        }, array_values($promociones));
    }

    private function enteroONulo($valor): ?int
    {
        return ($valor === null || trim((string) $valor) === '') ? null : (int) $valor;
    }

    // Vacio = sin valor (en tarjetas, hereda del nivel de arriba); el numero va tal cual a la funcion.
    private function numeroONulo($valor)
    {
        return ($valor === null || trim((string) $valor) === '') ? null : $valor;
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

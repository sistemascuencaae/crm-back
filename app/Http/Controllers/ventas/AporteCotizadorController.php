<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Aportes del cotizador (clon de MarkupCotizadorController; valor en dolares y cabecera sin rango
// de negociacion): ventas.caporte, ventas.daporte y ventas.asignacion_aporte_productos. El negocio
// vive en ventas.fn_caporte_* y ventas.fn_asignacion_aporte_productos_* (produccion_aporte_cotizador.sql):
// este controller solo valida la forma, arma el jsonb con 'auditoria' y traduce la respuesta.
// Los productos se graban con el mismo add/edit de la cabecera (clave 'productos').
class AporteCotizadorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Todas las cabeceras (activas e inactivas), sin paginar.
    public function listAporteCotizador()
    {
        try {
            $registros = DB::select('SELECT * FROM ventas.fn_caporte_listar()');

            // PDO entrega los numeric como texto: al JSON van como numeros.
            foreach ($registros as $r) {
                $r->valor_aporte = (float) $r->valor_aporte;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $registros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las cabeceras de aporte', $e->getMessage()));
        }
    }

    // Conceptos de aporte (con sus topes) y regiones activas para los combos del modal.
    public function combosAporteCotizador()
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_caporte_combos() AS resultado');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los conceptos y regiones', $e->getMessage()));
        }
    }

    // Cabecera con sus detalles, para precargar el modal de editar.
    public function getAporteCotizador($id)
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_caporte_buscar(?) AS resultado', [(int) $id]);

            if (!$fila || !$fila->resultado) {
                return response()->json(RespuestaApi::returnResultado('error', 'La cabecera de aporte no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo obtener la cabecera de aporte', $e->getMessage()));
        }
    }

    public function addAporteCotizador(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasAporte());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadAporte($request);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_caporte_crear(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'id' => $respuesta['id'] ?? null,
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo crear la cabecera de aporte', $e->getMessage()));
        }
    }

    public function editAporteCotizador(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasAporte());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadAporte($request);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_caporte_modificar(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'id' => $respuesta['id'] ?? null,
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo actualizar la cabecera de aporte', $e->getMessage()));
        }
    }

    // Tabla de productos del modal: los activos (y los inactivos que ya tiene la cabecera) con
    // asignado y conflicto. Query ?caporte_id=&reg_id=&pcotizador_id=; el que falte va NULL.
    public function catalogoProductosAporteCotizador(Request $request)
    {
        try {
            $validator = Validator::make($request->query(), [
                'caporte_id'    => 'nullable|integer',
                'reg_id'        => 'nullable|integer',
                'pcotizador_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $registros = DB::select('SELECT * FROM ventas.fn_asignacion_aporte_productos_catalogo_productos(?::integer, ?::integer, ?::integer)', [
                $this->enteroONulo($request->query('caporte_id')),
                $this->enteroONulo($request->query('reg_id')),
                $this->enteroONulo($request->query('pcotizador_id')),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $registros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el catálogo de productos', $e->getMessage()));
        }
    }

    // Auditoria de UNA cabecera: sus guardados y sus asignaciones (auditoria.logs_cambios).
    public function aporteCotizadorAuditoria(Request $request)
    {
        try {
            $id       = (int) $request->query('id', 0);
            $pagina   = max((int) $request->query('pagina', 1), 1);
            $tamanio  = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Cabecera de aporte no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_caporte_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_caporte_auditoria_listar_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);
            $total   = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total'   => (int) $total,
                'pagina'  => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la cabecera de aporte', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------------

    // Solo los tipos. Lo obligatorio y el negocio (con mensajes en espanol) lo valida
    // ventas.fn_caporte_validaciones. La cabecera no lleva rango de negociacion; el detalle si.
    private function reglasAporte(): array
    {
        return [
            'pcotizador_id'                 => 'nullable|integer',
            'reg_id'                        => 'nullable|integer',
            'nombre'                        => 'nullable|string|max:255',
            'valor_aporte'                  => 'nullable|numeric',
            'activo'                        => 'nullable|boolean',
            'detalles'                      => 'nullable|array',
            'detalles.*'                    => 'array',
            'detalles.*.id'                 => 'nullable|integer',
            'detalles.*.orden'              => 'nullable|integer',
            'detalles.*.fecha_inicio'       => 'nullable|string',
            'detalles.*.fecha_fin'          => 'nullable|string',
            'detalles.*.lunes'              => 'nullable|boolean',
            'detalles.*.martes'             => 'nullable|boolean',
            'detalles.*.miercoles'          => 'nullable|boolean',
            'detalles.*.jueves'             => 'nullable|boolean',
            'detalles.*.viernes'            => 'nullable|boolean',
            'detalles.*.sabado'             => 'nullable|boolean',
            'detalles.*.domingo'            => 'nullable|boolean',
            'detalles.*.minimo_negociacion' => 'nullable|numeric',
            'detalles.*.maximo_negociacion' => 'nullable|numeric',
            'detalles.*.valor_aporte'       => 'nullable|numeric',
            'detalles.*.activo'             => 'nullable|boolean',
            'productos'                     => 'nullable|array',
            'productos.*'                   => 'integer',
        ];
    }

    private function payloadAporte(Request $request): array
    {
        $payload = [
            'pcotizador_id'      => $request->input('pcotizador_id'),
            'reg_id'             => $request->input('reg_id'),
            'nombre'             => $request->input('nombre'),
            'valor_aporte'       => $request->input('valor_aporte'),
            'activo'             => $this->booleanoONulo($request->input('activo')),
            'detalles'           => $this->payloadDetalles($request),
        ];

        // Lista completa de productos de la cabecera ([] = quitar todos). Sin la clave (o null) no se tocan.
        if (is_array($request->input('productos'))) {
            $payload['productos'] = array_values(array_map('intval', $request->input('productos')));
        }

        return $payload;
    }

    // Sin id = nuevo; con id = existente (la funcion verifica que sea de esta cabecera).
    // Los que no vienen no se tocan; el dia que no viene cuenta como no marcado.
    private function payloadDetalles(Request $request): array
    {
        return array_map(function ($d) {
            $detalle = [
                'id'                 => isset($d['id']) && $d['id'] !== '' ? (int) $d['id'] : null,
                'orden'              => $d['orden'] ?? null,
                'fecha_inicio'       => $d['fecha_inicio'] ?? null,
                'fecha_fin'          => $d['fecha_fin'] ?? null,
                'minimo_negociacion' => $d['minimo_negociacion'] ?? null,
                'maximo_negociacion' => $d['maximo_negociacion'] ?? null,
                'valor_aporte'       => $d['valor_aporte'] ?? null,
                'activo'             => $this->booleanoONulo($d['activo'] ?? null),
            ];
            foreach (['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'] as $dia) {
                $detalle[$dia] = filter_var($d[$dia] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            return $detalle;
        }, array_values((array) $request->input('detalles', [])));
    }

    // null se queda null (la funcion pone el valor por defecto o conserva el actual).
    private function booleanoONulo($valor): ?bool
    {
        return ($valor === null || $valor === '') ? null : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    // Entero del query string; null si falta o viene vacio.
    private function enteroONulo($valor): ?int
    {
        return ($valor === null || trim((string) $valor) === '') ? null : (int) $valor;
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

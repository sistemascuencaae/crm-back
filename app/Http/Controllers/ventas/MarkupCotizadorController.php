<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Markup del cotizador (pestana 2): cabeceras ventas.cmarkup, sus detalles ventas.dmarkup
// y los productos de ventas.asignacion_markup_productos, todo con un solo Guardar (add/edit).
// El negocio vive en ventas.fn_cmarkup_* y ventas.fn_asignacion_markup_productos_* (PLANES/COTIZADOR):
// este controller solo valida la forma, arma el jsonb con 'auditoria' y traduce la respuesta.
class MarkupCotizadorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Todas las cabeceras (activas e inactivas), sin paginar.
    public function listMarkupCotizador()
    {
        try {
            $registros = DB::select('SELECT * FROM ventas.fn_cmarkup_listar()');

            // PDO entrega los numeric como texto: al JSON van como numeros.
            foreach ($registros as $r) {
                $r->valor_markup       = (float) $r->valor_markup;
                $r->minimo_negociacion = (float) $r->minimo_negociacion;
                $r->maximo_negociacion = (float) $r->maximo_negociacion;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $registros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las cabeceras de markup', $e->getMessage()));
        }
    }

    // Conceptos de markup (con sus topes) y regiones activas para los combos del modal.
    public function combosMarkupCotizador()
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_cmarkup_combos() AS resultado');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los conceptos y regiones', $e->getMessage()));
        }
    }

    // Cabecera con sus detalles, para precargar el modal de editar.
    public function getMarkupCotizador($id)
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_cmarkup_buscar(?) AS resultado', [(int) $id]);

            if (!$fila || !$fila->resultado) {
                return response()->json(RespuestaApi::returnResultado('error', 'La cabecera de markup no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo obtener la cabecera de markup', $e->getMessage()));
        }
    }

    public function addMarkupCotizador(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasMarkup());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadMarkup($request);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_cmarkup_crear(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'id' => $respuesta['id'] ?? null,
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo crear la cabecera de markup', $e->getMessage()));
        }
    }

    public function editMarkupCotizador(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasMarkup());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadMarkup($request);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_cmarkup_modificar(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'id' => $respuesta['id'] ?? null,
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo actualizar la cabecera de markup', $e->getMessage()));
        }
    }

    // Catalogo de la tabla de productos del modal: activos + inactivos ya asignados, con
    // asignado (en esta cabecera) y conflicto (otra cabecera de esa region y concepto).
    // ?cmarkup_id=&reg_id=&pcotizador_id= : el que falte va NULL (al crear no hay cabecera).
    public function catalogoProductosMarkupCotizador(Request $request)
    {
        try {
            $validator = Validator::make($request->query(), [
                'cmarkup_id'    => 'nullable|integer',
                'reg_id'        => 'nullable|integer',
                'pcotizador_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $registros = DB::select('SELECT * FROM ventas.fn_asignacion_markup_productos_catalogo_productos(?::integer, ?::integer, ?::integer)', [
                $this->enteroONulo($request->query('cmarkup_id')),
                $this->enteroONulo($request->query('reg_id')),
                $this->enteroONulo($request->query('pcotizador_id')),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $registros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el catálogo de productos', $e->getMessage()));
        }
    }

    // Auditoria de UNA cabecera: sus guardados y sus asignaciones (auditoria.logs_cambios).
    public function markupCotizadorAuditoria(Request $request)
    {
        try {
            $id       = (int) $request->query('id', 0);
            $pagina   = max((int) $request->query('pagina', 1), 1);
            $tamanio  = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Cabecera de markup no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_cmarkup_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_cmarkup_auditoria_listar_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);
            $total   = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total'   => (int) $total,
                'pagina'  => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la cabecera de markup', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------------

    // Solo los tipos. Lo obligatorio y el negocio (con mensajes en espanol) lo valida
    // ventas.fn_cmarkup_validaciones.
    private function reglasMarkup(): array
    {
        return [
            'pcotizador_id'                 => 'nullable|integer',
            'reg_id'                        => 'nullable|integer',
            'nombre'                        => 'nullable|string|max:255',
            'valor_markup'                  => 'nullable|numeric',
            'minimo_negociacion'            => 'nullable|numeric',
            'maximo_negociacion'            => 'nullable|numeric',
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
            'detalles.*.valor_markup'       => 'nullable|numeric',
            'detalles.*.activo'             => 'nullable|boolean',
            'productos'                     => 'nullable|array',
            'productos.*'                   => 'integer',
        ];
    }

    private function payloadMarkup(Request $request): array
    {
        $payload = [
            'pcotizador_id'      => $request->input('pcotizador_id'),
            'reg_id'             => $request->input('reg_id'),
            'nombre'             => $request->input('nombre'),
            'valor_markup'       => $request->input('valor_markup'),
            'minimo_negociacion' => $request->input('minimo_negociacion'),
            'maximo_negociacion' => $request->input('maximo_negociacion'),
            'activo'             => $this->booleanoONulo($request->input('activo')),
            'detalles'           => $this->payloadDetalles($request),
        ];

        // Lista final de productos: sin la clave (o null) no se tocan; [] los quita todos.
        if (is_array($request->input('productos'))) {
            $payload['productos'] = array_map('intval', array_values($request->input('productos')));
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
                'valor_markup'       => $d['valor_markup'] ?? null,
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

    // Entero del query string; null si falta o viene vacio (lo demas ya lo freno el Validator).
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

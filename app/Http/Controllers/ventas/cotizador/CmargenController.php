<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\ventas\cotizador\Cmargen;
use App\Models\ventas\cotizador\Dmargen;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Version 1.0
// Ventas > Margen y aportes, pestaña Margen (V01): las cabeceras ventas.cmargen, sus detalles ventas.dmargen y sus productos
// ventas.cmargen_productos, todo con un solo Guardar (agregar o editar). Sin eliminar: cabeceras y detalles se desactivan.
// El negocio vive en ventas.fn_cmargen_*, ventas.fn_dmargen_* y ventas.fn_cmargen_productos_* (PLANES/COTIZADOR/
// produccion_cmargen.sql): este controller valida la forma, arma el jsonb con los campos de los modelos (Cmargen y Dmargen),
// la lista de productos y el bloque 'auditoria', y devuelve tal cual el {success, message} de la función. Viene de
// ventas/MargenCotizadorController (Fase 2 del plan del cotizador, 11-nov-2026), con los mismos mensajes y las mismas
// funciones SQL (las que confundían, con su nombre nuevo). El ojito de Región pasó a RegionController.
class CmargenController extends Controller
{
    use AuditoriaForenseTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listCmargen — todas las cabeceras (activas e inactivas), con el concepto, la región y cuántos detalles
    // activos y productos tienen. Son pocas: sin paginar. Antes listMargenCotizador.
    public function listCmargen()
    {
        try {
            $registros = DB::select('SELECT * FROM ventas.fn_cmargen_listar()');

            // PDO entrega los numeric como texto: al JSON van como números.
            foreach ($registros as $r) {
                $r->valor_margen = (float) $r->valor_margen;
                $r->minimo_negociacion = (float) $r->minimo_negociacion;
                $r->maximo_negociacion = (float) $r->maximo_negociacion;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $registros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las cabeceras de margen', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/listCombosCmargen — los combos del formulario en una sola llamada: los conceptos de tipo MARGEN (con
    // sus topes) y las regiones activas. Antes combosMargenCotizador.
    public function listCombosCmargen()
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_cmargen_combos_listar() AS resultado');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los conceptos y regiones', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/getCmargen/{id} — una cabecera con sus detalles, para precargar el formulario de editar. Antes
    // getMargenCotizador.
    public function getCmargen($id)
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_cmargen_buscar(?) AS resultado', [(int) $id]);

            if (!$fila || !$fila->resultado) {
                return response()->json(RespuestaApi::returnResultado('error', 'La cabecera de margen no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo obtener la cabecera de margen', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/addCmargen — crea una cabecera con sus detalles y, si vienen, sus productos. Devuelve su id. Antes
    // addMargenCotizador.
    public function addCmargen(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasRequest());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadCmargen($request);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_cmargen_crear(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'id' => $respuesta['id'] ?? null,
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo crear la cabecera de margen', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/editCmargen/{id} — modifica una cabecera con sus detalles y, si vienen, sus productos. Devuelve su id.
    // Antes editMargenCotizador.
    public function editCmargen(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasRequest());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadCmargen($request);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_cmargen_modificar(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'id' => $respuesta['id'] ?? null,
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo actualizar la cabecera de margen', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/listProductosCmargen?cmargen_id&reg_id&cotizador_parametros_id — la tabla de productos del formulario:
    // los activos y los inactivos ya asignados, con asignado (en esta cabecera) y conflicto (otra cabecera de esa región y
    // concepto). El que falte va NULL (al crear no hay cabecera). Antes catalogoProductosMargenCotizador.
    public function listProductosCmargen(Request $request)
    {
        try {
            $validator = Validator::make($request->query(), [
                'cmargen_id' => 'nullable|integer',
                'reg_id' => 'nullable|integer',
                'cotizador_parametros_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $registros = DB::select('SELECT * FROM ventas.fn_cmargen_productos_catalogo_productos(?::integer, ?::integer, ?::integer)', [
                $this->enteroONulo($request->query('cmargen_id')),
                $this->enteroONulo($request->query('reg_id')),
                $this->enteroONulo($request->query('cotizador_parametros_id')),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $registros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el catálogo de productos', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/cmargenAuditoria?id&pagina&tamanio&busqueda — resumen + historial paginado de UNA cabecera: sus
    // guardados (con los detalles) y la asignación y el retiro de sus productos (auditoria.logs_cambios). Antes
    // margenCotizadorAuditoria.
    public function cmargenAuditoria(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Cabecera de margen no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_cmargen_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_cmargen_auditoria_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la cabecera de margen', $e->getMessage()));
        }
    }

    // Solo los tipos. Lo obligatorio y el negocio (con mensajes en español) lo valida ventas.fn_cmargen_validaciones.
    private function reglasRequest(): array
    {
        return [
            'cotizador_parametros_id' => 'nullable|integer',
            'reg_id' => 'nullable|integer',
            'nombre' => 'nullable|string|max:255',
            'valor_margen' => 'nullable|numeric',
            'minimo_negociacion' => 'nullable|numeric',
            'maximo_negociacion' => 'nullable|numeric',
            'activo' => 'nullable|boolean',
            'detalles' => 'nullable|array',
            'detalles.*' => 'array',
            'detalles.*.id' => 'nullable|integer',
            'detalles.*.orden' => 'nullable|integer',
            'detalles.*.fecha_inicio' => 'nullable|string',
            'detalles.*.fecha_fin' => 'nullable|string',
            'detalles.*.lunes' => 'nullable|boolean',
            'detalles.*.martes' => 'nullable|boolean',
            'detalles.*.miercoles' => 'nullable|boolean',
            'detalles.*.jueves' => 'nullable|boolean',
            'detalles.*.viernes' => 'nullable|boolean',
            'detalles.*.sabado' => 'nullable|boolean',
            'detalles.*.domingo' => 'nullable|boolean',
            'detalles.*.minimo_negociacion' => 'nullable|numeric',
            'detalles.*.maximo_negociacion' => 'nullable|numeric',
            'detalles.*.valor_margen' => 'nullable|numeric',
            'detalles.*.activo' => 'nullable|boolean',
            'productos' => 'nullable|array',
            'productos.*' => 'integer',
        ];
    }

    // La cabecera con los campos del modelo Cmargen, sus detalles y, si viene, la lista final de productos.
    private function payloadCmargen(Request $request): array
    {
        $campos = (new Cmargen())->getFillable();
        $payload = array_combine($campos, array_map(fn ($campo) => $request->input($campo), $campos));
        $payload['activo'] = $this->booleanoONulo($payload['activo']);
        $payload['detalles'] = $this->payloadDetalles($request);

        // Lista final de productos: sin la clave (o null) no se tocan; [] los quita todos.
        if (is_array($request->input('productos'))) {
            $payload['productos'] = array_map('intval', array_values($request->input('productos')));
        }

        return $payload;
    }

    // Cada detalle con los campos del modelo Dmargen. Sin id = nuevo; con id = existente (la función verifica que sea de
    // esta cabecera). Los que no vienen no se tocan; el día que no viene cuenta como no marcado.
    private function payloadDetalles(Request $request): array
    {
        $campos = (new Dmargen())->getFillable();

        return array_map(function ($d) use ($campos) {
            $detalle = ['id' => isset($d['id']) && $d['id'] !== '' ? (int) $d['id'] : null];
            foreach ($campos as $campo) {
                $detalle[$campo] = $d[$campo] ?? null;
            }
            $detalle['activo'] = $this->booleanoONulo($detalle['activo']);
            foreach (Dmargen::DIAS as $dia) {
                $detalle[$dia] = filter_var($d[$dia] ?? false, FILTER_VALIDATE_BOOLEAN);
            }

            return $detalle;
        }, array_values((array) $request->input('detalles', [])));
    }

    // null se queda null (la función pone el valor por defecto o conserva el actual).
    private function booleanoONulo($valor): ?bool
    {
        return ($valor === null || $valor === '') ? null : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    // Entero del query string; null si falta o viene vacío (lo demás ya lo frenó el Validator).
    private function enteroONulo($valor): ?int
    {
        return ($valor === null || trim((string) $valor) === '') ? null : (int) $valor;
    }
}

<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\ventas\cotizador\Linea;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Version 1.0
// Cotizador > Parámetros tipo calificación cliente, pestaña Performance crédito, panel izquierdo "Líneas" (ventas.linea): el
// catálogo de líneas que comparten Performance crédito (linea_id), Formal / Informal (linea_id) y el requerimiento automático
// 'formal informal' (valor_lista = el id). Se agregan y se cambian (nombre y activo); no se borran: se desactivan. Lo usa el
// mismo permiso del menú de la pantalla (el front pide create para agregar y edit para cambiar), igual que el resto de la pantalla.
// El negocio vive en ventas.fn_linea_* (PLANES/COTIZADOR/produccion_linea.sql): este controller valida la forma, arma el jsonb
// con los campos del modelo y el bloque 'auditoria', y traduce el código que levanta la función.
// 11-nov-2026 (líneas).
class LineaController extends Controller
{
    use AuditoriaForenseTrait;

    // Mensaje para cada código que levantan las funciones.
    private const MENSAJES_ERROR = [
        'REQUIERE_NOMBRE' => 'El nombre de la línea es obligatorio.',
        'NOMBRE_LARGO' => 'El nombre de la línea admite hasta 50 caracteres.',
        'LINEA_DUPLICADA' => 'Ya existe una línea con ese nombre.',
        'ACTIVO_INVALIDO' => 'Activo solo acepta sí o no.',
        'LINEA_NO_EXISTE' => 'La línea ya no existe.',
    ];

    // Los mensajes del Validator en español (la pantalla siempre manda todo bien: solo otro cliente de la API los ve).
    private const MENSAJES = [
        'required' => 'Falta el campo :attribute.',
        'integer'  => 'El campo :attribute tiene que ser un número entero.',
        'min'      => 'El campo :attribute no es válido.',
        'boolean'  => 'El campo :attribute tiene que ser verdadero o falso.',
        'string'   => 'El campo :attribute tiene que ser un texto.',
        'max'      => 'El campo :attribute no puede pasar de :max caracteres.',
    ];

    // Los nombres de los campos en esos mensajes.
    private const ATRIBUTOS = [
        'id' => 'línea',
        'nombre' => 'nombre',
        'activo' => 'activo',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listLinea?pagina&tamanio&busqueda — el panel izquierdo: todas las líneas (primero las activas, por nombre),
    // con cuántos tipos de cliente tiene cada una en Performance crédito (tipos_cliente). Busca por nombre.
    public function listLinea(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ventas.fn_linea_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las líneas', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/listCombosLinea — las líneas ACTIVAS para los combos (codigo = el id como texto, nombre): Clonar de
    // Performance crédito. Formal / Informal y el requerimiento 'formal informal' leen la misma función por su endpoint.
    public function listCombosLinea()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'lineas' => DB::select('SELECT * FROM ventas.fn_linea_activas_listar()'),
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las líneas', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/addLinea body {nombre} — una línea nueva (nace activa). Devuelve su id.
    public function addLinea(Request $request)
    {
        return $this->guardar($request, null);
    }

    // Version 1.0
    // POST /api/ventas/editLinea/{id} body {nombre, activo} — cambia el nombre o la desactiva / reactiva. Devuelve su id.
    public function editLinea(Request $request, $id)
    {
        return $this->guardar($request, $id);
    }

    // Version 1.0
    // GET /api/ventas/lineaAuditoria?id&pagina&tamanio&busqueda — resumen + historial paginado de UNA línea del catálogo
    // (auditoria.logs_cambios): su creación, los cambios de nombre y de activo.
    public function lineaAuditoria(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Parámetro no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_linea_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_linea_auditoria_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la línea', $e->getMessage()));
        }
    }

    // Agregar (id null) o cambiar: la forma con el Validator; el negocio (nombre obligatorio, hasta 50, único) lo valida
    // ventas.fn_linea_validaciones con sus códigos. Al cambiar, activo tiene que venir.
    private function guardar(Request $request, $id)
    {
        try {
            $validator = Validator::make(array_merge($request->all(), ['id' => $id]), [
                'id'     => 'nullable|integer|min:1',
                'nombre' => 'nullable|string|max:200',
                'activo' => ($id === null ? 'nullable' : 'required') . '|boolean',
            ], self::MENSAJES, self::ATRIBUTOS);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            // Los campos del modelo; activo como booleano (null = la función decide: nueva activa, o como estaba).
            $payload = Arr::only($request->all(), (new Linea())->getFillable());
            if (array_key_exists('activo', $payload)) {
                $payload['activo'] = $payload['activo'] === null ? null : filter_var($payload['activo'], FILTER_VALIDATE_BOOLEAN);
            }
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            if ($id === null) {
                $resultado = DB::selectOne('SELECT ventas.fn_linea_crear(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            } else {
                $payload['id'] = (int) $id;
                $resultado = DB::selectOne('SELECT ventas.fn_linea_modificar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', ['id' => (int) $resultado->id]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar la línea');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar la línea', $e->getMessage()));
        }
    }

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico.
    private function respuestaError(QueryException $e, string $generico)
    {
        // El código se busca como palabra entera, nunca con strpos: así no se confunde con otro código más largo que lo contenga.
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (preg_match('/\b' . preg_quote($codigo, '/') . '\b/', $e->getMessage())) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $generico, $e->getMessage()));
    }
}

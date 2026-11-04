<?php

namespace App\Http\Controllers\configuracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// 30-oct (fase 3b de Yupana): Configuración → Colores de calificación. El color del badge de cada calificación del
// cliente (tarjeta del kanban y cabecera del caso) vive en crm.calificacion_color; agregar, editar y eliminar van por
// crm.fn_calificacion_color_* (validaciones, mensajes y auditoría forense en la función, como Agencias y Clientes
// Dynamo); aquí se arma el jsonb y se traduce el código que levanta la función.
class CalificacionColorController extends Controller
{
    // 30-oct: textura = ORO | PLATA | BRONCE | PLATINO | DIAMANTE | vacío (color liso); el catálogo lo da fn_calificacion_color_texturas_listar.
    private const CAMPOS_PAYLOAD = ['calificacion', 'color', 'color_texto', 'orden', 'activo', 'textura'];

    // Mensaje amigable para cada código que levantan crm.fn_calificacion_color_validaciones / _modificar / _eliminar.
    private const MENSAJES_ERROR = [
        'REQUIERE_CALIFICACION' => 'La calificación es obligatoria.',
        'CALIFICACION_LARGA' => 'La calificación admite hasta 60 caracteres.',
        'CALIFICACION_DUPLICADA' => 'Ya existe una fila con esa calificación.',
        'REQUIERE_COLOR' => 'El color es obligatorio.',
        'COLOR_INVALIDO' => 'El color debe ser #RRGGBB.',
        'COLOR_TEXTO_INVALIDO' => 'El color del texto debe ser #RRGGBB.',
        'ORDEN_INVALIDO' => 'El orden debe ser un número entero.',
        'ACTIVO_INVALIDO' => 'El estado no es válido.',
        'TEXTURA_INVALIDA' => 'La textura no es válida.',
        'COLOR_NO_EXISTE' => 'La fila ya no existe.',
    ];

    public function __construct() {}

    // Version 1.0
    // GET /api/configuracion/listCalificacionColores — las filas ACTIVAS en orden: el mapa que cargan el kanban y la
    // cabecera del caso una sola vez (CalificacionColorService.colores()).
    public function listCalificacionColores()
    {
        try {
            $data = DB::select('SELECT * FROM crm.fn_calificacion_color_listar(true)');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los colores de calificación', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/configuracion/listCalificacionTexturas — las texturas del combo (codigo, nombre), una sola fuente con la
    // validación de la función.
    public function listCalificacionTexturas()
    {
        try {
            $data = DB::select('SELECT * FROM crm.fn_calificacion_color_texturas_listar()');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las texturas', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/configuracion/listCalificacionColoresPaginacion?pagina&tamanio&busqueda — el grid de la pantalla.
    // Responde {registros, total, pagina, tamanio} como Agencias.
    public function listCalificacionColoresPaginacion(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM crm.fn_calificacion_color_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los colores de calificación', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/configuracion/addCalificacionColor — crm.fn_calificacion_color_crear valida, inserta y deja la auditoría.
    public function addCalificacionColor(Request $request)
    {
        try {
            $payload = $request->only(self::CAMPOS_PAYLOAD);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_calificacion_color_crear(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $this->buscar((int) $resultado->id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar el color de calificación');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar el color de calificación', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/configuracion/editCalificacionColor/{id}
    public function editCalificacionColor(Request $request, $id)
    {
        try {
            $payload = $request->only(self::CAMPOS_PAYLOAD);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            DB::selectOne('SELECT crm.fn_calificacion_color_modificar(?::jsonb) AS id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $this->buscar((int) $id)));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al actualizar el color de calificación');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar el color de calificación', $e->getMessage()));
        }
    }

    // Version 1.0
    // DELETE /api/configuracion/deleteCalificacionColor/{id}
    public function deleteCalificacionColor(Request $request, $id)
    {
        try {
            $fila = $this->buscar((int) $id);

            DB::selectOne('SELECT crm.fn_calificacion_color_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $fila));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar el color de calificación');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar el color de calificación', $e->getMessage()));
        }
    }

    // La fila como la devuelve crm.fn_calificacion_color_buscar, o null si no existe.
    private function buscar(int $id): ?array
    {
        $fila = DB::selectOne('SELECT crm.fn_calificacion_color_buscar(?) AS datos', [$id]);

        return $fila && $fila->datos ? json_decode($fila->datos, true) : null;
    }

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico (como AgenciaController).
    private function respuestaError(QueryException $e, string $generico)
    {
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($e->getMessage(), $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $generico, $e->getMessage()));
    }

    // Contexto forense que las funciones guardan en auditoria.logs_cambios (módulo CALIFICACION_COLOR).
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ];
    }
}

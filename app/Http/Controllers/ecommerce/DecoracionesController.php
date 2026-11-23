<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Controllers\paginaWeb\DecoracionesTiendaController;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Decoraciones de temporada del inicio de la tienda (ecommerce.decoraciones): nieve, confeti, corazones en ciertas
// fechas. Listar, catálogos, crear, editar, papelera (borrado lógico), restaurar y auditoría.
// Las reglas viven en ecommerce.fn_decoracion_* (database/sql/decoraciones.sql); lo que pide la tienda está en
// paginaWeb/DecoracionesTiendaController.
class DecoracionesController extends Controller
{

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'DECORACION_NOMBRE_REQUERIDO' => 'Debe ingresar el nombre de la decoración.',
        'DECORACION_NOMBRE_LARGO' => 'El nombre de la decoración no puede tener más de 100 caracteres.',
        'DECORACION_DUPLICADA' => 'Ya existe una decoración con ese nombre (si está restaurando, cambie primero el nombre de la otra).',
        'ux_decoraciones_nombre' => 'Ya existe una decoración con ese nombre.',
        'DECORACION_EFECTO_INVALIDO' => 'Seleccione un efecto válido.',
        'DECORACION_INTENSIDAD_INVALIDA' => 'Seleccione una intensidad válida.',
        'DECORACION_SEGUNDOS_INVALIDOS' => 'Los segundos deben estar entre 5 y 60 (o dejarlo en "Todo el tiempo").',
        'DECORACION_FECHA_DESDE_REQUERIDA' => 'Debe ingresar la fecha desde.',
        'DECORACION_FECHA_HASTA_REQUERIDA' => 'Debe ingresar la fecha hasta.',
        'DECORACION_FECHA_DESDE_INVALIDA' => 'La fecha desde no es válida.',
        'DECORACION_FECHA_HASTA_INVALIDA' => 'La fecha hasta no es válida.',
        'DECORACION_FECHAS_INVERTIDAS' => 'La fecha hasta no puede ser anterior a la fecha desde.',
        'DECORACION_RANGO_ANUAL_LARGO' => 'Si se repite cada año, el rango de fechas no puede pasar de un año.',
        'DECORACION_ANUAL_INVALIDO' => 'El valor de "Se repite cada año" no es válido.',
        'DECORACION_ACTIVO_INVALIDO' => 'El valor de "Activa" no es válido.',
        'DECORACION_NO_EXISTE' => 'La decoración no existe o está en la papelera.',
        'DECORACION_NO_EN_PAPELERA' => 'La decoración no está en la papelera (puede que otro usuario ya la restauró).',
    ];

    private const MENSAJES_FORMULARIO = [
        'dec_nombre.required' => 'Debe ingresar el nombre de la decoración.',
        'dec_nombre.max' => 'El nombre de la decoración no puede tener más de 100 caracteres.',
        'dec_efecto.required' => 'Seleccione el efecto.',
        'dec_efecto.max' => 'Seleccione un efecto válido.',
        'dec_intensidad.max' => 'Seleccione una intensidad válida.',
        'dec_segundos.integer' => 'Los segundos deben ser un número entero.',
        'dec_fecha_desde.required' => 'Debe ingresar la fecha desde.',
        'dec_fecha_desde.date_format' => 'La fecha desde no es válida.',
        'dec_fecha_hasta.required' => 'Debe ingresar la fecha hasta.',
        'dec_fecha_hasta.date_format' => 'La fecha hasta no es válida.',
        'dec_anual.boolean' => 'El valor de "Se repite cada año" no es válido.',
        'dec_activo.boolean' => 'El valor de "Activa" no es válido.',
    ];

    // Lo único que se acepta del formulario.
    private const CAMPOS_PAYLOAD = [
        'dec_nombre', 
        'dec_efecto', 
        'dec_intensidad', 
        'dec_segundos', 
        'dec_fecha_desde', 
        'dec_fecha_hasta', 
        'dec_anual', 
        'dec_activo'
    ];

    public function __construct()
    {
        // Lo público (la decoración de hoy) está en paginaWeb/DecoracionesTiendaController.
        $this->middleware('auth:api');
    }

    // Decoraciones (fuera de la papelera), paginadas, con su estado de hoy -> ecommerce.fn_decoracion_listar_paginacion.
    // GET ?pagina=&tamanio=&busqueda=
    public function listar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_decoracion_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Decoraciones listadas con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las decoraciones', $th->getMessage()));
        }
    }

    // Efectos e intensidades para los combos del formulario -> ecommerce.fn_decoracion_catalogos.
    public function catalogos()
    {
        try {
            $catalogos = DB::selectOne('SELECT ecommerce.fn_decoracion_catalogos() AS c');

            return response()->json(RespuestaApi::returnResultado('success', 'Catálogos cargados con éxito', json_decode($catalogos->c, true)));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los catálogos', $th->getMessage()));
        }
    }

    // Crea una decoración -> ecommerce.fn_decoracion_add.
    // Body: { dec_nombre, dec_efecto, dec_intensidad?, dec_segundos?, dec_fecha_desde, dec_fecha_hasta, dec_anual?, dec_activo? }
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), $this->reglas(false), self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_decoracion_add(?::jsonb) AS dec_id', [json_encode($request->only(self::CAMPOS_PAYLOAD) + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            DecoracionesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Decoración creada con éxito', ['dec_id' => $resultado->dec_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear la decoración');
        }
    }

    // Modifica una decoración -> ecommerce.fn_decoracion_edit. Lo que no viene se queda como está; dec_segundos null =
    // todo el tiempo.
    public function editar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), $this->reglas(true), self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $payload = $request->only(self::CAMPOS_PAYLOAD) + ['dec_id' => $id];
            $resultado = DB::selectOne('SELECT ecommerce.fn_decoracion_edit(?::jsonb) AS dec_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            DecoracionesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Decoración guardada con éxito', ['dec_id' => $resultado->dec_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar la decoración');
        }
    }

    public function eliminar(Request $request, int $id)
    {
        return $this->accion('fn_decoracion_papelera', ['dec_id' => $id], $request, 'Decoración enviada a la papelera', 'Error al eliminar la decoración');
    }

    public function restaurar(Request $request, int $id)
    {
        return $this->accion('fn_decoracion_restaurar', ['dec_id' => $id], $request, 'Decoración restaurada con éxito', 'Error al restaurar la decoración');
    }

    // Decoraciones en la papelera -> ecommerce.fn_decoracion_papelera_paginacion.
    public function papelera(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_decoracion_papelera_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera cargada con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la papelera', $th->getMessage()));
        }
    }

    // Auditoría de UNA decoración (también de las de la papelera): {resumen, eventos, total, pagina, tamanio}.
    // GET ?dec_id=&pagina=&tamanio=&busqueda=
    public function auditoria(Request $request)
    {
        try {
            $id = (int) $request->query('dec_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Decoración no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ecommerce.fn_decoracion_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ecommerce.fn_decoracion_auditoria_listar_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la decoración', $th->getMessage()));
        }
    }

    // ================= Internos =================

    // Solo forma y tipos: las reglas (catálogo, rangos, fechas, duplicados) las decide fn_decoracion_validaciones.
    private function reglas(bool $editar): array
    {
        $requerido = $editar ? 'sometimes|required' : 'required';

        return [
            'dec_nombre' => "$requerido|string|max:100",
            'dec_efecto' => "$requerido|string|max:20",
            'dec_intensidad' => 'nullable|string|max:10',
            'dec_segundos' => 'nullable|integer',
            'dec_fecha_desde' => "$requerido|date_format:Y-m-d",
            'dec_fecha_hasta' => "$requerido|date_format:Y-m-d",
            'dec_anual' => $editar ? 'sometimes|boolean' : 'nullable|boolean',
            'dec_activo' => $editar ? 'sometimes|boolean' : 'nullable|boolean',
        ];
    }

    // Papelera / restaurar: una función que recibe { dec_id, auditoria } y devuelve el id.
    private function accion(string $funcion, array $datos, Request $request, string $exito, string $porDefecto)
    {
        try {
            DB::selectOne("SELECT ecommerce.$funcion(?::jsonb) AS id", [json_encode($datos + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            DecoracionesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', $exito, $datos));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, $porDefecto);
        }
    }

    // errorInfo[2] trae solo el mensaje de PG (getMessage() incluye el SQL con el payload).
    private function errorTraducido(QueryException $e, string $porDefecto)
    {
        $texto = $e->errorInfo[2] ?? $e->getMessage();

        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (preg_match('/\b' . preg_quote($codigo, '/') . '\b/', $texto)) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $porDefecto, $e->getMessage()));
    }

    // Contexto del usuario para la auditoría forense (auditoria.fn_registrar_evento).
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

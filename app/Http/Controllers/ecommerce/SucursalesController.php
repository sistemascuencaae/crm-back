<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Controllers\paginaWeb\SucursalesTiendaController;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Nuestras tiendas: sucursales (ecommerce.sucursales) y sus ciudades (ecommerce.sucursales_ciudades).
// Listar, crear, editar, ordenar, papelera (borrado lógico), auditoría, y lo que pide la tienda (públicas).
// Las reglas viven en ecommerce.fn_sucursal_* (database/sql/sucursales.sql). Reemplaza a paginaWeb/Sucursal*Controller.
class SucursalesController extends Controller
{

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        // Ciudades
        'CIUDAD_NOMBRE_REQUERIDO' => 'Debe ingresar el nombre de la ciudad.',
        'CIUDAD_NOMBRE_LARGO' => 'El nombre de la ciudad no puede tener más de 80 caracteres.',
        'CIUDAD_DUPLICADA' => 'Ya existe una ciudad con ese nombre (si está restaurando, cambie primero el nombre de la otra).',
        'ux_sucursales_ciudades_nombre' => 'Ya existe una ciudad con ese nombre.',
        'CIUDAD_ACTIVO_INVALIDO' => 'El valor de "Activa" no es válido.',
        'CIUDAD_NO_EXISTE' => 'La ciudad no existe o está en la papelera.',
        'CIUDAD_NO_EN_PAPELERA' => 'La ciudad no está en la papelera (puede que otro usuario ya la restauró).',
        'CIUDAD_CON_SUCURSALES' => 'La ciudad tiene sucursales: muévalas a otra ciudad o elimínelas antes.',
        'CIUDAD_EN_PAPELERA' => 'La ciudad de esta sucursal está en la papelera: restaure primero la ciudad.',
        'REQUIERE_CIUDADES' => 'Envíe el nuevo orden de las ciudades.',
        'CIUDADES_NO_COINCIDEN' => 'Las ciudades cambiaron mientras las ordenaba. Vuelva a cargarlas.',
        // Sucursales
        'SUCURSAL_CIUDAD_REQUERIDA' => 'Seleccione la ciudad de la sucursal.',
        'SUCURSAL_CIUDAD_NO_EXISTE' => 'La ciudad seleccionada no existe o está en la papelera.',
        'SUCURSAL_NOMBRE_REQUERIDO' => 'Debe ingresar el nombre de la sucursal.',
        'SUCURSAL_NOMBRE_LARGO' => 'El nombre de la sucursal no puede tener más de 100 caracteres.',
        'SUCURSAL_DUPLICADA' => 'Ya existe una sucursal con ese nombre en esa ciudad (si está restaurando, cambie primero el nombre de la otra).',
        'ux_sucursales_nombre' => 'Ya existe una sucursal con ese nombre en esa ciudad.',
        'SUCURSAL_DIRECCION_REQUERIDA' => 'Debe ingresar la dirección.',
        'SUCURSAL_DIRECCION_LARGA' => 'La dirección no puede tener más de 250 caracteres.',
        'SUCURSAL_HORARIO_LARGO' => 'El horario no puede tener más de 150 caracteres.',
        'SUCURSAL_TELEFONO_INVALIDO' => 'El teléfono debe tener entre 7 y 20 caracteres: números, espacios, guiones, paréntesis o +.',
        'SUCURSAL_COORDENADAS_INCOMPLETAS' => 'Ingrese la latitud y la longitud juntas, o deje ambas vacías.',
        'SUCURSAL_COORDENADAS_INVERTIDAS' => 'Parece que la latitud y la longitud están invertidas.',
        'SUCURSAL_LATITUD_INVALIDA' => 'La latitud no es válida o está fuera de Ecuador.',
        'SUCURSAL_LONGITUD_INVALIDA' => 'La longitud no es válida o está fuera de Ecuador.',
        'SUCURSAL_ACTIVO_INVALIDO' => 'El valor de "Activa" no es válido.',
        'SUCURSAL_NO_EXISTE' => 'La sucursal no existe o está en la papelera.',
        'SUCURSAL_NO_EN_PAPELERA' => 'La sucursal no está en la papelera (puede que otro usuario ya la restauró).',
        'REQUIERE_SUCURSALES' => 'Envíe el nuevo orden de las sucursales.',
        'SUCURSALES_NO_COINCIDEN' => 'Las sucursales de la ciudad cambiaron mientras trabajaba (se agregó, movió o eliminó una). Vuelva a cargarla.',
    ];

    private const MENSAJES_FORMULARIO = [
        'sci_id.required' => 'Seleccione la ciudad de la sucursal.',
        'sci_id.integer' => 'La ciudad no es válida.',
        'suc_nombre.required' => 'Debe ingresar el nombre de la sucursal.',
        'suc_nombre.max' => 'El nombre de la sucursal no puede tener más de 100 caracteres.',
        'suc_direccion.required' => 'Debe ingresar la dirección.',
        'suc_direccion.max' => 'La dirección no puede tener más de 250 caracteres.',
        'suc_horario.max' => 'El horario no puede tener más de 150 caracteres.',
        'suc_telefono.max' => 'El teléfono no puede tener más de 20 caracteres.',
        'suc_latitud.numeric' => 'La latitud debe ser un número.',
        'suc_longitud.numeric' => 'La longitud debe ser un número.',
        'suc_activo.boolean' => 'El valor de "Activa" no es válido.',
        'sci_nombre.required' => 'Debe ingresar el nombre de la ciudad.',
        'sci_nombre.max' => 'El nombre de la ciudad no puede tener más de 80 caracteres.',
        'sci_activo.boolean' => 'El valor de "Activa" no es válido.',
        'sucursales.required' => 'Envíe el nuevo orden de las sucursales.',
        'sucursales.array' => 'El orden de las sucursales no es válido.',
        'ciudades.required' => 'Envíe el nuevo orden de las ciudades.',
        'ciudades.array' => 'El orden de las ciudades no es válido.',
    ];

    // Lo único que se acepta del formulario de la sucursal.
    private const CAMPOS_PAYLOAD = [
        'sci_id', 
        'suc_nombre', 
        'suc_direccion', 
        'suc_horario', 
        'suc_telefono', 
        'suc_latitud', 
        'suc_longitud', 
        'suc_activo'
    ];

    public function __construct()
    {
        // Lo público (las sucursales de la tienda) está en paginaWeb/SucursalesTiendaController.
        $this->middleware('auth:api');
    }

    // ================= Sucursales =================

    // Sucursales (fuera de la papelera), paginadas -> ecommerce.fn_sucursal_listar_paginacion.
    // GET ?pagina=&tamanio=&busqueda=&sci_id=   (sci_id vacío = todas)
    public function listar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));
            $sciId = $request->query('sci_id');
            $sciId = $sciId === null || $sciId === '' ? null : max((int) $sciId, 0);

            $registros = DB::select('SELECT * FROM ecommerce.fn_sucursal_listar_paginacion(?, ?, ?, ?)', [$pagina, $tamanio, $busqueda, $sciId]);
            // PDO entrega los decimales como texto: las coordenadas salen como número.
            foreach ($registros as $r) {
                $r->suc_latitud = $r->suc_latitud === null ? null : (float) $r->suc_latitud;
                $r->suc_longitud = $r->suc_longitud === null ? null : (float) $r->suc_longitud;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Sucursales listadas con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las sucursales', $th->getMessage()));
        }
    }

    // Crea una sucursal al final de su ciudad -> ecommerce.fn_sucursal_add.
    // Body: { sci_id, suc_nombre, suc_direccion, suc_horario?, suc_telefono?, suc_latitud?, suc_longitud?, suc_activo? }
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), $this->reglas(false), self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_sucursal_add(?::jsonb) AS suc_id', [json_encode($request->only(self::CAMPOS_PAYLOAD) + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            SucursalesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Sucursal creada con éxito', ['suc_id' => $resultado->suc_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear la sucursal');
        }
    }

    // Modifica una sucursal -> ecommerce.fn_sucursal_edit. Lo que no viene se queda como está; null borra un campo opcional.
    public function editar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), $this->reglas(true), self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $payload = $request->only(self::CAMPOS_PAYLOAD) + ['suc_id' => $id];
            $resultado = DB::selectOne('SELECT ecommerce.fn_sucursal_edit(?::jsonb) AS suc_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            SucursalesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Sucursal guardada con éxito', ['suc_id' => $resultado->suc_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar la sucursal');
        }
    }

    // Crea una sucursal nueva a partir de otra -> ecommerce.fn_sucursal_clonar. Lo que no viene se copia del origen.
    // Body: el mismo del formulario (suc_nombre obligatorio; puede ir a otra ciudad con sci_id).
    public function clonar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), ['suc_nombre' => 'required|string|max:100'] + $this->reglas(true), self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $payload = $request->only(self::CAMPOS_PAYLOAD) + ['suc_id_origen' => $id];
            $resultado = DB::selectOne('SELECT ecommerce.fn_sucursal_clonar(?::jsonb) AS suc_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            SucursalesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Sucursal clonada con éxito', ['suc_id' => $resultado->suc_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al clonar la sucursal');
        }
    }

    // Nuevo orden de las sucursales de una ciudad -> ecommerce.fn_sucursales_ordenar. Body: { sci_id, sucursales: [suc_id, ...] }
    public function ordenar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sci_id' => 'required|integer|min:1',
            'sucursales' => 'present|array',
            'sucursales.*' => 'integer|min:1',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            DB::selectOne('SELECT ecommerce.fn_sucursales_ordenar(?::jsonb) AS cambios', [json_encode([
                'sci_id' => $request->input('sci_id'),
                'sucursales' => array_values($request->input('sucursales', [])),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);
            SucursalesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado', null));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al ordenar las sucursales');
        }
    }

    public function eliminar(Request $request, int $id)
    {
        return $this->accion('fn_sucursal_papelera', ['suc_id' => $id], $request, 'Sucursal enviada a la papelera', 'Error al eliminar la sucursal');
    }

    public function restaurar(Request $request, int $id)
    {
        return $this->accion('fn_sucursal_restaurar', ['suc_id' => $id], $request, 'Sucursal restaurada con éxito', 'Error al restaurar la sucursal');
    }

    // Sucursales en la papelera -> ecommerce.fn_sucursal_papelera_paginacion.
    public function papelera(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_sucursal_papelera_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

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

    // Auditoría de UNA sucursal. GET ?suc_id=&pagina=&tamanio=&busqueda=
    public function auditoria(Request $request)
    {
        return $this->auditoriaDe($request, 'suc_id', 'fn_sucursal_auditoria', 'Sucursal no válida', 'No se pudo cargar la auditoría de la sucursal');
    }

    // ================= Ciudades =================

    // Las ciudades en su orden con cuántas sucursales tienen (?papelera=1: las de la papelera).
    public function ciudades(Request $request)
    {
        try {
            $registros = DB::select('SELECT * FROM ecommerce.fn_sucursal_ciudad_listar(?)', [filter_var($request->query('papelera', false), FILTER_VALIDATE_BOOLEAN)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Ciudades listadas con éxito', ['registros' => $registros]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las ciudades', $th->getMessage()));
        }
    }

    // Body: { sci_nombre, sci_activo? }
    public function crearCiudad(Request $request)
    {
        $validator = Validator::make($request->all(), ['sci_nombre' => 'required|string|max:80', 'sci_activo' => 'nullable|boolean'], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_sucursal_ciudad_add(?::jsonb) AS sci_id', [json_encode($request->only(['sci_nombre', 'sci_activo']) + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            SucursalesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Ciudad creada con éxito', ['sci_id' => $resultado->sci_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear la ciudad');
        }
    }

    // Body: { sci_nombre?, sci_activo? }
    public function editarCiudad(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), ['sci_nombre' => 'sometimes|required|string|max:80', 'sci_activo' => 'sometimes|boolean'], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_sucursal_ciudad_edit(?::jsonb) AS sci_id', [json_encode($request->only(['sci_nombre', 'sci_activo']) + ['sci_id' => $id, 'auditoria' => $this->contextoAuditoriaForense($request)])]);
            SucursalesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Ciudad guardada con éxito', ['sci_id' => $resultado->sci_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar la ciudad');
        }
    }

    // Crea una ciudad nueva con copia de sus sucursales -> ecommerce.fn_sucursal_ciudad_clonar. Las copias quedan inactivas.
    // Body: { sci_nombre, sci_activo?, sucursales?: [suc_id, ...] } (sucursales = las que se copian, en su orden; si no
    // viene, todas; [] = ninguna).
    public function clonarCiudad(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'sci_nombre' => 'required|string|max:80',
            'sci_activo' => 'sometimes|boolean',
            'sucursales' => 'sometimes|array',
            'sucursales.*' => 'integer|min:1',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $payload = $request->only(['sci_nombre', 'sci_activo']) + ['sci_id_origen' => $id];
            if ($request->has('sucursales')) {
                $payload['sucursales'] = array_values($request->input('sucursales', []));
            }
            $resultado = DB::selectOne('SELECT ecommerce.fn_sucursal_ciudad_clonar(?::jsonb) AS sci_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            SucursalesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Ciudad clonada con éxito', ['sci_id' => $resultado->sci_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al clonar la ciudad');
        }
    }

    public function eliminarCiudad(Request $request, int $id)
    {
        return $this->accion('fn_sucursal_ciudad_papelera', ['sci_id' => $id], $request, 'Ciudad enviada a la papelera', 'Error al eliminar la ciudad');
    }

    public function restaurarCiudad(Request $request, int $id)
    {
        return $this->accion('fn_sucursal_ciudad_restaurar', ['sci_id' => $id], $request, 'Ciudad restaurada con éxito', 'Error al restaurar la ciudad');
    }

    // Nuevo orden de TODAS las ciudades -> ecommerce.fn_sucursal_ciudades_ordenar. Body: { ciudades: [sci_id, ...] }
    public function ordenarCiudades(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ciudades' => 'present|array',
            'ciudades.*' => 'integer|min:1',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            DB::selectOne('SELECT ecommerce.fn_sucursal_ciudades_ordenar(?::jsonb) AS cambios', [json_encode([
                'ciudades' => array_values($request->input('ciudades', [])),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);
            SucursalesTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado', null));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al ordenar las ciudades');
        }
    }

    // Auditoría de UNA ciudad (también de las de la papelera). GET ?sci_id=&pagina=&tamanio=&busqueda=
    public function ciudadAuditoria(Request $request)
    {
        return $this->auditoriaDe($request, 'sci_id', 'fn_sucursal_ciudad_auditoria', 'Ciudad no válida', 'No se pudo cargar la auditoría de la ciudad');
    }

    // ================= Internos =================

    private function reglas(bool $editar): array
    {
        $requerido = $editar ? 'sometimes|required' : 'required';

        return [
            'sci_id' => "$requerido|integer|min:1",
            'suc_nombre' => "$requerido|string|max:100",
            'suc_direccion' => "$requerido|string|max:250",
            'suc_horario' => 'nullable|string|max:150',
            'suc_telefono' => 'nullable|string|max:20',
            'suc_latitud' => 'nullable|numeric',
            'suc_longitud' => 'nullable|numeric',
            'suc_activo' => $editar ? 'sometimes|boolean' : 'nullable|boolean',
        ];
    }

    // Auditoría de un registro: {resumen, eventos, total, pagina, tamanio}. $funcion sin el sufijo _resumen / _listar_paginacion.
    private function auditoriaDe(Request $request, string $campo, string $funcion, string $noValido, string $porDefecto)
    {
        try {
            $id = (int) $request->query($campo, 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', $noValido, null));
            }

            $resumen = DB::selectOne("SELECT * FROM ecommerce.{$funcion}_resumen(?)", [$id]);
            $eventos = DB::select("SELECT * FROM ecommerce.{$funcion}_listar_paginacion(?, ?, ?, ?)", [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', $porDefecto, $th->getMessage()));
        }
    }

    // Papelera / restaurar: una función que recibe { <id>, auditoria } y devuelve el id.
    private function accion(string $funcion, array $datos, Request $request, string $exito, string $porDefecto)
    {
        try {
            DB::selectOne("SELECT ecommerce.$funcion(?::jsonb) AS id", [json_encode($datos + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            SucursalesTiendaController::limpiarCache();

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

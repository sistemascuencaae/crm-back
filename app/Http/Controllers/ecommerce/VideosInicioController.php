<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Controllers\paginaWeb\VideosInicioTiendaController;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Video de inicio de la tienda (ecommerce.videos_inicio): un MP4 con fechas desde / hasta, y uno predeterminado
// (opcional) que sale cuando ninguno está en fecha. Listar, subir video, crear, editar, papelera (borrado lógico),
// restaurar y auditoría. Mismo diseño que la Publicidad de inicio (VentanasEmergentesController).
// Las reglas viven en ecommerce.fn_video_inicio_* (database/sql/videos_inicio.sql); lo que pide la tienda está en
// paginaWeb/VideosInicioTiendaController.
class VideosInicioController extends Controller
{
    private const VIDEO_MAXIMO_KB = 15360;

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'VIDEO_NOMBRE_REQUERIDO' => 'Debe ingresar el nombre del video.',
        'VIDEO_NOMBRE_LARGO' => 'El nombre del video no puede tener más de 100 caracteres.',
        'VIDEO_DUPLICADO' => 'Ya existe un video con ese nombre (si está restaurando, cambie primero el nombre del otro).',
        'ux_videos_inicio_nombre' => 'Ya existe un video con ese nombre.',
        'VIDEO_ARCHIVO_REQUERIDO' => 'Suba el video.',
        'VIDEO_ARCHIVO_INVALIDO' => 'El video no es válido: vuelva a subirlo.',
        'VIDEO_PREDETERMINADO_INVALIDO' => 'El valor de "Predeterminado" no es válido.',
        'VIDEO_PREDETERMINADO_INACTIVO' => 'El video predeterminado no se puede desactivar: quítele la marca o envíelo a la papelera.',
        'VIDEO_PREDETERMINADO_OCUPADO' => 'Ya hay otro video predeterminado: quítele la marca antes de restaurar este.',
        'ux_videos_inicio_predeterminado' => 'Ya hay otro video predeterminado.',
        'VIDEO_ACTIVO_INVALIDO' => 'El valor de "Activo" no es válido.',
        'VIDEO_FECHA_DESDE_REQUERIDA' => 'Debe ingresar la fecha desde.',
        'VIDEO_FECHA_HASTA_REQUERIDA' => 'Debe ingresar la fecha hasta.',
        'VIDEO_FECHA_DESDE_INVALIDA' => 'La fecha desde no es válida.',
        'VIDEO_FECHA_HASTA_INVALIDA' => 'La fecha hasta no es válida.',
        'VIDEO_FECHAS_INVERTIDAS' => 'La fecha hasta no puede ser anterior a la fecha desde.',
        'VIDEO_FECHAS_CRUZADAS' => 'Esas fechas se cruzan con otro video activo: la tienda no sabría cuál mostrar.',
        'VIDEO_NO_EXISTE' => 'El video no existe o está en la papelera.',
        'VIDEO_NO_EN_PAPELERA' => 'El video no está en la papelera (puede que otro usuario ya lo restauró).',
    ];

    private const MENSAJES_FORMULARIO = [
        'vin_nombre.required' => 'Debe ingresar el nombre del video.',
        'vin_nombre.max' => 'El nombre del video no puede tener más de 100 caracteres.',
        'vin_video.required' => 'Suba el video.',
        'vin_video.regex' => 'El video no es válido: vuelva a subirlo.',
        'vin_predeterminado.boolean' => 'El valor de "Predeterminado" no es válido.',
        'vin_fecha_desde.date_format' => 'La fecha desde no es válida.',
        'vin_fecha_hasta.date_format' => 'La fecha hasta no es válida.',
        'vin_activo.boolean' => 'El valor de "Activo" no es válido.',
    ];

    // Lo único que se acepta del formulario.
    private const CAMPOS_PAYLOAD = [
        'vin_nombre',
        'vin_video',
        'vin_predeterminado',
        'vin_fecha_desde',
        'vin_fecha_hasta',
        'vin_activo',
    ];

    public function __construct()
    {
        // Lo público (el video de hoy y los archivos) está en paginaWeb/VideosInicioTiendaController.
        $this->middleware('auth:api');
    }

    // Videos (fuera de la papelera), paginados, con su estado de hoy y la dirección de su archivo
    // -> ecommerce.fn_video_inicio_listar_paginacion. GET ?pagina=&tamanio=&busqueda=
    public function listar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_video_inicio_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Videos de inicio listados con éxito', [
                'registros' => $this->conUrl($registros),
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los videos de inicio', $th->getMessage()));
        }
    }

    // Sube el video (MP4, hasta 15 MB) a ecommerce/videos (NAS o local según crm.parametro 'NAS').
    // Devuelve {nombre, url}: se guarda con crear / editar (vin_video = nombre).
    public function subirVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video' => 'required|file|mimetypes:video/mp4|max:' . self::VIDEO_MAXIMO_KB,
        ], [
            'video.required' => 'Elija un video.',
            'video.file' => 'Elija un video.',
            'video.uploaded' => 'El video no se pudo subir: puede pesar hasta 15 MB.',
            'video.mimetypes' => 'Solo se aceptan videos MP4.',
            'video.max' => 'El video no puede pesar más de 15 MB.',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        $nombre = Str::random(40) . '.mp4';
        if (!ArchivosPaginaWeb::guardar($request->file('video'), VideosInicioTiendaController::CARPETA, $nombre)) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir el video.', null));
        }

        return response()->json(RespuestaApi::returnResultado('success', 'Video subido', [
            'nombre' => $nombre,
            'url' => VideosInicioTiendaController::urlVideo($nombre),
        ]));
    }

    // Crea un video -> ecommerce.fn_video_inicio_add.
    // Body: { vin_nombre, vin_video, vin_predeterminado?, vin_fecha_desde?, vin_fecha_hasta?, vin_activo? }
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), $this->reglas(false), self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_video_inicio_add(?::jsonb) AS vin_id', [json_encode($request->only(self::CAMPOS_PAYLOAD) + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            VideosInicioTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Video de inicio creado con éxito', ['vin_id' => $resultado->vin_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear el video de inicio');
        }
    }

    // Modifica un video -> ecommerce.fn_video_inicio_edit. Lo que no viene se queda como está. Si cambió el
    // video, el anterior se borra.
    public function editar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), $this->reglas(true), self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $antes = json_decode(DB::selectOne('SELECT ecommerce.fn_video_inicio_buscar(?) AS v', [$id])->v ?? 'null', true);
            $payload = $request->only(self::CAMPOS_PAYLOAD) + ['vin_id' => $id];
            $resultado = DB::selectOne('SELECT ecommerce.fn_video_inicio_edit(?::jsonb) AS vin_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            VideosInicioTiendaController::limpiarCache();

            $videoAnterior = $antes['vin_video'] ?? null;
            if ($videoAnterior && $request->filled('vin_video') && $request->input('vin_video') !== $videoAnterior) {
                ArchivosPaginaWeb::borrarRuta(VideosInicioTiendaController::CARPETA . '/' . basename($videoAnterior));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Video de inicio guardado con éxito', ['vin_id' => $resultado->vin_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar el video de inicio');
        }
    }

    public function eliminar(Request $request, int $id)
    {
        return $this->accion('fn_video_inicio_papelera', ['vin_id' => $id], $request, 'Video de inicio enviado a la papelera', 'Error al eliminar el video de inicio');
    }

    public function restaurar(Request $request, int $id)
    {
        return $this->accion('fn_video_inicio_restaurar', ['vin_id' => $id], $request, 'Video de inicio restaurado con éxito', 'Error al restaurar el video de inicio');
    }

    // Videos en la papelera -> ecommerce.fn_video_inicio_papelera_paginacion.
    public function papelera(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_video_inicio_papelera_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera cargada con éxito', [
                'registros' => $this->conUrl($registros),
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la papelera', $th->getMessage()));
        }
    }

    // Auditoría de UN video (también de los de la papelera): {resumen, eventos, total, pagina, tamanio}.
    // GET ?vin_id=&pagina=&tamanio=&busqueda=
    public function auditoria(Request $request)
    {
        try {
            $id = (int) $request->query('vin_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Video de inicio no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ecommerce.fn_video_inicio_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ecommerce.fn_video_inicio_auditoria_listar_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del video de inicio', $th->getMessage()));
        }
    }

    // ================= Internos =================

    // Solo forma y tipos: las reglas (fechas, cruces, predeterminado, duplicados) las decide
    // fn_video_inicio_validaciones.
    private function reglas(bool $editar): array
    {
        $requerido = $editar ? 'sometimes|required' : 'required';

        return [
            'vin_nombre' => "$requerido|string|max:100",
            // En lista (no en texto con |): el regex lleva | y Laravel cortaría la regla ahí.
            'vin_video' => array_merge(explode('|', $requerido), ['string', 'regex:/^[A-Za-z0-9]{40}\.mp4$/']),
            'vin_predeterminado' => $editar ? 'sometimes|boolean' : 'nullable|boolean',
            'vin_fecha_desde' => 'nullable|date_format:Y-m-d',
            'vin_fecha_hasta' => 'nullable|date_format:Y-m-d',
            'vin_activo' => $editar ? 'sometimes|boolean' : 'nullable|boolean',
        ];
    }

    // Cada fila con la dirección pública de su video (video_url).
    private function conUrl(array $registros): array
    {
        foreach ($registros as $r) {
            $r->video_url = VideosInicioTiendaController::urlVideo($r->vin_video ?? null);
        }

        return $registros;
    }

    // Papelera / restaurar: una función que recibe { vin_id, auditoria } y devuelve el id.
    private function accion(string $funcion, array $datos, Request $request, string $exito, string $porDefecto)
    {
        try {
            DB::selectOne("SELECT ecommerce.$funcion(?::jsonb) AS id", [json_encode($datos + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            VideosInicioTiendaController::limpiarCache();

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

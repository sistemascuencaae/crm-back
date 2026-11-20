<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Controllers\paginaWeb\ConfiguracionTiendaController;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Configuración de la tienda (ecommerce.configuraciones_tienda, una sola fila): número de WhatsApp, ventana emergente y
// video de intro del inicio. Ver, subir la imagen y el video, guardar y auditoría.
// Las reglas viven en ecommerce.fn_configuracion_tienda_* (database/sql/configuracion_tienda*.sql); lo que pide la
// tienda (y los archivos) está en paginaWeb/ConfiguracionTiendaController.
class ConfiguracionController extends Controller
{
    private const IMAGEN_MAXIMO_KB = 2048;
    private const VIDEO_MAXIMO_KB = 15360;

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'CONFIG_WHATSAPP_INVALIDO' => 'El número de WhatsApp no es válido: escríbalo con el código del país, por ejemplo 593991234567.',
        'CONFIG_WHATSAPP_ECUADOR' => 'Un número de Ecuador debe ser un celular: 593 seguido de 9 dígitos que empiezan con 9 (por ejemplo 593991234567).',
        'CONFIG_VENTANA_IMAGEN_INVALIDA' => 'La imagen de la ventana emergente no es válida. Súbala de nuevo.',
        'CONFIG_VENTANA_SIN_IMAGEN' => 'Para mostrar la ventana emergente, primero suba su imagen.',
        'CONFIG_INTRO_VIDEO_INVALIDO' => 'El video de intro no es válido. Súbalo de nuevo.',
        'CONFIG_INTRO_SIN_VIDEO' => 'Para mostrar el video de intro, primero suba el video.',
        'CONFIG_ACTIVO_INVALIDO' => 'El valor de "Mostrar" no es válido.',
    ];

    private const MENSAJES_FORMULARIO = [
        'cfg_whatsapp_numero.max' => 'El número de WhatsApp es demasiado largo.',
        'cfg_ventana_activa.boolean' => 'El valor de "Mostrar" no es válido.',
        'cfg_intro_activa.boolean' => 'El valor de "Mostrar" no es válido.',
    ];

    // Lo único que se acepta del formulario.
    private const CAMPOS_PAYLOAD = [
        'cfg_whatsapp_numero', 
        'cfg_ventana_imagen', 
        'cfg_ventana_activa', 
        'cfg_intro_video', 
        'cfg_intro_activa'
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // La configuración, con la dirección de la imagen y del video, y quién la cambió por última vez.
    public function ver()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Configuración cargada con éxito', $this->configuracion()));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la configuración', $th->getMessage()));
        }
    }

    // Guarda -> ecommerce.fn_configuracion_tienda_edit. Body: los campos que cambian (CAMPOS_PAYLOAD); null o '' borra.
    // Si la imagen o el video se reemplazaron o se quitaron, el archivo anterior se borra del servidor.
    public function editar(Request $request)
    {
        // Si el número llega como número (sin comillas), se trata como texto: la base lo limpia y lo valida.
        if (is_int($request->input('cfg_whatsapp_numero'))) {
            $request->merge(['cfg_whatsapp_numero' => (string) $request->input('cfg_whatsapp_numero')]);
        }
        $validator = Validator::make($request->all(), [
            'cfg_whatsapp_numero' => 'nullable|string|max:30',
            'cfg_ventana_imagen' => 'nullable|string|max:60',
            'cfg_ventana_activa' => 'sometimes|boolean',
            'cfg_intro_video' => 'nullable|string|max:60',
            'cfg_intro_activa' => 'sometimes|boolean',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $antes = DB::selectOne('SELECT cfg_ventana_imagen, cfg_intro_video FROM ecommerce.fn_configuracion_tienda_ver()');
            DB::selectOne('SELECT ecommerce.fn_configuracion_tienda_edit(?::jsonb) AS id', [json_encode($request->only(self::CAMPOS_PAYLOAD) + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            ConfiguracionTiendaController::limpiarCache();
            $configuracion = $this->configuracion();

            // Archivos que ya no usa nadie
            foreach (['cfg_ventana_imagen', 'cfg_intro_video'] as $campo) {
                if ($antes && $antes->$campo && $antes->$campo !== $configuracion->$campo) {
                    ConfiguracionTiendaController::borrarArchivo($antes->$campo);
                }
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Configuración guardada con éxito', $configuracion));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar la configuración');
        }
    }

    // Sube la imagen de la ventana emergente (WebP, JPG o PNG, hasta 2 MB). Devuelve {nombre, url}: se guarda con
    // "Guardar" (cfg_ventana_imagen = nombre).
    public function subirImagenVentana(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imagen' => 'required|file|mimetypes:image/webp,image/jpeg,image/png|max:' . self::IMAGEN_MAXIMO_KB,
        ], [
            'imagen.required' => 'Elija una imagen.',
            'imagen.file' => 'Elija una imagen.',
            'imagen.uploaded' => 'La imagen no se pudo subir: puede pesar hasta 2 MB.',
            'imagen.mimetypes' => 'Solo se aceptan imágenes WebP, JPG o PNG.',
            'imagen.max' => 'La imagen no puede pesar más de 2 MB.',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        $extension = array_search($request->file('imagen')->getMimeType(), ConfiguracionTiendaController::TIPOS, true) ?: 'webp';

        return $this->guardarArchivo($request, 'imagen', $extension, 'Imagen subida', 'No se pudo subir la imagen.');
    }

    // Sube el video de intro (MP4, hasta 15 MB). Devuelve {nombre, url}: se guarda con "Guardar" (cfg_intro_video = nombre).
    public function subirVideoIntro(Request $request)
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

        return $this->guardarArchivo($request, 'video', 'mp4', 'Video subido', 'No se pudo subir el video.');
    }

    // Auditoría: {resumen, eventos, total, pagina, tamanio}. GET ?pagina=&tamanio=&busqueda=
    public function auditoria(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $resumen = DB::selectOne('SELECT * FROM ecommerce.fn_configuracion_tienda_auditoria_resumen()');
            $eventos = DB::select('SELECT * FROM ecommerce.fn_configuracion_tienda_auditoria_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la configuración', $th->getMessage()));
        }
    }

    // ================= Internos =================

    // La fila de fn_configuracion_tienda_ver con la dirección pública de la imagen y del video.
    private function configuracion(): object
    {
        $c = DB::selectOne('SELECT * FROM ecommerce.fn_configuracion_tienda_ver()') ?? (object) [];
        $c->ventana_imagen_url = ConfiguracionTiendaController::urlArchivo($c->cfg_ventana_imagen ?? null);
        $c->intro_video_url = ConfiguracionTiendaController::urlArchivo($c->cfg_intro_video ?? null);

        return $c;
    }

    private function guardarArchivo(Request $request, string $campo, string $extension, string $exito, string $error)
    {
        try {
            $nombre = Str::random(40) . '.' . $extension;
            $request->file($campo)->storeAs(ConfiguracionTiendaController::CARPETA, $nombre, 'local');

            return response()->json(RespuestaApi::returnResultado('success', $exito, [
                'nombre' => $nombre,
                'url' => ConfiguracionTiendaController::urlArchivo($nombre),
            ]));
        } catch (\Throwable $th) {
            Log::error($error, ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', $error, null));
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

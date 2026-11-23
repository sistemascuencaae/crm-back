<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Controllers\paginaWeb\ConfiguracionTiendaController;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Configuración de la tienda (ecommerce.configuraciones_tienda, una sola fila): el número de WhatsApp. Ver, guardar y
// auditoría. La publicidad y el video de inicio tienen su propia pantalla (VentanasEmergentesController y
// VideosInicioController).
// Las reglas viven en ecommerce.fn_configuracion_tienda_* (database/sql/configuracion_tienda*.sql); lo que pide la
// tienda está en paginaWeb/ConfiguracionTiendaController.
class ConfiguracionController extends Controller
{
    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'CONFIG_WHATSAPP_INVALIDO' => 'El número de WhatsApp no es válido: escríbalo con el código del país, por ejemplo 593991234567.',
        'CONFIG_WHATSAPP_ECUADOR' => 'Un número de Ecuador debe ser un celular: 593 seguido de 9 dígitos que empiezan con 9 (por ejemplo 593991234567).',
    ];

    private const MENSAJES_FORMULARIO = [
        'cfg_whatsapp_numero.max' => 'El número de WhatsApp es demasiado largo.',
    ];

    // Lo único que se acepta del formulario.
    private const CAMPOS_PAYLOAD = [
        'cfg_whatsapp_numero',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // La configuración y quién la cambió por última vez.
    public function ver()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Configuración cargada con éxito', $this->configuracion()));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la configuración', $th->getMessage()));
        }
    }

    // Guarda -> ecommerce.fn_configuracion_tienda_edit. Body: { cfg_whatsapp_numero } (null o '' lo borra).
    public function editar(Request $request)
    {
        // Si el número llega como número (sin comillas), se trata como texto: la base lo limpia y lo valida.
        if (is_int($request->input('cfg_whatsapp_numero'))) {
            $request->merge(['cfg_whatsapp_numero' => (string) $request->input('cfg_whatsapp_numero')]);
        }
        $validator = Validator::make($request->all(), [
            'cfg_whatsapp_numero' => 'nullable|string|max:30',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            DB::selectOne('SELECT ecommerce.fn_configuracion_tienda_edit(?::jsonb) AS id', [json_encode($request->only(self::CAMPOS_PAYLOAD) + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            ConfiguracionTiendaController::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Configuración guardada con éxito', $this->configuracion()));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar la configuración');
        }
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

    // La fila de fn_configuracion_tienda_ver: { cfg_whatsapp_numero, cfg_fecha_modificacion, modificado_por }.
    private function configuracion(): object
    {
        return DB::selectOne('SELECT * FROM ecommerce.fn_configuracion_tienda_ver()') ?? (object) [];
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

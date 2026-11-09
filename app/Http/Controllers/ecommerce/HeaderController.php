<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Headers de la tienda: cada header es una fila de ecommerce.headers con sus 9 partes (header_bloques), los textos de
// cada parte (header_textos) y los links (header_enlaces). Las reglas viven en las funciones ecommerce.fn_header_*.
class HeaderController extends Controller
{
    public function __construct()
    {
        // verImagen es pública: la tienda pide el logo sin sesión.
        $this->middleware('auth:api')->except('verImagen');
    }

    // Imágenes de los headers (el logo): carpeta privada storage/app/headers; la tienda y el CRM las piden por
    // api/public/headers/imagenes/{nombre}. Nombre aleatorio de 40 letras y números + .webp (nunca cambia de contenido).
    private const CARPETA_IMAGENES = 'headers';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.webp$/';
    // La dirección que se guarda en hbl_imagen: solo una imagen subida aquí.
    private const RUTA_IMAGEN = '#/api/public/headers/imagenes/[A-Za-z0-9]{40}\.webp$#';

    // Mensaje amigable para cada excepción que puede lanzar ecommerce.fn_header_add (y sus validaciones).
    // Los dos últimos son los índices únicos: cubren dos altas al mismo tiempo que pasaron las validaciones.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'REQUIERE_NOMBRE' => 'Debe ingresar el nombre del header.',
        'NOMBRE_LARGO' => 'El nombre no puede tener más de 60 caracteres.',
        'HEADER_DUPLICADO' => 'Ya existe un header con ese nombre.',
        'DESCRIPCION_LARGA' => 'La descripción no puede tener más de 250 caracteres.',
        'FUENTE_INVALIDA' => 'La tipografía seleccionada no es válida.',
        'VALOR_INVALIDO' => 'Uno de los campos Sí/No tiene un valor no válido.',
        'PREDETERMINADO_INACTIVO' => 'El header predeterminado no puede estar inactivo.',
        'FECHA_INVALIDA' => 'Las fechas de vigencia no son válidas.',
        'REQUIERE_VIGENCIA' => 'Un header activo que no es el predeterminado necesita fecha de inicio y de fin (y si lleva una, lleva las dos).',
        'VIGENCIA_INVALIDA' => 'La fecha de fin debe ser posterior a la de inicio.',
        'VIGENCIA_CRUZADA' => 'Ya hay otro header activo en esas fechas.',
        'COLOR_INVALIDO' => 'Uno de los colores no es válido. Use el formato #RRGGBB.',
        'HEADER_NO_EXISTE' => 'El header no existe o está en la papelera.',
        'REQUIERE_PREDETERMINADO' => 'La tienda necesita un header predeterminado: no se le puede quitar la marca.',
        // Partes del header (ecommerce.fn_header_edit_<parte> y fn_header_edit_orden)
        'PARTE_NO_EXISTE' => 'La parte del header no existe.',
        'REDONDEO_INVALIDO' => 'El redondeo debe estar entre 0 y 50 px.',
        'ANCHO_INVALIDO' => 'El ancho del logo debe estar entre 40 y 400 px.',
        'SEGUNDOS_INVALIDOS' => 'Las frases del buscador deben cambiar cada 2 a 30 segundos.',
        'ENLACE_INVALIDO' => 'El destino debe ser una ruta de la tienda (empieza con /) o una dirección http(s), de hasta 250 caracteres.',
        'ICONO_INVALIDO' => 'El ícono no es válido.',
        'TEXTOS_INVALIDOS' => 'Los textos enviados no son válidos.',
        'TEXTO_CLAVE_INVALIDA' => 'Uno de los textos no corresponde a esta parte del header.',
        'TEXTOS_CANTIDAD' => 'Se enviaron más textos de los que admite esta parte.',
        'TEXTO_NO_EXISTE' => 'Uno de los textos no existe o ya no pertenece a esta parte.',
        'REQUIERE_TEXTO' => 'Los textos no pueden quedar vacíos.',
        'TEXTO_LARGO' => 'Uno de los textos es demasiado largo.',
        'TAMANO_INVALIDO' => 'El tamaño de letra debe estar entre 8 y 48 px.',
        'ENLACES_INVALIDOS' => 'Los links enviados no son válidos.',
        'ENLACES_CANTIDAD' => 'La barra admite hasta 8 links.',
        'SUBLINKS_CANTIDAD' => 'Cada link admite hasta 10 sublinks.',
        'SUBLINKS_NIVEL' => 'Los sublinks no pueden tener sublinks.',
        'ENLACE_NO_EXISTE' => 'Uno de los links no existe o ya no pertenece a esta barra.',
        'ENLACE_REPETIDO' => 'Un link se envió dos veces.',
        'REQUIERE_ENLACE' => 'Cada sublink, y cada link sin sublinks, necesita un destino.',
        'FRANJA_INVALIDA' => 'La franja no es válida.',
        'ORDEN_INVALIDO' => 'El orden debe traer exactamente las partes de esa franja, sin repetir.',
        'ux_headers_nombre' => 'Ya existe un header con ese nombre.',
        'ux_headers_predeterminado' => 'Otro usuario cambió el header predeterminado al mismo tiempo. Intente de nuevo.',
    ];

    // Campos que se envían tal cual a ecommerce.fn_header_add como jsonb. Las partes, textos y links se crean solos con
    // el diseño actual de la tienda y se editan después.
    private const CAMPOS_PAYLOAD = [
        'hea_nombre',
        'hea_descripcion',
        'hea_predeterminado',
        'hea_vigente_desde',
        'hea_vigente_hasta',
        'hea_activo',
        'hea_fuente',
        'hea_fijo',
        'hea_superior_visible',
        'hea_superior_fondo',
        'hea_superior_borde',
        'hea_media_fondo',
        'hea_inferior_visible',
        'hea_inferior_fondo',
        'hea_inferior_contorno',
    ];

    // Cada parte del header y su función. Es la lista blanca: la parte de la URL nunca se pega al SQL si no está aquí.
    private const FUNCIONES_PARTE = [
        'texto_promo' => 'ecommerce.fn_header_edit_texto_promo',
        'boton_promo' => 'ecommerce.fn_header_edit_boton_promo',
        'logo' => 'ecommerce.fn_header_edit_logo',
        'buscador' => 'ecommerce.fn_header_edit_buscador',
        'cuenta' => 'ecommerce.fn_header_edit_cuenta',
        'carrito' => 'ecommerce.fn_header_edit_carrito',
        'categorias' => 'ecommerce.fn_header_edit_categorias',
        'enlaces' => 'ecommerce.fn_header_edit_enlaces',
        'credito' => 'ecommerce.fn_header_edit_credito',
    ];

    // Campos de una parte que se envían a su función. Cada fn_header_edit_<parte> usa solo los que le corresponden.
    private const CAMPOS_PARTE = [
        'hbl_visible',
        'hbl_imagen',
        'hbl_imagen_alt',
        'hbl_ancho',
        'hbl_enlace',
        'hbl_nueva_pestana',
        'hbl_color_fondo',
        'hbl_color_contorno',
        'hbl_color_efecto',
        'hbl_color_texto_efecto',
        'hbl_color_foco',
        'hbl_redondeo',
        'hbl_icono',
        'hbl_color_icono',
        'hbl_color_fondo_icono',
        'hbl_panel_fondo',
        'hbl_panel_contorno',
        'hbl_panel_efecto',
        'hbl_contador_fondo',
        'hbl_contador_texto',
        'hbl_segundos',
        'textos',
        'enlaces',
    ];

    // Orquestador: arma el jsonb y llama a ecommerce.fn_header_add, que valida, crea el header con sus 9 partes y sus
    // textos, y registra la auditoría en una sola transacción.
    public function crear(Request $request)
    {
        $color = ['nullable', 'string', 'regex:/^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/'];

        $validator = Validator::make($request->all(), [
            'hea_nombre' => 'required|string|max:60',
            'hea_descripcion' => 'nullable|string|max:250',
            'hea_predeterminado' => 'nullable|boolean',
            'hea_vigente_desde' => 'nullable|date',
            'hea_vigente_hasta' => 'nullable|date|after:hea_vigente_desde',
            'hea_activo' => 'nullable|boolean',
            'hea_fuente' => 'nullable|string|max:30',
            'hea_fijo' => 'nullable|boolean',
            'hea_superior_visible' => 'nullable|boolean',
            'hea_superior_fondo' => $color,
            'hea_superior_borde' => $color,
            'hea_media_fondo' => $color,
            'hea_inferior_visible' => 'nullable|boolean',
            'hea_inferior_fondo' => $color,
            'hea_inferior_contorno' => $color,
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_PAYLOAD);

        try {
            // El bloque 'auditoria' (usuario/IP/user agent/request_id) va dentro del jsonb; la función PG lo usa para la
            // auditoría forense y para el usu_id de las filas, y NO lo guarda como dato del header.
            $resultado = DB::selectOne('SELECT ecommerce.fn_header_add(?::jsonb) AS hea_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Header creado con éxito', ['hea_id' => $resultado->hea_id]));
        } catch (QueryException $e) {
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear el header', $e->getMessage()));
        }
    }

    // Modifica los datos generales de un header -> ecommerce.fn_header_edit. Solo se envían los campos que cambian: los que
    // no vienen se quedan como están (la función mezcla lo actual con lo enviado antes de validar). Si nada cambió, no
    // escribe ni audita.
    public function editar(Request $request, int $id)
    {
        $color = ['nullable', 'string', 'regex:/^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/'];

        $validator = Validator::make($request->all(), [
            'hea_nombre' => 'sometimes|required|string|max:60',
            'hea_descripcion' => 'nullable|string|max:250',
            'hea_predeterminado' => 'sometimes|boolean',
            'hea_vigente_desde' => 'nullable|date',
            'hea_vigente_hasta' => 'nullable|date',
            'hea_activo' => 'sometimes|boolean',
            'hea_fuente' => 'sometimes|required|string|max:30',
            'hea_fijo' => 'sometimes|boolean',
            'hea_superior_visible' => 'sometimes|boolean',
            'hea_superior_fondo' => $color,
            'hea_superior_borde' => $color,
            'hea_media_fondo' => $color,
            'hea_inferior_visible' => 'sometimes|boolean',
            'hea_inferior_fondo' => $color,
            'hea_inferior_contorno' => $color,
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        // El id sale de la URL, nunca del cuerpo.
        $payload = $request->only(self::CAMPOS_PAYLOAD) + ['hea_id' => $id];

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_header_edit(?::jsonb) AS hea_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Header modificado con éxito', ['hea_id' => $resultado->hea_id]));
        } catch (QueryException $e) {
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al modificar el header', $e->getMessage()));
        }
    }

    // Edita UNA parte del header -> ecommerce.fn_header_edit_<parte>. Se envían solo los campos que cambian; textos y
    // enlaces van como listas (ver header_partes_edit.sql). Las reglas de cada parte viven en su función.
    public function editarParte(Request $request, int $id, string $parte)
    {
        if (!array_key_exists($parte, self::FUNCIONES_PARTE)) {
            return response()->json(RespuestaApi::returnResultado('error', 'La parte del header no existe.', null));
        }

        // El logo solo puede ser una imagen subida con subirImagen (WebP de nuestro servidor), o ninguna.
        $imagen = $request->input('hbl_imagen');
        if ($imagen !== null && $imagen !== '' && !preg_match(self::RUTA_IMAGEN, (string) parse_url((string) $imagen, PHP_URL_PATH))) {
            return response()->json(RespuestaApi::returnResultado('error', 'El logo debe ser una imagen WebP subida desde el CRM.', null));
        }

        $validator = Validator::make($request->all(), [
            'hbl_visible' => 'sometimes|boolean',
            'hbl_nueva_pestana' => 'sometimes|boolean',
            'hbl_ancho' => 'nullable|integer',
            'hbl_redondeo' => 'nullable|integer',
            'hbl_segundos' => 'nullable|integer',
            'textos' => 'sometimes|array',
            'textos.*.htx_clave' => 'required|string|max:15',
            'enlaces' => 'sometimes|array|max:8',
            'enlaces.*.hen_texto' => 'sometimes|string|max:60',
            'enlaces.*.sublinks' => 'sometimes|array|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        // El header sale de la URL, nunca del cuerpo.
        $payload = $request->only(self::CAMPOS_PARTE) + ['hea_id' => $id];
        $funcion = self::FUNCIONES_PARTE[$parte];

        try {
            $resultado = DB::selectOne("SELECT {$funcion}(?::jsonb) AS hea_id", [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Parte del header modificada con éxito', ['hea_id' => $resultado->hea_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al modificar la parte del header');
        }
    }

    // Orden de las partes dentro de una franja -> ecommerce.fn_header_edit_orden. orden = lista de partes (hbl_tipo).
    public function ordenarPartes(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'hbl_franja' => 'required|string|in:superior,media,inferior',
            'orden' => 'required|array|min:1',
            'orden.*' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only(['hbl_franja', 'orden']) + ['hea_id' => $id];

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_header_edit_orden(?::jsonb) AS hea_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', ['hea_id' => $resultado->hea_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar el orden');
        }
    }

    // Mensaje de MENSAJES_ERROR para el código que lanzó la función PG; si no hay, el genérico con el detalle.
    // Sube una imagen del header (el logo): solo WebP de verdad (mimetypes mira el contenido, no la extensión: un PNG
    // renombrado a .webp no pasa) y hasta 2 MB. Devuelve la dirección para ponerla en hbl_imagen; se publica al guardar.
    public function subirImagen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imagen' => 'required|file|mimetypes:image/webp|max:2048',
        ], [
            'imagen.required' => 'Elija una imagen.',
            'imagen.file' => 'Elija una imagen.',
            'imagen.mimetypes' => 'Solo se aceptan imágenes WebP (.webp).',
            'imagen.max' => 'La imagen no puede pesar más de 2 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        try {
            $nombre = Str::random(40) . '.webp';
            $request->file('imagen')->storeAs(self::CARPETA_IMAGENES, $nombre, 'local');

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen subida', [
                'url' => url('api/public/headers/imagenes/' . $nombre),
            ]));
        } catch (\Throwable $th) {
            Log::error('No se pudo subir la imagen del header', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir la imagen.', null));
        }
    }

    // Pública: entrega una imagen de la carpeta de headers y nada más (el nombre se revisa antes de abrir el archivo).
    public function verImagen(string $nombre)
    {
        $ruta = self::CARPETA_IMAGENES . '/' . $nombre;

        if (!preg_match(self::NOMBRE_IMAGEN, $nombre) || !Storage::disk('local')->exists($ruta)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($ruta), [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function errorTraducido(QueryException $e, string $mensajeGenerico)
    {
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($e->getMessage(), $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $mensajeGenerico, $e->getMessage()));
    }

    // Lista de headers (grilla de la izquierda) -> ecommerce.fn_header_listar_paginacion. Con estado y en_tienda (el que
    // la tienda muestra ahora).
    public function listar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_header_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);
            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Headers listados con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los headers', $th->getMessage()));
        }
    }

    // Un header completo con sus 9 partes, textos y links (grilla de la derecha) -> ecommerce.fn_header_buscar.
    public function buscar(int $id)
    {
        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_header_buscar(?) AS header', [$id]);

            if (!$resultado || $resultado->header === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontró el header.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Header encontrado', json_decode($resultado->header)));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el header', $th->getMessage()));
        }
    }

    // Bloque de contexto que viaja DENTRO del jsonb hacia las funciones PG para la auditoría FORENSE
    // (auditoria.fn_registrar_evento — UNA sola fila por operación). Si la función se ejecuta directo en la BD sin este
    // bloque, falla con REQUIERE_USUARIO: las filas del header necesitan usu_id.
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

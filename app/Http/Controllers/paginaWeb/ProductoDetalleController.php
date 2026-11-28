<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ecommerce\SeccionesController;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Diseño del detalle del producto (columna derecha de /product/:slug): cada diseño es una fila de
// ecommerce.detalles_productos con sus 6 bloques (detalle_producto_bloques: orden, visible y estilo) y sus cintas
// (detalle_producto_cintas). Uno es el predeterminado y los demás salen solo dentro de su vigencia. Las reglas viven en
// las funciones ecommerce.fn_detalle_producto_* (database/sql/detalle_producto.sql). Mismo patrón que FooterController.
// El mantenimiento (CRM) pide login; tienda() es pública.
class ProductoDetalleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['tienda']);
    }

    // Lo que lee la tienda (tienda()): se guarda 60 s; cada cambio guardado en el CRM lo borra. Los 60 s cubren el
    // cambio de diseño por fechas sin que nadie guarde nada.
    private const CACHE_TIENDA = 'ecommerce.detalle_producto_tienda';
    private const SEGUNDOS_CACHE_TIENDA = 60;

    // Mensaje amigable para cada excepción de las funciones ecommerce.fn_detalle_producto_*. Los dos últimos son los
    // índices únicos. Se busca la palabra completa (CINTA_INVALIDA no confunde a CINTAS_CANTIDAD).
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'REQUIERE_NOMBRE' => 'Debe ingresar el nombre del diseño.',
        'NOMBRE_LARGO' => 'El nombre no puede tener más de 60 caracteres.',
        'DETALLE_DUPLICADO' => 'Ya existe un diseño con ese nombre.',
        'DESCRIPCION_LARGA' => 'La descripción no puede tener más de 250 caracteres.',
        'VALOR_INVALIDO' => 'Uno de los campos Sí/No tiene un valor no válido.',
        'PREDETERMINADO_INACTIVO' => 'El diseño predeterminado no puede estar inactivo.',
        'FECHA_INVALIDA' => 'Las fechas no son válidas.',
        'REQUIERE_VIGENCIA' => 'Un diseño activo que no es el predeterminado necesita fecha de inicio y de fin (y si lleva una, lleva las dos).',
        'VIGENCIA_INVALIDA' => 'La fecha de fin debe ser posterior a la de inicio.',
        'VIGENCIA_CRUZADA' => 'Ya hay otro diseño activo en esas fechas.',
        'DETALLE_NO_EXISTE' => 'El diseño no existe o está en la papelera.',
        'COPIA_NO_EXISTE' => 'El diseño del que se quiere copiar no existe o está en la papelera.',
        'PREDETERMINADO_NO_ELIMINABLE' => 'El predeterminado no se puede eliminar: la tienda siempre necesita uno. Marque otro como predeterminado y luego elimine este.',
        'REQUIERE_PREDETERMINADO' => 'La tienda necesita un diseño predeterminado: no se le puede quitar la marca.',
        // Bloques
        'BLOQUE_NO_EXISTE' => 'El bloque no existe en este diseño. Recargue e intente de nuevo.',
        'BLOQUE_OBLIGATORIO' => 'Los datos del producto siempre se muestran: solo las cintas se pueden ocultar (cada una en la pestaña Cintas).',
        'COLOR_INVALIDO' => 'Uno de los colores no es válido. Use el formato #RRGGBB.',
        'TAMANO_TITULO_INVALIDO' => 'El tamaño del título debe estar entre 18 y 48 px.',
        'TAMANO_PRECIO_INVALIDO' => 'El tamaño del precio debe estar entre 24 y 60 px.',
        'REDONDEO_INVALIDO' => 'El redondeo de las esquinas de los botones debe estar entre 0 y 24 px.',
        'DISPOSICION_INVALIDA' => 'Los botones van uno al lado del otro o uno debajo del otro.',
        'ORDEN_INVALIDO' => 'El orden enviado no coincide con lo que hay en el diseño. Recargue e intente de nuevo.',
        // Cintas
        'CINTA_NO_EXISTE' => 'La cinta no existe o ya fue quitada.',
        'CINTAS_CANTIDAD' => 'Cada diseño admite hasta 10 cintas.',
        'CINTA_INVALIDA' => 'La cinta tiene un valor no válido (colores, tamaños o velocidad).',
        'CINTA_FUENTE_INVALIDA' => 'La fuente de la cinta no es una de las permitidas.',
        'CINTA_FILAS_INVALIDAS' => 'La cinta tiene que tener 1 o 2 filas.',
        'CINTA_MOVIMIENTO_UNA_FILA' => 'Una cinta en movimiento solo puede tener una fila.',
        'CINTA_VACIA' => 'Agregue al menos una frase o un ícono a la cinta.',
        'CINTA_PIEZAS_MAXIMO' => 'Cada fila de la cinta puede tener hasta 10 frases o grupos de íconos.',
        'CINTA_FRASE_VACIA' => 'Hay una frase vacía en la cinta: escríbala o quítela.',
        'CINTA_FRASE_LARGA' => 'Cada frase de la cinta puede tener hasta 120 caracteres.',
        'CINTA_FORMATO_INVALIDO' => 'El formato de una frase de la cinta no es válido. Vuelva a seleccionar los pedazos.',
        'CINTA_ICONOS_INVALIDOS' => 'Un grupo de íconos de la cinta no es válido (de 1 a 8: imágenes subidas o íconos de la lista; alto entre 12 y 60 px).',
        'ux_detalles_productos_nombre' => 'Ya existe un diseño con ese nombre.',
        'ux_detalles_productos_predeterminado' => 'Otro usuario cambió el diseño predeterminado al mismo tiempo. Intente de nuevo.',
    ];

    // Datos generales que se envían tal cual a fn_detalle_producto_add / fn_detalle_producto_edit.
    private const CAMPOS_PAYLOAD = [
        'dpr_nombre',
        'dpr_descripcion',
        'dpr_predeterminado',
        'dpr_vigente_desde',
        'dpr_vigente_hasta',
        'dpr_activo',
    ];

    private const CAMPOS_BLOQUE = [
        'dpb_visible',
        'dpb_color',
        'dpb_color_secundario',
        'dpb_tamano',
        'dpb_mayusculas',
        'dpb_tachado_visible',
        'dpb_redondeo',
        'dpb_disposicion',
        // Botones de compra: sus 10 colores (fondo, texto y borde de cada botón, y al pasar el ratón)
        'dpb_principal_texto',
        'dpb_principal_borde',
        'dpb_principal_hover_fondo',
        'dpb_principal_hover_texto',
        'dpb_principal_hover_borde',
        'dpb_secundario_fondo',
        'dpb_secundario_borde',
        'dpb_secundario_hover_fondo',
        'dpb_secundario_hover_texto',
        'dpb_secundario_hover_borde',
    ];

    private const CAMPOS_CINTA = [
        'dpc_id',
        'dpc_visible',
        'dpc_movimiento',
        'dpc_velocidad',
        'dpc_fondo',
        'dpc_borde_color',
        'dpc_borde_grosor',
        'dpc_relleno',
        'dpc_fuente',
        'dpc_filas',
    ];

    // El CRM guardó un cambio: la tienda lo ve en la próxima petición.
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_TIENDA);
    }

    // ================= Tienda (pública) =================

    // GET public/detalle-producto/tienda -> { bloques, cintas, url_imagenes } del diseño que se ve hoy
    // (fn_detalle_producto_tienda: bloques visibles en orden con su estilo y cintas visibles en orden; las imágenes de
    // las cintas: url_imagenes + nombre). data = null si todavía no hay diseños (la tienda usa su diseño de siempre).
    public function tienda()
    {
        try {
            $detalle = Cache::remember(self::CACHE_TIENDA, self::SEGUNDOS_CACHE_TIENDA, function () {
                $fila = DB::selectOne('SELECT ecommerce.fn_detalle_producto_tienda() AS detalle');

                return $fila->detalle
                    ? json_decode($fila->detalle, true) + ['url_imagenes' => url('api/public/secciones/imagenes') . '/']
                    : null;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Diseño del detalle del producto', $detalle));
        } catch (\Throwable $th) {
            Log::error('No se pudo leer el diseño del detalle del producto', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo leer el diseño del detalle.', null));
        }
    }

    // ================= Diseño =================

    // Lista de diseños (grilla de la izquierda) -> fn_detalle_producto_listar_paginacion, con estado y en_tienda.
    public function listar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_detalle_producto_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Diseños listados con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los diseños', $th->getMessage()));
        }
    }

    // Un diseño completo con sus bloques y cintas -> fn_detalle_producto_buscar (+ url_imagenes para las cintas).
    public function buscar(int $id)
    {
        try {
            $fila = DB::selectOne('SELECT ecommerce.fn_detalle_producto_buscar(?) AS detalle', [$id]);

            if (!$fila || $fila->detalle === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontró el diseño.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Diseño encontrado',
                json_decode($fila->detalle, true) + ['url_imagenes' => url('api/public/secciones/imagenes') . '/']));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el diseño', $th->getMessage()));
        }
    }

    // Crea un diseño con sus 6 bloques -> fn_detalle_producto_add. dpr_copiar_de (opcional): copia los bloques y las
    // cintas de ese diseño.
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), $this->reglasDiseno(false) + [
            'dpr_copiar_de' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only([...self::CAMPOS_PAYLOAD, 'dpr_copiar_de']);

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_detalle_producto_add(?::jsonb) AS dpr_id', [$this->json($payload, $request)]);
            self::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Diseño creado con éxito', ['dpr_id' => $resultado->dpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear el diseño');
        }
    }

    // Datos generales -> fn_detalle_producto_edit. Solo se envían los campos que cambian.
    public function editar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), $this->reglasDiseno(true));

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        // El id sale de la URL, nunca del cuerpo.
        $payload = $request->only(self::CAMPOS_PAYLOAD) + ['dpr_id' => $id];

        return $this->ejecutar('ecommerce.fn_detalle_producto_edit', $payload, $request,
            'Diseño modificado con éxito', 'Error al modificar el diseño');
    }

    // ================= Bloques =================

    // Visible y estilo de un bloque -> fn_detalle_producto_bloque_guardar. Los campos que su tipo no usa se ignoran.
    public function guardarBloque(Request $request, int $id, int $dpb)
    {
        $color = ['nullable', 'string', 'regex:/^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/'];
        $validator = Validator::make($request->all(), [
            'dpb_visible' => 'sometimes|boolean',
            'dpb_color' => $color,
            'dpb_color_secundario' => $color,
            'dpb_tamano' => 'nullable|integer',
            'dpb_mayusculas' => 'nullable|boolean',
            'dpb_tachado_visible' => 'nullable|boolean',
            'dpb_redondeo' => 'nullable|integer',
            'dpb_disposicion' => 'nullable|string|in:lado,debajo',
        ] + array_fill_keys(['dpb_principal_texto', 'dpb_principal_borde', 'dpb_principal_hover_fondo', 'dpb_principal_hover_texto', 'dpb_principal_hover_borde', 'dpb_secundario_fondo', 'dpb_secundario_borde', 'dpb_secundario_hover_fondo', 'dpb_secundario_hover_texto', 'dpb_secundario_hover_borde'], $color));

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_BLOQUE) + ['dpr_id' => $id, 'dpb_id' => $dpb];

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_detalle_producto_bloque_guardar(?::jsonb) AS dpb_id', [$this->json($payload, $request)]);
            self::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Bloque guardado con éxito', ['dpb_id' => $resultado->dpb_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar el bloque');
        }
    }

    // Orden de los bloques (drag and drop) -> fn_detalle_producto_bloques_ordenar. ids = los 6 bloques, en el nuevo orden.
    public function ordenarBloques(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|size:6',
            'ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        return $this->ejecutar('ecommerce.fn_detalle_producto_bloques_ordenar', ['dpr_id' => $id, 'ids' => $request->input('ids')],
            $request, 'Orden guardado con éxito', 'Error al guardar el orden');
    }

    // ================= Cintas =================

    // Crear (sin dpc_id) o editar (con dpc_id) una cinta -> fn_detalle_producto_cinta_guardar.
    public function guardarCinta(Request $request, int $id)
    {
        $color = ['nullable', 'string', 'regex:/^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/'];
        $validator = Validator::make($request->all(), [
            'dpc_id' => 'nullable|integer',
            'dpc_visible' => 'sometimes|boolean',
            'dpc_movimiento' => 'sometimes|boolean',
            'dpc_velocidad' => 'nullable|integer',
            'dpc_fondo' => $color,
            'dpc_borde_color' => $color,
            'dpc_borde_grosor' => 'nullable|integer',
            'dpc_relleno' => 'nullable|integer',
            'dpc_fuente' => 'nullable|string|max:40',
            'dpc_filas' => ($request->filled('dpc_id') ? 'sometimes|' : 'required|') . 'array|min:1|max:2',
        ], [
            'dpc_filas.required' => 'Agregue al menos una frase o un ícono a la cinta.',
            'dpc_filas.array' => 'La cinta tiene que tener 1 o 2 filas.',
            'dpc_filas.min' => 'La cinta tiene que tener 1 o 2 filas.',
            'dpc_filas.max' => 'La cinta tiene que tener 1 o 2 filas.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_CINTA) + ['dpr_id' => $id];

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_detalle_producto_cinta_guardar(?::jsonb) AS dpc_id', [$this->json($payload, $request)]);
            self::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', 'Cinta guardada con éxito', ['dpc_id' => $resultado->dpc_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar la cinta');
        }
    }

    // Quita una cinta -> fn_detalle_producto_cinta_eliminar.
    public function eliminarCinta(Request $request, int $id, int $dpc)
    {
        return $this->ejecutar('ecommerce.fn_detalle_producto_cinta_eliminar', ['dpr_id' => $id, 'dpc_id' => $dpc], $request,
            'Cinta quitada', 'Error al quitar la cinta');
    }

    // Orden de las cintas (drag and drop) -> fn_detalle_producto_cintas_ordenar. ids = todas las cintas, en el nuevo orden.
    public function ordenarCintas(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1|max:10',
            'ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        return $this->ejecutar('ecommerce.fn_detalle_producto_cintas_ordenar', ['dpr_id' => $id, 'ids' => $request->input('ids')],
            $request, 'Orden guardado con éxito', 'Error al guardar el orden');
    }

    // Sube una imagen de una cinta (por ejemplo el logo de un banco): WebP de hasta 2 MB, en la carpeta de las imágenes
    // de las secciones (se sirve igual). Devuelve su nombre, que se guarda en la cinta al guardarla.
    public function subirImagenCinta(Request $request)
    {
        $error = ArchivosPaginaWeb::errorImagen($request, 'imagen');
        if ($error !== null) {
            return response()->json(RespuestaApi::returnResultado('error', $error, null));
        }

        // NAS o local según crm.parametro 'NAS' (ArchivosPaginaWeb::disco).
        $nombre = Str::random(40) . '.webp';
        if (!ArchivosPaginaWeb::guardar($request->file('imagen'), SeccionesController::CARPETA_IMAGENES, $nombre)) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir la imagen.', null));
        }

        return response()->json(RespuestaApi::returnResultado('success', 'Imagen subida', [
            'nombre' => $nombre,
            'url' => url('api/public/secciones/imagenes/' . $nombre),
        ]));
    }

    // ================= Papelera =================

    // A la papelera -> fn_detalle_producto_eliminar. El predeterminado no se elimina.
    public function eliminar(Request $request, int $id)
    {
        return $this->ejecutar('ecommerce.fn_detalle_producto_eliminar', ['dpr_id' => $id], $request,
            'Diseño enviado a la papelera', 'Error al eliminar el diseño');
    }

    // Vuelve de la papelera, inactivo -> fn_detalle_producto_restaurar.
    public function restaurar(Request $request, int $id)
    {
        return $this->ejecutar('ecommerce.fn_detalle_producto_restaurar', ['dpr_id' => $id], $request,
            'Diseño restaurado (queda inactivo)', 'Error al restaurar el diseño');
    }

    // Diseños en la papelera -> fn_detalle_producto_papelera_paginacion.
    public function papelera(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_detalle_producto_papelera_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

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

    // Auditoría de UN diseño (también de los de la papelera): resumen e historial paginado con la foto completa.
    public function auditoria(Request $request)
    {
        try {
            $dprId = (int) $request->query('dpr_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($dprId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Diseño no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ecommerce.fn_detalle_producto_auditoria_resumen(?)', [$dprId]);
            $eventos = DB::select('SELECT * FROM ecommerce.fn_detalle_producto_auditoria_listar_paginacion(?, ?, ?, ?)', [$dprId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del diseño', $th->getMessage()));
        }
    }

    // ================= Ayudantes =================

    private function reglasDiseno(bool $editar): array
    {
        $si = $editar ? 'sometimes' : 'nullable';

        return [
            'dpr_nombre' => ($editar ? 'sometimes|' : '') . 'required|string|max:60',
            'dpr_descripcion' => 'nullable|string|max:250',
            'dpr_predeterminado' => "$si|boolean",
            'dpr_vigente_desde' => 'nullable|date',
            'dpr_vigente_hasta' => 'nullable|date',
            'dpr_activo' => "$si|boolean",
        ];
    }

    // Llama a una función de escritura que devuelve dpr_id (editar, eliminar, restaurar, quitar, ordenar).
    private function ejecutar(string $funcion, array $payload, Request $request, string $exito, string $error)
    {
        try {
            $resultado = DB::selectOne("SELECT {$funcion}(?::jsonb) AS dpr_id", [$this->json($payload, $request)]);
            self::limpiarCache();

            return response()->json(RespuestaApi::returnResultado('success', $exito, ['dpr_id' => $resultado->dpr_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, $error);
        }
    }

    // El payload + el bloque 'auditoria' (usuario/IP/user agent/request_id), que la función usa para la auditoría
    // forense y el usu_id de las filas, y NO guarda como dato.
    private function json(array $payload, Request $request): string
    {
        $u = auth('api')->user();

        return json_encode($payload + ['auditoria' => [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ]]);
    }

    // Mensaje de MENSAJES_ERROR para el código que lanzó la función PG (palabra completa); si no hay, el genérico con
    // el detalle. Solo la primera línea del error de PG: el resto trae el CONTEXT y el SQL con el payload.
    private function errorTraducido(QueryException $e, string $mensajeGenerico)
    {
        $primera = strtok((string) ($e->errorInfo[2] ?? $e->getMessage()), "\n");

        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (preg_match('/\b' . preg_quote($codigo, '/') . '\b/', $primera)) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $mensajeGenerico, $e->getMessage()));
    }
}

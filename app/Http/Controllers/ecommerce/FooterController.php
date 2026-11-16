<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\EnlacesService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Footers de la tienda: cada footer es una fila de ecommerce.footers con sus columnas (footer_columnas), los elementos
// de cada columna (footer_elementos: enlace, imagen, párrafo o franja de íconos; los enlaces con sub-enlaces hasta 3
// niveles), los íconos de cada franja (footer_iconos) y los enlaces de la franja de derechos (elementos sin columna).
// Las reglas viven en las funciones ecommerce.fn_footer_* (database/sql/footers.sql). Mismo patrón que HeaderController.
class FooterController extends Controller
{
    public function __construct()
    {
        // verImagen y tienda son públicas: la tienda las pide sin sesión.
        $this->middleware('auth:api')->except(['verImagen', 'tienda']);
    }

    // Imágenes de los footers (logo, logos de bancos): carpeta privada storage/app/footers; se piden por
    // api/public/footers/imagenes/{nombre}. En la base se guarda la ruta relativa; aquí se completa con el dominio al salir.
    private const CARPETA_IMAGENES = 'footers';
    private const RUTA_IMAGENES = '/api/public/footers/imagenes/';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.(webp|jpg|png)$/';
    private const TIPOS_IMAGEN = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];

    // Lo que lee la tienda (tienda()): se guarda 60 s; cada cambio guardado en el CRM lo borra. Los 60 s cubren el
    // cambio de footer por fechas sin que nadie guarde nada.
    private const CACHE_TIENDA = 'ecommerce.footer_tienda';
    private const SEGUNDOS_CACHE_TIENDA = 60;

    // Solo esto sale a la tienda (sin usuarios, fechas de auditoría ni papelera).
    private const TIENDA_FOOTER = ['foo_fuente', 'foo_fondo', 'foo_color_titulo', 'foo_color_texto', 'foo_color_enlace',
        'foo_color_efecto', 'foo_derechos_visible', 'foo_derechos_fondo', 'foo_derechos_color'];
    private const TIENDA_COLUMNA = ['fco_titulo', 'fco_ancho'];
    private const TIENDA_ELEMENTO = ['fel_tipo', 'fel_texto', 'fel_negrita', 'fel_cursiva', 'fel_mayusculas', 'fel_color',
        'fel_tamano', 'fel_enlace', 'fel_nueva_pestana', 'fel_icono', 'fel_color_icono', 'fel_imagen', 'fel_imagen_alt',
        'fel_ancho', 'fel_iconos_tipo', 'fel_forma', 'fel_color_fondo_icono', 'fel_color_contorno', 'fel_alto'];
    private const TIENDA_ICONO = ['fic_red', 'fic_imagen', 'fic_imagen_alt', 'fic_enlace', 'fic_nueva_pestana'];

    // Mensaje amigable para cada excepción de las funciones ecommerce.fn_footer_*. Los dos últimos son los índices únicos.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'REQUIERE_NOMBRE' => 'Debe ingresar el nombre del footer.',
        'NOMBRE_LARGO' => 'El nombre no puede tener más de 60 caracteres.',
        'FOOTER_DUPLICADO' => 'Ya existe un footer con ese nombre.',
        'DESCRIPCION_LARGA' => 'La descripción no puede tener más de 250 caracteres.',
        'FUENTE_INVALIDA' => 'La tipografía seleccionada no es válida.',
        'DERECHOS_LARGO' => 'El texto de derechos reservados no puede tener más de 200 caracteres.',
        'VALOR_INVALIDO' => 'Uno de los campos Sí/No tiene un valor no válido.',
        'PREDETERMINADO_INACTIVO' => 'El footer predeterminado no puede estar inactivo.',
        'FECHA_INVALIDA' => 'Las fechas no son válidas.',
        'REQUIERE_VIGENCIA' => 'Un footer activo que no es el predeterminado necesita fecha de inicio y de fin (y si lleva una, lleva las dos).',
        'VIGENCIA_INVALIDA' => 'La fecha de fin debe ser posterior a la de inicio.',
        'VIGENCIA_CRUZADA' => 'Ya hay otro footer activo en esas fechas.',
        'COLOR_INVALIDO' => 'Uno de los colores no es válido. Use el formato #RRGGBB.',
        'FOOTER_NO_EXISTE' => 'El footer no existe o está en la papelera.',
        'COPIA_NO_EXISTE' => 'El footer del que se quiere copiar no existe o está en la papelera.',
        'PREDETERMINADO_NO_ELIMINABLE' => 'El predeterminado no se puede eliminar: la tienda siempre necesita uno. Marque otro con la estrella y luego elimine este.',
        'REQUIERE_PREDETERMINADO' => 'La tienda necesita un footer predeterminado: no se le puede quitar la marca.',
        // Columnas
        'COLUMNA_NO_EXISTE' => 'La columna no existe o ya fue quitada.',
        'COLUMNAS_CANTIDAD' => 'El footer admite hasta 5 columnas.',
        'ANCHO_COLUMNA_INVALIDO' => 'El ancho de la columna debe ser angosta, normal o ancha.',
        'ORDEN_INVALIDO' => 'El orden enviado no coincide con lo que hay en el footer. Recargue e intente de nuevo.',
        // Elementos
        'ELEMENTO_NO_EXISTE' => 'El elemento no existe o ya fue quitado.',
        // Antes que TIPO_INVALIDO: lo contiene.
        'ICONOS_TIPO_INVALIDO' => 'Elija si la franja es de redes sociales o de logos.',
        'TIPO_INVALIDO' => 'El tipo de elemento debe ser enlace, imagen, párrafo o franja de íconos.',
        'PADRE_NO_EXISTE' => 'El enlace del que depende no existe o está en otra columna.',
        'SUBENLACE_SOLO_ENLACE' => 'Solo los enlaces pueden tener sub-enlaces, y un sub-enlace solo puede ser un enlace.',
        'SUBENLACES_NIVEL' => 'Los enlaces admiten hasta 3 niveles (enlace, sub-enlace y sub-enlace del sub-enlace).',
        'SUBENLACE_CICLO' => 'Un enlace no puede quedar dentro de sí mismo ni de uno de sus sub-enlaces.',
        'SUBENLACES_CANTIDAD' => 'Cada enlace admite hasta 10 sub-enlaces.',
        'ELEMENTOS_CANTIDAD' => 'Cada columna admite hasta 15 elementos.',
        'DERECHOS_SOLO_ENLACES' => 'En la franja de derechos solo van enlaces, sin sub-enlaces.',
        'DERECHOS_SIN_SUBENLACES' => 'Los enlaces de la franja de derechos no llevan sub-enlaces.',
        'DERECHOS_CANTIDAD' => 'La franja de derechos admite hasta 5 enlaces.',
        'REQUIERE_TEXTO' => 'El texto no puede quedar vacío.',
        'TEXTO_LARGO' => 'El texto es demasiado largo (enlaces: 60 caracteres; párrafos: 500).',
        'TAMANO_INVALIDO' => 'El tamaño de letra debe estar entre 8 y 48 px.',
        'ANCHO_INVALIDO' => 'El ancho de la imagen debe estar entre 40 y 400 px.',
        'ALTO_INVALIDO' => 'El alto de los íconos debe estar entre 16 y 80 px.',
        'ENLACE_REQUIERE_FECHAS' => 'Si el enlace es temporal necesita fecha de inicio y de fin; si no, deje las dos vacías.',
        'DESTINO_INVALIDO' => 'El destino no es válido: elíjalo de la lista (las direcciones web empiezan con https://).',
        'DESTINO_NO_EXISTE' => 'El destino ya no existe o está en la papelera: elija otro.',
        'CARPETA_NO_PRINCIPAL' => 'Solo se pueden enlazar carpetas de primer nivel.',
        // Los íconos (redes y logos) siguen con su dirección escrita.
        'ENLACE_INVALIDO' => 'La dirección de un ícono debe empezar con https://, mailto: o tel: (o ser una ruta de la tienda), de hasta 250 caracteres.',
        'ICONO_INVALIDO' => 'El ícono no es válido.',
        'IMAGEN_INVALIDA' => 'La imagen debe ser una subida desde el CRM.',
        'REQUIERE_IMAGEN' => 'Suba una imagen.',
        'FORMA_INVALIDA' => 'La forma de los íconos debe ser círculo, cuadrado o sin fondo.',
        'ICONOS_INVALIDOS' => 'Los íconos enviados no son válidos.',
        'ICONOS_CANTIDAD' => 'Cada franja admite hasta 12 íconos.',
        'ICONO_NO_EXISTE' => 'Uno de los íconos no existe o ya no pertenece a esta franja.',
        'ICONO_REPETIDO' => 'Un ícono se envió dos veces.',
        'RED_INVALIDA' => 'La red social no es válida.',
        'REQUIERE_RED' => 'Elija la red social de cada ícono.',
        'REQUIERE_ENLACE_RED' => 'Cada red social necesita su dirección.',
        'ux_footers_nombre' => 'Ya existe un footer con ese nombre.',
        'ux_footers_predeterminado' => 'Otro usuario cambió el footer predeterminado al mismo tiempo. Intente de nuevo.',
    ];

    // Datos generales que se envían tal cual a fn_footer_add / fn_footer_edit.
    private const CAMPOS_PAYLOAD = [
        'foo_nombre',
        'foo_descripcion',
        'foo_predeterminado',
        'foo_vigente_desde',
        'foo_vigente_hasta',
        'foo_activo',
        'foo_fuente',
        'foo_fondo',
        'foo_color_titulo',
        'foo_color_texto',
        'foo_color_enlace',
        'foo_color_efecto',
        'foo_derechos_visible',
        'foo_derechos_texto',
        'foo_derechos_fondo',
        'foo_derechos_color',
    ];

    private const CAMPOS_COLUMNA = ['fco_id', 'fco_titulo', 'fco_ancho', 'fco_visible'];

    private const CAMPOS_ELEMENTO = [
        'fel_id', 'fco_id', 'fel_padre_id', 'fel_tipo', 'fel_activo',
        'fel_texto', 'fel_negrita', 'fel_cursiva', 'fel_mayusculas', 'fel_color', 'fel_tamano',
        'fel_destino', 'fel_nueva_pestana', 'fel_icono', 'fel_vigente_desde', 'fel_vigente_hasta', 'fel_color_icono',
        'fel_imagen', 'fel_imagen_alt', 'fel_ancho',
        'fel_iconos_tipo', 'fel_forma', 'fel_color_fondo_icono', 'fel_color_contorno', 'fel_alto',
        'iconos',
    ];

    // ================= Footer =================

    // Lista de footers (grilla de la izquierda) -> fn_footer_listar_paginacion, con estado y en_tienda.
    public function listar(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_footer_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Footers listados con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los footers', $th->getMessage()));
        }
    }

    // Un footer completo con sus columnas, elementos e íconos -> fn_footer_buscar (imágenes con el dominio).
    public function buscar(int $id)
    {
        try {
            $fila = DB::selectOne('SELECT ecommerce.fn_footer_buscar(?) AS footer', [$id]);

            if (!$fila || $fila->footer === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontró el footer.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Footer encontrado',
                $this->conImagenesCompletas(json_decode($fila->footer, true))));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el footer', $th->getMessage()));
        }
    }

    // Crea un footer -> fn_footer_add. foo_copiar_de (opcional): copia las columnas y elementos de ese footer.
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), $this->reglasFooter(false) + [
            'foo_copiar_de' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only([...self::CAMPOS_PAYLOAD, 'foo_copiar_de']);

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_footer_add(?::jsonb) AS foo_id', [$this->json($payload, $request)]);
            Cache::forget(self::CACHE_TIENDA);

            return response()->json(RespuestaApi::returnResultado('success', 'Footer creado con éxito', ['foo_id' => $resultado->foo_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear el footer');
        }
    }

    // Datos generales, colores y franja de derechos -> fn_footer_edit. Solo se envían los campos que cambian.
    public function editar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), $this->reglasFooter(true));

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        // El id sale de la URL, nunca del cuerpo.
        $payload = $request->only(self::CAMPOS_PAYLOAD) + ['foo_id' => $id];

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_footer_edit(?::jsonb) AS foo_id', [$this->json($payload, $request)]);
            Cache::forget(self::CACHE_TIENDA);

            return response()->json(RespuestaApi::returnResultado('success', 'Footer modificado con éxito', ['foo_id' => $resultado->foo_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al modificar el footer');
        }
    }

    // ================= Columnas =================

    // Crear (sin fco_id) o editar (con fco_id) una columna -> fn_footer_columna_guardar.
    public function guardarColumna(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'fco_id' => 'nullable|integer',
            'fco_titulo' => 'nullable|string|max:60',
            'fco_ancho' => 'sometimes|string|in:angosta,normal,ancha',
            'fco_visible' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_COLUMNA) + ['foo_id' => $id];

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_footer_columna_guardar(?::jsonb) AS fco_id', [$this->json($payload, $request)]);
            Cache::forget(self::CACHE_TIENDA);

            return response()->json(RespuestaApi::returnResultado('success', 'Columna guardada con éxito', ['fco_id' => $resultado->fco_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar la columna');
        }
    }

    // Quita una columna con sus elementos -> fn_footer_columna_eliminar.
    public function eliminarColumna(Request $request, int $id, int $fco)
    {
        return $this->ejecutar('ecommerce.fn_footer_columna_eliminar', ['foo_id' => $id, 'fco_id' => $fco], $request,
            'Columna quitada', 'Error al quitar la columna');
    }

    // Orden de las columnas -> fn_footer_columnas_ordenar. ids = todas las columnas, en el nuevo orden.
    public function ordenarColumnas(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1|max:5',
            'ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        return $this->ejecutar('ecommerce.fn_footer_columnas_ordenar', ['foo_id' => $id, 'ids' => $request->input('ids')],
            $request, 'Orden guardado con éxito', 'Error al guardar el orden');
    }

    // ================= Elementos =================

    // Crear (sin fel_id) o editar (con fel_id) un elemento con sus íconos -> fn_footer_elemento_guardar.
    public function guardarElemento(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'fel_id' => 'nullable|integer',
            'fco_id' => 'nullable|integer',
            'fel_padre_id' => 'nullable|integer',
            'fel_tipo' => 'nullable|string|in:enlace,imagen,parrafo,iconos',
            'fel_activo' => 'sometimes|boolean',
            'fel_texto' => 'nullable|string|max:500',
            'fel_negrita' => 'sometimes|boolean',
            'fel_cursiva' => 'sometimes|boolean',
            'fel_mayusculas' => 'sometimes|boolean',
            'fel_tamano' => 'nullable|integer',
            'fel_destino' => 'nullable|array',
            'fel_nueva_pestana' => 'sometimes|boolean',
            'fel_vigente_desde' => 'nullable|date',
            'fel_vigente_hasta' => 'nullable|date',
            'fel_imagen' => 'nullable|string|max:500',
            'fel_imagen_alt' => 'nullable|string|max:120',
            'fel_ancho' => 'nullable|integer',
            'fel_alto' => 'nullable|integer',
            'iconos' => 'sometimes|array|max:12',
            'iconos.*.fic_imagen' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only(self::CAMPOS_ELEMENTO) + ['foo_id' => $id];

        // Las imágenes pueden venir con el dominio (como las devuelve el CRM): se guarda solo la ruta.
        if (array_key_exists('fel_imagen', $payload)) {
            $payload['fel_imagen'] = $this->rutaImagen($payload['fel_imagen']);
        }
        if (isset($payload['iconos']) && is_array($payload['iconos'])) {
            foreach ($payload['iconos'] as $i => $icono) {
                if (is_array($icono) && array_key_exists('fic_imagen', $icono)) {
                    $payload['iconos'][$i]['fic_imagen'] = $this->rutaImagen($icono['fic_imagen']);
                }
            }
        }

        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_footer_elemento_guardar(?::jsonb) AS fel_id', [$this->json($payload, $request)]);
            Cache::forget(self::CACHE_TIENDA);

            return response()->json(RespuestaApi::returnResultado('success', 'Elemento guardado con éxito', ['fel_id' => $resultado->fel_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar el elemento');
        }
    }

    // Quita un elemento con sus sub-enlaces -> fn_footer_elemento_eliminar.
    public function eliminarElemento(Request $request, int $id, int $fel)
    {
        return $this->ejecutar('ecommerce.fn_footer_elemento_eliminar', ['foo_id' => $id, 'fel_id' => $fel], $request,
            'Elemento quitado', 'Error al quitar el elemento');
    }

    // Ordenar y mover elementos -> fn_footer_elementos_ordenar. { fco_id (null = derechos), fel_padre_id, ids }.
    public function ordenarElementos(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'fco_id' => 'nullable|integer',
            'fel_padre_id' => 'nullable|integer',
            'ids' => 'required|array|min:1|max:15',
            'ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        $payload = $request->only(['fco_id', 'fel_padre_id', 'ids']) + ['foo_id' => $id];

        return $this->ejecutar('ecommerce.fn_footer_elementos_ordenar', $payload, $request,
            'Orden guardado con éxito', 'Error al guardar el orden');
    }

    // Sube una imagen del footer (logo, logos de bancos): WebP, JPG o PNG de verdad (mimetypes mira el contenido) y
    // hasta 2 MB. Devuelve la dirección para el elemento o el ícono; se publica al guardarlo.
    public function subirImagen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imagen' => 'required|file|mimetypes:image/webp,image/jpeg,image/png|max:2048',
        ], [
            'imagen.required' => 'Elija una imagen.',
            'imagen.file' => 'Elija una imagen.',
            'imagen.mimetypes' => 'Solo se aceptan imágenes WebP, JPG o PNG.',
            'imagen.max' => 'La imagen no puede pesar más de 2 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        try {
            $extension = array_search($request->file('imagen')->getMimeType(), self::TIPOS_IMAGEN, true);
            $nombre = Str::random(40) . '.' . $extension;
            $request->file('imagen')->storeAs(self::CARPETA_IMAGENES, $nombre, 'local');

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen subida', [
                'url' => url(self::RUTA_IMAGENES . $nombre),
            ]));
        } catch (\Throwable $th) {
            Log::error('No se pudo subir la imagen del footer', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir la imagen.', null));
        }
    }

    // ================= Papelera =================

    // A la papelera -> fn_footer_eliminar. El predeterminado no se elimina.
    public function eliminar(Request $request, int $id)
    {
        return $this->ejecutar('ecommerce.fn_footer_eliminar', ['foo_id' => $id], $request,
            'Footer enviado a la papelera', 'Error al eliminar el footer');
    }

    // Vuelve de la papelera, inactivo -> fn_footer_restaurar.
    public function restaurar(Request $request, int $id)
    {
        return $this->ejecutar('ecommerce.fn_footer_restaurar', ['foo_id' => $id], $request,
            'Footer restaurado (queda inactivo)', 'Error al restaurar el footer');
    }

    // Footers en la papelera -> fn_footer_papelera_paginacion.
    public function papelera(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_footer_papelera_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

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

    // Auditoría de UN footer (también de los de la papelera): resumen e historial paginado con la foto completa.
    public function auditoria(Request $request)
    {
        try {
            $fooId = (int) $request->query('foo_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($fooId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Footer no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ecommerce.fn_footer_auditoria_resumen(?)', [$fooId]);
            $eventos = DB::select('SELECT * FROM ecommerce.fn_footer_auditoria_listar_paginacion(?, ?, ?, ?)', [$fooId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del footer', $th->getMessage()));
        }
    }

    // ================= Públicas (tienda) =================

    // El footer que la tienda muestra ahora (fn_footer_tienda), listo para dibujar: solo columnas visibles, elementos
    // activos (los enlaces, además, vigentes) con sus sub-enlaces adentro, íconos activos y {año} ya reemplazado.
    // data = null si todavía no hay footers (la tienda usa su diseño de respaldo).
    public function tienda()
    {
        try {
            $footer = Cache::remember(self::CACHE_TIENDA, self::SEGUNDOS_CACHE_TIENDA, function () {
                $fila = DB::selectOne('SELECT ecommerce.fn_footer_tienda() AS footer');

                return $fila->footer ? $this->footerParaTienda(json_decode($fila->footer, true)) : null;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Footer de la tienda', $footer));
        } catch (\Throwable $th) {
            Log::error('No se pudo leer el footer de la tienda', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo leer el footer.', null));
        }
    }

    // Entrega una imagen de la carpeta de footers y nada más (el nombre se revisa antes de abrir el archivo).
    public function verImagen(string $nombre)
    {
        $ruta = self::CARPETA_IMAGENES . '/' . $nombre;

        if (!preg_match(self::NOMBRE_IMAGEN, $nombre) || !Storage::disk('local')->exists($ruta)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($ruta), [
            'Content-Type' => self::TIPOS_IMAGEN[pathinfo($nombre, PATHINFO_EXTENSION)],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ================= Ayudantes =================

    private function footerParaTienda(array $f): array
    {
        $f = $this->conImagenesCompletas($f);

        // fel_enlace = la dirección del destino elegido (fel_destino), todas con una sola consulta. Un enlace cuyo destino
        // hoy no lleva a ningún lado (inactivo o en la papelera) no sale; una imagen sale sin enlace.
        $destinos = [];
        foreach ($f['columnas'] ?? [] as $i => $c) {
            foreach ($c['elementos'] ?? [] as $j => $e) {
                $destinos["c$i-$j"] = $e['fel_destino'] ?? null;
            }
        }
        foreach ($f['derechos'] ?? [] as $j => $e) {
            $destinos["d-$j"] = $e['fel_destino'] ?? null;
        }
        $rutas = array_combine(array_keys($destinos), app(EnlacesService::class)->rutas(array_values($destinos)) ?: []) ?: [];
        $poner = function (array $e, ?string $ruta): array {
            $e['fel_enlace'] = $ruta;
            $e['sin_salida'] = !empty($e['fel_destino']) && $ruta === null;
            return $e;
        };
        foreach ($f['columnas'] ?? [] as $i => $c) {
            foreach ($c['elementos'] ?? [] as $j => $e) {
                $f['columnas'][$i]['elementos'][$j] = $poner($e, $rutas["c$i-$j"] ?? null);
            }
        }
        foreach ($f['derechos'] ?? [] as $j => $e) {
            $f['derechos'][$j] = $poner($e, $rutas["d-$j"] ?? null);
        }

        $ahora = now();
        $solo = fn(array $fila, array $campos) => array_intersect_key($fila, array_flip($campos));

        $visible = fn(array $e) => !empty($e['fel_activo'])
            && ($e['fel_tipo'] !== 'enlace' || empty($e['sin_salida']))
            && ($e['fel_tipo'] !== 'enlace' || empty($e['fel_vigente_desde'])
                || ($ahora->gte(\Carbon\Carbon::parse($e['fel_vigente_desde'])) && $ahora->lt(\Carbon\Carbon::parse($e['fel_vigente_hasta']))));

        // Arma el árbol de un lugar (una columna o la franja de derechos). Un enlace sin destino sale como texto (con su
        // ícono, como el horario) o como título de sus sub-enlaces; una franja de íconos sin íconos activos no sale.
        $arbol = function (array $elementos, ?int $padre) use (&$arbol, $visible, $solo): array {
            $hijos = array_values(array_filter($elementos, fn($e) => ($e['fel_padre_id'] ?? null) === $padre && $visible($e)));
            usort($hijos, fn($a, $b) => [$a['fel_orden'], $a['fel_id']] <=> [$b['fel_orden'], $b['fel_id']]);

            $salida = [];
            foreach ($hijos as $e) {
                $item = $solo($e, self::TIENDA_ELEMENTO);

                if ($e['fel_tipo'] === 'enlace') {
                    $item['subenlaces'] = $arbol($elementos, $e['fel_id']);
                }

                if ($e['fel_tipo'] === 'iconos') {
                    $item['iconos'] = array_values(array_map(fn($i) => $solo($i, self::TIENDA_ICONO),
                        array_filter($e['iconos'] ?? [], fn($i) => !empty($i['fic_activo']))));
                    if (!$item['iconos']) {
                        continue;
                    }
                }

                $salida[] = $item;
            }

            return $salida;
        };

        $columnas = [];
        foreach ($f['columnas'] ?? [] as $c) {
            if (empty($c['fco_visible'])) {
                continue;
            }
            $columnas[] = $solo($c, self::TIENDA_COLUMNA) + ['elementos' => $arbol($c['elementos'] ?? [], null)];
        }

        return $solo($f, self::TIENDA_FOOTER) + [
            'foo_derechos_texto' => str_replace('{año}', (string) $ahora->year, (string) ($f['foo_derechos_texto'] ?? '')),
            'columnas' => $columnas,
            'derechos' => $arbol($f['derechos'] ?? [], null),
        ];
    }

    // Las imágenes se guardan con su ruta relativa: al salir llevan el dominio del back.
    private function conImagenesCompletas(array $f): array
    {
        $completa = fn($ruta) => is_string($ruta) && str_starts_with($ruta, self::RUTA_IMAGENES) ? url($ruta) : $ruta;
        $elementos = function (array $lista) use ($completa): array {
            foreach ($lista as $i => $e) {
                $lista[$i]['fel_imagen'] = $completa($e['fel_imagen'] ?? null);
                foreach ($e['iconos'] ?? [] as $j => $icono) {
                    $lista[$i]['iconos'][$j]['fic_imagen'] = $completa($icono['fic_imagen'] ?? null);
                }
            }

            return $lista;
        };

        foreach ($f['columnas'] ?? [] as $i => $c) {
            $f['columnas'][$i]['elementos'] = $elementos($c['elementos'] ?? []);
        }
        $f['derechos'] = $elementos($f['derechos'] ?? []);

        return $f;
    }

    // De "https://dominio/api/public/footers/imagenes/x.webp" a "/api/public/footers/imagenes/x.webp" (la función SQL
    // revisa que sea una imagen subida desde el CRM).
    private function rutaImagen($valor)
    {
        if (!is_string($valor) || trim($valor) === '') {
            return $valor;
        }

        return (string) (parse_url(trim($valor), PHP_URL_PATH) ?? '');
    }

    private function reglasFooter(bool $editar): array
    {
        $color = ['nullable', 'string', 'regex:/^#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/'];
        $si = $editar ? 'sometimes' : 'nullable';

        return [
            'foo_nombre' => ($editar ? 'sometimes|' : '') . 'required|string|max:60',
            'foo_descripcion' => 'nullable|string|max:250',
            'foo_predeterminado' => "$si|boolean",
            'foo_vigente_desde' => 'nullable|date',
            'foo_vigente_hasta' => 'nullable|date',
            'foo_activo' => "$si|boolean",
            'foo_fuente' => "$si|string|max:30",
            'foo_fondo' => $color,
            'foo_color_titulo' => $color,
            'foo_color_texto' => $color,
            'foo_color_enlace' => $color,
            'foo_color_efecto' => $color,
            'foo_derechos_visible' => "$si|boolean",
            'foo_derechos_texto' => 'nullable|string|max:200',
            'foo_derechos_fondo' => $color,
            'foo_derechos_color' => $color,
        ];
    }

    // Llama a una función de escritura que devuelve foo_id (eliminar, restaurar, quitar, ordenar).
    private function ejecutar(string $funcion, array $payload, Request $request, string $exito, string $error)
    {
        try {
            $resultado = DB::selectOne("SELECT {$funcion}(?::jsonb) AS foo_id", [$this->json($payload, $request)]);
            Cache::forget(self::CACHE_TIENDA);

            return response()->json(RespuestaApi::returnResultado('success', $exito, ['foo_id' => $resultado->foo_id]));
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

    // Mensaje de MENSAJES_ERROR para el código que lanzó la función PG; si no hay, el genérico con el detalle. Se busca
    // solo en la primera línea del error de PG: el resto trae el CONTEXT (el código de la función, con otros códigos
    // escritos adentro) y el SQL con el payload.
    private function errorTraducido(QueryException $e, string $mensajeGenerico)
    {
        $primera = strtok((string) ($e->errorInfo[2] ?? $e->getMessage()), "\n");

        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($primera, $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $mensajeGenerico, $e->getMessage()));
    }
}

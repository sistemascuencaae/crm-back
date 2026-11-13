<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ProductoTiendaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Secciones de las páginas de la tienda (ecommerce.secciones y sus elementos en ecommerce.seccion_elementos): una
// plantilla puesta en una página con sus valores. Catálogo de plantillas, crear, editar, ordenar, papelera (borrado
// lógico), auditoría y las imágenes que se suben para las secciones.
// Las reglas viven en ecommerce.fn_seccion_* y fn_plantilla_buscar (database/sql/plantillas.sql, seccion_add.sql,
// seccion_edit.sql y seccion_papelera.sql). Aquí solo se revisa la forma del pedido; el contenido lo valida la base
// contra los campos de la plantilla.
class SeccionesController extends Controller
{
    // Imágenes de las secciones: carpeta privada storage/app/secciones; la tienda y el CRM las piden por
    // api/public/secciones/imagenes/{nombre}. En la base se guarda solo el nombre: 40 letras y números + .webp.
    private const CARPETA_IMAGENES = 'secciones';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.webp$/';

    // Códigos sin "dónde" -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se pudo identificar al usuario. Vuelva a iniciar sesión.',
        'PAGINA_NO_EXISTE' => 'La página no existe o está en la papelera.',
        'PAGINA_EN_PAPELERA' => 'No se puede restaurar porque su página está en la papelera: restaure primero la página.',
        'PLANTILLA_NO_EXISTE' => 'La plantilla elegida no existe.',
        'PLANTILLA_INACTIVA' => 'La plantilla elegida ya no está disponible para secciones nuevas.',
        'PLANTILLA_NO_SE_CAMBIA' => 'La plantilla de una sección no se puede cambiar: cree una sección nueva con la otra plantilla.',
        'REQUIERE_NOMBRE' => 'Debe ingresar el nombre de la sección.',
        'NOMBRE_LARGO' => 'El nombre no puede tener más de 80 caracteres.',
        'SECCION_DUPLICADA' => 'Ya existe una sección con ese nombre en la página (si está restaurando, cambie primero el nombre de la otra sección).',
        'VALOR_INVALIDO' => 'El valor de "Visible" no es válido.',
        'ELEMENTOS_INVALIDOS' => 'El contenido de la sección no es válido.',
        'SECCION_NO_EXISTE' => 'La sección no existe o está en la papelera.',
        'SECCION_NO_EN_PAPELERA' => 'La sección no está en la papelera (puede que otro usuario ya la restauró).',
        'PAPELERA_SECCION_VISIBLE' => 'No se puede eliminar porque está visible: ocúltela primero.',
        'REQUIERE_SECCIONES' => 'Envíe el nuevo orden de las secciones.',
        'SECCIONES_NO_COINCIDEN' => 'La página cambió mientras la ordenaba (se agregó o eliminó una sección). Vuelva a cargarla.',
    ];

    // Códigos con "dónde" (CODIGO:titulo_tamano, CODIGO:imagen[1].alt, CODIGO:tarjeta.fondo, CODIGO:productos) ->
    // qué le pasa a ese campo o lista. {min}, {max} y {largo} salen de la plantilla.
    private const MENSAJES_CAMPO = [
        'CAMPO_NO_EXISTE' => 'no es un campo de esta plantilla.',
        'REQUIERE_CAMPO' => 'es obligatorio.',
        'CAMPO_INVALIDO' => 'tiene un valor no válido.',
        'CAMPO_FUERA_DE_RANGO' => 'tiene que estar entre {min} y {max}.',
        'CAMPO_LARGO' => 'no puede tener más de {largo} caracteres.',
        'OPCION_INVALIDA' => 'no es una de las opciones permitidas.',
        'ENLACE_INVALIDO' => 'el enlace no es válido: elija un destino de la lista.',
        'ENLACE_NO_EXISTE' => 'el destino del enlace ya no existe o está en la papelera: elija otro.',
        'RESALTADO_INVALIDO' => 'la parte resaltada tiene que estar escrita dentro del texto.',
        'FORMATO_INVALIDO' => 'el formato del texto no es válido (cada pedazo tiene que estar dentro de la frase, sin pisarse con otro, y como máximo 5). Vuelva a seleccionar los pedazos.',
        'VALORES_INVALIDOS' => 'los valores no son válidos.',
        'CONTENIDO_NO_EXISTE' => 'no es parte de esta plantilla.',
        'CONTENIDO_INVALIDO' => 'la lista no es válida.',
        'CONTENIDO_CANTIDAD' => '{cantidad}.',
        'CONTENIDO_REPETIDO' => 'está repetido en la lista.',
        'ELEMENTO_INVALIDO' => 'no es válido para esta lista.',
        'IMAGEN_INVALIDA' => 'la imagen no es válida: súbala de nuevo desde el CRM.',
        'IMAGEN_MOVIL_NO_APLICA' => 'esta lista no lleva imagen para celular.',
        'GRUPO_NO_EXISTE' => 'el grupo de productos no existe o está en la papelera.',
        'PRODUCTO_NO_EXISTE' => 'el producto no existe o está en la papelera.',
        'GRUPO_REQUERIDO' => 'elija primero el grupo de productos y después, de ese grupo, el producto.',
        'PRODUCTO_NO_EN_GRUPO' => 'el producto ya no está en el grupo elegido (puede que lo hayan quitado del grupo): elija otro.',
        'CONTENIDO_GRUPO_DISTINTO' => 'todos los productos de la lista tienen que ser del mismo grupo.',
        'FECHAS_INVALIDAS' => 'tiene que ser después de "Sale desde".',
    ];

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['verImagen']]);
    }

    // Plantillas que se pueden elegir para una sección nueva (de sección y activas), en el orden del catálogo.
    public function plantillas()
    {
        try {
            $registros = DB::select(
                "SELECT pla_id, pla_codigo, pla_nombre, pla_descripcion, pla_miniatura, pla_tarjeta_id IS NOT NULL AS tiene_tarjeta
                   FROM ecommerce.plantillas
                  WHERE pla_alcance = 'seccion' AND pla_activo
                  ORDER BY pla_orden, pla_id"
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Plantillas listadas con éxito', ['registros' => $registros]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las plantillas', $th->getMessage()));
        }
    }

    // Una plantilla con todo lo necesario para armar el formulario -> ecommerce.fn_plantilla_buscar.
    public function plantilla(int $id)
    {
        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_plantilla_buscar(?) AS plantilla', [$id]);

            if (!$resultado || $resultado->plantilla === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'La plantilla no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Plantilla cargada con éxito', [
                'plantilla' => json_decode($resultado->plantilla),
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la plantilla', $th->getMessage()));
        }
    }

    // Una sección (también de la papelera) con sus elementos y su plantilla, para editarla. url_imagenes: a esa dirección
    // se le agrega el nombre guardado de cada imagen.
    public function ver(int $id)
    {
        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_seccion_buscar(?) AS seccion', [$id]);

            if (!$resultado || $resultado->seccion === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'La sección no existe.', null));
            }

            $seccion = json_decode($resultado->seccion);
            $plantilla = DB::selectOne('SELECT ecommerce.fn_plantilla_buscar(?) AS plantilla', [$seccion->pla_id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Sección cargada con éxito', [
                'seccion' => $seccion,
                'plantilla' => json_decode($plantilla->plantilla),
                'url_imagenes' => url('api/public/secciones/imagenes') . '/',
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la sección', $th->getMessage()));
        }
    }

    // Crea la sección al final de la página -> ecommerce.fn_seccion_add.
    // Body: { pag_id, pla_id, sec_nombre, sec_visible?, sec_valores?: {}, sec_valores_tarjeta?: {},
    //         elementos?: { clave_de_la_lista: [ { imagen?, imagen_movil?, gpr_id?, pro_id?, valores?: {} } ] } }
    public function crear(Request $request)
    {
        $error = $this->validarForma($request, true);
        if ($error) {
            return $error;
        }

        $payload = $this->payload($request, ['pag_id', 'pla_id', 'sec_nombre', 'sec_visible', 'sec_valores', 'sec_valores_tarjeta', 'elementos']);

        try {
            // En su propio bloque: si la base rechaza la sección, el bloque se deshace ANTES de que errorTraducido
            // consulte la plantilla para nombrar el campo (si no, esa consulta caería en una transacción abortada).
            $resultado = DB::transaction(fn() => DB::selectOne('SELECT ecommerce.fn_seccion_add(?::jsonb) AS sec_id', [json_encode($payload)]));

            return response()->json(RespuestaApi::returnResultado('success', 'Sección creada con éxito', ['sec_id' => $resultado->sec_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al crear la sección', (int) $request->input('pla_id'));
        }
    }

    // Modifica la sección -> ecommerce.fn_seccion_edit. Lo que no viene se queda como está; lo que viene REEMPLAZA a lo
    // anterior (sec_valores completo, elementos completos). Body: { sec_nombre, sec_visible?, sec_valores?,
    // sec_valores_tarjeta?, elementos? }
    public function editar(Request $request, int $id)
    {
        $error = $this->validarForma($request, false);
        if ($error) {
            return $error;
        }

        $payload = $this->payload($request, ['sec_nombre', 'sec_visible', 'sec_valores', 'sec_valores_tarjeta', 'elementos', 'pla_id']);
        $payload->sec_id = $id;

        try {
            $resultado = DB::transaction(fn() => DB::selectOne('SELECT ecommerce.fn_seccion_edit(?::jsonb) AS sec_id', [json_encode($payload)]));

            return response()->json(RespuestaApi::returnResultado('success', 'Sección modificada con éxito', ['sec_id' => $resultado->sec_id]));
        } catch (QueryException $e) {
            $plaId = DB::selectOne('SELECT pla_id FROM ecommerce.secciones WHERE sec_id = ?', [$id])->pla_id ?? null;

            return $this->errorTraducido($e, 'Error al modificar la sección', $plaId);
        }
    }

    // Nuevo orden de las secciones de una página -> ecommerce.fn_seccion_ordenar. Body: { secciones: [sec_id, ...] } con
    // TODAS las secciones de la página.
    public function ordenar(Request $request, int $pagId)
    {
        $validator = Validator::make($request->all(), [
            'secciones' => 'required|array|min:1',
            'secciones.*' => 'required|integer|min:1',
        ], [
            'secciones.required' => 'Envíe el nuevo orden de las secciones.',
            'secciones.array' => 'El orden de las secciones no es válido.',
            'secciones.min' => 'Envíe el nuevo orden de las secciones.',
            'secciones.*.integer' => 'Una de las secciones no es válida.',
            'secciones.*.min' => 'Una de las secciones no es válida.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            DB::selectOne('SELECT ecommerce.fn_seccion_ordenar(?::jsonb) AS pag_id', [json_encode([
                'pag_id' => $pagId,
                'secciones' => array_map('intval', $request->input('secciones')),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', ['pag_id' => $pagId]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al ordenar las secciones');
        }
    }

    // A la papelera: borrado lógico -> ecommerce.fn_seccion_papelera. No se puede vaciar.
    public function eliminar(Request $request, int $id)
    {
        try {
            $resultado = DB::selectOne('SELECT ecommerce.fn_seccion_papelera(?::jsonb) AS sec_id', [json_encode(['sec_id' => $id, 'auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Sección enviada a la papelera', ['sec_id' => $resultado->sec_id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al eliminar la sección');
        }
    }

    // Vuelve de la papelera al final de su página -> ecommerce.fn_seccion_restaurar (revalida su contenido).
    public function restaurar(Request $request, int $id)
    {
        try {
            $resultado = DB::transaction(fn() => DB::selectOne('SELECT ecommerce.fn_seccion_restaurar(?::jsonb) AS sec_id', [json_encode(['sec_id' => $id, 'auditoria' => $this->contextoAuditoriaForense($request)])]));

            return response()->json(RespuestaApi::returnResultado('success', 'Sección restaurada con éxito', ['sec_id' => $resultado->sec_id]));
        } catch (QueryException $e) {
            $plaId = DB::selectOne('SELECT pla_id FROM ecommerce.secciones WHERE sec_id = ?', [$id])->pla_id ?? null;

            return $this->errorTraducido($e, 'Error al restaurar la sección', $plaId);
        }
    }

    // Secciones de UNA página que están en la papelera -> ecommerce.fn_seccion_papelera_paginacion.
    // GET ?pag_id=&pagina=&tamanio=&busqueda=
    public function papelera(Request $request)
    {
        try {
            $pagId = (int) $request->query('pag_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($pagId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Página no válida', null));
            }

            $registros = DB::select('SELECT * FROM ecommerce.fn_seccion_papelera_paginacion(?, ?, ?, ?)', [$pagId, $pagina, $tamanio, $busqueda]);

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

    // Auditoría de UNA sección (también de las de la papelera). GET ?sec_id=&pagina=&tamanio=&busqueda=
    public function auditoria(Request $request)
    {
        try {
            $secId = (int) $request->query('sec_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($secId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Sección no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ecommerce.fn_seccion_auditoria_resumen(?)', [$secId]);
            $eventos = DB::select('SELECT * FROM ecommerce.fn_seccion_auditoria_listar_paginacion(?, ?, ?, ?)', [$secId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la sección', $th->getMessage()));
        }
    }

    // Tarjetas REALES de productos para la vista previa del CRM (foto, marca, precio, precio anterior): las de un grupo
    // (en su orden) o las de unos productos. Regla de los grupos de productos: salen los activos con stock
    // (ProductoTiendaService::tarjetasDe).
    // GET ?gpr_id=  o  ?pro_ids=1,2,3
    public function productos(Request $request, ProductoTiendaService $tienda)
    {
        try {
            $gprId = (int) $request->query('gpr_id', 0);
            if ($gprId > 0) {
                $proIds = array_column(DB::select(
                    'SELECT pro_id FROM ecommerce.grupo_productos_detalles WHERE gpr_id = ? ORDER BY gpd_orden', [$gprId]
                ), 'pro_id');
            } else {
                $proIds = array_filter(array_map('intval', explode(',', (string) $request->query('pro_ids', ''))));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Productos de la vista previa', [
                // Todas las del grupo: los afiches no tienen límite (tarjetasDe corta en 24 si no se le dice cuántas).
                'tarjetas' => $proIds ? $tienda->tarjetasDe($proIds, count($proIds)) : [],
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los productos de la vista previa', $th->getMessage()));
        }
    }

    // Marcas de unos productos para el editor (botones Oferta y Destacado junto a cada producto): las del producto, que
    // valen para todas las secciones. Se cambian con inventarios marcarProductoWeb (crm.fn_producto_web_marcar).
    // GET ?pro_ids=1,2,3
    public function marcasProductos(Request $request)
    {
        try {
            $proIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $request->query('pro_ids', ''))))));
            $marcas = $proIds ? DB::select(
                'SELECT pro_id, COALESCE(pro_oferta, false) AS oferta, COALESCE(pro_destacado, false) AS destacado
                   FROM public.producto WHERE pro_id = ANY (?::integer[])',
                ['{' . implode(',', array_slice($proIds, 0, 200)) . '}']
            ) : [];

            return response()->json(RespuestaApi::returnResultado('success', 'Marcas de los productos', ['marcas' => $marcas]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar las marcas de los productos', $th->getMessage()));
        }
    }

    // Sube una imagen para una lista de imágenes de una plantilla (pco_id): solo WebP de verdad (mimetypes mira el
    // contenido, no la extensión) y hasta el peso máximo de ESA lista (pco_peso_maximo_kb: banners 2 MB, tarjetas 500 KB,
    // logos y fotos del 360 200 KB). Devuelve el nombre (para guardarlo en la sección) y la dirección para verla.
    // Body (multipart): imagen, pco_id
    public function subirImagen(Request $request)
    {
        $contenido = DB::selectOne(
            "SELECT pco_id, pco_etiqueta, pco_peso_maximo_kb FROM ecommerce.plantilla_contenidos
              WHERE pco_id = ? AND pco_tipo = 'imagen'",
            [(int) $request->input('pco_id')]
        );

        if (!$contenido) {
            return response()->json(RespuestaApi::returnResultado('error', 'La lista de imágenes no es válida.', null));
        }

        $kb = (int) $contenido->pco_peso_maximo_kb;
        $peso = $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.') . ' MB' : $kb . ' KB';

        $validator = Validator::make($request->all(), [
            'imagen' => 'required|file|mimetypes:image/webp|max:' . $kb,
        ], [
            'imagen.required' => 'Elija una imagen.',
            'imagen.file' => 'Elija una imagen.',
            'imagen.mimetypes' => 'Solo se aceptan imágenes WebP (.webp).',
            'imagen.max' => "La imagen no puede pesar más de {$peso} ({$contenido->pco_etiqueta}).",
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        try {
            $nombre = Str::random(40) . '.webp';
            $request->file('imagen')->storeAs(self::CARPETA_IMAGENES, $nombre, 'local');

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen subida', [
                'nombre' => $nombre,
                'url' => url('api/public/secciones/imagenes/' . $nombre),
            ]));
        } catch (\Throwable $th) {
            Log::error('No se pudo subir la imagen de la sección', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir la imagen.', null));
        }
    }

    // Una imagen de las secciones (pública: la pide la tienda y el CRM). El nombre nunca cambia de contenido.
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

    // Traduce un error de sección con "dónde" (CODIGO:ruta). La usa también PaginasController al clonar.
    // Devuelve null si el error no es de ese tipo.
    public static function errorDeSeccion(QueryException $e, ?int $plaId = null)
    {
        $texto = $e->errorInfo[2] ?? $e->getMessage();

        if (!preg_match('/\b([A-Z_]+):([A-Za-z0-9_\[\]\.]+)/', $texto, $m) || !isset(self::MENSAJES_CAMPO[$m[1]])) {
            return null;
        }

        [$etiqueta, $datos] = self::describirRuta($m[2], $plaId);
        $mensaje = strtr(self::MENSAJES_CAMPO[$m[1]], $datos);

        return response()->json(RespuestaApi::returnResultado('error', $etiqueta . ': ' . $mensaje, ['codigo' => $m[1], 'campo' => $m[2]]));
    }

    // "imagen[1].alt" -> ["Imagen del banner n.º 1 › Texto alternativo", {min,max,largo,cantidad}] con las etiquetas de
    // la plantilla. Formas: clave | tarjeta.clave | lista | lista[n] | lista[n].clave
    private static function describirRuta(string $ruta, ?int $plaId): array
    {
        $datos = ['{min}' => '', '{max}' => '', '{largo}' => '', '{cantidad}' => 'no tiene la cantidad permitida de elementos'];
        preg_match('/^(?:(tarjeta)\.)?([a-z0-9_]+)(?:\[(\d+)\])?(?:\.([a-z0-9_]+))?$/', $ruta, $p);
        $esTarjeta = ($p[1] ?? '') === 'tarjeta';
        $primero = $p[2] ?? $ruta;
        $posicion = $p[3] ?? '';
        $claveElemento = $p[4] ?? '';

        // La lista (si la ruta habla de una) y el campo.
        $lista = null;
        if (!$esTarjeta && ($posicion !== '' || $claveElemento !== '' || !self::buscarCampo($plaId, null, $primero))) {
            $lista = DB::selectOne(
                'SELECT pco_id, pco_etiqueta, pco_minimo, pco_maximo FROM ecommerce.plantilla_contenidos
                  WHERE pco_clave = ? AND (?::integer IS NULL OR pla_id = ?) ORDER BY pco_id LIMIT 1',
                [$primero, $plaId, $plaId]
            );
        }

        $campo = null;
        if ($esTarjeta) {
            $tarjetaId = $plaId ? (DB::selectOne('SELECT pla_tarjeta_id FROM ecommerce.plantillas WHERE pla_id = ?', [$plaId])->pla_tarjeta_id ?? null) : null;
            $campo = self::buscarCampo($tarjetaId, null, $primero, true);
        } elseif ($lista && $claveElemento !== '') {
            $campo = self::buscarCampo(null, $lista->pco_id, $claveElemento);
        } elseif (!$lista) {
            $campo = self::buscarCampo($plaId, null, $primero);
        }

        $partes = [];
        if ($esTarjeta) {
            $partes[] = 'Tarjetas';
        }
        if ($lista) {
            $partes[] = $lista->pco_etiqueta . ($posicion !== '' ? ' n.º ' . $posicion : '');
            $datos['{cantidad}'] = $lista->pco_maximo === null
                ? 'tiene que tener al menos ' . $lista->pco_minimo . ' elemento(s)'
                : ($lista->pco_minimo == $lista->pco_maximo
                    ? 'tiene que tener exactamente ' . $lista->pco_maximo . ' elemento(s)'
                    : 'tiene que tener entre ' . $lista->pco_minimo . ' y ' . $lista->pco_maximo . ' elementos');
        }
        if ($campo) {
            $partes[] = $campo->pca_etiqueta;
            $datos['{min}'] = (string) $campo->pca_minimo;
            $datos['{max}'] = (string) $campo->pca_maximo;
            $datos['{largo}'] = (string) $campo->pca_largo_maximo;
        } elseif (!$lista) {
            $partes[] = $claveElemento !== '' ? $claveElemento : $primero;
        }

        return [implode(' › ', $partes), $datos];
    }

    // Un campo por clave: de una plantilla (pco_id vacío) o de una lista. Sin plantilla conocida, el primero con esa clave.
    private static function buscarCampo(?int $plaId, ?int $pcoId, string $clave, bool $deComponente = false)
    {
        if ($pcoId !== null) {
            return DB::selectOne('SELECT pca_etiqueta, pca_minimo, pca_maximo, pca_largo_maximo FROM ecommerce.plantilla_campos
                                   WHERE pco_id = ? AND pca_clave = ? LIMIT 1', [$pcoId, $clave]);
        }

        return DB::selectOne(
            "SELECT c.pca_etiqueta, c.pca_minimo, c.pca_maximo, c.pca_largo_maximo
               FROM ecommerce.plantilla_campos c JOIN ecommerce.plantillas p ON p.pla_id = c.pla_id
              WHERE c.pco_id IS NULL AND c.pca_clave = ? AND (?::integer IS NULL OR c.pla_id = ?)
                AND p.pla_alcance = ?
              ORDER BY c.pca_id LIMIT 1",
            [$clave, $plaId, $plaId, $deComponente ? 'componente' : 'seccion']
        );
    }

    // Revisa la forma del pedido (no el contenido: eso lo valida la base contra la plantilla).
    private function validarForma(Request $request, bool $alta)
    {
        $validator = Validator::make($request->all(), [
            'pag_id' => ($alta ? 'required' : 'sometimes') . '|integer|min:1',
            'pla_id' => ($alta ? 'required' : 'sometimes') . '|integer|min:1',
            'sec_nombre' => 'required|string|max:80',
            'sec_visible' => 'sometimes|boolean',
            'sec_valores' => 'sometimes|nullable|array',
            'sec_valores_tarjeta' => 'sometimes|nullable|array',
            'elementos' => 'sometimes|nullable|array',
        ], [
            'pag_id.required' => 'Falta la página de la sección.',
            'pag_id.integer' => 'La página no es válida.',
            'pla_id.required' => 'Elija la plantilla de la sección.',
            'pla_id.integer' => 'La plantilla no es válida.',
            'sec_nombre.required' => 'Debe ingresar el nombre de la sección.',
            'sec_nombre.max' => 'El nombre no puede tener más de 80 caracteres.',
            'sec_visible.boolean' => 'El valor de "Visible" no es válido.',
            'sec_valores.array' => 'Los valores de la sección no son válidos.',
            'sec_valores_tarjeta.array' => 'El estilo de las tarjetas no es válido.',
            'elementos.array' => 'El contenido de la sección no es válido.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        return null;
    }

    // Arma el p_json con los campos permitidos tomados del cuerpo JSON TAL CUAL llegó: así un objeto vacío sigue siendo
    // objeto ({}) y no se convierte en lista ([]) como pasaría con $request->all(). Lleva el bloque 'auditoria'.
    private function payload(Request $request, array $campos): object
    {
        $cuerpo = json_decode($request->getContent());
        if (!is_object($cuerpo)) {
            $cuerpo = (object) $request->all();
        }

        $payload = new \stdClass();
        foreach ($campos as $campo) {
            if (property_exists($cuerpo, $campo)) {
                $payload->$campo = $cuerpo->$campo;
            }
        }
        $payload->auditoria = $this->contextoAuditoriaForense($request);

        return $payload;
    }

    // errorInfo[2] trae solo el mensaje de PG (getMessage() incluye el SQL con el payload). Primero los errores con
    // "dónde" (campo o lista de la plantilla), después los códigos simples.
    private function errorTraducido(QueryException $e, string $porDefecto, ?int $plaId = null)
    {
        $conDonde = self::errorDeSeccion($e, $plaId);
        if ($conDonde) {
            return $conDonde;
        }

        $texto = $e->errorInfo[2] ?? $e->getMessage();

        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($texto, $codigo) !== false) {
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

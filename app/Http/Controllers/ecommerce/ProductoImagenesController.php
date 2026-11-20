<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Imágenes de un producto (Ecommerce > Contenido de productos; antes en Inventarios > Productos): un CRUD sencillo de UNA lista ordenada de hasta 10 imágenes. La
// tarjeta de la tienda muestra la 1.ª y la 2.ª; el detalle, todas. Listar, subir, editar (visible o reemplazar la foto),
// ordenar, eliminar (borra de verdad: no hay papelera) y auditoría.
// Las reglas (máximo 10, archivo sin repetir, auditoría módulo PRODUCTOS) viven en inventarios.fn_producto_imagen_*
// (database/sql/producto_imagenes_una_seccion.sql y producto_imagenes_sin_papelera.sql). La base guarda solo el nombre
// del archivo; el archivo va en ecommerce/productos/{pro_id}/ (NAS o local) y se entrega por
// api/public/pagina-web/imagenes/productos/{pro_id}/{nombre} (paginaWeb/ArchivosTiendaController::verImagenProducto).
class ProductoImagenesController extends Controller
{
    private const CARPETA = ArchivosPaginaWeb::CARPETA . '/productos';
    private const IMAGEN_MAXIMO_KB = 2048;

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se identificó al usuario que realiza el cambio.',
        'PRODUCTO_NO_EXISTE' => 'El producto no existe.',
        'REQUIERE_IMAGEN' => 'Elija una imagen.',
        'IMAGEN_INVALIDA' => 'La imagen no es válida: súbala de nuevo.',
        'IMAGEN_REPETIDA' => 'Esa imagen ya está en el producto.',
        'ux_producto_imagenes_archivo' => 'Esa imagen ya está en el producto.',
        'VISIBLE_INVALIDO' => 'El valor de "Visible" no es válido.',
        'IMAGENES_MAXIMO' => 'El producto ya tiene 10 imágenes: elimine una para agregar otra.',
        'IMAGEN_NO_EXISTE' => 'La imagen ya no existe (puede que otro usuario la eliminó).',
        'REQUIERE_IMAGENES' => 'Envíe el nuevo orden de las imágenes.',
        'IMAGENES_NO_COINCIDEN' => 'Las imágenes del producto cambiaron mientras las ordenaba. Vuelva a cargarlas.',
    ];

    private const MENSAJES_FORMULARIO = [
        'pro_id.required' => 'Falta el producto.',
        'pro_id.integer' => 'El producto no es válido.',
        'imagen.required' => 'Elija una imagen.',
        'imagen.file' => 'Elija una imagen.',
        'imagen.mimetypes' => 'Solo se aceptan imágenes WebP.',
        'imagen.max' => 'La imagen no puede pesar más de 2 MB.',
        'pim_visible.boolean' => 'El valor de "Visible" no es válido.',
        'imagenes.required' => 'Envíe el nuevo orden de las imágenes.',
        'imagenes.array' => 'El orden de las imágenes no es válido.',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Las imágenes de un producto en su orden, con su dirección completa.
    // GET ?pro_id=   → {registros: [{pim_id, pro_id, pim_imagen, url, pim_orden, pim_visible, ...}], total, maximo}
    public function listar(Request $request)
    {
        try {
            $proId = (int) $request->query('pro_id', 0);
            if ($proId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Producto no válido', null));
            }

            $registros = DB::select('SELECT * FROM inventarios.fn_producto_imagen_listar(?)', [$proId]);
            foreach ($registros as $r) {
                $r->url = $this->url($r->pro_id, $r->pim_imagen);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Imágenes listadas con éxito', [
                'registros' => $registros,
                'total' => count($registros),
                'maximo' => (int) DB::selectOne('SELECT inventarios.fn_producto_imagen_maximo() AS m')->m,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las imágenes', $th->getMessage()));
        }
    }

    // Sube una imagen y la agrega al final -> inventarios.fn_producto_imagen_add.
    // multipart: { pro_id, imagen (WebP, máx. 2 MB), pim_visible? }   → {pim_id, url}
    public function subir(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pro_id' => 'required|integer|min:1',
            'imagen' => 'required|file|mimetypes:image/webp|max:' . self::IMAGEN_MAXIMO_KB,
            'pim_visible' => 'nullable|boolean',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $proId = (int) $request->input('pro_id');
        $nombre = $this->guardarArchivo($request, $proId);
        if ($nombre === null) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo guardar la imagen.', null));
        }

        try {
            $payload = ['pro_id' => $proId, 'pim_imagen' => $nombre];
            if ($request->has('pim_visible')) {
                $payload['pim_visible'] = $request->boolean('pim_visible');
            }
            $resultado = DB::selectOne('SELECT inventarios.fn_producto_imagen_add(?::jsonb) AS pim_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen agregada con éxito', [
                'pim_id' => $resultado->pim_id,
                'url' => $this->url($proId, $nombre),
            ]));
        } catch (QueryException $e) {
            // No quedó en la base (por ejemplo, ya tenía 10): el archivo se borra.
            $this->borrarArchivo($proId, $nombre);

            return $this->errorTraducido($e, 'Error al agregar la imagen');
        }
    }

    // Cambia si se ve y/o reemplaza la foto -> inventarios.fn_producto_imagen_edit.
    // multipart o JSON: { pim_visible?, imagen? (WebP, máx. 2 MB: reemplaza la foto; la anterior se borra) }.
    public function editar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'imagen' => 'sometimes|file|mimetypes:image/webp|max:' . self::IMAGEN_MAXIMO_KB,
            'pim_visible' => 'sometimes|boolean',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $actual = DB::selectOne('SELECT pro_id, pim_imagen FROM inventarios.producto_imagenes WHERE pim_id = ?', [$id]);
        if (!$actual) {
            return response()->json(RespuestaApi::returnResultado('error', self::MENSAJES_ERROR['IMAGEN_NO_EXISTE'], null));
        }

        $payload = ['pim_id' => $id];
        if ($request->has('pim_visible')) {
            $payload['pim_visible'] = $request->boolean('pim_visible');
        }
        $nombre = null;
        if ($request->hasFile('imagen')) {
            $nombre = $this->guardarArchivo($request, (int) $actual->pro_id);
            if ($nombre === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se pudo guardar la imagen.', null));
            }
            $payload['pim_imagen'] = $nombre;
        }

        try {
            $resultado = DB::selectOne('SELECT inventarios.fn_producto_imagen_edit(?::jsonb) AS pim_id', [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])]);
            // La foto se reemplazó: la anterior ya no se usa.
            if ($nombre !== null) {
                $this->borrarArchivo((int) $actual->pro_id, $actual->pim_imagen);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen guardada con éxito', ['pim_id' => $resultado->pim_id]));
        } catch (QueryException $e) {
            if ($nombre !== null) {
                $this->borrarArchivo((int) $actual->pro_id, $nombre);
            }

            return $this->errorTraducido($e, 'Error al guardar la imagen');
        }
    }

    // Nuevo orden de TODAS las imágenes del producto (arrastrar y soltar) -> inventarios.fn_producto_imagenes_ordenar.
    // Body: { pro_id, imagenes: [pim_id, ...] }
    public function ordenar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pro_id' => 'required|integer|min:1',
            'imagenes' => 'present|array',
            'imagenes.*' => 'integer|min:1',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            DB::selectOne('SELECT inventarios.fn_producto_imagenes_ordenar(?::jsonb) AS cambios', [json_encode([
                'pro_id' => (int) $request->input('pro_id'),
                'imagenes' => array_values($request->input('imagenes', [])),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado', null));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al ordenar las imágenes');
        }
    }

    // Borra la imagen (sin papelera) -> inventarios.fn_producto_imagen_eliminar, y después su archivo.
    public function eliminar(Request $request, int $id)
    {
        try {
            $proId = DB::selectOne('SELECT pro_id FROM inventarios.producto_imagenes WHERE pim_id = ?', [$id])->pro_id ?? null;
            $nombre = DB::selectOne('SELECT inventarios.fn_producto_imagen_eliminar(?::jsonb) AS pim_imagen', [json_encode(['pim_id' => $id, 'auditoria' => $this->contextoAuditoriaForense($request)])])->pim_imagen;
            if ($proId !== null && $nombre) {
                $this->borrarArchivo((int) $proId, $nombre);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen eliminada', ['pim_id' => $id]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al eliminar la imagen');
        }
    }

    // Historial de las imágenes de UN producto. GET ?pro_id=&pagina=&tamanio=&busqueda=
    public function auditoria(Request $request)
    {
        try {
            $proId = (int) $request->query('pro_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($proId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Producto no válido', null));
            }

            $eventos = DB::select('SELECT * FROM inventarios.fn_producto_imagen_auditoria_listar_paginacion(?, ?, ?, ?)', [$proId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de las imágenes', $th->getMessage()));
        }
    }

    // -----------------------------------------------------------------

    // Guarda la imagen en ecommerce/productos/{pro_id}/ con un nombre de 40 letras y números + .webp.
    // El disco lo decide crm.parametro 'NAS': true = NAS (FTP, raíz /crm), false = local. Las carpetas que falten se
    // crean solas. Devuelve el nombre del archivo, o null si no se pudo guardar.
    private function guardarArchivo(Request $request, int $proId): ?string
    {
        // El disco (NAS o local) lo decide crm.parametro 'NAS' en ArchivosPaginaWeb::disco().
        $nombre = Str::random(40) . '.webp';

        return ArchivosPaginaWeb::guardar($request->file('imagen'), self::CARPETA . '/' . $proId, $nombre) ? $nombre : null;
    }

    // Borra un archivo de la carpeta del producto, del disco donde esté (si no se puede, solo queda en el log).
    private function borrarArchivo(int $proId, string $nombre): void
    {
        ArchivosPaginaWeb::borrarRuta(self::CARPETA . '/' . $proId . '/' . basename($nombre));
    }

    // Dirección pública de una imagen (la misma que arma la base con inventarios.fn_producto_imagen_ruta).
    private function url(int $proId, string $nombre): string
    {
        return url('api/public/pagina-web/imagenes/productos/' . $proId . '/' . $nombre);
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

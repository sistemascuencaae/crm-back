<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\LimpiadorDocumento;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Descripción completa de un producto (Ecommerce > Contenido de productos; antes en Inventarios > Productos): un documento con el
// editor de Documentos (títulos, colores, tablas, imágenes...) que la tienda muestra en el detalle del producto.
// Una por producto, un CRUD sencillo sin auditoría: ver, crear, editar, eliminar, copiar (al clonar) y subir imágenes.
// Las reglas viven en inventarios.fn_producto_descripcion_* (database/sql/producto_descripciones.sql,
// producto_descripciones_sin_auditoria.sql y producto_descripciones_add_edit.sql). El HTML se limpia
// aquí antes de guardarlo (LimpiadorDocumento, como Documentos) y las imágenes solo pueden ser de la carpeta del
// producto: storage/app/pagina-web/productos/{pro_id}/descripcion/, entregadas por
// api/public/pagina-web/imagenes/productos/{pro_id}/descripcion/{nombre} (paginaWeb/ArchivosTiendaController).
class ProductoDescripcionController extends Controller
{
    private const CARPETA = 'pagina-web/productos';
    private const TIPOS_IMAGEN = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];
    private const IMAGEN_MAXIMO_KB = 2048;
    // Las imágenes subidas que no quedaron en la descripción se borran al guardar, si tienen más de este tiempo
    // (las más nuevas pueden ser de alguien que todavía está escribiendo).
    private const HORAS_IMAGEN_SUELTA = 24;

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se identificó al usuario que realiza el cambio.',
        'PRODUCTO_NO_EXISTE' => 'El producto no existe.',
        'DESCRIPCION_LARGA' => 'La descripción es demasiado larga.',
        'DESCRIPCION_NO_PERMITIDA' => 'La descripción tiene contenido no permitido.',
        'REQUIERE_DESCRIPCION' => 'Escriba la descripción (para quitarla, use Eliminar).',
        'DESCRIPCION_DUPLICADA' => 'El producto ya tiene descripción completa (puede que otro usuario la creó). Vuelva a abrirla.',
        'DESCRIPCION_NO_EXISTE' => 'El producto ya no tiene descripción completa (puede que otro usuario la quitó). Vuelva a abrirla.',
        'ux_producto_descripciones_producto' => 'Otro usuario guardó la descripción al mismo tiempo. Vuelva a abrirla.',
    ];

    private const MENSAJES_FORMULARIO = [
        'pde_contenido.required' => 'Escriba la descripción.',
        'pde_contenido.string' => 'La descripción no es válida.',
        'pde_contenido.max' => 'La descripción es demasiado larga.',
        'pro_id_origen.required' => 'Falta el producto de origen.',
        'pro_id_origen.integer' => 'El producto de origen no es válido.',
        'imagen.required' => 'Elija una imagen.',
        'imagen.file' => 'Elija una imagen.',
        'imagen.mimetypes' => 'Solo se aceptan imágenes WebP, JPG o PNG.',
        'imagen.max' => 'La imagen no puede pesar más de 2 MB.',
    ];

    public function __construct(private LimpiadorDocumento $limpiador)
    {
        $this->middleware('auth:api');
    }

    // La descripción de un producto, con las imágenes con su dirección completa.
    // GET {id}  → {pro_id, pde_contenido ('' si no tiene), pde_fecha_creacion, pde_fecha_modificacion}
    public function ver(int $id)
    {
        try {
            $d = json_decode(DB::selectOne('SELECT inventarios.fn_producto_descripcion_buscar(?) AS d', [$id])->d ?? 'null', true);

            return response()->json(RespuestaApi::returnResultado('success', 'Descripción cargada', [
                'pro_id' => $id,
                'pde_contenido' => $this->conImagenesCompletas($d['pde_contenido'] ?? ''),
                'pde_fecha_creacion' => $d['pde_fecha_creacion'] ?? null,
                'pde_fecha_modificacion' => $d['pde_fecha_modificacion'] ?? null,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la descripción', $th->getMessage()));
        }
    }

    // Crea la descripción del producto (no tenía) -> inventarios.fn_producto_descripcion_add. Body: { pde_contenido }
    public function crear(Request $request, int $id)
    {
        return $this->escribir($request, $id, 'fn_producto_descripcion_add', 'Descripción creada con éxito', 'Error al crear la descripción');
    }

    // Cambia la descripción del producto -> inventarios.fn_producto_descripcion_edit. Body: { pde_contenido }
    public function editar(Request $request, int $id)
    {
        return $this->escribir($request, $id, 'fn_producto_descripcion_edit', 'Descripción guardada con éxito', 'Error al guardar la descripción');
    }

    // Quita la descripción del producto -> inventarios.fn_producto_descripcion_eliminar. La tienda vuelve a mostrar la
    // descripción corta del ERP. Sus imágenes se borran después (borrarImagenesSueltas).
    public function eliminar(Request $request, int $id)
    {
        try {
            $pdeId = DB::selectOne('SELECT inventarios.fn_producto_descripcion_eliminar(?::jsonb) AS pde_id', [json_encode([
                'pro_id' => $id,
                'auditoria' => $this->usuario(),
            ])])->pde_id;

            $this->borrarImagenesSueltas($id, '');

            return response()->json(RespuestaApi::returnResultado('success', 'Descripción eliminada', ['pde_id' => $pdeId]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al eliminar la descripción');
        }
    }

    // Copia la descripción de otro producto (al clonar) -> inventarios.fn_producto_descripcion_add.
    // Body: { pro_id_origen, pde_contenido? }. Sin pde_contenido se copia la del origen tal cual; con él (la del origen
    // cambiada antes de guardar la copia), esa. Las imágenes del origen que use se COPIAN a la carpeta de este producto
    // con nombre nuevo y el HTML se cambia para apuntar a ellas: cada producto queda con las suyas (borrar o cambiar
    // una no toca a la otra). Si el origen no tiene descripción y no se manda otra, no hace nada.
    public function copiar(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'pro_id_origen' => 'required|integer|min:1',
            'pde_contenido' => 'sometimes|nullable|string|max:500000',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        $origen = (int) $request->input('pro_id_origen');
        if ($request->has('pde_contenido')) {
            $html = (string) $request->input('pde_contenido', '');
        } else {
            $d = json_decode(DB::selectOne('SELECT inventarios.fn_producto_descripcion_buscar(?) AS d', [$origen])->d ?? 'null', true);
            $html = (string) ($d['pde_contenido'] ?? '');
        }
        if (trim($html) === '') {
            return response()->json(RespuestaApi::returnResultado('success', 'El producto de origen no tiene descripción completa', ['pde_id' => null]));
        }

        // Las imágenes del origen (con la dirección completa o relativa) -> una copia en la carpeta de este producto.
        $copiadas = [];
        $patron = '#(?:https?://[^/\s"\']+)?' . preg_quote($this->rutaRelativa($origen), '#') . '([A-Za-z0-9]{40}\.(webp|jpg|png))#';
        $html = preg_replace_callback($patron, function ($m) use ($origen, $id, &$copiadas) {
            if (!isset($copiadas[$m[1]])) {
                $nuevo = Str::random(40) . '.' . $m[2];
                try {
                    if (!Storage::disk('local')->copy($this->carpeta($origen) . '/' . $m[1], $this->carpeta($id) . '/' . $nuevo)) {
                        return $m[0];   // no se pudo copiar: queda la del origen y el limpiador la quita
                    }
                } catch (\Throwable $th) {
                    Log::warning('No se pudo copiar una imagen de la descripción', ['origen' => $origen, 'pro_id' => $id, 'archivo' => $m[1], 'error' => $th->getMessage()]);
                    return $m[0];
                }
                $copiadas[$m[1]] = $nuevo;
            }

            return $this->rutaRelativa($id) . $copiadas[$m[1]];
        }, $html);

        $contenido = $this->limpiador->limpiar($html, $this->rutaRelativa($id));
        if ($contenido === '') {
            return response()->json(RespuestaApi::returnResultado('success', 'El producto de origen no tiene descripción completa', ['pde_id' => null]));
        }

        try {
            $pdeId = DB::selectOne('SELECT inventarios.fn_producto_descripcion_add(?::jsonb) AS pde_id', [json_encode([
                'pro_id' => $id,
                'pde_contenido' => $contenido,
                'auditoria' => $this->usuario(),
            ])])->pde_id;

            return response()->json(RespuestaApi::returnResultado('success', 'Descripción copiada con éxito', ['pde_id' => $pdeId]));
        } catch (QueryException $e) {
            // No quedó en la base: las copias de las imágenes se borran.
            foreach ($copiadas as $nuevo) {
                Storage::disk('local')->delete($this->carpeta($id) . '/' . $nuevo);
            }

            return $this->errorTraducido($e, 'Error al copiar la descripción');
        }
    }

    // Sube una imagen para la descripción: WebP, JPG o PNG de verdad (mimetypes mira el contenido), hasta 2 MB.
    // multipart: { imagen }  → {nombre, url}. Queda en la descripción cuando se guarda.
    public function subirImagen(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'imagen' => 'required|file|mimetypes:image/webp,image/jpeg,image/png|max:' . self::IMAGEN_MAXIMO_KB,
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }
        // Antes de guardar el archivo: el usuario y el producto, con las validaciones de la base.
        try {
            DB::selectOne('SELECT inventarios.fn_producto_descripcion_validaciones(?::jsonb) AS v', [json_encode([
                'pro_id' => $id,
                'solo_producto' => true,
                'auditoria' => $this->usuario(),
            ])]);
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'No se pudo subir la imagen.');
        }

        try {
            $extension = array_search($request->file('imagen')->getMimeType(), self::TIPOS_IMAGEN, true) ?: 'webp';
            $nombre = Str::random(40) . '.' . $extension;
            $request->file('imagen')->storeAs($this->carpeta($id), $nombre, 'local');

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen subida', [
                'nombre' => $nombre,
                'url' => url(ltrim($this->rutaRelativa($id), '/') . $nombre),
            ]));
        } catch (\Throwable $th) {
            Log::error('No se pudo subir la imagen de la descripción', ['pro_id' => $id, 'error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir la imagen.', null));
        }
    }

    // -----------------------------------------------------------------

    // Crear y editar: valida, limpia el HTML (imágenes solo de la carpeta del producto) y llama a la función PG.
    private function escribir(Request $request, int $id, string $funcion, string $exito, string $porDefecto)
    {
        $validator = Validator::make($request->all(), [
            'pde_contenido' => 'required|string|max:500000',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        $contenido = $this->limpiador->limpiar((string) $request->input('pde_contenido'), $this->rutaRelativa($id));

        try {
            $pdeId = DB::selectOne("SELECT inventarios.$funcion(?::jsonb) AS pde_id", [json_encode([
                'pro_id' => $id,
                'pde_contenido' => $contenido,
                'auditoria' => $this->usuario(),
            ])])->pde_id;

            $this->borrarImagenesSueltas($id, $contenido);

            return response()->json(RespuestaApi::returnResultado('success', $exito, [
                'pde_id' => $pdeId,
                'pde_contenido' => $this->conImagenesCompletas($contenido),
            ]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, $porDefecto);
        }
    }

    private function carpeta(int $proId): string
    {
        return self::CARPETA . '/' . $proId . '/descripcion';
    }

    // Dirección relativa de las imágenes de la descripción (así se guardan en el HTML).
    private function rutaRelativa(int $proId): string
    {
        return '/api/public/pagina-web/imagenes/productos/' . $proId . '/descripcion/';
    }

    // Al entregar el HTML, las imágenes con la dirección de este servidor (la tienda está en otro).
    private function conImagenesCompletas(?string $html): string
    {
        return str_replace('src="/api/public/pagina-web/imagenes/productos/', 'src="' . url('api/public/pagina-web/imagenes/productos') . '/', (string) $html);
    }

    // Borra las imágenes de la carpeta de la descripción que ya no están en el HTML guardado (y no son recientes).
    private function borrarImagenesSueltas(int $proId, string $contenido): void
    {
        try {
            $limite = time() - self::HORAS_IMAGEN_SUELTA * 3600;
            foreach (Storage::disk('local')->files($this->carpeta($proId)) as $archivo) {
                $nombre = basename($archivo);
                if (!str_contains($contenido, '/' . $nombre) && Storage::disk('local')->lastModified($archivo) < $limite) {
                    Storage::disk('local')->delete($archivo);
                }
            }
        } catch (\Throwable $th) {
            Log::warning('No se pudieron limpiar las imágenes de la descripción', ['pro_id' => $proId, 'error' => $th->getMessage()]);
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

    // Quién hace el cambio (la base lo guarda en usu_id; no hay auditoría).
    private function usuario(): array
    {
        return ['usuario_id' => auth('api')->user()->id ?? null];
    }
}

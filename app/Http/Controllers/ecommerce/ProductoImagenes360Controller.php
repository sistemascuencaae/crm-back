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

// Vista 360° de un producto (Ecommerce > Contenido de productos): la serie de fotos del producto girando (las que salgan
// de la cámara; se recomiendan 36). Los cuadros se suben de uno en uno (subir) y después se guarda la serie completa en
// su orden (guardar); también mostrar u ocultar, quitar, descartar lo subido sin guardar y auditoría.
// Las reglas viven en inventarios.fn_producto_imagen_360_* (database/sql/producto_imagenes_360.sql). La base guarda solo
// el nombre del archivo; el archivo va en ecommerce/productos/{pro_id}/360/ (NAS o local) y se entrega por
// api/public/pagina-web/imagenes/productos/{pro_id}/360/{nombre} (paginaWeb/ArchivosTiendaController::verImagenProducto360).
class ProductoImagenes360Controller extends Controller
{
    private const CARPETA = ArchivosPaginaWeb::CARPETA . '/productos';
    private const IMAGEN_MAXIMO_KB = 2048;
    // Ancho y alto máximos de cada cuadro (px).
    private const IMAGEN_MAXIMO_PX = 1200;
    // Cuántos cuadros se recomiendan (uno cada 10°); no es un límite.
    private const RECOMENDADOS = 36;

    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'REQUIERE_USUARIO' => 'No se identificó al usuario que realiza el cambio.',
        'PRODUCTO_NO_EXISTE' => 'El producto no existe.',
        'REQUIERE_IMAGENES' => 'Suba las fotos de la vista 360°.',
        'IMAGENES_POCAS' => 'La vista 360° necesita al menos 2 fotos (se recomiendan 36).',
        'IMAGEN_INVALIDA' => 'Una de las fotos no es válida: súbalas de nuevo.',
        'IMAGEN_REPETIDA' => 'Una foto está repetida en la serie.',
        'VISIBLE_INVALIDO' => 'El valor de "Visible" no es válido.',
        'VISTA_NO_EXISTE' => 'El producto no tiene vista 360° (puede que otro usuario la quitó).',
        'ux_producto_vistas_360_producto' => 'Otro usuario guardó la vista 360° de este producto al mismo tiempo. Vuelva a cargarla.',
    ];

    private const MENSAJES_FORMULARIO = [
        'pro_id.required' => 'Falta el producto.',
        'pro_id.integer' => 'El producto no es válido.',
        'imagen.required' => 'Elija una foto.',
        'imagen.file' => 'Elija una foto.',
        'imagen.mimetypes' => 'Solo se aceptan fotos WebP.',
        'imagen.max' => 'Cada foto puede pesar hasta 2 MB.',
        'imagenes.required' => 'Suba las fotos de la vista 360°.',
        'imagenes.array' => 'La serie de fotos no es válida.',
        'pv3_visible.boolean' => 'El valor de "Visible" no es válido.',
        'pv3_visible.required' => 'Indique si se muestra.',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // La vista 360° del producto (null si no tiene), con la dirección de cada cuadro, y las recomendaciones.
    // GET ?pro_id=   → { vista: { pv3_id, pro_id, pv3_visible, ..., imagenes: [{pi3_id, pi3_imagen, pi3_orden, url}] } | null,
    //                    recomendados, maximo_px }
    public function ver(Request $request)
    {
        try {
            $proId = (int) $request->query('pro_id', 0);
            if ($proId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Producto no válido', null));
            }

            $fila = DB::selectOne('SELECT inventarios.fn_producto_imagen_360_buscar(?) AS vista', [$proId]);
            $vista = $fila && $fila->vista ? json_decode($fila->vista, true) : null;
            if ($vista) {
                foreach ($vista['imagenes'] as $i => $imagen) {
                    $vista['imagenes'][$i]['url'] = $this->url($proId, $imagen['pi3_imagen']);
                }
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Vista 360° cargada', [
                'vista' => $vista,
                'recomendados' => self::RECOMENDADOS,
                'maximo_px' => self::IMAGEN_MAXIMO_PX,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la vista 360°', $th->getMessage()));
        }
    }

    // Sube UN cuadro (todavía no queda en la vista: se guarda con guardar). multipart: { pro_id, imagen (WebP, hasta 2 MB
    // y 1200 px) }   → { nombre, url }
    public function subir(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pro_id' => 'required|integer|min:1',
            'imagen' => 'required|file|mimetypes:image/webp|max:' . self::IMAGEN_MAXIMO_KB,
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        // Hasta 1200 px de ancho y de alto (más grande pesa sin verse mejor en el visor).
        $medidas = @getimagesize($request->file('imagen')->getRealPath());
        if (!$medidas) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo leer la foto: súbala de nuevo.', null));
        }
        if ($medidas[0] > self::IMAGEN_MAXIMO_PX || $medidas[1] > self::IMAGEN_MAXIMO_PX) {
            return response()->json(RespuestaApi::returnResultado('error',
                'Cada foto puede medir hasta ' . self::IMAGEN_MAXIMO_PX . ' px de ancho y de alto (esta mide ' . $medidas[0] . ' × ' . $medidas[1] . ' px).', null));
        }

        $proId = (int) $request->input('pro_id');
        // El disco (NAS o local) lo decide crm.parametro 'NAS' en ArchivosPaginaWeb::disco().
        $nombre = Str::random(40) . '.webp';
        if (!ArchivosPaginaWeb::guardar($request->file('imagen'), $this->carpeta($proId), $nombre)) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo guardar la foto.', null));
        }

        return response()->json(RespuestaApi::returnResultado('success', 'Foto subida', [
            'nombre' => $nombre,
            'url' => $this->url($proId, $nombre),
            'ancho' => $medidas[0],
            'alto' => $medidas[1],
        ]));
    }

    // Guarda la serie completa en su orden (la crea o reemplaza la anterior) -> inventarios.fn_producto_imagen_360_guardar.
    // Body: { pro_id, imagenes: [nombre…], pv3_visible? }. Los archivos de la serie anterior que ya no se usan se borran.
    public function guardar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pro_id' => 'required|integer|min:1',
            'imagenes' => 'required|array',
            'imagenes.*' => 'string|max:60',
            'pv3_visible' => 'sometimes|boolean',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        $proId = (int) $request->input('pro_id');
        $payload = ['pro_id' => $proId, 'imagenes' => array_values($request->input('imagenes', []))];
        if ($request->has('pv3_visible')) {
            $payload['pv3_visible'] = $request->boolean('pv3_visible');
        }

        try {
            $resultado = json_decode(DB::selectOne('SELECT inventarios.fn_producto_imagen_360_guardar(?::jsonb) AS r',
                [json_encode($payload + ['auditoria' => $this->contextoAuditoriaForense($request)])])->r, true);
            $this->borrarArchivos($proId, $resultado['quitadas'] ?? []);

            return response()->json(RespuestaApi::returnResultado('success', 'Vista 360° guardada con éxito', ['pv3_id' => $resultado['pv3_id']]));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al guardar la vista 360°');
        }
    }

    // Mostrar u ocultar la vista 360° en la tienda -> inventarios.fn_producto_imagen_360_visible. Body: { pro_id, pv3_visible }
    public function visible(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pro_id' => 'required|integer|min:1',
            'pv3_visible' => 'required|boolean',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            DB::selectOne('SELECT inventarios.fn_producto_imagen_360_visible(?::jsonb) AS pv3_id', [json_encode([
                'pro_id' => (int) $request->input('pro_id'),
                'pv3_visible' => $request->boolean('pv3_visible'),
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            return response()->json(RespuestaApi::returnResultado('success',
                $request->boolean('pv3_visible') ? 'La vista 360° se muestra' : 'La vista 360° quedó oculta', null));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al cambiar la vista 360°');
        }
    }

    // Quita la vista 360° con todos sus cuadros (sin papelera) -> inventarios.fn_producto_imagen_360_eliminar; después
    // borra los archivos.
    public function eliminar(Request $request, int $id)
    {
        try {
            $nombres = json_decode(DB::selectOne('SELECT inventarios.fn_producto_imagen_360_eliminar(?::jsonb) AS r',
                [json_encode(['pro_id' => $id, 'auditoria' => $this->contextoAuditoriaForense($request)])])->r, true);
            $this->borrarArchivos($id, $nombres ?? []);

            return response()->json(RespuestaApi::returnResultado('success', 'Vista 360° quitada', null));
        } catch (QueryException $e) {
            return $this->errorTraducido($e, 'Error al quitar la vista 360°');
        }
    }

    // Borra fotos subidas que no se guardaron (al descartar en el CRM). La base dice cuáles de esos nombres no usa la vista
    // del producto (inventarios.fn_producto_imagen_360_sin_usar): solo esas se borran. Body: { pro_id, imagenes: [nombre…] }
    public function descartar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pro_id' => 'required|integer|min:1',
            'imagenes' => 'required|array',
            'imagenes.*' => 'string|max:60',
        ], self::MENSAJES_FORMULARIO);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $proId = (int) $request->input('pro_id');
            $sinUsar = json_decode(DB::selectOne('SELECT inventarios.fn_producto_imagen_360_sin_usar(?, ?::jsonb) AS r',
                [$proId, json_encode(array_values($request->input('imagenes', [])))])->r, true);
            $this->borrarArchivos($proId, $sinUsar ?? []);

            return response()->json(RespuestaApi::returnResultado('success', 'Fotos descartadas', ['borradas' => count($sinUsar ?? [])]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron descartar las fotos', $th->getMessage()));
        }
    }

    // Historial de la vista 360° de UN producto. GET ?pro_id=&pagina=&tamanio=&busqueda=
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

            $eventos = DB::select('SELECT * FROM inventarios.fn_producto_imagen_360_auditoria_listar_paginacion(?, ?, ?, ?)', [$proId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la vista 360°', $th->getMessage()));
        }
    }

    // -----------------------------------------------------------------

    private function carpeta(int $proId): string
    {
        return self::CARPETA . '/' . $proId . '/360';
    }

    // Borra archivos de la carpeta 360 del producto (si no se puede, solo queda en el log).
    private function borrarArchivos(int $proId, array $nombres): void
    {
        foreach ($nombres as $nombre) {
            if (is_string($nombre) && preg_match('/^[A-Za-z0-9]{40}\.webp$/', $nombre)) {
                ArchivosPaginaWeb::borrarRuta($this->carpeta($proId) . '/' . $nombre);
            }
        }
    }

    // Dirección pública de un cuadro.
    private function url(int $proId, string $nombre): string
    {
        return url('api/public/pagina-web/imagenes/productos/' . $proId . '/360/' . $nombre);
    }

    // errorInfo[2] trae solo el mensaje de PG (getMessage() incluye el SQL con el payload).
    private function errorTraducido(QueryException $e, string $porDefecto)
    {
        $texto = strtok((string) ($e->errorInfo[2] ?? $e->getMessage()), "\n");

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

<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Archivos de la tienda (los suben las pantallas de Ecommerce y la tienda los pide por api/public/...).
 *
 * Dónde se guardan: lo decide crm.parametro 'NAS' (como el resto del CRM).
 *   - nas = true:  disco 'nas'   (FTP, raíz NAS_FTP_ROOT del .env, hoy /crm)
 *   - nas = false: disco 'local' (storage/app/public/crm)
 * Dentro del disco todo va en la carpeta ecommerce (CARPETA), cada cosa en la suya:
 *   ecommerce/productos/{pro_id}               fotos del producto        (ecommerce/ProductoImagenesController)
 *   ecommerce/productos/{pro_id}/descripcion   imágenes de la descripción (ecommerce/ProductoDescripcionController)
 *   ecommerce/marcas                           logos de las marcas       (inventarios/MarcaController)
 *   ecommerce/secciones                        imágenes de las secciones (ecommerce/SeccionesController)
 *   ecommerce/headers | ecommerce/footers      imágenes del header y del footer
 *   ecommerce/documentos/imagenes | /pdf       documentos
 *   ecommerce/configuracion                    ventana emergente y video de intro
 *   ecommerce/archivos                         los que se subían con el módulo Página web anterior
 * Las carpetas que falten se crean solas. Las de antes (pagina-web, secciones, headers...) se pasan aquí con
 * php artisan tienda:ordenar-archivos.
 *
 * Dónde se buscan: en el disco activo; con el NAS activo, si no está ahí, también en local (los archivos que se
 * subieron antes de pasar al NAS siguen sirviendo). Con local activo no se consulta el NAS.
 */
class ArchivosPaginaWeb
{
    public const CARPETA = 'ecommerce';

    // Dónde estaban antes -> dónde van (tienda:ordenar-archivos). 'pagina-web/' = solo los archivos sueltos de esa
    // carpeta (sus subcarpetas productos y marcas tienen su propia línea).
    public const CARPETAS_ANTERIORES = [
        'pagina-web/productos'     => self::CARPETA . '/productos',
        'pagina-web/marcas'        => self::CARPETA . '/marcas',
        'pagina-web/'              => self::CARPETA . '/archivos',
        'secciones'                => self::CARPETA . '/secciones',
        'headers'                  => self::CARPETA . '/headers',
        'footers'                  => self::CARPETA . '/footers',
        'documentos/imagenes'      => self::CARPETA . '/documentos/imagenes',
        'documentos/pdf'           => self::CARPETA . '/documentos/pdf',
        'configuracion-tienda'     => self::CARPETA . '/configuracion',
    ];

    // Toda imagen que se sube desde el módulo: solo WebP (mimetypes mira el tipo real del contenido, no la extensión;
    // un PNG renombrado a .webp no pasa) y hasta 2 MB. Las JPG y PNG que ya estaban guardadas siguen sirviendo.
    private const REGLA_IMAGEN = 'required|file|mimetypes:image/webp|max:2048';

    // Cabeceras de todo archivo que se entrega: el nombre es aleatorio y nunca cambia de contenido (un año en caché).
    private const CABECERAS = [
        'Cache-Control'          => 'public, max-age=31536000, immutable',
        'X-Content-Type-Options' => 'nosniff',
    ];

    // El disco activo se lee una vez por petición.
    private static ?string $discoActivo = null;

    /** Revisa la imagen del campo $campo: el mensaje del error, o null si está bien. */
    public static function errorImagen(Request $request, string $campo): ?string
    {
        $validator = Validator::make($request->all(), [$campo => self::REGLA_IMAGEN], [
            $campo . '.required'  => 'Elige una imagen.',
            $campo . '.file'      => 'Elige una imagen.',
            $campo . '.mimetypes' => 'Solo se aceptan imágenes WebP.',
            $campo . '.max'       => 'La imagen no puede pesar más de 2 MB.',
        ]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }

    // -----------------------------------------------------------------
    // Disco

    /** 'nas' o 'local' según crm.parametro 'NAS'. Ahí se guardan los archivos nuevos. */
    public static function disco(): string
    {
        if (self::$discoActivo === null) {
            $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'NAS')
                ->first();

            self::$discoActivo = ($parametro && $parametro->nas == true) ? 'nas' : 'local';
        }

        return self::$discoActivo;
    }

    /** Discos donde se busca un archivo, en orden: el activo y, si es el NAS, también local. */
    public static function discosDeLectura(): array
    {
        return self::disco() === 'nas' ? ['nas', 'local'] : ['local'];
    }

    /** El disco donde está el archivo, o null si no está en ninguno. */
    public static function discoDe(string $ruta): ?string
    {
        foreach (self::discosDeLectura() as $disco) {
            try {
                if (Storage::disk($disco)->exists($ruta)) {
                    return $disco;
                }
            } catch (\Throwable $th) {
                Log::warning('No se pudo revisar el archivo de la tienda', ['disco' => $disco, 'ruta' => $ruta, 'error' => $th->getMessage()]);
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    // Guardar, entregar, copiar y borrar

    /** Guarda el archivo subido en $carpeta/$nombre del disco activo. false si no se pudo (queda en el log). */
    public static function guardar(UploadedFile $archivo, string $carpeta, string $nombre): bool
    {
        $disco = self::disco();
        try {
            // putFileAs devuelve false si no pudo escribir (no siempre lanza una excepción).
            if (Storage::disk($disco)->putFileAs($carpeta, $archivo, $nombre) !== false) {
                return true;
            }
            Log::error('No se pudo guardar el archivo de la tienda', ['disco' => $disco, 'carpeta' => $carpeta]);
        } catch (\Throwable $th) {
            Log::error('No se pudo guardar el archivo de la tienda', ['disco' => $disco, 'carpeta' => $carpeta, 'error' => $th->getMessage()]);
        }

        return false;
    }

    /**
     * Respuesta con el archivo (404 si no está). $tipo = Content-Type; $cabeceras se suman o reemplazan las de siempre.
     * Desde local se entrega con soporte de rangos (el video empieza sin descargarse entero); desde el NAS se lee y se
     * envía (sin rangos).
     */
    public static function entregar(string $ruta, string $tipo, array $cabeceras = [])
    {
        $disco = self::discoDe($ruta);
        if ($disco === null) {
            abort(404);
        }

        $cabeceras = array_merge(self::CABECERAS, ['Content-Type' => $tipo], $cabeceras);

        if ($disco === 'local') {
            return response()->file(Storage::disk('local')->path($ruta), $cabeceras);
        }

        return response()->stream(function () use ($ruta) {
            $flujo = Storage::disk('nas')->readStream($ruta);
            if (is_resource($flujo)) {
                fpassthru($flujo);
                fclose($flujo);
            }
        }, 200, $cabeceras);
    }

    /** Copia un archivo (esté donde esté) a $destino en el disco activo. false si no se pudo. */
    public static function copiar(string $origen, string $destino): bool
    {
        $discoOrigen = self::discoDe($origen);
        if ($discoOrigen === null) {
            return false;
        }

        $discoDestino = self::disco();
        if ($discoOrigen === $discoDestino) {
            return Storage::disk($discoDestino)->copy($origen, $destino);
        }

        $flujo = Storage::disk($discoOrigen)->readStream($origen);
        if (!is_resource($flujo)) {
            return false;
        }
        try {
            return Storage::disk($discoDestino)->writeStream($destino, $flujo) !== false;
        } finally {
            if (is_resource($flujo)) {
                fclose($flujo);
            }
        }
    }

    /** Borra un archivo del disco donde esté. Si no se puede, queda en el log y sigue. */
    public static function borrarRuta(string $ruta): void
    {
        $disco = self::discoDe($ruta);
        if ($disco === null) {
            return;
        }

        try {
            if (!Storage::disk($disco)->delete($ruta)) {
                Log::warning('No se pudo borrar el archivo de la tienda', ['disco' => $disco, 'ruta' => $ruta]);
            }
        } catch (\Throwable $th) {
            Log::error('No se pudo borrar el archivo de la tienda', ['disco' => $disco, 'ruta' => $ruta, 'error' => $th->getMessage()]);
        }
    }

    // -----------------------------------------------------------------
    // Direcciones de la carpeta pagina-web

    // Lo que viene después de .../api/public/pagina-web/imagenes/ en una dirección nuestra:
    // abc.webp, productos/15/abc.webp o marcas/abc.png (nombre aleatorio de Laravel, sin / ni ..)
    private const RUTA_PUBLICA = '#/api/public/pagina-web/imagenes/((?:productos/\d+/|marcas/)?)([A-Za-z0-9]+\.(?:jpg|jpeg|png|webp))$#';

    /** La ruta del archivo dentro del disco para una dirección nuestra; null si es de afuera o no tiene esa forma. */
    public static function rutaLocal(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (!preg_match(self::RUTA_PUBLICA, $path, $m)) {
            return null;
        }

        // Sin subcarpeta = un archivo del módulo Página web anterior (carpeta archivos).
        return self::CARPETA . '/' . ($m[1] === '' ? 'archivos/' : $m[1]) . $m[2];
    }

    /** Borra los archivos nuestros de esas direcciones. Si uno no se puede borrar, queda en el log y sigue. */
    public static function borrar(array $urls): void
    {
        foreach (array_unique($urls) as $url) {
            $ruta = self::rutaLocal((string) $url);
            if ($ruta !== null) {
                self::borrarRuta($ruta);
            }
        }
    }
}

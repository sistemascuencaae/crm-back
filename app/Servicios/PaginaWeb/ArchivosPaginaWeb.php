<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Archivos que sube Página Web (ArchivoPaginaWebController): se guardan en storage/app/pagina-web (carpeta privada)
 * y la tienda los pide por api/public/pagina-web/imagenes/…. Aquí está la única regla para subir una imagen y aquí
 * se borran los archivos al vaciar una papelera.
 */
class ArchivosPaginaWeb
{
    public const CARPETA = 'pagina-web';

    // Toda imagen que se sube desde el módulo: solo WebP (mimetypes mira el tipo real del contenido, no la extensión;
    // un PNG renombrado a .webp no pasa) y hasta 2 MB. Las JPG y PNG que ya estaban guardadas siguen sirviendo.
    private const REGLA_IMAGEN = 'required|file|mimetypes:image/webp|max:2048';

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

    // Lo que viene después de .../api/public/pagina-web/imagenes/ en una dirección nuestra:
    // abc.webp, productos/15/abc.webp o marcas/abc.png (nombre aleatorio de Laravel, sin / ni ..)
    private const RUTA_PUBLICA = '#/api/public/pagina-web/imagenes/((?:productos/\d+/|marcas/)?[A-Za-z0-9]+\.(?:jpg|jpeg|png|webp))$#';

    /** La ruta del archivo en el disco 'local' para una dirección nuestra; null si es de afuera o no tiene esa forma. */
    public static function rutaLocal(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match(self::RUTA_PUBLICA, $path, $m) ? self::CARPETA . '/' . $m[1] : null;
    }

    /** Borra los archivos nuestros de esas direcciones. Si uno no se puede borrar, queda en el log y sigue. */
    public static function borrar(array $urls): void
    {
        foreach (array_unique($urls) as $url) {
            $ruta = self::rutaLocal((string) $url);
            if ($ruta === null) {
                continue;
            }

            try {
                if (Storage::disk('local')->exists($ruta) && !Storage::disk('local')->delete($ruta)) {
                    Log::warning('No se pudo borrar el archivo de página web', ['ruta' => $ruta]);
                }
            } catch (\Throwable $th) {
                Log::error('No se pudo borrar el archivo de página web', ['ruta' => $ruta, 'error' => $th->getMessage()]);
            }
        }
    }
}

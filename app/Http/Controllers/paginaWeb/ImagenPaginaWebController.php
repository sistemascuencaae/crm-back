<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Imágenes de las páginas de la tienda (Garantía, etc.).
 *
 * subir(): CRM, con sesión. Guarda el archivo en storage/app/pagina-web/ (carpeta privada)
 *          y devuelve la URL con la que la tienda lo pide.
 * ver():   pública. Entrega una imagen de esa carpeta y nada más.
 *
 * No se usa storage:link: haría pública toda la carpeta storage/app/public,
 * incluidos los archivos del File Manager.
 * La base no guarda el archivo: el CRM pone la URL en el campo "imagen" de la página.
 */
class ImagenPaginaWebController extends Controller
{
    private const CARPETA = 'pagina-web';

    // Nombre que genera Laravel (aleatorio) + extensión permitida. Evita pedir otros archivos (../.env)
    private const NOMBRE_VALIDO = '/^[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$/';

    public function subir(Request $request)
    {
        // mimes revisa el contenido real del archivo, no solo su nombre. Sin SVG: puede llevar código.
        $validator = Validator::make($request->all(), [
            'imagen' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'imagen.required' => 'Elige una imagen.',
            'imagen.file'     => 'Elige una imagen.',
            'imagen.mimes'    => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max'      => 'La imagen no puede pesar más de 2 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        try {
            // Nombre aleatorio y extensión según el contenido. Disco 'local' = storage/app (privado)
            $ruta = $request->file('imagen')->store(self::CARPETA, 'local');
            $nombre = basename($ruta);

            return response()->json(RespuestaApi::returnResultado('success', 'Imagen subida', [
                'url' => url('api/public/pagina-web/imagenes/' . $nombre),
            ]));
        } catch (\Throwable $th) {
            Log::error('No se pudo subir la imagen de página web', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir la imagen.', null));
        }
    }

    public function ver(string $nombre)
    {
        $ruta = self::CARPETA . '/' . $nombre;

        if (!preg_match(self::NOMBRE_VALIDO, $nombre) || !Storage::disk('local')->exists($ruta)) {
            abort(404);
        }

        // El nombre es aleatorio y nunca cambia de contenido: el navegador puede guardarla un año
        return response()->file(Storage::disk('local')->path($ruta), [
            'Cache-Control'          => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
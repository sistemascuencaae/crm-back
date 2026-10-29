<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ArchivoPaginaWebController extends Controller
{
    private const CARPETA = ArchivosPaginaWeb::CARPETA;

    // Nombre que genera Laravel (aleatorio) + extensión permitida. Evita pedir otros archivos (../.env)
    private const IMAGEN_VALIDA = '/^[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$/';
    private const PDF_VALIDO    = '/^[A-Za-z0-9]+\.pdf$/';
    private const WEBP_VALIDO   = '/^[A-Za-z0-9]+\.webp$/';     // imágenes de productos de la tienda

    public function subirImagen(Request $request)
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

        return $this->guardar($request->file('imagen'), 'api/public/pagina-web/imagenes/', 'Imagen subida');
    }

    public function subirPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pdf' => 'required|file|mimes:pdf|max:10240',
        ], [
            'pdf.required' => 'Elige un PDF.',
            'pdf.file'     => 'Elige un PDF.',
            'pdf.mimes'    => 'El archivo debe ser un PDF.',
            'pdf.max'      => 'El PDF no puede pesar más de 10 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
        }

        return $this->guardar($request->file('pdf'), 'api/public/pagina-web/documentos/', 'PDF subido');
    }

    public function verImagen(string $nombre)
    {
        return $this->entregar($nombre, self::IMAGEN_VALIDA);
    }

    public function verPdf(string $nombre)
    {
        return $this->entregar($nombre, self::PDF_VALIDO);
    }

    // Imágenes de un producto de la tienda (las sube ProductoTiendaController en productos/{proId})
    public function verImagenProducto(int $proId, string $nombre)
    {
        return $this->entregar($nombre, self::WEBP_VALIDO, 'productos/' . $proId);
    }

    // Logos de las marcas (los sube MarcaWebController en marcas)
    public function verImagenMarca(string $nombre)
    {
        return $this->entregar($nombre, self::IMAGEN_VALIDA, 'marcas');
    }

    // -----------------------------------------------------------------

    // Guarda con nombre aleatorio (extensión según el contenido) en la carpeta privada y responde la URL pública.
    // $subcarpeta (por ejemplo productos/15) va dentro de la carpeta privada y también en la URL.
    // Pública para que ProductoTiendaController la reutilice.
    public function guardar($archivo, string $rutaPublica, string $mensaje, string $subcarpeta = '')
    {
        $sufijo = $subcarpeta === '' ? '' : $subcarpeta . '/';

        try {
            $ruta = $archivo->store(rtrim(self::CARPETA . '/' . $sufijo, '/'), 'local');

            return response()->json(RespuestaApi::returnResultado('success', $mensaje, [
                'url' => url($rutaPublica . $sufijo . basename($ruta)),
            ]));
        } catch (\Throwable $th) {
            Log::error('No se pudo subir el archivo de página web', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo subir el archivo.', null));
        }
    }

    // Entrega el archivo solo si el nombre es válido y existe. El nombre es aleatorio y nunca cambia
    // de contenido: el navegador puede guardarlo un año.
    private function entregar(string $nombre, string $patron, string $subcarpeta = '')
    {
        $ruta = self::CARPETA . '/' . ($subcarpeta === '' ? '' : $subcarpeta . '/') . $nombre;

        if (!preg_match($patron, $nombre) || !Storage::disk('local')->exists($ruta)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($ruta), [
            'Cache-Control'          => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
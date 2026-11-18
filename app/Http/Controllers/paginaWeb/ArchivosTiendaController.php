<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use Illuminate\Support\Facades\Storage;

// Archivos de la tienda (público, sin login): fotos de los productos, imágenes de la descripción completa de un
// producto, logos de las marcas y los archivos que se subían con Página web (imágenes y PDF). Todos en la carpeta privada
// storage/app/pagina-web (ArchivosPaginaWeb::CARPETA); los suben Inventarios (productos, descripción, marcas).
class ArchivosTiendaController extends Controller
{
    private const CARPETA = ArchivosPaginaWeb::CARPETA;

    // Nombre que genera Laravel (aleatorio) + extensión permitida. Evita pedir otros archivos (../.env)
    private const IMAGEN_VALIDA = '/^[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$/';
    private const PDF_VALIDO    = '/^[A-Za-z0-9]+\.pdf$/';
    private const WEBP_VALIDO   = '/^[A-Za-z0-9]+\.webp$/';     

    // GET public/pagina-web/imagenes/{nombre}
    public function verImagen(string $nombre)
    {
        return $this->entregar($nombre, self::IMAGEN_VALIDA);
    }

    // GET public/pagina-web/documentos/{nombre}
    public function verPdf(string $nombre)
    {
        return $this->entregar($nombre, self::PDF_VALIDO);
    }

    // Imágenes de un producto de la tienda (carpeta productos/{proId}). GET public/pagina-web/imagenes/productos/{proId}/{nombre}
    public function verImagenProducto(int $proId, string $nombre)
    {
        return $this->entregar($nombre, self::WEBP_VALIDO, 'productos/' . $proId);
    }

    // Imágenes de la descripción completa de un producto (carpeta productos/{proId}/descripcion; las sube
    // ProductoDescripcionController). WebP, JPG o PNG con nombre de 40 letras y números.
    public function verImagenDescripcionProducto(int $proId, string $nombre)
    {
        return $this->entregar($nombre, '/^[A-Za-z0-9]{40}\.(webp|jpg|png)$/', 'productos/' . $proId . '/descripcion');
    }

    // Logos de las marcas (los sube Inventarios > Marcas en marcas). GET public/pagina-web/imagenes/marcas/{nombre}
    public function verImagenMarca(string $nombre)
    {
        return $this->entregar($nombre, self::IMAGEN_VALIDA, 'marcas');
    }

    // Entrega el archivo solo si el nombre es válido y existe. El nombre es aleatorio y nunca cambia de contenido: el
    // navegador puede guardarlo un año.
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

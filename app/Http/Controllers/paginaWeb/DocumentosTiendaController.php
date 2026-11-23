<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use App\Servicios\PaginaWeb\LimpiadorDocumento;
use Illuminate\Support\Facades\DB;

// Documentos en la tienda (público, sin login): un documento activo por su dirección, la pantalla de una carpeta de
// primer nivel, la lista de documentos activos, y sus imágenes y PDF (ecommerce.fn_documento_*_publico/s).
// El mantenimiento está en ecommerce/DocumentosController (CRM).
class DocumentosTiendaController extends Controller
{
    // Imágenes y PDF: carpeta privada storage/app/documentos; se piden por api/public/documentos/imagenes|pdf/{nombre}.
    private const CARPETA_IMAGENES = ArchivosPaginaWeb::CARPETA . '/documentos/imagenes';
    private const CARPETA_PDF = ArchivosPaginaWeb::CARPETA . '/documentos/pdf';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.(webp|jpg|png)$/';
    private const NOMBRE_PDF = '/^[A-Za-z0-9]{40}\.pdf$/';
    private const TIPOS_IMAGEN = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];

    // Un documento activo por su dirección, para la tienda. GET public/documentos/{ruta}
    public function publico(string $ruta)
    {
        try {
            $documento = DB::selectOne('SELECT * FROM ecommerce.fn_documento_buscar_publico(?)', [$ruta]);
            // No existe o está inactivo: code 404 en el cuerpo (HTTP 200, estándar); la tienda muestra "no disponible".
            // RespuestaApi no deja elegir el código del error (siempre 500): se reemplaza solo el code
            if (!$documento) {
                return response()->json(['code' => 404] + RespuestaApi::returnResultado('error', 'El documento no existe.', null));
            }

            $documento->doc_contenido = $this->conImagenesCompletas($documento->doc_contenido);
            $documento->doc_pdf_url = $documento->doc_pdf ? url('api/public/documentos/pdf/' . $documento->doc_pdf) : null;
            unset($documento->doc_pdf);

            return response()->json(RespuestaApi::returnResultado('success', 'Documento', ['documento' => $documento]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el documento', null));
        }
    }

    // La pantalla de una carpeta de primer nivel (/documentos/carpeta/<ruta> en la tienda): su nombre y sus documentos
    // activos agrupados por subcarpeta ("" = directo en la carpeta). code 404 si no existe o no tiene documentos activos.
    // GET public/documentos/carpetas/{ruta}
    public function carpetaPublica(string $ruta)
    {
        try {
            $filas = DB::select('SELECT * FROM ecommerce.fn_documento_carpeta_publica(?)', [$ruta]);
            if (!$filas) {
                return response()->json(['code' => 404] + RespuestaApi::returnResultado('error', 'La carpeta no existe.', null));
            }

            $grupos = [];
            foreach ($filas as $f) {
                $grupos[$f->subcarpeta][] = [
                    'doc_titulo' => $f->doc_titulo,
                    'doc_ruta' => $f->doc_ruta,
                    'doc_pdf_url' => $f->doc_pdf ? url('api/public/documentos/pdf/' . $f->doc_pdf) : null,
                    'doc_pdf_etiqueta' => $f->doc_pdf_etiqueta,
                ];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta', [
                'carpeta' => $filas[0]->carpeta,
                'grupos' => array_map(fn($nombre, $docs) => ['subcarpeta' => $nombre, 'documentos' => $docs], array_keys($grupos), $grupos),
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la carpeta', null));
        }
    }

    // Los documentos activos por carpeta (para el pie de página o un índice). GET public/documentos
    public function publicos()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Documentos', [
                'documentos' => DB::select('SELECT * FROM ecommerce.fn_documento_listar_publicos()'),
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los documentos', null));
        }
    }

    public function verImagen(string $nombre)
    {
        if (!preg_match(self::NOMBRE_IMAGEN, $nombre, $m)) {
            abort(404);
        }

        // Del NAS o de local (ArchivosPaginaWeb::entregar).
        return ArchivosPaginaWeb::entregar(self::CARPETA_IMAGENES . '/' . $nombre, self::TIPOS_IMAGEN[$m[1]]);
    }

    public function verPdf(string $nombre)
    {
        if (!preg_match(self::NOMBRE_PDF, $nombre)) {
            abort(404);
        }

        // Del NAS o de local (ArchivosPaginaWeb::entregar).
        return ArchivosPaginaWeb::entregar(self::CARPETA_PDF . '/' . $nombre, 'application/pdf', [
            'Content-Disposition' => 'inline; filename="documento.pdf"',
        ]);
    }

    // Las imágenes y los enlaces a PDF de los documentos se guardan con dirección relativa: al entregarlos, con la de
    // este servidor (la tienda está en otro).
    private function conImagenesCompletas(?string $html): string
    {
        return str_replace(
            ['src="' . LimpiadorDocumento::RUTA_IMAGENES, 'href="/api/public/documentos/pdf/'],
            ['src="' . url('api/public/documentos/imagenes') . '/', 'href="' . url('api/public/documentos/pdf') . '/'],
            (string) $html
        );
    }
}

<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// Páginas de la tienda (público, sin login): las páginas del CRM (Ecommerce > Páginas) listas para dibujar y las
// imágenes de sus secciones.
//   GET public/paginas/inicio              → la página marcada como Inicio (data = null si no hay: la tienda usa su inicio fijo)
//   GET public/paginas/{ruta}              → una página activa por su dirección (code 404 en el cuerpo si no existe o está inactiva)
//   GET public/secciones/imagenes/{nombre} → una imagen de las secciones
// La base arma la página entera (ecommerce.fn_pagina_tienda: secciones visibles en orden, su plantilla, sus valores con la
// dirección de hoy de cada enlace y las tarjetas reales de sus productos). Aquí solo se completa la dirección de las
// fotos de las tarjetas y la de las imágenes de las secciones (la base no conoce la de este servidor).
// Sin caché de la respuesta: lo que se guarda en el CRM sale al momento. Siempre HTTP 200 (estándar): el resultado va en
// status y code. El mantenimiento está en ecommerce/PaginasController y SeccionesController (CRM).
class PaginasTiendaController extends Controller
{
    // Imágenes de las secciones: carpeta privada storage/app/secciones; nombre aleatorio de 40 letras y números + .webp
    private const CARPETA_IMAGENES = 'secciones';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.webp$/';

    public function inicio()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Página de inicio', $this->pagina(null)))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo leer la página de inicio', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página.', null));
        }
    }

    public function ver(string $ruta)
    {
        try {
            $pagina = $this->pagina($ruta);
            if (!$pagina) {
                // RespuestaApi no deja elegir el código del error (siempre 500): se reemplaza solo el code
                return response()->json(['code' => 404] + RespuestaApi::returnResultado('error', 'La página no existe.', null))
                    ->header('Cache-Control', 'no-cache');
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Página', $pagina))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo leer la página', ['ruta' => $ruta, 'error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página.', null));
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

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * La página tal como la arma la base (ecommerce.fn_pagina_tienda), con las tarjetas en su forma de la tienda y la
     * dirección de las imágenes: {pagina, url_imagenes, secciones: [...]}. null si no hay página.
     */
    private function pagina(?string $ruta): ?array
    {
        $fila = DB::selectOne('SELECT ecommerce.fn_pagina_tienda(?) AS pagina', [$ruta]);
        if (!$fila || $fila->pagina === null) {
            return null;
        }
        $pagina = json_decode($fila->pagina, true);

        foreach ($pagina['secciones'] as &$seccion) {
            // Objetos vacíos de la base siguen siendo objetos en el JSON
            foreach (['sec_valores', 'sec_valores_tarjeta', 'tarjetas'] as $campo) {
                $seccion[$campo] = $seccion[$campo] ?: (object) [];
            }
            foreach ($seccion['elementos'] as &$elemento) {
                $elemento['sel_valores'] = $elemento['sel_valores'] ?: (object) [];
            }
            unset($elemento);
            if (is_array($seccion['tarjetas'])) {
                foreach ($seccion['tarjetas'] as $clave => $lista) {
                    $seccion['tarjetas'][$clave] = array_map(fn ($t) => $this->tarjeta($t), $lista);
                }
            }
        }
        unset($seccion);

        return [
            'pagina' => $pagina['pagina'],
            'url_imagenes' => url('api/public/secciones/imagenes') . '/',
            'secciones' => $pagina['secciones'],
        ];
    }

    /**
     * La tarjeta de producto de todo el sitio, de una fila de ecommerce.fn_producto_tienda_tarjetas.
     */
    private function tarjeta(array $f): array
    {
        return [
            'pro_id'          => (int) $f['pro_id'],
            'codigo'          => $f['pro_codigo'],
            'nombre'          => $f['pro_nombre'],
            'marca'           => $f['marca'],
            'tpr_id'          => (int) $f['tpr_id'],
            'categoria'       => $f['categoria'],
            'imagenes'        => array_map(fn ($ruta) => url($ruta), is_array($f['imagenes'] ?? null) ? $f['imagenes'] : []),
            'destacado'       => (bool) $f['destacado'],
            // En oferta (producto.pro_oferta): etiqueta Oferta.
            'oferta'          => (bool) ($f['oferta'] ?? false),
            'precio'          => $f['precio'] !== null ? (float) $f['precio'] : null,
            'precio_anterior' => $f['precio_anterior'] !== null ? (float) $f['precio_anterior'] : null,
            'stock'           => (float) $f['stock'],
            // Su factura pide los datos de MOTOS (inventarios.fn_producto_es_moto)
            'es_moto'         => (bool) ($f['es_moto'] ?? false),
        ];
    }
}

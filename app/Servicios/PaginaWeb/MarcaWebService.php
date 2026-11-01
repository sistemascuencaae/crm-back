<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\DB;

/**
 * Marcas de la tienda. Las marcas vienen del ERP (public.marca); aquí se maneja su logo (uno solo por marca:
 * mar_imagen) y cuáles salen en "Nuestras marcas" (mar_web_destacada), en su orden. Llama a las funciones
 * ecommerce.fn_marca_web_*. El logo se ve en el inicio, Garantía extendida, Beneficios para empresa,
 * las páginas de marca y España Motos: cada cambio limpia sus cachés.
 */
class MarcaWebService
{
    // Códigos propios (los CAMPO_*, ORDEN_* y REQUIERE_USUARIO comunes están en ErroresPaginaWeb)
    public const MENSAJES_ERROR = [
        'MARCA_NO_EXISTE'  => 'La marca no existe o está inactiva en el sistema.',
        'MARCA_SIN_IMAGEN' => 'Sube el logo de la marca antes de ponerla en Nuestras marcas.',
        'CAMPO_IMAGEN'     => 'La dirección de la imagen no es válida.',
    ];

    /** Todas las pantallas de la tienda que muestran logos de marca deben ver el cambio. */
    public static function limpiarCache(): void
    {
        self::limpiarCacheNuestrasMarcas();
        PaginaMarcaService::limpiarCache();
        EspanaMotosService::limpiarCache();
    }

    /**
     * Las cachés donde se guarda "Nuestras marcas" (publicas()): el inicio, Garantía extendida y Beneficios para empresa.
     * También las limpia PaginaMarcaService: el enlace de cada logo depende de si la marca tiene una página publicada.
     */
    public static function limpiarCacheNuestrasMarcas(): void
    {
        InicioSeccionService::limpiarCache();
        PaginaInformativaService::limpiarCacheMarcas();
    }

    // -----------------------------------------------------------------
    // CRM
    // -----------------------------------------------------------------

    /** [{mar_id, mar_nombre, mar_activo, mar_imagen, mar_web_destacada, mar_web_orden, productos, pagina_direccion}] */
    public function listar(): array
    {
        return array_map(fn ($m) => [
            'mar_id'            => (int) $m->mar_id,
            'mar_nombre'        => $m->mar_nombre,
            'mar_activo'        => (bool) $m->mar_activo,
            'mar_imagen'        => $m->mar_imagen,
            'mar_web_destacada' => (bool) $m->mar_web_destacada,
            'mar_web_orden'     => (int) $m->mar_web_orden,
            'productos'         => (int) $m->productos,
            'pagina_direccion'  => $m->pagina_direccion,
        ], DB::select('SELECT * FROM ecommerce.fn_marca_web_listar()'));
    }

    /** $datos: solo lo que cambia (mar_imagen, mar_web_destacada) + usu_id */
    public function modificar(int $marId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_marca_web_modificar(?, ?::jsonb)', [$marId, $this->json($datos)]);
        self::limpiarCache();
    }

    /** $datos: {ids: [mar_id…] (todas las de Nuestras marcas, en el nuevo orden), usu_id} */
    public function ordenar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_marca_web_ordenar(?::jsonb)', [$this->json($datos)]);
        self::limpiarCache();
    }

    // -----------------------------------------------------------------
    // Tienda (va dentro del inicio y de las páginas informativas, que ya tienen su caché)
    // -----------------------------------------------------------------

    /** "Nuestras marcas": [{mar_id, nombre, imagen, enlace}] (el enlace ya viene armado: página de la marca o la tienda filtrada) */
    public function publicas(): array
    {
        return array_map(fn ($m) => [
            'mar_id' => (int) $m->mar_id,
            'nombre' => $m->nombre,
            'imagen' => $m->imagen,
            'enlace' => $m->enlace,
        ], DB::select('SELECT * FROM ecommerce.fn_marca_web_listar_publicas()'));
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}

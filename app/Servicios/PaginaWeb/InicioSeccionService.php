<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Interruptores de las secciones del inicio de la tienda (banner_principal, categorias_destacadas).
 * Llama a las funciones ecommerce.fn_inicio_seccion_*.
 */
class InicioSeccionService
{
    public const MENSAJES_ERROR = [
        'REQUIERE_USUARIO'        => 'No se identificó al usuario que realiza el cambio.',
        'SECCION_NO_EXISTE'       => 'La sección no existe.',
        'SECCION_ACTIVO_INVALIDO' => 'El valor de "mostrar" debe ser verdadero o falso.',
    ];

    /** [{ise_clave, ise_nombre, ise_activo}] */
    public function listar(): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_inicio_seccion_listar()');
    }

    /** $datos: {ise_activo, usu_id} */
    public function modificar(string $clave, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_inicio_seccion_modificar(?, ?::jsonb)', [$clave, json_encode($datos)]);

        // Lo que ve la tienda depende de estos interruptores: debe ver el cambio al momento
        CategoriaWebService::limpiarCache();
        GaleriaService::limpiarCacheInicioBanner();
    }

    /** Traduce el código que lanzó la base a un mensaje amigable; null si no es un código conocido. */
    public function mensajeError(QueryException $e): ?string
    {
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (preg_match('/\b' . $codigo . '\b/', $e->getMessage())) {
                return $mensaje;
            }
        }

        return null;
    }
}
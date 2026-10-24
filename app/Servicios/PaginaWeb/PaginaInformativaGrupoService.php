<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\DB;

/**
 * Carpetas de los documentos (Protección de datos, Términos…).
 * Solo sirven para agrupar y ordenar: en el CRM y en el diálogo de documentos de la tienda.
 * Llama a las funciones ecommerce.fn_pagina_informativa_grupo_*.
 */
class PaginaInformativaGrupoService
{
    // Mensaje amigable para cada código que lanzan las funciones ecommerce.fn_pagina_informativa_grupo_*
    public const MENSAJES_ERROR = [
        'REQUIERE_USUARIO'       => 'No se identificó al usuario que realiza el cambio.',
        'GRUPO_NOMBRE_REQUERIDO' => 'Escribe el nombre de la carpeta.',
        'GRUPO_NOMBRE_LARGO'     => 'El nombre de la carpeta no puede superar 60 caracteres.',
        'GRUPO_DUPLICADO'        => 'Ya existe una carpeta con ese nombre.',
        'GRUPO_NO_EXISTE'        => 'La carpeta no existe.',
        'GRUPO_CON_PAGINAS'      => 'La carpeta tiene documentos. Muévelos a otra carpeta o elimínalos antes.',
        'ORDEN_INVALIDO'         => 'El nuevo orden no es válido.',
        'ORDEN_DESACTUALIZADO'   => 'La lista cambió mientras la ordenabas. Recarga la página e inténtalo de nuevo.',
    ];

    /** Carpetas en su orden, con cuántos documentos tiene cada una. */
    public function listar(): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_pagina_informativa_grupo_listar()');
    }

    /** Crea la carpeta al final. Devuelve su id. */
    public function crear(array $datos): int
    {
        return DB::selectOne('SELECT ecommerce.fn_pagina_informativa_grupo_crear(?::jsonb) AS id', [$this->json($datos)])->id;
    }

    /** Renombra la carpeta. */
    public function modificar(int $pigId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_pagina_informativa_grupo_modificar(?, ?::jsonb)', [$pigId, $this->json($datos)]);
    }

    /** Solo se elimina si está vacía. */
    public function eliminar(int $pigId): void
    {
        DB::statement('SELECT ecommerce.fn_pagina_informativa_grupo_eliminar(?)', [$pigId]);
    }

    /** Nuevo orden de TODAS las carpetas. $datos: {ids: [pig_id…], usu_id} */
    public function ordenar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_pagina_informativa_grupo_ordenar(?::jsonb)', [$this->json($datos)]);
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Los textos del header (htx_texto, en cualquier posición: "textos.0.htx_texto", "partes.1.textos.2.htx_texto"...)
     * no se recortan: los segmentos del texto promocional llevan dentro el espacio que los separa ("hasta " + "18 MESES").
     * Los demás textos del header los recorta la base (ecommerce.fn_header_valor_texto).
     */
    protected function transform($key, $value)
    {
        if (is_string($key) && ($key === 'htx_texto' || str_ends_with($key, '.htx_texto'))) {
            return $value;
        }

        return parent::transform($key, $value);
    }
}

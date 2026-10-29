<?php

namespace App\Servicios\PaginaWeb;

use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Traduce a un mensaje amigable los códigos que lanzan las funciones de la base de Página Web.
 * Es el único lugar de traducción de errores: lo usan todos los controladores del módulo.
 *
 * Los códigos de un campo traen el campo pegado, y a veces un número:
 *     CAMPO_REQUERIDO:pco_titulo
 *     CAMPO_LARGO:barra_superior.texto:80
 * Con $porCampo = true (por defecto) la respuesta lleva data = {campo: mensaje}, y el CRM pinta el mensaje
 * debajo del campo. Con $porCampo = false siempre va solo el mensaje (pantallas que muestran el error arriba).
 * Cada módulo pasa sus códigos propios en $extra (por ejemplo SucursalService::MENSAJES_ERROR); esos ganan a los comunes.
 */
class ErroresPaginaWeb
{
    // %s se reemplaza por el número del código (CAMPO_LARGO:campo:80 → "Máximo 80 caracteres.")
    public const MENSAJES = [
        'CAMPO_REQUERIDO'      => 'Este campo es obligatorio.',
        'CAMPO_LARGO'          => 'Máximo %s caracteres.',
        'CAMPO_ENLACE'         => 'Debe ser una ruta que empiece por /, una dirección https://, un mailto: o un tel:.',
        'CAMPO_INVALIDO'       => 'El valor no es válido.',
        'CAMPO_CANTIDAD'       => 'Debe ser un número entero entre 1 y %s.',
        'CAMPO_FECHA'          => 'La fecha no es válida.',
        'FECHAS_RANGO'         => '"Hasta" debe ser posterior a "Desde".',
        'ICONO_INVALIDO'       => 'El ícono no es válido.',
        'FRASES_CANTIDAD'      => 'Ingresa entre 1 y 5 frases.',
        'FRASE_INVALIDA'       => 'Cada frase debe tener entre 1 y 60 caracteres.',
        'REDES_CANTIDAD'       => 'Puedes agregar como máximo 10 redes sociales.',
        'RED_TIPO_INVALIDO'    => 'Hay una red social no válida.',
        'RED_ENLACE_INVALIDO'  => 'Cada red social necesita un enlace https:// válido.',
        'RED_REPETIDA'         => 'No repitas la misma red social.',
        'REQUIERE_USUARIO'     => 'No se identificó al usuario que realiza el cambio.',
        'ENLACE_NO_EXISTE'     => 'El enlace no existe.',
        'ENLACE_PADRE_INVALIDO' => 'El enlace principal no existe o ya es un sub-enlace (solo se permite un nivel).',
        'ENLACE_HIJOS_MAXIMO'  => 'Un enlace principal puede tener como máximo 10 sub-enlaces.',
        'COLUMNA_NO_EXISTE'    => 'La columna no existe.',
        'PIE_COLUMNAS_MAXIMO'  => 'El footer admite como máximo 6 columnas.',
        'ORDEN_INVALIDO'       => 'El nuevo orden no es válido.',
        'ORDEN_DESACTUALIZADO' => 'La lista cambió mientras la ordenabas. Recarga la página e inténtalo de nuevo.',
        // Al restaurar algo cuyo padre sigue en la papelera
        'ENLACE_PADRE_EN_PAPELERA' => 'Primero restaura el enlace principal de este sub-enlace.',
        'COLUMNA_EN_PAPELERA'      => 'Primero restaura la columna de este enlace.',
        'GRUPO_EN_PAPELERA'        => 'Primero restaura la carpeta de esta página.',
        'CIUDAD_EN_PAPELERA'       => 'Primero restaura la ciudad de esta sucursal.',
    ];

    /** Respuesta de error para el CRM. Código desconocido → mensaje genérico (el detalle queda en el log). */
    public static function respuesta(QueryException $e, string $mensajeGenerico, array $extra = [], bool $porCampo = true)
    {
        $mensajes = $extra + self::MENSAJES;

        // El código viene después de "ERROR:", por ejemplo:  ERROR:  CAMPO_LARGO:barra_superior.texto:80
        if (preg_match('/ERROR:\s+([A-Z_]+)(?::([a-z0-9_.]+))?(?::(\d+))?/', $e->getMessage(), $m)
            && isset($mensajes[$m[1]])) {
            $mensaje = sprintf($mensajes[$m[1]], $m[3] ?? '');
            $campo   = $m[2] ?? '';

            if ($porCampo && $campo !== '') {
                return response()->json(RespuestaApi::returnResultado('error', 'Revisa los datos marcados.', [$campo => $mensaje]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
        }

        Log::error($mensajeGenerico, ['error' => $e->getMessage()]);

        return response()->json(RespuestaApi::returnResultado('error', $mensajeGenerico, null));
    }
}
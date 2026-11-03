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
        // Franjas del detalle del producto
        'FRANJA_NO_EXISTE'         => 'La franja no existe o está en la papelera.',
        'FRANJAS_MAXIMO'           => 'Puedes tener hasta 5 franjas. Elimina una para agregar otra.',
        'FRANJA_TEXTO_REQUERIDO'   => 'Escribe el texto o el texto resaltado de la franja.',
        // Grupos de los banners (GRUPO_EN_PAPELERA de las imágenes está en GaleriaService: el común es el de las carpetas)
        'GALERIA_SIN_GRUPOS'       => 'Esta galería no usa grupos.',
        'GRUPO_NO_EXISTE'          => 'El grupo ya no existe.',
        'GRUPO_SIEMPRE'            => 'El grupo Siempre no se puede cambiar ni borrar.',
        'GRUPOS_MAXIMO'            => 'Máximo 20 grupos por banner.',
        // Familias de productos (variantes)
        'FAMILIA_NO_EXISTE'        => 'La familia ya no existe.',
        'PRODUCTO_NO_EXISTE'       => 'El producto no existe o está inactivo.',
        'PRODUCTO_SIN_MARCA'       => 'El producto no tiene marca.',
        'PRODUCTO_EN_OTRA_FAMILIA' => 'Ese producto ya está en otra familia.',
        'PRODUCTO_YA_EN_FAMILIA'   => 'Ese producto ya está en la familia.',
        'PRODUCTO_NO_EN_FAMILIA'   => 'Ese producto no está en la familia.',
        'FAMILIA_OTRA_CATEGORIA'   => 'Es de otra categoría.',
        'FAMILIA_OTRA_MARCA'       => 'Es de otra marca.',
        'FAMILIA_LLENA'            => 'Máximo 30 productos por familia.',
        'FAMILIA_SIN_PRODUCTOS'    => 'Una familia no puede quedar sin productos.',
        'CARACTERISTICA_NO_EXISTE' => 'La característica no existe.',
        'CARACTERISTICAS_IGUALES'  => 'Elige dos características distintas.',
        // Clientes de la tienda (los lanzan la base o ClienteWeb*Controller)
        'CORREO_INVALIDO'              => 'Escribe un correo válido.',
        'TELEFONO_INVALIDO'            => 'Escribe un teléfono válido, por ejemplo 0991234567.',
        'IDENTIFICACION_INVALIDA'      => 'La cédula o el RUC no es válido.',
        'CORREO_REPETIDO'              => 'Ya hay una cuenta con ese correo.',
        'IDENTIFICACION_REPETIDA'      => 'Ya hay una cuenta con esa cédula o RUC.',
        'CORREO_IGUAL'                 => 'Ese ya es el correo de tu cuenta.',
        'POLITICA_REQUERIDA'           => 'Para crear tu cuenta debes aceptar la política de privacidad.',
        'CONTRASENA_INVALIDA'          => 'No pudimos guardar la contraseña. Inténtalo de nuevo.',
        'CLIENTE_NO_EXISTE'            => 'La cuenta no existe.',
        'ENLACE_INVALIDO'              => 'El enlace venció o ya se usó. Pide uno nuevo.',
        'CREDENCIALES_INVALIDAS'       => 'Correo o contraseña incorrectos.',
        'CUENTA_BLOQUEADA'             => 'Tu cuenta está bloqueada por varios intentos fallidos. Inténtalo en %s minutos.',
        'CUENTA_INACTIVA'              => 'Tu cuenta está desactivada. Escríbenos si necesitas ayuda.',
        'CONTRASENA_ACTUAL_INCORRECTA' => 'La contraseña actual no es correcta.',
        'CONTRASENA_DEBIL'             => 'La contraseña debe tener entre 8 y 72 caracteres, con al menos una letra y un número.',
        'CONTRASENA_CONFIRMACION'      => 'Las contraseñas no coinciden.',
        'CORREOS_MAXIMO'               => 'Ya te enviamos varios correos. Espera unos minutos e inténtalo de nuevo.',
        'SESION_VENCIDA'               => 'Tu sesión venció. Vuelve a ingresar.',
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

    // -----------------------------------------------------------------
    // Clientes de la tienda: además del mensaje, 'codigo' y 'campo' (la tienda pinta el error debajo de ese campo)
    // -----------------------------------------------------------------

    /** Error de un código de la base. Desconocido → mensaje genérico con codigo null (el detalle queda en el log). */
    public static function respuestaCliente(QueryException $e, string $mensajeGenerico)
    {
        if (preg_match('/ERROR:\s+([A-Z_]+)(?::([a-z0-9_.]+))?(?::(\d+))?/', $e->getMessage(), $m) && isset(self::MENSAJES[$m[1]])) {
            return self::errorCliente($m[1], $m[2] ?? null, $m[3] ?? null);
        }

        Log::error($mensajeGenerico, ['error' => $e->getMessage()]);

        return response()->json(RespuestaApi::returnResultado('error', $mensajeGenerico, null) + ['codigo' => null, 'campo' => null]);
    }

    /**
     * Error con un código conocido: {code, status: 'error', message, data: null, codigo, campo}.
     * $numero reemplaza el %s del mensaje (minutos de CUENTA_BLOQUEADA, largo de CAMPO_LARGO).
     * $code: el código del cuerpo (401 en SESION_VENCIDA; 500 como todo error de RespuestaApi).
     */
    public static function errorCliente(string $codigo, ?string $campo = null, $numero = null, int $code = 500)
    {
        $mensaje = sprintf(self::MENSAJES[$codigo], $numero ?? '');

        return response()->json(
            ['code' => $code] + RespuestaApi::returnResultado('error', $mensaje, null) + ['codigo' => $codigo, 'campo' => $campo ?: null]
        );
    }
}
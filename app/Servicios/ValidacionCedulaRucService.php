<?php

namespace App\Servicios;

class ValidacionCedulaRucService
{
    const CEDULA = 'CEDULA';
    const RUC_PERSONA_NATURAL = 'RUC_PERSONA_NATURAL';
    const RUC_SOCIEDAD_PRIVADA = 'RUC_SOCIEDAD_PRIVADA';
    const RUC_SOCIEDAD_PUBLICA = 'RUC_SOCIEDAD_PUBLICA';

    /**
     * Valida cualquier número de identificación ecuatoriano:
     * cédula, RUC persona natural, RUC sociedad pública, RUC sociedad privada
     */
    public static function esIdentificacionValida(?string $identificacion): bool
    {
        if (empty($identificacion)) {
            return false;
        }

        $longitud = strlen($identificacion);

        if (!self::esNumeroIdentificacionValida($identificacion, $longitud)) {
            return false;
        }

        if ($longitud === 10) {
            return self::esCedulaValida($identificacion);
        }

        if ($longitud === 13) {
            $tercerDigito = (int) $identificacion[2];

            if ($tercerDigito >= 0 && $tercerDigito <= 5) {
                return self::esRucPersonaNaturalValido($identificacion);
            } elseif ($tercerDigito === 6) {
                // Tercer dígito 6: puede ser RUC de persona natural NACIONALIZADO (cédula + '001') o sociedad
                // pública. Se intenta primero como natural; si no valida, se evalúa como pública.
                return self::esRucPersonaNaturalValido($identificacion)
                    || self::esRucSociedadPublicaValido($identificacion);
            } elseif ($tercerDigito === 9) {
                return self::esRucSociedadPrivadaValido($identificacion);
            }

            return false;
        }

        return false;
    }

    /**
     * Tipo de persona ('N' = Natural, 'J' = Jurídica) deducido SOLO del número, sin consultar al SRI.
     * Es el respaldo para cuando el SRI está caído o el throttle no deja consultarlo.
     *
     * Una persona jurídica NO tiene cédula: se identifica con RUC de 13 dígitos. Por eso cédula y pasaporte
     * son SIEMPRE Natural, y el único ambiguo es el RUC.
     *
     * En el RUC no basta mirar el tercer dígito: el 6 lo comparten la persona natural NACIONALIZADA y la
     * sociedad pública, y quien los separa es el dígito verificador (cada tipo usa coeficientes distintos).
     * Por eso se prueba con los validadores de arriba en vez de leer el dígito a mano.
     *
     * @param string|null $identificacion cédula / RUC / pasaporte
     * @param int|null $tipoIdentificacion 1 = cédula, 2 = RUC, 3 = pasaporte. Si no es uno de esos (dato
     *                                     legacy sucio), se deduce por la longitud del número.
     * @return string|null 'N' | 'J', o null si es un RUC que no valida como ninguno de los tres tipos
     */
    public static function tipoSujetoPorIdentificacion(?string $identificacion, ?int $tipoIdentificacion = null): ?string
    {
        $numero = trim((string) $identificacion);

        if (!in_array($tipoIdentificacion, [1, 2, 3], true)) {
            $tipoIdentificacion = strlen($numero) === 13 ? 2 : 1;
        }

        // Cédula y pasaporte: jamás jurídica.
        if ($tipoIdentificacion !== 2) {
            return 'N';
        }

        if (self::esRucPersonaNaturalValido($numero)) {
            return 'N';
        }

        if (self::esRucSociedadPrivadaValido($numero) || self::esRucSociedadPublicaValido($numero)) {
            return 'J';
        }

        return null;
    }

    /**
     * Verifica si un número de cédula es válido
     */
    public static function esCedulaValida(string $numeroCedula): bool
    {
        if (!self::validacionesPrevias($numeroCedula, 10, self::CEDULA)) {
            return false;
        }

        $ultimoDigito = (int) $numeroCedula[9];
        return self::algoritmoVerificaIdentificacion($numeroCedula, $ultimoDigito, self::CEDULA);
    }

    /**
     * Verifica si un número de RUC (cualquier tipo) es válido
     */
    public static function esRucValido(string $numeroRuc): bool
    {
        return self::esRucPersonaNaturalValido($numeroRuc)
            || self::esRucSociedadPrivadaValido($numeroRuc)
            || self::esRucSociedadPublicaValido($numeroRuc);
    }

    /**
     * Verifica si un RUC de persona natural es válido
     */
    public static function esRucPersonaNaturalValido(string $numeroRuc): bool
    {
        if (!self::validacionesPrevias($numeroRuc, 13, self::RUC_PERSONA_NATURAL)) {
            return false;
        }

        $ultimoDigito = (int) $numeroRuc[9];
        return self::algoritmoVerificaIdentificacion($numeroRuc, $ultimoDigito, self::RUC_PERSONA_NATURAL);
    }

    /**
     * Verifica si un RUC de sociedad privada es válido.
     *
     * NO se exige el dígito verificador (2026-07-28). Medido sobre las 178.903 sociedades privadas activas
     * del catálogo de la Superintendencia de Compañías: solo el 59% lo cumple. El 41% restante se rechazaba
     * — 36% son RUC que el SRI emitió sin respetar su propia regla, y 5% caían en 11-residuo=10, que no es
     * un dígito y por tanto nunca podía coincidir. El algoritmo NO está mal (si lo estuviera los aciertos
     * rondarían el 9% por azar, no el 59%): es el SRI el que no lo respeta, así que exigirlo dejaba fuera a
     * 4 de cada 10 empresas reales. Caso que lo destapó: 1793232706001 (ZULU LABZ S.A.), válido en el SRI.
     *
     * Se mantiene TODA la validación estructural: 13 dígitos numéricos, provincia 01-24 o 30, tercer dígito
     * 9 y establecimiento >= 001. Cédula y RUC de persona natural siguen exigiendo el dígito verificador,
     * que ahí sí es fiable y protege de los errores de tipeo.
     */
    public static function esRucSociedadPrivadaValido(string $numeroRuc): bool
    {
        return self::validacionesPrevias($numeroRuc, 13, self::RUC_SOCIEDAD_PRIVADA);
    }

    /**
     * Verifica si un RUC de sociedad pública es válido
     */
    public static function esRucSociedadPublicaValido(string $numeroRuc): bool
    {
        if (!self::validacionesPrevias($numeroRuc, 13, self::RUC_SOCIEDAD_PUBLICA)) {
            return false;
        }

        $ultimoDigito = (int) $numeroRuc[8];
        return self::algoritmoVerificaIdentificacion($numeroRuc, $ultimoDigito, self::RUC_SOCIEDAD_PUBLICA);
    }

    // --- Validaciones previas ---

    private static function validacionesPrevias(string $identificacion, int $longitud, string $tipo): bool
    {
        if ($tipo === self::CEDULA) {
            return self::esNumeroIdentificacionValida($identificacion, $longitud)
                && self::esCodigoProvinciaValido($identificacion)
                && self::esTercerDigitoValido($identificacion, $tipo);
        }

        return self::esNumeroIdentificacionValida($identificacion, $longitud)
            && self::esCodigoProvinciaValido($identificacion)
            && self::esTercerDigitoValido($identificacion, $tipo)
            && self::esCodigoEstablecimientoValido($identificacion);
    }

    private static function esNumeroIdentificacionValida(string $numero, int $longitud): bool
    {
        return strlen($numero) === $longitud && ctype_digit($numero);
    }

    /**
     * Códigos válidos: 01-24 (provincias) y 30 (extranjeros residentes)
     */
    private static function esCodigoProvinciaValido(string $numero): bool
    {
        $provincia = (int) substr($numero, 0, 2);
        return ($provincia >= 1 && $provincia <= 24) || $provincia === 30;
    }

    private static function esCodigoEstablecimientoValido(string $numeroRuc): bool
    {
        $ultimosTres = (int) substr($numeroRuc, 10, 3);
        return $ultimosTres >= 1;
    }

    private static function esTercerDigitoValido(string $numero, string $tipo): bool
    {
        $tercerDigito = (int) $numero[2];

        return match ($tipo) {
            self::CEDULA => $tercerDigito >= 0 && $tercerDigito <= 6,
            self::RUC_PERSONA_NATURAL => $tercerDigito >= 0 && $tercerDigito <= 6,
            self::RUC_SOCIEDAD_PUBLICA => $tercerDigito === 6,
            self::RUC_SOCIEDAD_PRIVADA => $tercerDigito === 9,
            default => false,
        };
    }

    // --- Algoritmo de verificación ---

    private static function algoritmoVerificaIdentificacion(string $numero, int $ultimoDigito, string $tipo): bool
    {
        $sumatoria = self::sumarDigitosIdentificacion($numero, $tipo);
        $digitoVerificador = self::obtenerDigitoVerificador($sumatoria, $tipo);
        return $ultimoDigito === $digitoVerificador;
    }

    private static function sumarDigitosIdentificacion(string $numero, string $tipo): int
    {
        $coeficientes = self::obtenerCoeficientes($tipo);
        $sumatoria = 0;

        for ($i = 0; $i < count($coeficientes); $i++) {
            $resultado = (int) $numero[$i] * $coeficientes[$i];
            $sumatoria += self::sumatoriaMultiplicacion($resultado, $tipo);
        }

        return $sumatoria;
    }

    private static function sumatoriaMultiplicacion(int $valor, string $tipo): int
    {
        if ($tipo === self::CEDULA) {
            return $valor >= 10 ? $valor - 9 : $valor;
        }

        if ($tipo === self::RUC_PERSONA_NATURAL) {
            return array_sum(str_split((string) $valor));
        }

        return $valor;
    }

    private static function obtenerCoeficientes(string $tipo): array
    {
        return match ($tipo) {
            self::CEDULA, self::RUC_PERSONA_NATURAL => [2, 1, 2, 1, 2, 1, 2, 1, 2],
            self::RUC_SOCIEDAD_PRIVADA => [4, 3, 2, 7, 6, 5, 4, 3, 2],
            self::RUC_SOCIEDAD_PUBLICA => [3, 2, 7, 6, 5, 4, 3, 2],
            default => [],
        };
    }

    private static function obtenerDigitoVerificador(int $sumatoria, string $tipo): int
    {
        if ($tipo === self::CEDULA || $tipo === self::RUC_PERSONA_NATURAL) {
            $residuo = $sumatoria % 10;
            return $residuo === 0 ? 0 : 10 - $residuo;
        }

        if ($tipo === self::RUC_SOCIEDAD_PUBLICA || $tipo === self::RUC_SOCIEDAD_PRIVADA) {
            $residuo = $sumatoria % 11;
            return $residuo === 0 ? 0 : 11 - $residuo;
        }

        return -1;
    }
}

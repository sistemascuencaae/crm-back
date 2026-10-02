<?php

namespace App\Http\Resources\fileManager;

use App\Models\fileManager\FmArchivo;

class FmEnlaceHelper{
    public const DISK = 'external';
    public const MIME_BASE = 'application/x-fm-link';

    /** Proveedores soportados => código numérico que espera ModalPowerBiComponent. */
    public const PROVEEDORES = [
        'powerbi' => 1,
        'drive'   => 2,
        'tableau' => 3,
        'otro'    => 4,
    ];

    public static function esEnlace(?FmArchivo $archivo): bool
    {
        if (!$archivo) return false;
        return is_string($archivo->mime_type)
            && str_starts_with($archivo->mime_type, self::MIME_BASE);
    }

    public static function urlEnlace(FmArchivo $archivo): ?string
    {
        return self::esEnlace($archivo) ? $archivo->ruta_fisica : null;
    }

    /** 'drive' | 'powerbi' | 'tableau' | 'otro' | null */
    public static function proveedor(FmArchivo $archivo): ?string
    {
        if (!self::esEnlace($archivo)) return null;
        $partes = explode('+', $archivo->mime_type, 2);
        $slug = $partes[1] ?? 'otro';
        return isset(self::PROVEEDORES[$slug]) ? $slug : 'otro';
    }

    /** Código numérico (1-4) para el visor. */
    public static function tipoReporte(FmArchivo $archivo): ?int
    {
        $slug = self::proveedor($archivo);
        return $slug ? self::PROVEEDORES[$slug] : null;
    }

    public static function mimeDe(string $proveedor): string
    {
        $slug = isset(self::PROVEEDORES[$proveedor]) ? $proveedor : 'otro';
        return self::MIME_BASE . '+' . $slug;
    }

    public static function slugsValidos(): array
    {
        return array_keys(self::PROVEEDORES);
    }

    /**
     * Un enlace protegido se abre dentro del sistema (modal); si no, en una
     * pestaña nueva del navegador. Solo aplica a enlaces.
     */
    public static function estaProtegido(FmArchivo $archivo): bool
    {
        return self::esEnlace($archivo) && (bool) $archivo->es_protegido;
    }
}
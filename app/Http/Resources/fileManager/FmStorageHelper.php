<?php

namespace App\Http\Resources\fileManager;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Operaciones de almacenamiento físico para el File Manager.
 *
 * Selecciona el disco NAS o local según el parámetro crm.parametro.abreviacion='NAS'
 * (igual que el resto del CRM). Encapsula el formato de path físico para mantener
 * los archivos organizados en disco: file-manager/{año}/{mes}/{id}-{slug}.{ext}
 */
class FmStorageHelper
{
    /**
     * Devuelve el nombre del disk activo: 'nas' o 'local'.
     */
    public static function diskName(): string
    {
        $parametro = DB::table('crm.parametro')
            ->where('abreviacion', 'NAS')
            ->first();

        return ($parametro && $parametro->nas == true) ? 'nas' : 'local';
    }

    /**
     * Devuelve la instancia del disco activo.
     */
    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /**
     * Construye el path físico para un archivo nuevo.
     * Formato: file-manager/{año}/{mes}/{id}-{slug}.{ext}
     */
    public static function buildPhysicalPath(int $archivoId, string $nombreOriginal): string
    {
        date_default_timezone_set('America/Guayaquil');
        $now = Carbon::now();
        $anio = $now->format('Y');
        $mes = $now->format('m');

        $info = pathinfo($nombreOriginal);
        $ext = isset($info['extension']) ? strtolower($info['extension']) : '';
        $slug = self::slug($info['filename'] ?? 'archivo');

        $nombreFisico = $archivoId . '-' . $slug . ($ext !== '' ? '.' . $ext : '');

        return "file-manager/{$anio}/{$mes}/{$nombreFisico}";
    }

    /**
     * Slug seguro para nombre de archivo en disco.
     * Mantiene letras/dígitos/guiones/puntos. Reemplaza espacios por guión.
     */
    public static function slug(string $texto): string
    {
        $texto = trim($texto);
        // Normaliza acentos y caracteres unicode a ascii cuando es posible
        $texto = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $texto = strtolower($texto);
        $texto = preg_replace('/\s+/', '-', $texto);
        $texto = preg_replace('/[^a-z0-9\-_.]/', '', $texto);
        $texto = preg_replace('/-+/', '-', $texto);
        $texto = trim($texto, '-');

        return $texto !== '' ? $texto : 'archivo';
    }

    /**
     * Guarda el archivo físico y devuelve los metadatos para persistir en fm_archivo.
     *
     * @return array{ruta_fisica:string, disk:string, mime_type:?string, tamano_bytes:int, extension:?string}
     */
    public static function store(UploadedFile $file, int $archivoId): array
    {
        $disk = self::diskName();
        $rutaFisica = self::buildPhysicalPath($archivoId, $file->getClientOriginalName());

        // putFileAs requiere directorio + filename separados
        $directorio = dirname($rutaFisica);
        $nombre = basename($rutaFisica);

        Storage::disk($disk)->putFileAs($directorio, $file, $nombre);

        $extension = $file->getClientOriginalExtension();
        $extension = $extension !== '' ? strtolower($extension) : null;

        return [
            'ruta_fisica'  => $rutaFisica,
            'disk'         => $disk,
            'mime_type'    => $file->getClientMimeType() ?: null,
            'tamano_bytes' => (int) $file->getSize(),
            'extension'    => $extension,
        ];
    }

    /**
     * Borra un archivo físico del disco indicado. Devuelve true si la operación
     * se intentó sin lanzar excepción (no garantiza que el archivo existía).
     */
    public static function delete(string $diskName, ?string $ruta): bool
    {
        if (!$ruta) {
            return false;
        }
        try {
            return (bool) Storage::disk($diskName)->delete($ruta);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Verifica que el archivo físico exista en el disco.
     */
    public static function exists(string $diskName, string $ruta): bool
    {
        try {
            return Storage::disk($diskName)->exists($ruta);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Stream de descarga (Content-Disposition: attachment).
     */
    public static function download(string $diskName, string $ruta, string $nombreVisible): StreamedResponse
    {
        return Storage::disk($diskName)->download($ruta, $nombreVisible);
    }

    /**
     * Stream para preview inline (PDF en iframe, imagen en <img>, etc.).
     */
    public static function streamPreview(
        string $diskName,
        string $ruta,
        ?string $mimeType,
        string $nombreVisible
    ): StreamedResponse {
        $headers = [
            'Content-Type' => $mimeType ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($nombreVisible) . '"',
        ];

        return Storage::disk($diskName)->response($ruta, $nombreVisible, $headers, 'inline');
    }
}

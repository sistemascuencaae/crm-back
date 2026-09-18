<?php

namespace App\Console\Commands;

use App\Http\Resources\crm\Funciones;
use App\Http\Resources\fileManager\FmStorageHelper;
use App\Models\fileManager\FmArchivo;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Busca archivos físicos en el disco que no tengan registro en `crm.fm_archivo`
 * y los elimina. Sirve para limpiar archivos quedados tras subidas fallidas
 * post-storage o tras una restauración parcial de DB.
 *
 *   php artisan fm:limpiar-huerfanos              (dry-run por defecto, sólo reporta)
 *   php artisan fm:limpiar-huerfanos --apply      (ejecuta el borrado real)
 *
 * Sólo procesa el directorio raíz `file-manager/`. Los registros soft-deleted
 * (papelera) NO se consideran huérfanos — sus binarios deben conservarse
 * hasta que el comando `fm:purgar-papelera` los elimine.
 */
class FmLimpiarHuerfanos extends Command
{
    protected $signature = 'fm:limpiar-huerfanos
                            {--apply : Ejecuta el borrado real. Sin esta opción sólo reporta (dry-run).}';

    protected $description = 'Encuentra y elimina archivos físicos en file-manager/ sin registro DB.';

    public function handle(): int
    {
        $log = new Funciones();
        $apply = (bool) $this->option('apply');

        $disk = FmStorageHelper::diskName();
        $this->info("Disco activo: {$disk}");

        if (!$apply) {
            $this->warn('Modo dry-run — NO se ejecutarán borrados. Use --apply para eliminar.');
        }

        try {
            $rutasFisicas = Storage::disk($disk)->allFiles('file-manager');
            $totalDisco = count($rutasFisicas);
            $this->info("Archivos en disco: {$totalDisco}");

            if ($totalDisco === 0) {
                $this->info('Nada que revisar.');
                return self::SUCCESS;
            }

            // withTrashed: los archivos en papelera tienen registro DB válido,
            // sus binarios NO son huérfanos.
            $rutasDb = FmArchivo::withTrashed()
                ->where('disk', $disk)
                ->pluck('ruta_fisica')
                ->filter()
                ->all();
            $setDb = array_flip($rutasDb);

            $huerfanos = [];
            foreach ($rutasFisicas as $ruta) {
                if (!isset($setDb[$ruta])) {
                    $huerfanos[] = $ruta;
                }
            }

            $totalHuerfanos = count($huerfanos);
            $this->info("Huérfanos detectados: {$totalHuerfanos}");

            if ($totalHuerfanos === 0) {
                return self::SUCCESS;
            }

            $previewN = min(20, $totalHuerfanos);
            $this->line("Primeros {$previewN}:");
            for ($i = 0; $i < $previewN; $i++) {
                $this->line('  - ' . $huerfanos[$i]);
            }
            if ($totalHuerfanos > $previewN) {
                $this->line('  ... y ' . ($totalHuerfanos - $previewN) . ' más');
            }

            if (!$apply) {
                $this->warn('Re-ejecute con --apply para eliminar.');
                return self::SUCCESS;
            }

            $borrados = 0;
            $fallos = 0;
            foreach ($huerfanos as $ruta) {
                if (FmStorageHelper::delete($disk, $ruta)) {
                    $borrados++;
                } else {
                    $fallos++;
                }
            }

            $log->logInfo(self::class, "Huérfanos limpiados: {$borrados} borrados, {$fallos} fallos (disk={$disk})");
            $this->info("Borrados: {$borrados}");
            if ($fallos > 0) {
                $this->warn("Fallos: {$fallos}");
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en fm:limpiar-huerfanos', $e);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}

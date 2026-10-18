<?php

namespace App\Console\Commands;

use App\Http\Resources\crm\Funciones;
use App\Http\Resources\fileManager\FmAuditHelper;
use App\Http\Resources\fileManager\FmStorageHelper;
use App\Models\fileManager\FmArchivo;
use App\Models\fileManager\FmCarpeta;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Purga definitivamente los items del File Manager que llevan más de N días en papelera.
 *
 *   php artisan fm:purgar-papelera --dias=30
 *   php artisan fm:purgar-papelera --dias=30 --dry-run
 */
class FmPurgarPapelera extends Command
{
    protected $signature = 'fm:purgar-papelera
                            {--dias=30 : Días de antigüedad mínimos en papelera para purgar}
                            {--dry-run : Sólo reporta lo que purgaría, sin tocar nada}';

    protected $description = 'Purga definitivamente carpetas y archivos del File Manager que llevan más de N días en papelera.';

    public function handle(): int
    {
        $log = new Funciones();
        $dias = (int) $this->option('dias');
        $dryRun = (bool) $this->option('dry-run');

        if ($dias < 1) {
            $this->error('--dias debe ser >= 1');
            return self::FAILURE;
        }

        $corte = Carbon::now()->subDays($dias);
        $this->info('Corte: ' . $corte->toDateTimeString() . ' (items eliminados antes serán purgados)');
        if ($dryRun) {
            $this->warn('Modo dry-run — NO se ejecutarán cambios');
        }

        try {
            $archivos = FmArchivo::onlyTrashed()
                ->where('deleted_at', '<', $corte)
                ->get(['id', 'disk', 'ruta_fisica']);

            $carpetas = FmCarpeta::onlyTrashed()
                ->where('deleted_at', '<', $corte)
                ->where('id', '!=', 1)
                ->orderBy('nivel', 'desc')
                ->get(['id', 'nombre']);

            $this->info('Archivos candidatos: ' . $archivos->count());
            $this->info('Carpetas candidatas: ' . $carpetas->count());

            if ($dryRun) {
                return self::SUCCESS;
            }

            if ($archivos->isEmpty() && $carpetas->isEmpty()) {
                $this->info('Nada que purgar.');
                return self::SUCCESS;
            }

            DB::transaction(function () use ($archivos, $carpetas) {
                if ($archivos->isNotEmpty()) {
                    FmArchivo::withTrashed()
                        ->whereIn('id', $archivos->pluck('id')->toArray())
                        ->forceDelete();
                }
                if ($carpetas->isNotEmpty()) {
                    FmCarpeta::withTrashed()
                        ->whereIn('id', $carpetas->pluck('id')->toArray())
                        ->forceDelete();
                }
            });

            // Borrar binarios físicos fuera de la transacción
            foreach ($archivos as $a) {
                FmStorageHelper::delete($a->disk, $a->ruta_fisica);
            }

            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_PURGAR,
                FmAuditHelper::ENTIDAD_CARPETA,
                0,
                [
                    'origen'         => 'cron',
                    'dias_corte'     => $dias,
                    'archivos_count' => $archivos->count(),
                    'carpetas_count' => $carpetas->count(),
                ],
                null
            );

            $log->logInfo(
                self::class,
                'Purga papelera: ' . $archivos->count() . ' archivos, ' . $carpetas->count() . ' carpetas'
            );

            $this->info('Purga completada.');
            return self::SUCCESS;
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en purga de papelera', $e);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}

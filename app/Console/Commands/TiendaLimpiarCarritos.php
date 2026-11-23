<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra los carritos abandonados de la tienda: la base decide cuáles (ecommerce.fn_carrito_limpiar: sin cambios en 30
 * días si es de invitado, 90 si es de un cliente). Corre todos los días desde el scheduler (app/Console/Kernel.php).
 *
 *   php artisan tienda:limpiar-carritos
 */
class TiendaLimpiarCarritos extends Command
{
    protected $signature = 'tienda:limpiar-carritos';

    protected $description = 'Borra los carritos de la tienda abandonados (invitado: 30 días sin cambios; cliente: 90).';

    public function handle(): int
    {
        $borrados = (int) DB::selectOne('SELECT ecommerce.fn_carrito_limpiar() AS n')->n;
        $this->info(now()->format('Y-m-d H:i:s') . " carritos abandonados borrados: {$borrados}");

        return self::SUCCESS;
    }
}

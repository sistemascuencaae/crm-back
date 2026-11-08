<?php

namespace App\Console\Commands;

use App\Servicios\PaginaWeb\ProductoTiendaService;
use Illuminate\Console\Command;

/**
 * Renueva la caché de los productos publicados de la tienda (consulta del ERP de ~6 s), para que ninguna visita la pague.
 * Programado cada 5 minutos en App\Console\Kernel; la caché dura 15, así no vence mientras el programador corra.
 *
 *   php artisan pagina-web:renovar-publicados
 */
class RenovarPublicados extends Command
{
    protected $signature = 'pagina-web:renovar-publicados';

    protected $description = 'Renueva la caché de los productos publicados de la tienda (15 minutos).';

    public function handle(ProductoTiendaService $productos): int
    {
        $inicio = microtime(true);
        $productos->renovarPublicados();

        $this->info(sprintf('Publicados renovados: %d productos en %.1f s.', count($productos->publicados()['productos']), microtime(true) - $inicio));

        return self::SUCCESS;
    }
}

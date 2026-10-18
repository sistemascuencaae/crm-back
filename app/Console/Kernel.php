<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\ServicioEndPoint::class, // JGSJ - registro la clase para que se ejecute
        \App\Console\Commands\FmPurgarPapelera::class,
        \App\Console\Commands\FmLimpiarHuerfanos::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly(); // Esta linea ya estaba por default aqui comentado



        // comando para ejecutar el ServicioEndPoint         php artisan schedule:work    
        // JGSJ Ejecutar cada 1 minuto
        // $schedule->command('servicioEndPoint')->everyMinute(); // Podemos ejecutar esta linea corta si no necesitamos guardar la respuesta del endPoint en un log
        $schedule->command('servicioEndPoint')->cron('0 */2 * * *')->withoutOverlapping()->sendOutputTo(storage_path('logs/scheduler.log')); // es para que se guarde la respuesta del endPoint en este archivo scheduler

        // Purga diaria de papelera del File Manager (items > 30 días). 02:00 AM.
        $schedule->command('fm:purgar-papelera --dias=30')
            ->cron('0 2 * * *')
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/fm-purgar-papelera.log'));

        // Limpieza semanal de archivos físicos sin registro DB (huérfanos). Domingo 03:00 AM.
        $schedule->command('fm:limpiar-huerfanos --apply')
            ->cron('0 3 * * 0')
            ->withoutOverlapping()
            ->sendOutputTo(storage_path('logs/fm-limpiar-huerfanos.log'));
        // Comandos de tiempo
            // ->hourly()
            // ->everyFiveMinutes()
            // ->daily()
            // ->cron('*/30 * * * *') // cada 30 minutos exactos


            // JGSJ CONFIG CRON
            // ->cron('0 */2 * * *') // Cada 2 horas en punto: 00:00, 02:00, 04:00, etc.
            // Minuto   Hora   DíaMes   Mes   DíaSemana
            //   0       */2     *       *        *

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

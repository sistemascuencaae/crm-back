<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ServicioEndPoint extends Command
{
    protected $signature = 'servicioEndPoint';
    protected $description = 'Es para que se ejecute cada cierto tiempo un endPoint que necesitemos'; // JGSJ

    public function handle()
    {
        // JGSJ 
        $this->info("Ejecutando endpoint... ");

        try {
            $response = Http::get('http://192.168.1.142:8009/api/crm/addCasoOscarBravo'); // desarrollo
            // $response = Http::get('http://api.almacenesespana.ec/api/crm/addCasoOscarBravo'); // produccion
            $this->info("Respuesta... " . $response->body());
        } catch (\Exception $e) {
            $this->error("Error al llamar el endpoint: " . $e->getMessage());
        }
    }
}

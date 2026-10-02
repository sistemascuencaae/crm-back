<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcesarClienteReiterativo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $identificacion;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($identificacion)
    {
        $this->identificacion = $identificacion;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            DB::transaction(function () {
                DB::delete("DELETE from crm.data_temp_cli_reiterativo where ent_identificacion = ?;", [$this->identificacion]);
                DB::selectOne("SELECT crm.fun_insert_ttemp_clireitera(?);", [$this->identificacion]);
            });
        } catch (\Exception $e) {
            Log::error('Error en ProcesarClienteReiterativo: ' . $e->getMessage());
        }
    }

}

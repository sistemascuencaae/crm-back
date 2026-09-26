<?php

namespace App\Http\Controllers\crm\series;

use App\Http\Controllers\Controller;
use App\Models\crm\series\RespaldoSeriesElim;
use Illuminate\Support\Facades\Auth;

class VariosSeries extends Controller
{
    public function resEliminaDespacho($data)
    {

        $existe = RespaldoSeriesElim::where('cfa_id',$data->cfa_id)->first();
        if (!$existe) {
            RespaldoSeriesElim::create([
                'cfa_id' => $data->cfa_id,
                'des_id' => $data->id,
                'ubicacion' => 'DESPACHO NCE',
                'fecha' => $data->fecha,
                'numero' => $data->numero,
                'linea' => null,
                'bod_id' => $data->bod_id,
                'user_id_elimina' => Auth::id(),
                'usuario_crea' => $data->usuario_crea
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\SeriesGeneradas;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SeriesGeneradasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addSeriesGeneradas(Request $request)
    {
        try {
            $exitoso = null;
            $error = null;

            $data = DB::transaction(function () use ($request, &$exitoso, &$error) {
                // Lista para guardar las series repetidas que exitan en alguna tabla
                $seriesRepetidas = [];

                // Recorremos las series generadas que viene del frontEnd
                foreach ($request->seriesGeneradas as $item) {
                    $serie = $item['serie'];

                    // Verificamos si la serie existe en alguna tabla
                    $productoSerie = DB::selectOne("select p.serie from gex.producto_serie p where p.serie = ?", [$serie]);
                    $stockSerie = DB::selectOne("select s.serie from gex.stock_serie s where s.serie = ?", [$serie]);
                    $dinventario = DB::selectOne("select d.serie from gex.dinventario d where d.serie = ?", [$serie]);
                    $dpreingreso = DB::selectOne("select d.serie from gex.dpreingreso d where d.serie = ?", [$serie]);
                    $ddespacho = DB::selectOne("select d.serie from gex.ddespacho d where d.serie = ?", [$serie]);
                    $seriesGeneradas = DB::selectOne("select s.serie from crm.series_generadas s where s.serie = ?", [$serie]);

                    // Si la serie existe en alguna tabla, la agregamos al array de serie repetidas
                    if ($productoSerie || $stockSerie || $dinventario || $dpreingreso || $ddespacho || $seriesGeneradas) {
                        $seriesRepetidas[] = $serie;
                    }
                }

                // Si hay series existentes, retornamos un mensaje de error
                if (!empty ($seriesRepetidas)) {
                    $seriesRepetidasString = implode(", ", $seriesRepetidas);

                    $error = 'Estas series ya existen: ' . $seriesRepetidasString;
                    return null;

                } else {
                    // Si no hay series existentes, creamos todas las nuevas series
                    foreach ($request->seriesGeneradas as $item) {
                        SeriesGeneradas::create([
                            'serie' => $item['serie']
                        ]);
                    }

                    $exitoso = 'Se guardó con éxito';
                    return null;
                }

            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else if ($exitoso) {
                return response()->json(RespuestaApi::returnResultado('success', $exitoso, ''));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}
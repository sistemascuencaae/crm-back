<?php

namespace App\Http\Controllers\crm\seriesalm;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RespuestaApi;
use App\Http\Controllers\Controller;
use App\Models\crm\seriesalm\SerieInventario;

class StockProSerieController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                'loadInitialData',
            ]
        ]);
    }

    public function loadInitialData($bodId)
    {
        //(select count(serie) from crm.stock_pro_serie ss where ss.pro_id = sbo.pro_id and ss.bod_id = sbo.bod_id )
        try {
            $bodega = DB::selectOne("SELECT bod_id, bod_nombre, ubi_nombre from public.bodega b
            left join public.ubicacion u on u.ubi_id = b.ubi_id where bod_id = ? limit 1;", [$bodId]);

            $datosSeries = DB::select("SELECT tt.*, (stock_actual-stock_serie) as diferencia from (
            select pro_id, pro_codigo, pro_nombre, bod_id, bodega, stock_actual,
            (select count(serie) from crm.stock_pro_serie ss where ss.pro_id = sbo.pro_id and ss.bod_id = sbo.bod_id ) as stock_serie
            from av_stock_producto_bodega sbo) tt where tt.bod_id = ?", [$bodId]);

            $data = (object) [
                "bodega" => $bodega,
                "productoSeries" => $datosSeries
            ];


            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }

    public function listSeries($pro_id, $bod_id)
    {
        try {
            $series = SerieInventario::where('pro_id', $pro_id)->where('bod_id', $bod_id)->orderBy('id', 'asc')->get();
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $series));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function addSeriesInv(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->input('data'); // Recibe los datos del frontend
            $eliminados = $request->input('eliminados'); // Datos de los elementos eliminados

            if ($data) {
                // Guardaremos las series duplicadas para devolverlas en la respuesta
                $duplicadas = [];

                // Recorremos las series enviadas en el array
                foreach ($data as $item) {
                    // Si el item tiene un ID (es decir, es una actualización)
                    if (isset($item['id']) && $item['id'] !== null) {
                        // Buscar la serie con ese ID
                        $serieExistente = SerieInventario::find($item['id']);

                        if ($serieExistente) {
                            // Verificar si la serie que intentas actualizar ya tiene el mismo nombre en la base de datos
                            if (SerieInventario::where('serie', $item['serie'])->where('id', '!=', $item['id'])->exists()) {
                                // Si existe otra serie con el mismo nombre, la agregamos a la lista de duplicadas
                                $duplicadas[] = $serieExistente;
                            } else {
                                // Actualizamos la serie existente
                                $serieExistente->update([
                                    'bod_id' => $item['bod_id'],
                                    'pro_id' => $item['pro_id'],
                                    'serie' => $item['serie'],
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    } else {
                        // Si el id es null, insertamos una nueva serie
                        $serieExistente = SerieInventario::where('serie', $item['serie'])->first();

                        if ($serieExistente) {
                            // Si la serie ya existe, la agregamos a la lista de duplicadas
                            $duplicadas[] = $serieExistente;
                        } else {
                            // Si no existe, creamos la serie
                            SerieInventario::create([
                                'bod_id' => $item['bod_id'],
                                'pro_id' => $item['pro_id'],
                                'serie' => $item['serie'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }

                // Si encontramos series duplicadas, las retornamos
                if (count($duplicadas) > 0) {
                    return response()->json(RespuestaApi::returnResultado('error', 'Algunas series ya existen', $duplicadas));
                }
            }

            // Aquí podrías manejar los registros eliminados si es necesario
            if ($eliminados) {
                foreach ($eliminados as $item) {
                    $serie = SerieInventario::find($item['id']);
                    if ($serie) {
                        $serie->delete();
                    }
                }
            }

            DB::commit(); // Commit the transaction after the operation

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', []));
        } catch (Exception $e) {
            DB::rollback(); // Si ocurre un error, se hace rollback de la transacción

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()));
        }
    }






}

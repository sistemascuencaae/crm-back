<?php

namespace App\Http\Controllers\crm\seriesalm;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RespuestaApi;
use App\Http\Controllers\Controller;
use App\Models\crm\seriesalm\BitacoraSeries;
use App\Models\crm\seriesalm\ContratoGexCRM;
use App\Models\crm\seriesalm\SerieInventario;
use Illuminate\Support\Facades\Auth;

class StockProSerieController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                //'loadInitialData',
                'addSeriesInv'
            ]
        ]);
    }

    public function loadInitialData($bodId)
    {
        //(select count(serie) from crm.stock_pro_serie ss where ss.pro_id = sbo.pro_id and ss.bod_id = sbo.bod_id )
        try {
            $bodUserId = Auth::id();
            $bodId = DB::selectOne("SELECT bod_id FROM crm.users where id = ?;", [$bodUserId]);
            $bodega = DB::selectOne("SELECT bod_id, bod_nombre, ubi_nombre from public.bodega b
            left join public.ubicacion u on u.ubi_id = b.ubi_id where bod_id = ? limit 1;", [$bodId->bod_id]);
            $datosSeries = $this->listarSaldos($bodId);
            $data = (object) [
                "bodega" => $bodega,
                "productoSeries" => $datosSeries
            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }


    public function getReporteBodegas()
    {
        //(select count(serie) from crm.stock_pro_serie ss where ss.pro_id = sbo.pro_id and ss.bod_id = sbo.bod_id )
        try {
            // $datosSeries = DB::select("SELECT distinct(tt.bodega),bod_id, bodega, sum(stock_actual) as stock_productos, sum(stock_serie) as stock_series from
            //                     av_stock_producto_bodega_sinregalos_v3 tt WHERE  bod_id not in ( 16, 47, 50, 60, 61, 181, 182, 200, 209, 211, 225, 204, 208, 240, 241, 242, 243, 244) group by 1, 2, 3;");

            $datosSeries = DB::select("SELECT 
                                                    DISTINCT(tt.bodega), 
                                                    bod_id, 
                                                    bodega, 
                                                    SUM(stock_actual) AS stock_productos, 
                                                    SUM(stock_serie) AS stock_series, 
                                                    SUM(stock_actual - stock_serie) AS diferencia,
                                                    CAST(SUM(CASE WHEN (stock_actual - stock_serie) < 0 THEN (stock_actual - stock_serie) * -1 ELSE 0 END) AS INTEGER) AS excedente,
                                                    CAST(SUM(CASE WHEN (stock_actual - stock_serie) > 0 THEN (stock_actual - stock_serie) ELSE 0 END) AS INTEGER) AS faltante,
                                                    CAST(SUM(stock_actual) - 
                                                    (
                                                        SUM(CASE WHEN (stock_actual - stock_serie) < 0 THEN (stock_actual - stock_serie) ELSE 0 END) * -1 +
                                                        SUM(CASE WHEN (stock_actual - stock_serie) > 0 THEN (stock_actual - stock_serie) ELSE 0 END)
                                                    ) AS INTEGER) AS total_satisfactorio
                                                FROM 
                                                    av_stock_producto_bodega_sinregalos_v3 tt 
                                                WHERE  
                                                    bod_id NOT IN (16, 50, 60, 61, 181, 200, 209, 211, 225, 204, 208, 240, 241, 242, 243, 244, 210) 
                                                GROUP BY 
                                                    1, 2, 3
                                                ORDER BY bodega ASC;
                                                ");

            $data = (object) [
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

            $duplicadas = []; // Array para almacenar las series duplicadas
            $eliminarSeries = $request->input('eliminarSeries'); // Series a eliminar
            $nuevasSeries = $request->input('nuevasSeries'); // Nuevas series a agregar
            $seriesEliminadas = $request->input('seriesEliminadas');
            $bod_id = $request->input('bod_id'); // ID de la bodega

            if ($seriesEliminadas) {
                foreach ($seriesEliminadas as $item) {
                    $serie = SerieInventario::find($item['id']);
                    if ($serie) {
                        $this->validacionesBitacora('ELIMINADO', $item['serie'], $serie);
                    }
                }
            }

            // Validación de eliminación de series
            if ($eliminarSeries && count($eliminarSeries) > 0) {
                foreach ($eliminarSeries as $item) {
                    $serie = SerieInventario::find($item['id']);
                    if ($serie) {

                        $serie->delete();
                    }
                }
            }


            // Validación de nuevas series para agregar
            $insertiBitacora = [];
            if ($nuevasSeries && count($nuevasSeries) > 0) {
                // Recorrer las nuevas series para detectar duplicados antes de insertar
                foreach ($nuevasSeries as $item) {
                    // Verificar si ya existe la serie en la base de datos
                    $serieExistente = SerieInventario::where('serie', $item['serie'])->with('producto')->with('bodega')->first();
                    $serieExisteContrato = ContratoGexCRM::where('serie', $item['serie'])->with('producto')->with('bodega')->first();


                    if ($serieExistente || $serieExisteContrato) {
                        // Si la serie ya existe, agregarla al array de duplicadas

                        if ($serieExisteContrato) {
                            $duplicadas[] = $serieExisteContrato;
                        } else {
                            $duplicadas[] = $serieExistente;
                        }
                    } else {
                        SerieInventario::create([
                            'bod_id' => $item['bod_id'],
                            'pro_id' => $item['pro_id'],
                            'serie' => $item['serie']
                        ]);
                        $insertiBitacora[] = [
                            'bod_id' => $item['bod_id'],
                            'pro_id' => $item['pro_id'],
                            'serie' => $item['serie']
                        ];
                    }
                }

                // Si se encontraron series duplicadas, no continuamos con la inserción
                if (count($duplicadas) > 0) {
                    // Rollback de la transacción si se encontraron duplicados
                    DB::rollback();
                    return response()->json(RespuestaApi::returnResultado('error', 'Las siguientes series ya existen', $duplicadas));
                } else {
                    foreach ($insertiBitacora as $item) {
                        $this->validacionesBitacora('INGRESO', $item['serie'], null);
                    }
                }

                // Si no hay duplicados, entonces insertamos las nuevas series
                // foreach ($nuevasSeries as $item) {
                //     SerieInventario::create([
                //         'bod_id' => $item['bod_id'],
                //         'pro_id' => $item['pro_id'],
                //         'serie' => $item['serie']
                //     ]);
                // }
            }




            DB::commit(); // Commit de la transacción

            // Si no hubo duplicados, devolvemos el mensaje de éxito
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $this->listarSaldos($bod_id)));
        } catch (Exception $e) {
            DB::rollback(); // Si ocurre un error, hacemos rollback

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()));
        }
    }


    public function getSerieDesCliente($serie, $bodId)
    {
        try {
            $data = DB::selectOne("SELECT ss.serie, p.pro_codigo, p.pro_nombre from crm.stock_pro_serie ss
                 inner join public.producto p on p.pro_id = ss.pro_id and ss.bod_id = ? where serie = ?;", [$bodId, $serie]);

            if ($data) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Serie no existe.', []));
            }
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }

    public function getSerie($serie)
    {
        try {
            $data = DB::selectOne("SELECT ss.serie, p.pro_codigo, p.pro_nombre from crm.stock_pro_serie ss
                 inner join public.producto p on p.pro_id = ss.pro_id where serie = ?;", [$serie]);

            if ($data) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Serie no existe.', []));
            }
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }

    public function despacharSerie($serie, $bodId)
    {
        try {
            $serieExiste = DB::selectOne("SELECT ss.id, ss.serie, bod_id from crm.stock_pro_serie ss where serie = ?;", [$serie]);
            if (!$serieExiste) {
                $serie = strtoupper($serie); // Convertir a mayúsculas
                $serieExiste = DB::selectOne("SELECT ss.id, ss.serie, bod_id FROM crm.stock_pro_serie ss
                           WHERE UPPER(serie) = ?;", [$serie]);
            }
            if ($serieExiste) {
                $userId = Auth::id();
                $bodIdUser = DB::selectOne("SELECT * from crm.users where id = ?;", [$userId]);
                if ($bodIdUser) {
                    if ($bodIdUser->bod_id == $serieExiste->bod_id || $bodIdUser->bod_id_dos == $serieExiste->bod_id || $bodIdUser->bod_id_tres == $serieExiste->bod_id) {
                        $data = DB::transaction(function () use ($serieExiste, $bodId) {
                            $this->validacionesBitacora('SALIDA SERIE', $serieExiste->serie, null);
                            DB::delete("DELETE FROM crm.stock_pro_serie WHERE id = ?;", [$serieExiste->id]);
                            $saldoSeries = $this->listarSaldos($bodId);
                            return $saldoSeries;
                        });
                    } else {
                        return response()->json(RespuestaApi::returnResultado('error', 'Error bodega no asignada.', []));
                    }
                } else {
                    return response()->json(RespuestaApi::returnResultado('error', 'Error permisos de usuario.', []));
                }
                // FELIPE
                return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con exito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Serie no existe.', []));
            }
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }


    public function ingresarSerieTransferencia($serie, $bodId)
    {
        try {

            $data = DB::transaction(function () use ($serie, $bodId) {
                $dataSerie = DB::selectOne("SELECT  * from crm.stock_pro_serie ss where bod_id = ? and serie = ?", [$bodId, $serie]);
                if ($dataSerie) {
                    DB::update("UPDATE crm.stock_pro_serie SET bod_id = ? WHERE id = ?;", [$bodId, $dataSerie->id]);
                } else {
                    DB::insert("INSERT INTO crm.stock_pro_serie (bod_id, pro_id, serie, created_at, updated_at)
                        VALUES(?, ?, ?, CURRENT_DATE, CURRENT_DATE);", [$bodId, $dataSerie->pro_id, $serie]);
                }
                $saldoSeries = $this->listarSaldos($bodId);
                return $saldoSeries;
            });

            if ($data) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con exito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Serie no existe.', []));
            }
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }


    private function listarSaldos($bodId)
    {

        $userId = Auth::id();
        $bodUser = DB::selectOne("SELECT bod_id, bod_id_dos, bod_id_tres from crm.users where id = ?;", [$userId]);

        $idBodegas = [];

        if ($bodUser->bod_id) {
            array_push($idBodegas, $bodUser->bod_id);
        }
        if ($bodUser->bod_id_dos) {
            array_push($idBodegas, $bodUser->bod_id_dos);
        }
        if ($bodUser->bod_id_tres) {
            array_push($idBodegas, $bodUser->bod_id_tres);
        }

        if (!empty($idBodegas)) {
            // Construye la lista de IDs separada por comas
            $placeholders = implode(',', array_fill(0, count($idBodegas), '?'));
            $datosSeries = DB::select("SELECT * FROM av_stock_producto_bodega_sinregalos_v3 WHERE BOD_ID IN ($placeholders) order by pro_codigo ASC", $idBodegas);
            //tt ORDER BY tt.stock_actual DESC
        } else {
            $datosSeries = []; // Si no hay bodegas, retorna un array vacío
        }

        return $datosSeries;
    }


    public function reportePorBodegaId($bodId)
    {
        try {
            // $datosSeries = DB::select("SELECT * FROM av_stock_producto_bodega_sinregalos_v3 WHERE  bod_id = ? order by stock_actual desc", [$bodId]);
            $datosSeries = DB::select("SELECT * FROM av_stock_producto_bodega_sinregalos_v3 WHERE  bod_id = ? order by pro_codigo ASC", [$bodId]);
            $bodega = DB::selectOne("SELECT bod_id, bod_nombre, ubi_nombre from public.bodega b
            left join public.ubicacion u on u.ubi_id = b.ubi_id where bod_id = ? limit 1;", [$bodId]);
            $data = (object) [
                "bodega" => $bodega,
                "productoSeries" => $datosSeries
            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }

    public function validacionesBitacora($accion, $serie, $datosSerie)
    {
        $serieMayu = strtoupper($serie);

        if ($accion == 'ELIMINADO' && $datosSerie) {
            $nuevaDataBitacora = (object)[
                "serie" => $serie,
                "bod_id" => $datosSerie["bod_id"],
                "pro_id" => $datosSerie["pro_id"],
                "accion" => 'ELIMINADO',
            ];
            BitacoraSeries::create((array)$nuevaDataBitacora);
            return true;
        }

        $serieStockProSerie = DB::selectOne("SELECT * FROM crm.stock_pro_serie WHERE UPPER(serie) = ? limit 1;", [$serieMayu]);
        if ($serieStockProSerie) {
            $nuevaDataBitacora = (object)[
                "serie" => $serie,
                "bod_id" => $serieStockProSerie->bod_id,
                "pro_id" => $serieStockProSerie->pro_id,
                "accion" => null
            ];


            if ($accion == 'INGRESO') {
                $nuevaDataBitacora->accion = 'INGRESO';
                $validarEliminadaAntes = DB::selectOne(
                    "SELECT * FROM crm.bitacora_series
                                        WHERE UPPER(serie) = ? and pro_id = ? and accion = 'ELIMINADO'
                                        order by created_at desc limit 1",
                    [$serieMayu, $serieStockProSerie->pro_id]
                );
                // Si encuentra una serie eliminada con los mismos valores, elimino esa serie de la bitácora
                if ($validarEliminadaAntes) {
                    if ($validarEliminadaAntes->bod_id == $serieStockProSerie->bod_id) {
                        DB::delete("DELETE from crm.bitacora_series WHERE id = ?;", [$validarEliminadaAntes->id]);
                    }
                    BitacoraSeries::create((array)$nuevaDataBitacora);
                    return true;
                }
                $serieExisteBitacora = BitacoraSeries::where(DB::raw("UPPER(serie)"), $serieMayu)
                    ->where("bod_id", $serieStockProSerie->bod_id)
                    ->where("pro_id", $serieStockProSerie->pro_id)
                    ->first();

                if (!$serieExisteBitacora) {
                    BitacoraSeries::create((array)$nuevaDataBitacora);
                    return true;
                }
            }

            if ($accion == 'SALIDA SERIE') {
                $nuevaDataBitacora->accion = $accion;
                BitacoraSeries::create((array)$nuevaDataBitacora);
                return true;
            }
            return false;
        } else {
            return false;
        }
    }


    public function buscarProCodigo($proCodigo)
    {
        try {
            $data = DB::selectOne("SELECT * FROM public.producto where pro_codigo = ?",[$proCodigo]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }
}

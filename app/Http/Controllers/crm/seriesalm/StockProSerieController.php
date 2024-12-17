<?php

namespace App\Http\Controllers\crm\seriesalm;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RespuestaApi;
use App\Http\Controllers\Controller;
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
            $datosSeries = DB::select("SELECT distinct(tt.bodega),bod_id, bodega, sum(stock_actual) as stock_productos, sum(stock_serie) as stock_series from
                                 av_stock_producto_bodega_sinregalos_v3 tt WHERE  bod_id not in ( 16,47,50,60,61,181,182,200,209,211, 225) group by 1,2,3;");

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

    // Este metodo crea, actualiza y elimina las series
    // public function addSeriesInv(Request $request)
    // {
    //     try {
    //         DB::beginTransaction();

    //         $data = $request->input('data'); // Recibe los datos del frontend
    //         $eliminados = $request->input('eliminados'); // Datos de los elementos eliminados

    //         $bod_id = $data[0]['bod_id'];

    //         if ($data) {
    //             // Guardaremos las series duplicadas para devolverlas en la respuesta
    //             $duplicadas = [];

    //             // Recorremos las series enviadas en el array
    //             foreach ($data as $item) {
    //                 // Si el item tiene un ID (es decir, es una actualización)
    //                 if (isset($item['id']) && $item['id'] !== null) {
    //                     // Buscar la serie con ese ID
    //                     $serieExistente = SerieInventario::find($item['id']);

    //                     if ($serieExistente) {
    //                         // Verificar si la serie que intentas actualizar ya tiene el mismo nombre en la base de datos
    //                         if (SerieInventario::where('serie', $item['serie'])->where('id', '!=', $item['id'])->exists()) {
    //                             // Si existe otra serie con el mismo nombre, la agregamos a la lista de duplicadas
    //                             $duplicadas[] = $serieExistente;
    //                         } else {
    //                             // Actualizamos la serie existente
    //                             $serieExistente->update([
    //                                 'bod_id' => $item['bod_id'],
    //                                 'pro_id' => $item['pro_id'],
    //                                 'serie' => $item['serie'],
    //                                 'updated_at' => now(),
    //                             ]);
    //                         }
    //                     }
    //                 } else {
    //                     // Si el id es null, insertamos una nueva serie
    //                     $serieExistente = SerieInventario::where('serie', $item['serie'])->first();

    //                     if ($serieExistente) {
    //                         // Si la serie ya existe, la agregamos a la lista de duplicadas
    //                         $duplicadas[] = $serieExistente;
    //                     } else {
    //                         // Si no existe, creamos la serie
    //                         SerieInventario::create([
    //                             'bod_id' => $item['bod_id'],
    //                             'pro_id' => $item['pro_id'],
    //                             'serie' => $item['serie'],
    //                             'created_at' => now(),
    //                             'updated_at' => now(),
    //                         ]);
    //                     }
    //                 }
    //             }

    //             // Si encontramos series duplicadas, las retornamos
    //             if (count($duplicadas) > 0) {
    //                 return response()->json(RespuestaApi::returnResultado('error', 'Las siguientes series ya existen', $duplicadas));
    //             }
    //         }

    //         // Aquí podrías manejar los registros eliminados si es necesario
    //         if ($eliminados) {
    //             foreach ($eliminados as $item) {
    //                 $serie = SerieInventario::find($item['id']);
    //                 if ($serie) {
    //                     $serie->delete();
    //                 }
    //             }
    //         }

    //         DB::commit(); // Commit the transaction after the operation

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $this->listarSaldos($bod_id)));
    //     } catch (Exception $e) {
    //         DB::rollback(); // Si ocurre un error, se hace rollback de la transacción

    //         return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar', $e->getMessage()));
    //     }
    // }

    public function addSeriesInv(Request $request)
    {
        try {
            DB::beginTransaction();

            $duplicadas = []; // Array para almacenar las series duplicadas
            $eliminarSeries = $request->input('eliminarSeries'); // Series a eliminar
            $nuevasSeries = $request->input('nuevasSeries'); // Nuevas series a agregar
            $bod_id = $request->input('bod_id'); // ID de la bodega

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
            if ($nuevasSeries && count($nuevasSeries) > 0) {
                // Recorrer las nuevas series para detectar duplicados antes de insertar
                foreach ($nuevasSeries as $item) {
                    // Verificar si ya existe la serie en la base de datos
                    $serieExistente = SerieInventario::where('serie', $item['serie'])->with('producto')->with('bodega')->first();
                    $serieExisteContrato = ContratoGexCRM::where('serie', $item['serie'])->with('producto')->with('bodega')->first();

                    if ($serieExistente || $serieExisteContrato) {
                        // Si la serie ya existe, agregarla al array de duplicadas

                        if($serieExisteContrato){
                            $duplicadas[] = $serieExisteContrato;
                        }else{
                            $duplicadas[] = $serieExistente;
                        }

                    } else {
                        SerieInventario::create([
                            'bod_id' => $item['bod_id'],
                            'pro_id' => $item['pro_id'],
                            'serie' => $item['serie']
                        ]);
                    }
                }

                // Si se encontraron series duplicadas, no continuamos con la inserción
                if (count($duplicadas) > 0) {
                    // Rollback de la transacción si se encontraron duplicados
                    DB::rollback();
                    return response()->json(RespuestaApi::returnResultado('error', 'Las siguientes series ya existen', $duplicadas));
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
                    if ($bodIdUser->bod_id == $serieExiste->bod_id || $bodIdUser->bod_id_dos == $serieExiste->bod_id) {
                        $data = DB::transaction(function () use ($serieExiste, $bodId) {
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
        $bodUser = DB::selectOne("SELECT bod_id,bod_id_dos from crm.users where id = ?;", [$userId]);

        $idBodegas = [];

        if ($bodUser->bod_id) {
            array_push($idBodegas, $bodUser->bod_id);
        }
        if ($bodUser->bod_id_dos) {
            array_push($idBodegas, $bodUser->bod_id_dos);
        }

        if (!empty($idBodegas)) {
            // Construye la lista de IDs separada por comas
            $placeholders = implode(',', array_fill(0, count($idBodegas), '?'));
            $datosSeries = DB::select("SELECT * FROM av_stock_producto_bodega_sinregalos_v3 WHERE BOD_ID IN ($placeholders)", $idBodegas);
            //tt ORDER BY tt.stock_actual DESC
        } else {
            $datosSeries = []; // Si no hay bodegas, retorna un array vacío
        }

        return $datosSeries;
    }


    public function reportePorBodegaId($bodId)
    {
        try {
            $datosSeries = DB::select("SELECT * FROM av_stock_producto_bodega_sinregalos_v3 WHERE  bod_id = ? order by  stock_actual desc", [$bodId]);
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
}

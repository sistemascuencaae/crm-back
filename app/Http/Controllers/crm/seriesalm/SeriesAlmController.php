<?php

namespace App\Http\Controllers\crm\seriesalm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\garantias\ContratoGex;
use App\Models\crm\series\Despacho;
use App\Models\crm\series\Inventario;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeriesAlmController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // borrar un inventario completo en el caso de que no haya despachos ni contratos gex (si funciona si esta procesado el inventario)
    public function borrarInventarioCompleto($numero_inventario)
    {
        try {
            $data = DB::transaction(function () use ($numero_inventario) {

                $inventario = DB::selectOne("SELECT * from gex.cinventario g where g.numero = $numero_inventario;");

                if ($inventario) {
                    // Actualizar estado del inventario a 'D' (o lo que necesites)
                    // DB::update("UPDATE gex.cinventario set estado = 'D' where numero = $numero_inventario;"); // solo desactiva el inventario

                    $itemsDInventario = DB::select("SELECT * from gex.dinventario g where g.numero = $numero_inventario;");

                    foreach ($itemsDInventario as $valor) {
                        // Verificar si existe un contrato_gex para el producto y serie
                        $existeContratos = DB::select("SELECT * from gex.contrato_gex g where serie like '%$valor->serie%' and pro_id = $valor->pro_id;");

                        // Verificar si existe un registro en ddespacho para el producto y serie
                        $existeDespachos = DB::select("SELECT * from gex.ddespacho g where pro_id = $valor->pro_id and serie = '$valor->serie' and tipo = '$valor->tipo';");

                        // Si no hay contrato, proceder con eliminaciones
                        if (!$existeContratos && !$existeDespachos) {

                            // borramos todos los items o series del inventario
                            DB::delete("DELETE from gex.stock_serie where pro_id = $valor->pro_id and serie = '$valor->serie' and tipo = '$valor->tipo' and bod_id = $inventario->bod_id;");

                            DB::delete("DELETE from gex.producto_serie where pro_id = $valor->pro_id and serie = '$valor->serie' and tipo = '$valor->tipo';");

                            DB::delete("DELETE from gex.dinventario where numero = $valor->numero and pro_id = $valor->pro_id and serie = '$valor->serie' and tipo = '$valor->tipo';");
                            // end borramos todos los items o series del inventario

                        } else {

                            // Construir objeto con contratos y despachos existentes
                            $existentes = [
                                'contratos' => $existeContratos,
                                'despachos' => $existeDespachos
                            ];

                            // Construir mensaje de error adecuadamente
                            $mensajeError = 'Existe ';
                            if ($existeContratos) {
                                $mensajeError .= count($existeContratos) . ' contratos, ';
                                // $mensajeError .= 'contratos: ' . json_encode($existeContratos);
                            }
                            if ($existeDespachos) {
                                $mensajeError .= count($existeDespachos) . ' despachos';
                            }
                            $mensajeError .= ' en este inventario.';

                            return response()->json(RespuestaApi::returnResultado('error', $mensajeError, $existentes));
                        }
                    }

                    DB::delete("DELETE from gex.cinventario where numero = $numero_inventario;"); // BORRAMOS LA CABECERA DEL INVENTARIO

                    return response()->json(RespuestaApi::returnResultado('success', 'Se elimino correctamente el inventario con el número: ' . $numero_inventario, ''));
                } else {
                    return response()->json(RespuestaApi::returnResultado('error', 'No existe el inventario con el número: ' . $numero_inventario, ''));
                }
            });

            return $data;
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // listar las bodegas activas
    public function listBodegas()
    {
        try {
            $data = DB::select("SELECT * from public.bodega b where b.bod_activo = true order by b.bod_nombre asc;");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // buscar los inventarios por bodega
    public function inventariosByBod_id($bod_id)
    {
        try {
            $data = Inventario::where('bod_id', $bod_id)
                ->with('detalle.producto', 'bodega')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // borrar un item de un inventario
    public function borrarItemInventario($numero_inventario, $bod_id, $pro_id, $serie, $tipo)
    {
        try {
            $data = DB::transaction(function () use ($numero_inventario, $bod_id, $pro_id, $serie, $tipo) {
                // Verificar si existe un contrato_gex para el producto y serie
                $existeContratos = DB::select("SELECT * from gex.contrato_gex g where serie like '%$serie%' and pro_id = $pro_id;");

                // Verificar si existe un registro en ddespacho para el producto y serie
                $existeDespachos = DB::select("SELECT * from gex.ddespacho g where pro_id = $pro_id and serie = '$serie' and tipo = '$tipo';");

                if (!$existeContratos && !$existeDespachos) {

                    // Si no hay contrato ni despacho, procedemos con la eliminacion de esa serie

                    // borramos todos los items o series del inventario
                    DB::delete("DELETE from gex.stock_serie where pro_id = $pro_id and serie = '$serie' and tipo = '$tipo' and bod_id = $bod_id;");

                    DB::delete("DELETE from gex.producto_serie where pro_id = $pro_id and serie = '$serie' and tipo = '$tipo';");

                    DB::delete("DELETE from gex.dinventario where numero = $numero_inventario and pro_id = $pro_id and serie = '$serie' and tipo = '$tipo';");
                    // end borramos todos los items o series del inventario

                    return response()->json(RespuestaApi::returnResultado('success', 'Se elimino correctamente la serie: ' . $serie, ''));
                } else {

                    // Construir objeto con contratos y despachos existentes
                    $existentes = [
                        'contratos' => $existeContratos,
                        'despachos' => $existeDespachos
                    ];

                    // Construir mensaje de error adecuadamente
                    $mensajeError = 'Existe ';
                    if ($existeContratos) {
                        $mensajeError .= count($existeContratos) . ' contratos, ';
                        // $mensajeError .= 'contratos: ' . json_encode($existeContratos);
                    }
                    if ($existeDespachos) {
                        $mensajeError .= count($existeDespachos) . ' despachos';
                    }
                    $mensajeError .= ' de esta serie.';

                    return response()->json(RespuestaApi::returnResultado('error', $mensajeError, $existentes));
                }
            });

            return $data;
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
    public function saldoProSeries(Request $request)
    {
        // Obtener parámetros del request
        $tiposPro = $request->input('tiposPro');
        $tiposProString = '{' . implode(',', array_map('intval', $tiposPro)) . '}';
        $bodId = $request->input('bodId');
        $periodo = $request->input('periodo');
        $fechaInicio = $request->input('fechaInicio');
        $fechaFin = $request->input('fechaFin');


        // Ejecutar la consulta
        $productos = DB::select("SELECT * FROM gex.obtener_saldo_pro_series( ?, ?, ?, ?, ?::INTEGER[] );", [$periodo, $bodId, $fechaInicio, $fechaFin, $tiposProString]);
        // Convertir a colección y agrupar por pro_codigo
        $productos = collect($productos);

        $data = $productos->groupBy('pro_codigo')->map(function ($items, $pro_codigo) {
            // Tomar el primer item como representativo del producto
            $firstItem = $items->first();
            return [
                'pro_codigo' => $pro_codigo,
                'pro_nombre' => $firstItem->pro_nombre,
                'sumacantidades' => $firstItem->sumacantidades,
                'series' => $items->filter(function ($item) {
                    return !is_null($item->serie);
                })->map(function ($item) {
                    return [
                        'serie' => $item->serie,
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Se listo con exito',
            'data' => $data
        ]);
    }



    public function dataSerieEliminar($serie)
    {
        try {
            $data = DB::select("SELECT tempds.*, (u.name ||' '||surname) as usuario  FROM (SELECT
                1 as orden,
                'INVENTARIO' as ubicacion,
                ci.fecha,
                ci.numero,
                di.linea,
                b.bod_id,
                null as bod_egresa,
                b.bod_nombre as bod_actual,
                ci.usuario_crea as responsable,
                null::INTEGER as alm_id,
                di.pro_id,
                di.serie,
                di.tipo,
                null as doc_rela
                from gex.cinventario ci
                inner join gex.dinventario di on di.numero = ci.numero
                inner join public.bodega b on b.bod_id = ci.bod_id
                where di.serie = ?
                union all
                select
                1 as ordne,
                'PREINGRESO' as ubicacion,
                cp.fecha,
                cp.numero,
                dp.linea,
                b.bod_id,
                b.bod_nombre as bod_egresa,
                null as bod_actual,
                cp.usuario_crea as responsable,
                null::INTEGER as alm_id,
                dp.pro_id,
                dp.serie,
                dp.tipo,
                null as doc_rela
                from gex.cpreingreso cp
                inner join gex.dpreingreso dp on dp.numero = cp.numero
                inner join public.bodega b on b.bod_id = cp.bod_id
                where dp.serie = ?
                union all
                select * from (select
                2 as orden,
                'DESPACHO' as ubicacion,
                cd.fecha,
                cd.numero,
                dd.linea as linea,
                b.bod_id,
                b.bod_nombre as bod_egresa,
                (case
                    when cd.cmo_id is null then
                        (
                            select concat(
                                        e.ent_identificacion, ' - ',
                                        (case
                                            when e.ent_nombres = '' then e.ent_apellidos
                                            else concat(e.ent_nombres, ' ', e.ent_apellidos)
                                        end)
                                )
                            from cfactura c1
                            join cliente l on c1.cli_id = l.cli_id
                            join entidad e on l.ent_id = e.ent_id
                            where c1.cfa_id = cd.cfa_id
                        )
                    else
                        (
                            select b1.bod_nombre
                            from cmovinv c1
                            join bodega b1 on c1.bod_id_fin = b1.bod_id
                            where c1.cmo_id = cd.cmo_id
                        )
                end) as bod_actual,
                cd.usuario_crea as responsable,
                null::INTEGER as alm_id,
                dd.pro_id,
                dd.serie,
                dd.tipo,
                (case when cd.cmo_id is null then (select concat(t.cti_sigla,' - ', a.alm_codigo, ' - ', p.pve_numero, ' - ',  c1.cfa_numero)
                	from cfactura c1 join puntoventa p on c1.pve_id = p.pve_id
                	join almacen a on p.alm_id = a.alm_id
                	join ctipocom t on c1.cti_id = t.cti_id
                	where c1.cfa_id = cd.cfa_id) else (select concat(t.cti_sigla,' - ', p.alm_id, ' - ', p.pve_numero, ' - ',  c1.cmo_numero)
                			from cmovinv c1 join puntoventa p on c1.pve_id = p.pve_id
			                join ctipocom t on c1.cti_id = t.cti_id
            			    where c1.cmo_id = cd.cmo_id) end) as doc_rela
                from gex.cdespacho cd
                inner join gex.ddespacho dd on dd.numero = cd.numero
                inner join public.bodega b on b.bod_id = cd.bod_id
                inner join public.bodega b2 on b2.bod_id = cd.bod_id_origen
                where dd.serie = ? order by numero desc) tempdes
                union all
                select
                3 as orden,
                'CONTRATO GEX' as ubicacion,
                cg.fecha,
                cg.numero,
                null as linea,
                null as bod_id,
                cg.nom_almacen as bod_egresa,
                null as bod_actual,
                cg.usuario_crea as responsable,
                cg.alm_id::INTEGER,
                cg.pro_id,
                cg.serie,
                null as tipo,
                null as doc_rela
                from gex.contrato_gex cg
                where cg.serie = ? ) tempds
                left join crm.users u on u.id = tempds.responsable
                order by 1 desc
                ", [$serie, $serie, $serie, $serie]);


            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminarSerieContrato($alm_id, $numero)
    {
        try {
            ContratoGex::where('alm_id', $alm_id)->where('numero', $numero)->delete();

            return response()->json(RespuestaApi::returnResultado('success', 'Contrato eliminado con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminarSerieDespacho($numeroDes, $serie, $bodId,)
    {
        try {

            $data = DB::transaction(function () use ($numeroDes, $serie, $bodId) {


                $dato = Despacho::with('detalle')->get()->where('numero', $numeroDes)->first();
                $bod_id = $dato['bod_id'];

                $data = $dato['detalle'];

                DB::table('gex.ddespacho')->where('numero', $numeroDes)->where('serie', $serie)->delete();

                foreach ($data as $d) {
                    if ($bodId == 'null') {
                        DB::table('gex.stock_serie')->updateOrInsert(
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                                'tipo' => $d['tipo'],
                            ],
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                                'tipo' => $d['tipo'],
                            ]
                        );
                    } else {
                        DB::table('gex.stock_serie')->updateOrInsert(
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bodId,
                                'tipo' => $d['tipo'],
                            ],
                            [
                                'pro_id' => $d['pro_id'],
                                'serie' => $d['serie'],
                                'bod_id' => $bod_id,
                                'tipo' => $d['tipo'],
                            ]
                        );
                    }
                }

                // $des = DB::selectOne("SELECT * from gex.ddespacho d where d.numero = ? and d.serie = ? and d.tipo = ?", [$numeroDes, $serie, $tipo]);
                // if ($des) {
                //     $eliminarSerie = DB::delete("DELETE FROM gex.ddespacho WHERE numero = ? AND serie = ? and tipo = ?", [$numeroDes, $serie, $tipo]);
                // }
                return $serie;
            });


            return response()->json(RespuestaApi::returnResultado('success', 'Serie eliminada con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
    public function eliminarSerieInventario($numeroInv, $serie, $pro_id, $tipo, $ssBodId)
    {
        try {
            $data = DB::transaction(function () use ($numeroInv, $serie, $pro_id, $tipo, $ssBodId) {
                // $inve = DB::selectOne("SELECT * from gex.producto_serie ps where ps.serie = ?", [$serie]);
                // if ($inve) {
                //eliminar del producto serie
                // $eliminarStockSerie = DB::delete("DELETE FROM gex.stock_serie WHERE pro_id = ? AND serie = ? AND tipo = ?;", [$pro_id, $serie, $tipo]);
                // $eliminarProSerie = DB::delete("DELETE FROM gex.producto_serie WHERE pro_id = ? AND serie= ? AND tipo = ?;", [$pro_id, $serie, $tipo]);
                // $eliminarInventario = DB::delete("DELETE FROM gex.dinventario WHERE numero = ? AND serie = ? AND tipo = ?;", [$numeroInv, $serie, $tipo]);

                $excludedBodId = $ssBodId; // El bod_id que deseas excluir
                // Obtener los datos
                $results = DB::select('SELECT * FROM gex.stock_serie WHERE serie = ?', [$serie]);
                // Guardar los datos en una variable
                $data = collect($results)->map(function ($item) {
                    return (array) $item;
                })->all();
                // Eliminar los registros existentes
                DB::table('gex.stock_serie')->where('serie', $serie)->delete();
                // Filtrar los datos excluyendo el bod_id especificado
                $filteredData = array_filter($data, function ($item) use ($excludedBodId) {
                    return $item['bod_id'] != $excludedBodId;
                });
                // Eliminar los datos de producto_serie
                if (sizeof($filteredData) === 0) {
                    $eliminarProSerie = DB::delete("DELETE FROM gex.producto_serie WHERE pro_id = ? AND serie= ? AND tipo = ?;", [$pro_id, $serie, $tipo]);
                }
                // Insertar los datos filtrados
                DB::table('gex.stock_serie')->insert($filteredData);
                $eliminarInventario = DB::delete("DELETE FROM gex.dinventario WHERE numero = ? AND serie = ? AND tipo = ?;", [$numeroInv, $serie, $tipo]);
                //}
                return $serie;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Serie eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function eliminarSeriePreIngreso($numeroPre, $serie, $pro_id, $tipo, $ssBodId)
    {
        try {
            $data = DB::transaction(function () use ($numeroPre, $serie, $pro_id, $tipo, $ssBodId) {
                //$inve = DB::selectOne("SELECT * from gex.producto_serie ps where ps.serie = ?", [$serie]);
                //if ($inve) {
                    //eliminar del producto serie
                    //$eliminarInventario = DB::delete("DELETE FROM gex.dpreingreso WHERE numero = ? AND serie = ? AND tipo = ?;", [$numeroPre, $serie, $tipo]);
                    //$eliminarStockSerie = DB::delete("DELETE FROM gex.stock_serie WHERE pro_id = ? AND serie = ? AND tipo = ? AND bod_id = ?;", [$pro_id, $serie, $tipo, $ssBodId]);
                    //$eliminarProSerie = DB::delete("DELETE FROM gex.producto_serie WHERE pro_id = ? AND serie= ? AND tipo = ?;", [$pro_id, $serie, $tipo]);

                    $excludedBodId = $ssBodId; // El bod_id que deseas excluir
                    // Obtener los datos
                    $results = DB::select('SELECT * FROM gex.stock_serie WHERE serie = ?', [$serie]);
                    // Guardar los datos en una variable
                    $data = collect($results)->map(function ($item) {
                        return (array) $item;
                    })->all();
                    // Eliminar los registros existentes
                    DB::table('gex.stock_serie')->where('serie', $serie)->delete();
                    // Filtrar los datos excluyendo el bod_id especificado
                    $filteredData = array_filter($data, function ($item) use ($excludedBodId) {
                        return $item['bod_id'] != $excludedBodId;
                    });
                    // Eliminar los datos de producto_serie
                    if (sizeof($filteredData) === 0) {
                        $eliminarProSerie = DB::delete("DELETE FROM gex.producto_serie WHERE pro_id = ? AND serie= ? AND tipo = ?;", [$pro_id, $serie, $tipo]);
                    }
                    // Insertar los datos filtrados
                    DB::table('gex.stock_serie')->insert($filteredData);
                    $eliminarInventario = DB::delete("DELETE FROM gex.dinventario WHERE numero = ? AND serie = ? AND tipo = ?;", [$numeroPre, $serie, $tipo]);
                //}
                return $serie;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Serie eliminada con exito', []));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }
}

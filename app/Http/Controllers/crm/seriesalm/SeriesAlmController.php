<?php

namespace App\Http\Controllers\crm\seriesalm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
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

}

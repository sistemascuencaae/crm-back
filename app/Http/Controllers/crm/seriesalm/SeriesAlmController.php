<?php

namespace App\Http\Controllers\crm\seriesalm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\series\Inventario;
use Exception;
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
                        $existeContratos = DB::select("SELECT * from gex.contrato_gex g where serie = '$valor->serie' and pro_id = $valor->pro_id;");

                        // Verificar si existe un registro en ddespacho para el producto y serie
                        $existeDespachos = DB::select("SELECT * from gex.ddespacho g where pro_id = $valor->pro_id and serie = '$valor->serie' and tipo = '$valor->tipo';");

                        // Si no hay contrato, proceder con eliminaciones
                        if (!$existeContratos && !$existeDespachos) {

                            // borramos todos los items o series del inventario
                            DB::delete("DELETE from gex.dinventario where numero = $valor->numero and pro_id = $valor->pro_id and serie = '$valor->serie' and tipo = '$valor->tipo';");

                            DB::delete("DELETE from gex.stock_serie where pro_id = $valor->pro_id and serie = '$valor->serie' and tipo = '$valor->tipo' and bod_id = $inventario->bod_id;");

                            DB::delete("DELETE from gex.producto_serie where pro_id = $valor->pro_id and serie = '$valor->serie' and tipo = '$valor->tipo';");
                            // end borramos todos los items o series del inventario

                        } else {
                            return response()->json(RespuestaApi::returnResultado('error', 'Ya existe despachos y contratos en este inventario', ''));
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

    // public function inventarioByNumero($numero_inventario)
    // {
    //     try {
    //         $data = Inventario::where('numero', $numero_inventario)->with('detalle')->first();
    //         return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
    //     }
    // }

    // listar las bodegas activas e inactivas
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
                $existeContratos = DB::select("SELECT * from gex.contrato_gex g where serie = '$serie' and pro_id = $pro_id;");

                if (count($existeContratos) > 0) {
                    // en el front mostrar en un array, porque derrepente haya una serie duplicada con el mismo producto
                    return response()->json(RespuestaApi::returnResultado('error', 'Ya existe un contrato gex de esta serie.', $existeContratos));
                } else {

                    // Verificar si existe un registro en ddespacho para el producto y serie
                    $existeDespachos = DB::select("SELECT * from gex.ddespacho g where pro_id = $pro_id and serie = '$serie' and tipo = '$tipo';");

                    if (count($existeDespachos) > 0) {
                        // en el front mostrar en un array, porque derrepente haya una serie duplicada con el mismo producto
                        return response()->json(RespuestaApi::returnResultado('error', 'Ya existe un despacho de esta serie.', $existeDespachos));
                    } else {

                        // Si no hay contrato ni despacho, procedemos con la eliminacion de esa serie

                        // borramos todos los items o series del inventario
                        DB::delete("DELETE from gex.dinventario where numero = $numero_inventario and pro_id = $pro_id and serie = '$serie' and tipo = '$tipo';");

                        DB::delete("DELETE from gex.stock_serie where pro_id = $pro_id and serie = '$serie' and tipo = '$tipo' and bod_id = $bod_id;");

                        DB::delete("DELETE from gex.producto_serie where pro_id = $pro_id and serie = '$serie' and tipo = '$tipo';");
                        // end borramos todos los items o series del inventario

                        return response()->json(RespuestaApi::returnResultado('success', 'Se elimino correctamente la serie: ' . $serie, ''));
                    }

                }

            });

            return $data;
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }


}
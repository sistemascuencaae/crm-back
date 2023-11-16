<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\FormatResponseTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class ReporteVentasController extends Controller
{
    use FormatResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function ventasPorLinea(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_ventas_por_linea(?,?,?,?,?)',
                                    [$input['linea_id'],
                                    $input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                    $input['vendedor_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }

    public function ventasPorgrupo(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_ventas_por_grupo(?,?,?,?,?)',
                                    [$input['linea_id'],
                                    $input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                    $input['vendedor_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }


    public function ventasPorProducto(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_ventas_por_articulo(?,?,?,?,?)',
                                    [$input['linea_id'],
                                    $input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                    $input['vendedor_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }

     public function ventasPorProductoResumen(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_ventas_por_articulo_resumen(?,?,?,?,?,?)',
                                    [$input['linea_id'],
                                    $input['grupo_id'],
                                    $input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                    $input['vendedor_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }

     public function ventasPorProductoFactura(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_ventas_por_articulo_factura(?,?,?,?,?,?)',
                                    [$input['linea_id'],
                                    $input['grupo_id'],
                                    $input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                    $input['vendedor_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }

    public function ventasResumenSRI(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_ventas_resumen_sri(?,?,?)',
                                    [$input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }

     public function ventasAbonosRetencion(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_ventas_abonos_retenciones(?,?,?)',
                                    [$input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }

}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\FormatResponseTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class ReporteComprasController extends Controller
{
    use FormatResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function comprasPorLinea(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_compras_por_linea(?,?,?,?)',
                                    [$input['linea_id'],
                                    $input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }

    public function comprasPorgrupo(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_compras_por_grupo(?,?,?,?)',
                                    [$input['linea_id'],
                                    $input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }


    public function comprasPorProducto(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_compras_por_articulo(?,?,?,?)',
                                    [$input['linea_id'],
                                    $input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }

     public function comprasPorProductoResumen(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_compras_por_articulo_resumen(?,?,?,?,?)',
                                    [$input['linea_id'],
                                    $input['grupo_id'],
                                    $input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }

     public function comprasPorProductoFactura(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_compras_por_articulo_factura(?,?,?,?,?)',
                                    [$input['linea_id'],
                                    $input['grupo_id'],
                                    $input['finicio'],
                                    $input['ffin'],
                                    $input['cliente_id'],
                                ]);


            return $this->insertOk($results);


        } catch (\Exception $e) {
            return $this->insertErrCustom(null, $e->getMessage());
        }
        return response()->json($data);
    }

    public function comprasResumenSRI(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_compras_resumen_sri(?,?,?)',
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

    public function comprasResumenSRIRetFte(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_compras_resumen_sri_retenciones_fte(?,?,?)',
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

    public function comprasResumenSRIRetIva(Request $request){
        try{
            $input = $request->all();
            $results=DB::select('SELECT * from reporte_compras_resumen_sri_retenciones_iva(?,?,?)',
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

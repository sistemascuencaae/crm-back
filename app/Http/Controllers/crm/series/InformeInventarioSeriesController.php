<?php

namespace App\Http\Controllers\crm\series;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InformeInventarioSeriesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function inventarios($bodega)
    {
        $data = DB::select("select c.numero, concat(c.numero, ' - ', c.responsable, ' - ', TO_CHAR(c.fecha::date, 'dd/mm/yyyy')) as inventario
                            from gex.cinventario c
                            where c.bod_id = " . $bodega);

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function bodegas()
    {
        $data = DB::select("select b.bod_id, b.bod_nombre as presenta from bodega b order by presenta");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
    
    public function infInvSeries($bodega, $numero)
    {
        $data = DB::select("select inventario, bod_nombre, producto, sum(cantidad) as cant
                            from (
                            select concat(c.numero, ' - ', c.responsable, ' - ', TO_CHAR(c.fecha::date, 'dd/mm/yyyy')) as inventario,
                                    b.bod_nombre,
                                    concat(p.pro_codigo,' - ',p.pro_nombre) as producto,
                                    (case when d.tipo = 'N' then 1 else 0.5 end) as cantidad
                            from gex.cinventario c join gex.dinventario d on c.numero = d.numero 
                                                join producto p on d.pro_id  = p.pro_id
                                                join bodega b on c.bod_id = b.bod_id
                            where (b.bod_id = " . $bodega . " or 0 = " . $bodega . ")
                                    and (c.numero = " . $numero . " or 0 = " . $numero . ")) as tabla
                            group by inventario, bod_nombre, producto
                            order by inventario, bod_nombre, producto");

        return response()->json(RespuestaApi::returnResultado('success', '200', $data));
    }
}
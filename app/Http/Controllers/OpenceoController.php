<?php

namespace App\Http\Controllers;

use App\Http\Controllers;
use App\Http\Traits\FormatResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;



class OpenceoController extends Controller
{
    use FormatResponseTrait;
    public function __construct()
    {
        $this->middleware('auth:admin', ['except' =>
        [
            'agencias',
            'departamentos',
            'ciudades',
            'cargos'
        ]]);
    }



    public function agencias()
    {
        try {
            $sql =  "select * from public.almacen where alm_activo = true order by alm_nombre";
            $datos = DB::select($sql);
            $resp = array(
                'code'      => 200,
                'status'    => 'success',
                'message'   => 'La información se consiguio sin problemas.',
                'data'  => $datos,
            );
        } catch (\Exception $e) {

            $resp = array(
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'Error: la información no se logro conseguir: ',
                'error'     =>  $e,
            );
        }
        return response()->json($resp);
    }

    public function departamentos()
    {
        try {
            $sql =  "select * from hclinico.departamento where dep_estado = true order by dep_nombre";
            $datos = DB::select($sql);
            $resp = array(
                'code'      => 200,
                'status'    => 'success',
                'message'   => 'La información se consiguio sin problemas.',
                'data'  => $datos,
            );
        } catch (\Exception $e) {

            $resp = array(
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'Error: la información no se logro conseguir: ',
                'error'     =>  $e,
            );
        }
        return response()->json($resp);
    }

    public function ciudades()
    {
        try {
            $sql =  "select * from public.ciudad order by ciu_nombre";
            $datos = DB::select($sql);
            $resp = array(
                'code'      => 200,
                'status'    => 'success',
                'message'   => 'La información se consiguio sin problemas.',
                'data'  => $datos,
            );
        } catch (\Exception $e) {

            $resp = array(
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'Error: la información no se logro conseguir: ',
                'error'     =>  $e,
            );
        }
        return response()->json($resp);
    }

    public function cargos()
    {
        try {
            $sql =  "select * from hclinico.cargo where car_estado = true order by car_nombre";
            $datos = DB::select($sql);
            $resp = array(
                'code'      => 200,
                'status'    => 'success',
                'message'   => 'La información se consiguio sin problemas.',
                'data'  => $datos,
            );
        } catch (\Exception $e) {

            $resp = array(
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'Error: la información no se logro conseguir: ',
                'error'     =>  $e,
            );
        }
        return response()->json($resp);
    }

}

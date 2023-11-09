<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\FormatResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\FormOcupacional;


class FormularioController extends Controller
{
    use FormatResponseTrait;
    public function __construct()
    {
        $this->middleware('auth:admin', ['except' =>
        [
            'create',
            'update',
            'byId',
            'all',
            'allActive',
            'byId_union'

        ]]);
    }



    public function all(){
        try{
            //$sql =  "select pac.*, ciu.ciu_nombre as ciudad_nombre from hclinico.paciente pac, public.ciudad ciu where pac.ciudad_id = ciu.ciu_id order by pac.pac_primer_apellido";
            $sql = "SELECT 'PRE-OCUPACIONAL' as tipo_formulario,fo.*,pac.*,alm.alm_nombre,ciu.ciu_nombre FROM hclinico.formulario_ocupacional fo
                    left join hclinico.paciente pac on pac.pac_id = fo.pac_id
                    left join public.almacen alm on fo.alm_id = alm.alm_id
                    left join public.ciudad ciu on ciu.ciu_id= pac.ciudad_id
                    ORDER BY fo.created_at ASC";

            $data = DB::select($sql);

            return $this->getOk($data);
        } catch (\Exception $e) {
            return $this->getErrCustom($e->getMessage(),'Error: la información no se logro conseguir: ');
        }
    }


    public function byId($id){
        try{
            $sql = "SELECT 'PRE-OCUPACIONAL' as tipo_formulario,fo.fo_id,pac.pac_id,alm.alm_nombre,ciu.ciu_nombre, fo.created_at as creacion, fo.updated_at as actualizacion, fo.fo_estado as estado, pac.pac_primer_apellido, pac.pac_segundo_apellido, pac.pac_primero_nombre, pac.pac_segundo_nombre FROM hclinico.formulario_ocupacional fo
            left join hclinico.paciente pac on pac.pac_id = fo.pac_id
            left join public.almacen alm on fo.alm_id = alm.alm_id
            left join public.ciudad ciu on ciu.ciu_id= pac.ciudad_id
            WHERE pac.pac_id = $id
            UNION 
            SELECT 'APTITUD-MEDICA' as tipo_formulario,fam.fam_id as fo_id ,pac.pac_id,'' as alm_nombre,ciu.ciu_nombre, fam.created_at as creacion, fam.updated_at as actualizacion, fam.fam_estado as estado, pac.pac_primer_apellido, pac.pac_segundo_apellido, pac.pac_primero_nombre, pac.pac_segundo_nombre FROM hclinico.formulario_aptitudmedica fam
            left join hclinico.paciente pac on pac.pac_id = fam.pac_id
            left join public.ciudad ciu on ciu.ciu_id= pac.ciudad_id
            WHERE pac.pac_id = $id";

            $data = DB::select($sql);

            return $this->getOk($data);
        } catch (\Exception $e) {
            return $this->getErrCustom($e->getMessage(),'Error: la información no se logro conseguir: ');
        }
    }




}
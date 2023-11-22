<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\FormatResponseTrait;
use App\Models\DAntecedentesTrabajo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Paciente;
use App\Models\FormOcupacional;


class PacienteController extends Controller
{
    use FormatResponseTrait;

    public function __construct()
    {
        $this->middleware('auth:admin', ['except' =>
        [
            'list',
            'all',
            'getImage',
            'addImage',
            'create',
            'byId',
            'update',
            'register',
            'reporteProylecma'
        ]]);
    }


    public function todos(){
        try {
            $sql =  "select * from web.formulario_ocupacional";
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
    public function allFO(){
        try {
            $data = Paciente::with('formOcupacional')->get();
            return $this->getOk($data);
        } catch (\Exception $e) {
            return $this->getErrCustom($e->getMessage(), 'Error: la información no se logro conseguir: ');
        }
    }
    public function byIdFO($id){
        try {
            $data = array(
                'paciente' => Paciente::with('formOcupacional.dAntecedentesTrabajo')->find($id)
            );
            if (is_object($data['paciente'])) {
                return $this->getOk($data);
            } else {
                return $this->getErrCustom($data, 'No existe');
            }
        } catch (\Exception $e) {
            return $this->getErrCustom($id, $e->getMessage());
        }
    }
    public function register(Request $request){
        $inPaciente = $request->input('paciente');
        $inFormOcupacional = $request->input('paciente.form_ocupacional');
        $inDAntecedentesTrabajo = $request->input('paciente.form_ocupacional.d_antecedentes_trabajo');

        $paciente = new Paciente($inPaciente);
        $formOcupa = new FormOcupacional($inFormOcupacional);
        //$fomrDAnteTrabajo = new DAntecedentesTrabajo($inDAntecedentesTrabajo);

        $validation = Validator::make(
            $inPaciente,
            [
                'pac_identificacion' => 'required',
                'pac_primero_nombre' => 'required',
                'pac_primer_apellido' => 'required',
            ],
            [
                'pac_identificacion.required' => 'La identificacion es requerida.',
                'pac_primero_nombre.required' => 'El primer nombre es requerida.',
                'pac_primer_apellido.required' => 'El primer apellido es requerido.',
            ]
        );
        if (!$validation->fails()) {
            try {
                DB::transaction(function () use ($paciente, $formOcupa, $inDAntecedentesTrabajo) {



                    $paciente->save();
                    $paciente->formOcupacional()->save($formOcupa);
                    $id = $paciente->formOcupacional->fo_id;

                    foreach ($inDAntecedentesTrabajo as &$valor) {
                        $fomrDAnteTrabajo = new DAntecedentesTrabajo($valor);
                        $fomrDAnteTrabajo->fo_id = $id;
                        $fomrDAnteTrabajo->save();
                    }


                    // $fomrDAnteTrabajo = FormOcupacional::findOrFail($id);
                    // $fomrDAnteTrabajo->dAntecedentesTrabajo()->create([
                    //     'dAntecedentesTrabajo' => $inDAntecedentesTrabajo
                    // ]);

                });
                $result = array(
                    'paciente' => Paciente::with('formOcupacional.dAntecedentesTrabajo')->find($paciente->pac_id)
                );
                if ($result) {
                    return $this->insertOk($result);
                } else {
                    return $this->insertErr($result);
                }
            } catch (\Exception $e) {
                return $this->insertErrCustom($request, $e->getMessage());
            }
        } else {
            return $this->insertErrCustom($validation->messages(), 'Datos inválidos: El nombre y apellido son requeridos');
        }
    }
    public function updateFO(Request $request){
        $pacId = $request->input('paciente.pac_id');
        $inPaciente = $request->input('paciente');
        $inFormOcupacional = $request->input('paciente.form_ocupacional');

        $validation = Validator::make(
            $inPaciente,
            [
                'pac_identificacion' => 'required',
                'pac_primero_nombre' => 'required',
                'pac_primer_apellido' => 'required',
            ],
            [
                'pac_identificacion.required' => 'La identificacion es requerida.',
                'pac_primero_nombre.required' => 'El primer nombre es requerida.',
                'pac_primer_apellido.required' => 'El primer apellido es requerido.',
            ]
        );

        if (!$validation->fails()) {
            try {
                $resultDBtransaction = DB::transaction(function () use ($pacId, $inPaciente, $inFormOcupacional) {
                    unset($inPaciente['pac_id']);
                    unset($inPaciente['form_ocupacional']);
                    unset($inFormOcupacional['fo_id']);
                    unset($inFormOcupacional['doc_id']);
                    $updatePaciente = Paciente::where('pac_id', $pacId)->update($inPaciente);
                    $updateFormOcupacional = FormOcupacional::where('pac_id', $pacId)->update($inFormOcupacional);
                    return $updatePaciente;
                });
                if ($resultDBtransaction) {
                    return $this->updateOk($resultDBtransaction);
                } else {
                    return $this->updateErr($resultDBtransaction);
                }
            } catch (\Exception $e) {
                return $this->updateErrCustom($request, $e->getMessage());
            }
        } else {
            return $this->insertErrCustom($validation->messages(), 'Datos inválidos: El nombre y apellido son requeridos');
        }
    }
    public function all(){
        try{
            //$sql =  "select pac.*, ciu.ciu_nombre as ciudad_nombre from hclinico.paciente pac, public.ciudad ciu where pac.ciudad_id = ciu.ciu_id order by pac.pac_primer_apellido";
            $sql = "SELECT DISTINCT pac.*, alm.alm_nombre, ciu.ciu_nombre
            FROM hclinico.paciente pac
            LEFT JOIN hclinico.formulario_ocupacional fo ON pac.pac_id = fo.pac_id
            LEFT JOIN public.almacen alm ON fo.alm_id = alm.alm_id
            LEFT JOIN public.ciudad ciu ON ciu.ciu_id = pac.ciudad_id
            ORDER BY pac.pac_id ASC;";

            $data = DB::select($sql);

            return $this->getOk($data);
        } catch (\Exception $e) {
            return $this->getErrCustom($e->getMessage(),'Error: la información no se logro conseguir: ');
        }
    }
    public function list(){
        try {
            $data = Paciente::where('pac_estado', true)->get();
            return $this->getOk($data);
        } catch (\Exception $e) {
            return $this->getErrCustom($e->getMessage(), 'Errorrr: la información no se logro conseguir: ');
        }
    }
    public function byId($id){
        try{
            $data = Paciente::find($id);
            return $this->getOk($data);
        } catch (\Exception $e) {
            return $this->getErrCustom($e->getMessage(),'Error: la información no se logro conseguir: ');
        }

    }
    public function create(Request $request) {
        $input = $request->all();
        $validation = Validator::make(
            $request->all(),
            [
                'pac_identificacion' => 'required',
                'pac_primer_apellido' => 'required',
            ],
            [
                'pac_identificacionrequired' => 'La identificacion es requerida.',
                'pac_primer_apellido.required' => 'El 1er apellido es requerido.',
            ]
        );

        if (!$validation->fails()) {
            try {
                $data = new Paciente($input);
                $data->save();

                if ($data) {return $this->insertOk($data);} else {return $this->insertErr($data);}

            } catch (\Exception $e) {return $this->insertErrCustom($input, $e->getMessage());}

        } else {
            return $this->insertErrCustom($validation->messages(), 'Datos inválidos: El nombre y apellido son requeridos');
        }
    }
    public function update(Request $request){

        $input = $request->all();
        $pacId = $request->input('pac_id');

        $validation = Validator::make($request->all(),
            [
                'pac_identificacion' => 'required',
                'pac_primer_apellido' => 'required',
            ],
            [
                'pac_identificacion.required' => 'La identificacion es requerida.',
                'pac_primer_apellido.required' => 'El 1er apellido es requerido.',
            ]
        );

        if (!$validation->fails()){
            try {
                unset($input['pac_id']);
                unset($input['accion']);
                unset($input['created_at']);
                $data = Paciente::where('pac_id', $pacId)->update($input);

                if($data){return $this->updateOk($data);}else{return $this->updateErr($data);}

            } catch (\Exception $e) {return $this->updateErrCustom($validation->messages(), $e->getMessage());}

        }else{
            return $this->updateErrCustom($validation->messages(), 'Datos inválidos');
        }
    }
    public function delete(Request $request, $id){

        try {
            $data = Paciente::where('pac_id',$id)->first();

            if (!empty($data)){
                $data->delete();
                return $this->deleteOk($data);
            }else{
                return $this->deleteErrCustom($data, 'No existe el registro.');
            }
        }catch(\Exception $e) {
            return $this->deleteErrCustom($data, $e->getMessage());
        }
    }
    public function getImage($filename){
        $isset = \Storage::disk('images')->exists($filename);
        if ($isset) {
            $file = \Storage::disk('images')->get($filename);
            return Response($file, 200);
        } else {
            $data = array(
                'code' => 404,
                'status' => 'error',
                'message' => 'La imagen no existe',
            );
            return response()->json($data, $data['code']);
        }
    }
    public function addImage(Request $request){
        $image = $request->file('image');
        if ($image) {
            $image_path = $image->getClientOriginalName();
            \Storage::disk('nas')->put("hclinico/".$image_path, \File::get($image));
        }
        $data = array(
            'image' => $image,
            'status' => 'success'
        );
        return response()->json($data, 200);
    }









}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\FormatResponseTrait;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\FormAptitudMedica;

class FormAptiMedicaController extends Controller
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
            'guardarImagen',
            'getImagen',
            'todasImagenes'

        ]]);
    }


    public function create(Request $request)
    {
        $inFormAptitudMedica = $request->all();
        $formAptiMedi = new FormAptitudMedica($inFormAptitudMedica);
        $validation = Validator::make(
            $inFormAptitudMedica,
            [
                // 'pac_identificacion' => 'required',
                // 'pac_primero_nombre' => 'required',
                // 'pac_primer_apellido' => 'required',
            ],
            [
                // 'pac_identificacion.required' => 'La identificacion es requerida.',
                // 'pac_primero_nombre.required' => 'El primer nombre es requerida.',
                // 'pac_primer_apellido.required' => 'El primer apellido es requerido.',
            ]
        );
        if (!$validation->fails()) {
            try {
                DB::transaction(function () use ($formAptiMedi) {
                    $formAptiMedi->save();
                });
                $result = FormAptitudMedica::find($formAptiMedi->fam_id);
                if ($result) {
                    return $this->miResultOK($result, 'Formulario creado correctamente');
                } else {
                    return $this->miResultError($result, 'No se pudo crear el formulario');
                }
            } catch (\Exception $e) {
                return $this->miResultException($request, $e->getMessage());
            }
        } else {
            return $this->miResultException($validation->messages(), 'Datos inválidos: Porfevor verificar los campos requeridos');
        }
    }



    public function update(Request $request)
    {
        $fam_id = $request->input('fam_id');
        $inFormAptitudMedica = $request->all();

        $validation = Validator::make(
            $inFormAptitudMedica,
            [
                // 'pac_identificacion' => 'required',
                // 'pac_primero_nombre' => 'required',
                // 'pac_primer_apellido' => 'required',
            ],
            [
                // 'pac_identificacion.required' => 'La identificacion es requerida.',
                // 'pac_primero_nombre.required' => 'El primer nombre es requerida.',
                // 'pac_primer_apellido.required' => 'El primer apellido es requerido.',
            ]
        );

        if (!$validation->fails()) {
            try {
                $resultDBtransaction = DB::transaction(function () use ($fam_id, $inFormAptitudMedica) {

                    unset($inFormAptitudMedica['fam_id']);
                    unset($inFormAptitudMedica['doc_id']);
                    unset($inFormAptitudMedica['pac_id']);
                    return FormAptitudMedica::where('fam_id', $fam_id)->update($inFormAptitudMedica);
                });
                if ($resultDBtransaction) {
                    return $this->miResultOK($resultDBtransaction, 'Formulario actualizado con exito');
                } else {
                    return $this->miResultError($resultDBtransaction, 'El formulario no existe');
                }
            } catch (\Exception $e) {
                return $this->miResultException($request, $e->getMessage());
            }
        } else {
            return $this->miResultException($validation->messages(), 'Datos inválidos: Revisar los campos obligatorios');
        }
    }



    public function byId($id)
    {
        try {
            $result = FormAptitudMedica::find($id);
            if ($result) {
                return $this->miResultOK($result, 'Datos obtenidos con exito');
            } else {
                return $this->miResultError($result, 'No existe');
            }
        } catch (\Exception $e) {
            return $this->miResultException($id, $e->getMessage());
        }
    }


    public function miResultOK($data, $mensaje)
    {
        $result = array(
            'code'      => 200,
            'status'    => 'success',
            'message'   =>  $mensaje,
            'data'     =>  $data
        );
        return $result;
    }
    public function miResultError($data, $mensaje)
    {
        $result = array(
            'code'      => 400,
            'status'    => 'error',
            'message'   => $mensaje,
            'data'     =>  $data
        );
        return $result;
    }


    public function miResultException($data, $mensaje)
    {
        $result = array(
            'code'      => 500,
            'status'    => 'error',
            'message'   =>  $mensaje,
            'data'     =>  $data
        );
        return $result;
    }
}

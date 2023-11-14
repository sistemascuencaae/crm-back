<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Http\Traits\FormatResponseTrait;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;



class CompanyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

     public function findById($id){
        $entity = Company::find($id);
        if (is_object($entity)) {
            $data = array(
                'code' => 200,
                'status' => 'success',
                'company' => $entity,
            );
        } else {
            $data = array(
                'code' => 404,
                'status' => 'error',
                'message' => 'Error: Empresa no existe',
            );
        }
        return response()->json($data, $data['code']);
    }



    public function edit(Request $request, $id) {
        //recoger datos por post
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);

        if (!empty($params_array)) {
            //validar los datos
            $validate = \Validator::make($params_array, [
               'name'    => 'required',
               'ruc'  => 'required',
               'iva'  => 'required',
            ]);
            if ($validate->fails()) {
                //LA VALIDACION A FALLADO
                $data = array(
                    'code'      => 404,
                    'status'    => 'error',
                    'message'   => 'Error: La validacion a fallado, revise que los datos requeridos esten completos',
                    'error'     => $validate->errors(),
                );
            } else {
                try{
                    //Quitar campos que no quiero actualizar
                    unset($params_array['id']);
                    unset($params_array['updated_at']);
                    unset($params_array['created_at']);


                    //MODIFICAR REGISTRO
                    $entity = Company::where('id', $id)->update($params_array);
                    //devolver el array con el resultado
                    $data = array(
                        'code'          => 200,
                        'status'        => 'success',
                        'message'       => 'Se modifico correctamente.',
                        'data'      => $params_array,
                    );
                }catch(\Exception $e){
                    //. $e->getMessage()
                    $data = array(
                        'code'      => 400,
                        'status'    => 'error',
                        'message'   => 'Error: No se pudo modificar, existe un conflicto en la base de datos: ',
                        'error'     =>  $e,
                    );
                }
            }
        }else {
            $data = array(
                'code'      => 400,
                'status'    => 'error',
                'message'   => 'Error: No se ha enviado ninguna información, o la información esta incompleta.',
                'data'      => $params_array,
            );
        }
        return response()->json($data, $data['code']);
    }



}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\FormatResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\FormOcupacional;
use App\Models\Galeria;

class FormOcupaController extends Controller
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
            'getImage',
            'todasImagenes'

        ]]);
    }


    public function create(Request $request)
    {
        $inFormOcupacional = $request->all();
        $formOcupa = new FormOcupacional($inFormOcupacional);
        $validation = Validator::make(
            $inFormOcupacional,
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
                DB::transaction(function () use ($formOcupa) {
                    $formOcupa->save();
                });
                $result = FormOcupacional::find($formOcupa->fo_id);
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
        $fo_id = $request->input('fo_id');
        $inFormOcupacional = $request->all();

        $validation = Validator::make(
            $inFormOcupacional,
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
                $resultDBtransaction = DB::transaction(function () use ($fo_id, $inFormOcupacional) {
                    unset($inFormOcupacional['fo_id']);
                    unset($inFormOcupacional['doc_id']);
                    unset($inFormOcupacional['pac_id']);
                    return FormOcupacional::where('fo_id', $fo_id)->update($inFormOcupacional);
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
            $result = FormOcupacional::find($id);
            if ($result) {
                return $this->miResultOK($result, 'Datos obtenidos con exito');
            } else {
                return $this->miResultError($result, 'No existe');
            }
        } catch (\Exception $e) {
            return $this->miResultException($id, $e->getMessage());
        }
    }


    public function allActive()
    {
        try {

            $result = FormOcupacional::all()->where('fo_estado', true);

            return $this->miResultOK($result, 'Datos obtenidos con exito');
        } catch (\Exception $e) {
            return $this->miResultException($e->getMessage(), 'Error: la información no se logro conseguir: ');
        }
    }

    public function getImagen($filename)
    {
        $isset = \Storage::disk('images')->exists($filename);
        if ($isset) {
            $file = \Storage::disk('images')->get($filename);
            return $this->miResultOK($filename, 'Si hay');
            //return $this->miResultOK($file, 'Imagen Obtenida');

        } else {

            return $this->miResultOK($filename, 'La imagen no existe');
        }
    }

    public function todasImagenes($formulario_id)
    {
        try {

            $sql = "SELECT * FROM hclinico.galeria WHERE ga_seccion = 'L' and formulario_id =" . $formulario_id.' order by ga_id asc';
            $data = DB::select($sql);

            return $this->miResultOK($data, 'Datos obtenidos con exito');
        } catch (\Exception $e) {
            return $this->miResultException($e->getMessage(), 'Error: la información no se logro conseguir: ');
        }
    }



    public function guardarImagen(Request $request){
        $resultDBtransaction = DB::transaction(function () use ($request) {
            $arrayParaAcrchivo = $request->input('formParaArchivo');
            for ($i = 0; $i <= count($arrayParaAcrchivo)-1; $i++) {

                if($request->input('formParaArchivo')[$i]['fileSource'] != null && $request->input('formParaArchivo')[$i]['estadoModificado'] == true){
                    $fileSource = $request->input('formParaArchivo')[$i]['fileSource'];
                    if(str_contains($request->input('formParaArchivo')[$i]['fileSource'], $request->input('formParaArchivo')[$i]['name']) == false){
                        $this->saveImagen($request->input('formParaArchivo')[$i]['name'], $fileSource);
                    }




                    if($arrayParaAcrchivo[$i]['accion'] == 'actualizar'){
                        $ga_id = $request->input('galeriaArray')[$i]['ga_id'];
                        Galeria::where('ga_id', $ga_id)->update($request->input('galeriaArray')[$i]);
                    }else{
                        $data = new Galeria($request->input('galeriaArray')[$i]);
                        $data->save();
                    }
                }


            }

        });
        return $this->miResultOK($request->all(), 'Imagenes guardas sin problemas'.$request);
    }

    public function getImage($filename){
        $isset = \Storage::disk('images')->exists($filename);
        //echo($filename);
        //echo($isset);
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









































    public function guardarImagen1(Request $request)
    {
        $fileSource1 = $request->input('formParaArchivo.fileSource1');
        $fileSource2 = $request->input('formParaArchivo.fileSource2');
        $fileSource3 = $request->input('formParaArchivo.fileSource3');
        $fileSource4 = $request->input('formParaArchivo.fileSource4');


        $resultDBtransaction = DB::transaction(function () use ($request, $fileSource1, $fileSource2, $fileSource3, $fileSource4) {
            if ($fileSource1 != null) {
                $this->saveImagen($request->input('formParaArchivo.name1'), $fileSource1);
                if (
                    $request->input('formParaArchivo.accion1') == 'crear' &&
                    $request->input('formParaArchivo.file1') != null
                ) {
                    $data = new Galeria($request->input('galeriaArray')[0]);
                    $data->save();
                } else {

                    $ga_id1 = $request->input('galeriaArray')[0]['ga_id'];
                    return Galeria::where('ga_id', $ga_id1)->update($request->input('galeriaArray')[0]);
                }
            }
            if ($fileSource2 != null) {
                $this->saveImagen($request->input('formParaArchivo.name2'), $fileSource2);
                if (
                    $request->input('formParaArchivo.accion2') == 'crear' &&
                    $request->input('formParaArchivo.file2') != null
                ) {
                    $data = new Galeria($request->input('galeriaArray')[1]);
                    $data->save();
                } else {
                    $ga_id2 = $request->input('galeriaArray')[1]['ga_id'];
                    return Galeria::where('ga_id', $ga_id2)->update($request->input('galeriaArray')[1]);
                }
            }
            if ($fileSource3 != null) {
                $this->saveImagen($request->input('formParaArchivo.name3'), $fileSource3);
                if (
                    $request->input('formParaArchivo.accion3') == 'crear' &&
                    $request->input('formParaArchivo.file3') != null
                ) {
                    $data = new Galeria($request->input('galeriaArray')[2]);
                    $data->save();
                } else {
                    $ga_id3 = $request->input('galeriaArray')[2]['ga_id'];
                    return Galeria::where('ga_id', $ga_id3)->update($request->input('galeriaArray')[2]);
                }
            }
            if ($fileSource4 != null) {
                $this->saveImagen($request->input('formParaArchivo.name4'), $fileSource4);
                if (
                    $request->input('formParaArchivo.accion4') == 'crear' &&
                    $request->input('formParaArchivo.file4') != null
                ) {
                    $data = new Galeria($request->input('galeriaArray')[3]);
                    $data->save();
                } else {

                    $ga_id4 = $request->input('galeriaArray')[3]['ga_id'];
                    return Galeria::where('ga_id', $ga_id4)->update($request->input('galeriaArray')[3]);
                }
            }
        });
        return $this->miResultOK($request->all(), 'Imagenes guardas con exito');
    }



    public function saveImagen($name, $fileSource)
    {
        $folderPath = "C:/xampp/htdocs/color14/hclinico-back/storage/app/images/" . $name;
        $image_parts = explode(";base64,", $fileSource);
        $image_base64 = base64_decode($image_parts[1]);
        $file = $folderPath . '.png';
        file_put_contents($file, $image_base64);
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

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\FormatResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Galeria;


class GaleriaController extends Controller
{
    use FormatResponseTrait;
    public function __construct()
    {
        $this->middleware('auth:admin', ['except' =>
        [
            'all',
            'byId',
            'getImage',
            'addImage'
        ]]);
    }

    public function all($tipo_formulario,$id){
        try{
            $sql = "SELECT ga.*, fo.fo_id, pac.pac_id,
            pac.pac_primer_apellido, pac.pac_segundo_apellido,pac.pac_primero_nombre, pac.pac_segundo_nombre
            FROM hclinico.galeria ga
            LEFT JOIN hclinico.formulario_ocupacional fo on fo.fo_id = ga.formulario_id
            LEFT JOIN hclinico.paciente pac on pac.pac_id = fo.pac_id
            where ga.formulario_id = $id and ga.ga_tipo_formulario = '$tipo_formulario'
            ORDER BY ga_id ASC";

            $data = DB::select($sql);

            return $this->getOk($data);
        } catch (\Exception $e) {
            return $this->getErrCustom($e->getMessage(),'Error: la información no se logro conseguir: ');
        }
    }
    public function byId($id){
        try{
            $data = Galeria::find($id);
            return $this->getOk($data);
        } catch (\Exception $e) {
            return $this->getErrCustom($e->getMessage(),'Error: la información no se logro conseguir: ');
        }
    }
    public function create(Request $request){
        $input = $request->all();
        $validation = Validator::make(
            $request->all(),
            [
                'ga_titulo' => 'required',
                'ga_descripcion' => 'required',
            ],
            [
                'ga_titulo.required' => 'El título es requerido.',
                'ga_descripcion.required' => 'El título es requerido.',
            ]
        );

        if (!$validation->fails()){
            try {
                $data = new Galeria($input);
                $data->save();
                if ($data) {return $this->insertOk($data);} else {return $this->insertErr($data);}
            } catch (\Exception $e) {return $this->insertErrCustom($input, $e->getMessage());}

        }else{
            return $this->insertErrCustom($validation->messages(), 'Datos inválidos: El título y descripción son requeridos');
        }

    }
    public function update(Request $request){

        $input = $request->all();
        $Id = $request->input('ga_id');

        $validation = Validator::make($request->all(),
            [
                'ga_titulo' => 'required',
                'ga_descripcion' => 'required',
            ],
            [
                'ga_titulo.required' => 'El título es requerido.',
                'ga_descripcion.required' => 'El título es requerido.',
            ]
        );

        if (!$validation->fails()){
            try {
                unset($input['ga_id']);
                unset($input['formulario_id']);
                unset($input['ga_tipo_formulario']);
                unset($input['ga_seccion']);
                unset($input['created_at']);

                $data = Galeria::where('ga_id', $Id)->update($input);

                if($data){return $this->updateOk($data);}else{return $this->updateErr($data);}

            } catch (\Exception $e) {return $this->updateErrCustom($validation->messages(), $e->getMessage());}

        }else{
            return $this->updateErrCustom($validation->messages(), 'Datos inválidos');
        }
    }
    public function delete(Request $request, $id){

        try {
            $data = Galeria::where('ga_id',$id)->first();

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


    public function addImage(Request $request){
        /*
            PARA QUE FUNCIOENTE ESTE CODIGO HAY QUE:
            configurar el fichero httpd.conf y le añado:
            <IfModule mod_headers.c>
              Header set Access-Control-Allow-Origin "*"
            </IfModule>
        */
        //header('Access-Control-Allow-Origin: *');
        //header('Access-Control-Allow-Headers: *');

        $image=$request->file('image');

        if($image){
            $image_path=$image->getClientOriginalName();
           \Storage::disk('images')->put($image_path, \File::get($image));
        }
        $data=array(
           'image'=>$image,
           'status'=>'success'
       );

       return response()->json($data,200);

    }







}

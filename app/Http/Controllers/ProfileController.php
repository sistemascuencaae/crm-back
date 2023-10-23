<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Traits\FormatResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Profile;
use App\Models\Access;


class ProfileController extends Controller{
    use FormatResponseTrait;
    //  public function __construct() {
    //       $this->middleware('auth:api');
    // }


    public function all(){
        try{
            $data= \App\Models\Profile::all();
            return $this->getOk($data);
        } catch (\Exception $e) {
            return $this->getErrCustom($e->getMessage(),'Error: la información no se logro conseguir: ');
        }
        return response()->json($data);
    }



    public function list()
    {
        try{
            //$data = \App\Models\Profile::where('isactive', '1')->get();
            $sql= "SELECT * FROM hclinico.profiles where  isactive=1";
            $data = DB::select($sql);

            return $this->getOk($data);
        } catch (\Exception $e) {
            return $this->getErrCustom($e->getMessage(),'Error: la información no se logro conseguir: ');
        }
    }


    public function findById($id){

        $profile = Profile::with(['access','access.menu'])->find($id);
        if (is_object($profile)) {
            $data = array(
                'code' => 200,
                'status' => 'success',
                'profile' => $profile,
            );
        } else {
            $data = array(
                'code' => 404,
                'status' => 'error',
                'message' => 'Error: Profile no existe',
            );
        }
        return response()->json($data, $data['code']);
    }

    public function findByProgram($profile,$program){

        /*$profile = Access::where([
                    ['profile_id','=',$profile],
                    ['menu_id','=',$program]
            ])->get();*/
        $profile = Access::with('menu')->whereHas('menu', function ($query) use ($program) {
            $query->where('name',$program);
        })->where([
            ['profile_id', $profile]
        ])->get();

        if (is_object($profile)) {
            $data = array(
                'code' => 200,
                'status' => 'success',
                'profile' => $profile,
            );
        } else {
            $data = array(
                'code' => 404,
                'status' => 'error',
                'message' => 'Error: Profile no existe',
            );
        }
        return response()->json($data, $data['code']);
    }


    public function findByUser($userid){
         $sql= "SELECT u.id,u.name,u.surname,u.login,u.profile_id,
                    a.menu_id,
                    m.code,m.module,m.name,m.url,m.icon,
                    a.create,a.edit,a.delete,a.report,a.other
                FROM users u
                INNER JOIN access a on u.profile_id=a.profile_id
                INNER JOIN menu m on a.menu_id=m.id
                where  u.id=?
                ORDER BY m.code";

        try{
            $accesos = DB::select($sql,[$userid]);
            $data = array(
                'code' => 200,
                'status' => 'success',
                'data' => $accesos
            );
        }
        catch(\Exception $e){
            $data = array(
            'code'      => 400,
            'status'    => 'error',
            'message'   => 'Error: No se obtener los permisos del usuario',
            'error'     =>  $e,
            );
        }
        return response()->json($data, $data['code']);
    }


    public function create(Request $request){
        $date= date('Y-m-d H:i:s');
        $json = $request->input('json', null);
        $params_array = json_decode($json,true); //consigo un objeto

        $validation =\Validator::make($params_array, [
                        'name' => 'required',
            ]);


        if (!$validation->fails()) {
            $input = $request->all();
            $profile = new Profile($params_array);
            $profile->save();
            $lastProfile = Profile::latest('id')->first();

            foreach ($params_array['access'] as $parent_row) {
                $access = new Access($parent_row);
                $access->profile_id = $lastProfile->id;
                $access->save();
            }


            if ($profile) {
                //Confirma en Mensaje
                $data = array(
                    'code'      => 200,
                    'status'    => 'success',
                    'message'   => 'Profile creada',
                    'profile'  => $profile,
                );
            } else {
                 //LA VALIDACION A FALLADO
                $data = array(
                    'code'      => 404,
                    'status'    => 'error',
                    'message'   => 'Profile no creado',
                    'errors'    => $validate->errors()
                );
            }
        } else {
             //NO SE ENVIO NADA
            $data = array(
                'code'      => 404,
                'status'    => 'error',
                'message'   => 'No has enviado ningun profile',
                'profile'  => $json,
            );
        }
        return response()->json($data);
    }



    public function edit(Request $request, $id) {
        //recoger datos por post
        $json = $request->input('json', null);
        $params_array = json_decode($json, true);
        $params_array1 = $params_array;

        if (!empty($params_array)) {
            //validar los datos
            $validate = \Validator::make($params_array, [
               'id'    => 'required',
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
                    // actualizo el profile
                    unset($params_array1['access']);
                    $profile = Profile::where('id', $id)->update($params_array1);
                    //Quitar campos que no quiero actualizar
                    unset($params_array['id']);
                    unset($params_array['created_at']);
                    unset($params_array['updated_at']);

                    //eliminos loa access actuales
                    $res=Access::where('profile_id',$id)->delete();
                    //guardo los nuevos access

                    foreach ($params_array['access'] as $parent_row) {
                        $access = new Access($parent_row);
                        $access->profile_id = $id;
                        $access->save();
                    }


                    unset($params_array['access']);
                    //MODIFICAR REGISTRO de perfil
                    //$profile = Profile::where('id', $id)->update($params_array);


                    //devolver el array con el resultado
                    $data = array(
                        'code'          => 200,
                        'status'        => 'success',
                        'message'       => 'Se modifico correctamente.',
                        'data'      => $id,
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
            );
        }
        return response()->json($data, $data['code']);
    }




}

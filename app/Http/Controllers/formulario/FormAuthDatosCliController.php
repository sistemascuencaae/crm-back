<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Controllers\crm\EmailController;
use App\Http\Resources\RespuestaApi;
use App\Models\Formulario\AutoTrataDatos;
use App\Models\Formulario\FormSeccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormAuthDatosCliController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'list', 'add',
            'listAlmacenes',
            'getAlmacenId',
            'store'
        ]]);
    }

    public function store()
    {
        try {
            $arrayUno = DB::select("SELECT emp.emp_id, (ent.ent_nombres ||' '|| ent.ent_apellidos) as nombre FROM public.empleado emp
            inner join entidad ent on ent.ent_id = emp.ent_id where emp.emp_activo = true");
            $data = (object)[
                "empleados" => $arrayUno
            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }


    public function add(Request $request)
    {
        try {
            $dataInput = $request->all();
            $nombreCompleto = $dataInput['apellidos'] . ' ' . $dataInput['nombres'];
            $dataInput['nombre_completo'] = strtoupper($nombreCompleto);
            $data = AutoTrataDatos::create($dataInput);

            $almacen = DB::selectOne("SELECT * from almacen where alm_id = ?",[$dataInput["alm_id"]]);
            $empleado = DB::selectOne("SELECT emp.emp_id, (ent.ent_nombres ||' '|| ent.ent_apellidos) as nombre FROM public.empleado emp
            inner join entidad ent on ent.ent_id = emp.ent_id where emp.emp_id = ?",[$dataInput["emp_id"]]);

















            $dataObject = (object)[
                "fecha_solicitud" => $data["created_at"],
                "almacen" => $almacen->alm_nombre,
                "agente" => $empleado->nombre,
                "cliente" => $nombreCompleto,
                "telefono" => $dataInput["telefono_principal"],
                "email" => $dataInput["email"]
            ];



            $emailController = new EmailController();
            $emailController->sendEmailAutoRevDatos($dataInput["email"],$dataObject);//$email, $object
            return response()->json(RespuestaApi::returnResultado('success', 'Guardado con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar.', $th));
        }
    }
    //getAlmacenId
    public function getAlmacenId($id)
    {
        try {
            $data = DB::selectOne("SELECT * FROM public.almacen where alm_id = ?",[$id]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }
    public function listAlmacenes()
    {
        try {
            $data = DB::select("SELECT * FROM public.almacen where alm_activo = true order by 1 asc");
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }




}

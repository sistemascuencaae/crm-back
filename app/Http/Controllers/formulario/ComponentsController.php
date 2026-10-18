<?php

namespace App\Http\Controllers\formulario;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Formulario\CampoLikert;
use App\Models\Formulario\FormCampo;
use App\Models\Formulario\FormCampoLikert;
use App\Models\Formulario\FormCampoValor;
use App\Models\Formulario\FormTipoCampo;
use App\Models\Formulario\Formulario;
use App\Models\Formulario\FormValor;
use App\Models\Formulario\Parametro;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


class ComponentsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'store',
            'list',
            'listAll',
            'full',
            'add',
            'listAll',
            'addCampoValor1',
            'addCampoValor',
            'restoreById',
            'restoreById',
            'deleteById',
            'deleteById',
            'edit'
        ]]);
    }

    public function loadInitialData()
    {
        try {
            $tiposApps = DB::select("SELECT * FROM crm.tipo_user_apps where activo = true");

            $data = (object)[
                "tiposUsers" => $tiposApps
            ];


            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }

    public function listarEntidades($tipoUser)
    {
        try {

            $tiposUser = DB::select("SELECT * FROM crm.tipo_user_apps where activo = true");
            $usersDynamo = [];
            $usersCrm = [];
            $empleados = [];
            $data = [];
            foreach ($tiposUser as $key => $value) {
                echo ('$value: '.json_encode($value));

                // $data = (object)[

                // ];
            }







            // if ($tipoUser === 'DYNAMO') {
            //     $usersDynamo = DB::select("SELECT * FROM public.usuario");
            // }
            // if ($tipoUser === 'CRM') {
            //     $usersCrm = DB::select("SELECT * FROM crm.users");
            // }
            // if ($tipoUser === 'APP-AGENTE-VENDEDOR') {
            //     $empleados = DB::select("SELECT * FROM public.entidad ent INNER JOIN public.empleado emp ON emp.ent_id = ent.ent_id");
            // }

            // $data = (object)[
            //     "usersDynamo" => $usersDynamo,
            //     "usersCrm" => $usersCrm,
            //     "empleados" => $empleados
            // ];

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th->getMessage()));
        }
    }
}

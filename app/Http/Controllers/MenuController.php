<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\FormatResponseTrait;
use App\Models\Access;
use App\Models\Menu;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class MenuController extends Controller
{
    use FormatResponseTrait;

    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function list()
    {
        // $data = Menu::all();
        $data = Menu::orderBy('code', 'asc')->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function findById($id)
    {
        $entity = Menu::find($id);
        if (is_object($entity)) {
            $data = array(
                'code' => 200,
                'status' => 'success',
                'family' => $entity,
            );
        } else {
            $data = array(
                'code' => 404,
                'status' => 'error',
                'message' => 'Error: Familia no existe',
            );
        }
        return response()->json($data, $data['code']);
    }



    public function addMenu(Request $request)
    {
        try {
            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($request, &$error, &$exitoso) {

                // Convertir en JSON lo que me envian desde el FrontEnd
                $dataFront = json_encode($request->all());
                $dataJSON = json_decode($dataFront);

                if (Menu::where('code', $dataJSON->code)->first()) {
                    $error = 'Ya EXISTE un menú con código ' . $dataJSON->code;
                    return null;
                } else {

                    if (!is_null($dataJSON->module) && Menu::where('module', $dataJSON->module)->first()) {
                        $error = 'Ya EXISTE un menú con el modulo: ' . $dataJSON->module;
                        return null;
                    } else {

                        if (Menu::where('name', $dataJSON->name)->first()) {
                            $error = 'Ya EXISTE un menú con el nombre: ' . $dataJSON->name;
                            return null;
                        } else {

                            Menu::create($request->all());
                            $exitoso = Menu::orderBy('code', 'asc')->get();
                            return null;
                        }
                    }
                }
            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $exitoso));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editMenu(Request $request, $id)
    {
        try {
            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($request, $id, &$error, &$exitoso) {
                $menu = Menu::findOrFail($id);

                // Obtener los valores existentes del menú antes de la actualización
                $existingCode = $menu->code;
                $existingModule = $menu->module;
                $existingName = $menu->name;

                // Obtener los nuevos valores de código, módulo y nombre
                $code = $request->input('code');
                $module = $request->input('module');
                $name = $request->input('name');

                if ($code !== $existingCode && Menu::where('code', $code)->first()) {
                    $error = 'Ya EXISTE un menú con código ' . $code;
                    return null;
                } else if ($module !== $existingModule && !is_null($module) && Menu::where('module', $module)->first()) {
                    $error = 'Ya EXISTE un menú con el modulo: ' . $module;
                    return null;
                } else if ($name !== $existingName && Menu::where('name', $name)->first()) {
                    $error = 'Ya EXISTE un menú con el nombre: ' . $name;
                    return null;
                }

                // Realizar la actualización si no hay errores
                $menu->update($request->all());
                $exitoso = Menu::orderBy('code', 'asc')->get();
            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $exitoso));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function deleteMenu(Request $request, $id)
    {
        try {
            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($request, $id, &$error, &$exitoso) {
                $menu = Menu::findOrFail($id);

                $existingRecord = Access::where('menu_id', $menu->id)
                    ->where('id', '!=', $id) // Excluir el registro actual de la consulta
                    ->first();

                if ($existingRecord) {
                    $error = 'Este menu ya esta asignado a un perfil';
                    return null;
                } else {

                    $menu->delete();

                    $exitoso = Menu::orderBy('code', 'asc')->get();
                    return null;
                }
            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxitoo', $exitoso));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}

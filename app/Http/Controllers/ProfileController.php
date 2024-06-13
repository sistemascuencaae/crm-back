<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\FormatResponseTrait;
use App\Models\Menu;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Profile;
use App\Models\Access;


class ProfileController extends Controller
{
    use FormatResponseTrait;
    //  public function __construct() {
    //       $this->middleware('auth:api');
    // }

    public function list()
    {
        try {
            //$data = \App\Models\Profile::where('isactive', '1')->get();
            $sql = "SELECT * FROM hclinico.profiles where  isactive=1";
            $data = DB::select($sql);

            return $this->getOk($data);
        } catch (Exception $e) {
            return $this->getErrCustom($e->getMessage(), 'Error: la información no se logro conseguir: ');
        }
    }

    public function findById($id)
    {

        $profile = Profile::with(['access', 'access.menu'])->find($id);
        if (is_object($profile)) {
            $data = array(
                'code' => 200,
                'status' => 'success',
                // 'profile' => $profile,
                'data' => $profile,
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

    public function findByProgram($profile, $program)
    {

        /*$profile = Access::where([
                    ['profile_id','=',$profile],
                    ['menu_id','=',$program]
            ])->get();*/
        $profile = Access::with('menu')->whereHas('menu', function ($query) use ($program) {
            $query->where('name', $program);
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

    public function findByUser($userid)
    {
        $sql = "SELECT u.id,u.name,u.surname,u.login,u.profile_id,
                    a.menu_id,
                    m.code,m.module,m.name,m.url,m.icon,
                    a.create,a.edit,a.delete,a.report,a.ejecutar
                FROM crm.users u
                INNER JOIN access a on u.profile_id=a.profile_id
                INNER JOIN menu m on a.menu_id=m.id
                where  u.id=?
                ORDER BY m.code";

        try {
            $accesos = DB::select($sql, [$userid]);
            $data = array(
                'code' => 200,
                'status' => 'success',
                'data' => $accesos
            );
        } catch (Exception $e) {
            $data = array(
                'code' => 400,
                'status' => 'error',
                'message' => 'Error: No se obtener los permisos del usuario',
                'error' => $e,
            );
        }
        return response()->json($data, $data['code']);
    }

    // JUAN PERFILES

    public function all()
    {
        try {
            $data = Profile::orderBy('id', 'asc')->get();

            // Especificar las propiedades que representan fechas en tu objeto
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $data->map(function ($item) use ($dateFields) {
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function create(Request $request)
    {
        try {
            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($request, &$error, &$exitoso) {

                // Verificamos si ya existe un perfil con el mismo nombre
                $existingProfile = Profile::where('name', $request->name)->first();

                if ($existingProfile) {
                    $error = 'Ya EXISTE un perfil con el mismo nombre';
                    return null;
                } else {
                    $profile = Profile::create([
                        'name' => $request->name,
                        'isactive' => $request->isactive,
                    ]);

                    // Crear los accesos del perfil
                    foreach ($request->access as $accessData) {
                        $accessData['profile_id'] = $profile->id;
                        Access::create($accessData);
                    }

                    $exitoso = Profile::orderBy('id', 'asc')->get();

                    // Especificar las propiedades que representan fechas en tu objeto
                    $dateFields = ['created_at', 'updated_at'];
                    // Utilizar la función map para transformar y obtener una nueva colección
                    $exitoso->map(function ($item) use ($dateFields) {
                        $funciones = new Funciones();
                        $funciones->formatoFechaItem($item, $dateFields);
                        return $item;
                    });

                    return null;
                }
            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $exitoso));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function buscarAccesosByProfileId(Request $request, $profile_id)
    {
        try {
            $menus = Menu::orderBy('code', 'asc')->get();

            $accesosPerfil = [];

            foreach ($menus as $menuData) {
                $acceso = Access::where('profile_id', $profile_id)->where('menu_id', $menuData['id'])->with('menu')->first();

                if ($acceso) {
                    // Agregamos el acceso al array, como hacer un push en el front angular
                    $accesosPerfil[] = $acceso;
                } else {
                    // Si no se encuentra un acceso de un menu, creamos uno default
                    $defaultAccess = [
                        'id' => null,
                        'profile_id' => $profile_id,
                        'menu_id' => $menuData['id'],
                        'view' => 0,
                        'create' => 0,
                        'edit' => 0,
                        'delete' => 0,
                        'report' => 0,
                        'ejecutar' => 0,
                        'created_at' => null,
                        'updated_at' => null,
                        'menu' => $menuData // Agregar el objeto Menu relacionado
                    ];
                    $accesosPerfil[] = (object) $defaultAccess;
                }
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $accesosPerfil));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function edit(Request $request, $id)
    {
        try {
            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($id, $request, &$error, &$exitoso) {

                // Verificar si ya existe un perfil con el mismo nombre
                $existingProfile = Profile::where('name', $request->name)
                    ->where('id', '!=', $id) // Excluye el perfil que estás editando
                    ->first();

                if ($existingProfile) {
                    $error = 'Ya EXISTE un perfil con el mismo nombre';
                    return null;
                    // return response()->json(RespuestaApi::returnResultado('error', 'El Perfil ya existe', ''));
                } else {

                    $perfil = Profile::findOrFail($id);

                    $perfil->update($request->all());

                    //eliminos los access actuales
                    Access::where('profile_id', $id)->delete();

                    // Crear los accesos del perfil
                    foreach ($request->access as $accessData) {
                        $accessData['profile_id'] = $perfil->id;
                        Access::create($accessData);
                    }

                    $exitoso = Profile::orderBy('id', 'asc')->get();

                    // Especificar las propiedades que representan fechas en tu objeto
                    $dateFields = ['created_at', 'updated_at'];
                    // Utilizar la función map para transformar y obtener una nueva colección
                    $exitoso->map(function ($item) use ($dateFields) {
                        $funciones = new Funciones();
                        $funciones->formatoFechaItem($item, $dateFields);
                        return $item;
                    });

                    return null;
                }
            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, ''));
            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $exitoso));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function deleteProfile(Request $request, $id)
    {
        try {
            $data = DB::transaction(function () use ($id) {
                $profile = Profile::findOrFail($id);

                // Verificar si existen usuarios relacionados con este perfil
                if (User::where('profile_id', $profile->id)->exists()) {
                    return response()->json(RespuestaApi::returnResultado('error', 'No se puede eliminar este perfil porque ya esta asignado a un usuario', ''));
                }

                // Elimina el perfil y sus registros relacionados
                $profile->access()->delete();
                $profile->delete();

                return $profile;
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function clonProfile(Request $request)
    {
        try {
            $error = null;
            $exitoso = null;

            DB::transaction(function () use ($request, &$error, &$exitoso) {

                // Verificar si ya existe un perfil con el mismo nombre
                $existingProfile = Profile::where('name', $request->name)->first();

                if ($existingProfile) {
                    $error = 'Ya EXISTE un perfil con el mismo nombre';
                    return null;
                } else {

                    $profile = Profile::create([
                        'name' => $request->name,
                        'isactive' => $request->isactive,
                    ]);

                    // Crear los accesos del perfil
                    foreach ($request->access as $accessData) {
                        $accessData['profile_id'] = $profile->id;
                        Access::create($accessData);
                    }

                    $exitoso = Profile::orderBy('id', 'asc')->get();

                    // Especificar las propiedades que representan fechas en tu objeto
                    $dateFields = ['created_at', 'updated_at'];
                    // Utilizar la función map para transformar y obtener una nueva colección
                    $exitoso->map(function ($item) use ($dateFields) {
                        $funciones = new Funciones();
                        $funciones->formatoFechaItem($item, $dateFields);
                        return $item;
                    });

                    return null;
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

}

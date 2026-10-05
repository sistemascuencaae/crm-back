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
use Illuminate\Support\Str;
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
                    a.create,a.edit,a.delete,a.report,a.cerrar_actividad,a.cerrar_reagendar_actividad,a.audit,a.solicitud_credito,a.revertir,a.rutaje,a.canal_cliente,a.ejecutar
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
            $data = Profile::orderBy('name', 'asc')->get();

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
        if (!$this->tienePermisoPerfiles('create')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para crear perfiles', ''));
        }

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

                    $this->guardarTiposCasoPerfil($request, $profile->id);
                    $this->registrarAuditoriaPerfil($request, $profile->id, 'INSERT', null);

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
                        'cerrar_actividad' => 0,
                        'cerrar_reagendar_actividad' => 0,
                        'audit' => 0,
                        'solicitud_credito' => 0,
                        'revertir' => 0,
                        'rutaje' => 0,
                        'canal_cliente' => 0,
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
        if (!$this->tienePermisoPerfiles('edit')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para editar perfiles', ''));
        }

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

                    // Foto antes de tocar nada, para el diff de la auditoría.
                    $fotoAntes = $this->fotoAuditoriaPerfil($perfil->id);

                    $perfil->update($request->all());

                    //eliminos los access actuales
                    Access::where('profile_id', $id)->delete();

                    // Crear los accesos del perfil
                    foreach ($request->access as $accessData) {
                        $accessData['profile_id'] = $perfil->id;
                        Access::create($accessData);
                    }

                    $this->guardarTiposCasoPerfil($request, $perfil->id);
                    $this->registrarAuditoriaPerfil($request, $perfil->id, 'UPDATE', $fotoAntes);

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

    // Los perfiles no se eliminan, solo se desactivan (el botón Eliminar del listado quedó comentado).
    public function deleteProfile(Request $request, $id)
    {
        return response()->json(RespuestaApi::returnResultado('error', 'Los perfiles no se eliminan: desactívelo desde Editar.', ''));

        // try {
        //     $data = DB::transaction(function () use ($id) {
        //         $profile = Profile::findOrFail($id);

        //         // Verificar si existen usuarios relacionados con este perfil
        //         if (User::where('profile_id', $profile->id)->exists()) {
        //             return response()->json(RespuestaApi::returnResultado('error', 'No se puede eliminar este perfil porque ya esta asignado a un usuario', ''));
        //         }

        //         // Elimina el perfil y sus registros relacionados
        //         $profile->access()->delete();
        //         $profile->delete();

        //         return $profile;
        //     });
        //     return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $data));
        // } catch (Exception $e) {
        //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        // }
    }

    public function clonProfile(Request $request)
    {
        if (!$this->tienePermisoPerfiles('create')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para clonar perfiles', ''));
        }

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

                    // El request trae el id del perfil de origen: la auditoría anota de cuál salió.
                    $this->guardarTiposCasoPerfil($request, $profile->id);
                    $this->registrarAuditoriaPerfil($request, $profile->id, 'INSERT', null, (int) $request->input('id') ?: null);

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

    // Listado paginado de perfiles (grid de profile-list), mismo estándar que el de usuarios.
    // Sin busqueda -> crm.fn_perfil_listar_paginacion | con busqueda -> crm.fn_perfil_buscar_paginacion
    public function listarPerfiles(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($busqueda !== '') {
                $registros = DB::select('SELECT * FROM crm.fn_perfil_buscar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);
            } else {
                $registros = DB::select('SELECT * FROM crm.fn_perfil_listar_paginacion(?, ?)', [$pagina, $tamanio]);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Perfiles listados con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los perfiles', $th->getMessage()));
        }
    }

    // Tipos de caso con la marca del perfil, para la fila "Todos los casos" de la matriz. Perfil 0 = Nuevo.
    public function tiposCasoPerfil($profile_id)
    {
        try {
            $tipos = DB::select('SELECT * FROM crm.fn_perfil_tipo_caso_listar(?)', [(int) $profile_id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $tipos));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los tipos de caso', $th->getMessage()));
        }
    }

    // Auditoría de UN perfil (modal del listado): resumen y eventos de auditoria.logs_cambios, módulo PERFILES.
    public function perfilAuditoria(Request $request)
    {
        if (!$this->tienePermisoPerfiles('audit')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para ver la auditoría de perfiles', null));
        }

        try {
            $profileId = (int) $request->query('profile_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($profileId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Perfil no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM crm.fn_perfil_auditoria_resumen(?)', [$profileId]);
            $eventos = DB::select('SELECT * FROM crm.fn_perfil_auditoria_listar_paginacion(?, ?, ?, ?)', [$profileId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del perfil', $th->getMessage()));
        }
    }

    // Permisos del menú Perfiles (crm.menu.name = 'PROFILES') del usuario que hace la petición.
    private function tienePermisoPerfiles($accion)
    {
        return Access::where('profile_id', auth()->user()->profile_id)
            ->where($accion, 1)
            ->whereHas('menu', function ($query) {
                $query->where('name', 'PROFILES');
            })
            ->exists();
    }

    // Sin tipos_caso en el request no se tocan, así nada los borra por accidente.
    private function guardarTiposCasoPerfil(Request $request, $profileId)
    {
        if (!$request->has('tipos_caso')) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', (array) $request->input('tipos_caso', []))));

        DB::select('SELECT crm.fn_perfil_tipo_caso_reemplazar(?, ?::jsonb)', [$profileId, json_encode($ids)]);
    }

    // Foto del perfil (nombre, estado, permisos por menú y tipos de caso) para la auditoría; null si no existe.
    private function fotoAuditoriaPerfil($profileId)
    {
        return DB::selectOne('SELECT crm.fn_perfil_auditoria_armar_foto(?) AS foto', [$profileId])->foto;
    }

    // Una fila por alta, clon o edición en auditoria.logs_cambios (módulo PERFILES); la foto de después la arma la función.
    private function registrarAuditoriaPerfil(Request $request, $profileId, $operacion, $fotoAntes, $clonadoDe = null)
    {
        DB::select('SELECT crm.fn_perfil_auditoria_registrar(?, ?, ?::jsonb, ?::integer, ?::jsonb)', [
            $profileId,
            $operacion,
            $fotoAntes,
            $clonadoDe,
            json_encode($this->contextoAuditoriaForense($request)),
        ]);
    }

    // Quién, desde dónde y en qué request, igual que en usuarios y clientes.
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ];
    }

}

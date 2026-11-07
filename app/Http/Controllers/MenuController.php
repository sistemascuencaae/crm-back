<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\FormatResponseTrait;
use App\Models\Access;
use App\Models\Menu;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class MenuController extends Controller
{
    use FormatResponseTrait;

    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    // Lo que la pantalla puede mandar a crm.fn_menu_crear y crm.fn_menu_modificar.
    private const CAMPOS_PAYLOAD = ['module', 'name', 'description', 'url', 'icon', 'padre_id', 'titulo', 'en_menu'];

    // Las reglas están en las funciones crm.fn_menu_*; aquí solo el texto de cada código.
    private const MENSAJES_ERROR = [
        'MENU_NO_EXISTE' => 'El menú no existe.',
        'REQUIERE_TITULO' => 'El título es obligatorio.',
        'TITULO_MUY_LARGO' => 'El título no puede pasar de 120 caracteres.',
        'NOMBRE_DUPLICADO' => 'Ya existe un menú con ese nombre.',
        'RUTA_DUPLICADA' => 'Otro menú ya usa esa ruta: el último tramo de la URL no se puede repetir.',
        'REQUIERE_DESCRIPCION' => 'La descripción es obligatoria.',
        'URL_MUY_LARGA' => 'La URL no puede pasar de 50 caracteres.',
        'ICONO_MUY_LARGO' => 'El ícono no puede pasar de 50 caracteres.',
        'PADRE_NO_EXISTE' => 'El menú padre no existe.',
        'PADRE_CICLO' => 'Un menú no puede quedar debajo de sí mismo ni de sus descendientes.',
        'DESTINO_NO_EXISTE' => 'El menú donde se quiere soltar ya no existe.',
        'ZONA_INVALIDA' => 'La posición de destino no es válida.',
        'MOVER_SOBRE_SI_MISMO' => 'No se puede soltar un menú sobre sí mismo.',
        'MENU_CON_HIJOS' => 'Este menú tiene hijos: muévalos o elimínelos primero.',
        'MENU_ASIGNADO_A_PERFIL' => 'Este menu ya esta asignado a un perfil',
    ];

    public function list()
    {
        // $data = Menu::all();
        $data = $this->menusEnOrden();

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

    // Version 2.0: las reglas y la auditoría pasaron a crm.fn_menu_crear.
    public function addMenu(Request $request)
    {
        if (!$this->tienePermisoMenus('create')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para crear menús', ''));
        }

        return $this->ejecutar('crm.fn_menu_crear', $request, $this->datosFormulario($request), 'Se guardó con éxito', 'Error al guardar el menú');
    }

    // Version 2.0: las reglas y la auditoría pasaron a crm.fn_menu_modificar.
    public function editMenu(Request $request, $id)
    {
        if (!$this->tienePermisoMenus('edit')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para editar menús', ''));
        }

        return $this->ejecutar('crm.fn_menu_modificar', $request, ['id' => (int) $id] + $this->datosFormulario($request), 'Se guardó con éxito', 'Error al guardar el menú');
    }

    // Version 2.0: las reglas y la auditoría pasaron a crm.fn_menu_eliminar.
    public function deleteMenu(Request $request, $id)
    {
        if (!$this->tienePermisoMenus('delete')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para eliminar menús', ''));
        }

        return $this->ejecutar('crm.fn_menu_eliminar', $request, ['id' => (int) $id], 'Se elimino con éxito', 'Error al eliminar el menú');
    }

    // Version 1.0
    // El arrastre de la pantalla: id = el menú, destino_id = la fila donde se soltó, zona = antes, despues o dentro.
    public function moverMenu(Request $request)
    {
        if (!$this->tienePermisoMenus('edit')) {
            return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para mover menús', ''));
        }

        return $this->ejecutar('crm.fn_menu_mover', $request, $request->only(['id', 'destino_id', 'zona']), 'Se movió con éxito', 'Error al mover el menú');
    }

    // Version 1.0
    // El menú lateral del usuario autenticado, con su perfil actual: lo que tiene con Ejecutar y En menú, más los padres, en orden de
    // árbol. Las reglas están en crm.fn_menu_arbol_perfil. La pantalla lo pide al iniciar sesión y al recargar.
    public function menuLateral()
    {
        try {
            $filas = DB::select('SELECT * FROM crm.fn_menu_arbol_perfil(?) ORDER BY posicion', [auth('api')->user()->profile_id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Menú cargado', $filas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el menú', $e->getMessage()));
        }
    }

    // Version 2.0: el árbol sale de crm.fn_menu_arbol_listar, con nivel, tiene_hijos y es_grupo.
    private function menusEnOrden()
    {
        return Menu::fromQuery('SELECT * FROM crm.fn_menu_arbol_listar() ORDER BY posicion');
    }

    // Version 1.0
    private function datosFormulario(Request $request): array
    {
        return $request->only(self::CAMPOS_PAYLOAD);
    }

    // Version 1.0
    // Todas las escrituras responden lo mismo: la lista completa, por la que la pantalla rearma el árbol.
    // $funcion es siempre un nombre fijo de este controlador, nunca algo que llegue del usuario.
    private function ejecutar(string $funcion, Request $request, array $datos, string $mensajeExito, string $mensajeError)
    {
        try {
            $datos['auditoria'] = $this->contextoAuditoriaForense($request);

            DB::selectOne("SELECT {$funcion}(?::jsonb) AS id", [json_encode($datos, JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', $mensajeExito, $this->menusEnOrden()));
        } catch (QueryException $e) {
            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', $mensajeError, $e->getMessage()));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $mensajeError, $e->getMessage()));
        }
    }

    // Version 1.0
    // Permisos del menú Menús (crm.menu.name = 'MENUS') del usuario que hace la petición.
    private function tienePermisoMenus(string $accion): bool
    {
        return Access::where('profile_id', auth()->user()->profile_id)
            ->where($accion, 1)
            ->whereHas('menu', function ($query) {
                $query->where('name', 'MENUS');
            })
            ->exists();
    }

    // Version 1.0
    // Quién, desde dónde y en qué request, igual que en usuarios y perfiles.
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

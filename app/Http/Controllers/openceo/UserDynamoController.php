<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\Almacen;
use App\Models\openceo\Usuario;
use App\Models\User;
use App\Models\UsuarioAlmacen;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserDynamoController extends Controller
{
    public function listAllUsuariosActivos()
    {
        try {
            // 309 - A1 MENU CAJA-FACT
            // 308 - MENU FACT LOCALES DEP

            $data = DB::select("SELECT u.usu_id,
                                        u.usu_alias,
                                        u.usu_nombre || ' ' || u.usu_apellido AS usu_nombre_completo,
                                        p.pve_id,
                                        p.pve_numero,
                                        p.pve_numero || ' - ' || p.pve_nombre AS pve_nombre,
                                        a.alm_id,
                                        a.alm_codigo || ' - ' || a.alm_nombre AS alm_nombre
                                    FROM usuario u
                                        JOIN puntoventa p ON p.pve_id = u.pve_id
                                        JOIN almacen a ON a.alm_id = p.alm_id
                                    WHERE u.usu_activo = true
                                        AND u.cme_id IN (308, 309)
                                    ORDER BY u.usu_nombre ASC;
                                ");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listAlmacenesActivos()
    {
        try {
            $data = Almacen::where('alm_activo', true)
                ->with(['puntoventa' => function ($query) {
                    $query->selectRaw("*, CONCAT(pve_numero, ' - ', pve_nombre) as pve_nombre1")->where('pve_activo', true)->orderBy('pve_nombre', 'asc');
                }])
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editUsuarioPuntoVenta(Request $request)
    {
        try {
            $exitoso = null;
            $error = null;

            $data = DB::transaction(function () use ($request, &$exitoso, &$error) {

                $actualizado = Usuario::where('usu_id', $request->usu_id)
                    ->update(['pve_id' => $request->pve_id]);

                // devuelve 0 cuando NO se actualiza
                if ($actualizado === 0) {
                    $error = 'No se pudo actualizar o el usuario no existe.';
                    return null;
                } else { // devuelve 1 cuando SI se actualiza



                    // START Este bloque de código, actualiza el permiso y la agencia de la cajera en el CRM.
                    // 1. Verificamos que sea diferente de una feria para que no me cambie la agencia ni los permismos de la cajera.
                    // (Puntos de venta de las ferias que estan vinculados alm_id = 1 que es Luis_Cordero_2 (601...))
                    if (!in_array(
                        $request->pve_numero,
                        [601, 602, 603, 604, 605, 606, 607, 608, 609, 610, 611, 612, 613, 614, 615, 616, 617, 618, 619, 620, 621, 622, 623, 624, 625, 626]
                    )) {

                        // 2. Buscamos el usuario por usu_alias.
                        $user = User::where('usu_alias', $request->usu_alias)->first();

                        // 3. Si existe en el CRM ingresa para actualizar su agencia.
                        if ($user) {
                            // 4. Actualiza la agencia de la cajera.
                            $user->update([
                                'alm_id' => $request->alm_id
                            ]);

                            // 5. Elimina todos los permisos de las agencias de la cajera.
                            UsuarioAlmacen::where('user_id', $user->id)->delete();

                            // 6. Crea el permiso de la agencia.
                            UsuarioAlmacen::create([
                                'alm_id' => $request->alm_id,
                                'user_id' => $user->id,
                            ]);
                        }
                    }
                    //  END Este bloque de código, actualiza el permiso y la agencia de la cajera en el CRM.



                    //       cti_id                                         /  pgr_id
                    // (fila1) 59 FACTURA ELECTRONICA							184 IFACTURACION
                    // (fila2) 67 NOTAS DE CREDITO CLIENTES ELECTRONICAS		399 iNotaCreditoDirecto.jsf
                    // (fila3) 67 NOTAS DE CREDITO CLIENTES ELECTRONICAS		320 iNccvalores.jsf
                    // (fila4) 59 FACTURA ELECTRONICA							281 iFactPed.jsf

                    $reglas = [
                        ['cti_id' => 59, 'prg_id' => 184],
                        ['cti_id' => 67, 'prg_id' => 399],
                        ['cti_id' => 67, 'prg_id' => 320],
                        ['cti_id' => 59, 'prg_id' => 281],
                    ];

                    foreach ($reglas as $r) {
                        DB::table('usuario_comprobante')->updateOrInsert(
                            [
                                'usu_id' => $request->usu_id,
                                'cti_id' => $r['cti_id'],
                                'prg_id' => $r['prg_id'],
                            ],
                            [
                                'pve_id' => $request->pve_id,
                            ]
                        );
                    }

                    $exitoso = 'Se actualizó con éxito.';
                    $usuario = DB::selectOne("SELECT u.usu_id, u.usu_alias, u.usu_nombre || ' ' || u.usu_apellido as usu_nombre_completo,
                                                        p.pve_id, p.pve_numero || ' - ' || p.pve_nombre as pve_nombre,
                                                        a.alm_id, a.alm_codigo || ' - ' || a.alm_nombre as alm_nombre
                                                    FROM usuario u
                                                        JOIN puntoventa p ON p.pve_id = u.pve_id
                                                        JOIN almacen a ON a.alm_id = p.alm_id
                                                    WHERE u.usu_id = ?;", [$request->usu_id]);
                    return $usuario;
                }
            });

            if ($error) {
                return response()->json(RespuestaApi::returnResultado('error', $error, $data));
            } else if ($exitoso) {
                return response()->json(RespuestaApi::returnResultado('success', $exitoso, $data));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}

<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteDynamoController extends Controller
{
    //
    public function add(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'direccion' => 'required|string',
        //     'telefono' => 'required|string',
        //     'identificacion' => 'required|string',
        //     'tipoIdentificacion' => 'required|string',
        //     'nombres' => 'required|string',
        //     'apellidos' => 'required|string',
        //     'email' => 'required|string',
        //     'empId' => 'required|numeric',
        //     'identificacionConyugue' => 'required|string',
        //     'nombreConyugue' => 'required|string',
        //     'apellidoConyugue' => 'required|string',
        // ]);
        //echo ('$validator: '.json_encode($validator->fails()));
        return response()->json($validator->errors(), 422); ;
        try {
            $cliente = db::transaction(function () use ($request) {

                $direccion = $request->input('direccion');
                $telefono = $request->input('telefono');
                $identificacion = $request->input('identificacion');
                $tipoIdentificacion = $request->input('tipoIdentificacion');
                $nombres = $request->input('nombres');
                $apellidos = $request->input('apellidos');
                $email = $request->input('email');
                $empId = $request->input('empId');
                $identificacionConyugue = $request->input('identificacionConyugue');
                $nombreConyugue = $request->input('nombreConyugue');
                $apellidoConyugue = $request->input('apellidoConyugue');

                $dirId = DB::insert("INSERT into direccion (dir_calle_principal) values (?) returning dir_id;", [$direccion]);

                $telId = DB::insert("INSERT into telefono (tte_id, tel_numero) values (1, ?) returning tel_id;", [$telefono]);

                $entId = DB::insert("INSERT into entidad (ent_identificacion, ent_nombres, ent_apellidos, tit_id, ent_direccion_principal , ent_tipo_identificacion, ent_email, ent_telefono_principal)
                        values (?,?,?,(select to_number(par_texto,'999999') as tit_id
                        from parametro where par_abreviacion='TIT' and mod_abreviatura='CLI' limit 1),
                        ?,?,?,?) returning ent_id;", [$identificacion, $nombres, $apellidos, $dirId, $tipoIdentificacion, $email, $telId]);

                $cliId = DB::insert("INSERT into cliente (cli_codigo, ent_id, ubi_id, zon_id,
                            cat_id, pol_id, lpr_id, cli_tipocli, emp_id, can_id, ent_nombre_comercial, cli_tiposujeto,
                            cli_sexo, cli_estadocivil, cli_ingresos) values
                            (?, ?,(select to_number(par_texto,'999999') as tit_id
                            from parametro
                            where par_abreviacion='UBI' and mod_abreviatura='CLI'
                            limit 1), (select zon_id
                            from zona where zon_codigo in (select par_texto
                            from parametro
                            where par_abreviacion='ZON' and mod_abreviatura='CLI'
                            limit 1) limit 1), (select cat_id
                                from catcliente where cat_abreviacion = 'clien'), (select pol_id
                                from politica
                                where pol_nombre = 'CONTADO' and pol_tipocli =1
                                ), (select lpr_id
                                    from listapre where lpr_nombre in (select par_texto
                                    from parametro
                                    where par_abreviacion='LPR' and mod_abreviatura='CLI'
                                    limit 1) limit 1), 1, ?, (select to_number(par_texto,'999999') as can_id
                                    from parametro
                                    where par_abreviacion='CAN' and mod_abreviatura='CLI'
                                    limit 1),?, 'N','M','S', 'I' ) returning cli_id", [$identificacion, $entId, $empId, ($nombres . ' ' . $apellidos)]);

                $cliTipoPago = DB::insert("insert into cliente_tipo_pago(cli_id, sfp_id) values (?, 1)", [$cliId]);
                if ($identificacionConyugue && $nombreConyugue && $apellidoConyugue) {
                    $cliAnexo = DB::insert(
                        "INSERT into cliente_anexo(cliane_identificacion_conyuge, cliane_nombre_conyuge,cli_id) values (?,?,?)",
                        [$identificacionConyugue, $nombreConyugue, $cliId]
                    );
                }

                $cliente = DB::select('SELECT * FROM public.cliente WHERE cli_id = ?', [$cliId]);
                return $cliente;
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $cliente));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Al listar', $th->getMessage()));
        }
    }
}


// query: insert into cliente (cli_codigo, ent_id, ubi_id, zon_id,
//         cat_id, pol_id, lpr_id, cli_tipocli, emp_id, can_id, ent_nombre_comercial, cli_tiposujeto,
//         cli_sexo, cli_estadocivil, cli_ingresos) values
//         ($1, $2,(select to_number(par_texto,'999999') as tit_id
//         from parametro
//         where par_abreviacion='UBI' and mod_abreviatura='CLI'
//         limit 1), (select zon_id
//           from zona where zon_codigo in (select par_texto
//           from parametro
//           where par_abreviacion='ZON' and mod_abreviatura='CLI'
//           limit 1) limit 1), (select cat_id
//             from catcliente where cat_abreviacion = 'clien'), (select pol_id
//               from politica
//               where pol_nombre = 'CONTADO' and pol_tipocli =1
//               ), (select lpr_id
//                 from listapre where lpr_nombre in (select par_texto
//                 from parametro
//                 where par_abreviacion='LPR' and mod_abreviatura='CLI'
//                 limit 1) limit 1), 1, $3, (select to_number(par_texto,'999999') as can_id
//                 from parametro
//                 where par_abreviacion='CAN' and mod_abreviatura='CLI'
//                 limit 1), $4, 'N','M','S', 'I' ) returning cli_id -- PARAMETERS: ["1303753618",1246021,438,"PABLO MARCELO ABAD NIETO"]

<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\Cliente;
use App\Models\openceo\Direccion;
use App\Models\openceo\Entidad;
use App\Models\openceo\Telefono;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

use function PHPUnit\Framework\throwException;

class ClienteDynamoController extends Controller
{
    //
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'direccion' => 'required|string',
            'telefono' => 'required|string',
            'identificacion' => 'required|string',
            'tipoIdentificacion' => 'required|string',
            'nombres' => 'required|string',
            'apellidos' => 'required|string',
            'email' => 'required|string',
            'empId' => 'required|numeric',
            // 'identificacionConyugue' => 'required|string',
            // 'nombreConyugue' => 'required|string',
            // 'apellidoConyugue' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación datos', $validator->errors()));
        }
        try {
            $cliente = DB::transaction(function () use ($request) {
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
                //--- NUEVA DIRECCION
                $newDireccion = new Direccion();
                $newDireccion->dir_calle_principal = $direccion;
                $newDireccion->save();
                //--- NUEVO TELEFONO
                $newTelefono = new Telefono();
                $newTelefono->tte_id = 1;
                $newTelefono->tel_numero = $telefono;
                $newTelefono->save();
                //--- NUEVA ENTIDAD
                $valor1 = DB::selectOne("SELECT to_number(par_texto,'999999') as tit_id from parametro where par_abreviacion='TIT' and mod_abreviatura='CLI' limit 1");
                $newEntidad = new Entidad();
                $newEntidad->ent_identificacion = $identificacion;
                $newEntidad->ent_nombres = $nombres;
                $newEntidad->ent_apellidos = $apellidos;
                $newEntidad->tit_id = $valor1->tit_id;
                $newEntidad->ent_direccion_principal = $newDireccion->dir_id;
                $newEntidad->ent_tipo_identificacion = $tipoIdentificacion;
                $newEntidad->ent_email = $email;
                $newEntidad->ent_telefono_principal = $newTelefono->tel_id;
                $newEntidad->save();
                //--- NUEVO CLIENTE
                $valor2 = DB::selectOne("SELECT to_number(par_texto,'999999') as tit_id from parametro where par_abreviacion='UBI' and mod_abreviatura='CLI' LIMIT 1");
                $valor3 = DB::selectOne("SELECT zon_id from zona where zon_codigo in (select par_texto from parametro
                            where par_abreviacion='ZON' and mod_abreviatura='CLI' limit 1) limit 1");
                $valor4 = DB::selectOne("SELECT cat_id from catcliente where cat_abreviacion = 'clien'");
                $valor5 = DB::selectOne("SELECT pol_id from politica where pol_nombre = 'CONTADO' and pol_tipocli = 1");
                $valor6 = DB::selectOne("SELECT lpr_id from listapre where lpr_nombre in (select par_texto
                            from parametro where par_abreviacion='LPR' and mod_abreviatura='CLI' limit 1) limit 1");
                $valor7 = DB::selectOne("SELECT to_number(par_texto,'999999') as can_id from parametro where par_abreviacion='CAN' and mod_abreviatura='CLI' limit 1");
                $newCliente = new Cliente();
                $newCliente->cli_codigo = $identificacion;
                $newCliente->ent_id = $newEntidad->ent_id;
                $newCliente->ubi_id = $valor2->tit_id;
                $newCliente->zon_id = $valor3->zon_id;
                $newCliente->cat_id = $valor4->cat_id;
                $newCliente->pol_id = $valor5->pol_id;
                $newCliente->lpr_id = $valor6->lpr_id;
                $newCliente->cli_tipocli = 1;
                $newCliente->emp_id = $empId;
                $newCliente->can_id = $valor7->can_id;
                $newCliente->ent_nombre_comercial = $nombres . ' ' . $apellidos;
                $newCliente->cli_tiposujeto = 'N';
                $newCliente->cli_sexo = 'M';
                $newCliente->cli_estadocivil = 'S';
                $newCliente->cli_ingresos = 'I';
                $newCliente->save();
                $cliTipoPago = DB::insert("insert into cliente_tipo_pago(cli_id, sfp_id) values (?, 1)", [$newCliente->cli_id]);
                if ($identificacionConyugue && $nombreConyugue && $apellidoConyugue) {
                    $cliAnexo = DB::insert(
                        "INSERT into cliente_anexo(cliane_identificacion_conyuge, cliane_nombre_conyuge,cli_id) values (?,?,?)",
                        [$identificacionConyugue, $nombreConyugue, $newCliente->cli_id]
                    );
                }
                return $newCliente;
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

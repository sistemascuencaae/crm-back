<?php

namespace App\Http\Controllers\sts;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\Cliente;
use App\Models\openceo\Direccion;
use App\Models\openceo\Entidad;
use App\Models\openceo\Telefono;
use App\Models\sts\ClientesMultinivel;
use App\Servicios\ValidacionCedulaRucService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use Validator;

class DynamoClienteController extends Controller
{
    // Version 1.0
    public function verificarClienteDynamo(Request $request)
    {
        try {
            $identificacion = trim($request->input('identificacion'));
            $tipoidentificacion = trim($request->input('tipoidentificacion'));

            // 1: Validar que los campos requeridos no estén vacíos
            if (empty($identificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar una identificación.', null));
            }

            if (empty($tipoidentificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar el tipo de identificación.', null));
            }

            // 2: Validar formato de la identificación según su tipo 1=Cédula; 2=RUC; 3=Pasaporte no se valida
            if ($tipoidentificacion == 1) {
                if (!ValidacionCedulaRucService::esCedulaValida($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'La cédula ingresada no es válida', null));
                }
            } elseif ($tipoidentificacion == 2) {
                if (!ValidacionCedulaRucService::esRucValido($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'El RUC ingresado no es válido', null));
                }
            }

            // 3: Corto a 10 digitos la identificacion que viene del frontEnd
            $identificacionBusqueda = substr($identificacion, 0, 10);

            // 4: Verifico si ya existe el cliente
            $clienteOpenceo = DB::selectOne(
                "SELECT c.cli_codigo FROM public.cliente c
                                                WHERE SUBSTRING(TRIM(c.cli_codigo), 1, 10) = ?
                                                    AND c.cli_tipocli = 1",
                [$identificacionBusqueda]
            );

            if ($clienteOpenceo) {
                return response()->json(RespuestaApi::returnResultado('success', 'El cliente ya existe', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'El cliente no existe', null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }





    // Version 1.0
    public function addDynamoCliente(Request $request)
    {
        try {
            $identificacion = trim($request->input('identificacion'));
            $tipoidentificacion = trim($request->input('tipoidentificacion'));

            // 1: Revalidar identificación
            if (empty($identificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar una identificación.', null));
            }

            if (empty($tipoidentificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar el tipo de identificación.', null));
            }

            if ($tipoidentificacion == 1) {
                if (!ValidacionCedulaRucService::esCedulaValida($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'La cédula ingresada no es válida', null));
                }
            } elseif ($tipoidentificacion == 2) {
                if (!ValidacionCedulaRucService::esRucValido($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'El RUC ingresado no es válido', null));
                }
            }

            // 2: Validar todos los campos del formulario
            $validator = Validator::make($request->all(), [
                'direccion' => 'required|string',
                'telefono' => 'required|string',
                'identificacion' => 'required|string',
                'tipoidentificacion' => 'required|string',
                'nombres' => 'required|string',
                'apellidos' => 'required|string',
                'email' => 'required|string',
                'empId' => 'required|numeric',
                'usuario_netos' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', 'Validación datos', $validator->errors()));
            }

            // 3: Cortamos a 10 caracteres
            $identificacionBusqueda = substr($identificacion, 0, 10);

            // 4: Verificar si ya existe como cliente
            $clienteOpenceo = DB::selectOne(
                "SELECT * FROM public.cliente c
                                            WHERE SUBSTRING(TRIM(c.cli_codigo), 1, 10) = ?
                                                AND c.cli_tipocli = 1",
                [$identificacionBusqueda]
            );

            if ($clienteOpenceo) {
                return response()->json(RespuestaApi::returnResultado('error', 'El cliente ya existe', null));
            }

            // 5: Buscar si existe la entidad
            $entidad = DB::selectOne("SELECT * FROM public.entidad e
                WHERE SUBSTRING(TRIM(e.ent_identificacion), 1, 10) = ?", [$identificacionBusqueda]);

            // 6: Crear cliente
            $cliente = DB::transaction(function () use ($request, $entidad) {
                $direccion = mb_strtoupper(trim($request->input('direccion')));
                $telefono = trim($request->input('telefono'));
                $identificacion = trim($request->input('identificacion'));
                $tipoIdentificacion = trim($request->input('tipoidentificacion'));
                $nombres = mb_strtoupper(trim($request->input('nombres')));
                $apellidos = mb_strtoupper(trim($request->input('apellidos')));
                $email = mb_strtolower(trim($request->input('email')));
                $empId = $request->input('empId');
                $identificacionConyugue = mb_strtoupper(trim($request->input('identificacionConyugue') ?? ''));
                $nombreConyugue = mb_strtoupper(trim($request->input('nombreConyugue') ?? ''));
                $apellidoConyugue = mb_strtoupper(trim($request->input('apellidoConyugue') ?? ''));

                // 6.1: Crear nueva dirección (siempre se crea)
                $newDireccion = new Direccion();
                $newDireccion->dir_calle_principal = $direccion;
                $newDireccion->dir_calle_secundaria = '.';
                $newDireccion->save();

                // 6.2: Crear nuevo teléfono (siempre se crea)
                $newTelefono = new Telefono();
                $newTelefono->tte_id = 2; // 2 es celular
                $newTelefono->tel_numero = $telefono;
                $newTelefono->save();

                $entId = null;

                if ($entidad) {
                    // 6.3.1: ENTIDAD EXISTE -> actualizar con los nuevos datos de dirección/teléfono
                    DB::update(
                        "UPDATE public.entidad SET
                        ent_nombres = ?,
                        ent_apellidos = ?,
                        ent_tipo_identificacion = ?,
                        ent_email = ?,
                        ent_direccion_principal = ?,
                        ent_telefono_principal = ?
                        WHERE ent_id = ?",
                        [$nombres, $apellidos, $tipoIdentificacion, $email, $newDireccion->dir_id, $newTelefono->tel_id, $entidad->ent_id]
                    );

                    $entId = $entidad->ent_id;
                } else {
                    // 6.3.2: ENTIDAD NO EXISTE -> crear nueva entidad
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

                    $entId = $newEntidad->ent_id;
                }

                // 6.4: Obtener parámetros por defecto para el nuevo cliente
                // Ubicación
                $valor2 = DB::selectOne("SELECT to_number(par_texto,'999999') as tit_id from parametro where par_abreviacion='UBI' and mod_abreviatura='CLI' LIMIT 1");
                // Zona
                $valor3 = DB::selectOne("SELECT zon_id from zona where zon_codigo in (select par_texto from parametro where par_abreviacion='ZON' and mod_abreviatura='CLI' limit 1) limit 1");
                // Categoría
                $valor4 = DB::selectOne("SELECT cat_id from catcliente where cat_abreviacion = 'clien'");
                // Política
                $valor5 = DB::selectOne("SELECT pol_id from politica where pol_nombre = 'CONTADO' and pol_tipocli = 1");
                // Lista de precios
                $valor6 = DB::selectOne("SELECT lpr_id from listapre where lpr_nombre in (select par_texto from parametro where par_abreviacion='LPR' and mod_abreviatura='CLI' limit 1) limit 1");
                // Canal
                $valor7 = DB::selectOne("SELECT to_number(par_texto,'999999') as can_id from parametro where par_abreviacion='CAN' and mod_abreviatura='CLI' limit 1");

                // 6.5: Crear nuevo cliente
                $newCliente = new Cliente();

                $newCliente->cli_codigo = $identificacion;
                $newCliente->ent_id = $entId;
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
                $newCliente->cli_activo = true;
                $newCliente->save();

                // 6.6: Asignar tipo de pago por defecto sfp_id=1
                DB::insert("insert into cliente_tipo_pago(cli_id, sfp_id) values (?, 1)", [$newCliente->cli_id]);

                // 6.7: Registrar datos del cónyuge (opcional)
                if ($identificacionConyugue && $nombreConyugue && $apellidoConyugue) {
                    DB::insert(
                        "INSERT into cliente_anexo(cliane_identificacion_conyuge, cliane_nombre_conyuge,cli_id) values (?,?,?)",
                        [$identificacionConyugue, $nombreConyugue, $newCliente->cli_id]
                    );
                }

                // 6.8: Registrar el cliente al corredor Netos en Dynamo
                $usuarioNetos = trim($request->input('usuario_netos'));
                $clienteMultinivel = new ClientesMultinivel();
                $clienteMultinivel->cli_id = $newCliente->cli_id;
                $clienteMultinivel->usuario_netos = $usuarioNetos;
                $clienteMultinivel->save();

                return $newCliente;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente creado con éxito', $cliente));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}

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
use Illuminate\Support\Facades\Crypt;
use Exception;
use Illuminate\Http\JsonResponse;
use Validator;

class DynamoClienteController extends Controller
{
    // Version 1.0
    public function verificarClienteDynamo(Request $request)
    {
        try {
            $tipoidentificacion = trim($request->input('tipoidentificacion'));
            $identificacion = trim($request->input('identificacion'));

            if (empty($identificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar una identificación.', null));
            }
            if (empty($tipoidentificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar el tipo de identificación.', null));
            }

            // Extraer usuario_netos del token cifrado (igual que addDynamoCliente)
            $tokenData = $this->validarTokenEnlace($request);
            if ($tokenData instanceof JsonResponse) {
                return $tokenData;
            }
            $usuarioNetos = $tokenData['usuario_netos'];

            if ($tipoidentificacion == 1) {
                if (!ValidacionCedulaRucService::esCedulaValida($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'La cédula ingresada no es válida', null));
                }
            } elseif ($tipoidentificacion == 2) {
                if (!ValidacionCedulaRucService::esRucValido($identificacion)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'El RUC ingresado no es válido', null));
                }
            }

            $identificacionBusqueda = substr($identificacion, 0, 10);

            // LEFT JOIN: netos_vinculado = null si el cliente existe pero no tiene multinivel
            $resultado = DB::selectOne("SELECT c.cli_id, c.cli_codigo,
                                            e.ent_id, e.ent_nombres, e.ent_apellidos, e.ent_email, e.ent_tipo_identificacion,
                                            t.tel_numero,
                                            d.dir_calle_principal, d.dir_calle_secundaria,
                                            cm.usuario_netos AS netos_vinculado
                                        FROM public.cliente c
                                        JOIN public.entidad e ON e.ent_id = c.ent_id
                                        LEFT JOIN public.clientes_multinivel cm ON cm.cli_id = c.cli_id
                                        LEFT JOIN public.direccion d ON d.dir_id = e.ent_direccion_principal
                                        LEFT JOIN public.telefono t ON t.tel_id = e.ent_telefono_principal
                                        WHERE SUBSTRING(TRIM(c.cli_codigo), 1, 10) = ?
                                            AND c.cli_tipocli = 1
                                        ORDER BY c.cli_id ASC
                                        LIMIT 1", [$identificacionBusqueda]);

            // Escenario 4: cliente no existe
            if (!$resultado) {
                return response()->json(RespuestaApi::returnResultado('success', 'El cliente no existe', null));
            }

            // Escenario 3: cliente existe pero pertenece a otro vendedor
            if ($resultado->netos_vinculado !== null && $resultado->netos_vinculado !== $usuarioNetos) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece al corredor: ' . $resultado->netos_vinculado, null));
            }

            // Escenarios 1 y 2: cliente libre o ya vinculado al mismo vendedor
            $mensaje = $resultado->netos_vinculado === $usuarioNetos
                ? 'El cliente ya existe'
                : 'El cliente existe y está disponible';

            return response()->json(RespuestaApi::returnResultado('success', $mensaje, $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }


    // Version 1.0
    public function updateDynamoCliente(Request $request)
    {
        try {
            $tokenData = $this->validarTokenEnlace($request);
            if ($tokenData instanceof JsonResponse) {
                return $tokenData;
            }
            $usuarioNetos = $tokenData['usuario_netos'];

            $identificacion = mb_strtoupper(trim($request->input('identificacion')));
            $nombres = mb_strtoupper(trim($request->input('nombres')));
            $apellidos = mb_strtoupper(trim($request->input('apellidos')));
            $email = mb_strtolower(trim($request->input('email')));
            $telefono = trim($request->input('telefono'));
            $direccion = mb_strtoupper(trim($request->input('direccion')));
            $direccionSecundaria = mb_strtoupper(trim($request->input('dir_calle_secundaria') ?? ''));

            if (
                empty($identificacion) || empty($nombres) || empty($apellidos)
                || empty($email) || empty($telefono) || empty($direccion) || empty($direccionSecundaria)
            ) {
                return response()->json(RespuestaApi::returnResultado('error', 'Todos los campos son requeridos.', null));
            }

            $identificacionBusqueda = substr($identificacion, 0, 10);

            // ent_telefono_principal y ent_direccion_principal son FKs a tel_id y dir_id
            $resultado = DB::selectOne("SELECT c.cli_id, e.ent_id,
                                            e.ent_telefono_principal  AS tel_id,
                                            e.ent_direccion_principal AS dir_id,
                                            cm.usuario_netos AS netos_vinculado
                                        FROM public.cliente c
                                            JOIN public.entidad e ON e.ent_id = c.ent_id
                                            LEFT JOIN public.clientes_multinivel cm ON cm.cli_id = c.cli_id
                                        WHERE SUBSTRING(TRIM(c.cli_codigo), 1, 10) = ?
                                            AND c.cli_tipocli = 1
                                        ORDER BY c.cli_id ASC
                                        LIMIT 1", [$identificacionBusqueda]);

            if (!$resultado) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontró el cliente para actualizar.', null));
            }

            if ($resultado->netos_vinculado !== null && $resultado->netos_vinculado !== $usuarioNetos) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece a otro vendedor.', null));
            }

            DB::beginTransaction();

            // Actualizar datos de la entidad (solo campos propios, no los FKs)
            Entidad::where('ent_id', $resultado->ent_id)->update([
                'ent_nombres' => trim($nombres),
                'ent_apellidos' => trim($apellidos),
                'ent_email' => trim($email),
            ]);

            // Actualizar nombre comercial en la tabla cliente
            Cliente::where('cli_id', $resultado->cli_id)->update([
                'ent_nombre_comercial' => trim($apellidos) . ' ' . trim($nombres),
            ]);

            // Actualizar el registro de teléfono usando el FK tel_id
            if ($resultado->tel_id) {
                DB::update("UPDATE public.telefono SET tel_numero = ? WHERE tel_id = ?", [trim($telefono), $resultado->tel_id]);
            }

            // Actualizar el registro de dirección usando el FK dir_id
            if ($resultado->dir_id) {
                DB::update(
                    "UPDATE public.direccion SET dir_calle_principal = ?, dir_calle_secundaria = ? WHERE dir_id = ?",
                    [trim($direccion), trim($direccionSecundaria) ?: '.', $resultado->dir_id]
                );
            }

            // Escenario 1: cliente libre → vincularlo al vendedor
            if ($resultado->netos_vinculado === null) {
                ClientesMultinivel::create([
                    'cli_id' => $resultado->cli_id,
                    'usuario_netos' => $usuarioNetos,
                ]);
            }

            DB::commit();

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente actualizado con éxito', null));
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar el cliente', $e->getMessage()));
        }
    }





    // Version 1.0
    public function addDynamoCliente(Request $request)
    {
        try {
            // 0. Descifrar y validar el token de la URL (caducidad + integridad).
            // El usuario_netos sale de aquí, NO del formulario.
            $credenciales = $this->validarTokenEnlace($request);
            if ($credenciales instanceof JsonResponse) {
                return $credenciales;
            }

            $usuarioNetos = trim($credenciales['usuario_netos']);

            $identificacion = trim($request->input('identificacion'));
            $tipoidentificacion = trim($request->input('tipoidentificacion'));

            // 1. Validamos que exista el tipo y la identificación
            if (empty($tipoidentificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar el tipo de identificación.', null));
            }

            if (empty($identificacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Debe ingresar una identificación.', null));
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

            // 2. Validamos todos los campos del formulario
            $validator = Validator::make($request->all(), [
                'tipoidentificacion' => 'required|string',
                'identificacion' => 'required|string',
                'nombres' => 'required|string',
                'apellidos' => 'required|string',
                'email' => 'required|string',
                'telefono' => 'required|string',
                'direccion' => 'required|string',
                'dir_calle_secundaria' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', 'Validación datos', $validator->errors()));
            }

            // 3. Cortamos a 10 caracteres
            $identificacionBusqueda = substr($identificacion, 0, 10);

            // 4. Verificamos si ya existe como cliente
            $clienteOpenceo = DB::selectOne("SELECT * FROM public.cliente c
                                                WHERE SUBSTRING(TRIM(c.cli_codigo), 1, 10) = ?
                                                AND c.cli_tipocli = 1", [$identificacionBusqueda]);

            if ($clienteOpenceo) {
                return response()->json(RespuestaApi::returnResultado('error', 'El cliente ya existe', null));
            }

            // 5. Buscar si existe la entidad
            $entidad = DB::selectOne("SELECT * FROM public.entidad e
                                        WHERE SUBSTRING(TRIM(e.ent_identificacion), 1, 10) = ?", [$identificacionBusqueda]);

            // 6. Crear cliente básico
            DB::transaction(function () use ($request, $entidad, $usuarioNetos) {
                $direccion = mb_strtoupper(trim($request->input('direccion')));
                $direccionSecundaria = mb_strtoupper(trim($request->input('dir_calle_secundaria')));
                $telefono = trim($request->input('telefono'));
                $identificacion = trim($request->input('identificacion'));
                $tipoIdentificacion = trim($request->input('tipoidentificacion'));
                $nombres = mb_strtoupper(trim($request->input('nombres')));
                $apellidos = mb_strtoupper(trim($request->input('apellidos')));
                $email = mb_strtolower(trim($request->input('email')));
                $empId = 1;
                $identificacionConyugue = mb_strtoupper(trim($request->input('identificacionConyugue') ?? ''));
                $nombreConyugue = mb_strtoupper(trim($request->input('nombreConyugue') ?? ''));
                $apellidoConyugue = mb_strtoupper(trim($request->input('apellidoConyugue') ?? ''));

                // 6.1. Crear nueva dirección (siempre se crea)
                $newDireccion = new Direccion();
                $newDireccion->dir_calle_principal = $direccion;
                $newDireccion->dir_calle_secundaria = $direccionSecundaria;
                $newDireccion->save();

                // 6.2. Crear nuevo teléfono (siempre se crea)
                $newTelefono = new Telefono();
                $newTelefono->tte_id = 2; // 2 es celular
                $newTelefono->tel_numero = $telefono;
                $newTelefono->save();

                $entId = null;

                if ($entidad) {
                    // 6.3.1. ENTIDAD EXISTE -> actualizamos con la nueva dirección/teléfono
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
                    // 6.3.2. ENTIDAD NO EXISTE -> creamos la nueva entidad
                    $valor1 = DB::selectOne("SELECT to_number(par_texto,'999999') AS tit_id
                                                FROM parametro
                                                WHERE par_abreviacion='TIT'
                                                    AND mod_abreviatura='CLI'
                                                LIMIT 1");

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

                // 6.4. Obtener datos por defecto para el nuevo cliente
                // Ubicación
                $valor2 = DB::selectOne("SELECT to_number(par_texto,'999999') AS tit_id
                                            FROM parametro
                                            WHERE par_abreviacion='UBI'
                                                AND mod_abreviatura='CLI'
                                            LIMIT 1");

                // Zona
                $valor3 = DB::selectOne("SELECT zon_id
                                            FROM zona
                                            WHERE zon_codigo
                                                IN (SELECT par_texto
                                                        FROM parametro
                                                        WHERE par_abreviacion='ZON'
                                                            AND mod_abreviatura='CLI'
                                                        LIMIT 1)
                                            LIMIT 1");

                // Categoría
                $valor4 = DB::selectOne("SELECT cat_id
                                            FROM catcliente
                                            WHERE cat_abreviacion = 'clien'");

                // Política
                $valor5 = DB::selectOne("SELECT pol_id
                                            FROM politica
                                            WHERE pol_nombre = 'CONTADO'
                                                AND pol_tipocli = 1");

                // Lista de precios
                $valor6 = DB::selectOne("SELECT lpr_id
                                            FROM listapre
                                            WHERE lpr_nombre
                                                IN (SELECT par_texto
                                                        FROM parametro
                                                        WHERE par_abreviacion='LPR'
                                                            AND mod_abreviatura='CLI'
                                                        LIMIT 1)
                                            LIMIT 1");

                // Canal
                $valor7 = DB::selectOne("SELECT to_number(par_texto,'999999') AS can_id
                                            FROM parametro
                                            WHERE par_abreviacion='CAN'
                                                AND mod_abreviatura='CLI'
                                            LIMIT 1");

                // 6.5. Crear nuevo cliente
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
                $newCliente->ent_nombre_comercial = $apellidos . ' ' . $nombres;
                $newCliente->cli_tiposujeto = 'N';
                $newCliente->cli_sexo = 'M';
                $newCliente->cli_estadocivil = 'S';
                $newCliente->cli_ingresos = 'I';
                $newCliente->cli_activo = true;
                $newCliente->save();

                // 6.6. Asignar tipo de pago por defecto sfp_id=1
                DB::insert("INSERT INTO cliente_tipo_pago(cli_id, sfp_id)
                            VALUES (?, 1)", [$newCliente->cli_id]);

                // 6.7. Registrar datos del cónyuge (opcional)
                if ($identificacionConyugue && $nombreConyugue && $apellidoConyugue) {
                    DB::insert("INSERT INTO cliente_anexo(cliane_identificacion_conyuge, cliane_nombre_conyuge,cli_id)
                                VALUES (?,?,?)", [$identificacionConyugue, $nombreConyugue, $newCliente->cli_id]);
                }

                // 6.8: Registrar el cliente al corredor Netos en Dynamo
                // ($usuarioNetos proviene del token cifrado, no del formulario)
                $clienteMultinivel = new ClientesMultinivel();
                $clienteMultinivel->cli_id = $newCliente->cli_id;
                $clienteMultinivel->usuario_netos = $usuarioNetos;
                $clienteMultinivel->save();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente creado con éxito', null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }




    // Version 1.0
    public function generarLinkFormulario(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'usuario_netos' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', 'Validación datos incorrectos', $validator->errors()));
            }

            $usuarioNetos = trim($request->input('usuario_netos'));

            $parametro = DB::table('crm.parametro')
                ->where('abreviacion', 'URL-FRONTEND')
                ->first();

            if (!$parametro || empty($parametro->valor)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No está configurado el parámetro URL-FRONTEND.', null));
            }

            // valor = "base,/ruta?t=,minutos"
            // [0] URL del frontend, [1] ruta del formulario, [2] minutos de vigencia
            $partes = explode(',', $parametro->valor);

            $ruta = isset($partes[1]) ? trim($partes[1]) : '';
            if ($ruta === '') {
                return response()->json(RespuestaApi::returnResultado('error', 'No está configurada la ruta del formulario en el parámetro URL-FRONTEND.', null));
            }

            $minutos = isset($partes[2]) ? (int) trim($partes[2]) : 0;
            if ($minutos < 1) {
                return response()->json(RespuestaApi::returnResultado('error', 'No está configurado el tiempo de duración en el parámetro URL-FRONTEND.', null));
            }

            if ($parametro->activar == true) {
                $base = rtrim(trim($partes[0]), '/');
            } else {
                $base = 'http://192.168.1.142:4201';
            }

            $expires = now()->addMinutes($minutos)->timestamp;

            // Credenciales cifrado y autenticado: nadie puede leerlo ni manipularlo
            $t = Crypt::encryptString(json_encode([
                'usuario_netos' => $usuarioNetos,
                'expires' => $expires,
            ]));

            $url = $base . $ruta . urlencode($t);

            // Solo se devuelve la URL
            return response()->json(RespuestaApi::returnResultado('success', 'Link generado con éxito', ['url' => $url,]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }




    // Version 1.0
    // Valida únicamente el token del enlace
    public function validarEnlace(Request $request)
    {
        try {
            // Descifrar y validar el token del enlace (caducidad + integridad)
            $credenciales = $this->validarTokenEnlace($request);

            if ($credenciales instanceof JsonResponse) {
                return $credenciales;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Enlace válido', null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }




    // Descifra y valida el token ?t= del enlace (caducidad + integridad).
    // Devuelve el array de las credenciales (['usuario_netos' => ..., 'expires' => ...])
    // Metodo privado que se usa para validar si la url es correcta en el metodo addDynamoCliente
    private function validarTokenEnlace(Request $request)
    {
        $t = (string) $request->input('t');

        if ($t === '') {
            return response()->json(RespuestaApi::returnResultado('error', 'Enlace no válido.', null));
        }

        try {
            $credenciales = json_decode(Crypt::decryptString($t), true);
        } catch (\Throwable $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Enlace no válido.', null));
        }

        if (!is_array($credenciales) || empty($credenciales['usuario_netos']) || empty($credenciales['expires'])) {
            return response()->json(RespuestaApi::returnResultado('error', 'Enlace no válido.', null));
        }

        if ((int) $credenciales['expires'] < now()->timestamp) {
            return response()->json(RespuestaApi::returnResultado('error', 'El enlace ha expirado. Solicite uno nuevo.', null));
        }

        return $credenciales;
    }
}

<?php

namespace App\Http\Controllers\corredores;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\Cliente;
use App\Models\openceo\Direccion;
use App\Models\openceo\Entidad;
use App\Models\openceo\Telefono;
use App\Models\sts\ClientesMultinivel;
use App\Servicios\ValidacionCedulaRucService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

// Formulario de clientes para corredores INTERNOS del CRM (Corredor ALM).
// Mismo flujo de 4 escenarios del formulario público STS pero SIN link cifrado:
// la identidad sale del usuario JWT (etiqueta "usu_alias - APELLIDOS NOMBRES")
// y tipo_corredor va QUEMADO en 1 (1. Corredor ALM; 2. Corredor STS;).
// La creación de clientes nuevos reusa crm.fn_clientes_registrar (módulo cliente
// dynamo) SIN modificarla; el caso "entidad existe sin cliente" se resuelve aquí
// en PHP porque la función lo rechaza con IDENTIFICACION_DUPLICADA a propósito.
class CorredorClienteController extends Controller
{
    private const TIPO_CORREDOR_ALM = 1;
    private const EMP_ID_DEFAULT = 1;
    private const SFP_ID_EFECTIVO = 1;
    private const TTE_ID_CELULAR = 2;
    // Tipo de dirección por defecto del formulario (catálogo crm.fn_tipo_direccion_listar: CASA / TRABAJO)
    private const DIR_TIPO_DEFAULT = 'CASA';

    // Mensaje amigable para cada excepción que puede lanzar crm.fn_clientes_registrar
    // (mismo mapa del ClienteController de openceo; aquí solo aplican las de contado)
    private const MENSAJES_ERROR = [
        'REQUIERE_TIPOS_PAGO' => 'Debe registrar al menos un tipo de pago.',
        'REQUIERE_AGENTE' => 'Debe seleccionar un agente.',
        'REQUIRE_IDENTIFICACION' => 'Debe ingresar la identificación.',
        'REQUIERE_UBICACION' => 'Debe seleccionar una ubicación.',
        'REQUIERE_CATEGORIA' => 'Debe seleccionar una categoría.',
        'REQUIERE_ZONA' => 'Debe seleccionar una zona.',
        'REQUIERE_CANAL' => 'Debe seleccionar un canal.',
        'REQUIERE_LISTAPRE' => 'Debe seleccionar una lista de precios.',
        'IDENTIFICACION_INVALIDA' => 'La identificación ingresada no es válida.',
        'IDENTIFICACION_DUPLICADA' => 'Ya existe un cliente con esa identificación.',
        'CODIGO_DUPLICADO' => 'Ya existe un cliente con ese código.',
        'POLITICA_INVALIDA' => 'La política seleccionada no es válida.',
    ];

    // Version 1.0
    // Consulta la identificación y resuelve los 4 escenarios (igual que el
    // formulario público STS, pero el corredor es el usuario logueado del CRM).
    public function verificarClienteCorredor(Request $request)
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

            $corredor = $this->etiquetaCorredor();
            if ($corredor === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se pudo identificar al usuario.', null));
            }

            // Días de vigencia de la vinculación cliente-corredor (parámetro CLICOR)
            $diasCorredor = $this->obtenerDiasParametroCorredor();
            if ($diasCorredor instanceof JsonResponse) {
                return $diasCorredor;
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

            $identificacionBusqueda = substr($identificacion, 0, 10);

            // LEFT JOIN: corredor_vinculado = null si el cliente existe pero no tiene
            // vinculación VIGENTE (activo = true y fecha_desvinculacion NULL)
            $resultado = DB::selectOne("SELECT c.cli_id, c.cli_codigo,
                                            e.ent_id, e.ent_nombres, e.ent_apellidos, e.ent_email, e.ent_tipo_identificacion,
                                            t.tel_numero,
                                            d.dir_calle_principal, d.dir_calle_secundaria,
                                            cm.corredor AS corredor_vinculado
                                        FROM public.cliente c
                                        JOIN public.entidad e ON e.ent_id = c.ent_id
                                        LEFT JOIN public.clientes_multinivel cm ON cm.cli_id = c.cli_id
                                            AND cm.activo = true
                                            AND cm.fecha_desvinculacion IS NULL
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

            // Si la vinculación vigente ya cumplió los días de CLICOR se desvincula
            // (activo = false) y el cliente se evalúa como libre
            if ($resultado->corredor_vinculado !== null && $this->desvincularCorredorSiExpiro($resultado->cli_id, $diasCorredor)) {
                $resultado->corredor_vinculado = null;
            }

            // Escenario 3: cliente existe pero pertenece a otro corredor
            if ($resultado->corredor_vinculado !== null && $resultado->corredor_vinculado !== $corredor) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece al corredor: ' . $resultado->corredor_vinculado, null));
            }

            // Escenarios 1 y 2: cliente libre o ya vinculado al mismo corredor
            $mensaje = $resultado->corredor_vinculado === $corredor
                ? 'El cliente ya existe'
                : 'El cliente existe y está disponible';

            return response()->json(RespuestaApi::returnResultado('success', $mensaje, $resultado));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al consultar el cliente', $e->getMessage()));
        }
    }

    // Version 1.0
    // Guarda el cliente decidiendo el camino en el servidor (no confía en el front):
    // - cliente existe  -> actualiza datos + vincula si está libre
    // - cliente no existe y entidad tampoco -> crm.fn_clientes_registrar (contado) + vincula
    // - cliente no existe pero entidad sí   -> camino PHP (reusa la entidad) + vincula
    public function guardarClienteCorredor(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipoidentificacion' => 'required',
                'identificacion' => 'required|string',
                'nombres' => 'required|string',
                'apellidos' => 'required|string',
                'email' => 'required|email',
                'telefono' => 'required|digits:10',
                'direccion' => 'required|string',
                'dir_calle_secundaria' => 'required|string',
            ], [
                'email.email' => 'Ingrese un email válido.',
                'telefono.digits' => 'El teléfono debe tener 10 dígitos.',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null));
            }

            $corredor = $this->etiquetaCorredor();
            if ($corredor === null) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se pudo identificar al usuario.', null));
            }

            // Días de vigencia de la vinculación cliente-corredor (parámetro CLICOR)
            $diasCorredor = $this->obtenerDiasParametroCorredor();
            if ($diasCorredor instanceof JsonResponse) {
                return $diasCorredor;
            }

            $tipoidentificacion = trim($request->input('tipoidentificacion'));
            $identificacion = trim($request->input('identificacion'));
            $nombres = mb_strtoupper(trim($request->input('nombres')));
            $apellidos = mb_strtoupper(trim($request->input('apellidos')));
            $email = mb_strtolower(trim($request->input('email')));
            $telefono = trim($request->input('telefono'));
            $direccion = mb_strtoupper(trim($request->input('direccion')));
            $direccionSecundaria = mb_strtoupper(trim($request->input('dir_calle_secundaria')));

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

            $cliente = DB::selectOne("SELECT c.cli_id, e.ent_id,
                                            e.ent_telefono_principal  AS tel_id,
                                            e.ent_direccion_principal AS dir_id,
                                            cm.corredor AS corredor_vinculado
                                        FROM public.cliente c
                                            JOIN public.entidad e ON e.ent_id = c.ent_id
                                            LEFT JOIN public.clientes_multinivel cm ON cm.cli_id = c.cli_id
                                                AND cm.activo = true
                                                AND cm.fecha_desvinculacion IS NULL
                                        WHERE SUBSTRING(TRIM(c.cli_codigo), 1, 10) = ?
                                            AND c.cli_tipocli = 1
                                        ORDER BY c.cli_id ASC
                                        LIMIT 1", [$identificacionBusqueda]);

            if ($cliente) {
                // Escenarios 1 y 2: actualizar datos y vincular si está libre
                return $this->actualizarClienteExistente($cliente, $corredor, $diasCorredor, [
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'email' => $email,
                    'telefono' => $telefono,
                    'direccion' => $direccion,
                    'direccionSecundaria' => $direccionSecundaria,
                ]);
            }

            // Escenario 4: cliente no existe. Si la entidad tampoco existe se usa
            // crm.fn_clientes_registrar; si la entidad existe (sin cliente) la
            // función la rechazaría, así que ese caso va por el camino PHP.
            $entidad = DB::selectOne("SELECT * FROM public.entidad e
                                        WHERE SUBSTRING(TRIM(e.ent_identificacion), 1, 10) = ?", [$identificacionBusqueda]);

            if (!$entidad) {
                return $this->crearClienteViaFuncion($request, $corredor, $diasCorredor, [
                    'identificacion' => $identificacion,
                    'tipoidentificacion' => $tipoidentificacion,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'email' => $email,
                    'telefono' => $telefono,
                    'direccion' => $direccion,
                    'direccionSecundaria' => $direccionSecundaria,
                ]);
            }

            return $this->crearClienteDesdeEntidad($entidad, $corredor, $diasCorredor, [
                'identificacion' => $identificacion,
                'tipoidentificacion' => $tipoidentificacion,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'email' => $email,
                'telefono' => $telefono,
                'direccion' => $direccion,
                'direccionSecundaria' => $direccionSecundaria,
            ]);
        } catch (Exception $e) {
            if ($this->esCarreraVinculacion($e)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece a otro corredor.', null));
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar el cliente', $e->getMessage()));
        }
    }

    // Escenarios 1 y 2: el cliente ya existe en openceo. Actualiza entidad/teléfono/
    // dirección (creándolos si el cliente no tenía FK, para no perder el dato) y
    // crea la vinculación tipo ALM si el cliente está libre.
    private function actualizarClienteExistente($cliente, string $corredor, int $diasCorredor, array $datos)
    {
        if ($cliente->corredor_vinculado !== null && $this->desvincularCorredorSiExpiro($cliente->cli_id, $diasCorredor)) {
            $cliente->corredor_vinculado = null;
        }

        if ($cliente->corredor_vinculado !== null && $cliente->corredor_vinculado !== $corredor) {
            return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece a otro corredor.', null));
        }

        try {
            DB::beginTransaction();

            Entidad::where('ent_id', $cliente->ent_id)->update([
                'ent_nombres' => $datos['nombres'],
                'ent_apellidos' => $datos['apellidos'],
                'ent_email' => $datos['email'],
            ]);

            Cliente::where('cli_id', $cliente->cli_id)->update([
                'ent_nombre_comercial' => $datos['apellidos'] . ' ' . $datos['nombres'],
            ]);

            // Teléfono principal: se actualiza, o se CREA si el cliente no tenía
            // (a diferencia del formulario público, aquí el dato no se pierde)
            if ($cliente->tel_id) {
                DB::update("UPDATE public.telefono SET tel_numero = ? WHERE tel_id = ?", [$datos['telefono'], $cliente->tel_id]);
            } else {
                $newTelefono = new Telefono();
                $newTelefono->tte_id = self::TTE_ID_CELULAR;
                $newTelefono->tel_numero = $datos['telefono'];
                $newTelefono->save();

                DB::update("UPDATE public.entidad SET ent_telefono_principal = ? WHERE ent_id = ?", [$newTelefono->tel_id, $cliente->ent_id]);
            }

            // Dirección principal: misma regla que el teléfono. El tipo solo se
            // rellena con el default si estaba vacío (no pisa un TRABAJO ya asignado)
            if ($cliente->dir_id) {
                DB::update(
                    "UPDATE public.direccion SET dir_calle_principal = ?, dir_calle_secundaria = ?, dir_tipo = COALESCE(dir_tipo, ?) WHERE dir_id = ?",
                    [$datos['direccion'], $datos['direccionSecundaria'], self::DIR_TIPO_DEFAULT, $cliente->dir_id]
                );
            } else {
                $newDireccion = new Direccion();
                $newDireccion->dir_calle_principal = $datos['direccion'];
                $newDireccion->dir_calle_secundaria = $datos['direccionSecundaria'];
                $newDireccion->dir_tipo = self::DIR_TIPO_DEFAULT;
                $newDireccion->save();

                DB::update("UPDATE public.entidad SET ent_direccion_principal = ? WHERE ent_id = ?", [$newDireccion->dir_id, $cliente->ent_id]);
            }

            // Escenario 1: cliente libre -> vincularlo al corredor interno
            if ($cliente->corredor_vinculado === null) {
                $this->vincularCorredor($cliente->cli_id, $corredor, $diasCorredor);
            }

            DB::commit();

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente actualizado con éxito', null));
        } catch (Exception $e) {
            DB::rollBack();

            if ($this->esCarreraVinculacion($e)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece a otro corredor.', null));
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar el cliente', $e->getMessage()));
        }
    }

    // Escenario 4a: ni cliente ni entidad existen. Camino PREFERIDO: se arma el
    // payload mínimo de CONTADO y se llama a crm.fn_clientes_registrar tal cual
    // (la función aplica los defaults de categoría/ubicación/zona/canal/lista/
    // demográficos y escribe la auditoría forense). Luego se vincula al corredor.
    private function crearClienteViaFuncion(Request $request, string $corredor, int $diasCorredor, array $datos)
    {
        $politica = DB::selectOne("SELECT pol_id FROM politica WHERE pol_nombre = 'CONTADO' AND pol_tipocli = 1");
        if (!$politica) {
            return response()->json(RespuestaApi::returnResultado('error', 'No está configurada la política CONTADO, comuniquese con el administrador.', null));
        }

        // Título por defecto (parámetro TIT/CLI); la función NO lo defaultea
        $titulo = DB::selectOne("SELECT to_number(par_texto,'999999') AS tit_id
                                    FROM parametro
                                    WHERE par_abreviacion='TIT'
                                        AND mod_abreviatura='CLI'
                                    LIMIT 1");

        $payload = [
            'ent_identificacion' => $datos['identificacion'],
            'ent_tipo_identificacion' => (int) $datos['tipoidentificacion'],
            'ent_nombres' => $datos['nombres'],
            'ent_apellidos' => $datos['apellidos'],
            'ent_email' => $datos['email'],
            'ent_nombre_comercial' => $datos['apellidos'] . ' ' . $datos['nombres'],
            'tit_id' => $titulo->tit_id ?? null,
            'pol_id' => $politica->pol_id,
            'emp_id' => self::EMP_ID_DEFAULT,
            'cli_credito' => false,
            'tipos_pago' => [['sfp_id' => self::SFP_ID_EFECTIVO, 'ctip_default' => true]],
            'direccion' => [
                'dir_calle_principal' => $datos['direccion'],
                'dir_calle_secundaria' => $datos['direccionSecundaria'],
                'dir_principal' => true,
                'dir_tipo' => self::DIR_TIPO_DEFAULT,
            ],
            'telefono' => [
                'tte_id' => self::TTE_ID_CELULAR,
                'tel_numero' => $datos['telefono'],
                'tel_principal' => true,
            ],
            // Etiqueta para cliente.created_by + contexto de auditoría forense
            'usuario_auditoria' => $corredor,
            'auditoria' => $this->contextoAuditoriaForense($request),
        ];

        try {
            // La función corre dentro de la transacción externa: si la vinculación
            // falla (carrera), el cliente creado también se revierte.
            DB::transaction(function () use ($payload, $corredor, $diasCorredor) {
                $resultado = DB::selectOne('SELECT crm.fn_clientes_registrar(?::jsonb) AS cli_id', [json_encode($payload)]);

                $this->vincularCorredor($resultado->cli_id, $corredor, $diasCorredor);
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente creado con éxito', null));
        } catch (QueryException $e) {
            if ($this->esCarreraVinculacion($e)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece a otro corredor.', null));
            }

            foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
                if (strpos($e->getMessage(), $codigo) !== false) {
                    return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
                }
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear el cliente', $e->getMessage()));
        }
    }

    // Escenario 4b: la entidad existe (garante, proveedor, etc.) pero sin cliente.
    // crm.fn_clientes_registrar rechaza este caso (IDENTIFICACION_DUPLICADA), así
    // que se resuelve en PHP reusando la entidad, igual que el formulario público
    // STS: dirección/teléfono nuevos + update de entidad + cliente con defaults.
    private function crearClienteDesdeEntidad($entidad, string $corredor, int $diasCorredor, array $datos)
    {
        try {
            DB::transaction(function () use ($entidad, $corredor, $diasCorredor, $datos) {
                $newDireccion = new Direccion();
                $newDireccion->dir_calle_principal = $datos['direccion'];
                $newDireccion->dir_calle_secundaria = $datos['direccionSecundaria'];
                $newDireccion->dir_tipo = self::DIR_TIPO_DEFAULT;
                $newDireccion->save();

                $newTelefono = new Telefono();
                $newTelefono->tte_id = self::TTE_ID_CELULAR;
                $newTelefono->tel_numero = $datos['telefono'];
                $newTelefono->save();

                DB::update(
                    "UPDATE public.entidad SET
                                    ent_nombres = ?,
                                    ent_apellidos = ?,
                                    ent_tipo_identificacion = ?,
                                    ent_email = ?,
                                    ent_direccion_principal = ?,
                                    ent_telefono_principal = ?
                                WHERE ent_id = ?",
                    [$datos['nombres'], $datos['apellidos'], $datos['tipoidentificacion'], $datos['email'], $newDireccion->dir_id, $newTelefono->tel_id, $entidad->ent_id]
                );

                // Defaults del cliente (mismos parámetros que usa el formulario público)
                $valorUbi = DB::selectOne("SELECT to_number(par_texto,'999999') AS tit_id
                                            FROM parametro
                                            WHERE par_abreviacion='UBI'
                                                AND mod_abreviatura='CLI'
                                            LIMIT 1");

                $valorZon = DB::selectOne("SELECT zon_id
                                            FROM zona
                                            WHERE zon_codigo
                                                IN (SELECT par_texto
                                                        FROM parametro
                                                        WHERE par_abreviacion='ZON'
                                                            AND mod_abreviatura='CLI'
                                                        LIMIT 1)
                                            LIMIT 1");

                $valorCat = DB::selectOne("SELECT cat_id
                                            FROM catcliente
                                            WHERE cat_abreviacion = 'clien'");

                $valorPol = DB::selectOne("SELECT pol_id
                                            FROM politica
                                            WHERE pol_nombre = 'CONTADO'
                                                AND pol_tipocli = 1");

                $valorLpr = DB::selectOne("SELECT lpr_id
                                            FROM listapre
                                            WHERE lpr_nombre
                                                IN (SELECT par_texto
                                                        FROM parametro
                                                        WHERE par_abreviacion='LPR'
                                                            AND mod_abreviatura='CLI'
                                                        LIMIT 1)
                                            LIMIT 1");

                $valorCan = DB::selectOne("SELECT to_number(par_texto,'999999') AS can_id
                                            FROM parametro
                                            WHERE par_abreviacion='CAN'
                                                AND mod_abreviatura='CLI'
                                            LIMIT 1");

                $newCliente = new Cliente();
                $newCliente->cli_codigo = $datos['identificacion'];
                $newCliente->ent_id = $entidad->ent_id;
                $newCliente->ubi_id = $valorUbi->tit_id;
                $newCliente->zon_id = $valorZon->zon_id;
                $newCliente->cat_id = $valorCat->cat_id;
                $newCliente->pol_id = $valorPol->pol_id;
                $newCliente->lpr_id = $valorLpr->lpr_id;
                $newCliente->cli_tipocli = 1;
                $newCliente->emp_id = self::EMP_ID_DEFAULT;
                $newCliente->can_id = $valorCan->can_id;
                $newCliente->ent_nombre_comercial = $datos['apellidos'] . ' ' . $datos['nombres'];
                $newCliente->cli_tiposujeto = 'N';
                $newCliente->cli_sexo = 'M';
                $newCliente->cli_estadocivil = 'S';
                $newCliente->cli_ingresos = 'I';
                $newCliente->cli_activo = true;
                $newCliente->save();

                DB::insert("INSERT INTO cliente_tipo_pago(cli_id, sfp_id)
                            VALUES (?, ?)", [$newCliente->cli_id, self::SFP_ID_EFECTIVO]);

                $this->vincularCorredor($newCliente->cli_id, $corredor, $diasCorredor);
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente creado con éxito', null));
        } catch (Exception $e) {
            if ($this->esCarreraVinculacion($e)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Este cliente ya pertenece a otro corredor.', null));
            }

            return response()->json(RespuestaApi::returnResultado('error', 'Error al crear el cliente', $e->getMessage()));
        }
    }

    // Crea la vinculación vigente cliente-corredor con tipo ALM quemado.
    private function vincularCorredor(int $cliId, string $corredor, int $diasCorredor): void
    {
        $vinculo = new ClientesMultinivel();
        $vinculo->cli_id = $cliId;
        $vinculo->corredor = $corredor;
        $vinculo->tipo_corredor = self::TIPO_CORREDOR_ALM;
        $vinculo->dias_parametro = $diasCorredor;
        $vinculo->activo = true;
        $vinculo->save();
    }

    // Etiqueta del corredor interno: "usu_alias - APELLIDOS NOMBRES" del usuario
    // JWT (mismo formato que cliente.created_by). Se trunca a 100 caracteres.
    private function etiquetaCorredor(): ?string
    {
        $u = auth('api')->user();
        if (!$u) {
            return null;
        }

        $etiqueta = trim(trim($u->usu_alias) . ' - ' . trim($u->surname) . ' ' . trim($u->name));

        return mb_substr($etiqueta, 0, 100);
    }

    // Contexto del usuario para la auditoría forense de crm.fn_clientes_registrar
    // (mismo bloque que arma el ClienteController de openceo).
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

    // Lee el parámetro CLICOR (días de vigencia de la vinculación cliente-corredor).
    // Acepta 0 o más días; error si no existe, está vacío o es negativo.
    private function obtenerDiasParametroCorredor()
    {
        $parametro = DB::table('crm.parametro')
            ->where('abreviacion', 'CLICOR')
            ->first();

        $valor = $parametro ? trim((string) $parametro->valor) : '';

        if ($valor === '' || (int) $valor < 0) {
            return response()->json(RespuestaApi::returnResultado('error', 'No está configurado el parámetro, comuniquese con el administrador.', null));
        }

        return (int) $valor;
    }

    // Detecta la violación del índice único parcial uq_clientes_multinivel_vigente
    // (SQLSTATE 23505): otro corredor vinculó al mismo cliente en paralelo.
    private function esCarreraVinculacion(Exception $e): bool
    {
        return $e instanceof QueryException
            && ($e->errorInfo[0] ?? null) === '23505'
            && strpos($e->getMessage(), 'uq_clientes_multinivel_vigente') !== false;
    }

    // Si la vinculación vigente del cliente ya cumplió los días de CLICOR la
    // desactiva (activo = false + fecha_desvinculacion = NOW()). Devuelve true
    // si se desvinculó (el cliente queda libre para un nuevo corredor).
    private function desvincularCorredorSiExpiro($cliId, int $dias): bool
    {
        $filas = DB::update("UPDATE public.clientes_multinivel
                                SET activo = false,
                                    fecha_desvinculacion = NOW(),
                                    updated_at = NOW()
                            WHERE cli_id = ?
                                AND activo = true
                                AND fecha_desvinculacion IS NULL
                                AND created_at <= ?", [$cliId, now()->subDays($dias)]);

        return $filas > 0;
    }
}

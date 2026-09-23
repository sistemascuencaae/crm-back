<?php

namespace App\Servicios\Consultas;

use App\Models\openceo\Direccion;
use App\Models\openceo\Telefono;
use App\Models\sts\ClientesMultinivel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

// Helpers comunes a TODOS los proveedores de consulta de clientes (GaranCheck hoy, otros mañana).
// Nada de aquí modifica las funciones de cliente: solo arma el payload que ya reciben
// crm.fn_clientes_registrar / crm.fn_clientes_modificar (copiado de CorredorClienteController)
// y reusa el mecanismo de vinculación cliente-corredor de DynamoClienteController.
class ConsultasService
{
    // Particulas que forman parte de un apellido compuesto (ej. "De La Rosa", "Del Pozo").
    const PARTICULAS_APELLIDO = ['de', 'del', 'la', 'las', 'los', 'san', 'santa', 'da', 'di', 'do', 'y', 'e'];

    // 1. Corredor ALM; 2. Corredor STS (misma columna clientes_multinivel.tipo_corredor que llena Dynamo)
    const TIPO_CORREDOR_STS = 2;

    // Fuente que originó el alta. Van a public.cliente.proveedor y a
    // clientes_multinivel_consultas.proveedor, con los mismos textos de crm.consulta_identidad.
    const PROVEEDOR_GARANCHECK = 'GARANCHECK';
    const PROVEEDOR_ECUADOR_LEGAL = 'ECUADOR LEGAL';
    const PROVEEDOR_SRI = 'SRI';

    // Dirección de los clientes creados por una fuente de identidad, que no devuelve domicilio.
    const DIRECCION_MULTINIVEL = 'SN - MULTINIVEL';

    private const EMP_ID_DEFAULT = 1;
    private const SFP_ID_EFECTIVO = 1;
    private const TTE_ID_CELULAR = 2;
    private const DIR_TIPO_DEFAULT = 'CASA';

    // =====================================================================================
    // NOMBRES
    // =====================================================================================

    // Separar nombre completo "APELLIDOS NOMBRES" en apellidos y nombres
    public static function separarNombreCompleto($nombreCompleto): array
    {
        // Normalizamos espacios multiples y bordes.
        $nombreCompleto = trim(preg_replace('/\s+/', ' ', (string) $nombreCompleto));

        if ($nombreCompleto === '') {
            return ['apellidos' => null, 'nombres' => null];
        }

        $partes = explode(' ', $nombreCompleto);

        // Con 2 palabras o menos es ambiguo: la primera es apellido, el resto nombre.
        if (count($partes) <= 2) {
            $apellidos = array_shift($partes);
            $nombres = implode(' ', $partes);

            return [
                'apellidos' => $apellidos !== '' ? mb_strtoupper($apellidos, 'UTF-8') : null,
                'nombres' => $nombres !== '' ? mb_strtoupper($nombres, 'UTF-8') : null,
            ];
        }

        // Recorremos armando 2 "unidades de apellido"; cada unidad absorbe las
        // particulas que la anteceden y una palabra nucleo.
        $i = 0;
        $total = count($partes);
        $apellidosCompletados = 0;

        while ($i < $total && $apellidosCompletados < 2) {
            // Absorbemos particulas consecutivas.
            while ($i < $total && in_array(mb_strtolower($partes[$i]), self::PARTICULAS_APELLIDO, true)) {
                $i++;
            }
            // Absorbemos la palabra nucleo del apellido.
            if ($i < $total) {
                $i++;
            }
            $apellidosCompletados++;
        }

        $apellidos = implode(' ', array_slice($partes, 0, $i));
        $nombres = implode(' ', array_slice($partes, $i));

        return [
            'apellidos' => $apellidos !== '' ? mb_strtoupper($apellidos, 'UTF-8') : null,
            'nombres' => $nombres !== '' ? mb_strtoupper($nombres, 'UTF-8') : null,
        ];
    }

    // Separar razon social en apellidos y nombres
    public static function partirRazonSocialEmpresa($nombreCompleto): array
    {
        // Normalizamos espacios multiples y bordes.
        $nombreCompleto = trim(preg_replace('/\s+/', ' ', (string) $nombreCompleto));

        if ($nombreCompleto === '') {
            return ['apellidos' => null, 'nombres' => null];
        }

        $partes = explode(' ', $nombreCompleto);

        // Una sola palabra: va toda en apellidos.
        if (count($partes) === 1) {
            return ['apellidos' => mb_strtoupper($partes[0], 'UTF-8'), 'nombres' => null];
        }

        // Punto de corte: primera mitad (redondeada hacia arriba) -> apellidos, resto -> nombres.
        $corte = (int) ceil(count($partes) / 2);
        $apellidos = implode(' ', array_slice($partes, 0, $corte));
        $nombres = implode(' ', array_slice($partes, $corte));

        return [
            'apellidos' => $apellidos !== '' ? mb_strtoupper($apellidos, 'UTF-8') : null,
            'nombres' => $nombres !== '' ? mb_strtoupper($nombres, 'UTF-8') : null,
        ];
    }

    // =====================================================================================
    // CLIENTE (alta contado mínima / actualización de nombres)
    // $datos = lo que devuelve XService::extraerDatosCliente() + identificacion + tipo_identificacion
    // $contexto = lo que devuelve contextoAuditoria()
    // =====================================================================================

    // Alta a CONTADO con el mismo payload mínimo del corredor. Si la entidad ya existía
    // (proveedor, garante) fn_clientes_registrar la reusa; por eso se arrastran tit_id y
    // fecha de nacimiento de la foto. Devuelve el cli_id.
    public static function registrarCliente(array $datos, ?array $foto, array $contexto): int
    {
        $defaults = self::defaultsCanal();

        // Geo: GaranCheck trae domicilio, así que lo que no casa cae a la provincia por defecto.
        // Las fuentes de identidad (Ecuador Legal) no traen dirección: ahí la geo queda en NULL
        // antes que inventar un cantón que después ensucia zonas y reportes.
        $geoDefaults = ($datos['geo_por_defecto'] ?? true) ? $defaults : [];
        $geo = self::geoPorNombre($datos['provincia'] ?? null, $datos['canton'] ?? null, $datos['parroquia'] ?? null, $geoDefaults);

        $payload = [
            'ent_identificacion' => $datos['identificacion'],
            'ent_tipo_identificacion' => (int) $datos['tipo_identificacion'],
            'ent_nombres' => $datos['nombres'],
            'ent_apellidos' => $datos['apellidos'],
            'ent_email' => $datos['email'],
            'ent_nombre_comercial' => $datos['nombre_comercial'],
            'pol_id' => $defaults['pol_id'],
            'emp_id' => self::EMP_ID_DEFAULT,
            'cli_credito' => false,
            'tipos_pago' => [['sfp_id' => self::SFP_ID_EFECTIVO, 'ctip_default' => true]],
            'direccion' => [
                'dir_calle_principal' => $datos['direccion'],
                'dir_calle_secundaria' => null,
                'dir_principal' => true,
                'dir_activo' => true,
                'dir_tipo' => self::DIR_TIPO_DEFAULT,
                'dir_prv_id' => $geo['prv_id'],
                'dir_ctn_id' => $geo['ctn_id'],
                'dir_prq_id' => $geo['prq_id'],
            ],
            'telefono' => [
                'tte_id' => self::TTE_ID_CELULAR,
                'tel_numero' => $datos['telefono'],
                'tel_principal' => true,
                'tel_activo' => true,
            ],
            'tit_id' => $foto['tit_id'] ?? $defaults['tit_id'],
            'ent_fechanacimiento' => $foto['ent_fechanacimiento'] ?? null,
            'dinardap' => ['cli_tiposujeto' => $datos['tipo_sujeto'] ?: 'N'],
            'proveedor' => $datos['proveedor'] ?? null,
            'usuario_auditoria' => $contexto['usuario_auditoria'],
            'auditoria' => $contexto['auditoria'],
        ];

        $fila = DB::selectOne('SELECT crm.fn_clientes_registrar(?::jsonb) AS cli_id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

        return (int) $fila->cli_id;
    }

    // "PICHINCHA / QUITO / BELISARIO QUEVEDO / RUIZ DE LA CASTILLA N30-13 Y ANDAGOYA"
    // → [provincia, cantón, parroquia, calle]. Con menos de 4 tramos no hay geo: todo es calle.
    // El separador es " / " con espacios: una calle "S/N" no se parte.
    public static function partirDireccionSri(string $direccion): array
    {
        $tramos = array_values(array_filter(array_map('trim', preg_split('#\s+/\s+#', $direccion)), 'strlen'));

        if (count($tramos) < 4) {
            return [null, null, null, trim($direccion)];
        }

        return [$tramos[0], $tramos[1], $tramos[2], implode(' / ', array_slice($tramos, 3))];
    }

    // PLAN B cuando GaranCheck no responde: traduce lo que devuelven Ecuador Legal (cédula) y el
    // SRI (RUC) al mismo arreglo que extraerDatosCliente, para que el alta use un solo camino.
    //
    // Esas fuentes NO dan correo ni teléfono: el cliente nace sin ellos y el corredor los completa
    // al Guardar. Tampoco dan domicilio —el endpoint obtenerPorNumerosRuc del SRI no trae dirección;
    // la que usa GaranCheck sale de otra fuente que él agrega—, así que la calle queda en
    // DIRECCION_MULTINIVEL y la geo en NULL. Si algún día llega una dirección, se usa.
    public static function datosDesdeIdentidad(array $respuesta, string $identificacion, int $tipoIdentificacion, string $proveedor): array
    {
        $tipoSujeto = ($respuesta['tipo_sujeto'] ?? 'N') === 'J' ? 'J' : 'N';
        $apellidos = trim((string) ($respuesta['apellidos'] ?? ''));
        $nombres = trim((string) ($respuesta['nombres'] ?? ''));

        if ($tipoSujeto === 'J') {
            // Mismo estándar del ERP que usa GaranCheck: razón social COMPLETA en apellidos y un
            // punto en nombres. El SRI la parte mitad/mitad y aquí se corrige.
            $razonSocial = trim(preg_replace('/\s+/', ' ', (string) ($respuesta['razonSocial'] ?? trim("{$apellidos} {$nombres}"))));
            $apellidos = $razonSocial !== '' ? mb_strtoupper($razonSocial, 'UTF-8') : null;
            $nombres = $razonSocial !== '' ? '.' : null;
            $nombreComercial = $apellidos;
        } else {
            $apellidos = $apellidos !== '' ? mb_strtoupper($apellidos, 'UTF-8') : null;
            $nombres = $nombres !== '' ? mb_strtoupper($nombres, 'UTF-8') : null;
            $nombreComercial = trim("{$apellidos} {$nombres}") ?: null;
        }

        if ($apellidos === null && $nombres === null) {
            throw new RuntimeException('La fuente de identidad no devolvió el nombre; no se pudo registrar al cliente.');
        }

        // Domicilio: solo el SRI lo manda, en "PROVINCIA / CANTON / PARROQUIA / CALLE".
        [$provincia, $canton, $parroquia, $calle] = self::partirDireccionSri((string) ($respuesta['direccion'] ?? ''));

        return [
            'tipo_sujeto' => $tipoSujeto,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'nombre_comercial' => $nombreComercial,
            'email' => null,
            'telefono' => null,
            'direccion' => $calle !== '' ? mb_strtoupper($calle, 'UTF-8') : self::DIRECCION_MULTINIVEL,
            'provincia' => $provincia,
            'canton' => $canton,
            'parroquia' => $parroquia,
            'identificacion' => $identificacion,
            'tipo_identificacion' => $tipoIdentificacion,
            'proveedor' => $proveedor,
            // Sin domicilio del proveedor no se inventa provincia: la geo queda en NULL.
            'geo_por_defecto' => false,
        ];
    }

    // Cliente existente: ficha COMPLETA de la foto (fn_clientes_modificar reescribe todo) pisando
    // SOLO nombres, apellidos y nombre comercial. Lanza si la función rechaza (p.ej. CRÉDITO con
    // ficha incompleta); el que llama decide si eso es fatal.
    public static function actualizarNombresCliente(array $foto, array $datos, array $contexto): int
    {
        $payload = self::asegurarPrincipales($foto, $datos);

        $payload['ent_nombres'] = $datos['nombres'];
        $payload['ent_apellidos'] = $datos['apellidos'];
        $payload['ent_nombre_comercial'] = $datos['nombre_comercial'];
        $payload['usuario_auditoria'] = $contexto['usuario_auditoria'];
        $payload['auditoria'] = $contexto['auditoria'];

        $fila = DB::selectOne('SELECT crm.fn_clientes_modificar(?::jsonb) AS cli_id', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

        return (int) $fila->cli_id;
    }

    // usuario_auditoria alimenta cliente.created_by/updated_by; el bloque auditoria va a la
    // auditoría forense. CRM: "usu_alias - APELLIDOS NOMBRES"; STS: "CORREDOR - <corredor>".
    // $usuId: canal SIN sesión JWT (el formulario público del corredor, que se autentica con el
    // token cifrado del enlace). Sin él la auditoría de esas altas quedaría con usuario en NULL.
    public static function contextoAuditoria(Request $request, ?string $corredor, ?int $usuId = null): array
    {
        $u = auth('api')->user();
        $nombreUsuario = $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : '';

        $usuarioAuditoria = $corredor !== null
            ? 'CORREDOR - ' . $corredor
            : trim(trim($u->usu_alias ?? '') . ' - ' . $nombreUsuario);

        // Sin usuario JWT el autor es el corredor del token, igual que en DynamoClienteController.
        $login = $u->usu_alias ?? ($corredor !== null ? mb_substr($corredor, 0, 100) : null);
        $nombre = $nombreUsuario !== '' ? $nombreUsuario : ($u ? null : 'MULTINIVEL');

        return [
            'usuario_auditoria' => mb_substr($usuarioAuditoria, 0, 100),
            'auditoria' => [
                'usuario_id' => $u->id ?? $usuId,
                'usuario_login' => $login,
                'usuario_nombre' => $nombre,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => (string) Str::uuid(),
            ],
        ];
    }

    // Valores propios del canal SOLO para el alta (misma regla que el corredor y el modal del CRM):
    // política CONTADO, título TIT/CLI, provincia AZUAY + primer cantón ACTIVO + primera parroquia ACTIVA.
    public static function defaultsCanal(): array
    {
        $politica = DB::selectOne("SELECT pol_id FROM politica WHERE pol_nombre = 'CONTADO' AND pol_tipocli = 1");
        if (!$politica) {
            throw new RuntimeException('No está configurada la política CONTADO, comuniquese con el administrador.');
        }

        $titulo = DB::selectOne("SELECT to_number(par_texto,'999999') AS tit_id
                                    FROM parametro
                                    WHERE par_abreviacion='TIT'
                                        AND mod_abreviatura='CLI'
                                    LIMIT 1");

        $provinciaDefecto = "(SELECT prv_id FROM provincia WHERE UPPER(TRIM(prv_nombre)) = 'AZUAY' AND prv_activo = true LIMIT 1)";
        $cantonDefecto = "(SELECT ctn_id FROM canton WHERE prv_id = {$provinciaDefecto} AND ctn_activo = true ORDER BY ctn_nombre LIMIT 1)";

        $provincia = DB::selectOne("SELECT {$provinciaDefecto} AS prv_id");
        $canton = DB::selectOne("SELECT {$cantonDefecto} AS ctn_id");
        $parroquia = DB::selectOne("SELECT prq_id FROM parroquia
                                        WHERE ctn_id = {$cantonDefecto} AND prq_activo = true
                                        ORDER BY prq_nombre LIMIT 1");

        return [
            'pol_id' => $politica->pol_id,
            'tit_id' => $titulo->tit_id ?? null,
            'prv_id' => $provincia->prv_id ?? null,
            'ctn_id' => $canton->ctn_id ?? null,
            'prq_id' => $parroquia->prq_id ?? null,
        ];
    }

    // Casa provincia/cantón/parroquia por NOMBRE (sin tildes, mayúsculas) contra los catálogos
    // activos. Si provincia o cantón no casan se devuelve el default completo (nunca se mezcla una
    // provincia real con el cantón de AZUAY); si solo falla la parroquia, la primera activa del cantón.
    public static function geoPorNombre(?string $provincia, ?string $canton, ?string $parroquia, array $defaults): array
    {
        $geo = ['prv_id' => $defaults['prv_id'] ?? null, 'ctn_id' => $defaults['ctn_id'] ?? null, 'prq_id' => $defaults['prq_id'] ?? null];

        $provincia = self::normalizarNombre($provincia);
        $canton = self::normalizarNombre($canton);
        $parroquia = self::normalizarNombre($parroquia);

        if ($provincia === '' || $canton === '') {
            return $geo;
        }

        $prv = DB::selectOne("SELECT prv_id FROM provincia
                                WHERE translate(UPPER(TRIM(prv_nombre)), 'ÁÉÍÓÚÜ', 'AEIOUU') = ? AND prv_activo = true
                                LIMIT 1", [$provincia]);
        if (!$prv) {
            return $geo;
        }

        $ctn = DB::selectOne("SELECT ctn_id FROM canton
                                WHERE prv_id = ? AND translate(UPPER(TRIM(ctn_nombre)), 'ÁÉÍÓÚÜ', 'AEIOUU') = ? AND ctn_activo = true
                                LIMIT 1", [$prv->prv_id, $canton]);
        if (!$ctn) {
            return $geo;
        }

        $prq = $parroquia !== ''
            ? DB::selectOne("SELECT prq_id FROM parroquia
                                WHERE ctn_id = ? AND translate(UPPER(TRIM(prq_nombre)), 'ÁÉÍÓÚÜ', 'AEIOUU') = ? AND prq_activo = true
                                LIMIT 1", [$ctn->ctn_id, $parroquia])
            : null;
        if (!$prq) {
            $prq = DB::selectOne("SELECT prq_id FROM parroquia
                                    WHERE ctn_id = ? AND prq_activo = true
                                    ORDER BY prq_nombre LIMIT 1", [$ctn->ctn_id]);
        }

        return ['prv_id' => $prv->prv_id, 'ctn_id' => $ctn->ctn_id, 'prq_id' => $prq->prq_id ?? null];
    }

    // =====================================================================================
    // CORREDOR (copia de DynamoClienteController: parámetro CLICOR + clientes_multinivel)
    // =====================================================================================

    // Días de vigencia de la vinculación cliente-corredor (parámetro CLICOR). 0 o más; error si falta.
    public static function diasParametroCorredor(): int
    {
        $parametro = DB::table('crm.parametro')->where('abreviacion', 'CLICOR')->first();
        $valor = $parametro ? trim((string) $parametro->valor) : '';

        if ($valor === '' || !ctype_digit($valor)) {
            throw new RuntimeException('El parámetro CLICOR no tiene un número de días válido, comuníquese con el administrador.');
        }

        return (int) $valor;
    }

    // Corredor con vinculación VIGENTE, o null si está libre. Si la vinculación ya cumplió los
    // días de CLICOR se desvincula aquí mismo y el cliente pasa a considerarse libre.
    public static function corredorVinculado(int $cliId, int $dias): ?string
    {
        $fila = DB::selectOne("SELECT corredor FROM public.clientes_multinivel
                                WHERE cli_id = ?
                                    AND activo = true
                                    AND fecha_desvinculacion IS NULL
                                LIMIT 1", [$cliId]);

        if (!$fila) {
            return null;
        }

        if (self::desvincularCorredorSiExpiro($cliId, $dias)) {
            return null;
        }

        return $fila->corredor;
    }

    // Vincula el cliente al corredor si está libre. Si ya tiene vinculación vigente no hace nada:
    // el caso "pertenece a otro corredor" se rechaza antes de llegar aquí.
    public static function vincularCorredor(int $cliId, string $corredor, int $tipoCorredor, int $dias): void
    {
        $vigente = DB::selectOne("SELECT cli_id FROM public.clientes_multinivel
                                    WHERE cli_id = ?
                                        AND activo = true
                                        AND fecha_desvinculacion IS NULL
                                    LIMIT 1", [$cliId]);

        if ($vigente) {
            return;
        }

        $vinculo = new ClientesMultinivel();
        $vinculo->cli_id = $cliId;
        $vinculo->corredor = $corredor;
        $vinculo->tipo_corredor = $tipoCorredor;
        $vinculo->dias_parametro = $dias;
        $vinculo->activo = true;
        $vinculo->save();
    }

    // =====================================================================================
    // PRIVADOS
    // =====================================================================================

    // Un cliente sin dirección o teléfono principal hace abortar a fn_clientes_modificar: se crean
    // con los datos del proveedor y se apuntan en la entidad (misma solución del corredor).
    private static function asegurarPrincipales(array $foto, array $datos): array
    {
        $entId = (int) $foto['ent_id'];

        if (empty($foto['direccion']['dir_id'])) {
            $nuevaDireccion = new Direccion();
            $nuevaDireccion->dir_calle_principal = $datos['direccion'];
            $nuevaDireccion->dir_calle_secundaria = null;
            $nuevaDireccion->dir_tipo = self::DIR_TIPO_DEFAULT;
            $nuevaDireccion->dir_principal = true;
            $nuevaDireccion->dir_activo = true;
            $nuevaDireccion->save();

            DB::update("UPDATE public.entidad SET ent_direccion_principal = ? WHERE ent_id = ?", [$nuevaDireccion->dir_id, $entId]);

            $foto['direccion'] = array_merge(
                is_array($foto['direccion'] ?? null) ? $foto['direccion'] : [],
                ['dir_id' => $nuevaDireccion->dir_id, 'dir_principal' => true, 'dir_activo' => true, 'dir_tipo' => self::DIR_TIPO_DEFAULT]
            );
        }

        if (empty($foto['telefono']['tel_id'])) {
            $nuevoTelefono = new Telefono();
            $nuevoTelefono->tte_id = self::TTE_ID_CELULAR;
            $nuevoTelefono->tel_numero = $datos['telefono'];
            $nuevoTelefono->tel_principal = true;
            $nuevoTelefono->tel_activo = true;
            $nuevoTelefono->save();

            DB::update("UPDATE public.entidad SET ent_telefono_principal = ? WHERE ent_id = ?", [$nuevoTelefono->tel_id, $entId]);

            $foto['telefono'] = array_merge(
                is_array($foto['telefono'] ?? null) ? $foto['telefono'] : [],
                ['tel_id' => $nuevoTelefono->tel_id, 'tte_id' => self::TTE_ID_CELULAR, 'tel_principal' => true, 'tel_activo' => true]
            );
        }

        return $foto;
    }

    // Si la vinculación vigente ya cumplió los días de CLICOR (created_at + días <= hoy) la
    // desactiva. Devuelve true si se desvinculó.
    private static function desvincularCorredorSiExpiro(int $cliId, int $dias): bool
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

    // MAYÚSCULAS, sin tildes, espacios colapsados: para comparar nombres de catálogo.
    private static function normalizarNombre(?string $texto): string
    {
        $texto = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $texto)), 'UTF-8');

        return strtr($texto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U']);
    }
}

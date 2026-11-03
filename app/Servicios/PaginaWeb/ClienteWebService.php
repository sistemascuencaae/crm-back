<?php

namespace App\Servicios\PaginaWeb;

use App\Models\mail\SendMailClienteWeb;
use App\Models\paginaWeb\ClienteWeb;
use App\Servicios\ValidacionCedulaRucService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Clientes de la tienda web (ecommerce.clientes_web), aparte de los usuarios del CRM. Los datos y sus reglas están en
 * las funciones ecommerce.fn_cliente_web_*; aquí solo se cifra y compara la contraseña (bcrypt: la base nunca la recibe
 * en texto), se generan los tokens de los enlaces de los correos (a la base va solo su sha256), se mandan los correos
 * y se emiten los JWT del guard 'cliente'.
 */
class ClienteWebService
{
    // Duración de la sesión: 1 día, o 30 si marcó "recordarme"
    public const MINUTOS_SESION     = 1440;
    public const MINUTOS_RECORDARME = 43200;

    // Duración y página de la tienda de cada enlace
    private const ENLACES = [
        'confirmar_correo' => ['minutos' => 1440, 'duracion' => '24 horas', 'pagina' => 'confirmar-correo'],
        'restablecer'      => ['minutos' => 60, 'duracion' => '1 hora', 'pagina' => 'nueva-contrasena'],
        'cambiar_correo'   => ['minutos' => 1440, 'duracion' => '24 horas', 'pagina' => 'cambiar-correo'],
    ];

    // Además del throttle por IP: no más de 3 correos del mismo tipo a la misma cuenta por hora
    private const CORREOS_POR_HORA = 3;

    // Lo que la tienda ve del perfil (sin clw_id ni sesiones_desde)
    private const CAMPOS_PERFIL = ['clw_nombres', 'clw_apellidos', 'clw_correo', 'clw_telefono', 'clw_identificacion', 'correo_confirmado'];

    // -----------------------------------------------------------------
    // Validaciones que hace Laravel (la base no ve la contraseña ni conoce el algoritmo de la cédula)
    // -----------------------------------------------------------------

    /** Contraseña nueva: 8 a 72 caracteres, al menos una letra y un número, y igual a su confirmación. [codigo, campo] o null. */
    public static function errorContrasena(Request $request): ?array
    {
        $contrasena = (string) $request->input('contrasena');

        if (strlen($contrasena) < 8 || strlen($contrasena) > 72 || !preg_match('/\pL/u', $contrasena) || !preg_match('/\d/', $contrasena)) {
            return ['CONTRASENA_DEBIL', 'contrasena'];
        }

        if ($contrasena !== (string) $request->input('contrasena_confirmation')) {
            return ['CONTRASENA_CONFIRMACION', 'contrasena_confirmation'];
        }

        return null;
    }

    /** La identificación es opcional: vacía vale; si llega, debe ser una cédula o un RUC válidos. */
    public static function identificacionInvalida($identificacion): bool
    {
        $identificacion = trim((string) $identificacion);

        return $identificacion !== '' && !ValidacionCedulaRucService::esIdentificacionValida($identificacion);
    }

    // -----------------------------------------------------------------
    // Cuenta
    // -----------------------------------------------------------------

    /** $datos: {clw_nombres, clw_apellidos, clw_correo, clw_telefono, clw_identificacion, acepta_politica}. Devuelve el clw_id. */
    public function registrar(array $datos, string $contrasena): int
    {
        return DB::selectOne('SELECT ecommerce.fn_cliente_web_registrar(?::jsonb) AS id', [
            $this->json($datos + ['clw_contrasena' => Hash::make($contrasena)]),
        ])->id;
    }

    /** {clw_id, clw_correo, clw_nombres, clw_apellidos, clw_telefono, clw_identificacion, correo_confirmado, sesiones_desde}; null si está desactivada */
    public function perfil(int $clwId): ?object
    {
        return DB::selectOne('SELECT * FROM ecommerce.fn_cliente_web_perfil(?)', [$clwId]);
    }

    /** El perfil como lo ve la tienda */
    public function perfilPublico(object $perfil): array
    {
        $publico = array_intersect_key((array) $perfil, array_flip(self::CAMPOS_PERFIL));
        $publico['correo_confirmado'] = (bool) $publico['correo_confirmado'];

        return $publico;
    }

    /** $datos: solo lo que cambia (clw_nombres, clw_apellidos, clw_telefono, clw_identificacion) */
    public function modificarPerfil(int $clwId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_cliente_web_modificar_perfil(?, ?::jsonb)', [$clwId, $this->json($datos)]);
    }

    /** ¿La contraseña escrita es la de la cuenta? */
    public function contrasenaCorrecta(int $clwId, $contrasena): bool
    {
        $huella = DB::selectOne('SELECT ecommerce.fn_cliente_web_contrasena(?) AS huella', [$clwId])->huella;

        return $huella !== null && Hash::check((string) $contrasena, $huella);
    }

    /** Cambia la contraseña y cierra las demás sesiones (mueve sesiones_desde) */
    public function cambiarContrasena(int $clwId, string $contrasena): void
    {
        DB::statement('SELECT ecommerce.fn_cliente_web_cambiar_contrasena(?, ?)', [$clwId, Hash::make($contrasena)]);
    }

    public function restablecerContrasena(int $clwId, string $contrasena): void
    {
        DB::statement('SELECT ecommerce.fn_cliente_web_restablecer_contrasena(?, ?)', [$clwId, Hash::make($contrasena)]);
    }

    public function confirmarCorreo(int $clwId): void
    {
        DB::statement('SELECT ecommerce.fn_cliente_web_confirmar_correo(?)', [$clwId]);
    }

    /** El correo nuevo, normalizado; lanza CORREO_INVALIDO, CORREO_REPETIDO o CORREO_IGUAL */
    public function correoDisponible(int $clwId, $correo): string
    {
        return DB::selectOne('SELECT ecommerce.fn_cliente_web_correo_disponible(?, ?) AS correo', [$clwId, $correo])->correo;
    }

    public function cambiarCorreo(int $clwId, string $correo): void
    {
        DB::statement('SELECT ecommerce.fn_cliente_web_cambiar_correo(?, ?)', [$clwId, $correo]);
    }

    // -----------------------------------------------------------------
    // Ingreso
    // -----------------------------------------------------------------

    /**
     * {clw_id, clw_contrasena, clw_activo, clw_bloqueado_hasta, bloqueo_segundos}; null si no hay cuenta con ese correo.
     * bloqueo_segundos: lo que falta del bloqueo, medido con la hora de la base (la de este servidor puede ser otra).
     */
    public function buscarParaIngreso($correo): ?object
    {
        return DB::selectOne('SELECT c.*, extract(epoch from (c.clw_bloqueado_hasta - now())) AS bloqueo_segundos
                                FROM ecommerce.fn_cliente_web_buscar_para_ingreso(?) c', [$correo]);
    }

    /** Cuenta el intento; si con este se bloquea, devuelve cuántos segundos dura el bloqueo (si no, null) */
    public function ingresoFallido(int $clwId): ?float
    {
        $segundos = DB::selectOne('SELECT extract(epoch from (h - now())) AS segundos FROM ecommerce.fn_cliente_web_ingreso_fallido(?) h', [$clwId])->segundos;

        return $segundos === null ? null : (float) $segundos;
    }

    public function ingresoExitoso(int $clwId): void
    {
        DB::statement('SELECT ecommerce.fn_cliente_web_ingreso_exitoso(?)', [$clwId]);
    }

    /** {clw_id, clw_correo, clw_nombres, correo_confirmado, clw_activo}; null si no hay cuenta con ese correo */
    public function buscarPorCorreo($correo): ?object
    {
        return DB::selectOne('SELECT * FROM ecommerce.fn_cliente_web_buscar_por_correo(?)', [$correo]);
    }

    /** JWT del guard 'cliente' y lo que necesita la tienda: {token, minutos, perfil} */
    public function sesion(object $perfil, int $minutos): array
    {
        return [
            'token'   => auth('cliente')->setTTL($minutos)->login(ClienteWeb::find($perfil->clw_id)),
            'minutos' => $minutos,
            'perfil'  => $this->perfilPublico($perfil),
        ];
    }

    // -----------------------------------------------------------------
    // Enlaces de los correos
    // -----------------------------------------------------------------

    /** ¿El enlace todavía sirve? No lo gasta (la tienda lo pregunta al abrir la página de nueva contraseña). */
    public function enlaceValido($token, string $tipo): bool
    {
        return (bool) DB::selectOne('SELECT ecommerce.fn_cliente_web_enlace_valido(?, ?) AS valido', [hash('sha256', (string) $token), $tipo])->valido;
    }

    /** Usa el enlace (una sola vez): {clw_id, dato}. Lanza ENLACE_INVALIDO si venció, ya se usó o no existe. */
    public function usarEnlace($token, string $tipo): object
    {
        return DB::selectOne('SELECT * FROM ecommerce.fn_cliente_web_enlace_usar(?, ?)', [hash('sha256', (string) $token), $tipo]);
    }

    /**
     * Crea el enlace y manda su correo a $cliente (clw_id, clw_nombres, clw_correo), o a $destino si llega (el correo nuevo).
     * Devuelve 'enviado', 'fallo' (el envío falló: queda en el log) o 'limite' (ya se mandaron 3 de este tipo esta hora:
     * no se crea el enlace, así el anterior sigue sirviendo).
     */
    public function enviarEnlace(object $cliente, string $tipo, ?string $dato = null, ?string $destino = null): string
    {
        $clave = 'clientes-correo:' . $tipo . ':' . $cliente->clw_id;
        if (RateLimiter::tooManyAttempts($clave, self::CORREOS_POR_HORA)) {
            return 'limite';
        }
        RateLimiter::hit($clave, 3600);

        $enlace = self::ENLACES[$tipo];
        $token  = Str::random(64);
        DB::statement('SELECT ecommerce.fn_cliente_web_enlace_crear(?, ?, ?, ?, ?)', [
            $cliente->clw_id, $tipo, hash('sha256', $token), $dato, $enlace['minutos'],
        ]);

        return $this->enviar($destino ?? $cliente->clw_correo, $cliente->clw_id, (object) [
            'tipo'     => $tipo,
            'nombres'  => $cliente->clw_nombres,
            'enlace'   => rtrim(config('app.tienda_url'), '/') . '/' . $enlace['pagina'] . '?token=' . $token,
            'duracion' => $enlace['duracion'],
        ]) ? 'enviado' : 'fallo';
    }

    /** Aviso sin enlace: 'aviso_contrasena' o 'aviso_correo' (con el correo nuevo). true si salió. */
    public function enviarAviso(string $tipo, string $correo, object $cliente, ?string $correoNuevo = null): bool
    {
        return $this->enviar($correo, $cliente->clw_id, (object) [
            'tipo'         => $tipo,
            'nombres'      => $cliente->clw_nombres,
            'correo_nuevo' => $correoNuevo,
        ]);
    }

    // -----------------------------------------------------------------
    // CRM (nunca ve ni cambia contraseñas)
    // -----------------------------------------------------------------

    /** {registros: [...], total} */
    public function listar(string $busqueda, int $pagina, int $tamanio): array
    {
        $registros = DB::select('SELECT * FROM ecommerce.fn_cliente_web_listar(?, ?, ?)', [$busqueda, $pagina, $tamanio]);

        return ['registros' => $registros, 'total' => (int) ($registros[0]->total ?? 0)];
    }

    public function buscar(int $clwId): ?object
    {
        return DB::selectOne('SELECT * FROM ecommerce.fn_cliente_web_buscar(?)', [$clwId]);
    }

    /** $activo: true / false (otra cosa → CAMPO_INVALIDO de la base). Desactivar cierra sus sesiones. */
    public function cambiarActivo(int $clwId, $activo, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_cliente_web_cambiar_activo(?, ?::boolean, ?)', [
            $clwId, is_bool($activo) ? ($activo ? 'true' : 'false') : null, $usuId,
        ]);
    }

    public function desbloquear(int $clwId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_cliente_web_desbloquear(?, ?)', [$clwId, $usuId]);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** Si el envío falla, la acción no se cae: queda en el log sin la dirección ni el token */
    private function enviar(string $correo, int $clwId, object $datos): bool
    {
        try {
            Mail::to($correo)->send(new SendMailClienteWeb($datos));

            return true;
        } catch (\Throwable $th) {
            Log::error('No se pudo enviar el correo al cliente de la tienda', [
                'tipo'   => $datos->tipo,
                'clw_id' => $clwId,
                'error'  => preg_replace('/[^\s<>"\']+@[^\s<>"\']+/', '***', $th->getMessage()),
            ]);

            return false;
        }
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}

<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\mail\SendMailClienteWeb;
use App\Auth\ClientesWebProveedor;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

// Clientes de la tienda web (ecommerce.clientes_web), aparte de los usuarios del CRM.
// Sin sesión: registro, ingreso, olvidé mi contraseña y los enlaces de los correos. Con sesión (middleware
// cliente.sesion, que deja el perfil en el request): ver y cambiar la cuenta, contraseña, correo y salir.
// Las reglas viven en ecommerce.fn_cliente_web_* (database/sql/clientes_web.sql): validaciones (también la cédula o el
// RUC), guardado y auditoría (módulo CLIENTES_WEB). Aquí solo se cifra y compara la contraseña (bcrypt: la base nunca la
// recibe en texto), se generan los tokens de los enlaces (a la base va solo su sha256), se mandan los correos y se
// emiten los JWT del guard 'cliente'. Cada escritura manda {datos, auditoria} (estándar de Clientes). Respuesta de siempre (RespuestaApi, HTTP 200); los errores llevan además
// 'codigo' y 'campo'. Cada ruta tiene su propio throttle (routes/api.php).
// Reemplaza a ClienteWebPublicoController, ClienteWebCuentaController y al servicio de clientes web.
class ClientesTiendaController extends Controller
{
    // Duración de la sesión: 1 día, o 30 si marcó "recordarme"
    private const MINUTOS_SESION     = 1440;
    private const MINUTOS_RECORDARME = 43200;

    // Duración y página de la tienda de cada enlace
    private const ENLACES = [
        'confirmar_correo' => ['minutos' => 1440, 'duracion' => '24 horas', 'pagina' => 'confirmar-correo'],
        'restablecer'      => ['minutos' => 60, 'duracion' => '1 hora', 'pagina' => 'nueva-contrasena'],
        'cambiar_correo'   => ['minutos' => 1440, 'duracion' => '24 horas', 'pagina' => 'cambiar-correo'],
    ];

    // Además del throttle por IP: no más de 3 correos del mismo tipo a la misma cuenta por hora
    private const CORREOS_POR_HORA = 3;

    // Lo único que se acepta al registrarse (además de contrasena, contrasena_confirmation y acepta_politica)
    private const CAMPOS_REGISTRO = ['clw_nombres', 'clw_apellidos', 'clw_correo', 'clw_telefono', 'clw_identificacion'];

    // Lo único que se cambia en el perfil (el correo tiene su propio flujo con enlace)
    private const CAMPOS_MODIFICABLES = ['clw_nombres', 'clw_apellidos', 'clw_telefono', 'clw_identificacion'];

    // Lo que la tienda ve del perfil (sin clw_id ni sesiones_desde)
    private const CAMPOS_PERFIL_PUBLICO = ['clw_nombres', 'clw_apellidos', 'clw_correo', 'clw_telefono', 'clw_identificacion', 'correo_confirmado'];

    // Huella bcrypt fija (la de "password" de las fábricas de Laravel; no es de nadie): cuando el correo no existe se
    // compara igual contra ella, así el tiempo de respuesta no delata si hay o no una cuenta con ese correo
    private const HUELLA_FIJA = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    // Código que lanza la función PG (o este controlador) -> mensaje para la tienda.
    // %s se reemplaza por el número del código (CAMPO_LARGO:clw_correo:150 → "Máximo 150 caracteres.")
    private const MENSAJES_ERROR = [
        'CAMPO_REQUERIDO'              => 'Este campo es obligatorio.',
        'CAMPO_LARGO'                  => 'Máximo %s caracteres.',
        'CAMPO_INVALIDO'               => 'El valor no es válido.',
        'REQUIERE_USUARIO'             => 'No se identificó al usuario que realiza el cambio.',
        'CORREO_INVALIDO'              => 'Escribe un correo válido.',
        'TELEFONO_INVALIDO'            => 'Escribe un teléfono válido, por ejemplo 0991234567.',
        'IDENTIFICACION_INVALIDA'      => 'La cédula o el RUC no es válido.',
        'CORREO_REPETIDO'              => 'Ya hay una cuenta con ese correo.',
        'IDENTIFICACION_REPETIDA'      => 'Ya hay una cuenta con esa cédula o RUC.',
        'CORREO_IGUAL'                 => 'Ese ya es el correo de tu cuenta.',
        'POLITICA_REQUERIDA'           => 'Para crear tu cuenta debes aceptar la política de privacidad.',
        'CONTRASENA_INVALIDA'          => 'No pudimos guardar la contraseña. Inténtalo de nuevo.',
        'CLIENTE_NO_EXISTE'            => 'La cuenta no existe.',
        'ENLACE_INVALIDO'              => 'El enlace venció o ya se usó. Pide uno nuevo.',
        'CREDENCIALES_INVALIDAS'       => 'Correo o contraseña incorrectos.',
        'CUENTA_BLOQUEADA'             => 'Tu cuenta está bloqueada por varios intentos fallidos. Inténtalo en %s minutos.',
        'CUENTA_INACTIVA'              => 'Tu cuenta está desactivada. Escríbenos si necesitas ayuda.',
        'CONTRASENA_ACTUAL_INCORRECTA' => 'La contraseña actual no es correcta.',
        'CONTRASENA_DEBIL'             => 'La contraseña debe tener entre 8 y 72 caracteres, con al menos una letra y un número.',
        'CONTRASENA_CONFIRMACION'      => 'Las contraseñas no coinciden.',
        'CORREOS_MAXIMO'               => 'Ya te enviamos varios correos. Espera unos minutos e inténtalo de nuevo.',
    ];

    // =================================================================
    // Sin sesión
    // =================================================================

    // POST public/pagina-web/clientes/registro
    //   {clw_nombres, clw_apellidos, clw_correo, clw_telefono, clw_identificacion (opcional), contrasena,
    //    contrasena_confirmation, acepta_politica}  → {token, minutos, perfil, correo_enviado} (ya inicia sesión)
    public function registro(Request $request)
    {
        if ($error = $this->errorContrasena($request)) {
            return $this->errorCliente(...$error);
        }

        try {
            $datos = $request->only(self::CAMPOS_REGISTRO) + ['acepta_politica' => $request->boolean('acepta_politica')];
            // La cuenta y su primer ingreso van juntos: si algo falla no queda una cuenta a medias (que al reintentar
            // diría "ya hay una cuenta con ese correo")
            $clwId = DB::transaction(function () use ($request, $datos) {
                $id = DB::selectOne('SELECT ecommerce.fn_cliente_web_registrar(?::jsonb) AS id', [
                    $this->json($datos + [
                        'clw_contrasena' => Hash::make($request->input('contrasena')),
                        'auditoria'      => $this->auditoria($request, (object) [
                            'clw_correo' => $request->input('clw_correo'),
                            'clw_nombres' => $request->input('clw_nombres'),
                            'clw_apellidos' => $request->input('clw_apellidos'),
                        ]),
                    ]),
                ])->id;
                DB::statement('SELECT ecommerce.fn_cliente_web_ingreso_exitoso(?)', [$id]);

                return $id;
            });

            $perfil = $this->perfilCliente($clwId);
            // La cuenta ya está creada: si el enlace de confirmación falla, se responde igual con éxito y
            // correo_enviado false (el cliente lo pide de nuevo con "reenviar confirmación")
            try {
                $correo = $this->enviarEnlace($perfil, 'confirmar_correo');
            } catch (QueryException $e) {
                Log::error('No se pudo crear el enlace de confirmación del cliente de la tienda', [
                    'clw_id' => $clwId,
                    'error'  => $e->getMessage(),
                ]);
                $correo = 'fallo';
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Cuenta creada con éxito',
                $this->sesion($perfil, self::MINUTOS_SESION) + ['correo_enviado' => $correo === 'enviado']));
        } catch (QueryException $e) {
            return $this->respuestaCliente($e, 'No se pudo crear la cuenta');
        }
    }

    // POST public/pagina-web/clientes/ingresar   {clw_correo, contrasena, recordarme}  → {token, minutos, perfil}
    public function ingresar(Request $request)
    {
        try {
            // Sin filas: no hay cuenta con ese correo. bloqueo_segundos: lo que falta del bloqueo, medido con la hora
            // de la base (la de este servidor puede ser otra)
            $cuenta = DB::selectOne('SELECT c.*, extract(epoch from (c.clw_bloqueado_hasta - now())) AS bloqueo_segundos
                                       FROM ecommerce.fn_cliente_web_buscar_para_ingreso(?) c', [trim((string) $request->input('clw_correo'))]);
            $contrasena = (string) $request->input('contrasena');

            if ($cuenta === null) {
                Hash::check($contrasena, self::HUELLA_FIJA);

                return $this->errorCliente('CREDENCIALES_INVALIDAS');
            }

            if ($cuenta->bloqueo_segundos !== null && $cuenta->bloqueo_segundos > 0) {
                return $this->bloqueada($cuenta->bloqueo_segundos);
            }

            if (!Hash::check($contrasena, $cuenta->clw_contrasena)) {
                // Cuenta el intento; si con este se bloquea, devuelve cuántos segundos dura el bloqueo (si no, null)
                $segundos = DB::selectOne('SELECT extract(epoch from (h - now())) AS segundos FROM ecommerce.fn_cliente_web_ingreso_fallido(?) h', [$cuenta->clw_id])->segundos;
                $segundos = $segundos === null ? null : (float) $segundos;

                return $segundos !== null ? $this->bloqueada($segundos) : $this->errorCliente('CREDENCIALES_INVALIDAS');
            }

            if (!$cuenta->clw_activo) {
                return $this->errorCliente('CUENTA_INACTIVA');
            }

            DB::statement('SELECT ecommerce.fn_cliente_web_ingreso_exitoso(?)', [$cuenta->clw_id]);
            $minutos = $request->boolean('recordarme') ? self::MINUTOS_RECORDARME : self::MINUTOS_SESION;

            return response()->json(RespuestaApi::returnResultado('success', 'Bienvenido',
                $this->sesion($this->perfilCliente($cuenta->clw_id), $minutos)));
        } catch (QueryException $e) {
            return $this->respuestaCliente($e, 'No se pudo ingresar');
        }
    }

    // POST public/pagina-web/clientes/olvide-contrasena   {clw_correo}
    // Siempre responde lo mismo, exista o no la cuenta (no delata qué correos tienen cuenta)
    public function olvideContrasena(Request $request)
    {
        try {
            // {clw_id, clw_correo, clw_nombres, correo_confirmado, clw_activo}; null si no hay cuenta con ese correo
            $cuenta = DB::selectOne('SELECT * FROM ecommerce.fn_cliente_web_buscar_por_correo(?)', [trim((string) $request->input('clw_correo'))]);

            if ($cuenta !== null && $cuenta->clw_activo) {
                $this->enviarEnlace($cuenta, 'restablecer');
            }
        } catch (QueryException $e) {
            // Un correo mal escrito tampoco cambia la respuesta
        }

        return response()->json(RespuestaApi::returnResultado('success', 'Si el correo tiene una cuenta, te enviamos un enlace', null));
    }

    // POST public/pagina-web/clientes/restablecer-contrasena   {token, contrasena, contrasena_confirmation}  (no inicia sesión)
    public function restablecerContrasena(Request $request)
    {
        // La contraseña se valida antes de usar el enlace: si está mal, el enlace sigue sirviendo
        if ($error = $this->errorContrasena($request)) {
            return $this->errorCliente(...$error);
        }

        try {
            $clwId = DB::transaction(function () use ($request) {
                $enlace = $this->usarEnlace($request->input('token'), 'restablecer');
                DB::selectOne('SELECT ecommerce.fn_cliente_web_restablecer_contrasena(?::jsonb) AS id', [$this->json([
                    'clw_id'         => $enlace->clw_id,
                    'clw_contrasena' => Hash::make($request->input('contrasena')),
                    'auditoria'      => $this->auditoria($request, $this->perfilCliente($enlace->clw_id)),
                ])]);

                return $enlace->clw_id;
            });

            $perfil = $this->perfilCliente($clwId);
            $this->enviarAviso('aviso_contrasena', $perfil->clw_correo, $perfil);

            return response()->json(RespuestaApi::returnResultado('success', 'Tu contraseña se cambió. Ya puedes ingresar.', null));
        } catch (QueryException $e) {
            return $this->respuestaCliente($e, 'No se pudo cambiar la contraseña');
        }
    }

    // POST public/pagina-web/clientes/restablecer-contrasena/verificar   {token}  → {valido: true | false}
    // La tienda lo pregunta al abrir la página de nueva contraseña; no gasta el enlace. Un token vacío o raro → false
    public function verificarEnlaceRestablecer(Request $request)
    {
        try {
            $valido = (bool) DB::selectOne('SELECT ecommerce.fn_cliente_web_enlace_valido(?, ?) AS valido', [
                hash('sha256', (string) $request->input('token')), 'restablecer',
            ])->valido;

            return response()->json(RespuestaApi::returnResultado('success', $valido ? 'El enlace sirve' : 'El enlace venció o ya se usó', ['valido' => $valido]));
        } catch (QueryException $e) {
            return $this->respuestaCliente($e, 'No se pudo revisar el enlace');
        }
    }

    // POST public/pagina-web/clientes/confirmar-correo   {token}
    public function confirmarCorreo(Request $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $enlace = $this->usarEnlace($request->input('token'), 'confirmar_correo');
                DB::selectOne('SELECT ecommerce.fn_cliente_web_confirmar_correo(?::jsonb) AS id', [$this->json([
                    'clw_id'    => $enlace->clw_id,
                    'auditoria' => $this->auditoria($request, $this->perfilCliente($enlace->clw_id)),
                ])]);
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Tu correo quedó confirmado', null));
        } catch (QueryException $e) {
            return $this->respuestaCliente($e, 'No se pudo confirmar el correo');
        }
    }

    // POST public/pagina-web/clientes/confirmar-cambio-correo   {token}  (avisa al correo anterior)
    public function confirmarCambioCorreo(Request $request)
    {
        try {
            [$antes, $correoNuevo] = DB::transaction(function () use ($request) {
                $enlace = $this->usarEnlace($request->input('token'), 'cambiar_correo');
                $antes  = $this->perfilCliente($enlace->clw_id);
                DB::selectOne('SELECT ecommerce.fn_cliente_web_cambiar_correo(?::jsonb) AS id', [$this->json([
                    'clw_id'     => $enlace->clw_id,
                    'clw_correo' => $enlace->dato,
                    'auditoria'  => $this->auditoria($request, $antes),
                ])]);

                return [$antes, $enlace->dato];
            });

            $this->enviarAviso('aviso_correo', $antes->clw_correo, $antes, $correoNuevo);

            return response()->json(RespuestaApi::returnResultado('success', 'El correo de tu cuenta cambió', ['clw_correo' => $correoNuevo]));
        } catch (QueryException $e) {
            return $this->respuestaCliente($e, 'No se pudo cambiar el correo');
        }
    }

    // =================================================================
    // Con sesión (cliente.sesion)
    // =================================================================

    // GET public/pagina-web/clientes/cuenta
    public function perfil(Request $request)
    {
        return response()->json(RespuestaApi::returnResultado('success', 'Tu cuenta', $this->perfilPublico($this->cliente($request))));
    }

    // PUT public/pagina-web/clientes/cuenta   (solo lo que cambia: clw_nombres, clw_apellidos, clw_telefono, clw_identificacion)
    public function modificar(Request $request)
    {
        try {
            $cliente = $this->cliente($request);
            $clwId = $cliente->clw_id;
            DB::selectOne('SELECT ecommerce.fn_cliente_web_modificar_perfil(?::jsonb) AS id', [$this->json(
                $request->only(self::CAMPOS_MODIFICABLES) + ['clw_id' => $clwId, 'auditoria' => $this->auditoria($request, $cliente)]
            )]);

            return response()->json(RespuestaApi::returnResultado('success', 'Tus datos se guardaron', $this->perfilPublico($this->perfilCliente($clwId))));
        } catch (QueryException $e) {
            return $this->respuestaCliente($e, 'No se pudieron guardar tus datos');
        }
    }

    // PUT public/pagina-web/clientes/cuenta/contrasena   {contrasena_actual, contrasena, contrasena_confirmation}
    // Cierra las demás sesiones (mueve sesiones_desde) y devuelve un token nuevo para esta, con la misma duración que tenía
    public function cambiarContrasena(Request $request)
    {
        $cliente = $this->cliente($request);

        if (!$this->contrasenaCorrecta($cliente->clw_id, $request->input('contrasena_actual'))) {
            return $this->errorCliente('CONTRASENA_ACTUAL_INCORRECTA', 'contrasena_actual');
        }
        if ($error = $this->errorContrasena($request)) {
            return $this->errorCliente(...$error);
        }

        try {
            $payload = auth('cliente')->payload();
            $minutos = (int) round(($payload->get('exp') - $payload->get('iat')) / 60);

            DB::selectOne('SELECT ecommerce.fn_cliente_web_cambiar_contrasena(?::jsonb) AS id', [$this->json([
                'clw_id'         => $cliente->clw_id,
                'clw_contrasena' => Hash::make($request->input('contrasena')),
                'auditoria'      => $this->auditoria($request, $cliente),
            ])]);
            $this->enviarAviso('aviso_contrasena', $cliente->clw_correo, $cliente);

            return response()->json(RespuestaApi::returnResultado('success', 'Tu contraseña se cambió', $this->sesion($cliente, $minutos)));
        } catch (QueryException $e) {
            return $this->respuestaCliente($e, 'No se pudo cambiar la contraseña');
        }
    }

    // POST public/pagina-web/clientes/cuenta/reenviar-confirmacion   → {correo_enviado}
    public function reenviarConfirmacion(Request $request)
    {
        $cliente = $this->cliente($request);

        if ($cliente->correo_confirmado) {
            return response()->json(RespuestaApi::returnResultado('success', 'Tu correo ya está confirmado', ['correo_enviado' => false]));
        }

        try {
            return $this->respuestaEnlace($this->enviarEnlace($cliente, 'confirmar_correo'), 'Te enviamos el enlace a tu correo');
        } catch (QueryException $e) {
            return $this->respuestaCliente($e, 'No se pudo enviar el enlace');
        }
    }

    // POST public/pagina-web/clientes/cuenta/cambiar-correo   {clw_correo (el nuevo), contrasena_actual}
    // Manda el enlace al correo nuevo; el correo no cambia hasta que se usa (confirmar-cambio-correo)
    public function cambiarCorreo(Request $request)
    {
        $cliente = $this->cliente($request);

        if (!$this->contrasenaCorrecta($cliente->clw_id, $request->input('contrasena_actual'))) {
            return $this->errorCliente('CONTRASENA_ACTUAL_INCORRECTA', 'contrasena_actual');
        }

        try {
            // El correo nuevo, normalizado; lanza CORREO_INVALIDO, CORREO_REPETIDO o CORREO_IGUAL
            $correo = (string) DB::selectOne('SELECT ecommerce.fn_cliente_web_correo_disponible(?, ?) AS correo', [$cliente->clw_id, $request->input('clw_correo')])->correo;

            return $this->respuestaEnlace($this->enviarEnlace($cliente, 'cambiar_correo', $correo, $correo),
                'Te enviamos un enlace a tu nuevo correo para confirmarlo');
        } catch (QueryException $e) {
            return $this->respuestaCliente($e, 'No se pudo cambiar el correo');
        }
    }

    // POST public/pagina-web/clientes/cuenta/salir   (este token queda en la lista negra)
    public function salir()
    {
        auth('cliente')->logout();

        return response()->json(RespuestaApi::returnResultado('success', 'Cerraste sesión', null));
    }

    // =================================================================
    // Internos
    // =================================================================

    // Contraseña nueva: 8 a 72 caracteres, al menos una letra y un número, e igual a su confirmación. [codigo, campo] o null.
    // La valida Laravel: la base nunca ve la contraseña.
    private function errorContrasena(Request $request): ?array
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

    // Contexto de auditoría (estándar de Clientes) de lo que hace el propio cliente: sin usuario del CRM; login = su correo
    private function auditoria(Request $request, ?object $cliente): array
    {
        return [
            'usuario_id'     => null,
            'usuario_login'  => $cliente ? mb_strtolower(trim((string) ($cliente->clw_correo ?? ''))) : null,
            'usuario_nombre' => $cliente ? trim(trim((string) ($cliente->clw_nombres ?? '')) . ' ' . trim((string) ($cliente->clw_apellidos ?? ''))) : null,
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'request_id'     => (string) Str::uuid(),
        ];
    }

    // {clw_id, clw_correo, clw_nombres, clw_apellidos, clw_telefono, clw_identificacion, correo_confirmado, sesiones_desde};
    // null si está desactivada
    private function perfilCliente(int $clwId): ?object
    {
        return DB::selectOne('SELECT * FROM ecommerce.fn_cliente_web_perfil(?)', [$clwId]);
    }

    // El perfil como lo ve la tienda
    private function perfilPublico(object $perfil): array
    {
        $publico = array_intersect_key((array) $perfil, array_flip(self::CAMPOS_PERFIL_PUBLICO));
        $publico['correo_confirmado'] = (bool) $publico['correo_confirmado'];

        return $publico;
    }

    // ¿La contraseña escrita es la de la cuenta?
    private function contrasenaCorrecta(int $clwId, $contrasena): bool
    {
        $huella = DB::selectOne('SELECT ecommerce.fn_cliente_web_contrasena(?) AS huella', [$clwId])->huella;

        return $huella !== null && Hash::check((string) $contrasena, $huella);
    }

    // JWT del guard 'cliente' y lo que necesita la tienda: {token, minutos, perfil}
    private function sesion(object $perfil, int $minutos): array
    {
        return [
            'token'   => auth('cliente')->setTTL($minutos)->login(ClientesWebProveedor::cliente((array) $perfil)),
            'minutos' => $minutos,
            'perfil'  => $this->perfilPublico($perfil),
        ];
    }

    // El perfil que dejó cliente.sesion
    private function cliente(Request $request): object
    {
        return $request->attributes->get('cliente_perfil');
    }

    // Usa el enlace (una sola vez): {clw_id, dato}. Lanza ENLACE_INVALIDO si venció, ya se usó o no existe.
    private function usarEnlace($token, string $tipo): object
    {
        return DB::selectOne('SELECT * FROM ecommerce.fn_cliente_web_enlace_usar(?, ?)', [hash('sha256', (string) $token), $tipo]);
    }

    // Crea el enlace y manda su correo a $cliente (clw_id, clw_nombres, clw_correo), o a $destino si llega (el correo nuevo).
    // Devuelve 'enviado', 'fallo' (el envío falló: queda en el log) o 'limite' (ya se mandaron 3 de este tipo esta hora:
    // no se crea el enlace, así el anterior sigue sirviendo).
    private function enviarEnlace(object $cliente, string $tipo, ?string $dato = null, ?string $destino = null): string
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

    // Aviso sin enlace: 'aviso_contrasena' o 'aviso_correo' (con el correo nuevo). true si salió.
    private function enviarAviso(string $tipo, string $correo, object $cliente, ?string $correoNuevo = null): bool
    {
        return $this->enviar($correo, $cliente->clw_id, (object) [
            'tipo'         => $tipo,
            'nombres'      => $cliente->clw_nombres,
            'correo_nuevo' => $correoNuevo,
        ]);
    }

    // Si el envío falla, la acción no se cae: queda en el log sin la dirección ni el token
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

    // Resultado de enviarEnlace: 'limite' → espera unos minutos; si no, ok con correo_enviado
    private function respuestaEnlace(string $resultado, string $mensaje)
    {
        if ($resultado === 'limite') {
            return $this->errorCliente('CORREOS_MAXIMO');
        }

        return response()->json(RespuestaApi::returnResultado('success', $mensaje, ['correo_enviado' => $resultado === 'enviado']));
    }

    // CUENTA_BLOQUEADA con los minutos que faltan (mínimo 1)
    private function bloqueada($segundos)
    {
        return $this->errorCliente('CUENTA_BLOQUEADA', null, max(1, (int) ceil($segundos / 60)));
    }

    // Error de un código de la base. Desconocido → mensaje genérico con codigo null (el detalle queda en el log).
    // El código viene después de "ERROR:", con el campo y a veces un número:  ERROR:  CAMPO_LARGO:clw_correo:150
    private function respuestaCliente(QueryException $e, string $mensajeGenerico)
    {
        if (preg_match('/ERROR:\s+([A-Z_]+)(?::([a-z0-9_.]+))?(?::(\d+))?/', $e->getMessage(), $m) && isset(self::MENSAJES_ERROR[$m[1]])) {
            return $this->errorCliente($m[1], $m[2] ?? null, $m[3] ?? null);
        }

        Log::error($mensajeGenerico, ['error' => $e->getMessage()]);

        return response()->json(RespuestaApi::returnResultado('error', $mensajeGenerico, null) + ['codigo' => null, 'campo' => null]);
    }

    // Error con un código conocido: {code: 500, status: 'error', message, data: null, codigo, campo}.
    // $numero reemplaza el %s del mensaje (minutos de CUENTA_BLOQUEADA, largo de CAMPO_LARGO).
    private function errorCliente(string $codigo, ?string $campo = null, $numero = null)
    {
        $mensaje = sprintf(self::MENSAJES_ERROR[$codigo], $numero ?? '');

        return response()->json(
            ['code' => 500] + RespuestaApi::returnResultado('error', $mensaje, null) + ['codigo' => $codigo, 'campo' => $campo ?: null]
        );
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}

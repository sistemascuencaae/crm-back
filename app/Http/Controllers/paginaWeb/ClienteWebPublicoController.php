<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ClienteWebService;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Clientes de la tienda, sin sesión: registro, ingreso, olvidé mi contraseña y los enlaces de los correos.
 * Respuesta de siempre (RespuestaApi, HTTP 200); los errores llevan además 'codigo' y 'campo' (ErroresPaginaWeb).
 * Cada ruta tiene su propio throttle (routes/api.php).
 */
class ClienteWebPublicoController extends Controller
{
    // Lo único que se acepta al registrarse (además de contrasena, contrasena_confirmation y acepta_politica)
    private const CAMPOS_REGISTRO = ['clw_nombres', 'clw_apellidos', 'clw_correo', 'clw_telefono', 'clw_identificacion'];

    // Huella bcrypt fija (la de "password" de las fábricas de Laravel; no es de nadie): cuando el correo no existe se
    // compara igual contra ella, así el tiempo de respuesta no delata si hay o no una cuenta con ese correo
    private const HUELLA_FIJA = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function __construct(private ClienteWebService $servicio)
    {
    }

    // POST public/pagina-web/clientes/registro
    //   {clw_nombres, clw_apellidos, clw_correo, clw_telefono, clw_identificacion (opcional), contrasena,
    //    contrasena_confirmation, acepta_politica}  → {token, minutos, perfil, correo_enviado} (ya inicia sesión)
    public function registro(Request $request)
    {
        if ($error = ClienteWebService::errorContrasena($request)) {
            return ErroresPaginaWeb::errorCliente(...$error);
        }
        if (ClienteWebService::identificacionInvalida($request->input('clw_identificacion'))) {
            return ErroresPaginaWeb::errorCliente('IDENTIFICACION_INVALIDA', 'clw_identificacion');
        }

        try {
            $clwId = $this->servicio->registrar(
                $request->only(self::CAMPOS_REGISTRO) + ['acepta_politica' => $request->boolean('acepta_politica')],
                $request->input('contrasena')
            );
            $perfil = $this->servicio->perfil($clwId);
            $correo = $this->servicio->enviarEnlace($perfil, 'confirmar_correo');
            $this->servicio->ingresoExitoso($clwId);

            return response()->json(RespuestaApi::returnResultado('success', 'Cuenta creada con éxito',
                $this->servicio->sesion($perfil, ClienteWebService::MINUTOS_SESION) + ['correo_enviado' => $correo === 'enviado']));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuestaCliente($e, 'No se pudo crear la cuenta');
        }
    }

    // POST public/pagina-web/clientes/ingresar   {clw_correo, contrasena, recordarme}  → {token, minutos, perfil}
    public function ingresar(Request $request)
    {
        try {
            $cuenta     = $this->servicio->buscarParaIngreso(trim((string) $request->input('clw_correo')));
            $contrasena = (string) $request->input('contrasena');

            if ($cuenta === null) {
                Hash::check($contrasena, self::HUELLA_FIJA);

                return ErroresPaginaWeb::errorCliente('CREDENCIALES_INVALIDAS');
            }

            if ($cuenta->bloqueo_segundos !== null && $cuenta->bloqueo_segundos > 0) {
                return $this->bloqueada($cuenta->bloqueo_segundos);
            }

            if (!Hash::check($contrasena, $cuenta->clw_contrasena)) {
                $segundos = $this->servicio->ingresoFallido($cuenta->clw_id);

                return $segundos !== null ? $this->bloqueada($segundos) : ErroresPaginaWeb::errorCliente('CREDENCIALES_INVALIDAS');
            }

            if (!$cuenta->clw_activo) {
                return ErroresPaginaWeb::errorCliente('CUENTA_INACTIVA');
            }

            $this->servicio->ingresoExitoso($cuenta->clw_id);
            $minutos = $request->boolean('recordarme') ? ClienteWebService::MINUTOS_RECORDARME : ClienteWebService::MINUTOS_SESION;

            return response()->json(RespuestaApi::returnResultado('success', 'Bienvenido',
                $this->servicio->sesion($this->servicio->perfil($cuenta->clw_id), $minutos)));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuestaCliente($e, 'No se pudo ingresar');
        }
    }

    // POST public/pagina-web/clientes/olvide-contrasena   {clw_correo}
    // Siempre responde lo mismo, exista o no la cuenta (no delata qué correos tienen cuenta)
    public function olvideContrasena(Request $request)
    {
        try {
            $cuenta = $this->servicio->buscarPorCorreo(trim((string) $request->input('clw_correo')));

            if ($cuenta !== null && $cuenta->clw_activo) {
                $this->servicio->enviarEnlace($cuenta, 'restablecer');
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
        if ($error = ClienteWebService::errorContrasena($request)) {
            return ErroresPaginaWeb::errorCliente(...$error);
        }

        try {
            $clwId = DB::transaction(function () use ($request) {
                $enlace = $this->servicio->usarEnlace($request->input('token'), 'restablecer');
                $this->servicio->restablecerContrasena($enlace->clw_id, $request->input('contrasena'));

                return $enlace->clw_id;
            });

            $perfil = $this->servicio->perfil($clwId);
            $this->servicio->enviarAviso('aviso_contrasena', $perfil->clw_correo, $perfil);

            return response()->json(RespuestaApi::returnResultado('success', 'Tu contraseña se cambió. Ya puedes ingresar.', null));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuestaCliente($e, 'No se pudo cambiar la contraseña');
        }
    }

    // POST public/pagina-web/clientes/restablecer-contrasena/verificar   {token}  → {valido: true | false}
    // La tienda lo pregunta al abrir la página de nueva contraseña; no gasta el enlace. Un token vacío o raro → false
    public function verificarEnlaceRestablecer(Request $request)
    {
        try {
            $valido = $this->servicio->enlaceValido($request->input('token'), 'restablecer');

            return response()->json(RespuestaApi::returnResultado('success', $valido ? 'El enlace sirve' : 'El enlace venció o ya se usó', ['valido' => $valido]));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuestaCliente($e, 'No se pudo revisar el enlace');
        }
    }

    // POST public/pagina-web/clientes/confirmar-correo   {token}
    public function confirmarCorreo(Request $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $enlace = $this->servicio->usarEnlace($request->input('token'), 'confirmar_correo');
                $this->servicio->confirmarCorreo($enlace->clw_id);
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Tu correo quedó confirmado', null));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuestaCliente($e, 'No se pudo confirmar el correo');
        }
    }

    // POST public/pagina-web/clientes/confirmar-cambio-correo   {token}  (avisa al correo anterior)
    public function confirmarCambioCorreo(Request $request)
    {
        try {
            [$antes, $correoNuevo] = DB::transaction(function () use ($request) {
                $enlace = $this->servicio->usarEnlace($request->input('token'), 'cambiar_correo');
                $antes  = $this->servicio->perfil($enlace->clw_id);
                $this->servicio->cambiarCorreo($enlace->clw_id, $enlace->dato);

                return [$antes, $enlace->dato];
            });

            $this->servicio->enviarAviso('aviso_correo', $antes->clw_correo, $antes, $correoNuevo);

            return response()->json(RespuestaApi::returnResultado('success', 'El correo de tu cuenta cambió', ['clw_correo' => $correoNuevo]));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuestaCliente($e, 'No se pudo cambiar el correo');
        }
    }

    // -----------------------------------------------------------------

    /** CUENTA_BLOQUEADA con los minutos que faltan (mínimo 1) */
    private function bloqueada($segundos)
    {
        return ErroresPaginaWeb::errorCliente('CUENTA_BLOQUEADA', null, max(1, (int) ceil($segundos / 60)));
    }
}

<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ClienteWebService;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * La cuenta de un cliente de la tienda, con su sesión (middleware cliente.sesion, que deja el perfil en el request).
 * Respuesta de siempre (RespuestaApi, HTTP 200); los errores llevan además 'codigo' y 'campo' (ErroresPaginaWeb).
 */
class ClienteWebCuentaController extends Controller
{
    // Lo único que se cambia en el perfil (el correo tiene su propio flujo con enlace)
    private const CAMPOS_PERFIL = ['clw_nombres', 'clw_apellidos', 'clw_telefono', 'clw_identificacion'];

    public function __construct(private ClienteWebService $servicio)
    {
    }

    // GET public/pagina-web/clientes/cuenta
    public function perfil(Request $request)
    {
        return response()->json(RespuestaApi::returnResultado('success', 'Tu cuenta', $this->servicio->perfilPublico($this->cliente($request))));
    }

    // PUT public/pagina-web/clientes/cuenta   (solo lo que cambia: clw_nombres, clw_apellidos, clw_telefono, clw_identificacion)
    public function modificar(Request $request)
    {
        if ($request->has('clw_identificacion') && ClienteWebService::identificacionInvalida($request->input('clw_identificacion'))) {
            return ErroresPaginaWeb::errorCliente('IDENTIFICACION_INVALIDA', 'clw_identificacion');
        }

        try {
            $clwId = $this->cliente($request)->clw_id;
            $this->servicio->modificarPerfil($clwId, $request->only(self::CAMPOS_PERFIL));

            return response()->json(RespuestaApi::returnResultado('success', 'Tus datos se guardaron', $this->servicio->perfilPublico($this->servicio->perfil($clwId))));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuestaCliente($e, 'No se pudieron guardar tus datos');
        }
    }

    // PUT public/pagina-web/clientes/cuenta/contrasena   {contrasena_actual, contrasena, contrasena_confirmation}
    // Cierra las demás sesiones y devuelve un token nuevo para esta, con la misma duración que tenía
    public function cambiarContrasena(Request $request)
    {
        $cliente = $this->cliente($request);

        if (!$this->servicio->contrasenaCorrecta($cliente->clw_id, $request->input('contrasena_actual'))) {
            return ErroresPaginaWeb::errorCliente('CONTRASENA_ACTUAL_INCORRECTA', 'contrasena_actual');
        }
        if ($error = ClienteWebService::errorContrasena($request)) {
            return ErroresPaginaWeb::errorCliente(...$error);
        }

        try {
            $payload = auth('cliente')->payload();
            $minutos = (int) round(($payload->get('exp') - $payload->get('iat')) / 60);

            $this->servicio->cambiarContrasena($cliente->clw_id, $request->input('contrasena'));
            $this->servicio->enviarAviso('aviso_contrasena', $cliente->clw_correo, $cliente);

            return response()->json(RespuestaApi::returnResultado('success', 'Tu contraseña se cambió', $this->servicio->sesion($cliente, $minutos)));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuestaCliente($e, 'No se pudo cambiar la contraseña');
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
            return $this->respuestaEnlace($this->servicio->enviarEnlace($cliente, 'confirmar_correo'), 'Te enviamos el enlace a tu correo');
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuestaCliente($e, 'No se pudo enviar el enlace');
        }
    }

    // POST public/pagina-web/clientes/cuenta/cambiar-correo   {clw_correo (el nuevo), contrasena_actual}
    // Manda el enlace al correo nuevo; el correo no cambia hasta que se usa (confirmar-cambio-correo)
    public function cambiarCorreo(Request $request)
    {
        $cliente = $this->cliente($request);

        if (!$this->servicio->contrasenaCorrecta($cliente->clw_id, $request->input('contrasena_actual'))) {
            return ErroresPaginaWeb::errorCliente('CONTRASENA_ACTUAL_INCORRECTA', 'contrasena_actual');
        }

        try {
            $correo = $this->servicio->correoDisponible($cliente->clw_id, $request->input('clw_correo'));

            return $this->respuestaEnlace($this->servicio->enviarEnlace($cliente, 'cambiar_correo', $correo, $correo),
                'Te enviamos un enlace a tu nuevo correo para confirmarlo');
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuestaCliente($e, 'No se pudo cambiar el correo');
        }
    }

    // POST public/pagina-web/clientes/cuenta/salir   (este token queda en la lista negra)
    public function salir()
    {
        auth('cliente')->logout();

        return response()->json(RespuestaApi::returnResultado('success', 'Cerraste sesión', null));
    }

    // -----------------------------------------------------------------

    /** El perfil que dejó cliente.sesion */
    private function cliente(Request $request): object
    {
        return $request->attributes->get('cliente_perfil');
    }

    /** Resultado de enviarEnlace: 'limite' → espera unos minutos; si no, ok con correo_enviado */
    private function respuestaEnlace(string $resultado, string $mensaje)
    {
        if ($resultado === 'limite') {
            return ErroresPaginaWeb::errorCliente('CORREOS_MAXIMO');
        }

        return response()->json(RespuestaApi::returnResultado('success', $mensaje, ['correo_enviado' => $resultado === 'enviado']));
    }
}

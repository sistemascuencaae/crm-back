<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Carrito de la tienda, con sesión (el del cliente) y sin sesión (invitado: sirve de cotización).
// Las reglas viven en ecommerce.fn_carrito_* (database/sql/carritos.sql): qué producto entra, de 1 a 10 unidades, sin
// pasar el stock, el precio de la vista web, los avisos, unir al iniciar sesión y la limpieza de abandonados. Aquí solo
// se decide de quién es el carrito y se genera el token del invitado.
//   - Con sesión (Authorization: Bearer, guard 'cliente'): el carrito del cliente. El middleware cliente.sesion:opcional
//     deja el perfil en el request.
//   - Sin sesión: el invitado manda su token en la cabecera X-Carrito. Si no tiene, al agregar el primer producto se le
//     genera uno y va en la respuesta (carrito_token): la tienda lo guarda y lo manda siempre. A la base va solo su sha256.
// Cada operación responde el carrito completo, listo para pintar. Sin auditoría (decisión del usuario).
// Respuesta de siempre (RespuestaApi, HTTP 200); los errores llevan además 'codigo' y 'campo'.
class CarritoTiendaController extends Controller
{
    private const CABECERA_INVITADO = 'X-Carrito';

    // Código que lanza la función PG -> mensaje para la tienda. %s: el número del código (máximo o stock)
    private const MENSAJES_ERROR = [
        'PRODUCTO_NO_DISPONIBLE' => 'Este producto ya no está disponible.',
        'PRODUCTO_SOLO_WHATSAPP' => 'Este producto se pide por WhatsApp.',
        'CANTIDAD_INVALIDA'      => 'Elige una cantidad válida.',
        'CANTIDAD_MAXIMA'        => 'Puedes llevar hasta %s unidades de cada producto.',
        'STOCK_INSUFICIENTE'     => 'Solo hay %s unidades disponibles.',
        'LINEA_NO_EXISTE'        => 'Ese producto ya no está en tu carrito.',
        'CARRITO_SIN_DUENO'      => 'No encontramos tu carrito.',
        'CARRITO_INVALIDO'       => 'No encontramos tu carrito.',
        'CLIENTE_NO_EXISTE'      => 'Tu sesión venció. Vuelve a ingresar.',
    ];

    // Aviso de una línea (lo decide la base) -> texto para la tienda
    private const AVISOS = [
        'NO_DISPONIBLE'      => 'Ya no está disponible. Quítalo para continuar.',
        'STOCK_INSUFICIENTE' => 'Solo hay %s unidades disponibles. Baja la cantidad para continuar.',
    ];

    // GET public/pagina-web/carrito → {lineas, total, unidades, hay_avisos}
    public function ver(Request $request)
    {
        $dueno = $this->dueno($request);
        if ($dueno === null) {
            return $this->respuesta($this->vacio(), 'Tu carrito');
        }

        try {
            return $this->respuesta($this->carrito($dueno), 'Tu carrito');
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo cargar el carrito');
        }
    }

    // POST public/pagina-web/carrito/lineas   {pro_id, cantidad}  (si ya estaba, suma)
    public function agregar(Request $request)
    {
        [$dueno, $token] = $this->duenoParaAgregar($request);

        return $this->escribir('fn_carrito_agregar', $dueno, $this->linea($request), 'Producto agregado al carrito', 'No se pudo agregar el producto', $token);
    }

    // PUT public/pagina-web/carrito/lineas/{proId}   {cantidad}
    public function cambiarCantidad(Request $request, int $proId)
    {
        $dueno = $this->dueno($request);
        if ($dueno === null) {
            return $this->errorCarrito('LINEA_NO_EXISTE');
        }

        return $this->escribir('fn_carrito_cambiar_cantidad', $dueno, ['pro_id' => $proId, 'cantidad' => $request->input('cantidad')], 'Cantidad actualizada', 'No se pudo cambiar la cantidad');
    }

    // DELETE public/pagina-web/carrito/lineas/{proId}
    public function quitar(Request $request, int $proId)
    {
        $dueno = $this->dueno($request);
        if ($dueno === null) {
            return $this->respuesta($this->vacio(), 'Producto quitado del carrito');
        }

        return $this->escribir('fn_carrito_quitar', $dueno, ['pro_id' => $proId], 'Producto quitado del carrito', 'No se pudo quitar el producto');
    }

    // DELETE public/pagina-web/carrito
    public function vaciar(Request $request)
    {
        $dueno = $this->dueno($request);
        if ($dueno === null) {
            return $this->respuesta($this->vacio(), 'Carrito vacío');
        }

        return $this->escribir('fn_carrito_vaciar', $dueno, [], 'Carrito vacío', 'No se pudo vaciar el carrito');
    }

    // POST public/pagina-web/carrito/unir   (con sesión y la cabecera X-Carrito) — justo después de ingresar o registrarse:
    // las líneas del carrito de invitado pasan al del cliente. Después la tienda borra su token de invitado.
    public function unir(Request $request)
    {
        $token = $this->tokenInvitado($request);
        $dueno = ['clw_id' => (int) $request->attributes->get('cliente_perfil')->clw_id];
        if ($token === null) {
            return $this->ver($request);
        }

        return $this->escribir('fn_carrito_unir', $dueno, ['huella' => hash('sha256', $token)], 'Tu carrito', 'No se pudo unir el carrito');
    }

    // -----------------------------------------------------------------

    // Llama a la escritura con {dueño + datos} y responde el carrito del dueño (y el token nuevo del invitado, si se generó)
    private function escribir(string $funcion, array $dueno, array $datos, string $mensaje, string $mensajeGenerico, ?string $tokenNuevo = null)
    {
        try {
            DB::selectOne("SELECT ecommerce.{$funcion}(?::jsonb) AS id", [json_encode($dueno + $datos, JSON_UNESCAPED_UNICODE)]);

            return $this->respuesta($this->carrito($dueno), $mensaje, $tokenNuevo);
        } catch (QueryException $e) {
            return $this->respuestaError($e, $mensajeGenerico);
        }
    }

    // El carrito armado por la base, con las fotos con la dirección de este servidor y el texto de cada aviso
    private function carrito(array $dueno): array
    {
        $c = json_decode(DB::selectOne('SELECT ecommerce.fn_carrito_ver(?::jsonb) AS c', [json_encode($dueno)])->c, true);
        foreach ($c['lineas'] as $i => $l) {
            $c['lineas'][$i]['imagenes'] = array_map(fn ($ruta) => url($ruta), is_array($l['imagenes']) ? $l['imagenes'] : []);
            $c['lineas'][$i]['aviso_mensaje'] = isset(self::AVISOS[$l['aviso'] ?? ''])
                ? sprintf(self::AVISOS[$l['aviso']], $l['stock'])
                : null;
        }

        return $c;
    }

    private function vacio(): array
    {
        return ['lineas' => [], 'total' => 0, 'unidades' => 0, 'hay_avisos' => false];
    }

    // De quién es el carrito: {clw_id} con sesión, {huella} del invitado con token, o null (invitado sin token)
    private function dueno(Request $request): ?array
    {
        $perfil = $request->attributes->get('cliente_perfil');
        if ($perfil) {
            return ['clw_id' => (int) $perfil->clw_id];
        }
        $token = $this->tokenInvitado($request);

        return $token === null ? null : ['huella' => hash('sha256', $token)];
    }

    // Al agregar, un invitado sin token recibe uno nuevo: [dueño, token nuevo o null]
    private function duenoParaAgregar(Request $request): array
    {
        $dueno = $this->dueno($request);
        if ($dueno !== null) {
            return [$dueno, null];
        }
        $token = Str::random(64);

        return [['huella' => hash('sha256', $token)], $token];
    }

    // El token del invitado (cabecera X-Carrito). Uno que no tenga la forma de los que genera este back no cuenta.
    private function tokenInvitado(Request $request): ?string
    {
        $token = trim((string) $request->header(self::CABECERA_INVITADO, ''));

        return preg_match('/^[A-Za-z0-9]{64}$/', $token) ? $token : null;
    }

    // Lo único que se acepta de una línea (los tipos los revisa la base)
    private function linea(Request $request): array
    {
        return ['pro_id' => $request->input('pro_id'), 'cantidad' => $request->input('cantidad', 1)];
    }

    private function respuesta(array $carrito, string $mensaje, ?string $tokenNuevo = null)
    {
        return response()->json(RespuestaApi::returnResultado('success', $mensaje, $carrito + ($tokenNuevo ? ['carrito_token' => $tokenNuevo] : [])));
    }

    // Error de un código de la base. Desconocido → mensaje genérico con codigo null (el detalle queda en el log).
    // El código viene después de "ERROR:", con el campo y a veces un número:  ERROR:  STOCK_INSUFICIENTE:cantidad:3
    private function respuestaError(QueryException $e, string $mensajeGenerico)
    {
        if (preg_match('/ERROR:\s+([A-Z_]+)(?::([a-z0-9_.]+))?(?::(\d+))?/', $e->getMessage(), $m) && isset(self::MENSAJES_ERROR[$m[1]])) {
            return $this->errorCarrito($m[1], $m[2] ?? null, $m[3] ?? null);
        }

        Log::error($mensajeGenerico, ['error' => $e->getMessage()]);

        return response()->json(RespuestaApi::returnResultado('error', $mensajeGenerico, null) + ['codigo' => null, 'campo' => null]);
    }

    // {code: 500, status: 'error', message, data: null, codigo, campo}
    private function errorCarrito(string $codigo, ?string $campo = null, $numero = null)
    {
        $mensaje = sprintf(self::MENSAJES_ERROR[$codigo], $numero ?? '');

        return response()->json(
            ['code' => 500] + RespuestaApi::returnResultado('error', $mensaje, null) + ['codigo' => $codigo, 'campo' => $campo ?: null]
        );
    }
}

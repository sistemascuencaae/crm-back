<?php

namespace App\Http\Controllers\anuncios;

use App\Http\Controllers\Controller;
use App\Events\AnuncioEvent;
use App\Http\Resources\RespuestaApi;
use App\Models\anuncios\Anuncio;
use App\Models\anuncios\AnuncioDestino;
use App\Models\anuncios\AnuncioImagen;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * CRUD de administracion de anuncios.
 * El consumo por parte del usuario final vive en AnunciosUsuarioController.
 */
class AnunciosController extends Controller
{
    /** carpeta raiz dentro del disco */
    private const CARPETA = "anuncios";

    /** tamano maximo por imagen en KB */
    private const MAX_KB_IMAGEN = 5120;

    /**
     * Interruptor del modulo para TODO el CRUD, en un solo lugar.
     *
     * Va como middleware de closure y no como verificacion repetida en cada
     * metodo: asi cubre tambien los que se agreguen despues, sin que a nadie
     * se le olvide ponerlo. Con el modulo apagado ningun endpoint llega a
     * consultar crm.anuncios, asi que el codigo se puede desplegar aunque
     * las tablas todavia no existan.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!Anuncio::moduloActivo()) {
                return response()->json(
                    RespuestaApi::returnResultado('error', 'El módulo de anuncios no está habilitado', null)
                );
            }
            return $next($request);
        });
    }

    public function listAllAnuncios()
    {
        try {
            $data = Anuncio::with(["imagenes", "destinos"])
                ->orderBy("orden")
                ->orderBy("id", "desc")
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function getAnuncio($id)
    {
        try {
            $data = Anuncio::with(["imagenes", "destinos"])->findOrFail($id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function addAnuncio(Request $request)
    {
        try {
            $validacion = $this->validarCabecera($request);
            if ($validacion !== null) {
                return response()->json(RespuestaApi::returnResultado('error', $validacion, null));
            }

            $data = DB::transaction(function () use ($request) {

                $anuncio = Anuncio::create([
                    "titulo"       => $request->input("titulo"),
                    "descripcion"  => $request->input("descripcion"),
                    "fecha_inicio" => $request->input("fecha_inicio"),
                    "fecha_fin"    => $request->input("fecha_fin"),
                    "activo"       => $request->boolean("activo", true),
                    "ver_todos"    => $request->boolean("ver_todos", false),
                    "orden"        => (int) $request->input("orden", 0),
                    "created_by"   => auth()->id(),
                ]);

                $this->guardarDestinos($anuncio, $request);
                $this->guardarImagenes($anuncio, $request->file("imagenes"), $request->input("alts", []));

                return Anuncio::with(["imagenes", "destinos"])->find($anuncio->id);
            });

            // se avisa DESPUES del commit: si se dispara dentro y la
            // transaccion falla, los clientes consultarian un anuncio
            // que nunca existio
            if ($data->activo) {
                event(new AnuncioEvent());
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editAnuncio(Request $request, $id)
    {
        try {
            $validacion = $this->validarCabecera($request);
            if ($validacion !== null) {
                return response()->json(RespuestaApi::returnResultado('error', $validacion, null));
            }

            $data = DB::transaction(function () use ($request, $id) {

                $anuncio = Anuncio::findOrFail($id);

                $anuncio->update([
                    "titulo"       => $request->input("titulo"),
                    "descripcion"  => $request->input("descripcion"),
                    "fecha_inicio" => $request->input("fecha_inicio"),
                    "fecha_fin"    => $request->input("fecha_fin"),
                    "activo"       => $request->boolean("activo", true),
                    "ver_todos"    => $request->boolean("ver_todos", false),
                    "orden"        => (int) $request->input("orden", 0),
                ]);

                // los destinos se reemplazan completos: es mas simple y evita
                // tener que calcular altas y bajas desde el frontend
                if ($request->has("destinos")) {
                    AnuncioDestino::where("anuncio_id", $anuncio->id)->delete();
                    $this->guardarDestinos($anuncio, $request);
                }

                // las imagenes NO se tocan aqui: tienen sus propios endpoints
                return Anuncio::with(["imagenes", "destinos"])->find($anuncio->id);
            });

            // Se avisa tambien al editar, no solo al crear: si se agregan
            // destinatarios nuevos (otro departamento, usuarios sueltos), a
            // esos les tiene que llegar en el momento.
            //
            // Reenviarlo a quien ya lo vio no molesta: el aviso no lleva
            // datos, el cliente reconsulta y solo notifica lo que no tenia.
            // Y si el anuncio se desactivo, la reconsulta se lo quita a todos.
            event(new AnuncioEvent());

            return response()->json(RespuestaApi::returnResultado('success', 'Se edito con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function deleteAnuncio($id)
    {
        try {
            $data = DB::transaction(function () use ($id) {
                $anuncio = Anuncio::with("imagenes")->findOrFail($id);

                // se borran los archivos uno por uno y no la carpeta completa:
                // si el anuncio es viejo puede tener imagenes guardadas antes
                // de que existiera la carpeta por anuncio
                foreach ($anuncio->imagenes as $imagen) {
                    $this->disco()->delete($imagen->ruta);
                }
                $this->disco()->deleteDirectory(self::CARPETA . "/" . $anuncio->id);

                // imagenes, destinos y vistos caen por ON DELETE CASCADE
                $anuncio->delete();

                return $anuncio;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------
    // Imagenes
    // ------------------------------------------------------------------

    public function addImagenes(Request $request, $anuncio_id)
    {
        try {
            $data = DB::transaction(function () use ($request, $anuncio_id) {
                $anuncio = Anuncio::findOrFail($anuncio_id);

                $this->guardarImagenes($anuncio, $request->file("imagenes"), $request->input("alts", []));

                return Anuncio::with("imagenes")->find($anuncio->id);
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function deleteImagen($id)
    {
        $ruta = '';
        try {
            $data = DB::transaction(function () use ($id, &$ruta) {
                $imagen = AnuncioImagen::findOrFail($id);
                $ruta = $imagen->ruta;
                $imagen->delete();

                return $imagen;
            });

            // el archivo se borra despues de que la transaccion confirmo:
            // si se borra antes y el commit falla, queda el registro sin archivo
            $this->disco()->delete($ruta);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * Recibe [{id, orden}, ...] y reordena sin volver a subir nada.
     */
    public function reordenarImagenes(Request $request, $anuncio_id)
    {
        try {
            $data = DB::transaction(function () use ($request, $anuncio_id) {

                $ordenes = $this->comoArreglo($request->input("imagenes"));

                foreach ($ordenes as $item) {
                    AnuncioImagen::where("id", $item["id"] ?? 0)
                        ->where("anuncio_id", $anuncio_id)
                        ->update(["orden" => (int) ($item["orden"] ?? 0)]);
                }

                return Anuncio::with("imagenes")->find($anuncio_id);
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se reordeno con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------
    // Apoyo
    // ------------------------------------------------------------------

    /**
     * El disco no es fijo: depende de la bandera NAS en crm.parametro,
     * igual que en Archivos2Controller y ChatController.
     */
    private function disco()
    {
        $parametro = DB::table('crm.parametro')->where('abreviacion', 'NAS')->first();

        return ($parametro && $parametro->nas == true)
            ? Storage::disk('nas')
            : Storage::disk('local');
    }

    private function guardarDestinos(Anuncio $anuncio, Request $request): void
    {
        if ($anuncio->ver_todos) {
            // con ver_todos la tabla de destinos se ignora en la consulta:
            // guardarlos solo dejaria basura que confunde al editar
            return;
        }

        foreach ($this->comoArreglo($request->input("destinos")) as $destino) {
            $tipo = $destino["tipo"] ?? null;

            if (!AnuncioDestino::tipoValido($tipo)) {
                // sin CHECK en la tabla, este es el unico filtro que existe
                throw new Exception("Tipo de destino no valido: " . json_encode($tipo));
            }

            AnuncioDestino::create([
                "anuncio_id" => $anuncio->id,
                "tipo"       => $tipo,
                "destino_id" => (int) ($destino["destino_id"] ?? 0),
            ]);
        }
    }

    /**
     * El orden de las imagenes es el orden del arreglo que manda el
     * frontend, que es el que el usuario dejo arrastrando las miniaturas.
     */
    private function guardarImagenes(Anuncio $anuncio, $archivos, $alts): void
    {
        if (empty($archivos)) {
            return;
        }

        $disco = $this->disco();
        $carpeta = self::CARPETA . "/" . $anuncio->id;

        // continua la numeracion si el anuncio ya tenia imagenes
        $orden = (int) AnuncioImagen::where("anuncio_id", $anuncio->id)->max("orden");

        foreach ($archivos as $indice => $archivo) {

            if (!$archivo->isValid()) {
                throw new Exception("Archivo invalido en la posicion " . $indice);
            }
            if (strpos((string) $archivo->getMimeType(), "image/") !== 0) {
                throw new Exception("Solo se permiten imagenes. Recibido: " . $archivo->getMimeType());
            }
            if ($archivo->getSize() > self::MAX_KB_IMAGEN * 1024) {
                throw new Exception("La imagen supera los " . (self::MAX_KB_IMAGEN / 1024) . " MB");
            }

            $nombreLimpio = str_replace(' ', '-', $archivo->getClientOriginalName());
            // uniqid ademas de la carpeta por anuncio: putFileAs sobrescribe
            // sin avisar, y dos "banner.jpg" el mismo dia se pisaban
            $nombre = Carbon::now()->format('Y-m-d') . '-' . uniqid() . '-' . $nombreLimpio;

            $ruta = $disco->putFileAs($carpeta, $archivo, $nombre);

            AnuncioImagen::create([
                "anuncio_id" => $anuncio->id,
                "ruta"       => $ruta,
                "alt"        => $alts[$indice] ?? null,
                "orden"      => ++$orden,
            ]);
        }
    }

    /** acepta arreglo nativo o cadena JSON, porque en multipart llega texto */
    private function comoArreglo($valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }
        if (is_string($valor) && $valor !== '') {
            $decodificado = json_decode($valor, true);
            return is_array($decodificado) ? $decodificado : [];
        }
        return [];
    }

    private function validarCabecera(Request $request): ?string
    {
        if (!$request->filled("titulo")) {
            return "El título es obligatorio";
        }
        if (!$request->filled("fecha_inicio") || !$request->filled("fecha_fin")) {
            return "Las fechas de inicio y fin son obligatorias";
        }
        if (strtotime($request->input("fecha_fin")) < strtotime($request->input("fecha_inicio"))) {
            return "La fecha fin no puede ser anterior a la fecha inicio";
        }
        return null;
    }
}

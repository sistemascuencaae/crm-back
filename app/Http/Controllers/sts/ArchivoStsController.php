<?php

namespace App\Http\Controllers\sts;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\Galeria;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ArchivoStsController extends Controller
{
    public function __construct()
    {
        // Rate limit: máximo 20 requests por minuto por IP en 'addArchivos'.
        // Si se supera, Laravel responde 429 Too Many Requests automáticamente.
        $this->middleware('throttle:20,1')->only('addArchivos');
        // Listar es más liviano, permitimos más requests por minuto.
        $this->middleware('throttle:60,1')->only('listArchivos');
        // Delete: limitado pero más permisivo que upload.
        $this->middleware('throttle:30,1')->only('deleteArchivo');
    }



    public function addArchivos(Request $request)
    {
        // ===== 1) VALIDACIÓN DE INPUT =====
        // - usuario_netos: requerido y string (el saneo se hace después).
        // - archivos: array con al menos 1 elemento.
        // - cada archivo: debe ser file real, con extensión/MIME permitida.
        $validator = Validator::make($request->all(), [
            'usuario_netos' => 'required|string',
            'archivos' => 'required|array|min:1',
            // 'archivos.*' => 'required|file|mimes:jpeg,jpg,png,gif,webp,bmp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip,rar',
            'archivos.*' => 'required|file|mimes:pdf',
        ], [
            'usuario_netos.required' => 'El usuario es incorrecto.',
            'usuario_netos.string' => 'El usuario es incorrecto.',
            'archivos.required' => 'Debe enviar al menos un archivo.',
            'archivos.*.file' => 'Uno de los items no es un archivo válido.',
            'archivos.*.mimes' => 'Solo se admiten archivos: :values.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null), 422);
        }

        // ===== 2) SANITIZACIÓN DE usuario_netos =====
        // Reemplaza caracteres peligrosos (/, \, :, *, espacios, etc.) por '-'.
        // Si luego del saneo queda vacío → el input era basura → rechazamos.
        $usuarioNetos = $this->sanitizarSegmento($request->input('usuario_netos'));
        if ($usuarioNetos === '') {
            return response()->json(RespuestaApi::returnResultado('error', 'El usuario es incorrecto.', null), 422);
        }

        // ===== 3) VALIDACIÓN DE TAMAÑO TOTAL =====
        // Sumamos el size de todos los archivos. Si supera 100MB, cortamos antes
        // de tocar el NAS para no escribir nada que vamos a tener que rollbackear.
        $limiteBytes = 100 * 1024 * 1024;
        $totalBytes = 0;
        foreach ($request->file('archivos') as $archivoData) {
            $totalBytes += $archivoData->getSize();
        }
        if ($totalBytes > $limiteBytes) {
            $totalMb = round($totalBytes / 1024 / 1024, 2);
            return response()->json(RespuestaApi::returnResultado('error', "El total de los archivos ({$totalMb}MB) supera el límite de 100MB.", null), 422);
        }

        // ===== 4) DECIDIR DISCO (NAS o LOCAL) =====
        // Según la tabla parametro, se puede alternar entre NAS y disco local.
        $parametro = DB::table('crm.parametro')->where('abreviacion', 'NAS')->first();
        $disk = ($parametro && $parametro->nas) ? 'nas' : 'local';

        // ===== 5) PROCESAMIENTO DE ARCHIVOS =====
        // Usamos transacción MANUAL (beginTransaction/commit/rollBack) para poder,
        // si algo falla, borrar del NAS lo que ya se había escrito.
        // $pathsEscritos guarda los archivos creados para limpiarlos en el catch.
        $pathsEscritos = [];
        DB::beginTransaction();
        try {
            $archivosGuardados = [];
            $galeriasGuardadas = [];

            foreach ($request->file('archivos') as $archivoData) {
                // Nombre seguro: basename + whitelist regex + fallback "archivo".
                $nombreSeguro = $this->sanitizarNombre($archivoData->getClientOriginalName());

                // Prefijo con fecha/hora para evitar colisiones entre uploads.
                $nombreUnico = Carbon::now()->format('Y-m-d_His') . '-' . $nombreSeguro;

                // Clasificación por MIME: si arranca con "image/" → galería, sino → archivo.
                $esImagen = str_starts_with($archivoData->getMimeType() ?? '', 'image/');
                $carpeta = $esImagen ? 'galerias' : 'archivos';

                // Guarda el binario físico en sts/<usuario_netos>/<galerias|archivos>/<nombre>
                $path = Storage::disk($disk)->putFileAs("sts/{$usuarioNetos}/{$carpeta}", $archivoData, $nombreUnico);

                // Si putFileAs devuelve false, el write falló → disparamos exception
                // para que el catch haga rollback y limpie lo anterior.
                if ($path === false) {
                    throw new \RuntimeException("Falló al guardar {$nombreSeguro}.");
                }

                // Lo agregamos a la lista para poder limpiar si luego algo falla.
                $pathsEscritos[] = $path;

                // Inserción en la tabla correspondiente según tipo.
                if ($esImagen) {
                    $galeriasGuardadas[] = Galeria::create([
                        'titulo' => $nombreUnico,
                        'descripcion' => $nombreSeguro,
                        'imagen' => $path,
                        'tipo_gal_id' => 12, // tipo "STS"
                        'usuario_netos' => $usuarioNetos,
                    ]);
                } else {
                    $archivosGuardados[] = Archivo::create([
                        'titulo' => $nombreUnico,
                        'observacion' => $nombreSeguro,
                        'archivo' => $path,
                        'caso_id' => null,
                        'tipo' => 'STS',
                        'usuario_netos' => $usuarioNetos,
                    ]);
                }

                // Auditoría: quién subió qué, desde qué IP, tamaño, MIME, path final.
                // Escribe en storage/logs/logsCrm.log (canal 'single' de tu logging.php).
                Log::info('STS archivo subido', [
                    'usuario_netos' => $usuarioNetos,
                    'user_id' => optional(auth()->user())->id,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'archivo' => $nombreSeguro,
                    'tipo' => $esImagen ? 'galeria' : 'archivo',
                    'mime' => $archivoData->getMimeType(),
                    'size_bytes' => $archivoData->getSize(),
                    'disk' => $disk,
                    'path' => $path,
                ]);
            }

            // Si llegamos acá sin excepciones, confirmamos la transacción en DB.
            DB::commit();

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', null));
        } catch (\Throwable $e) {
            // ===== 6) ROLLBACK COMPLETO =====
            // (a) DB: revertimos todos los Archivo::create / Galeria::create.
            DB::rollBack();

            // (b) FILESYSTEM: borramos del NAS lo que sí se llegó a escribir.
            // El try/catch interno es para que si un delete falla (ej. ya no existe),
            // no aborte el cleanup del resto.
            foreach ($pathsEscritos as $p) {
                try {
                    Storage::disk($disk)->delete($p);
                } catch (\Throwable $ignored) {
                }
            }

            // Log del error con los paths que se tuvieron que limpiar, para auditoría.
            Log::error('STS error al subir archivos', [
                'usuario_netos' => $usuarioNetos,
                'ip' => $request->ip(),
                'paths_limpiados' => $pathsEscritos,
                'mensaje' => $e->getMessage(),
            ]);

            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }



    public function listArchivos(Request $request)
    {
        // ===== 1) VALIDACIÓN =====
        $validator = Validator::make($request->all(), [
            'usuario_netos' => 'required|string',
        ], [
            'usuario_netos.required' => 'El usuario es incorrecto.',
            'usuario_netos.string' => 'El usuario es incorrecto.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()), 422);
        }

        // ===== 2) SANITIZAR — exact match con lo que se guardó en upload =====
        $usuarioNetos = $this->sanitizarSegmento($request->input('usuario_netos'));
        if ($usuarioNetos === '') {
            return response()->json(RespuestaApi::returnResultado('error', 'El usuario es incorrecto.', null), 422);
        }

        // ===== 3) QUERIES =====
        // URL pública del NAS.
        $baseUrl = 'http://img.almespana.com.ec:8582/crm/';

        try {
            $archivos = Archivo::where('usuario_netos', $usuarioNetos)
                ->where('tipo', 'STS')
                ->orderByDesc('id')
                ->get(['id', 'titulo', 'archivo', 'usuario_netos', 'created_at'])
                ->map(fn($a) => [
                    'id' => $a->id,
                    'titulo' => $a->titulo,
                    'archivo' => $baseUrl . $a->archivo,
                    'usuario_netos' => $a->usuario_netos,
                    'tipo' => 'archivo',
                    'created_at' => $a->created_at,
                ]);

            $imagenes = Galeria::where('usuario_netos', $usuarioNetos)
                ->where('tipo_gal_id', 12)
                ->orderByDesc('id')
                ->get(['id', 'titulo', 'imagen', 'usuario_netos', 'created_at'])
                ->map(fn($i) => [
                    'id' => $i->id,
                    'titulo' => $i->titulo,
                    'imagen' => $baseUrl . $i->imagen,
                    'usuario_netos' => $i->usuario_netos,
                    'tipo' => 'imagen',
                    'created_at' => $i->created_at,
                ]);

            // Omitimos cualquiera de los dos arrays si vino vacío.
            $data = [];
            if ($archivos->isNotEmpty()) {
                $data['archivos'] = $archivos;
            }
            if ($imagenes->isNotEmpty()) {
                $data['imagenes'] = $imagenes;
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Listado exitoso', $data));
        } catch (\Throwable $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }




    public function deleteArchivo(Request $request)
    {
        // ===== 1) VALIDACIÓN =====
        $validator = Validator::make($request->all(), [
            'usuario_netos' => 'required|string',
            'id' => 'required|integer|min:1',
            'tipo' => 'required|in:archivo,imagen',
        ], [
            'usuario_netos.required' => 'El usuario es incorrecto.',
            'usuario_netos.string' => 'El usuario es incorrecto.',
            'id.required' => 'Falta el id del archivo a eliminar.',
            'id.integer' => 'El id debe ser numérico.',
            'tipo.required' => 'Falta el tipo archivo.',
            'tipo.in' => 'El tipo debe ser archivo.',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), null), 422);
        }

        // ===== 2) SANITIZAR usuario_netos =====
        $usuarioNetos = $this->sanitizarSegmento($request->input('usuario_netos'));
        if ($usuarioNetos === '') {
            return response()->json(RespuestaApi::returnResultado('error', 'El usuario es incorrecto.', null), 422);
        }

        $id = (int) $request->input('id');
        $tipo = $request->input('tipo');

        // ===== 3) BUSCAR EL REGISTRO (filtrando por usuario_netos para seguridad) =====
        if ($tipo === 'archivo') {
            $registro = Archivo::where('id', $id)
                ->where('usuario_netos', $usuarioNetos)
                ->where('tipo', 'STS')
                ->first();
            $campoPath = 'archivo';
        } else {
            $registro = Galeria::where('id', $id)
                ->where('usuario_netos', $usuarioNetos)
                ->where('tipo_gal_id', 12)
                ->first();
            $campoPath = 'imagen';
        }

        if (!$registro) {
            return response()->json(RespuestaApi::returnResultado('error', 'Archivo no encontrado.', null), 404);
        }

        $path = $registro->{$campoPath};

        // ===== 4) DISCO A USAR =====
        $parametro = DB::table('crm.parametro')->where('abreviacion', 'NAS')->first();
        $disk = ($parametro && $parametro->nas) ? 'nas' : 'local';

        // ===== 5) BORRAR EN TRANSACCIÓN =====
        // DB primero. Si el borrado del disco falla, throw → rollback (el registro vuelve).
        DB::beginTransaction();
        try {
            $registro->delete();

            if (Storage::disk($disk)->exists($path)) {
                if (!Storage::disk($disk)->delete($path)) {
                    throw new \RuntimeException("No se pudo borrar el archivo del disco: {$path}");
                }
            }

            DB::commit();

            Log::info('STS archivo eliminado', [
                'usuario_netos' => $usuarioNetos,
                'user_id' => optional(auth()->user())->id,
                'ip' => $request->ip(),
                'tipo' => $tipo,
                'id' => $id,
                'path' => $path,
                'disk' => $disk,
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', null));
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('STS error al eliminar archivo', [
                'usuario_netos' => $usuarioNetos,
                'ip' => $request->ip(),
                'tipo' => $tipo,
                'id' => $id,
                'path' => $path,
                'mensaje' => $e->getMessage(),
            ]);

            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }

    /**
     * Sanea el nombre original de un archivo para que sea seguro en disco:
     * - basename() corta intentos de path traversal (../../etc).
     * - ltrim('.') evita archivos ocultos (.htaccess, .env).
     * - pathinfo separa base y extensión (la extensión se preserva en minúscula).
     * - sanitizarSegmento deja sólo [A-Za-z0-9._-], reemplaza el resto por '-'.
     * - trunca a 100 chars para evitar filenames gigantes.
     * - fallback "archivo" si después de todo quedó vacío.
     */
    private function sanitizarNombre(string $nombreOriginal): string
    {
        $nombre = basename($nombreOriginal);
        $nombre = ltrim($nombre, '.');

        $pi = pathinfo($nombre);
        $base = $pi['filename'] ?? '';
        $ext = isset($pi['extension']) ? '.' . strtolower($pi['extension']) : '';

        $base = $this->sanitizarSegmento($base);
        $base = substr($base, 0, 100);

        if ($base === '' || $base === false) {
            $base = 'archivo';
        }

        return $base . $ext;
    }

    /**
     * Helper genérico para convertir cualquier string en un "segmento seguro"
     * para usarse como carpeta o nombre de archivo.
     * - trim() quita espacios al inicio y final.
     * - Reemplaza cualquier caracter fuera de [A-Za-z0-9._-] por '-'
     *   (cubre los prohibidos de Windows: \ / : * ? " < > | y también espacios, emojis, etc.)
     * - Colapsa secuencias de '-' seguidos en uno solo.
     * - Limpia '-' y '.' en los bordes.
     */
    private function sanitizarSegmento(string $input): string
    {
        $s = trim($input);
        // Str::ascii convierte tildes/ñ a su equivalente sin acento:
        // "José" → "Jose", "niño" → "nino", "año" → "ano"
        $s = Str::ascii($s);
        $s = preg_replace('/[^A-Za-z0-9._-]/', '-', $s);
        $s = preg_replace('/-{2,}/', '-', $s);
        $s = trim($s, '.-');
        return $s ?? '';
    }
}

<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Controlador de Series para carga masiva desde Excel.
 *
 * Flujo del modulo:
 *   1) previewExcelSeries        -> Lee el Excel y devuelve sus filas como JSON.
 *   2) listTiposProductoDynamo   -> Llena el dropdown del modal de tipo de producto.
 *   3) validarColumnasProducto   -> Valida que las columnas del Excel coincidan con
 *                                   los parametros del tipo seleccionado.
 *   4) listProductosActivosByTprId -> Llena el ng-select de productos filtrados.
 *   5) addSeriesProductos        -> Inserta los registros en la tabla series.
 */
class SeriesController extends Controller
{
    // Tope maximo de filas por lote (proteccion contra archivos enormes).
    private const MAX_FILAS = 5000;

    /**
     * Lista los tipos de producto activos que se mostraran en el modal.
     * Excluye los marcados como tpr_reporta = 209 (regla de negocio existente).
     *
     * @return \Illuminate\Http\JsonResponse  data: [{tpr_id, tpr_nombre}, ...]
     */
    public function listTiposProductoDynamo()
    {
        try {
            $data = DB::select("SELECT tpr_id, tpr_nombre
                                FROM public.tipo_producto
                                WHERE tpr_activo = true AND tpr_reporta <> 209
                                ORDER BY tpr_nombre ASC");
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    /**
     * Lista los productos activos pertenecientes a un tipo de producto especifico.
     * Se invoca desde el frontend solo despues de que se valido el tpr_id en el modal.
     *
     * @param  int|string $tpr_id ID del tipo de producto (viene como parametro de URL).
     * @return \Illuminate\Http\JsonResponse  data: [{pro_id, producto}, ...]
     */
    public function listProductosActivosByTprId($tpr_id)
    {
        try {
            // Validacion basica del tipo: debe ser numero positivo.
            if (!is_numeric($tpr_id) || (int) $tpr_id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'tpr_id invalido.', []), 422);
            }

            // CONCAT(codigo, ' - ', nombre) -> facilita la busqueda en el ng-select del frontend.
            $data = DB::select("SELECT p.pro_id, CONCAT(p.pro_codigo, ' - ', p.pro_nombre) AS producto
                                FROM public.producto p
                                WHERE p.pro_activo = true
                                    AND p.tpr_id = ?
                                ORDER BY p.pro_codigo ASC", [(int) $tpr_id]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    /**
     * Lista todos los lotes de series cargados, agrupados por codigo_lote.
     * Cada lote incluye: usuario que lo subio, tipo de producto, observacion,
     * cantidad de registros y fecha del primer registro del lote.
     *
     * @return \Illuminate\Http\JsonResponse  data: [{codigo_lote, user_id, usuario, tpr_id, tpr_nombre, observacion, total_registros, fecha}, ...]
     */
    public function listLotesSeries()
    {
        try {
            $data = DB::select("SELECT
                                    s.codigo_lote,
                                    s.user_id,
                                    concat(u.usu_alias, ' - ', u.name, ' ', u.surname) AS usuario,
                                    s.tpr_id,
                                    tp.tpr_nombre,
                                    s.observacion,
                                    COUNT(*) AS total_registros,
                                    TO_CHAR(MIN(s.created_at), 'DD/MM/YYYY HH24:MI') AS fecha
                                FROM public.series s
                                    LEFT JOIN crm.users u ON u.id = s.user_id
                                    LEFT JOIN public.tipo_producto tp ON tp.tpr_id = s.tpr_id
                                GROUP BY s.codigo_lote, s.user_id, u.usu_alias, u.name, u.surname,
                                         s.tpr_id, tp.tpr_nombre, s.observacion
                                ORDER BY MIN(s.created_at) DESC");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    /**
     * Devuelve el array de columnas (en orden) definido en parametros_tipo_producto
     * para un tipo de producto. Reutiliza el mismo parseo que la validacion.
     *
     * @param  int $tprId
     * @return array  Ej: ['MARCA', 'Modelo', 'Procesador']
     */
    private function columnasDelTipoProducto(int $tprId): array
    {
        // NO se filtra por estado a proposito: para VER/DESCARGAR lotes historicos
        // necesitamos los nombres de columna aunque el parametro ya este desactivado.
        // ORDER BY estado DESC, id DESC -> determinista: prefiere el activo y, si
        // hubiera mas de uno (no deberia), el mas reciente.
        $row = DB::select(
            "SELECT string_to_array(
                        replace(replace(replace(valor, '[', ''), ']', ''), '''', ''),
                        ','
                    ) AS columnas_array
             FROM public.parametros_tipo_producto
             WHERE tpr_id = ?
             ORDER BY estado DESC, id DESC
             LIMIT 1",
            [$tprId]
        );

        if (empty($row)) {
            return [];
        }
        return $this->pgArrayToPhp($row[0]->columnas_array ?? '{}');
    }

    /**
     * Devuelve los registros de un lote junto con el encabezado (nombres reales
     * de las columnas tomados del parametro del tipo de producto del lote).
     *
     * @param  string $codigo_lote  UUID del lote.
     * @return \Illuminate\Http\JsonResponse  data: { encabezado, filas, lote }
     */
    public function getSeriesByLote($codigo_lote)
    {
        try {
            $codigoLote = trim((string) $codigo_lote);
            if ($codigoLote === '' || !preg_match('/^[0-9a-fA-F\-]{36}$/', $codigoLote)) {
                return response()->json(RespuestaApi::returnResultado('error', 'codigo_lote invalido.', []), 422);
            }

            // tpr_id del lote (todas las filas comparten el mismo).
            $cab = DB::select(
                "SELECT s.tpr_id, tp.tpr_nombre, s.observacion
                 FROM public.series s
                 LEFT JOIN public.tipo_producto tp ON tp.tpr_id = s.tpr_id
                 WHERE s.codigo_lote = ?
                 LIMIT 1",
                [$codigoLote]
            );

            if (empty($cab)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El lote no existe.', []), 404);
            }

            $tprId    = (int) $cab[0]->tpr_id;
            $columnas = $this->columnasDelTipoProducto($tprId);
            $totalCol = count($columnas);

            // Encabezado: campo1..campoN -> nombre real del parametro.
            $encabezado = [];
            for ($i = 0; $i < $totalCol; $i++) {
                $encabezado[] = [
                    'campo'  => 'campo' . ($i + 1),
                    'titulo' => $columnas[$i],
                ];
            }

            // Traer las filas del lote con el nombre del producto.
            $filas = DB::select(
                "SELECT s.*, CONCAT(p.pro_codigo, ' - ', p.pro_nombre) AS producto_nombre
                 FROM public.series s
                 LEFT JOIN public.producto p ON p.pro_id = s.pro_id
                 WHERE s.codigo_lote = ?
                 ORDER BY s.id ASC",
                [$codigoLote]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito.', [
                'encabezado' => $encabezado,
                'filas'      => $filas,
                'lote'       => [
                    'codigo_lote' => $codigoLote,
                    'tpr_id'      => $tprId,
                    'tpr_nombre'  => $cab[0]->tpr_nombre,
                    'observacion' => $cab[0]->observacion,
                ],
            ]));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    /**
     * Genera y descarga un .xlsx con todos los registros del lote.
     * Columnas: las del parametro (en orden) + Producto + Observacion + Fecha.
     *
     * @param  string $codigo_lote  UUID del lote.
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function downloadLoteSeries($codigo_lote)
    {
        try {
            $codigoLote = trim((string) $codigo_lote);
            if ($codigoLote === '' || !preg_match('/^[0-9a-fA-F\-]{36}$/', $codigoLote)) {
                return response()->json(RespuestaApi::returnResultado('error', 'codigo_lote invalido.', []), 422);
            }

            $cab = DB::select(
                "SELECT tpr_id FROM public.series WHERE codigo_lote = ? LIMIT 1",
                [$codigoLote]
            );
            if (empty($cab)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El lote no existe.', []), 404);
            }

            $tprId    = (int) $cab[0]->tpr_id;
            $columnas = $this->columnasDelTipoProducto($tprId);
            $totalCol = count($columnas);

            $filas = DB::select(
                "SELECT s.*, CONCAT(p.pro_codigo, ' - ', p.pro_nombre) AS producto_nombre
                 FROM public.series s
                 LEFT JOIN public.producto p ON p.pro_id = s.pro_id
                 WHERE s.codigo_lote = ?
                 ORDER BY s.id ASC",
                [$codigoLote]
            );

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $hoja = $spreadsheet->getActiveSheet();

            // API moderna de PhpSpreadsheet: setCellValue([col, row], valor).
            // (setCellValueByColumnAndRow fue removido en versiones nuevas).
            // Encabezados: columnas del parametro + Producto + Fecha.
            $colIdx = 1;
            for ($i = 0; $i < $totalCol; $i++) {
                $hoja->setCellValue([$colIdx++, 1], (string) $columnas[$i]);
            }
            $hoja->setCellValue([$colIdx++, 1], 'Producto');
            $hoja->setCellValue([$colIdx, 1], 'Fecha');

            // Filas de datos.
            $fila = 2;
            foreach ($filas as $r) {
                $c = 1;
                for ($i = 0; $i < $totalCol; $i++) {
                    $key = 'campo' . ($i + 1);
                    $hoja->setCellValue([$c++, $fila], (string) ($r->$key ?? ''));
                }
                $hoja->setCellValue([$c++, $fila], (string) ($r->producto_nombre ?? ''));
                $hoja->setCellValue([$c, $fila], (string) ($r->created_at ?? ''));
                $fila++;
            }

            $nombreArchivo = 'lote_' . substr($codigoLote, 0, 8) . '.xlsx';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            // Generar el binario en un buffer DENTRO del try para que cualquier
            // error quede capturado (streamDownload se ejecuta fuera del try/catch).
            ob_start();
            $writer->save('php://output');
            $contenido = ob_get_clean();

            // Liberar memoria del spreadsheet.
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $writer);

            return response($contenido, 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
                'Content-Length'      => strlen($contenido),
            ]);

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    /**
     * Elimina TODOS los registros de un lote (todas las filas con el mismo codigo_lote).
     * Operacion destructiva e irreversible.
     *
     * @param  string $codigo_lote  UUID del lote (parametro de URL).
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteLoteSeries($codigo_lote)
    {
        try {
            $codigoLote = trim((string) $codigo_lote);

            // Validacion basica del formato UUID (proteccion contra valores arbitrarios).
            if ($codigoLote === '' || !preg_match('/^[0-9a-fA-F\-]{36}$/', $codigoLote)) {
                return response()->json(RespuestaApi::returnResultado('error', 'codigo_lote invalido.', []), 422);
            }

            // Verificar que el lote exista antes de intentar borrar.
            $existe = DB::select(
                "SELECT 1 AS ok FROM public.series WHERE codigo_lote = ? LIMIT 1",
                [$codigoLote]
            );
            if (empty($existe)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El lote no existe o ya fue eliminado.', []), 404);
            }

            // Borrado dentro de transaccion. delete() devuelve el numero de filas afectadas.
            $eliminados = 0;
            DB::transaction(function () use ($codigoLote, &$eliminados) {
                $eliminados = DB::table('series')->where('codigo_lote', $codigoLote)->delete();
            });

            return response()->json(RespuestaApi::returnResultado('success', "Se elimino el lote ($eliminados registros).", [
                'eliminados'  => $eliminados,
                'codigo_lote' => $codigoLote,
            ]));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    /**
     * Valida los encabezados del Excel contra los parametros definidos para el tipo de producto.
     *
     * Tres niveles de validacion:
     *   1) Existencia del parametro (parametros_tipo_producto.tpr_id).
     *   2) Cantidad de columnas: deben coincidir exacto.
     *   3) Nombres: invoca a la funcion SQL crm.validar_columnas_producto (case-insensitive).
     *
     * Si todo OK -> devuelve el orden correcto de las columnas para que el frontend
     * reordene el preview y guarde los datos alineados al parametro.
     *
     * @param  Request $request  body: { tpr_id: int, headers: string[] }
     * @return \Illuminate\Http\JsonResponse
     */
    public function validarColumnasProducto(Request $request)
    {
        try {
            $tprId   = $request->input('tpr_id');
            $headers = $request->input('headers');

            // Validacion de inputs basicos.
            if (!is_numeric($tprId) || (int) $tprId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'tpr_id es requerido.', []), 422);
            }
            if (!is_array($headers) || empty($headers)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Los headers del Excel son requeridos.', []), 422);
            }

            // 1) Buscar el parametro ACTIVO definido para este tpr_id y extraer su array.
            //    El campo `valor` se guarda como string tipo "['col1','col2','col3']".
            //    Se limpia con replaces y se hace split por coma usando string_to_array.
            //    Para CARGA NUEVA solo valen parametros con estado = true; si esta
            //    desactivado se trata como inexistente (no se permite cargar contra el).
            //    ORDER BY id DESC -> determinista si hubiera mas de uno (no deberia).
            $parametroInfo = DB::select(
                "SELECT string_to_array(
                            replace(replace(replace(valor, '[', ''), ']', ''), '''', ''),
                            ','
                        ) AS columnas_array
                 FROM public.parametros_tipo_producto
                 WHERE tpr_id = ? AND estado = true
                 ORDER BY id DESC
                 LIMIT 1",
                [(int) $tprId]
            );

            // Si no hay parametro ACTIVO para este tipo, no se puede validar nada.
            if (empty($parametroInfo)) {
                return response()->json(RespuestaApi::returnResultado('error',
                    'El parametro para este tipo de producto no existe. Por favor comuniquese con el administrador.',
                    ['sin_parametro' => true]
                ), 422);
            }

            // Convertir el array nativo de PostgreSQL ("{a,b,c}") a array PHP limpio.
            $columnasParamRaw = $parametroInfo[0]->columnas_array ?? '{}';
            $ordenColumnas    = $this->pgArrayToPhp($columnasParamRaw);

            // Normalizar (trim) cada header recibido para que las comparaciones sean fiables.
            $headersClean   = array_values(array_map(fn($h) => trim((string) $h), $headers));
            $totalEsperado  = count($ordenColumnas);
            $totalRecibido  = count($headersClean);

            // 2) La cantidad de columnas debe coincidir exacto con el parametro.
            if ($totalEsperado > 0 && $totalRecibido !== $totalEsperado) {
                return response()->json(RespuestaApi::returnResultado('error',
                    "El parametro espera $totalEsperado columna(s) pero el Excel tiene $totalRecibido. Corrige el archivo y vuelve a subirlo.",
                    [
                        'cantidad_incorrecta' => true,
                        'total_esperado'     => $totalEsperado,
                        'total_recibido'     => $totalRecibido,
                    ]
                ), 422);
            }

            // 3) Validar nombres llamando a la funcion SQL crm.validar_columnas_producto.
            //    La funcion devuelve TABLE(nombre TEXT, estado TEXT) con 'OK' o 'NO EXISTE'.
            $arrayLiteral = $this->toPgArrayLiteral($headersClean);
            $resultado = DB::select(
                "SELECT nombre, estado FROM crm.validar_columnas_producto(?::int, ?::text[])",
                [(int) $tprId, $arrayLiteral]
            );

            // Recolectar las columnas que NO existen para reportarlas al usuario.
            $noExisten = [];
            foreach ($resultado as $r) {
                $estado = strtoupper(trim((string) ($r->estado ?? '')));
                if ($estado !== 'OK') {
                    $noExisten[] = (string) ($r->nombre ?? '');
                }
            }

            if (!empty($noExisten)) {
                return response()->json(RespuestaApi::returnResultado('error',
                    'Las siguientes columnas no existen para el tipo de producto seleccionado: ' . implode(', ', $noExisten),
                    [
                        'validacion'        => $resultado,
                        'columnasNoExisten' => $noExisten,
                    ]
                ), 422);
            }

            // Exito: devolvemos tambien el orden correcto para que el frontend reordene el preview.
            return response()->json(RespuestaApi::returnResultado('success', 'Todas las columnas son validas.', [
                'validacion'     => $resultado,
                'orden_columnas' => $ordenColumnas,
            ]));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    /**
     * Convierte el literal de array nativo de PostgreSQL a array PHP.
     * Ejemplos: "{a,b,c}" o "{\"a\",\"b,c\"}" -> ['a','b','c'] / ['a','b,c']
     *
     * Usa str_getcsv porque maneja correctamente valores entre comillas con
     * comas internas (que pueden venir de PostgreSQL).
     */
    private function pgArrayToPhp(string $pgArray): array
    {
        $s = trim($pgArray);
        if ($s === '' || $s === '{}') return [];

        // Quitar las llaves de inicio/fin del literal pg.
        if ($s[0] === '{') $s = substr($s, 1);
        if (substr($s, -1) === '}') $s = substr($s, 0, -1);

        // Parsear como CSV (respeta comillas dobles y backslash como escape).
        $values = str_getcsv($s, ',', '"', '\\');

        // Trim de cada valor y descartar vacios.
        return array_values(array_filter(
            array_map(fn($v) => trim((string) $v), $values),
            fn($v) => $v !== ''
        ));
    }

    /**
     * Convierte un array PHP al formato literal de array PostgreSQL: {"a","b","c"}
     * Escapa backslashes y comillas dobles internas. Se usa para pasar arrays
     * como parametros con cast ::text[].
     */
    private function toPgArrayLiteral(array $values): string
    {
        $escaped = array_map(function ($v) {
            $s = (string) $v;
            // El orden importa: primero backslash (porque escapar la comilla agrega backslash).
            $s = str_replace('\\', '\\\\', $s);
            $s = str_replace('"', '\\"', $s);
            return '"' . $s . '"';
        }, $values);
        return '{' . implode(',', $escaped) . '}';
    }

    /**
     * Lee un archivo Excel (.xlsx, .xls o .csv) y devuelve sus filas como JSON.
     * Mapea cada columna en orden a "campo1", "campo2", ..., "campoN".
     *
     * @param  Request $request  multipart/form-data con el campo "archivo".
     * @return \Illuminate\Http\JsonResponse  data: { encabezado, filas, totalFilas, totalColumnas }
     */
    public function previewExcelSeries(Request $request)
    {
        try {
            // No permitir array de archivos: solo UN archivo en el campo "archivo".
            $rawArchivo = $request->file('archivo');
            if (is_array($rawArchivo)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Solo se permite UN archivo en el campo "archivo".', []), 422);
            }

            // Validacion estandar de Laravel: tipo de archivo y tamano maximo (10 MB).
            $validator = Validator::make(
                $request->all(),
                ['archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240'],
                [
                    'archivo.required' => 'Debes adjuntar un archivo en el campo "archivo".',
                    'archivo.file'     => 'El campo "archivo" debe ser un archivo valido.',
                    'archivo.mimes'    => 'El archivo debe ser .xlsx, .xls o .csv.',
                    'archivo.max'      => 'El archivo no puede exceder 10 MB.',
                ]
            );

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first('archivo'), ['errores' => $validator->errors()->all()]), 422);
            }

            $archivo = $request->file('archivo');

            // Verificacion adicional: que el archivo se haya subido sin corrupcion.
            if (!$archivo->isValid()) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo no se subio correctamente. Vuelve a intentarlo.', []), 422);
            }

            // Archivos de 0 bytes no son procesables.
            if ($archivo->getSize() === 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo esta vacio.', []), 422);
            }

            // Cargar el archivo con PhpSpreadsheet y convertir la hoja activa a array PHP.
            // Parametros de toArray:
            //   - null: valor para celdas vacias.
            //   - true: incluir celdas vacias (necesario para que cuadre el indice).
            //   - false: NO formatear (importante para que las fechas no se reordenen segun locale).
            //   - false: NO usar referencias de celda (devuelve indices 0..N).
            $spreadsheet = IOFactory::load($archivo->getRealPath());
            $hoja        = $spreadsheet->getActiveSheet();
            $filas       = $hoja->toArray(null, true, false, false);

            // Debe haber al menos encabezado (fila 1) + 1 fila de datos.
            if (count($filas) < 2) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo no contiene filas de datos.', []), 422);
            }

            // Extraer la fila de encabezado del array (queda $filas con solo los datos).
            $encabezadoRaw = array_shift($filas);
            $totalColumnas = count($encabezadoRaw);

            // Construir el array de encabezado: [{campo: 'campo1', titulo: 'Marca'}, ...]
            // Si el titulo viene vacio se pone "Columna N" como fallback.
            $encabezado = [];
            for ($i = 0; $i < $totalColumnas; $i++) {
                $titulo = trim((string) ($encabezadoRaw[$i] ?? ''));
                $encabezado[] = [
                    'campo'  => 'campo' . ($i + 1),
                    'titulo' => $titulo !== '' ? $titulo : 'Columna ' . ($i + 1),
                ];
            }

            // Procesar cada fila de datos: saltar las completamente vacias y limitar al maximo.
            $datos = [];
            foreach ($filas as $fila) {
                if ($this->filaVacia($fila)) {
                    continue;
                }
                $row = [];
                for ($i = 0; $i < $totalColumnas; $i++) {
                    $valor = $fila[$i] ?? null;
                    // Convertir cada valor a string limpio, manteniendo nulls intactos.
                    $row['campo' . ($i + 1)] = $valor === null ? null : trim((string) $valor);
                }
                $datos[] = $row;

                // Cortar antes de exceder el tope de filas por lote.
                if (count($datos) >= self::MAX_FILAS) {
                    break;
                }
            }

            // Si despues de filtrar filas vacias no queda nada, error.
            if (empty($datos)) {
                return response()->json(RespuestaApi::returnResultado('error', 'El archivo no contiene filas con datos.', []), 422);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Preview generado correctamente.', [
                'encabezado'    => $encabezado,
                'filas'         => $datos,
                'totalFilas'    => count($datos),
                'totalColumnas' => $totalColumnas,
            ]));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    /**
     * Inserta en la tabla series las filas validadas en el frontend.
     *
     * Pasos:
     *   1) Validaciones de input (filas, tope, user autenticado, observacion, tpr_id).
     *   2) Defense-in-depth: chequear que todos los pro_id pertenezcan al tpr_id.
     *   3) Generar codigo_lote (UUID) comun para todas las filas del mismo guardado.
     *   4) Construir los registros y filtrar las que no tengan pro_id valido.
     *   5) Insertar en una transaccion en chunks de 500 para no saturar la BD.
     *
     * @param  Request $request  body: { filas: array, observacion: string, tpr_id: int }
     * @return \Illuminate\Http\JsonResponse
     */
    public function addSeriesProductos(Request $request)
    {
        try {
            // ----- 1) Validaciones de inputs ---------------------------------------
            $filas = $request->input('filas');

            if (!is_array($filas) || empty($filas)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No hay filas para guardar.', []), 422);
            }

            // Limite duro por lote (proteccion contra DoS por payload gigante).
            if (count($filas) > self::MAX_FILAS) {
                return response()->json(RespuestaApi::returnResultado('error', 'El lote excede el limite de ' . self::MAX_FILAS . ' filas.', []), 422);
            }

            // user_id se toma del JWT (la ruta tiene middleware jwt.auth).
            $userId = auth()->id();
            if (!$userId) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se pudo identificar al usuario autenticado.', []), 401);
            }

            // Observacion es OBLIGATORIA y maximo 255 caracteres (igual que en BD).
            $observacion = trim((string) $request->input('observacion', ''));
            if ($observacion === '') {
                return response()->json(RespuestaApi::returnResultado('error', 'La observacion es requerida.', []), 422);
            }
            if (mb_strlen($observacion) > 255) {
                return response()->json(RespuestaApi::returnResultado('error', 'La observacion no puede exceder 255 caracteres.', []), 422);
            }

            // tpr_id es OBLIGATORIO y debe ser numerico positivo.
            $tprId = $request->input('tpr_id');
            if (!is_numeric($tprId) || (int) $tprId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'tpr_id es requerido.', []), 422);
            }
            $tprId = (int) $tprId;

            // ----- 2) Defense-in-depth: validar pro_id vs tpr_id -------------------
            // El frontend filtra productos por tpr_id, pero un usuario malicioso podria
            // manipular el JSON. Aqui validamos contra la BD que todos los pro_id
            // realmente pertenezcan al tipo seleccionado.

            // Recolectar pro_ids UNICOS de todas las filas (sin duplicados).
            $proIdsRecibidos = [];
            foreach ($filas as $fila) {
                if (!is_array($fila)) continue;
                $pid = $fila['pro_id'] ?? null;
                if (is_numeric($pid) && (int) $pid > 0) {
                    $proIdsRecibidos[(int) $pid] = true;
                }
            }
            $proIdsRecibidos = array_keys($proIdsRecibidos);

            if (!empty($proIdsRecibidos)) {
                // UNA sola query para validar todos los IDs (independiente del numero de filas).
                $placeholders = rtrim(str_repeat('?,', count($proIdsRecibidos)), ',');
                $rowsValidos = DB::select(
                    "SELECT pro_id FROM public.producto
                     WHERE pro_activo = true AND tpr_id = ? AND pro_id IN ($placeholders)",
                    array_merge([$tprId], $proIdsRecibidos)
                );
                $proIdsValidos   = array_map(fn($r) => (int) $r->pro_id, $rowsValidos);
                $proIdsInvalidos = array_values(array_diff($proIdsRecibidos, $proIdsValidos));

                // Si hay alguno que no esta en la BD para ese tipo -> rechazar todo.
                if (!empty($proIdsInvalidos)) {
                    return response()->json(RespuestaApi::returnResultado('error',
                        'Hay productos que no pertenecen al tipo seleccionado o estan inactivos: ' . implode(', ', $proIdsInvalidos),
                        ['proIdsInvalidos' => $proIdsInvalidos]
                    ), 422);
                }
            }

            // ----- 3) Generar identificadores del lote -----------------------------
            $codigoLote = (string) Str::uuid();

            // Forzar timezone de Ecuador para que created_at/updated_at queden consistentes.
            date_default_timezone_set('America/Guayaquil');
            $now = Carbon::now()->toDateTimeString();

            // ----- 4) Construir registros a insertar -------------------------------
            $insertar = [];
            $filasSinProducto = 0;
            foreach ($filas as $fila) {
                if (!is_array($fila)) {
                    continue;
                }

                // Filas sin pro_id valido se descartan (se contabilizan para el mensaje final).
                $proId = $fila['pro_id'] ?? null;
                if (!is_numeric($proId) || (int) $proId <= 0) {
                    $filasSinProducto++;
                    continue;
                }

                // Datos comunes para cada registro del lote.
                $registro = [
                    'codigo_lote' => $codigoLote,
                    'user_id'     => $userId,
                    'tpr_id'      => $tprId,
                    'pro_id'      => (int) $proId,
                    'observacion' => $observacion,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];

                // Copiar los campos campo1..campoN dinamicamente (whitelist con regex).
                // Esto evita inyectar columnas arbitrarias desde el frontend.
                $tieneValor = false;
                foreach ($fila as $key => $valor) {
                    if (!is_string($key) || !preg_match('/^campo\d+$/', $key)) {
                        continue;
                    }
                    if ($valor !== null && $valor !== '') {
                        $registro[$key] = (string) $valor;
                        $tieneValor = true;
                    }
                }

                // Solo agregar la fila si tiene al menos un campo con valor.
                if ($tieneValor) {
                    $insertar[] = $registro;
                }
            }

            // Si despues de filtrar no hay nada que insertar, error con mensaje claro.
            if (empty($insertar)) {
                $msg = $filasSinProducto > 0
                    ? 'Ninguna fila tiene producto asignado (pro_id).'
                    : 'Todas las filas estan vacias.';
                return response()->json(RespuestaApi::returnResultado('error', $msg, []), 422);
            }

            // ----- 5) Insertar en transaccion por chunks ---------------------------
            // chunks de 500 evitan exceder el limite de parametros de Postgres (~65k).
            $insertados = 0;
            DB::transaction(function () use ($insertar, &$insertados) {
                foreach (array_chunk($insertar, 500) as $chunk) {
                    DB::table('series')->insert($chunk);
                    $insertados += count($chunk);
                }
            });

            // Devolver el codigo_lote para que el frontend pueda referenciarlo si quiere.
            return response()->json(RespuestaApi::returnResultado('success', "Se guardaron $insertados registros correctamente.", [
                'insertados'  => $insertados,
                'codigo_lote' => $codigoLote,
                'user_id'     => $userId,
            ]));

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', $e->getMessage(), []));
        }
    }

    /**
     * Determina si una fila esta completamente vacia (todos sus valores null o solo espacios).
     * Se usa para descartar filas inutiles del Excel.
     */
    private function filaVacia(array $valores): bool
    {
        foreach ($valores as $v) {
            if ($v !== null && trim((string) $v) !== '') {
                return false;
            }
        }
        return true;
    }
}

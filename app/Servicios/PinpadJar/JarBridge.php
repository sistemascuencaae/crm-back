<?php

namespace App\Servicios\PinpadJar;

/**
 * JarBridge — orquesta el lanzamiento del Trama Builder JavaFX desde PHP.
 *
 * COMUNICACION:
 *   PHP ──── shell_exec ────▶ Java (JavaFX UI en pantalla del cajero)
 *   PHP ◀──── lee archivo ──── Java (cuando genera trama)
 *
 * No hay socket ni pipe entre PHP y Java — la comunicacion es por archivos
 * en `storage/app/pinpad-sessions/`. Es simple, robusto y no requiere
 * configuracion extra (ningun puerto adicional).
 */
final class JarBridge
{
    /**
     * abrir() - lanza el TramaBuilder en background.
     *
     * @param string $sessionId  UUID generado por el controller
     * @return array  Info del comando lanzado (util para debug)
     * @throws \RuntimeException  si falta algun archivo o path
     */
    public function abrir(string $sessionId): array
    {
        // Lee los paths de config/pinpad.php (que a su vez leen de .env)
        $javaBin     = config('pinpad.jar.java_bin');
        $javafxLib   = config('pinpad.jar.javafx_lib');
        $tramaJar    = config('pinpad.jar.libreria_tramas_jar');

        // base_path() es helper de Laravel: devuelve la ruta absoluta de la
        // raiz del proyecto. Aqui apunta a `crm-back/jars/`.
        $bridgeDir   = base_path('jars');

        // Crea (si no existe) la carpeta donde Java escribira los archivos
        // de sesion: `crm-back/storage/app/pinpad-sessions/`
        $storagePath = $this->ensureStoragePath();

        // ---- VALIDACIONES TEMPRANAS ----
        // Si algo falta, fallamos AHORA con mensaje claro en vez de dejar
        // que Java falle silenciosamente.
        if (!is_file($javaBin)) {
            throw new \RuntimeException("java.exe no encontrado en: $javaBin");
        }
        if (!is_dir($javafxLib)) {
            throw new \RuntimeException("JavaFX SDK no encontrado en: $javafxLib");
        }
        if (!is_file($tramaJar)) {
            throw new \RuntimeException("Libreria de tramas no encontrada en: $tramaJar");
        }
        if (!is_file($bridgeDir . DIRECTORY_SEPARATOR . 'TramaJarBridge.class')) {
            throw new \RuntimeException("TramaJarBridge.class no encontrado. Compilarlo segun jars/COMPILAR.md");
        }

        // ---- CONSTRUCCION DEL CLASSPATH ----
        // En Java, el classpath es la lista de carpetas/JARs donde buscar clases.
        // En Windows, el separador entre paths es ';' (en Linux es ':').
        // Importante: TODO el classpath va entre comillas dobles para que cmd.exe
        // lo trate como UN solo argumento (el ';' interno no se interpreta como
        // separador de comandos shell).
        $classpath = '"' . $tramaJar . ';' . $bridgeDir . '"';

        // ---- COMANDO JAVA ----
        // sprintf con %s para inyectar los paths.
        // --module-path y --add-modules: requeridos por JavaFX 17 (que se
        //   distribuye como modulos JPMS, no como JAR clasico).
        // -cp <classpath>: clases tradicionales (la libreria + nuestro wrapper).
        // TramaJarBridge: el nombre completo de la clase main.
        // Los 2 ultimos argumentos son lo que recibe nuestro main(args).
        $javaCmd = sprintf(
            '"%s" --module-path "%s" --add-modules javafx.controls,javafx.fxml -cp %s TramaJarBridge "%s" "%s"',
            $javaBin,
            $javafxLib,
            $classpath,
            $sessionId,
            $storagePath
        );

        // ---- LANZAMIENTO EN BACKGROUND ----
        // start /B: ejecuta el comando en background, en la misma consola
        //          (sin abrir una ventana cmd extra que confunda al usuario).
        // ""      : titulo vacio (start lo requiere si despues vienen comillas).
        // > NUL 2>&1 : redirige stdout y stderr a NUL (Windows /dev/null).
        //              Sin esto, popen se queda colgado esperando que el
        //              proceso hijo cierre los streams de I/O.
        $cmd = 'start /B "" ' . $javaCmd . ' > NUL 2>&1';

        // popen() abre el proceso para escritura/lectura.
        // pclose() cierra inmediatamente el handle, dejando al proceso
        //          corriendo en background. Patron "fire and forget" en PHP.
        pclose(popen($cmd, 'r'));

        // Devolvemos info para que el frontend pueda hacer polling y
        // tambien para debug (si algo falla podemos ver el cmd exacto).
        return [
            'session_id' => $sessionId,
            'storage'    => $storagePath,
            'cmd'        => $cmd,
        ];
    }

    /**
     * leer() - chequea si la sesion ya tiene una trama (ready),
     * si el usuario cerro sin generar (cancelled) o todavia esta esperando (pending).
     */
    public function leer(string $sessionId): array
    {
        // Sanitizamos el sessionId para evitar path traversal
        // (ej: alguien pasando "../../../etc/passwd" como sessionId).
        $sessionId = $this->sanitize($sessionId);
        $storage   = $this->ensureStoragePath();

        // Construimos los paths esperados:
        //   {storage}/{uuid}.txt   = trama generada (success)
        //   {storage}/{uuid}.done  = ventana cerrada sin generar (cancelled)
        $tramaFile = $storage . DIRECTORY_SEPARATOR . $sessionId . '.txt';
        $doneFile  = $storage . DIRECTORY_SEPARATOR . $sessionId . '.done';

        // PRIORIDAD 1: si hay archivo .txt, hay trama lista
        if (is_file($tramaFile)) {
            $trama = trim(file_get_contents($tramaFile));
            return [
                'status' => 'ready',
                'trama'  => $trama,
                'len'    => strlen($trama),
            ];
        }

        // PRIORIDAD 2: si solo hay .done, el usuario cerro sin generar
        if (is_file($doneFile)) {
            return ['status' => 'cancelled'];
        }

        // PRIORIDAD 3: ninguno existe = todavia esta abierto/usandose
        return ['status' => 'pending'];
    }

    /**
     * limpiar() - borra los archivos de una sesion finalizada.
     * Se llama desde el frontend tras consumir la trama.
     */
    public function limpiar(string $sessionId): void
    {
        $sessionId = $this->sanitize($sessionId);
        $storage   = $this->ensureStoragePath();

        // El @ silencia warnings si los archivos no existen.
        // unlink() borra el archivo del filesystem.
        @unlink($storage . DIRECTORY_SEPARATOR . $sessionId . '.txt');
        @unlink($storage . DIRECTORY_SEPARATOR . $sessionId . '.done');
    }

    /**
     * limpiarAntiguas() - garbage collector. Borra archivos de sesiones que
     * llevan mucho tiempo sin actividad (default 60 min).
     *
     * Util si una sesion quedo huerfana (cajero cerro la ventana del browser
     * antes de que terminara la sesion, etc.).
     */
    public function limpiarAntiguas(int $minutos = 60): int
    {
        $storage = $this->ensureStoragePath();
        $cutoff  = time() - ($minutos * 60);  // timestamp UNIX limite
        $deleted = 0;

        // glob() lista archivos por patron. {txt,done} con GLOB_BRACE expande
        // a una busqueda de ambas extensiones.
        foreach (glob($storage . DIRECTORY_SEPARATOR . '*.{txt,done}', GLOB_BRACE) as $file) {
            // filemtime() = ultima modificacion del archivo (timestamp).
            // Si es anterior al cutoff, lo borramos.
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * Asegura que la carpeta de sesiones exista. La crea si no, devuelve la ruta.
     */
    private function ensureStoragePath(): string
    {
        // storage_path() es helper Laravel que devuelve la ruta absoluta
        // a `crm-back/storage/`.
        $path = storage_path('app' . DIRECTORY_SEPARATOR . 'pinpad-sessions');

        // mkdir con true en el 3er arg = recursive (crea carpetas padre si faltan)
        // 0775 = permisos: owner rwx, group rwx, others rx
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
        return $path;
    }

    /**
     * Valida que el sessionId tenga formato UUID/alfanumerico SIN slashes,
     * dos puntos, etc. Previene path traversal.
     */
    private function sanitize(string $sessionId): string
    {
        // Regex: solo letras, numeros y guiones, entre 8 y 64 chars.
        // Cubre UUIDs (36 chars) y otros formatos razonables.
        if (!preg_match('/^[a-zA-Z0-9\-]{8,64}$/', $sessionId)) {
            throw new \InvalidArgumentException("session_id invalido");
        }
        return $sessionId;
    }
}

// Importamos la app principal del JAR oficial de Medianet.
// `showWindow()` abre la ventana JavaFX del Trama Builder.
import com.wposs.libreria_integracion_tramas.app.TramaBuilderApp;
// TramaHolder es un singleton de la libreria que expone una "JavaFX property"
// donde se publica la trama recien generada. Le pondremos un listener.
import com.wposs.libreria_integracion_tramas.util.TramaHolder;
// JavaFX: clase base de toda app, control del lifecycle, y Stage = ventana.
import javafx.application.Application;
import javafx.application.Platform;
import javafx.stage.Stage;

// IO para escribir archivos (el medio de comunicacion con Laravel)
import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;

/**
 * Puente Laravel <-> Trama Builder (JavaFX).
 *
 * Argumentos:
 *   args[0] = session_id (uuid generado por Laravel)
 *   args[1] = directorio absoluto donde escribir archivos de session
 *
 * Flujo:
 *   - Abre la ventana oficial del Trama Builder.
 *   - Cuando TramaHolder.tramaProperty() cambia (= usuario dio Generar),
 *     escribe la trama a {storage}/{session_id}.txt
 *   - Cuando la ventana se cierra (stop() de JavaFX), escribe
 *     {storage}/{session_id}.done para senalizar a Laravel que termino.
 */
public class TramaJarBridge extends Application {

    // Variables estaticas: las llena main() y las lee start() y stop().
    // Son estaticas porque JavaFX crea la instancia con su propio constructor
    // (no podemos pasarles datos por ahi facilmente).
    private static String sessionId;
    private static Path   storageDir;

    /**
     * start() - entry point JavaFX, se llama cuando la app esta lista.
     * El parametro `primaryStage` es la ventana principal que JavaFX da
     * automaticamente, pero en este caso NO la usamos (la libreria oficial
     * abre su propia ventana via TramaBuilderApp.showWindow()).
     */
    @Override
    public void start(Stage primaryStage) {
        // Suscribimos un listener a la "trama property" de la libreria.
        // En JavaFX, las "Property" emiten eventos cuando cambian de valor.
        // (obs, oldVal, newVal) => observable, valor anterior, valor nuevo.
        TramaHolder.tramaProperty().addListener((obs, oldVal, newVal) -> {
            // Solo nos interesan los cambios a un valor no vacio (la libreria
            // a veces "reset" la propiedad a "" entre transacciones).
            if (newVal != null && !newVal.isEmpty()) {
                // Escribimos la trama a un archivo que Laravel puede leer.
                writeFile(sessionId + ".txt", newVal);
            }
        });

        // Llama al metodo estatico de la libreria que abre la ventana.
        // Esto lanza el formulario completo: tabs PP, CT, LT, PC, RA, CP.
        TramaBuilderApp.showWindow();

        // Configuracion de JavaFX: cuando se cierre el ultimo Stage (= la
        // ventana del TramaBuilder), termina la app y se llama stop().
        Platform.setImplicitExit(true);
    }

    /**
     * stop() - se llama cuando la app esta cerrandose.
     * Escribimos un marker .done para que Laravel sepa que la sesion
     * termino (con o sin haber generado una trama).
     */
    @Override
    public void stop() {
        writeFile(sessionId + ".done", String.valueOf(System.currentTimeMillis()));
    }

    /**
     * writeFile() - helper para escribir un archivo en la carpeta de sesion.
     */
    private void writeFile(String name, String content) {
        try {
            // Asegura que el directorio exista (Laravel ya lo crea, pero
            // esto es por si acaso esta corriendo en otro contexto).
            Files.createDirectories(storageDir);
            // resolve() concatena el nombre al path base de manera segura
            // (cross-platform: Windows usa \, Linux usa /).
            Path file = storageDir.resolve(name);
            // Escribe los bytes del string (UTF-8) en el archivo.
            // Sobreescribe si ya existia.
            Files.write(file, content.getBytes(StandardCharsets.UTF_8));
        } catch (IOException e) {
            // Si fallo el write (ej: permisos), lo logueamos y seguimos.
            // No queremos que un error de IO mate la UI.
            System.err.println("[TramaJarBridge] error escribiendo " + name + ": " + e.getMessage());
        }
    }

    /**
     * main() - punto de entrada normal de Java.
     * Recibe los argumentos de la linea de comando.
     */
    public static void main(String[] args) {
        // Validacion: necesitamos exactamente 2 argumentos.
        if (args.length < 2) {
            System.err.println("Usage: TramaJarBridge <session_id> <storage_path>");
            System.exit(1);
        }
        // Guardamos en las variables estaticas para que start() las pueda leer.
        sessionId  = args[0];
        storageDir = Paths.get(args[1]);
        // launch() es el metodo de Application que arranca el ciclo de vida
        // JavaFX: inicializa la plataforma, llama a start(), etc.
        launch(args);
    }
}

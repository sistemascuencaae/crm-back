<?php

return [
    /*
     * ============== PIN PAD FISICO (DESTINO TCP) ==============
     * Tu Laravel abre socket aqui y envia/recibe tramas.
     * El Pin Pad luego habla con el switch Medianet por su cuenta.
     */
    'ip' => env('PINPAD_IP', '192.168.1.242'),
    'port' => (int) env('PINPAD_PORT', 6500),

    /*
     * ============== PARAMETROS DE RED (informativo / CP) ==============
     */
    'pinpad_mask' => env('PINPAD_MSK', '255.255.255.0'),
    'pinpad_gateway' => env('PINPAD_GW', '192.168.1.200'),

    /*
     * ============== HOST MEDIANET (informativo) ==============
     * IP del switch al que el Pin Pad sale internamente.
     * Tu Laravel NO lo alcanza ni lo necesita.
     */
    'host_medianet' => env('PINPAD_HOST', '10.10.3.200'),

    /*
     * ============== IDENTIFICADORES DEL COMERCIO ==============
     */
    'mid' => env('PINPAD_MID', ''),
    'tid' => env('PINPAD_TID', ''),
    'cid_terminal' => env('PINPAD_CID_TERMINAL', ''),

    /*
     * ============== DATOS DEL COMERCIO PARA EL VOUCHER ==============
     * Devueltos en probe() para que el frontend los use al imprimir el ticket.
     */
    'comercio' => [
        'nombre' => env('PINPAD_COMERCIO_NOMBRE', ''),
        'ruc' => env('PINPAD_COMERCIO_RUC', ''),
        'direccion' => env('PINPAD_COMERCIO_DIRECCION', ''),
        'telefono' => env('PINPAD_COMERCIO_TELEFONO', ''),
        'ciudad' => env('PINPAD_COMERCIO_CIUDAD', ''),
    ],

    /*
     * ============== TIMEOUT Y MODO ==============
     */
    'timeout_ms' => (int) env('PINPAD_TIMEOUT_MS', 30000),
    'modo' => env('PINPAD_MODO', 'exclusivo'),

    /*
     * ============== JAR BRIDGE (TramaBuilder JavaFX) ==============
     * Para abrir la UI oficial del Trama Builder desde el frontend Angular.
     * Se usa solo en el componente /pinpad-libreria-jar (independiente del
     * resto del modulo Pin Pad).
     */
    'jar' => [
        // Java binario (asumimos que esta en PATH del sistema operativo;
        // override via .env si esta en otra ruta).
        'java_bin' => env('JAVA_BIN', 'java'),
        // JavaFX SDK incluido en el proyecto (jars/javafx-sdk/lib).
        'javafx_lib' => env('JAVAFX_LIB_PATH', base_path('jars/javafx-sdk/lib')),
        // Libreria oficial de Medianet, incluida en el proyecto.
        'libreria_tramas_jar' => env('LIBRERIA_TRAMAS_JAR', base_path('jars/Libreria_integracion_tramas_V1.2.jar')),
    ],

    /*
     * ============== CACHE DE REVERSO ==============
     * TTL en minutos. La libreria oficial mantiene la trama de reverso en
     * memoria mientras la app este abierta (sin TTL explicito). Manual
     * 4.1.4: "el sistema intentara reversar la ultima transaccion".
     *
     * Default 480 min (8 horas) = duracion de un turno tipico de caja.
     * Ajusta segun tus horarios:
     *   - turno simple 8h  → 480
     *   - turno doble 16h  → 960
     *   - 24/7             → 1440 (max razonable)
     *   - reverso solo automatico tras timeout → 5
     */
    'reverso_ttl_minutes' => (int) env('PINPAD_REVERSO_TTL_MINUTES', 480),
];

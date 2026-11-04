<?php

namespace App\Servicios\Consultas;

// 30-oct: lo que ConsultasController necesita de un calificador (GaranCheck, Yupana). Cada servicio sabe llamar a su
// proveedor, registrar cada llamada en crm.proveedor_respuestas y leer SU JSON (nombres, precarga, evaluación); el
// controlador no distingue cuál es. Cuál se usa lo decide crm.fn_cliente_consultas_proveedor_decidir (la agencia).
interface ProveedorConsulta
{
    // Va a la columna proveedor del historial y de las respuestas (GARANCHECK, YUPANA).
    public function nombre(): string;

    // El nombre que ve el usuario en los mensajes y el spinner ("Consultando GaranCheck...").
    public function nombreVisible(): string;

    // El id de crm.proveedor_respuestas de la última llamada (el "Cód. N" del aviso), o null.
    public function idUltimaRespuesta(): ?int;

    // La consulta pagada: devuelve el JSON que se guarda en crm.cliente_historial_consultas.valor. Lanza RuntimeException
    // si el proveedor no atendió (registrando antes la fila de la respuesta).
    public function consultar(string $identificacion, array $registro = []): array;

    // Datos mínimos para crear o corregir al cliente (nombres, apellidos, nombre_comercial, email, telefono, direccion,
    // provincia, canton, parroquia, tipo_sujeto). Lanza si el JSON no trae identidad.
    public function extraerDatosCliente(array $json, string $tipoSujeto): array;

    // Los de extraerDatosCliente más los personales (fecha_nacimiento, sexo, estado_civil, nivel_estudios,
    // nacionalidad, conyuge; null lo que el proveedor no trae) para precargar el modal.
    public function extraerDatosPrecarga(array $json, string $tipoSujeto): array;

    // La evaluación/calificación lista para la pantalla, o null si la consulta no trae calificación.
    public function evaluacionParaPantalla(array $json): ?array;
}

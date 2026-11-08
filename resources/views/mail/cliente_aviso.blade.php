<!DOCTYPE html>
<html lang="es">

<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
</head>

<body style="font-family: Arial, sans-serif; color: #222;">
    Hola {{ $object->nombres }},
    <br><br>
    @if ($object->tipo === 'aviso_correo')
        El correo de tu cuenta cambió a {{ $object->correo_nuevo }}. Desde ahora ingresa con ese correo.
    @else
        Tu contraseña se cambió. Por seguridad, cerramos la sesión en tus otros equipos.
    @endif
    <br><br>
    Si no fuiste tú, escríbenos de inmediato.

    <h5 style="text-align: center;">Por favor, no responda a este mensaje.</h5>
</body>

</html>

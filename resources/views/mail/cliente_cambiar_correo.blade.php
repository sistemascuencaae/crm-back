<!DOCTYPE html>
<html lang="es">

<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
</head>

<body style="font-family: Arial, sans-serif; color: #222;">
    Hola {{ $object->nombres }},
    <br><br>
    Pediste usar este correo en tu cuenta. Confírmalo con este botón:
    <br><br>
    <a href="{{ $object->enlace }}"
        style="display: inline-block; padding: 10px 20px; background: #d32f2f; color: #fff; text-decoration: none; border-radius: 4px;">
        Confirmar mi nuevo correo
    </a>
    <br><br>
    Si el botón no funciona, copia este enlace en tu navegador:
    <br>
    {{ $object->enlace }}
    <br><br>
    El enlace dura {{ $object->duracion }}. Hasta que lo uses, tu cuenta sigue con el correo anterior.
    <br><br>
    Si no lo pediste, ignora este mensaje.

    <h5 style="text-align: center;">Por favor, no responda a este mensaje.</h5>
</body>

</html>

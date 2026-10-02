<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta content="es" http-equiv="Content-Language" />
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
    <style type="text/css">
        .auto-style1 {
            text-align: center;
            color: black;
        }
    </style>
</head>

<body>
    {{-- Aquí se muestran los campos del objeto: $object->campo_que_queremos_mostrar --}}
    Estimado(a) {{ $object->apellidos ?? '' }} {{ $object->nombres ?? '' }},
    <br>
    <br>
    Estas son sus credenciales:
    <br>
    <b>Usuario: </b>{{ $object->usuario ?? '' }}
    <br>
    <b>Contraseña: </b>{{ $object->contrasena ?? '' }}

    <h5 class="auto-style1">Por favor, no responda a este mensaje.</h5>

</body>

</html>

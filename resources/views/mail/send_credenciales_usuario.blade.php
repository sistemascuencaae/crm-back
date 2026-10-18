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
    Estimado(a) {{ $object->apellidos ?? '' }} {{ $object->nombres ?? '' }},
    <br>
    <br>
    {{ !empty($object->es_alta) ? 'Se creó su usuario del CRM. Estas son sus credenciales:' : 'Sus credenciales del CRM cambiaron:' }}
    <br>
    <b>Usuario: </b>{{ $object->usuario ?? '' }}
    <br>
    <b>Contraseña: </b>{{ $object->contrasena ?? 'la misma que ya tenía' }}
    @if (!empty($object->dynamo))
        <br>
        <br>
        En Dynamo ingresa con el mismo usuario y contraseña.
    @endif

    <br><br><a href="https://crm.almacenesespana.com.ec">Ingresar al CRM</a>

    <h5 class="auto-style1">Por favor, no responda a este mensaje.</h5>

</body>

</html>

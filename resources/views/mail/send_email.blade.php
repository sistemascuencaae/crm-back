<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta content="es" http-equiv="Content-Language" />
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
    <title>Untitled 1</title>
    <style type="text/css">
        .auto-style1 {
            text-align: center;
            color: black;
        }

        #tablaProd {
            border: 1px solid black;
            border-collapse: collapse;
            width: 100%;
            color: black;
        }

        #tablaProd th {
            background-color: #dddddd;
            color: black;
        }
    </style>
</head>

<body>
    <table style="width: 100%">
        <tr>
            <td class="auto-style1"><b>Proforma #{{ $object->cpp_id }}</b></td>
        </tr>
    </table>
    <table style="width: 100%">
        <tr>
            <td><b>Almespana Cia. Ltda.</b></td>
        </tr>
    </table>
    <table style="width: 100%">
        <tr>
            <td style="width: 134px"><b>Fecha:</b></td>
            <td>{{ $object->cpp_fecha }} </td>
        </tr>
        <tr>
            <td style="width: 134px"><b>Cédula:</b></td>
            <td>{{ $object->cli_codigo }}</td>
        </tr>
        <tr>
            <td style="width: 134px"><b>Cliente:</b></td>
            <td>{{ $object->empleado }} </td>
        </tr>
    </table>

    <br>

    <table id="tablaProd">
        <thead>
            <tr id="tablaProd">
                <th id="tablaProd" style="width: 10%;">Código</th>
                <th id="tablaProd" style="width: 60%">Descripción</th>
                <th id="tablaProd" style="width: 10%">Cantidad</th>
                <th id="tablaProd" style="width: 10%">PVP</th>
                <th id="tablaProd" style="width: 10%">Total</th>
            </tr>
        </thead>
        <tbody>
            @if (!is_null($object->dpedidoProforma))
                @foreach ($object->dpedidoProforma as $p)
                    </tr>
                    <td id="tablaProd" style="width: 10%;">{{ $p->pro_codigo }}</td>
                    <td id="tablaProd" style="width: 60%;">{{ $p->pro_nombre }}</td>
                    <td id="tablaProd" style="width: 10%;">{{ $p->dpp_cantidad }}
                    </td>
                    <td id="tablaProd" style="width: 10%;">{{ $p->dpp_costoprecio }}</td>
                    <td id="tablaProd" style="width: 10%;"> {{ $p->dpp_valortotal }}</td>
                    </tr>
                @endforeach
            @else
                <td colspan="5">No hay productos.</td>
            @endif

            <tr id="tablaProd">
                <td rowspan="4" colspan="3" style="width: 80%;"></td>
            </tr>

            <tr id="tablaProd">
                <th id="tablaProd" style="width: 10%;">subtotal</th>
                <td style="width: 10%;">{{ $object->sub_total_compra }}</td>
            </tr>

            <tr id="tablaProd">
                <th id="tablaProd" style="width: 10%">iva</th>
                <td style="width: 10%">12%</td>
            </tr>

            <tr id="tablaProd">
                <th id="tablaProd" style="width: 10%">total</th>
                <td style="width: 10%">{{ $object->total_compra }}</td>
            </tr>
        </tbody>
    </table>

</body>

</html>

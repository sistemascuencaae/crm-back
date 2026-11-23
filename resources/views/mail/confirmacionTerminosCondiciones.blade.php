<!DOCTYPE html>
<html>

<head>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            width: 100%;
            height: 100vh;
            background-image: url('{{ asset('img/formulario-institucional/bg-login.png') }}');
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        .logo img {
            position: absolute;
            top: 0;
            left: 0;
            padding: 20px;
            width: 20%;
        }

        .content {
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
        }

        #titulo {
            color: green;
            line-height: 1;
            text-shadow: 2px 2px 4px rgba(88, 212, 201, 0.2);
        }

        .mt-3,
        .mb-2 {
            margin-top: 1em;
            margin-bottom: 0.5em;
        }

        @media (max-width: 576px) {
            .logo img {
                width: 40%;
            }
        }
    </style>
</head>

<body>
    <div class="logo">
        <img src="{{ asset('img/formulario-institucional/logoalmagex.png') }}" alt="Logo Image">
    </div>

    <div class="content">
        <p id="titulo" class="fs-1">Gracias por aceptar los términos y condiciones.</p>
    </div>
</body>

</html>

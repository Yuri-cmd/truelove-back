<!DOCTYPE html>
<html>
<head>
    <title>Verificación de correo electrónico</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
        <!-- Encabezado con la imagen -->
        <header style="text-align: center; margin-bottom: 20px;">
            <img src="https://www.truelovesupply.com/wp-content/uploads/2022/01/cropped-Logo-slogan.png" alt="TRUELOVE Logo" style="max-width: 200px; height: auto;">
        </header>

        <!-- Imagen adicional -->
        <img src="https://media.istockphoto.com/id/1289220657/es/foto/propietario-de-una-peque%C3%B1a-empresa-de-pie-en-la-entrada-del-caf%C3%A9.jpg?s=2048x2048&w=is&k=20&c=LHJWeFOED-ibdOcFPlbBwvM7Wy8MWLK6pALI8LYYRdU=" alt="Imagen negocio" style="max-width: 400px; height: auto; margin-bottom: 20px;">

        <!-- Saludo personalizado -->
        <h3 style="text-align: start;">Hola, {{ $name }}</h3>

        <p>Gracias por registrar tu negocio con nosotros. Para completar tu registro, por favor utiliza el siguiente código de verificación:</p>

        <div style="background: #f5f5f5; padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;">
            <h1 style="color: #f34739; margin: 0; letter-spacing: 5px;">{{ $code }}</h1>
        </div>

        <p>Si no has solicitado este código, puedes ignorar este correo.</p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificación de correo electrónico</title>
</head>

<body
    style="font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; width: 100%; background-color: #f4f4f4;">
    <table cellpadding="0" cellspacing="0" style="width: 100%; background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0"
                    style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 20px;">
                            <!-- Header Image -->
                            <img src="https://res.cloudinary.com/dqpjzyd6p/image/upload/v1731790090/emailheader_go2uxc.jpg"
                                alt="True Love Portal"
                                style="width: 100%; height: auto; display: block; margin-bottom: 20px;">

                            <!-- Greeting -->
                            <h3 style="text-align: start; margin-top: 0;">Hola, {{ $name }}</h3>

                            <p style="margin: 0 0 20px;">Has solicitado restablecer tu contraseña. Para continuar con el proceso, por favor utiliza el siguiente código de verificación:</p>

                            <!-- Verification Code Box -->
                            <div
                                style="background: #f5f5f5; padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;">
                                <h1 style="color: #f34739; margin: 0; letter-spacing: 5px;">{{ $code }}</h1>
                            </div>

                            <p style="margin: 20px 0 0;">Si no has solicitado este código, puedes ignorar este correo.
                            </p>
                            
                            <p style="margin: 20px 0 0; font-size: 12px; color: #999;">
                                Este código expirará en 15 minutos por razones de seguridad.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
<!-- resources\views\emails\account-deletion-rejected.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitud de eliminación</title>
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
                                alt="TRUE LOVE"
                                style="width: 100%; height: auto; display: block; margin-bottom: 20px;">

                            <!-- Greeting -->
                            <h3 style="text-align: start; margin-top: 0;">Hola, {{ $nombre }}</h3>

                            <p style="margin: 0 0 20px;">
                                Hemos revisado tu solicitud de eliminación de cuenta.
                            </p>

                            <!-- Request ID Box -->
                            <div
                                style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #f59e0b;">
                                <p style="margin: 0; color: #666; font-size: 14px;">ID de Solicitud:</p>
                                <h2 style="color: #f34739; margin: 5px 0 0; letter-spacing: 2px;">{{ $request_id }}</h2>
                            </div>

                            <div style="background: #fffbeb; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #f59e0b;">
                                <p style="margin: 0; font-weight: bold; color: #92400e;">Estado: No procesada</p>
                                <p style="margin: 10px 0 0; color: #666; font-size: 14px;">
                                    <strong>Motivo:</strong><br>
                                    {{ $motivo_rechazo }}
                                </p>
                            </div>

                            <div style="background: #f0fdf4; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #10b981;">
                                <p style="margin: 0; font-weight: bold; color: #065f46;">Tu cuenta permanece activa</p>
                                <p style="margin: 10px 0 0; color: #666; font-size: 14px;">
                                    Puedes seguir disfrutando de todos los beneficios de TRUE LOVE.
                                </p>
                            </div>

                            <p style="margin: 20px 0 0; color: #666;">
                                Si tienes alguna duda o deseas volver a solicitar la eliminación de tu cuenta, por favor contáctanos a través de nuestro Centro de ayuda.
                            </p>

                            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">

                            <p style="margin: 0; color: #999; font-size: 12px; text-align: center;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tus credenciales de acceso - TRUELOVE</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f6f6f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 0;">
                <table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header Image -->
                    <tr>
                        <td style="padding: 0;">
                            <img src="https://res.cloudinary.com/dqpjzyd6p/image/upload/v1731790090/emailheader_go2uxc.jpg" alt="TRUELOVE Delivery" style="width: 100%; height: auto; display: block;">
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #333333; margin: 0 0 20px; font-size: 24px;">¡Bienvenido Motorizado TRUELOVE!</h2>
                            <p style="color: #666666; margin: 0 0 25px; line-height: 1.6;">Has sido aprobado como motorizado. A continuación encontrarás tus credenciales de acceso a la plataforma:</p>
                            
                            <!-- Credentials Box -->
                            <table role="presentation" style="width: 100%; background-color: #f8f8f8; border-radius: 6px; margin-bottom: 25px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 10px; color: #333333;">
                                            <strong>Usuario:</strong> 
                                            <span style="color: #E60023;">{{ $username }}</span>
                                        </p>
                                        <p style="margin: 0; color: #333333;">
                                            <strong>Contraseña:</strong> 
                                            <span style="color: #E60023;">{{ $password }}</span>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #666666; margin: 0 0 25px; padding: 15px; background-color: #fff4f4; border-radius: 6px; border-left: 4px solid #E60023;">
                                <strong>Importante:</strong> Por razones de seguridad, te recomendamos cambiar tu contraseña después de iniciar sesión por primera vez.
                            </p>

                            <!-- Additional Info -->
                            <div style="background-color: #f8f8f8; padding: 20px; border-radius: 6px; margin-bottom: 25px;">
                                <p style="color: #666666; margin: 0 0 15px; line-height: 1.6;">
                                    Como motorizado de TRUELOVE, tendrás acceso a:
                                </p>
                                <ul style="color: #666666; margin: 0; padding-left: 20px; line-height: 1.6;">
                                    <li>Panel de control de entregas</li>
                                    <li>Sistema de gestión de rutas</li>
                                    <li>Historial de pedidos</li>
                                    <li>Soporte técnico 24/7</li>
                                </ul>
                            </div>

                            <!-- Login Button -->
                            <table role="presentation" style="width: 100%; margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="https://deliverytruelove.com/login" style="background-color: #E60023; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block;">Iniciar Sesión</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f6f6f6; padding: 30px; text-align: center; border-top: 1px solid #eeeeee;">
                            <p style="color: #999999; margin: 0; font-size: 14px;">
                                © 2024 TRUELOVE. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
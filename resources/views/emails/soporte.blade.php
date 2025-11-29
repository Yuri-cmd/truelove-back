<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Consulta de Soporte</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #D9043D 0%, #c70339 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #D9043D;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .info-row {
            margin: 10px 0;
        }
        .label {
            font-weight: bold;
            color: #D9043D;
            display: inline-block;
            min-width: 140px;
        }
        .value {
            color: #555;
        }
        .mensaje-box {
            background-color: #fff;
            border: 2px solid #e9ecef;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            min-height: 100px;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            background-color: #D9043D;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🆘 Nueva Consulta de Soporte</h1>
            <p style="margin: 10px 0 0 0; font-size: 14px;">TRUE LOVE Delivery</p>
        </div>

        <div class="content">
            <p style="font-size: 16px; margin-bottom: 20px;">
                Has recibido una nueva consulta de soporte a través del formulario web.
            </p>

            <div class="info-box">
                <h3 style="margin-top: 0; color: #D9043D;">📋 Información del Usuario</h3>
                
                <div class="info-row">
                    <span class="label">👤 Nombre:</span>
                    <span class="value">{{ $datos['nombre'] }} {{ $datos['apellido'] }}</span>
                </div>

                <div class="info-row">
                    <span class="label">📧 Email:</span>
                    <span class="value">
                        <a href="mailto:{{ $datos['email'] }}" style="color: #D9043D; text-decoration: none;">
                            {{ $datos['email'] }}
                        </a>
                    </span>
                </div>

                <div class="info-row">
                    <span class="label">📱 Teléfono:</span>
                    <span class="value">{{ $datos['telefono'] }}</span>
                </div>

                <div class="info-row">
                    <span class="label">📅 Fecha:</span>
                    <span class="value">{{ $datos['fecha'] }}</span>
                </div>
            </div>

            <div class="info-box">
                <h3 style="margin-top: 0; color: #D9043D;">📝 Detalles de la Consulta</h3>
                
                <div class="info-row">
                    <span class="label">Tipo:</span>
                    <span class="badge">{{ $datos['tipoConsulta'] }}</span>
                </div>

                <div class="info-row">
                    <span class="label">Asunto:</span>
                    <span class="value" style="font-weight: 600;">{{ $datos['asunto'] }}</span>
                </div>
            </div>

            <h3 style="color: #D9043D; margin-top: 25px;">💬 Mensaje:</h3>
            <div class="mensaje-box">
                {{ $datos['mensaje'] }}
            </div>

            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-top: 20px; border-radius: 5px;">
                <p style="margin: 0; font-size: 14px;">
                    <strong>⏰ Recordatorio:</strong> Responder en un plazo máximo de 24 horas hábiles.
                </p>
            </div>
        </div>

        <div class="footer">
            <p style="margin: 5px 0;">
                <strong>TRUE LOVE Delivery</strong><br>
                Domingo Torero 120, Huachipa, Lima, Perú<br>
                📧 info@deliverytruelove.com | 📱 +51 989 815 260
            </p>
            <p style="margin: 15px 0 5px 0; color: #999; font-size: 11px;">
                Este correo fue generado automáticamente desde el formulario de soporte web.
            </p>
        </div>
    </div>
</body>
</html>

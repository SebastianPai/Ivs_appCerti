<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin: 0; padding: 0; background-color: #f4f6f8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f4f6f8; padding: 40px 0; }
        .webkit { max-width: 600px; background-color: #ffffff; margin: 0 auto; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .header { text-align: center; padding: 30px 0 20px; border-bottom: 4px solid #F59E0B; } /* Borde Amarillo (Atención/Revisión) */
        .content { padding: 30px 40px; color: #374151; font-size: 16px; line-height: 1.6; }
        .tag { display: inline-block; background-color: #FFFBEB; color: #B45309; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 10px; border: 1px solid #FCD34D; }
        h2 { margin: 0 0 15px; color: #111827; }
        .info-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .info-table td { padding: 10px; background-color: #f9fafb; border: 1px solid #e5e7eb; }
        .info-label { font-size: 13px; color: #6b7280; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 4px; }
        .info-value { font-size: 16px; color: #111827; font-weight: 700; }
        .btn { display: block; width: 200px; margin: 25px auto 0; padding: 12px 0; background-color: #1f2937; color: #ffffff !important; text-decoration: none; border-radius: 6px; text-align: center; font-weight: 600; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <center>
            <div class="webkit">
                <div class="header">
                    <img src="https://www.ivscertificaciones.com/img/ivs_img.png" alt="IVS" style="height: 40px;">
                </div>
                <div class="content">
                    <span class="tag">Pendiente de Revisión</span>
                    <h2>Hola, Revisor</h2>
                    <p>El evaluador <strong>{{ $evaluador_name }}</strong> ha completado la inspección técnica y ha enviado la solicitud para tu aprobación final.</p>

                    <table class="info-table">
                        <tr>
                            <td style="border-radius: 8px 8px 0 0;">
                                <span class="info-label">Placa del Vehículo</span>
                                <span class="info-value">{{ $placa }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span class="info-label">Evaluador Responsable</span>
                                <span class="info-value">{{ $evaluador_name }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td style="border-radius: 0 0 8px 8px;">
                                <span class="info-label">Fecha de Envío</span>
                                <span class="info-value">{{ now()->format('d/m/Y H:i') }}</span>
                            </td>
                        </tr>
                    </table>

                    <a href="{{ $url_gestion }}" class="btn">Revisar Solicitud</a>
                </div>
            </div>
            <div class="footer">
                <p>Sistema de Gestión IVS Certificaciones</p>
            </div>
        </center>
    </div>
</body>
</html>
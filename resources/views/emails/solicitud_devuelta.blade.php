<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* ... (Pega aquí los mismos estilos CSS que ya tienes en tu otro correo) ... */
        /* Estilos base */
        body { margin: 0; padding: 0; background-color: #f4f6f8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        table { border-spacing: 0; }
        td { padding: 0; }
        img { border: 0; }
        
        /* Contenedor principal */
        .wrapper { width: 100%; table-layout: fixed; background-color: #f4f6f8; padding-bottom: 60px; padding-top: 40px; }
        
        /* La tarjeta blanca */
        .webkit { max-width: 600px; background-color: #ffffff; margin: 0 auto; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; }
        
        /* Encabezado con Logo */
        .header { text-align: center; padding: 40px 0 20px 0; background-color: #ffffff; }
        .logo-img { max-width: 150px; height: auto; }
        
        /* Contenido */
        .content { padding: 0 40px 40px 40px; text-align: left; color: #4b5563; line-height: 1.6; font-size: 16px; }
        
        /* Títulos */
        h1 { margin: 0 0 15px; font-size: 24px; font-weight: 700; color: #1f2937; text-align: center; letter-spacing: -0.5px; }
        p { margin: 0 0 20px; }
        
        /* Caja de detalles */
        .info-box { background-color: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; padding: 25px; margin: 25px 0; }
        .info-row { display: block; margin-bottom: 5px; font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .info-value { font-weight: 700; color: #111827; font-size: 18px; }

        /* Botón */
        .btn { display: block; width: 220px; margin: 0 auto; padding: 14px 0; background-color: #DC2626; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; text-align: center; box-shadow: 0 2px 4px rgba(220, 38, 38, 0.2); }
        .btn:hover { background-color: #b91c1c; }

        /* Sección de Contacto */
        .contact-section { border-top: 1px solid #e5e7eb; margin-top: 40px; padding-top: 30px; text-align: center; color: #6b7280; font-size: 14px; }
        .contact-title { font-weight: 700; color: #374151; margin-bottom: 10px; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
        .contact-link { color: #DC2626; text-decoration: none; font-weight: 500; }
        
        /* Footer Legal */
        .footer { text-align: center; padding-top: 20px; font-size: 12px; color: #9ca3af; }
        /* Estilos adicionales para la alerta */
        .alert-box { background-color: #FEF2F2; border: 1px solid #FEE2E2; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .alert-title { color: #991B1B; font-weight: 700; font-size: 14px; text-transform: uppercase; margin-bottom: 5px; display: block; }
        .alert-body { color: #7F1D1D; font-size: 16px; line-height: 1.5; }
        /* ... */
    </style>
</head>
<body>
    <div class="wrapper">
        <center>
            <div class="webkit">
                <div class="header">
                    <img src="https://www.ivscertificaciones.com/img/ivs_img.png" alt="IVS Certificaciones" class="logo-img">
                </div>

                <div class="content">
                    <h1>⚠️ Solicitud Devuelta</h1>
                    
                    <p>Hola <strong>{{ $user_name }}</strong>,</p>
                    <p>Tu solicitud ha sido revisada por nuestro equipo y requiere correcciones antes de continuar.</p>

                    <div class="alert-box">
                        <span class="alert-title">Motivo de la devolución:</span>
                        <div class="alert-body">
                            {{ $observacion }}
                        </div>
                    </div>

                    <div class="info-box">
                        <table width="100%">
                            <tr>
                                <td style="padding-bottom: 20px;">
                                    <span class="info-row">Placa del Vehículo</span>
                                    <span class="info-value">{{ $placa }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <span class="info-row">Acción Requerida</span>
                                    <span class="info-value" style="color: #DC2626;">Corregir Documentos</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <p>Por favor, ingresa a la plataforma para subsanar estas novedades:</p>

                    <a href="{{ $url_gestion }}" class="btn">Corregir Solicitud</a>

                    </div>
            </div>
        </center>
    </div>
</body>
</html>
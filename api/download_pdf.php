<?php
/**
 * API para descargar PDFs de presupuestos
 * Usado por el cotizador moderno
 */

// Cargar configuración - buscar en múltiples ubicaciones
$configPaths = [
    __DIR__ . '/../config.php',           // Railway (raíz del proyecto)
    __DIR__ . '/../sistema/config.php',   // Local (dentro de sistema)
];

$configLoaded = false;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    die("Error: Archivo de configuración no encontrado en ninguna ubicación");
}

// Cargar DB - buscar en múltiples ubicaciones
$dbPaths = [
    __DIR__ . '/../sistema/includes/db.php',   // Local
    __DIR__ . '/../includes/db.php',           // Railway alternativo
];

$dbLoaded = false;
foreach ($dbPaths as $dbPath) {
    if (file_exists($dbPath)) {
        require_once $dbPath;
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded) {
    die("Error: Archivo de base de datos no encontrado en ninguna ubicación");
}

// Obtener ID del presupuesto
$presupuesto_id = $_GET['id'] ?? 0;

if (!$presupuesto_id) {
    die("Error: ID de presupuesto no proporcionado");
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    // Obtener datos del presupuesto
    $query = "SELECT * FROM presupuestos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $presupuesto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die("Error: Presupuesto no encontrado");
    }
    
    $presupuesto = $result->fetch_assoc();
    
    // Obtener detalles de las opciones seleccionadas y SUS PRECIOS GUARDADOS
    $query_detalles = "SELECT o.nombre, o.descuento, c.nombre as categoria_nombre, pd.precio 
                      FROM presupuesto_detalles pd
                      JOIN opciones o ON pd.opcion_id = o.id
                      LEFT JOIN categorias c ON o.categoria_id = c.id 
                      WHERE pd.presupuesto_id = ?";
    
    $stmt_detalles = $conn->prepare($query_detalles);
    
    // Si la consulta falla o no hay tabla de detalles, creamos detalles simulados
    $opciones_detalles = [];
    
    if ($stmt_detalles) {
        $stmt_detalles->bind_param('i', $presupuesto_id);
        $stmt_detalles->execute();
        $result_detalles = $stmt_detalles->get_result();
        
        while ($row = $result_detalles->fetch_assoc()) {
            $opciones_detalles[] = $row;
        }
    }
    
    // Headers para visualizar en el navegador como HTML
    header('Content-Type: text/html; charset=utf-8');
    
    // Formatear fecha
    $fecha_formateada = date('d/m/Y', strtotime($presupuesto['created_at']));
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Presupuesto <?php echo htmlspecialchars($presupuesto['numero_presupuesto']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            line-height: 1.6;
            background: #f8f9fa;
        }
        
        .print-bar {
            background: #fff;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid #e9ecef;
        }
        
                 .button {
             background: #4f4f4f;
             color: white;
             border: none;
             padding: 12px 24px;
             border-radius: 6px;
             cursor: pointer;
             font-size: 14px;
             font-weight: 500;
             margin: 0 8px;
             text-decoration: none;
             display: inline-block;
             transition: background 0.2s;
         }
         
         .button:hover {
             background: #e5554a;
         }
        
        .button.secondary {
            background: #6b7280;
        }
        
        .button.secondary:hover {
            background: #4b5563;
        }
        
        .content {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            min-height: calc(100vh - 80px);
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .document {
            padding: 40px;
        }
        
                 .header {
             text-align: center;
             border-bottom: 3px solid #4f4f4f;
             padding-bottom: 20px;
             margin-bottom: 30px;
         }
         
         .company-name {
             font-size: 28px;
             font-weight: bold;
             color: #4f4f4f;
             margin-bottom: 10px;
         }
        
        .document-title {
            font-size: 24px;
            margin: 20px 0;
            color: #1a1a1a;
        }
        
        .quote-number {
            font-size: 18px;
            color: #666;
        }
        
        .info-section {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            gap: 30px;
        }
        
        .client-info, .quote-info {
            flex: 1;
        }
        
                 .section-title {
             font-size: 16px;
             font-weight: bold;
             color: #4f4f4f;
             border-bottom: 2px solid #e5e5e5;
             padding-bottom: 8px;
             margin-bottom: 15px;
         }
        
        .info-row {
            margin: 10px 0;
            padding: 5px 0;
        }
        
        .label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
            color: #555;
        }
        
        .observaciones-section {
            margin: 30px 0;
        }
        
                 .observaciones-content {
             background: #f8f9fa;
             padding: 20px;
             border-radius: 8px;
             border-left: 4px solid #4f4f4f;
             margin-top: 10px;
             white-space: pre-wrap;
             line-height: 1.6;
         }
        
        .items-section {
            margin: 40px 0;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .items-table th {
            background: #f8f9fa;
            padding: 15px 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: bold;
            color: #495057;
        }
        
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .items-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .totals-section {
            margin: 40px 0;
            text-align: right;
        }
        
        .totals-table {
            margin-left: auto;
            width: 300px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .totals-table td {
            padding: 12px 20px;
            border-bottom: 1px solid #e5e5e5;
            background: white;
        }
        
        .totals-table tr:last-child td {
            border-bottom: none;
        }
        
                 .total-final {
             font-size: 18px;
             font-weight: bold;
             background: #4f4f4f !important;
             color: white !important;
         }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            color: #666;
            font-size: 14px;
            border-top: 2px solid #e5e5e5;
            padding-top: 30px;
        }
        
        .footer p {
            margin: 8px 0;
        }
        
        @media print {
            .print-bar { 
                display: none !important; 
            }
            
            body { 
                background: white; 
            }
            
            .content {
                box-shadow: none;
                max-width: none;
                margin: 0;
            }
            
            .document {
                padding: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .info-section {
                flex-direction: column;
                gap: 20px;
            }
            
            .document {
                padding: 20px;
            }
            
            .button {
                margin: 5px;
                padding: 10px 16px;
                font-size: 13px;
            }
        }
    </style>
    <script>
        function printDocument() {
            window.print();
        }
        
        function goBack() {
            window.location.href = "../cotizador.php";
        }
        
        function downloadPDF() {
            // Algunos navegadores permiten guardar como PDF al imprimir
            window.print();
        }
    </script>
</head>
<body>
    <div class="print-bar">
        <button class="button" onclick="printDocument()">📄 Imprimir</button>
        <button class="button" onclick="downloadPDF()">💾 Guardar como PDF</button>
        <button class="button secondary" onclick="goBack()">← Volver al Cotizador</button>
    </div>
    
    <div class="content">
        <div class="document">
            <div class="header">
                <div class="company-name">Sistema de Cotización de Ascensores</div>
                <div class="document-title">PRESUPUESTO</div>
                <div class="quote-number"><?php echo htmlspecialchars($presupuesto['numero_presupuesto']); ?></div>
            </div>
            
            <div class="info-section">
                <div class="client-info">
                    <div class="section-title">Datos del Cliente</div>
                    <div class="info-row">
                        <span class="label">Nombre:</span> <?php echo htmlspecialchars($presupuesto['cliente_nombre']); ?>
                    </div>
                    <div class="info-row">
                        <span class="label">Email:</span> <?php echo htmlspecialchars($presupuesto['cliente_email']); ?>
                    </div>
                    <?php if (!empty($presupuesto['cliente_telefono'])): ?>
                    <div class="info-row">
                        <span class="label">Teléfono:</span> <?php echo htmlspecialchars($presupuesto['cliente_telefono']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($presupuesto['cliente_empresa'])): ?>
                    <div class="info-row">
                        <span class="label">Empresa:</span> <?php echo htmlspecialchars($presupuesto['cliente_empresa']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="quote-info">
                    <div class="section-title">Información del Presupuesto</div>
                    <div class="info-row">
                        <span class="label">Fecha:</span> <?php echo $fecha_formateada; ?>
                    </div>
                    <div class="info-row">
                        <span class="label">Estado:</span> Pendiente
                    </div>
                    <div class="info-row">
                        <span class="label">Validez:</span> 30 días
                    </div>
                </div>
            </div>
            
            <?php if (!empty($presupuesto['observaciones'])): ?>
            <div class="observaciones-section">
                <div class="section-title">Observaciones del Cliente</div>
                <div class="observaciones-content">
                    <?php echo htmlspecialchars($presupuesto['observaciones']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="items-section">
                <div class="section-title">Configuración del Ascensor</div>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Descripción</th>
                            <th style="width: 120px; text-align: right;">Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($opciones_detalles)): ?>
                            <?php $i = 1; foreach ($opciones_detalles as $opcion): ?>
                                <?php 
                                // Ya no necesitamos calcular el precio, lo obtenemos directamente.
                                $precio = $opcion['precio'] ?? 0;
                                
                                // Si es un descuento, mostramos el porcentaje en lugar del precio.
                                if ($opcion['categoria_nombre'] === 'Descuentos') {
                                    $display_text = htmlspecialchars($opcion['nombre']) . " (" . (float)$opcion['descuento'] . "%)";
                                    $display_price = "-";
                                } else {
                                    $display_text = htmlspecialchars($opcion['nombre']);
                                    $display_price = "AR$" . number_format($precio, 2, ',', '.');
                                }
                                ?>
                                <tr>
                                    <td><?php echo $i; ?></td>
                                    <td><?php echo $display_text; ?></td>
                                    <td style="text-align: right;"><?php echo $display_price; ?></td>
                                </tr>
                            <?php $i++; endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td>1</td>
                                <td>Configuración personalizada de ascensor</td>
                                <td style="text-align: right;">AR$<?php echo number_format($presupuesto['subtotal'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="totals-section">
                <table class="totals-table">
                    <tr>
                        <td>Subtotal:</td>
                        <td style="text-align: right;">AR$<?php echo number_format($presupuesto['subtotal'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php if ($presupuesto['descuento_porcentaje'] > 0): ?>
                    <tr>
                        <td>Descuento (<?php echo $presupuesto['descuento_porcentaje']; ?>%):</td>
                        <td style="text-align: right; color: #dc3545;">-AR$<?php echo number_format($presupuesto['descuento_monto'], 2, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-final">
                        <td>TOTAL:</td>
                        <td style="text-align: right;">AR$<?php echo number_format($presupuesto['total'], 2, ',', '.'); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="footer">
                <p><strong>Este presupuesto es válido por 30 días desde la fecha de emisión.</strong></p>
                <p>Para cualquier consulta, no dude en contactarnos.</p>
                <p><em>¡Gracias por confiar en nosotros!</em></p>
            </div>
        </div>
    </div>
</body>
</html> 
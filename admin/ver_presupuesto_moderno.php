<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Obtener ID del presupuesto
$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: presupuestos.php');
    exit;
}

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
    die("Error: No se pudo encontrar el archivo de configuración en ninguna ubicación");
}

// Cargar DB - buscar en múltiples ubicaciones
$dbPaths = [
    __DIR__ . '/../sistema/includes/db.php',   // Local
    __DIR__ . '/../includes/db.php',           // Railway alternativo
];

foreach ($dbPaths as $dbPath) {
    if (file_exists($dbPath)) {
        require_once $dbPath;
        break;
    }
}

// Obtener presupuesto
$presupuesto = null;
$items = [];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Obtener presupuesto
    $stmt = $conn->prepare("SELECT * FROM presupuestos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $presupuesto = $result->fetch_assoc();
        
        // Obtener items del presupuesto
        // Verificar si existe opciones_json
        if (isset($presupuesto['opciones_json']) && !empty($presupuesto['opciones_json'])) {
            // Método 1: Desde opciones_json
            $opciones_json = $presupuesto['opciones_json'];
            $opciones_data = json_decode($opciones_json, true);
            
            if (is_array($opciones_data)) {
                if (isset($opciones_data['opciones_ids'])) {
                    $opciones_ids = $opciones_data['opciones_ids'];
                } else {
                    $opciones_ids = $opciones_data;
                }
                
                if (!empty($opciones_ids)) {
                    $placeholders = str_repeat('?,', count($opciones_ids) - 1) . '?';
                    $query = "SELECT o.*, c.nombre as categoria_nombre 
                             FROM opciones o 
                             LEFT JOIN categorias c ON o.categoria_id = c.id 
                             WHERE o.id IN ($placeholders)";
                    
                    $stmt = $conn->prepare($query);
                    $types = str_repeat('i', count($opciones_ids));
                    $stmt->bind_param($types, ...$opciones_ids);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        $items[] = $row;
                    }
                }
            }
        } else {
            // Método 2: Desde tabla presupuesto_detalles
            $query = "SELECT o.nombre, o.descuento, c.nombre as categoria_nombre, pd.precio 
                     FROM presupuesto_detalles pd
                     JOIN opciones o ON pd.opcion_id = o.id
                     LEFT JOIN categorias c ON o.categoria_id = c.id 
                     WHERE pd.presupuesto_id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
        }
    } else {
        header('Location: presupuestos.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    header('Location: presupuestos.php');
    exit;
}

// Usar los totales guardados en el presupuesto en lugar de recalcularlos
$subtotal = $presupuesto['subtotal'] ?? 0;
$descuento_porcentaje = $presupuesto['descuento_porcentaje'] ?? 0;
$descuento = $presupuesto['descuento_monto'] ?? 0;
$total = $presupuesto['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Presupuesto #<?php echo $id; ?> - Panel Admin</title>
    <link rel="stylesheet" href="../assets/css/modern-dark-theme.css">
    <style>
        .dashboard-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .content-wrapper {
            flex: 1;
            padding: var(--spacing-xl);
            overflow-y: auto;
        }

        /* Header del presupuesto */
        .quote-header {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-lg);
        }

        .quote-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-lg);
        }

        .quote-id-section {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .quote-id {
            font-size: var(--text-2xl);
            font-weight: 700;
            color: var(--text-primary);
        }

        .quote-status {
            display: inline-flex;
            align-items: center;
            padding: var(--spacing-xs) var(--spacing-md);
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            font-weight: 500;
            gap: var(--spacing-xs);
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }

        .quote-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }

        .meta-label {
            font-size: var(--text-xs);
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-value {
            font-size: var(--text-base);
            color: var(--text-primary);
            font-weight: 500;
        }

        /* Cliente info */
        .client-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .client-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }

        .client-avatar {
            width: 48px;
            height: 48px;
            background: var(--accent-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: var(--text-lg);
        }

        .client-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md);
        }

        /* Items del presupuesto */
        .items-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: var(--spacing-lg);
        }

        .items-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .items-table {
            width: 100%;
        }

        .item-row {
            display: grid;
            grid-template-columns: 60px 2fr 3fr 120px 120px;
            padding: var(--spacing-md) var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
            align-items: center;
        }

        .item-header {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: var(--text-xs);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .item-row:hover:not(.item-header) {
            background: var(--bg-hover);
        }

        .item-number {
            color: var(--text-muted);
            font-size: var(--text-sm);
        }

        .item-category {
            font-size: var(--text-xs);
            color: var(--text-secondary);
        }

        .item-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .item-price {
            font-family: var(--font-mono);
            color: var(--accent-success);
            text-align: right;
        }

        /* Totales */
        .totals-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            max-width: 400px;
            margin-left: auto;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: var(--spacing-sm) 0;
            font-size: var(--text-sm);
        }

        .total-row.subtotal {
            color: var(--text-secondary);
        }

        .total-row.discount {
            color: var(--accent-warning);
        }

        .total-row.final {
            border-top: 2px solid var(--border-color);
            padding-top: var(--spacing-md);
            margin-top: var(--spacing-sm);
            font-size: var(--text-lg);
            font-weight: 700;
        }

        .total-row.final .total-value {
            color: var(--accent-primary);
            font-size: var(--text-xl);
        }

        /* Actions */
        .actions-section {
            display: flex;
            gap: var(--spacing-md);
            justify-content: flex-end;
            margin-top: var(--spacing-lg);
        }

        /* Timeline */
        .timeline-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
        }

        .timeline-item {
            display: flex;
            gap: var(--spacing-md);
            padding: var(--spacing-md) 0;
            position: relative;
        }

        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 20px;
            top: 40px;
            bottom: -20px;
            width: 2px;
            background: var(--border-color);
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            background: var(--bg-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            z-index: 1;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
        }

        .timeline-date {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1 style="font-size: var(--text-xl); display: flex; align-items: center; gap: var(--spacing-sm);">
                    <span id="logo-icon"></span>
                    Panel Admin
                </h1>
            </div>
            
            <nav class="sidebar-menu">
                <a href="index.php" class="sidebar-item">
                    <span id="nav-dashboard-icon"></span>
                    <span>Dashboard</span>
                </a>
                <a href="gestionar_datos.php" class="sidebar-item">
                    <span id="nav-data-icon"></span>
                    <span>Gestionar Datos</span>
                </a>
                <a href="presupuestos.php" class="sidebar-item active">
                    <span id="nav-quotes-icon"></span>
                    <span>Presupuestos</span>
                </a>

                <div style="margin-top: auto; padding: var(--spacing-md);">
                    <a href="../cotizador.php" class="sidebar-item" target="_blank">
                        <span id="nav-calculator-icon"></span>
                        <span>Ir al Cotizador</span>
                    </a>
                    <a href="index.php?logout=1" class="sidebar-item" style="color: var(--accent-danger);">
                        <span id="nav-logout-icon"></span>
                        <span>Cerrar Sesión</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header" style="background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); padding: var(--spacing-lg) var(--spacing-xl);">
                <div class="header-grid" style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: var(--spacing-md);">
                        <a href="presupuestos.php" class="btn btn-icon btn-secondary">
                            <span id="back-icon"></span>
                        </a>
                        <div>
                            <h2 class="header-title" style="font-size: var(--text-lg); font-weight: 600;">Detalle del Presupuesto</h2>
                            <p class="header-subtitle" style="font-size: var(--text-sm); color: var(--text-secondary);">Información completa del presupuesto #<?php echo $id; ?></p>
                        </div>
                    </div>
                    
                    <div class="header-actions" style="display: flex; gap: var(--spacing-md);">
                        <button class="btn btn-secondary" onclick="imprimirPresupuesto()">
                            <span id="print-icon"></span>
                            Imprimir
                        </button>
                        <button class="btn btn-primary" onclick="descargarPDF()">
                            <span id="pdf-icon"></span>
                            Descargar PDF
                        </button>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content-wrapper">
                <!-- Header del presupuesto -->
                <div class="quote-header">
                    <div class="quote-header-top">
                        <div class="quote-id-section">
                            <h1 class="quote-id">Presupuesto #<?php echo $presupuesto['id']; ?></h1>
                        </div>
                        <div>
                            <span class="badge badge-info">
                                <span id="delivery-icon"></span>
                                <?php echo $presupuesto['plazo_entrega']; ?> días
                            </span>
                        </div>
                    </div>
                    
                    <div class="quote-meta">
                        <div class="meta-item">
                            <span class="meta-label">Fecha de creación</span>
                            <span class="meta-value">
                                <?php 
                                $fecha = new DateTime($presupuesto['created_at']);
                                echo $fecha->format('d/m/Y H:i');
                                ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Total de items</span>
                            <span class="meta-value"><?php echo count($items); ?> productos</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Valor total</span>
                            <span class="meta-value" style="color: var(--accent-success); font-size: var(--text-lg);">
                                $<?php echo number_format($presupuesto['total'], 2, ',', '.'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Información del cliente -->
                <div class="client-section">
                    <div class="client-header">
                        <div class="client-avatar">
                            <?php echo strtoupper(substr($presupuesto['cliente_nombre'] ?? $presupuesto['nombre_cliente'] ?? 'C', 0, 1)); ?>
                        </div>
                        <h3>Información del Cliente</h3>
                    </div>
                    
                    <div class="client-details">
                        <div class="meta-item">
                            <span class="meta-label">Nombre</span>
                            <span class="meta-value"><?php echo htmlspecialchars($presupuesto['cliente_nombre'] ?? $presupuesto['nombre_cliente'] ?? 'No especificado'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Email</span>
                            <span class="meta-value"><?php echo htmlspecialchars($presupuesto['cliente_email'] ?? $presupuesto['email_cliente'] ?? 'No especificado'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Teléfono</span>
                            <span class="meta-value"><?php echo htmlspecialchars($presupuesto['cliente_telefono'] ?? $presupuesto['telefono_cliente'] ?? 'No especificado'); ?></span>
                        </div>
                        <?php if (!empty($presupuesto['ubicacion_obra'])): ?>
                        <div class="meta-item">
                            <span class="meta-label">Ubicación de la obra</span>
                            <span class="meta-value"><?php echo htmlspecialchars($presupuesto['ubicacion_obra']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($presupuesto['observaciones'])): ?>
                        <div class="meta-item" style="grid-column: 1 / -1;">
                            <span class="meta-label">Observaciones del cliente</span>
                            <div class="meta-value" style="background: var(--bg-secondary); padding: var(--spacing-md); border-radius: var(--radius-md); margin-top: var(--spacing-xs); white-space: pre-wrap; line-height: 1.5;">
                                <?php echo htmlspecialchars($presupuesto['observaciones']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Items del presupuesto -->
                <div class="items-section">
                    <div class="items-header">
                        <h3>Productos Incluidos</h3>
                        <span class="badge badge-primary"><?php echo count($items); ?> items</span>
                    </div>
                    
                    <div class="items-table">
                        <div class="item-row item-header">
                            <div>#</div>
                            <div>Categoría</div>
                            <div>Producto</div>
                            <div style="text-align: right;">Precio Unit.</div>
                            <div style="text-align: right;">Total</div>
                        </div>
                        
                        <?php 
                        $num = 1;
                        foreach ($items as $item): 
                            // El precio ahora viene directamente de la consulta como 'precio'
                            $precio = $item['precio'] ?? 0;
                        ?>
                        <div class="item-row">
                            <div class="item-number"><?php echo $num++; ?></div>
                            <div class="item-category">
                                <span class="badge badge-info">
                                    <?php echo htmlspecialchars($item['categoria_nombre'] ?? 'Sin categoría'); ?>
                                </span>
                            </div>
                            <div class="item-name"><?php echo htmlspecialchars($item['nombre']); ?></div>
                            <div class="item-price">
                                <?php 
                                if (isset($item['categoria_nombre']) && $item['categoria_nombre'] === 'Descuentos' && isset($item['descuento']) && $item['descuento'] > 0) {
                                    echo (float)$item['descuento'] . '% desc.';
                                } else {
                                    echo '$' . number_format($precio, 2, ',', '.');
                                }
                                ?>
                            </div>
                            <div class="item-price">
                                <?php 
                                if (isset($item['categoria_nombre']) && $item['categoria_nombre'] === 'Descuentos') {
                                    echo '-';
                                } else {
                                    echo '$' . number_format($precio, 2, ',', '.');
                                }
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Totales -->
                <div class="totals-section">
                    <div class="total-row subtotal">
                        <span>Subtotal</span>
                        <span>$<?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                    </div>
                    
                    <?php if ($descuento_porcentaje > 0): ?>
                    <div class="total-row discount">
                        <span>Descuento (<?php echo $descuento_porcentaje; ?>%)</span>
                        <span>-$<?php echo number_format($descuento, 2, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="total-row final">
                        <span>Total</span>
                        <span class="total-value">$<?php echo number_format($total, 2, ',', '.'); ?></span>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="timeline-section" style="margin-top: var(--spacing-lg);">
                    <h3 style="margin-bottom: var(--spacing-lg);">Historial</h3>
                    
                    <div class="timeline-item">
                        <div class="timeline-icon" style="background: var(--accent-success); color: white;">
                            <span id="timeline-created-icon"></span>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title">Presupuesto creado</div>
                            <div class="timeline-date">
                                <?php echo $fecha->format('d/m/Y H:i'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Timeline item removido: ya no mostramos estados -->
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/modern-icons.js"></script>
    <script>
        // Cargar iconos
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar
            document.getElementById('logo-icon').innerHTML = modernUI.getIcon('chart');
            document.getElementById('nav-dashboard-icon').innerHTML = modernUI.getIcon('dashboard');
            document.getElementById('nav-data-icon').innerHTML = modernUI.getIcon('settings');
            document.getElementById('nav-quotes-icon').innerHTML = modernUI.getIcon('document');
            document.getElementById('nav-calculator-icon').innerHTML = modernUI.getIcon('cart');
            document.getElementById('nav-logout-icon').innerHTML = modernUI.getIcon('logout');
            
            // Header
            document.getElementById('back-icon').innerHTML = modernUI.getIcon('arrowRight');
            document.getElementById('print-icon').innerHTML = modernUI.getIcon('document');
            document.getElementById('pdf-icon').innerHTML = modernUI.getIcon('pdf');
            
            // Quote
            // document.getElementById('status-icon').innerHTML = modernUI.getIcon('clock', 'icon-sm');
            document.getElementById('delivery-icon').innerHTML = modernUI.getIcon('calendar', 'icon-sm');
            
            // Timeline
            document.getElementById('timeline-created-icon').innerHTML = modernUI.getIcon('check');
            // document.getElementById('timeline-pending-icon').innerHTML = modernUI.getIcon('clock');
        });

        function descargarPDF() {
            window.open('../api/download_pdf.php?id=<?php echo $id; ?>', '_blank');
        }

        function imprimirPresupuesto() {
            window.open('../api/download_pdf.php?id=<?php echo $id; ?>', '_blank');
        }
    </script>
</body>
</html> 
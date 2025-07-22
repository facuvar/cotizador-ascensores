<?php
session_start();

// Cargar configuración - buscar en múltiples ubicaciones
$configPaths = [
    __DIR__ . '/../config.php',           // Railway (raíz del proyecto)
    __DIR__ . '/../sistema/config.php',   // Local (dentro de sistema)
];

$configLoaded = false;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        try {
            require_once $configPath;
            $configLoaded = true;
            break;
        } catch (Exception $e) {
            continue;
        }
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

$dbConnected = false;
foreach ($dbPaths as $dbPath) {
    if (file_exists($dbPath)) {
        try {
            require_once $dbPath;
            $dbConnected = true;
            break;
        } catch (Exception $e) {
            if (defined('IS_RAILWAY') && IS_RAILWAY) {
                railway_log("Error loading db.php in dashboard: " . $e->getMessage());
            }
            continue;
        }
    }
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Inicializar estadísticas
$totalPresupuestos = 0;
$totalProductos = 0;
$totalOpciones = 0;
$ultimosPresupuestos = null;

// Obtener estadísticas solo si la DB está conectada
if ($dbConnected) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();

        // Contar presupuestos
        $query = "SELECT COUNT(*) as total FROM presupuestos";
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $totalPresupuestos = $row['total'];
        }

        // Contar productos
        $query = "SELECT COUNT(*) as total FROM xls_productos";
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $totalProductos = $row['total'];
        }

        // Contar opciones
        $query = "SELECT COUNT(*) as total FROM xls_opciones";
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $totalOpciones = $row['total'];
        }

        // Obtener últimos presupuestos
        $query = "SELECT * FROM presupuestos ORDER BY fecha_creacion DESC LIMIT 5";
        $ultimosPresupuestos = $conn->query($query);
        
    } catch (Exception $e) {
        if (defined('IS_RAILWAY') && IS_RAILWAY) {
            railway_log("Error getting stats: " . $e->getMessage());
        }
    }
}

// Procesar cierre de sesión
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Sistema de Presupuestos</title>
    <link rel="stylesheet" href="../assets/css/modern-dark-theme.css?v=<?php echo time(); ?>">
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

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-value {
            font-size: var(--text-3xl);
            font-weight: 700;
            color: var(--accent-primary);
            margin-bottom: var(--spacing-sm);
        }

        .stat-label {
            font-size: var(--text-sm);
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .recent-quotes {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .recent-quotes-header {
            background: var(--bg-secondary);
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
        }

        .recent-quotes-title {
            font-size: var(--text-lg);
            font-weight: 600;
            margin: 0;
        }

        .quote-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--spacing-md) var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
        }

        .quote-item:last-child {
            border-bottom: none;
        }

        .quote-info {
            flex: 1;
        }

        .quote-client {
            font-weight: 500;
            margin-bottom: 2px;
        }

        .quote-date {
            font-size: var(--text-xs);
            color: var(--text-secondary);
        }

        .quote-total {
            font-family: var(--font-mono);
            font-weight: 600;
            color: var(--accent-success);
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
                <a href="index.php" class="sidebar-item active">
                    <span id="nav-dashboard-icon"></span>
                    <span>Dashboard</span>
                </a>
                <a href="gestionar_datos.php" class="sidebar-item">
                    <span id="nav-data-icon"></span>
                    <span>Gestionar Datos</span>
                </a>
                <a href="presupuestos.php" class="sidebar-item">
                    <span id="nav-quotes-icon"></span>
                    <span>Presupuestos</span>
                </a>
                <a href="ajustar_precios.php" class="sidebar-item">
                    <span id="nav-prices-icon"></span>
                    <span>Ajustar Precios</span>
                </a>
                <a href="gestionar_reglas_exclusion_dual.php" class="sidebar-item">
                    <span id="nav-rules-icon"></span>
                    <span>Reglas de Exclusión</span>
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
                    <div>
                        <h2 class="header-title" style="font-size: var(--text-lg); font-weight: 600;">Dashboard</h2>
                        <p class="header-subtitle" style="font-size: var(--text-sm); color: var(--text-secondary);">Panel de control principal del sistema</p>
                    </div>
                    
                    <div class="header-actions" style="display: flex; gap: var(--spacing-md);">
                        <a href="../cotizador.php" target="_blank" class="btn btn-secondary">
                            <span id="calculator-icon"></span>
                            Ir al Cotizador
                        </a>
                        <a href="gestionar_datos.php" class="btn btn-primary">
                            <span id="data-icon"></span>
                            Gestionar Datos
                        </a>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content-wrapper">
                <?php if (!$dbConnected): ?>
                    <div class="alert alert-warning fade-in">
                        <span id="warning-icon"></span>
                        <strong>Advertencia:</strong> Conexión a base de datos limitada.
                        <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'railway'): ?>
                            Verificando conexión a Railway MySQL...
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalPresupuestos; ?></div>
                        <div class="stat-label">Presupuestos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalProductos; ?></div>
                        <div class="stat-label">Productos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalOpciones; ?></div>
                        <div class="stat-label">Opciones</div>
                    </div>
                </div>
                
                <!-- Recent Quotes -->
                <?php if ($ultimosPresupuestos && $ultimosPresupuestos->num_rows > 0): ?>
                <div class="recent-quotes">
                    <div class="recent-quotes-header">
                        <h3 class="recent-quotes-title">Últimos Presupuestos</h3>
                    </div>
                    <?php while ($presupuesto = $ultimosPresupuestos->fetch_assoc()): ?>
                    <div class="quote-item">
                        <div class="quote-info">
                            <div class="quote-client"><?php echo htmlspecialchars($presupuesto['cliente_nombre'] ?? 'Cliente'); ?></div>
                            <div class="quote-date"><?php echo date('d/m/Y H:i', strtotime($presupuesto['created_at'] ?? $presupuesto['fecha_creacion'] ?? 'now')); ?></div>
                        </div>
                        <div class="quote-total">$<?php echo number_format($presupuesto['total'] ?? 0, 2); ?></div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="stat-card">
                    <div style="text-align: center; color: var(--text-secondary);">
                        <div style="font-size: 3rem; margin-bottom: var(--spacing-md); opacity: 0.3;">📊</div>
                        <h3>No hay presupuestos aún</h3>
                        <p>Los presupuestos generados aparecerán aquí</p>
                        <a href="../cotizador.php" target="_blank" class="btn btn-primary" style="margin-top: var(--spacing-md);">
                            Crear primer presupuesto
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="stat-card">
                    <h3 style="margin-bottom: var(--spacing-lg);">Acciones Rápidas</h3>
                    <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap;">
                        <a href="gestionar_datos.php" class="btn btn-primary">
                            <span id="data-icon-2"></span>
                            Gestionar Datos
                        </a>
                        <a href="presupuestos.php" class="btn btn-secondary">
                            <span id="quotes-icon-2"></span>
                            Ver Presupuestos
                        </a>
                        <a href="ajustar_precios.php" class="btn btn-secondary">
                            <span id="prices-icon-2"></span>
                            Ajustar Precios
                        </a>
                        <a href="../cotizador.php" target="_blank" class="btn btn-success">
                            <span id="calculator-icon-2"></span>
                            Ir al Cotizador
                        </a>
                    </div>
                </div>
                
                <!-- System Info -->
                <div class="stat-card">
                    <h3 style="margin-bottom: var(--spacing-lg);">Estado del Sistema</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
                        <div>
                            <p><strong>Entorno:</strong> 
                                <?php echo defined('ENVIRONMENT') && ENVIRONMENT === 'railway' ? '🚂 Railway' : '💻 Local'; ?>
                            </p>
                            <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                            <p><strong>Base de datos:</strong> 
                                <?php echo $dbConnected ? '✅ Conectada' : '❌ Desconectada'; ?>
                            </p>
                        </div>
                        <div>
                            <p><strong>Usuario:</strong> <?php echo $_SESSION['admin_user'] ?? 'admin'; ?></p>
                            <p><strong>Sesión iniciada:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                            <?php if (defined('BASE_URL')): ?>
                                <p><strong>URL Base:</strong> <?php echo BASE_URL; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Icons Script -->
    <script src="../assets/js/modern-icons.js"></script>
    <script>
        // Cargar iconos
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar
            document.getElementById('logo-icon').innerHTML = modernUI.getIcon('chart');
            document.getElementById('nav-dashboard-icon').innerHTML = modernUI.getIcon('dashboard');
            document.getElementById('nav-data-icon').innerHTML = modernUI.getIcon('settings');
            document.getElementById('nav-quotes-icon').innerHTML = modernUI.getIcon('document');
            document.getElementById('nav-prices-icon').innerHTML = modernUI.getIcon('dollar');
            document.getElementById('nav-rules-icon').innerHTML = modernUI.getIcon('check-shield');
            document.getElementById('nav-calculator-icon').innerHTML = modernUI.getIcon('cart');
            document.getElementById('nav-logout-icon').innerHTML = modernUI.getIcon('logout');
            
            // Header
            const calculatorIcon = document.getElementById('calculator-icon');
            if (calculatorIcon) calculatorIcon.innerHTML = modernUI.getIcon('cart');
            const dataIcon = document.getElementById('data-icon');
            if (dataIcon) dataIcon.innerHTML = modernUI.getIcon('settings');
            
            // Alerts
            const warningIcon = document.getElementById('warning-icon');
            if (warningIcon) warningIcon.innerHTML = modernUI.getIcon('warning');
            
            // Quick Actions
            const dataIcon2 = document.getElementById('data-icon-2');
            if (dataIcon2) dataIcon2.innerHTML = modernUI.getIcon('settings');
            const quotesIcon2 = document.getElementById('quotes-icon-2');
            if (quotesIcon2) quotesIcon2.innerHTML = modernUI.getIcon('document');
            const pricesIcon2 = document.getElementById('prices-icon-2');
            if (pricesIcon2) pricesIcon2.innerHTML = modernUI.getIcon('dollar');
            const calculatorIcon2 = document.getElementById('calculator-icon-2');
            if (calculatorIcon2) calculatorIcon2.innerHTML = modernUI.getIcon('cart');
        });
    </script>
</body>
</html>

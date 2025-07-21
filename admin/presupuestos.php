<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
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

// Obtener presupuestos
$presupuestos = [];
$filtro = $_GET['filtro'] ?? 'todos';
$busqueda = $_GET['busqueda'] ?? '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 10;
$total_presupuestos = 0;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $query_base = "SELECT * FROM presupuestos WHERE 1=1";
    $query_count = "SELECT COUNT(*) as total FROM presupuestos WHERE 1=1";
    $where = "";
    // Aplicar filtros
    if ($busqueda) {
        $where .= " AND (cliente_nombre LIKE '%$busqueda%' OR cliente_email LIKE '%$busqueda%' OR id = '$busqueda')";
    }
    switch ($filtro) {
        case 'hoy':
            $where .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'semana':
            $where .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'mes':
            $where .= " AND MONTH(created_at) = MONTH(CURRENT_DATE())";
            break;
    }
    $query_base .= $where . " ORDER BY created_at DESC";
    $query_count .= $where;

    // Obtener total de presupuestos para paginación
    $result_count = $conn->query($query_count);
    if ($result_count && $row = $result_count->fetch_assoc()) {
        $total_presupuestos = intval($row['total']);
    }
    $total_paginas = max(1, ceil($total_presupuestos / $por_pagina));
    $offset = ($pagina - 1) * $por_pagina;
    $query_paginada = $query_base . " LIMIT $por_pagina OFFSET $offset";

    $result = $conn->query($query_paginada);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $presupuestos[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
}

// Calcular estadísticas
$stats = [
    'total' => count($presupuestos),
    'total_monto' => array_sum(array_column($presupuestos, 'total')),
    'promedio' => count($presupuestos) > 0 ? array_sum(array_column($presupuestos, 'total')) / count($presupuestos) : 0
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuestos - Panel Admin</title>
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

        /* Filtros */
        .filters-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .filters-row {
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            gap: var(--spacing-sm);
        }

        .filter-button {
            padding: var(--spacing-sm) var(--spacing-md);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            font-size: var(--text-sm);
        }

        .filter-button:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .filter-button.active {
            background: var(--accent-primary);
            color: white;
            border-color: var(--accent-primary);
        }

        /* Search box mejorado */
        .search-section {
            flex: 1;
            max-width: 400px;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: var(--spacing-sm) var(--spacing-md);
        }

        .search-box input {
            background: transparent;
            border: none;
            color: var(--text-primary);
            outline: none;
            flex: 1;
        }

        /* Estadísticas rápidas */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .quick-stat {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .quick-stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-primary);
        }

        .quick-stat-content {
            flex: 1;
        }

        .quick-stat-value {
            font-size: var(--text-xl);
            font-weight: 700;
            color: var(--text-primary);
        }

        .quick-stat-label {
            font-size: var(--text-xs);
            color: var(--text-secondary);
        }

        /* Tabla mejorada */
        .presupuestos-table {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            min-width: 1000px;
            width: 100%;
        }

        .table-header {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: var(--text-xs);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-row {
            display: grid;
            grid-template-columns: 70px 1fr 160px 80px 110px 90px 200px;
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            align-items: center;
            transition: background 0.2s ease;
            min-width: 800px;
        }

        .table-row:hover:not(.table-header) {
            background: var(--bg-hover);
        }

        .table-cell {
            padding: 0 var(--spacing-sm);
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .customer-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .customer-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .customer-email {
            font-size: var(--text-xs);
            color: var(--text-secondary);
        }

        .amount-cell {
            font-family: var(--font-mono);
            font-weight: 600;
            color: var(--accent-success);
            text-align: left;
            padding-right: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .badge-info {
            white-space: nowrap;
            display: inline-block;
        }

        .date-cell {
            font-size: var(--text-xs);
            color: var(--text-secondary);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-sm);
            font-size: var(--text-xs);
            font-weight: 500;
            gap: var(--spacing-xs);
        }

        /* .status-badge.pendiente {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        } */

        .status-badge.completado {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .status-badge.cancelado {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: calc(var(--spacing-xl) * 3);
            color: var(--text-muted);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: var(--spacing-lg);
            opacity: 0.3;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-lg);
        }

        .page-link {
            padding: var(--spacing-sm) var(--spacing-md);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .page-link:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .page-link.active {
            background: var(--accent-primary);
            color: white;
            border-color: var(--accent-primary);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .table-row {
                grid-template-columns: 60px 1fr 140px 70px 90px 80px 180px;
                min-width: 720px;
            }
            
            .btn-sm {
                padding: var(--spacing-xs) var(--spacing-sm);
            }
        }
        
        @media (max-width: 768px) {
            .table-row {
                grid-template-columns: 45px 1fr 110px 60px 80px 70px 170px;
                font-size: var(--text-xs);
                min-width: 635px;
            }
            
            .btn-sm {
                min-width: 28px !important;
                padding: 4px !important;
            }
            
            .presupuestos-table {
                min-width: 650px;
            }
        }
        .btn-disabled, .btn:disabled {
            opacity: 0.5 !important;
            pointer-events: none !important;
            cursor: not-allowed !important;
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
                <a href="ajustar_precios.php" class="sidebar-item">
                    <span id="nav-prices-icon"></span>
                    <span>Ajustar Precios</span>
                </a>
                <a href="gestionar_reglas_exclusion_dual.php" class="sidebar-item">
                    <span id="nav-rules-icon"></span>
                    <span>Reglas de Exclusión (Dual)</span>
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
                        <h2 class="header-title" style="font-size: var(--text-lg); font-weight: 600;">Presupuestos
<?php if (!empty($_SESSION['is_demo'])): ?>
    <span style="color: #fff; font-weight: 600; font-size: 1rem; margin-left: 1.5rem;">Modo Demo - Las acciones se encuentran deshabilitadas</span>
<?php endif; ?>
</h2>
                        <p class="header-subtitle" style="font-size: var(--text-sm); color: var(--text-secondary);">Gestiona todos los presupuestos generados</p>
                    </div>
                    
                    <div class="header-actions" style="display: flex; gap: var(--spacing-md);">
                        <a href="../cotizador.php" class="btn btn-primary" target="_blank">
                            <span id="new-icon"></span>
                            Nuevo Presupuesto
                        </a>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content-wrapper">
                <!-- Mensajes de éxito o error -->
                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; border-radius: var(--radius-md); padding: var(--spacing-md); margin-bottom: var(--spacing-lg);">
                    <div style="display: flex; align-items: center; gap: var(--spacing-sm);">
                        <div id="success-icon"></div>
                        <div><?php echo $_SESSION['success_message']; ?></div>
                    </div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; border-radius: var(--radius-md); padding: var(--spacing-md); margin-bottom: var(--spacing-lg);">
                    <div style="display: flex; align-items: center; gap: var(--spacing-sm);">
                        <div id="error-icon"></div>
                        <div><?php echo $_SESSION['error_message']; ?></div>
                    </div>
                </div>
                <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="quick-stat">
                        <div class="quick-stat-icon">
                            <span id="stat-total-icon"></span>
                        </div>
                        <div class="quick-stat-content">
                            <div class="quick-stat-value"><?php echo $stats['total']; ?></div>
                            <div class="quick-stat-label">Total Presupuestos</div>
                        </div>
                    </div>
                    
                    <div class="quick-stat">
                        <div class="quick-stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-success);">
                            <span id="stat-money-icon"></span>
                        </div>
                        <div class="quick-stat-content">
                            <div class="quick-stat-value">AR$<?php echo number_format($stats['total_monto'], 0, ',', '.'); ?></div>
                            <div class="quick-stat-label">Monto Total</div>
                        </div>
                    </div>
                    
                    <div class="quick-stat">
                        <div class="quick-stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--accent-warning);">
                            <span id="stat-avg-icon"></span>
                        </div>
                        <div class="quick-stat-content">
                            <div class="quick-stat-value">AR$<?php echo number_format($stats['promedio'], 0, ',', '.'); ?></div>
                            <div class="quick-stat-label">Promedio por Presupuesto</div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filters-section">
                    <div class="filters-row">
                        <div class="filter-group">
                            <a href="?filtro=todos" class="filter-button <?php echo $filtro === 'todos' ? 'active' : ''; ?>">
                                Todos
                            </a>
                            <a href="?filtro=hoy" class="filter-button <?php echo $filtro === 'hoy' ? 'active' : ''; ?>">
                                Hoy
                            </a>
                            <a href="?filtro=semana" class="filter-button <?php echo $filtro === 'semana' ? 'active' : ''; ?>">
                                Esta Semana
                            </a>
                            <a href="?filtro=mes" class="filter-button <?php echo $filtro === 'mes' ? 'active' : ''; ?>">
                                Este Mes
                            </a>
                        </div>
                        
                        <div class="search-section">
                            <form method="GET" action="">
                                <input type="hidden" name="filtro" value="<?php echo $filtro; ?>">
                                <div class="search-box">
                                    <span id="search-icon"></span>
                                    <input type="text" 
                                           name="busqueda" 
                                           placeholder="Buscar por cliente, email o ID..." 
                                           value="<?php echo htmlspecialchars($busqueda); ?>">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Presupuestos -->
                <div style="overflow-x: auto; width: 100%;">
                    <div class="presupuestos-table">
                        <div class="table-row table-header">
                            <div class="table-cell">ID</div>
                            <div class="table-cell">Cliente</div>
                            <div class="table-cell">Total</div>
                            <div class="table-cell">Items</div>
                            <div class="table-cell">Fecha</div>
                            <!-- <div class="table-cell">Estado</div> -->
                            <div class="table-cell">Acciones</div>
                        </div>
                    
                    <?php if (empty($presupuestos)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📄</div>
                        <h3>No hay presupuestos</h3>
                        <p class="text-muted">No se encontraron presupuestos con los filtros seleccionados</p>
                        <a href="../cotizador.php" class="btn btn-primary mt-3" target="_blank">
                            <span id="empty-new-icon"></span>
                            Crear Primer Presupuesto
                        </a>
                    </div>
                    <?php else: ?>
                        <?php foreach ($presupuestos as $presupuesto): ?>
                        <div class="table-row">
                            <div class="table-cell">
                                <span class="badge badge-primary">#<?php echo $presupuesto['id']; ?></span>
                            </div>
                            <div class="table-cell">
                                <div class="customer-info">
                                    <span class="customer-name">
                                        <?php echo htmlspecialchars($presupuesto['cliente_nombre'] ?? 'Sin nombre'); ?>
                                    </span>
                                    <span class="customer-email">
                                        <?php echo htmlspecialchars($presupuesto['cliente_email'] ?? 'Sin email'); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="table-cell amount-cell">
                                AR$<?php echo number_format($presupuesto['total'], 2, ',', '.'); ?>
                            </div>
                            <div class="table-cell">
                                <?php 
                                // Consultar las opciones relacionadas con este presupuesto
                                $query_items = "SELECT COUNT(*) as total FROM presupuesto_detalles WHERE presupuesto_id = " . $presupuesto['id'];
                                $result_items = $conn->query($query_items);
                                $items_count = 0;
                                
                                if ($result_items && $row = $result_items->fetch_assoc()) {
                                    $items_count = $row['total'];
                                }
                                ?>
                                <span class="badge badge-info"><?php echo $items_count; ?> items</span>
                            </div>
                            <div class="table-cell date-cell">
                                <?php 
                                $fecha = new DateTime($presupuesto['created_at']);
                                echo $fecha->format('d/m/Y H:i');
                                ?>
                            </div>
                            <!-- <div class="table-cell">
                                <span class="status-badge pendiente">
                                    <span id="status-icon-<?php echo $presupuesto['id']; ?>"></span>
                                    Pendiente
                                </span>
                            </div> -->
                            <div class="table-cell" style="padding-right: var(--spacing-lg); min-width: 150px;">
                                <div style="display: flex; gap: 6px; justify-content: flex-start; flex-wrap: nowrap; width: 100%; min-width: 140px;">
                                    <button class="btn btn-sm btn-secondary" onclick="verPresupuesto(<?php echo $presupuesto['id']; ?>)" title="Ver detalle">
                                        <span id="view-icon-<?php echo $presupuesto['id']; ?>"></span>
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="descargarPDF(<?php echo $presupuesto['id']; ?>)" title="Descargar PDF">
                                        <span id="pdf-icon-<?php echo $presupuesto['id']; ?>"></span>
                                    </button>
                                    <button class="btn btn-sm btn-secondary<?php if (!empty($_SESSION['is_demo'])) echo ' btn-disabled'; ?>" onclick="confirmarEliminar(<?php echo $presupuesto['id']; ?>)" title="Eliminar presupuesto" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                                        <span id="delete-icon-<?php echo $presupuesto['id']; ?>"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>

                <!-- Paginación (simulada) -->
                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" class="page-link">&laquo; Anterior</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>" class="page-link<?php if ($i == $pagina) echo ' active'; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($pagina < $total_paginas): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" class="page-link">Siguiente &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/modern-icons.js"></script>
    <script>
function safeSetIcon(id, icon, size) {
    var el = document.getElementById(id);
    if (el) el.innerHTML = modernUI.getIcon(icon, size);
}

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar
    safeSetIcon('logo-icon', 'chart');
    safeSetIcon('nav-dashboard-icon', 'dashboard');
    safeSetIcon('nav-data-icon', 'settings');
    safeSetIcon('nav-quotes-icon', 'document');
    safeSetIcon('nav-prices-icon', 'dollar');
    safeSetIcon('nav-calculator-icon', 'cart');
    safeSetIcon('nav-logout-icon', 'logout');
    // Header
    safeSetIcon('new-icon', 'add');
    // Stats
    safeSetIcon('stat-total-icon', 'document');
    safeSetIcon('stat-money-icon', 'dollar');
    safeSetIcon('stat-avg-icon', 'chart');
    // Search
    safeSetIcon('search-icon', 'search');
    // Table actions
    document.querySelectorAll('[id^="view-icon-"]').forEach(function(el) {
        el.innerHTML = modernUI.getIcon('eye', 'icon-sm');
    });
    document.querySelectorAll('[id^="pdf-icon-"]').forEach(function(el) {
        el.innerHTML = modernUI.getIcon('pdf', 'icon-sm');
    });
    document.querySelectorAll('[id^="delete-icon-"]').forEach(function(el) {
        el.innerHTML = modernUI.getIcon('delete', 'icon-sm');
    });
    // Empty state
    safeSetIcon('empty-new-icon', 'add');
    // Iconos para alertas
    safeSetIcon('success-icon', 'check-circle');
    safeSetIcon('error-icon', 'alert-circle');
});

        function verPresupuesto(id) {
            window.location.href = `ver_presupuesto_moderno.php?id=${id}`;
        }

        function descargarPDF(id) {
            window.open(`../api/download_pdf.php?id=${id}`, '_blank');
        }

        function exportarPresupuestos() {
            modernUI.showToast('Función de exportación en desarrollo', 'info');
        }

        function confirmarEliminar(id) {
            if (confirm(`¿Está seguro que desea eliminar el presupuesto #${id}?\nEsta acción no se puede deshacer.`)) {
                window.location.href = `eliminar_presupuesto.php?id=${id}`;
            }
        }

        // Auto-submit al escribir en búsqueda
        const searchInput = document.querySelector('input[name="busqueda"]');
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>
</body>
</html> 
<?php
// Incluir configuración antes de iniciar la sesión
require_once __DIR__ . '/config.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Manejar logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Cargar DB
$dbPath = __DIR__ . '/../sistema/includes/db.php';
$dbConnected = false;

if (file_exists($dbPath)) {
    try {
        require_once $dbPath;
        $dbConnected = true;
    } catch (Exception $e) {
        error_log("Error loading db.php: " . $e->getMessage());
    }
}

// Verificar login
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Procesar login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLoggedIn) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (defined('ADMIN_USER') && defined('ADMIN_PASS') && defined('DEMO_USER') && defined('DEMO_PASS')) {
        if ($username === ADMIN_USER && password_verify($password, ADMIN_PASS)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            $_SESSION['is_demo'] = false;
            $_SESSION['login_time'] = time();
            $isLoggedIn = true;
        } elseif ($username === DEMO_USER && password_verify($password, DEMO_PASS)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            $_SESSION['is_demo'] = true;
            $_SESSION['login_time'] = time();
            $isLoggedIn = true;
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    }
}

// Si está logueado, mostrar dashboard
if ($isLoggedIn) {
    // Obtener estadísticas
    $stats = [
        'presupuestos' => ['total' => 0, 'mes' => 0, 'cambio' => 0],
        'clientes' => ['total' => 0, 'nuevos' => 0, 'cambio' => 0],
        'ingresos' => ['total' => 0, 'mes' => 0, 'cambio' => 0],
        'productos' => ['total' => 0, 'activos' => 0]
    ];
    
    $ultimosPresupuestos = [];
    $chartData = ['labels' => [], 'values' => []];
    
    if ($dbConnected) {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Total presupuestos
            $result = $conn->query("SELECT COUNT(*) as total FROM presupuestos");
            if ($result) {
                $stats['presupuestos']['total'] = $result->fetch_assoc()['total'];
            }
            
            // Presupuestos del mes (usando ID como aproximación)
            $result = $conn->query("SELECT COUNT(*) as total FROM presupuestos WHERE id > 0");
            if ($result) {
                $stats['presupuestos']['mes'] = $result->fetch_assoc()['total'];
            }
            
            // Total ingresos
            $result = $conn->query("SELECT SUM(total) as total FROM presupuestos");
            if ($result) {
                $stats['ingresos']['total'] = $result->fetch_assoc()['total'] ?? 0;
            }
            
            // Ingresos del mes (usando todos los registros como aproximación)
            $result = $conn->query("SELECT SUM(total) as total FROM presupuestos WHERE id > 0");
            if ($result) {
                $stats['ingresos']['mes'] = $result->fetch_assoc()['total'] ?? 0;
            }
            
            // Total opciones
            $result = $conn->query("SELECT COUNT(*) as total FROM opciones");
            if ($result) {
                $stats['productos']['total'] = $result->fetch_assoc()['total'];
            }
            
            // Últimos presupuestos
            $result = $conn->query("SELECT * FROM presupuestos ORDER BY id DESC LIMIT 10");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $ultimosPresupuestos[] = $row;
                }
                // Debug: agregar al log para verificar
                error_log("Admin: Se encontraron " . count($ultimosPresupuestos) . " presupuestos");
            } else {
                error_log("Admin: Error en consulta presupuestos: " . $conn->error);
            }
            
            // Datos para gráfico (simulado por ahora - sin fecha_creacion)
            $chartData['labels'] = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
            $chartData['values'] = [12, 34, 56, 78, 45, 67, 23];
            
        } catch (Exception $e) {
            error_log("Error getting stats: " . $e->getMessage());
        }
    }
    
    // Calcular cambios porcentuales (simulados por ahora)
    $stats['presupuestos']['cambio'] = 12.5;
    $stats['clientes']['cambio'] = -2.4;
    $stats['ingresos']['cambio'] = 18.2;
    
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Panel Admin</title>
    <link rel="stylesheet" href="../assets/css/modern-dark-theme.css">
    <style>
        /* Layout principal */
        .dashboard-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Contenido principal */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header mejorado */
        .dashboard-header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: var(--spacing-lg) var(--spacing-xl);
        }

        .header-grid {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header-grid h1 {
             color: var(--text-primary);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--accent-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Grid de contenido */
        .content-grid {
            flex: 1;
            padding: var(--spacing-xl);
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: var(--spacing-lg);
            align-content: start;
        }

        /* Cards de estadísticas */
        .stats-grid {
            grid-column: span 12;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md);
        }

        .stat-card {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }

        .stat-title {
            color: var(--text-secondary);
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 2em;
            font-weight: 600;
            color: var(--text-primary);
        }

        .stat-change {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: 0.9em;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--error);
        }

        /* Gráfico */
        .chart-container {
            grid-column: span 8;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            position: relative;
            height: 400px; /* Alto fijo para el contenedor del gráfico */
        }

        /* Tabla de últimos presupuestos */
        .recent-presupuestos {
            grid-column: span 4;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
        }

        .presupuesto-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }

        .presupuesto-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            background: var(--bg-primary);
        }

        .presupuesto-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .presupuesto-title {
            font-weight: 500;
            color: var(--text-primary);
        }

        .presupuesto-date {
            font-size: 0.8em;
            color: var(--text-secondary);
        }

        .presupuesto-amount {
            font-weight: 600;
            color: var(--accent-primary);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .chart-container,
            .recent-presupuestos {
                grid-column: span 12;
            }
            .sidebar {
                display: none; /* Ocultar en pantallas pequeñas */
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
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
        
        <div class="main-content">
            <header class="dashboard-header">
                <div class="header-grid">
                    <h1>Dashboard
<?php if (!empty($_SESSION['is_demo'])): ?>
    <span style="color: #fff; font-weight: 600; font-size: 1rem; margin-left: 1.5rem;">Modo Demo - Las acciones se encuentran deshabilitadas</span>
<?php endif; ?>
</h1>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['admin_user'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_user']); ?></div>
                            <a href="index.php?logout=1" class="btn btn-secondary" style="margin-top: 6px; font-size: 1rem; padding: 8px 18px;">Cerrar sesión</a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content-grid">
                <!-- Estadísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-title">Presupuestos Totales</div>
                        <div class="stat-value"><?php echo number_format($stats['presupuestos']['total']); ?></div>
                        <div class="stat-change <?php echo $stats['presupuestos']['cambio'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $stats['presupuestos']['cambio'] >= 0 ? '↑' : '↓'; ?>
                            <?php echo abs($stats['presupuestos']['cambio']); ?>%
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-title">Presupuestado al día de hoy</div>
                        <div class="stat-value">AR$ <?php echo number_format($stats['ingresos']['total'], 2); ?></div>
                        <div class="stat-change <?php echo $stats['ingresos']['cambio'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $stats['ingresos']['cambio'] >= 0 ? '↑' : '↓'; ?>
                            <?php echo abs($stats['ingresos']['cambio']); ?>%
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-title">Productos Activos</div>
                        <div class="stat-value"><?php echo number_format($stats['productos']['total']); ?></div>
                    </div>
                </div>

                <!-- Gráfico -->
                <div class="chart-container">
                    <h2 class="chart-title" style="font-size: var(--text-lg); font-weight: 600; margin-bottom: var(--spacing-md);">Presupuestos x Día</h2>
                    <canvas id="ingresosChart"></canvas>
                    <!-- Aquí NO debe haber valores de dinero ni columna de números sueltos -->
                </div>

                <!-- Últimos Presupuestos -->
                <div class="recent-presupuestos">
                    <h2>Últimos Presupuestos</h2>
                    <div class="presupuesto-list">
                        <?php foreach ($ultimosPresupuestos as $presupuesto): ?>
                        <div class="presupuesto-item">
                            <div class="presupuesto-info">
                                <div class="presupuesto-title">Presupuesto #<?php echo $presupuesto['id']; ?></div>
                                <div class="presupuesto-date">
                                    <?php echo date('d/m/Y', strtotime($presupuesto['fecha_creacion'] ?? 'now')); ?>
                                </div>
                            </div>
                            <div class="presupuesto-amount">
                                AR$ <?php echo number_format($presupuesto['total'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Datos del gráfico
        const chartData = {
            labels: <?php echo json_encode($chartData['labels']); ?>,
            values: <?php echo json_encode($chartData['values']); ?>
        };

        // Crear gráfico
        const ctx = document.getElementById('ingresosChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Presupuestos',
                    data: chartData.values,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value; // Solo número, sin ARS
                            }
                        }
                    }
                }
            }
        });
    </script>
    <script src="../assets/js/modern-icons.js"></script>
    <script>
        // Inicializar iconos del sidebar
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('logo-icon').innerHTML = modernUI.getIcon('chart');
            document.getElementById('nav-dashboard-icon').innerHTML = modernUI.getIcon('dashboard');
            document.getElementById('nav-data-icon').innerHTML = modernUI.getIcon('settings');
            document.getElementById('nav-quotes-icon').innerHTML = modernUI.getIcon('document');
            document.getElementById('nav-prices-icon').innerHTML = modernUI.getIcon('dollar');
            document.getElementById('nav-rules-icon').innerHTML = modernUI.getIcon('check-shield');
            document.getElementById('nav-calculator-icon').innerHTML = modernUI.getIcon('cart');
            document.getElementById('nav-logout-icon').innerHTML = modernUI.getIcon('logout');
        });
    </script>
</body>
</html>
<?php
} else {
    // Mostrar formulario de login
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel Admin</title>
    <link rel="stylesheet" href="../assets/css/modern-dark-theme.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: var(--bg-primary);
        }

        .login-container {
            background: var(--bg-secondary);
            padding: var(--spacing-xl);
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .login-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }

        .form-label {
            color: var(--text-secondary);
            font-size: 0.9em;
        }

        .form-input {
            padding: var(--spacing-sm);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-primary);
        }

        .form-button {
            background: var(--accent-primary);
            color: white;
            padding: var(--spacing-sm);
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .form-button:hover {
            background: var(--accent-primary-dark);
        }

        .error-message {
            color: var(--error);
            text-align: center;
            margin-bottom: var(--spacing-md);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Panel de Administración</h1>
            <p>Ingrese sus credenciales para continuar</p>
        </div>

        <?php if ($error): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form class="login-form" method="POST">
            <div class="form-group">
                <label class="form-label" for="username">Usuario</label>
                <input class="form-input" type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Contraseña</label>
                <input class="form-input" type="password" id="password" name="password" required>
            </div>

            <button class="form-button" type="submit">Iniciar Sesión</button>
        </form>
        <div style="margin: 1.5rem 0; padding: 1rem; background: #f3f3f3; color: #222; border-radius: 0.5rem; font-size: 1.1rem; font-style: italic; text-align: center; max-width: 350px; margin-left: auto; margin-right: auto;">
        Usuario: <b>demo</b> &nbsp; Contraseña: <b>demo123</b>
    </div>
    </div>
</body>
</html>
<?php
}
?> 
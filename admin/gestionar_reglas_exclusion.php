<?php
// Mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'cotizador_ascensores');
define('DB_USER', 'root');
define('DB_PASS', '');

require_once __DIR__ . '/../includes/db.php';

// Procesar formulario de nueva regla
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'crear') {
        $opcion_id = $_POST['opcion_id'];
        $opcion_excluida_id = $_POST['opcion_excluida_id'];
        $mensaje_error = $_POST['mensaje_error'];
        
        // Verificar si la regla ya existe
        $stmt_check = $pdo->prepare("SELECT id FROM opciones_excluyentes WHERE opcion_id = ? AND opcion_excluida_id = ?");
        $stmt_check->execute([$opcion_id, $opcion_excluida_id]);
        
        if ($stmt_check->fetch()) {
            // La regla ya existe
            header('Location: gestionar_reglas_exclusion.php?error=duplicate');
            exit;
        }
        
        // Verificar que no se esté excluyendo a sí misma
        if ($opcion_id == $opcion_excluida_id) {
            header('Location: gestionar_reglas_exclusion.php?error=self_exclusion');
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO opciones_excluyentes (opcion_id, opcion_excluida_id, mensaje_error) VALUES (?, ?, ?)");
            $stmt->execute([$opcion_id, $opcion_excluida_id, $mensaje_error]);
            
            header('Location: gestionar_reglas_exclusion.php?success=1');
            exit;
        } catch (PDOException $e) {
            // Si es un error de duplicado, mostrar mensaje específico
            if ($e->getCode() == 23000) {
                header('Location: gestionar_reglas_exclusion.php?error=duplicate');
            } else {
                header('Location: gestionar_reglas_exclusion.php?error=database');
            }
            exit;
        }
    } elseif ($_POST['action'] === 'eliminar') {
        $regla_id = $_POST['regla_id'];
        $stmt = $pdo->prepare("DELETE FROM opciones_excluyentes WHERE id = ?");
        $stmt->execute([$regla_id]);
        
        header('Location: gestionar_reglas_exclusion.php?deleted=1');
        exit;
    } elseif ($_POST['action'] === 'toggle') {
        $regla_id = $_POST['regla_id'];
        $activo = $_POST['activo'] == 1 ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE opciones_excluyentes SET activo = ? WHERE id = ?");
        $stmt->execute([$activo, $regla_id]);
        
        header('Location: gestionar_reglas_exclusion.php?updated=1');
        exit;
    }
}

// Obtener todas las opciones para los dropdowns
$stmt = $pdo->prepare("SELECT o.id, o.nombre, c.nombre as categoria FROM opciones o INNER JOIN categorias c ON o.categoria_id = c.id ORDER BY c.nombre, o.nombre");
$stmt->execute();
$opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las reglas existentes
$stmt = $pdo->prepare("
    SELECT 
        oe.id,
        oe.opcion_id,
        oe.opcion_excluida_id,
        oe.mensaje_error,
        oe.activo,
        o1.nombre as opcion_nombre,
        o2.nombre as opcion_excluida_nombre,
        c1.nombre as categoria_nombre,
        c2.nombre as categoria_excluida_nombre
    FROM opciones_excluyentes oe
    INNER JOIN opciones o1 ON oe.opcion_id = o1.id
    INNER JOIN opciones o2 ON oe.opcion_excluida_id = o2.id
    INNER JOIN categorias c1 ON o1.categoria_id = c1.id
    INNER JOIN categorias c2 ON o2.categoria_id = c2.id
    ORDER BY oe.opcion_id, oe.opcion_excluida_id
");
$stmt->execute();
$reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Reglas de Exclusión - Panel Admin</title>
    <link rel="stylesheet" href="../assets/css/modern-dark-theme.css">
    <style>
        /* Layout principal */
        .dashboard-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: var(--spacing-xl);
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-header h1 {
            color: var(--text-primary);
            font-size: var(--text-xl);
            font-weight: 700;
            margin: 0;
        }

        .sidebar-menu {
            flex: 1;
            padding: var(--spacing-md);
            display: flex;
            flex-direction: column;
            /* Elimino gap para igualar al index */
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius-md);
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
            margin-bottom: var(--spacing-xs);
            gap: var(--spacing-sm);
        }

        .sidebar-item:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .sidebar-item.active {
            background: var(--accent-primary);
            color: white;
        }

        /* Contenido principal */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header */
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

        /* Contenido */
        .content-area {
            flex: 1;
            padding: var(--spacing-xl);
            overflow-y: auto;
        }

        /* Formulario */
        .form-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }

        .form-section h2 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
            font-size: var(--text-lg);
            font-weight: 600;
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
            font-weight: 600;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: var(--text-base);
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        /* Reglas */
        .rules-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
        }

        .rules-section h2 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
            font-size: var(--text-lg);
            font-weight: 600;
        }

        .rule-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-md);
            background: var(--bg-secondary);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--spacing-lg);
        }

        .rule-item.inactive {
            opacity: 0.6;
            background: var(--bg-tertiary);
        }

        .rule-info {
            flex: 1;
        }

        .rule-info > div {
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }

        .rule-info strong {
            color: var(--accent-primary);
        }

        .rule-actions {
            display: flex;
            gap: var(--spacing-sm);
            flex-shrink: 0;
        }

        /* Alertas */
        .alert {
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
            border: 1px solid;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border-color: rgba(34, 197, 94, 0.2);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.2);
        }

        /* Botones */
        .btn {
            padding: var(--spacing-sm) var(--spacing-md);
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .btn-primary {
            background: var(--accent-primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-primary);
            opacity: 0.9;
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--bg-hover);
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-success {
            background: #22c55e;
            color: white;
        }

        .btn-success:hover {
            background: #16a34a;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>Panel Admin</h1>
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
                <a href="presupuestos.php" class="sidebar-item">
                    <span id="nav-quotes-icon"></span>
                    <span>Presupuestos</span>
                </a>
                <a href="ajustar_precios.php" class="sidebar-item">
                    <span id="nav-prices-icon"></span>
                    <span>Ajustar Precios</span>
                </a>
                <a href="gestionar_reglas_exclusion_dual.php" class="sidebar-item active">
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
        
        <div class="main-content">
            <header class="dashboard-header">
                <div class="header-grid">
                    <h1>Gestionar Reglas de Exclusión</h1>
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

            <div class="content-area">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">✅ Regla creada exitosamente</div>
                <?php endif; ?>
                
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">✅ Regla eliminada exitosamente</div>
                <?php endif; ?>
                
                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success">✅ Estado de regla actualizado</div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error']) && $_GET['error'] === 'duplicate'): ?>
                    <div class="alert alert-danger">❌ Error: Esta regla de exclusión ya existe</div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error']) && $_GET['error'] === 'self_exclusion'): ?>
                    <div class="alert alert-danger">❌ Error: Una opción no puede excluirse a sí misma</div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error']) && $_GET['error'] === 'database'): ?>
                    <div class="alert alert-danger">❌ Error de base de datos. Inténtalo de nuevo.</div>
                <?php endif; ?>
                
                <!-- Formulario para crear nueva regla -->
                <div class="form-section">
                    <h2>Crear Nueva Regla de Exclusión</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="crear">
                        
                        <div class="form-group">
                            <label><strong>Si selecciono:</strong></label>
                            <select name="opcion_id" required>
                                <option value="">Selecciona una opción...</option>
                                <?php foreach ($opciones as $opcion): ?>
                                    <option value="<?= $opcion['id'] ?>">
                                        [<?= $opcion['categoria'] ?>] <?= htmlspecialchars($opcion['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><strong>Entonces NO puedo seleccionar:</strong></label>
                            <select name="opcion_excluida_id" required>
                                <option value="">Selecciona una opción...</option>
                                <?php foreach ($opciones as $opcion): ?>
                                    <option value="<?= $opcion['id'] ?>">
                                        [<?= $opcion['categoria'] ?>] <?= htmlspecialchars($opcion['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><strong>Mensaje de error:</strong></label>
                            <input type="text" name="mensaje_error" 
                                   placeholder="Esta opción no es compatible con la selección anterior">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Crear Regla</button>
                    </form>
                </div>
                
                <!-- Lista de reglas existentes -->
                <div class="rules-section">
                    <h2>Reglas de Exclusión Existentes (<?= count($reglas) ?>)</h2>
                    
                    <?php if (empty($reglas)): ?>
                        <p style="color: var(--text-secondary);">No hay reglas de exclusión configuradas.</p>
                    <?php else: ?>
                        <?php foreach ($reglas as $regla): ?>
                            <div class="rule-item <?= $regla['activo'] ? '' : 'inactive' ?>">
                                <div class="rule-info">
                                    <div><strong>Si selecciono:</strong> [<?= $regla['categoria_nombre'] ?>] <?= htmlspecialchars($regla['opcion_nombre']) ?></div>
                                    <div><strong>Entonces NO puedo seleccionar:</strong> [<?= $regla['categoria_excluida_nombre'] ?>] <?= htmlspecialchars($regla['opcion_excluida_nombre']) ?></div>
                                    <div><strong>Mensaje:</strong> <?= htmlspecialchars($regla['mensaje_error']) ?></div>
                                    <div><strong>Estado:</strong> <?= $regla['activo'] ? '✅ Activa' : '❌ Inactiva' ?></div>
                                </div>
                                
                                <div class="rule-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="regla_id" value="<?= $regla['id'] ?>">
                                        <input type="hidden" name="activo" value="<?= $regla['activo'] ?>">
                                        <button type="submit" class="btn btn-<?= $regla['activo'] ? 'warning' : 'success' ?>">
                                            <?= $regla['activo'] ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta regla?')">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="regla_id" value="<?= $regla['id'] ?>">
                                        <button type="submit" class="btn btn-danger">Eliminar</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/modern-icons.js"></script>
    <script>
        // Inicializar iconos del sidebar
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('nav-dashboard-icon').innerHTML = modernUI.getIcon('dashboard');
            document.getElementById('nav-data-icon').innerHTML = modernUI.getIcon('settings');
            document.getElementById('nav-quotes-icon').innerHTML = modernUI.getIcon('document');
            document.getElementById('nav-prices-icon').innerHTML = modernUI.getIcon('dollar');
            document.getElementById('nav-rules-icon').innerHTML = modernUI.getIcon('shield');
            document.getElementById('nav-calculator-icon').innerHTML = modernUI.getIcon('cart');
            document.getElementById('nav-logout-icon').innerHTML = modernUI.getIcon('logout');
        });
    </script>
</body>
</html> 
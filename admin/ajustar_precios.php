<?php
// Iniciar sesión
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
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

$dbLoaded = false;
foreach ($dbPaths as $dbPath) {
    if (file_exists($dbPath)) {
        require_once $dbPath;
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded) {
    die("Error: No se pudo encontrar el archivo de base de datos en ninguna ubicación");
}

// Verificar login
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$isLoggedIn) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$tipoMensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajustar_precios'])) {
    $porcentaje = floatval($_POST['porcentaje'] ?? 0);
    
    if ($porcentaje == 0) {
        $mensaje = 'Por favor ingresa un porcentaje válido';
        $tipoMensaje = 'error';
    } else {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Iniciar transacción
            $conn->begin_transaction();
            
            // Calcular el factor de ajuste
            $factor = 1 + ($porcentaje / 100);
            
                         // Obtener todas las categorías excepto descuentos
             $categoriasQuery = "SELECT id, nombre FROM categorias WHERE nombre NOT LIKE '%descuento%' AND nombre NOT LIKE '%pago%' ORDER BY nombre";
             $categoriasResult = $conn->query($categoriasQuery);
             
             $categoriasAfectadas = [];
             $opcionesActualizadas = 0;
             $preciosActualizados = 0;
             
             // Verificar si existe la tabla opcion_precios
             $tablaOpcionPreciosExiste = $conn->query("SHOW TABLES LIKE 'opcion_precios'")->num_rows > 0;
             
             while ($categoria = $categoriasResult->fetch_assoc()) {
                 $categoriasAfectadas[] = $categoria['nombre'];
                 
                 if ($tablaOpcionPreciosExiste) {
                     // Actualizar precios en la tabla opcion_precios para esta categoría
                     $updateQuery = "
                         UPDATE opcion_precios op 
                         INNER JOIN opciones o ON op.opcion_id = o.id 
                         SET op.precio = ROUND(op.precio * ?, 2)
                         WHERE o.categoria_id = ?
                     ";
                     
                     $stmt = $conn->prepare($updateQuery);
                     $stmt->bind_param('di', $factor, $categoria['id']);
                     $stmt->execute();
                     
                     $preciosActualizados += $stmt->affected_rows;
                     $stmt->close();
                 }
                 
                 // Actualizar precios en la tabla opciones (estructura local)
                 $updateOpcionesQuery = "
                     UPDATE opciones 
                     SET 
                         precio = ROUND(precio * ?, 2),
                         precio_90_dias = ROUND(precio_90_dias * ?, 2),
                         precio_160_dias = ROUND(precio_160_dias * ?, 2),
                         precio_270_dias = ROUND(precio_270_dias * ?, 2)
                     WHERE categoria_id = ? AND (precio > 0 OR precio_90_dias > 0 OR precio_160_dias > 0 OR precio_270_dias > 0)
                 ";
                 
                 $stmt = $conn->prepare($updateOpcionesQuery);
                 $stmt->bind_param('ddddi', $factor, $factor, $factor, $factor, $categoria['id']);
                 $stmt->execute();
                 
                 $opcionesActualizadas += $stmt->affected_rows;
                 $stmt->close();
             }
            
            // Confirmar transacción
            $conn->commit();
            
            $accion = $porcentaje > 0 ? 'incrementado' : 'disminuido';
            $porcentajeAbs = abs($porcentaje);
            
            $mensaje = "¡Precios actualizados exitosamente!<br>";
            $mensaje .= "Se han {$accion} los precios en un {$porcentajeAbs}%<br>";
            $mensaje .= "Categorías afectadas: " . implode(', ', $categoriasAfectadas) . "<br>";
            $mensaje .= "Precios por plazo actualizados: {$preciosActualizados}<br>";
            $mensaje .= "Precios base actualizados: {$opcionesActualizadas}";
            $tipoMensaje = 'success';
            
        } catch (Exception $e) {
            $conn->rollback();
            $mensaje = 'Error al actualizar precios: ' . $e->getMessage();
            $tipoMensaje = 'error';
        }
    }
}

// Obtener estadísticas actuales
$stats = [
    'total_opciones' => 0,
    'total_precios' => 0,
    'categorias' => []
];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Contar opciones totales (excluyendo descuentos)
    $result = $conn->query("
        SELECT COUNT(*) as total 
        FROM opciones o 
        INNER JOIN categorias c ON o.categoria_id = c.id 
        WHERE c.nombre NOT LIKE '%descuento%' AND c.nombre NOT LIKE '%pago%'
    ");
    if ($result) {
        $stats['total_opciones'] = $result->fetch_assoc()['total'];
    }
    
         // Contar precios totales
     $tablaOpcionPreciosExiste = $conn->query("SHOW TABLES LIKE 'opcion_precios'")->num_rows > 0;
     
     if ($tablaOpcionPreciosExiste) {
         $result = $conn->query("
             SELECT COUNT(*) as total 
             FROM opcion_precios op 
             INNER JOIN opciones o ON op.opcion_id = o.id 
             INNER JOIN categorias c ON o.categoria_id = c.id 
             WHERE c.nombre NOT LIKE '%descuento%' AND c.nombre NOT LIKE '%pago%'
         ");
         if ($result) {
             $stats['total_precios'] = $result->fetch_assoc()['total'];
         }
     } else {
         // Contar precios en la estructura local (4 precios por opción)
         $result = $conn->query("
             SELECT COUNT(*) * 4 as total 
             FROM opciones o 
             INNER JOIN categorias c ON o.categoria_id = c.id 
             WHERE c.nombre NOT LIKE '%descuento%' AND c.nombre NOT LIKE '%pago%'
             AND (o.precio > 0 OR o.precio_90_dias > 0 OR o.precio_160_dias > 0 OR o.precio_270_dias > 0)
         ");
         if ($result) {
             $stats['total_precios'] = $result->fetch_assoc()['total'];
         }
     }
    
         // Obtener estadísticas por categoría
     if ($tablaOpcionPreciosExiste) {
         $result = $conn->query("
             SELECT 
                 c.nombre,
                 COUNT(DISTINCT o.id) as opciones,
                 COUNT(op.id) as precios,
                 AVG(op.precio) as precio_promedio
             FROM categorias c
             LEFT JOIN opciones o ON c.id = o.categoria_id
             LEFT JOIN opcion_precios op ON o.id = op.opcion_id
             WHERE c.nombre NOT LIKE '%descuento%' AND c.nombre NOT LIKE '%pago%'
             GROUP BY c.id, c.nombre
             ORDER BY c.nombre
         ");
     } else {
         // Estadísticas para estructura local
         $result = $conn->query("
             SELECT 
                 c.nombre,
                 COUNT(o.id) as opciones,
                 COUNT(o.id) * 4 as precios,
                 (AVG(o.precio) + AVG(o.precio_90_dias) + AVG(o.precio_160_dias) + AVG(o.precio_270_dias)) / 4 as precio_promedio
             FROM categorias c
             LEFT JOIN opciones o ON c.id = o.categoria_id
             WHERE c.nombre NOT LIKE '%descuento%' AND c.nombre NOT LIKE '%pago%'
             AND (o.precio > 0 OR o.precio_90_dias > 0 OR o.precio_160_dias > 0 OR o.precio_270_dias > 0)
             GROUP BY c.id, c.nombre
             ORDER BY c.nombre
         ");
     }
     
     if ($result) {
         while ($row = $result->fetch_assoc()) {
             $stats['categorias'][] = $row;
         }
     }
    
} catch (Exception $e) {
    error_log("Error getting stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustar Precios - Panel Admin</title>
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
            grid-template-columns: 1fr;
            gap: var(--spacing-lg);
            align-content: start;
        }
        
        .adjustment-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-lg);
        }
        
        .adjustment-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: var(--spacing-lg);
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }
        
        .percentage-input {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .percentage-input input {
            flex: 1;
            max-width: 200px;
        }
        
        .percentage-input span {
            font-size: var(--text-lg);
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            text-align: center;
        }
        
        .stat-value {
            font-size: var(--text-2xl);
            font-weight: 700;
            color: var(--accent-primary);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            font-size: var(--text-sm);
            color: var(--text-secondary);
        }
        
        .categories-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--spacing-lg);
        }
        
        .categories-table th,
        .categories-table td {
            padding: var(--spacing-md);
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .categories-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .warning-box {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid var(--accent-warning);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        
        .warning-box h3 {
            color: var(--accent-warning);
            margin-bottom: var(--spacing-sm);
        }
        
        .example-box {
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            margin-top: var(--spacing-lg);
        }
        
        .example-box h4 {
            color: var(--accent-info);
            margin-bottom: var(--spacing-sm);
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
                <a href="presupuestos.php" class="sidebar-item">
                    <span id="nav-quotes-icon"></span>
                    <span>Presupuestos</span>
                </a>

                <a href="ajustar_precios.php" class="sidebar-item active">
                    <span id="nav-prices-icon"></span>
                    <span>Ajustar Precios</span>
                </a>
                <div style="margin-top: auto; padding: var(--spacing-md);">
                    <a href="../cotizador.php" class="sidebar-item" target="_blank">
                        <span id="nav-calculator-icon"></span>
                        <span>Ir al Cotizador</span>
                    </a>
                    <a href="?logout=1" class="sidebar-item" style="color: var(--accent-danger);">
                        <span id="nav-logout-icon"></span>
                        <span>Cerrar Sesión</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-grid">
                    <div>
                        <h2 class="header-title">Ajustar Precios
<?php if (!empty($_SESSION['is_demo'])): ?>
    <span style="color: #fff; font-weight: 600; font-size: 1rem; margin-left: 1.5rem;">Modo Demo - Las acciones se encuentran deshabilitadas</span>
<?php endif; ?>
</h2>
                        <p class="header-subtitle">Incrementar o disminuir precios masivamente</p>
                    </div>
                    
                    <div class="user-info">
                        <div style="text-align: right;">
                            <div style="font-weight: 600; color: var(--text-primary);">
                                <?php echo htmlspecialchars($_SESSION['admin_user'] ?? 'Admin'); ?>
                            </div>
                            <div style="font-size: var(--text-xs); color: var(--text-secondary);">
                                Administrador
                            </div>
                        </div>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['admin_user'] ?? 'A', 0, 1)); ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Grid de Contenido -->
            <div class="content-grid">
        
        <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipoMensaje; ?>" style="margin-bottom: var(--spacing-lg);">
            <?php echo $mensaje; ?>
        </div>
        <?php endif; ?>
        
        <div class="warning-box">
            <h3>⚠️ Importante</h3>
            <p>Esta acción modificará <strong>todos los precios</strong> de ascensores y adicionales en <strong>todos los plazos</strong> (90 días, 160-180 días, 270 días).</p>
            <p>Los descuentos y formas de pago <strong>NO</strong> se verán afectados.</p>
            <p><strong>Recomendación:</strong> Realiza una copia de seguridad de la base de datos antes de proceder.</p>
        </div>
        
        <!-- Estadísticas actuales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_opciones']); ?></div>
                <div class="stat-label">Opciones Totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_precios']); ?></div>
                <div class="stat-label">Precios por Plazo</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($stats['categorias']); ?></div>
                <div class="stat-label">Categorías Afectadas</div>
            </div>
        </div>
        
        <!-- Formulario de ajuste -->
        <div class="adjustment-card">
            <h2 style="margin-bottom: var(--spacing-lg);">Ajustar Precios</h2>
            
            <form method="POST" class="adjustment-form">
                <div class="form-group">
                    <label for="porcentaje">Porcentaje de Ajuste</label>
                    <div class="percentage-input">
                        <input 
                            type="number" 
                            id="porcentaje" 
                            name="porcentaje" 
                            step="0.1" 
                            min="-50" 
                            max="100" 
                            placeholder="Ej: 10 o -5"
                            required
                            class="form-control"
                        >
                        <span>%</span>
                    </div>
                    <small class="form-text">
                        Valores positivos incrementan precios, valores negativos los disminuyen.<br>
                        Ejemplo: 10 = +10%, -5 = -5%
                    </small>
                </div>
                
                <button type="submit" name="ajustar_precios" class="btn btn-primary btn-lg<?php if (!empty($_SESSION['is_demo'])) echo ' btn-disabled'; ?>" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                    Aplicar Ajuste
                </button>
            </form>
            
            <div class="example-box">
                <h4>Ejemplos de uso:</h4>
                <ul>
                    <li><strong>+10%:</strong> Si un ascensor cuesta $50,000, pasará a costar $55,000</li>
                    <li><strong>-5%:</strong> Si un adicional cuesta $1,000, pasará a costar $950</li>
                    <li><strong>+15%:</strong> Incremento general por inflación o aumento de costos</li>
                </ul>
            </div>
        </div>
        
        <!-- Tabla de categorías -->
        <div class="adjustment-card">
            <h2 style="margin-bottom: var(--spacing-lg);">Categorías que se Verán Afectadas</h2>
            
            <table class="categories-table">
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th>Opciones</th>
                        <th>Precios por Plazo</th>
                        <th>Precio Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['categorias'] as $categoria): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($categoria['nombre']); ?></td>
                        <td><?php echo number_format($categoria['opciones']); ?></td>
                        <td><?php echo number_format($categoria['precios']); ?></td>
                        <td>$<?php echo number_format($categoria['precio_promedio'] ?? 0, 2, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
                     </div>
         </main>
     </div>
     
     <script src="../assets/js/modern-icons.js"></script>
     <script>
         // Cargar iconos
         document.addEventListener('DOMContentLoaded', function() {
             // Sidebar icons
             document.getElementById('logo-icon').innerHTML = modernUI.getIcon('chart');
             document.getElementById('nav-dashboard-icon').innerHTML = modernUI.getIcon('dashboard');
             document.getElementById('nav-data-icon').innerHTML = modernUI.getIcon('settings');
             document.getElementById('nav-quotes-icon').innerHTML = modernUI.getIcon('document');
             document.getElementById('nav-prices-icon').innerHTML = modernUI.getIcon('dollar');
             document.getElementById('nav-calculator-icon').innerHTML = modernUI.getIcon('cart');
             document.getElementById('nav-logout-icon').innerHTML = modernUI.getIcon('logout');
         });
     </script>
     <script>
        // Confirmación antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            const porcentaje = document.getElementById('porcentaje').value;
            const accion = parseFloat(porcentaje) > 0 ? 'incrementar' : 'disminuir';
            const porcentajeAbs = Math.abs(parseFloat(porcentaje));
            
            const confirmacion = confirm(
                `¿Estás seguro de que deseas ${accion} todos los precios en un ${porcentajeAbs}%?\n\n` +
                `Esta acción afectará:\n` +
                `• <?php echo $stats['total_opciones']; ?> opciones de productos\n` +
                `• <?php echo $stats['total_precios']; ?> precios individuales por plazo\n` +
                `• Todas las categorías excepto descuentos\n\n` +
                `Esta acción NO se puede deshacer fácilmente.`
            );
            
            if (!confirmacion) {
                e.preventDefault();
            }
        });
        
        // Validación en tiempo real
        document.getElementById('porcentaje').addEventListener('input', function() {
            const valor = parseFloat(this.value);
            const button = document.querySelector('button[type="submit"]');
            
            if (isNaN(valor) || valor === 0) {
                button.disabled = true;
                button.textContent = 'Ingresa un porcentaje válido';
            } else {
                button.disabled = false;
                const accion = valor > 0 ? 'Incrementar' : 'Disminuir';
                button.textContent = `${accion} Precios ${Math.abs(valor)}%`;
            }
        });
    </script>
</body>
</html>
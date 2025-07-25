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

// Procesar formulario de nuevas reglas (dual list)
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'crear_dual') {
    $opcion_id = $_POST['opcion_id'];
    $opciones_excluidas = isset($_POST['opciones_excluidas']) ? $_POST['opciones_excluidas'] : [];
    $mensaje_error = $_POST['mensaje_error'];

    // Eliminar reglas existentes para esta opción (para evitar duplicados)
    $stmt_del = $pdo->prepare("DELETE FROM opciones_excluyentes WHERE opcion_id = ?");
    $stmt_del->execute([$opcion_id]);

    // Insertar nuevas reglas
    $stmt_ins = $pdo->prepare("INSERT INTO opciones_excluyentes (opcion_id, opcion_excluida_id, mensaje_error) VALUES (?, ?, ?)");
    foreach ($opciones_excluidas as $opcion_excluida_id) {
        if ($opcion_id == $opcion_excluida_id) continue; // No permitir auto-exclusión
        $stmt_ins->execute([$opcion_id, $opcion_excluida_id, $mensaje_error]);
    }
    header('Location: gestionar_reglas_exclusion_dual.php?success=1');
    exit;
}

// 1. Procesar edición desde el modal
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'editar_dual') {
    $opcion_id = $_POST['opcion_id'];
    $opciones_excluidas = isset($_POST['opciones_excluidas']) ? $_POST['opciones_excluidas'] : [];
    $mensaje_error = $_POST['mensaje_error'];

    // Eliminar reglas existentes para esta opción
    $stmt_del = $pdo->prepare("DELETE FROM opciones_excluyentes WHERE opcion_id = ?");
    $stmt_del->execute([$opcion_id]);

    // Insertar nuevas reglas
    $stmt_ins = $pdo->prepare("INSERT INTO opciones_excluyentes (opcion_id, opcion_excluida_id, mensaje_error) VALUES (?, ?, ?)");
    foreach ($opciones_excluidas as $opcion_excluida_id) {
        if ($opcion_id == $opcion_excluida_id) continue;
        $stmt_ins->execute([$opcion_id, $opcion_excluida_id, $mensaje_error]);
    }
    header('Location: gestionar_reglas_exclusion_dual.php?success=1');
    exit;
}

// Obtener todas las opciones para los dropdowns
$stmt = $pdo->prepare("SELECT o.id, o.nombre, c.id as categoria_id, c.nombre as categoria FROM opciones o INNER JOIN categorias c ON o.categoria_id = c.id ORDER BY c.nombre, o.nombre");
$stmt->execute();
$opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las categorías para el filtro
$stmt_categorias = $pdo->prepare("SELECT id, nombre FROM categorias ORDER BY nombre");
$stmt_categorias->execute();
$categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);


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
    <title>Gestionar Reglas de Exclusión (Dual) - Panel Admin</title>
    <link rel="stylesheet" href="../assets/css/modern-dark-theme.css">
    <style>
        /* Copio los estilos principales del original */
        .dashboard-layout { display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 280px; background: var(--bg-secondary); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; overflow-y: auto; }
        .sidebar-header { padding: var(--spacing-xl); border-bottom: 1px solid var(--border-color); }
        .sidebar-header h1 { color: var(--text-primary); font-size: var(--text-xl); font-weight: 700; margin: 0; }
        .sidebar-menu { padding: var(--spacing-md); display: flex; flex-direction: column; /* Elimino gap para igualar al index */ }
        .sidebar-item { display: flex; align-items: center; padding: var(--spacing-sm) var(--spacing-md); border-radius: var(--radius-md); color: var(--text-secondary); text-decoration: none; transition: all 0.2s ease; font-weight: 500; margin-bottom: var(--spacing-xs); gap: var(--spacing-sm); }
        .sidebar-item:hover { background: var(--bg-hover); color: var(--text-primary); }
        .sidebar-item.active { background: var(--accent-primary); color: white; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .dashboard-header { background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); padding: var(--spacing-lg) var(--spacing-xl); }
        .header-grid { display: flex; align-items: center; justify-content: space-between; }
        .header-grid h1 { color: var(--text-primary); }
        .user-info { display: flex; align-items: center; gap: var(--spacing-md); }
        .user-avatar { width: 40px; height: 40px; background: var(--accent-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .content-area { flex: 1; padding: var(--spacing-xl); overflow-y: auto; }
        .form-section { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: var(--spacing-xl); margin-bottom: var(--spacing-xl); }
        .form-section h2 { color: var(--text-primary); margin-bottom: var(--spacing-lg); font-size: var(--text-lg); font-weight: 600; }
        .form-group { margin-bottom: var(--spacing-lg); }
        .form-group label { display: block; margin-bottom: var(--spacing-sm); color: var(--text-primary); font-weight: 600; }
        .form-group select, .form-group input { width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); color: var(--text-primary); font-size: var(--text-base); }
        .form-group select:focus, .form-group input:focus { outline: none; border-color: var(--accent-primary); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1); }
        .dual-list-container { display: flex; gap: 24px; align-items: center; justify-content: center; }
        .dual-list-box { width: 700px; height: 520px; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); overflow-y: auto; }
        .dual-list-box select { width: 100%; height: 100%; border: none; background: transparent; color: var(--text-primary); font-size: 0.8rem; }
        .dual-list-actions { display: flex; flex-direction: column; gap: 12px; }
        .dual-list-actions button { padding: 8px 16px; border-radius: var(--radius-md); border: none; background: var(--accent-primary); color: white; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .dual-list-actions button:hover { background: var(--accent-primary); opacity: 0.85; }
        /* Resto igual que original... */
        .rules-section { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: var(--spacing-xl); }
        .rules-section h2 { color: var(--text-primary); margin-bottom: var(--spacing-lg); font-size: var(--text-lg); font-weight: 600; }
        .rule-item { border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: var(--spacing-lg); margin-bottom: var(--spacing-md); background: var(--bg-secondary); display: flex; justify-content: space-between; align-items: flex-start; gap: var(--spacing-lg); }
        .rule-item.inactive { opacity: 0.6; background: var(--bg-tertiary); }
        .rule-info { flex: 1; }
        .rule-info > div { margin-bottom: var(--spacing-sm); color: var(--text-primary); }
        .rule-info strong { color: var(--accent-primary); }
        .rule-actions { display: flex; gap: var(--spacing-sm); flex-shrink: 0; }
        .alert { padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg); border: 1px solid; }
        .alert-success { background: rgba(34, 197, 94, 0.1); color: #22c55e; border-color: rgba(34, 197, 94, 0.2); }
        .alert-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.2); }
        .btn { padding: var(--spacing-sm) var(--spacing-md); border: none; border-radius: var(--radius-md); font-weight: 500; cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: var(--spacing-sm); }
        .btn-primary { background: var(--accent-primary); color: white; }
        .btn-primary:hover { background: var(--accent-primary); opacity: 0.9; }
        .btn-secondary { background: var(--bg-tertiary); color: var(--text-primary); }
        .btn-secondary:hover { background: var(--bg-hover); }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #22c55e; color: white; }
        .btn-success:hover { background: #16a34a; }
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
                <a href="ajustar_precios.php" class="sidebar-item">
                    <span id="nav-prices-icon"></span>
                    <span>Ajustar Precios</span>
                </a>
                <a href="gestionar_reglas_exclusion_dual.php" class="sidebar-item active">
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
                    <h1>Gestionar Reglas de Exclusión (Dual List)</h1>
                    <div class="user-info">
                        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['admin_user'], 0, 1)); ?></div>
                        <div>
                            <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_user']); ?></div>
                            <a href="index.php?logout=1" class="btn btn-secondary" style="margin-top: 6px; font-size: 1rem; padding: 8px 18px;">Cerrar sesión</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="content-area">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">✅ Reglas de exclusión actualizadas exitosamente</div>
                <?php endif; ?>
                <!-- Formulario dual list -->
                <div class="form-section">
                    <h2>Configurar Exclusiones con Dual List</h2>
                    <form method="POST" id="dual-list-form">
                        <input type="hidden" name="action" value="crear_dual">

                        <div class="form-group">
                             <label for="categoria_filtro"><strong>Paso 1: Selecciona una Categoría para filtrar los productos</strong></label>
                            <select id="categoria_filtro" class="form-control">
                                <option value="">-- Todas las Categorías --</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><strong>Si selecciono:</strong></label>
                            <select name="opcion_id" id="opcion_id" required>
                                <option value="">Selecciona una opción...</option>
                                <?php foreach ($opciones as $opcion): ?>
                                    <option value="<?= $opcion['id'] ?>">[<?= $opcion['categoria'] ?>] <?= htmlspecialchars($opcion['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><strong>Entonces NO puedo seleccionar:</strong></label>
                            <div class="dual-list-container">
                                <div class="dual-list-box">
                                    <select id="opciones-disponibles" multiple size="12">
                                        <!-- Opciones disponibles -->
                                    </select>
                                </div>
                                <div class="dual-list-actions">
                                    <button type="button" id="add-exclusion">&gt;&gt;</button>
                                    <button type="button" id="remove-exclusion">&lt;&lt;</button>
                                </div>
                                <div class="dual-list-box">
                                    <select id="opciones-excluidas" name="opciones_excluidas[]" multiple size="12">
                                        <!-- Opciones excluidas -->
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><strong>Mensaje de error:</strong></label>
                            <input type="text" name="mensaje_error" placeholder="Esta opción no es compatible con la selección anterior">
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar Exclusiones</button>
                    </form>
                </div>
                <!-- Lista de reglas existentes (igual que original) -->
                <div class="rules-section">
                    <h2>Reglas de Exclusión Existentes (<?= count($reglas) ?>)</h2>
                    <?php if (empty($reglas)): ?>
                        <p style="color: var(--text-secondary);">No hay reglas de exclusión configuradas.</p>
                    <?php else: ?>
                        <?php
                        // Agrupar reglas por opcion_id para edición masiva
                        $reglas_por_opcion = [];
                        foreach ($reglas as $regla) {
                            $reglas_por_opcion[$regla['opcion_id']][] = $regla;
                        }
                        foreach ($reglas_por_opcion as $opcion_id => $grupo):
                            $primera = $grupo[0];
                        ?>
                            <div class="rule-item <?= $primera['activo'] ? '' : 'inactive' ?>">
                                <div class="rule-info">
                                    <div><strong>Si selecciono:</strong> [<?= $primera['categoria_nombre'] ?>] <?= htmlspecialchars($primera['opcion_nombre']) ?></div>
                                    <div><strong>Entonces NO puedo seleccionar:</strong>
                                        <ul style="margin: 0 0 0 1.5em; color: var(--text-primary);">
                                            <?php foreach ($grupo as $regla): ?>
                                                <li>[<?= $regla['categoria_excluida_nombre'] ?>] <?= htmlspecialchars($regla['opcion_excluida_nombre']) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div><strong>Mensaje:</strong> <?= htmlspecialchars($primera['mensaje_error']) ?></div>
                                    <div><strong>Estado:</strong> <?= $primera['activo'] ? '✅ Activa' : '❌ Inactiva' ?></div>
                                </div>
                                <div class="rule-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="regla_id" value="<?= $primera['id'] ?>">
                                        <input type="hidden" name="activo" value="<?= $primera['activo'] ?>">
                                        <button type="submit" class="btn btn-<?= $primera['activo'] ? 'warning' : 'success' ?>">
                                            <?= $primera['activo'] ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta regla?')">
                                        <input type="hidden" name="action" value="eliminar">
                                        <input type="hidden" name="regla_id" value="<?= $primera['id'] ?>">
                                        <button type="submit" class="btn btn-danger">Eliminar</button>
                                    </form>
                                    <button type="button" class="btn btn-secondary btn-editar" data-opcion-id="<?= $opcion_id ?>">Editar</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Modal de edición -->
                <div id="modal-editar" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:1000; align-items:center; justify-content:center;">
                    <div style="background:var(--bg-card); padding:2.5rem; border-radius:var(--radius-lg); min-width:700px; max-width:90vw; margin:auto; position:relative;">
                        <button id="cerrar-modal-editar" style="position:absolute; top:1rem; right:1rem; background:none; border:none; color:var(--text-secondary); font-size:2rem; cursor:pointer;">&times;</button>
                        <h2>Editar Exclusiones</h2>
                        <form method="POST" id="form-editar-dual">
                            <input type="hidden" name="action" value="editar_dual">
                            <input type="hidden" name="opcion_id" id="editar_opcion_id">
                            <div class="form-group">
                                <label><strong>Si selecciono:</strong></label>
                                <input type="text" id="editar_opcion_nombre" disabled style="width:100%; background:var(--bg-tertiary); color:var(--text-primary); border:1px solid var(--border-color); padding:var(--spacing-md); border-radius:var(--radius-md);">
                            </div>
                            <div class="form-group">
                                <label><strong>Entonces NO puedo seleccionar:</strong></label>
                                <div class="dual-list-container">
                                    <div class="dual-list-box">
                                        <select id="editar_opciones_disponibles" multiple size="12"></select>
                                    </div>
                                    <div class="dual-list-actions">
                                        <button type="button" id="editar_add_exclusion">&gt;&gt;</button>
                                        <button type="button" id="editar_remove_exclusion">&lt;&lt;</button>
                                    </div>
                                    <div class="dual-list-box">
                                        <select id="editar_opciones_excluidas" name="opciones_excluidas[]" multiple size="12"></select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><strong>Mensaje de error:</strong></label>
                                <input type="text" name="mensaje_error" id="editar_mensaje_error" placeholder="Esta opción no es compatible con la selección anterior">
                            </div>
                            <div style="display:flex; gap:1rem; justify-content:flex-end;">
                                <button type="button" class="btn btn-secondary" id="cancelar-edicion">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                            </div>
                        </form>
                    </div>
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
            document.getElementById('nav-rules-icon').innerHTML = modernUI.getIcon('check-shield');
            document.getElementById('nav-calculator-icon').innerHTML = modernUI.getIcon('cart');
            document.getElementById('nav-logout-icon').innerHTML = modernUI.getIcon('logout');
        });

        // Opciones PHP a JS
        const opciones_totales = <?php echo json_encode($opciones); ?>;
        let opciones_filtradas = [...opciones_totales];
        const reglas = <?php echo json_encode($reglas); ?>;

        const categoriaFiltro = document.getElementById('categoria_filtro');
        const opcionSelect = document.getElementById('opcion_id');
        const disponibles = document.getElementById('opciones-disponibles');
        const excluidas = document.getElementById('opciones-excluidas');
        const addBtn = document.getElementById('add-exclusion');
        const removeBtn = document.getElementById('remove-exclusion');
        
        function actualizarOpcionesPrincipales() {
            const categoriaId = categoriaFiltro.value;
            opcionSelect.innerHTML = '<option value="">Selecciona una opción...</option>';
            
            opciones_filtradas = categoriaId ? opciones_totales.filter(op => op.categoria_id == categoriaId) : opciones_totales;

            opciones_filtradas.forEach(op => {
                const option = document.createElement('option');
                option.value = op.id;
                option.textContent = `[${op.categoria}] ${op.nombre}`;
                opcionSelect.appendChild(option);
            });

            renderDualList();
        }

        categoriaFiltro.addEventListener('change', actualizarOpcionesPrincipales);

        function renderDualList() {
            // Limpiar
            disponibles.innerHTML = '';
            excluidas.innerHTML = '';
            const selectedOpcion = opcionSelect.value;
            if (!selectedOpcion) return;
            // Buscar exclusiones actuales
            const excluidasIds = reglas.filter(r => r.opcion_id == selectedOpcion).map(r => r.opcion_excluida_id.toString());
            // Llenar disponibles y excluidas con TODAS las opciones
            opciones_totales.forEach(op => {
                if (op.id == selectedOpcion) return; // No autoexclusión
                const option = document.createElement('option');
                option.value = op.id;
                option.textContent = `[${op.categoria}] ${op.nombre}`;
                if (excluidasIds.includes(op.id.toString())) {
                    excluidas.appendChild(option);
                } else {
                    disponibles.appendChild(option);
                }
            });
        }

        opcionSelect.addEventListener('change', renderDualList);

        addBtn.addEventListener('click', function() {
            Array.from(disponibles.selectedOptions).forEach(opt => {
                excluidas.appendChild(opt);
            });
        });
        removeBtn.addEventListener('click', function() {
            Array.from(excluidas.selectedOptions).forEach(opt => {
                disponibles.appendChild(opt);
            });
        });

        // Al enviar el formulario, seleccionar todas las opciones excluidas
        document.getElementById('dual-list-form').addEventListener('submit', function() {
            Array.from(excluidas.options).forEach(opt => opt.selected = true);
        });

        // --- EDICIÓN DE REGLAS CON DUAL LIST ---
        const reglasPorOpcion = {};
        reglas.forEach(r => {
            if (!reglasPorOpcion[r.opcion_id]) reglasPorOpcion[r.opcion_id] = [];
            reglasPorOpcion[r.opcion_id].push(r);
        });

        const btnsEditar = document.querySelectorAll('.btn-editar');
        const modalEditar = document.getElementById('modal-editar');
        const cerrarModalEditar = document.getElementById('cerrar-modal-editar');
        const cancelarEdicion = document.getElementById('cancelar-edicion');
        const editarOpcionId = document.getElementById('editar_opcion_id');
        const editarOpcionNombre = document.getElementById('editar_opcion_nombre');
        const editarMensajeError = document.getElementById('editar_mensaje_error');
        const editarDisponibles = document.getElementById('editar_opciones_disponibles');
        const editarExcluidas = document.getElementById('editar_opciones_excluidas');
        const editarAddBtn = document.getElementById('editar_add_exclusion');
        const editarRemoveBtn = document.getElementById('editar_remove_exclusion');

        btnsEditar.forEach(btn => {
            btn.addEventListener('click', function() {
                const opcionId = this.getAttribute('data-opcion-id');
                const grupo = reglasPorOpcion[opcionId];
                if (!grupo) return;
                editarOpcionId.value = opcionId;
                editarOpcionNombre.value = `[${grupo[0].categoria_nombre}] ${grupo[0].opcion_nombre}`;
                editarMensajeError.value = grupo[0].mensaje_error;
                // Llenar dual list
                editarDisponibles.innerHTML = '';
                editarExcluidas.innerHTML = '';
                const excluidasIds = grupo.map(r => r.opcion_excluida_id.toString());
                opciones_totales.forEach(op => {
                    if (op.id == opcionId) return; // No autoexclusión
                    const option = document.createElement('option');
                    option.value = op.id;
                    option.textContent = `[${op.categoria}] ${op.nombre}`;
                    if (excluidasIds.includes(op.id.toString())) {
                        editarExcluidas.appendChild(option);
                    } else {
                        editarDisponibles.appendChild(option);
                    }
                });
                modalEditar.style.display = 'flex';
            });
        });
        cerrarModalEditar.addEventListener('click', () => modalEditar.style.display = 'none');
        cancelarEdicion.addEventListener('click', () => modalEditar.style.display = 'none');
        editarAddBtn.addEventListener('click', function() {
            Array.from(editarDisponibles.selectedOptions).forEach(opt => {
                editarExcluidas.appendChild(opt);
            });
        });
        editarRemoveBtn.addEventListener('click', function() {
            Array.from(editarExcluidas.selectedOptions).forEach(opt => {
                editarDisponibles.appendChild(opt);
            });
        });
        document.getElementById('form-editar-dual').addEventListener('submit', function() {
            Array.from(editarExcluidas.options).forEach(opt => opt.selected = true);
        });
    </script>
</body>
</html> 
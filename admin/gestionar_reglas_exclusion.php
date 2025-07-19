<?php
require_once '../includes/db.php';

// Procesar formulario de nueva regla
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'crear') {
        $opcion_id = $_POST['opcion_id'];
        $opcion_excluida_id = $_POST['opcion_excluida_id'];
        $mensaje_error = $_POST['mensaje_error'];
        
        $stmt = $pdo->prepare("INSERT INTO opciones_excluyentes (opcion_id, opcion_excluida_id, mensaje_error) VALUES (?, ?, ?)");
        $stmt->execute([$opcion_id, $opcion_excluida_id, $mensaje_error]);
        
        header('Location: gestionar_reglas_exclusion.php?success=1');
        exit;
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
    <title>Gestionar Reglas de Exclusión - Admin</title>
    <link rel="stylesheet" href="../assets/css/modern-dark-theme.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .rules-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .rule-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .rule-item.inactive {
            opacity: 0.6;
            background: #f9f9f9;
        }
        .rule-info {
            flex: 1;
        }
        .rule-actions {
            display: flex;
            gap: 10px;
        }
        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Gestionar Reglas de Exclusión</h1>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ Regla creada exitosamente</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">✅ Regla eliminada exitosamente</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">✅ Estado de regla actualizado</div>
        <?php endif; ?>
        
        <!-- Formulario para crear nueva regla -->
        <div class="form-section">
            <h2>Crear Nueva Regla de Exclusión</h2>
            <form method="POST">
                <input type="hidden" name="action" value="crear">
                
                <div style="margin-bottom: 15px;">
                    <label><strong>Si selecciono:</strong></label>
                    <select name="opcion_id" required style="width: 100%; padding: 8px; margin-top: 5px;">
                        <option value="">Selecciona una opción...</option>
                        <?php foreach ($opciones as $opcion): ?>
                            <option value="<?= $opcion['id'] ?>">
                                [<?= $opcion['categoria'] ?>] <?= htmlspecialchars($opcion['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label><strong>Entonces NO puedo seleccionar:</strong></label>
                    <select name="opcion_excluida_id" required style="width: 100%; padding: 8px; margin-top: 5px;">
                        <option value="">Selecciona una opción...</option>
                        <?php foreach ($opciones as $opcion): ?>
                            <option value="<?= $opcion['id'] ?>">
                                [<?= $opcion['categoria'] ?>] <?= htmlspecialchars($opcion['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label><strong>Mensaje de error:</strong></label>
                    <input type="text" name="mensaje_error" 
                           placeholder="Esta opción no es compatible con la selección anterior"
                           style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                
                <button type="submit" class="btn btn-primary">Crear Regla</button>
            </form>
        </div>
        
        <!-- Lista de reglas existentes -->
        <div class="rules-section">
            <h2>Reglas de Exclusión Existentes (<?= count($reglas) ?>)</h2>
            
            <?php if (empty($reglas)): ?>
                <p>No hay reglas de exclusión configuradas.</p>
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
        
        <div style="margin-top: 20px;">
            <a href="index.php" class="btn btn-secondary">← Volver al Dashboard</a>
        </div>
    </div>
</body>
</html> 
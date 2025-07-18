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

foreach ($dbPaths as $dbPath) {
    if (file_exists($dbPath)) {
        require_once $dbPath;
        break;
    }
}

// Obtener datos
$categorias = [];
$opciones = [];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verificar la conexión
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    // Obtener categorías ordenadas por campo orden
    $result = $conn->query("SELECT * FROM categorias ORDER BY orden ASC, nombre ASC");
    if (!$result) {
        die("Error en la consulta de categorías: " . $conn->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }
    
    // Obtener opciones con categorías ordenadas por campo orden
    $query = "SELECT o.*, c.nombre as categoria_nombre 
              FROM opciones o 
              LEFT JOIN categorias c ON o.categoria_id = c.id 
              ORDER BY c.orden ASC, o.orden ASC, o.nombre ASC";
    
    $result = $conn->query($query);
    if (!$result) {
        die("Error en la consulta de opciones: " . $conn->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        $opciones[] = $row;
    }

    // Función para extraer el número de paradas de un nombre
    function extraerNumeroParadas($nombre) {
        // Caso especial para Gearless - asignarle un número alto para que aparezca al final
        if (stripos($nombre, 'Gearless') !== false) {
            return 1000; // Un número muy alto para que aparezca después de todas las paradas numeradas
        }
        
        // Extracción normal para nombres con formato "X Paradas"
        if (preg_match('/(\d+)\s+Paradas/', $nombre, $matches)) {
            return (int)$matches[1];
        }
        
        return 999; // Valor por defecto para los que no tienen número de paradas
    }
    
    // COMENTADO: Esta función sobrescribe el orden de la base de datos
    // Ahora usamos el campo 'orden' de la base de datos en lugar de ordenamiento automático
    /*
    // Ordenar las opciones por número de paradas
    usort($opciones, function($a, $b) {
        // Primero ordenar por categoría
        if ($a['categoria_nombre'] != $b['categoria_nombre']) {
            return strcmp($a['categoria_nombre'], $b['categoria_nombre']);
        }
        
        // Dentro de la misma categoría, ordenar por número de paradas
        $paradasA = extraerNumeroParadas($a['nombre']);
        $paradasB = extraerNumeroParadas($b['nombre']);
        
        if ($paradasA == $paradasB) {
            // Si tienen el mismo número de paradas, ordenar por nombre
            return strcmp($a['nombre'], $b['nombre']);
        }
        
        return $paradasA - $paradasB;
    });
    */
} catch (Exception $e) {
    die("Error en gestionar_datos.php: " . $e->getMessage());
}

// Procesar acciones
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Log de depuración
    error_log("Acción recibida: " . $action);
    error_log("POST data: " . print_r($_POST, true));
    
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        switch ($action) {
            case 'add_categoria':
                $nombre = $_POST['nombre'] ?? '';
                if ($nombre) {
                    // Obtener el siguiente orden
                    $result = $conn->query("SELECT MAX(orden) as max_orden FROM categorias");
                    $max_orden = $result->fetch_assoc()['max_orden'] ?? 0;
                    $nuevo_orden = $max_orden + 1;
                    
                    $stmt = $conn->prepare("INSERT INTO categorias (nombre, orden) VALUES (?, ?)");
                    $stmt->bind_param("si", $nombre, $nuevo_orden);
                    if ($stmt->execute()) {
                        $mensaje = "Categoría agregada exitosamente";
                    }
                }
                break;
                
            case 'add_opcion':
                $categoria_id = $_POST['categoria_id'] ?? 0;
                $nombre = $_POST['nombre'] ?? '';
                $precio_90 = $_POST['precio_90_dias'] ?? 0;
                $precio_160 = $_POST['precio_160_dias'] ?? 0;
                $precio_270 = $_POST['precio_270_dias'] ?? 0;
                $descuento = $_POST['descuento'] ?? 0;
                
                if ($nombre && $categoria_id) {
                    // Obtener el siguiente orden para esta categoría
                    $result = $conn->query("SELECT MAX(orden) as max_orden FROM opciones WHERE categoria_id = $categoria_id");
                    $max_orden = $result->fetch_assoc()['max_orden'] ?? 0;
                    $nuevo_orden = $max_orden + 1;
                    
                    $stmt = $conn->prepare("INSERT INTO opciones (categoria_id, nombre, precio_90_dias, precio_160_dias, precio_270_dias, descuento, orden) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isddddi", $categoria_id, $nombre, $precio_90, $precio_160, $precio_270, $descuento, $nuevo_orden);
                    if ($stmt->execute()) {
                        $mensaje = "Opción agregada exitosamente";
                    }
                }
                break;
                
            case 'duplicate_opcion':
                $id = $_POST['id'] ?? 0;
                
                if ($id) {
                    // Primero obtener los datos de la opción original
                    $stmt = $conn->prepare("SELECT * FROM opciones WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($opcion = $result->fetch_assoc()) {
                        // Obtener el siguiente orden para esta categoría
                        $result = $conn->query("SELECT MAX(orden) as max_orden FROM opciones WHERE categoria_id = " . $opcion['categoria_id']);
                        $max_orden = $result->fetch_assoc()['max_orden'] ?? 0;
                        $nuevo_orden = $max_orden + 1;
                        
                        // Crear una copia con nombre modificado
                        $nombre_copia = $opcion['nombre'] . ' (copia)';
                        $stmt = $conn->prepare("INSERT INTO opciones (categoria_id, nombre, precio_90_dias, precio_160_dias, precio_270_dias, descuento, orden) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param(
                            "isddddi", 
                            $opcion['categoria_id'], 
                            $nombre_copia, 
                            $opcion['precio_90_dias'], 
                            $opcion['precio_160_dias'], 
                            $opcion['precio_270_dias'], 
                            $opcion['descuento'],
                            $nuevo_orden
                        );
                        
                        if ($stmt->execute()) {
                            $mensaje = "Opción duplicada exitosamente";
                        }
                    }
                }
                break;
                
            case 'edit_opcion':
                $id = $_POST['id'] ?? 0;
                $categoria_id = $_POST['categoria_id'] ?? 0;
                $nombre = $_POST['nombre'] ?? '';
                $precio_90 = $_POST['precio_90_dias'] ?? 0;
                $precio_160 = $_POST['precio_160_dias'] ?? 0;
                $precio_270 = $_POST['precio_270_dias'] ?? 0;
                $descuento = $_POST['descuento'] ?? 0;
                
                if ($id && $nombre && $categoria_id) {
                    $stmt = $conn->prepare("UPDATE opciones SET categoria_id=?, nombre=?, precio_90_dias=?, precio_160_dias=?, precio_270_dias=?, descuento=? WHERE id=?");
                    $stmt->bind_param("isddddi", $categoria_id, $nombre, $precio_90, $precio_160, $precio_270, $descuento, $id);
                    if ($stmt->execute()) {
                        $mensaje = "Opción actualizada exitosamente";
                    }
                }
                break;
                
            case 'delete_opcion':
                $id = $_POST['id'] ?? 0;
                if ($id) {
                    // Verificar si hay registros en presupuesto_detalles que referencian esta opción
                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM presupuesto_detalles WHERE opcion_id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = $result->fetch_assoc()['total'];
                    
                    if ($count > 0) {
                        // Hay referencias, preguntar al usuario qué hacer
                        $force_delete = $_POST['force_delete'] ?? false;
                        
                        if (!$force_delete) {
                            // Mostrar mensaje de confirmación
                            $error = "Esta opción está siendo utilizada en $count presupuesto(s). ¿Deseas eliminarla de todos modos? Esto también eliminará las referencias en los presupuestos.";
                            break;
                        } else {
                            // El usuario confirmó, eliminar primero las referencias
                            $conn->begin_transaction();
                            
                            try {
                                // Eliminar primero los registros dependientes
                                $stmt = $conn->prepare("DELETE FROM presupuesto_detalles WHERE opcion_id = ?");
                                $stmt->bind_param("i", $id);
                                $stmt->execute();
                                
                                // Ahora eliminar la opción
                                $stmt = $conn->prepare("DELETE FROM opciones WHERE id = ?");
                                $stmt->bind_param("i", $id);
                                $stmt->execute();
                                
                                $conn->commit();
                                $mensaje = "Opción eliminada exitosamente (junto con $count referencias en presupuestos)";
                            } catch (Exception $e) {
                                $conn->rollback();
                                throw $e;
                            }
                        }
                    } else {
                        // No hay referencias, eliminar directamente
                        $stmt = $conn->prepare("DELETE FROM opciones WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            $mensaje = "Opción eliminada exitosamente";
                        }
                    }
                }
                break;
                
            case 'move_categoria_up':
                $id = $_POST['id'] ?? 0;
                if ($id) {
                    // Obtener el orden actual
                    $stmt = $conn->prepare("SELECT orden FROM categorias WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $categoria_actual = $result->fetch_assoc();
                    
                    if ($categoria_actual) {
                        $orden_actual = $categoria_actual['orden'] ?? 0;
                        
                        // Buscar la categoría anterior
                        $stmt = $conn->prepare("SELECT id, orden FROM categorias WHERE orden < ? ORDER BY orden DESC LIMIT 1");
                        $stmt->bind_param("i", $orden_actual);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $categoria_anterior = $result->fetch_assoc();
                        
                        if ($categoria_anterior) {
                            // Intercambiar órdenes
                            $orden_anterior = $categoria_anterior['orden'];
                            $id_anterior = $categoria_anterior['id'];
                            
                            $conn->begin_transaction();
                            
                            $stmt1 = $conn->prepare("UPDATE categorias SET orden = ? WHERE id = ?");
                            $stmt1->bind_param("ii", $orden_anterior, $id);
                            $stmt1->execute();
                            
                            $stmt2 = $conn->prepare("UPDATE categorias SET orden = ? WHERE id = ?");
                            $stmt2->bind_param("ii", $orden_actual, $id_anterior);
                            $stmt2->execute();
                            
                            $conn->commit();
                            $mensaje = "Categoría movida hacia arriba";
                        } else {
                            $mensaje = "La categoría ya está en la primera posición";
                        }
                    } else {
                        $mensaje = "Error: No se encontró la categoría";
                    }
                } else {
                    $mensaje = "Error: ID de categoría no válido";
                }
                break;
                
            case 'move_categoria_down':
                $id = $_POST['id'] ?? 0;
                if ($id) {
                    // Obtener el orden actual
                    $stmt = $conn->prepare("SELECT orden FROM categorias WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $categoria_actual = $result->fetch_assoc();
                    
                    if ($categoria_actual) {
                        $orden_actual = $categoria_actual['orden'] ?? 0;
                        
                        // Buscar la categoría siguiente
                        $stmt = $conn->prepare("SELECT id, orden FROM categorias WHERE orden > ? ORDER BY orden ASC LIMIT 1");
                        $stmt->bind_param("i", $orden_actual);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $categoria_siguiente = $result->fetch_assoc();
                        
                        if ($categoria_siguiente) {
                            // Intercambiar órdenes
                            $orden_siguiente = $categoria_siguiente['orden'];
                            $id_siguiente = $categoria_siguiente['id'];
                            
                            $conn->begin_transaction();
                            
                            $stmt1 = $conn->prepare("UPDATE categorias SET orden = ? WHERE id = ?");
                            $stmt1->bind_param("ii", $orden_siguiente, $id);
                            $stmt1->execute();
                            
                            $stmt2 = $conn->prepare("UPDATE categorias SET orden = ? WHERE id = ?");
                            $stmt2->bind_param("ii", $orden_actual, $id_siguiente);
                            $stmt2->execute();
                            
                            $conn->commit();
                            $mensaje = "Categoría movida hacia abajo";
                        } else {
                            $mensaje = "La categoría ya está en la última posición";
                        }
                    } else {
                        $mensaje = "Error: No se encontró la categoría";
                    }
                } else {
                    $mensaje = "Error: ID de categoría no válido";
                }
                break;
                
            case 'move_opcion_up':
                $id = $_POST['id'] ?? 0;
                if ($id) {
                    // Obtener la opción actual
                    $stmt = $conn->prepare("SELECT categoria_id, orden FROM opciones WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $opcion_actual = $result->fetch_assoc();
                    
                    if ($opcion_actual) {
                        $categoria_id = $opcion_actual['categoria_id'];
                        $orden_actual = $opcion_actual['orden'] ?? 0;
                        
                        // Buscar la opción anterior en la misma categoría
                        $stmt = $conn->prepare("SELECT id, orden FROM opciones WHERE categoria_id = ? AND orden < ? ORDER BY orden DESC LIMIT 1");
                        $stmt->bind_param("ii", $categoria_id, $orden_actual);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $opcion_anterior = $result->fetch_assoc();
                        
                        if ($opcion_anterior) {
                            // Intercambiar órdenes
                            $orden_anterior = $opcion_anterior['orden'];
                            $id_anterior = $opcion_anterior['id'];
                            
                            $conn->begin_transaction();
                            
                            $stmt1 = $conn->prepare("UPDATE opciones SET orden = ? WHERE id = ?");
                            $stmt1->bind_param("ii", $orden_anterior, $id);
                            $stmt1->execute();
                            
                            $stmt2 = $conn->prepare("UPDATE opciones SET orden = ? WHERE id = ?");
                            $stmt2->bind_param("ii", $orden_actual, $id_anterior);
                            $stmt2->execute();
                            
                            $conn->commit();
                            $mensaje = "Opción movida hacia arriba";
                        } else {
                            $mensaje = "La opción ya está en la primera posición de su categoría";
                        }
                    } else {
                        $mensaje = "Error: No se encontró la opción";
                    }
                } else {
                    $mensaje = "Error: ID de opción no válido";
                }
                break;
                
            case 'move_opcion_down':
                $id = $_POST['id'] ?? 0;
                if ($id) {
                    // Obtener la opción actual
                    $stmt = $conn->prepare("SELECT categoria_id, orden FROM opciones WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $opcion_actual = $result->fetch_assoc();
                    
                    if ($opcion_actual) {
                        $categoria_id = $opcion_actual['categoria_id'];
                        $orden_actual = $opcion_actual['orden'] ?? 0;
                        
                        // Buscar la opción siguiente en la misma categoría
                        $stmt = $conn->prepare("SELECT id, orden FROM opciones WHERE categoria_id = ? AND orden > ? ORDER BY orden ASC LIMIT 1");
                        $stmt->bind_param("ii", $categoria_id, $orden_actual);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $opcion_siguiente = $result->fetch_assoc();
                        
                        if ($opcion_siguiente) {
                            // Intercambiar órdenes
                            $orden_siguiente = $opcion_siguiente['orden'];
                            $id_siguiente = $opcion_siguiente['id'];
                            
                            $conn->begin_transaction();
                            
                            $stmt1 = $conn->prepare("UPDATE opciones SET orden = ? WHERE id = ?");
                            $stmt1->bind_param("ii", $orden_siguiente, $id);
                            $stmt1->execute();
                            
                            $stmt2 = $conn->prepare("UPDATE opciones SET orden = ? WHERE id = ?");
                            $stmt2->bind_param("ii", $orden_actual, $id_siguiente);
                            $stmt2->execute();
                            
                            $conn->commit();
                            $mensaje = "Opción movida hacia abajo";
                        } else {
                            $mensaje = "La opción ya está en la última posición de su categoría";
                        }
                    } else {
                        $mensaje = "Error: No se encontró la opción";
                    }
                } else {
                    $mensaje = "Error: ID de opción no válido";
                }
                break;
                
            case 'move_categoria_to_position':
                $id = $_POST['id'] ?? 0;
                $nueva_posicion = $_POST['posicion'] ?? 0;
                
                if ($id && $nueva_posicion > 0) {
                    $conn->begin_transaction();
                    
                    // Obtener la posición actual
                    $stmt = $conn->prepare("SELECT orden FROM categorias WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $categoria_actual = $result->fetch_assoc();
                    
                    if ($categoria_actual) {
                        $posicion_actual = $categoria_actual['orden'];
                        
                        if ($posicion_actual != $nueva_posicion) {
                            // Ajustar las posiciones de otras categorías
                            if ($nueva_posicion < $posicion_actual) {
                                // Mover hacia arriba: incrementar orden de las que están entre nueva_posicion y posicion_actual
                                $stmt = $conn->prepare("UPDATE categorias SET orden = orden + 1 WHERE orden >= ? AND orden < ?");
                                $stmt->bind_param("ii", $nueva_posicion, $posicion_actual);
                                $stmt->execute();
                            } else {
                                // Mover hacia abajo: decrementar orden de las que están entre posicion_actual y nueva_posicion
                                $stmt = $conn->prepare("UPDATE categorias SET orden = orden - 1 WHERE orden > ? AND orden <= ?");
                                $stmt->bind_param("ii", $posicion_actual, $nueva_posicion);
                                $stmt->execute();
                            }
                            
                            // Actualizar la posición del elemento movido
                            $stmt = $conn->prepare("UPDATE categorias SET orden = ? WHERE id = ?");
                            $stmt->bind_param("ii", $nueva_posicion, $id);
                            $stmt->execute();
                            
                            $conn->commit();
                            $mensaje = "Categoría movida a la posición $nueva_posicion";
                        } else {
                            $mensaje = "La categoría ya está en esa posición";
                        }
                    } else {
                        $conn->rollback();
                        $mensaje = "Error: No se encontró la categoría";
                    }
                } else {
                    $mensaje = "Error: Datos no válidos";
                }
                break;
                
            case 'move_opcion_to_position':
                $id = $_POST['id'] ?? 0;
                $nueva_posicion = $_POST['posicion'] ?? 0;
                $categoria_id = $_POST['categoria_id'] ?? 0;
                
                if ($id && $nueva_posicion > 0 && $categoria_id) {
                    $conn->begin_transaction();
                    
                    // Obtener la posición actual
                    $stmt = $conn->prepare("SELECT orden FROM opciones WHERE id = ? AND categoria_id = ?");
                    $stmt->bind_param("ii", $id, $categoria_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $opcion_actual = $result->fetch_assoc();
                    
                    if ($opcion_actual) {
                        $posicion_actual = $opcion_actual['orden'];
                        
                        if ($posicion_actual != $nueva_posicion) {
                            // Ajustar las posiciones de otras opciones en la misma categoría
                            if ($nueva_posicion < $posicion_actual) {
                                // Mover hacia arriba: incrementar orden de las que están entre nueva_posicion y posicion_actual
                                $stmt = $conn->prepare("UPDATE opciones SET orden = orden + 1 WHERE categoria_id = ? AND orden >= ? AND orden < ?");
                                $stmt->bind_param("iii", $categoria_id, $nueva_posicion, $posicion_actual);
                                $stmt->execute();
                            } else {
                                // Mover hacia abajo: decrementar orden de las que están entre posicion_actual y nueva_posicion
                                $stmt = $conn->prepare("UPDATE opciones SET orden = orden - 1 WHERE categoria_id = ? AND orden > ? AND orden <= ?");
                                $stmt->bind_param("iii", $categoria_id, $posicion_actual, $nueva_posicion);
                                $stmt->execute();
                            }
                            
                            // Actualizar la posición del elemento movido
                            $stmt = $conn->prepare("UPDATE opciones SET orden = ? WHERE id = ?");
                            $stmt->bind_param("ii", $nueva_posicion, $id);
                            $stmt->execute();
                            
                            $conn->commit();
                            $mensaje = "Opción movida a la posición $nueva_posicion";
                        } else {
                            $mensaje = "La opción ya está en esa posición";
                        }
                    } else {
                        $conn->rollback();
                        $mensaje = "Error: No se encontró la opción";
                    }
                } else {
                    $mensaje = "Error: Datos no válidos";
                }
                break;
        }
        
        // Recargar página para mostrar cambios - SOLO si no hay error con botones
        if (isset($error_con_botones)) {
            // No hacer redirección, mantener en la misma página para mostrar botones
        } else if ($mensaje) {
            header("Location: gestionar_datos.php?msg=" . urlencode($mensaje));
            exit;
        } else if ($error) {
            header("Location: gestionar_datos.php?error=" . urlencode($error));
            exit;
        } else {
            header("Location: gestionar_datos.php");
            exit;
        }
        
    } catch (Exception $e) {
        // Para mysqli no existe inTransaction(), así que simplemente intentamos rollback
        if (isset($conn)) {
            try {
                $conn->rollback();
            } catch (Exception $rollbackException) {
                // Si falla el rollback, lo registramos pero continuamos
                error_log("Error en rollback: " . $rollbackException->getMessage());
            }
        }
        $error = "Error: " . $e->getMessage();
        error_log("Error en gestionar_datos.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Recargar página para mostrar cambios - SOLO si no hay error con botones
        if (isset($error_con_botones)) {
            // No hacer redirección, mantener en la misma página para mostrar botones
        } else if ($mensaje) {
            header("Location: gestionar_datos.php?msg=" . urlencode($mensaje));
            exit;
        } else if ($error) {
            header("Location: gestionar_datos.php?error=" . urlencode($error));
            exit;
        } else {
            header("Location: gestionar_datos.php");
            exit;
        }
    }
}

// Mensaje de la URL
if (isset($_GET['msg'])) {
    $mensaje = $_GET['msg'];
}

if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Datos - Panel Admin</title>
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

        /* Tabs */
        .tabs-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-lg);
        }

        .tabs-header {
            display: flex;
            gap: 1rem;
            margin-top: 2.5rem;
            margin-bottom: 2.5rem;
            margin-left: 2.5rem;
            margin-right: 2.5rem;
        }

        .tab-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            color: #f3f4f6;
            background: #343a40;
            border: 1.5px solid #23272b;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(26,38,54,0.06);
            cursor: pointer;
            transition: background 0.18s, color 0.18s, border 0.18s;
            outline: none;
        }

        .tab-button:hover {
            background: #23272b;
            color: #fff;
            border-color: #111;
        }

        .tab-button.active {
            background: #23272b;
            color: #fff;
            border-color: #111;
            box-shadow: 0 2px 8px rgba(26,38,54,0.10);
        }

        .tab-button span[class^="icon"] {
            display: flex;
            align-items: center;
            font-size: 1.2em;
        }

        .tab-content {
            padding: var(--spacing-lg);
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-lg);
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: var(--spacing-sm) var(--spacing-md);
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            background: transparent;
            border: none;
            color: var(--text-primary);
            outline: none;
            flex: 1;
        }

        /* Data table mejorada */
        .data-table {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .table-row {
            display: grid;
            grid-template-columns: 2fr 80px 3fr 1fr 1fr 1fr 1fr 100px 120px;
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            align-items: center;
            transition: background 0.2s ease;
        }

        .table-header {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: var(--text-xs);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-row:hover:not(.table-header) {
            background: var(--bg-hover);
        }

        .table-cell {
            padding: 0 var(--spacing-sm);
        }

        .price-cell {
            font-family: var(--font-mono);
            color: var(--accent-success);
        }

        .actions-cell {
            display: flex;
            gap: var(--spacing-xs);
            justify-content: flex-end;
        }

        /* Controles de ordenamiento */
        .order-controls {
            display: flex;
            gap: 2px;
            justify-content: center;
            align-items: center;
        }

        .btn-xs {
            padding: 2px 6px;
            font-size: 10px;
            min-width: 20px;
            height: 20px;
            border-radius: 3px;
        }

        .position-input {
            width: 45px;
            height: 20px;
            padding: 2px 4px;
            font-size: 10px;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            background: var(--bg-primary);
            color: var(--text-primary);
            text-align: center;
            margin: 0 2px;
        }

        .position-input:focus {
            outline: none;
            border-color: var(--accent-primary);
        }

        /* Tabla de categorías */
        #tab-categorias .table-row {
            grid-template-columns: 2fr 80px 1fr 100px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-lg);
        }

        .modal-title {
            font-size: var(--text-xl);
            font-weight: 600;
        }

        /* Stats cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .mini-stat {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            text-align: center;
        }

        .mini-stat-value {
            font-size: var(--text-2xl);
            font-weight: 700;
            color: var(--accent-primary);
        }

        .mini-stat-label {
            font-size: var(--text-xs);
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: var(--spacing-xl) * 2;
            color: var(--text-muted);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
            opacity: 0.3;
        }

        .btn-disabled, .btn:disabled {
            opacity: 0.5 !important;
            pointer-events: none !important;
            cursor: not-allowed !important;
        }
        input:disabled, select:disabled, textarea:disabled {
            background: #eee !important;
            color: #888 !important;
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
                <a href="gestionar_datos.php" class="sidebar-item active">
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
                    <div style="display: flex; align-items: center; gap: 1.5rem;">
                        <h2 class="header-title" style="font-size: var(--text-lg); font-weight: 600;">Gestionar Datos</h2>
                        <?php if (!empty($_SESSION['is_demo'])): ?>
                            <span style="color: #fff; font-weight: 600; font-size: 1rem; margin-left: 1.5rem;">Modo Demo - Las acciones se encuentran deshabilitadas</span>
                        <?php endif; ?>
                    </div>
                    <p class="header-subtitle" style="font-size: var(--text-sm); color: var(--text-secondary);">Administra categorías y opciones del sistema</p>
                </div>
            </header>

            <!-- Content -->
            <div class="content-wrapper">
                <?php if ($mensaje): ?>
                <div class="alert alert-success fade-in">
                    <span id="success-icon"></span>
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger fade-in">
                    <span id="error-icon"></span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($error_con_botones)): ?>
                <div class="alert alert-danger fade-in">
                    <span id="error-icon-2"></span>
                    <?php echo htmlspecialchars($error_con_botones['mensaje']); ?>
                    
                    <div style="margin-top: 15px;">
                        <button class="btn btn-danger" onclick="eliminarOpcionForzado(<?php echo $error_con_botones['opcion_id']; ?>, '<?php echo addslashes($error_con_botones['opcion_nombre']); ?>', <?php echo $error_con_botones['count']; ?>)">
                            Eliminar de todos modos
                        </button>
                        <button class="btn btn-secondary" onclick="location.reload()" style="margin-left: 10px;">
                            Cancelar
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="stats-row">
                    <div class="mini-stat">
                        <div class="mini-stat-value"><?php echo count($categorias); ?></div>
                        <div class="mini-stat-label">Categorías</div>
                    </div>
                    <div class="mini-stat">
                        <div class="mini-stat-value"><?php echo count($opciones); ?></div>
                        <div class="mini-stat-label">Opciones</div>
                    </div>
                    <div class="mini-stat">
                        <div class="mini-stat-value">
                            <?php 
                            $activas = array_filter($opciones, function($o) {
                                return $o['precio_90_dias'] > 0 || $o['precio_160_dias'] > 0 || $o['precio_270_dias'] > 0;
                            });
                            echo count($activas);
                            ?>
                        </div>
                        <div class="mini-stat-label">Con Precio</div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="tabs-container">
                    <div class="tabs-header">
                        <button class="tab-button active" onclick="cambiarTab('opciones')">
                            <span id="tab-options-icon"></span>
                            Opciones
                        </button>
                        <button class="tab-button" onclick="cambiarTab('categorias')">
                            <span id="tab-categories-icon"></span>
                            Categorías
                        </button>
                    </div>

                    <!-- Tab Opciones -->
                    <div id="tab-opciones" class="tab-content active">
                        <!-- Toolbar -->
                        <div class="toolbar">
                            <div class="search-box">
                                <span id="search-icon"></span>
                                <input type="text" placeholder="Buscar opciones..." id="searchInput" onkeyup="filtrarOpciones()">
                            </div>
                            
                            <select class="form-control" style="width: 200px;" onchange="filtrarPorCategoria(this.value)" id="selectCategoria">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (strtolower($cat['nombre']) == 'ascensores') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Table -->
                        <div class="data-table">
                            <div class="table-row table-header">
                                <div class="table-cell">Categoría</div>
                                <div class="table-cell">Posición</div>
                                <div class="table-cell">Nombre</div>
                                <div class="table-cell">160-180 Días</div>
                                <div class="table-cell">90 Días</div>
                                <div class="table-cell">270 Días</div>
                                <div class="table-cell">Descuento</div>
                                <div class="table-cell">Orden</div>
                                <div class="table-cell">Acciones</div>
                            </div>
                            
                            <?php if (empty($opciones)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📦</div>
                                <p>No hay opciones registradas</p>
                                <p class="text-small text-muted">Agrega tu primera opción para comenzar</p>
                            </div>
                            <?php else: ?>
                                <?php 
                                // Calcular posiciones relativas dentro de cada categoría
                                $posiciones_por_categoria = [];
                                foreach ($categorias as $cat) {
                                    $opciones_categoria = array_filter($opciones, function($o) use ($cat) {
                                        return $o['categoria_id'] == $cat['id'];
                                    });
                                    // Ordenar por campo orden
                                    usort($opciones_categoria, function($a, $b) {
                                        return ($a['orden'] ?? 0) - ($b['orden'] ?? 0);
                                    });
                                    // Asignar posiciones
                                    $posicion = 1;
                                    foreach ($opciones_categoria as $opcion) {
                                        $posiciones_por_categoria[$opcion['id']] = $posicion++;
                                    }
                                }
                                ?>
                                <?php foreach ($opciones as $opcion): ?>
                                <div class="table-row opcion-row" data-categoria="<?php echo $opcion['categoria_id']; ?>" data-nombre="<?php echo strtolower($opcion['nombre']); ?>">
                                    <div class="table-cell">
                                        <span class="badge badge-primary">
                                            <?php echo htmlspecialchars($opcion['categoria_nombre'] ?? 'Sin categoría'); ?>
                                        </span>
                                    </div>
                                    <div class="table-cell">
                                        <span class="badge badge-secondary" style="font-family: var(--font-mono); font-weight: 600;">
                                            #<?php echo $posiciones_por_categoria[$opcion['id']] ?? 0; ?>
                                        </span>
                                    </div>
                                    <div class="table-cell">
                                        <strong><?php echo htmlspecialchars($opcion['nombre']); ?></strong>
                                    </div>
                                    <div class="table-cell price-cell">
                                        <?php echo $opcion['precio_160_dias'] > 0 ? '$' . number_format($opcion['precio_160_dias'], 2, ',', '.') : '-'; ?>
                                    </div>
                                    <div class="table-cell price-cell">
                                        <?php echo $opcion['precio_90_dias'] > 0 ? '$' . number_format($opcion['precio_90_dias'], 2, ',', '.') : '-'; ?>
                                    </div>
                                    <div class="table-cell price-cell">
                                        <?php echo $opcion['precio_270_dias'] > 0 ? '$' . number_format($opcion['precio_270_dias'], 2, ',', '.') : '-'; ?>
                                    </div>
                                    <div class="table-cell">
                                        <?php if ($opcion['descuento'] > 0): ?>
                                            <span class="badge badge-success"><?php echo $opcion['descuento']; ?>%</span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </div>
                                    <div class="table-cell">
                                        <div class="order-controls">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="move_opcion_up">
                                                <input type="hidden" name="id" value="<?php echo $opcion['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-secondary<?php if (!empty($_SESSION['is_demo'])) echo ' btn-disabled'; ?>" title="Subir" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                                                    <span>↑</span>
                                                </button>
                                            </form>
                                            <input type="number" 
                                                   class="position-input" 
                                                   value="<?php echo $opcion['orden'] ?? 0; ?>" 
                                                   min="1" 
                                                   title="Posición (Enter para aplicar)"
                                                   onkeypress="if(event.key==='Enter') moverOpcionAPosicion(<?php echo $opcion['id']; ?>, this.value, <?php echo $opcion['categoria_id']; ?>)"
                                                   onblur="if(this.value != <?php echo $opcion['orden'] ?? 0; ?>) moverOpcionAPosicion(<?php echo $opcion['id']; ?>, this.value, <?php echo $opcion['categoria_id']; ?>)">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="move_opcion_down">
                                                <input type="hidden" name="id" value="<?php echo $opcion['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-secondary<?php if (!empty($_SESSION['is_demo'])) echo ' btn-disabled'; ?>" title="Bajar" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                                                    <span>↓</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="table-cell actions-cell">
                                        <button class="btn btn-sm btn-secondary<?php if (!empty($_SESSION['is_demo'])) echo ' btn-disabled'; ?>" onclick="duplicarOpcion(<?php echo $opcion['id']; ?>)" title="Duplicar" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                                            <span id="duplicate-icon-<?php echo $opcion['id']; ?>"></span>
                                        </button>
                                        <button class="btn btn-sm btn-secondary<?php if (!empty($_SESSION['is_demo'])) echo ' btn-disabled'; ?>" onclick="editarOpcion(<?php echo $opcion['id']; ?>)" title="Editar" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                                            <span id="edit-icon-<?php echo $opcion['id']; ?>"></span>
                                        </button>
                                        <button class="btn btn-sm btn-danger<?php if (!empty($_SESSION['is_demo'])) echo ' btn-disabled'; ?>" onclick="eliminarOpcion(<?php echo $opcion['id']; ?>, '<?php echo addslashes($opcion['nombre']); ?>')" title="Eliminar" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                                            <span id="delete-icon-<?php echo $opcion['id']; ?>"></span>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tab Categorías -->
                    <div id="tab-categorias" class="tab-content">
                        <div class="toolbar">
                            <h3>Gestión de Categorías</h3>
                            <button class="btn btn-primary" onclick="mostrarModalCategoria()">
                                <span id="add-cat-icon"></span>
                                Nueva Categoría
                            </button>
                        </div>

                        <div class="data-table">
                            <div class="table-row table-header">
                                <div class="table-cell">Nombre</div>
                                <div class="table-cell">Posición</div>
                                <div class="table-cell">Opciones</div>
                                <div class="table-cell">Orden</div>
                            </div>
                            
                            <?php if (empty($categorias)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📁</div>
                                <p>No hay categorías registradas</p>
                                <p class="text-small text-muted">Agrega tu primera categoría para comenzar</p>
                            </div>
                            <?php else: ?>
                                <?php 
                                // Calcular posiciones relativas de categorías
                                $categorias_ordenadas = [...$categorias];
                                usort($categorias_ordenadas, function($a, $b) {
                                    return ($a['orden'] ?? 0) - ($b['orden'] ?? 0);
                                });
                                $posiciones_categorias = [];
                                $posicion = 1;
                                foreach ($categorias_ordenadas as $cat) {
                                    $posiciones_categorias[$cat['id']] = $posicion++;
                                }
                                ?>
                                <?php foreach ($categorias as $cat): ?>
                                <div class="table-row">
                                    <div class="table-cell">
                                        <strong><?php echo htmlspecialchars($cat['nombre']); ?></strong>
                                    </div>
                                    <div class="table-cell">
                                        <span class="badge badge-secondary" style="font-family: var(--font-mono); font-weight: 600;">
                                            #<?php echo $posiciones_categorias[$cat['id']] ?? 0; ?>
                                        </span>
                                    </div>
                                    <div class="table-cell">
                                        <span class="badge badge-primary">
                                            <?php 
                                            $count = count(array_filter($opciones, function($o) use ($cat) {
                                                return $o['categoria_id'] == $cat['id'];
                                            }));
                                            echo $count . ' opciones';
                                            ?>
                                        </span>
                                    </div>
                                    <div class="table-cell">
                                        <div class="order-controls">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="move_categoria_up">
                                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-secondary<?php if (!empty($_SESSION['is_demo'])) echo ' btn-disabled'; ?>" title="Subir" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                                                    <span>↑</span>
                                                </button>
                                            </form>
                                            <input type="number" 
                                                   class="position-input" 
                                                   value="<?php echo $cat['orden'] ?? 0; ?>" 
                                                   min="1" 
                                                   title="Posición (Enter para aplicar)"
                                                   onkeypress="if(event.key==='Enter') moverCategoriaAPosicion(<?php echo $cat['id']; ?>, this.value)"
                                                   onblur="if(this.value != <?php echo $cat['orden'] ?? 0; ?>) moverCategoriaAPosicion(<?php echo $cat['id']; ?>, this.value)">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="move_categoria_down">
                                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-secondary<?php if (!empty($_SESSION['is_demo'])) echo ' btn-disabled'; ?>" title="Bajar" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                                                    <span>↓</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Agregar Opción -->
    <div id="modalAgregar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Agregar Nueva Opción</h3>
                <button class="btn btn-icon" onclick="cerrarModal('modalAgregar')">
                    <span id="close-modal-icon"></span>
                </button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_opcion">
                
                <div class="form-group">
                    <label class="form-label">Categoría</label>
                    <select name="categoria_id" class="form-control" required>
                        <option value="">Seleccionar categoría</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo htmlspecialchars($cat['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nombre de la Opción</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                
                <div class="grid grid-cols-3" style="gap: var(--spacing-md);">
                    <div class="form-group">
                        <label class="form-label">160-180 Días</label>
                        <input type="text" name="precio_160_dias" class="form-control" onchange="formatearPrecio(this)" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">90 Días</label>
                        <input type="text" name="precio_90_dias" class="form-control" onchange="formatearPrecio(this)" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">270 Días</label>
                        <input type="text" name="precio_270_dias" class="form-control" onchange="formatearPrecio(this)" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descuento (%)</label>
                    <input type="number" name="descuento" class="form-control" min="0" max="100" value="0" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-lg);">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalAgregar')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary<?php if (!empty($_SESSION['is_demo'])) echo ' btn-disabled'; ?>" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                        <span id="save-icon"></span>
                        Guardar Opción
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Agregar Categoría -->
    <div id="modalCategoria" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">Nueva Categoría</h3>
                <button class="btn btn-icon" onclick="cerrarModal('modalCategoria')">
                    <span id="close-cat-icon"></span>
                </button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_categoria">
                
                <div class="form-group">
                    <label class="form-label">Nombre de la Categoría</label>
                    <input type="text" name="nombre" class="form-control" required autofocus>
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-lg);">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalCategoria')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary<?php if (!empty($_SESSION['is_demo'])) echo ' btn-disabled'; ?>" <?php if (!empty($_SESSION['is_demo'])) echo 'disabled'; ?>>
                        <span id="save-cat-icon"></span>
                        Crear Categoría
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Opción -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Editar Opción</h3>
                <button class="btn btn-icon" onclick="cerrarModal('modalEditar')">
                    <span id="close-edit-modal-icon"></span>
                </button>
            </div>
            
            <form method="POST" action="" id="formEditar">
                <input type="hidden" name="action" value="edit_opcion">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label class="form-label">Categoría</label>
                    <select name="categoria_id" id="edit_categoria_id" class="form-control" required>
                        <option value="">Seleccionar categoría</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo htmlspecialchars($cat['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nombre de la Opción</label>
                    <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                </div>
                
                <div class="grid grid-cols-3" style="gap: var(--spacing-md);">
                    <div class="form-group">
                        <label class="form-label">160-180 Días</label>
                        <input type="text" name="precio_160_dias" id="edit_precio_160_dias" class="form-control" onchange="formatearPrecio(this)">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">90 Días</label>
                        <input type="text" name="precio_90_dias" id="edit_precio_90_dias" class="form-control" onchange="formatearPrecio(this)">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">270 Días</label>
                        <input type="text" name="precio_270_dias" id="edit_precio_270_dias" class="form-control" onchange="formatearPrecio(this)">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Descuento (%)</label>
                    <input type="number" name="descuento" id="edit_descuento" class="form-control" min="0" max="100" value="0">
                </div>
                
                <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-lg);">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalEditar')">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <span id="update-icon"></span>
                        Actualizar Opción
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Form oculto para eliminar -->
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="action" value="delete_opcion">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <!-- Form oculto para duplicar -->
    <form id="duplicateForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="action" value="duplicate_opcion">
        <input type="hidden" name="id" id="duplicateId">
    </form>

    <script src="../assets/js/modern-icons.js"></script>
    <script>
        // Cargar iconos
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
            safeSetIcon('add-icon', 'add');
            // Alerts
            safeSetIcon('success-icon', 'check');
            safeSetIcon('error-icon', 'error');
            safeSetIcon('error-icon-2', 'error');
            // Tabs
            safeSetIcon('tab-options-icon', 'package');
            safeSetIcon('tab-categories-icon', 'settings');
            // Search
            safeSetIcon('search-icon', 'search');
            // Table actions
            document.querySelectorAll('[id^="duplicate-icon-"]').forEach(function(el) {
                el.innerHTML = modernUI.getIcon('duplicate', 'icon-sm');
            });
            document.querySelectorAll('[id^="edit-icon-"]').forEach(function(el) {
                el.innerHTML = modernUI.getIcon('edit', 'icon-sm');
            });
            document.querySelectorAll('[id^="delete-icon-"]').forEach(function(el) {
                el.innerHTML = modernUI.getIcon('delete', 'icon-sm');
            });
            // Modal
            safeSetIcon('close-modal-icon', 'close');
            safeSetIcon('save-icon', 'save');
            safeSetIcon('add-cat-icon', 'add');
            safeSetIcon('close-cat-icon', 'close');
            safeSetIcon('save-cat-icon', 'save');
            safeSetIcon('close-edit-modal-icon', 'close');
            safeSetIcon('update-icon', 'update');
        });

        // Funciones
        function cambiarTab(tab) {
            // Ocultar todos los tabs
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
            
            // Mostrar el tab seleccionado
            document.getElementById('tab-' + tab).classList.add('active');
            event.target.closest('.tab-button').classList.add('active');
        }

        function mostrarModalAgregar() {
            document.getElementById('modalAgregar').classList.add('active');
        }

        function mostrarModalCategoria() {
            document.getElementById('modalCategoria').classList.add('active');
        }

        function cerrarModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function filtrarOpciones() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.opcion-row');
            
            rows.forEach(row => {
                const nombre = row.getAttribute('data-nombre');
                if (nombre.includes(searchTerm)) {
                    row.style.display = 'grid';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function filtrarPorCategoria(categoriaId) {
            const rows = document.querySelectorAll('.opcion-row');
            
            rows.forEach(row => {
                if (!categoriaId || row.getAttribute('data-categoria') === categoriaId) {
                    row.style.display = 'grid';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function eliminarOpcion(id, nombre) {
            if (confirm(`¿Estás seguro de eliminar la opción "${nombre}"?`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        function eliminarOpcionForzado(id, nombre, count) {
            if (confirm(`Esta opción está siendo utilizada en ${count} presupuesto(s). ¿Deseas eliminarla de todos modos? Esto también eliminará las referencias en los presupuestos.`)) {
                // Crear un formulario temporal con force_delete
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_opcion';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                const forceInput = document.createElement('input');
                forceInput.type = 'hidden';
                forceInput.name = 'force_delete';
                forceInput.value = '1';
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                form.appendChild(forceInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        function duplicarOpcion(id) {
            if (confirm('¿Deseas duplicar esta opción? Se creará una copia con los mismos valores.')) {
                document.getElementById('duplicateId').value = id;
                document.getElementById('duplicateForm').submit();
            }
        }

        function editarOpcion(id) {
            // Obtener datos de la opción
            fetch(`api_gestionar_datos.php?action=get_opcion&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const opcion = data.opcion;
                        
                        // Llenar el formulario
                        document.getElementById('edit_id').value = opcion.id;
                        document.getElementById('edit_categoria_id').value = opcion.categoria_id;
                        document.getElementById('edit_nombre').value = opcion.nombre;
                        
                        // Formatear precios
                        const precio90 = document.getElementById('edit_precio_90_dias');
                        precio90.value = parseFloat(opcion.precio_90_dias || 0).toLocaleString('es-AR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        precio90.setAttribute('data-valor', opcion.precio_90_dias || 0);
                        
                        const precio160 = document.getElementById('edit_precio_160_dias');
                        precio160.value = parseFloat(opcion.precio_160_dias || 0).toLocaleString('es-AR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        precio160.setAttribute('data-valor', opcion.precio_160_dias || 0);
                        
                        const precio270 = document.getElementById('edit_precio_270_dias');
                        precio270.value = parseFloat(opcion.precio_270_dias || 0).toLocaleString('es-AR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        precio270.setAttribute('data-valor', opcion.precio_270_dias || 0);
                        
                        document.getElementById('edit_descuento').value = opcion.descuento || 0;
                        
                        // Mostrar modal
                        document.getElementById('modalEditar').classList.add('active');
                    } else {
                        modernUI.showToast('Error al cargar los datos de la opción', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modernUI.showToast('Error al cargar los datos de la opción', 'error');
                });
        }

        function exportarDatos() {
            // TODO: Implementar exportación
            modernUI.showToast('Función de exportación en desarrollo', 'info');
        }

        // Formatear precios con puntos y comas
        function formatearPrecio(input) {
            // Eliminar caracteres no numéricos excepto punto y coma
            let valor = input.value.replace(/[^\d.,]/g, '');
            
            // Convertir cualquier formato a un número
            valor = valor.replace(/\./g, '').replace(',', '.');
            let numero = parseFloat(valor);
            
            if (isNaN(numero)) {
                numero = 0;
            }
            
            // Formatear con 2 decimales, coma como separador decimal y punto para miles
            input.value = numero.toLocaleString('es-AR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            // Almacenar el valor numérico en un atributo para usarlo en el submit
            input.setAttribute('data-valor', numero);
        }
        
        // Preparar formularios antes de enviar
        document.addEventListener('DOMContentLoaded', function() {
            // Formulario de agregar
            const formAgregar = document.querySelector('#modalAgregar form');
            if (formAgregar) {
                formAgregar.addEventListener('submit', function(e) {
                    prepararFormulario(this);
                });
            }
            
            // Formulario de editar
            const formEditar = document.querySelector('#formEditar');
            if (formEditar) {
                formEditar.addEventListener('submit', function(e) {
                    prepararFormulario(this);
                });
            }
            
            // Inicializar campos de precio con formato
            document.querySelectorAll('input[name^="precio_"]').forEach(function(input) {
                formatearPrecio(input);
            });
        });
        
        // Preparar formulario antes del envío
        function prepararFormulario(form) {
            form.querySelectorAll('input[name^="precio_"]').forEach(function(input) {
                // Obtener el valor numérico almacenado o convertir el valor actual
                let valor = input.getAttribute('data-valor');
                if (!valor) {
                    // Si no hay data-valor, intentar convertir
                    valor = input.value.replace(/\./g, '').replace(',', '.');
                    valor = parseFloat(valor) || 0;
                }
                // Establecer el valor numérico para el envío
                input.value = valor;
            });
        }

        // Cerrar modales con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });

        // Funciones de ordenamiento AJAX
        function moverOpcion(id, direccion) {
            const action = direccion === 'up' ? 'move_opcion_up' : 'move_opcion_down';
            
            fetch('ajax_ordenamiento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${action}&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recargar la página para mostrar los cambios
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }

        function moverCategoria(id, direccion) {
            const action = direccion === 'up' ? 'move_categoria_up' : 'move_categoria_down';
            
            fetch('ajax_ordenamiento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${action}&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recargar la página para mostrar los cambios
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }

        // Nuevas funciones para mover a posición específica
        function moverCategoriaAPosicion(id, posicion) {
            posicion = parseInt(posicion);
            if (isNaN(posicion) || posicion < 1) {
                alert('Por favor ingresa una posición válida (número mayor a 0)');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'move_categoria_to_position');
            formData.append('id', id);
            formData.append('posicion', posicion);

            fetch('gestionar_datos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    // Si hay redirección, recargar la página
                    location.reload();
                } else {
                    return response.text();
                }
            })
            .then(data => {
                if (data) {
                    console.log('Respuesta:', data);
                }
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
                location.reload();
            });
        }

        function moverOpcionAPosicion(id, posicion, categoriaId) {
            posicion = parseInt(posicion);
            if (isNaN(posicion) || posicion < 1) {
                alert('Por favor ingresa una posición válida (número mayor a 0)');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'move_opcion_to_position');
            formData.append('id', id);
            formData.append('posicion', posicion);
            formData.append('categoria_id', categoriaId);

            fetch('gestionar_datos.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.redirected) {
                    // Si hay redirección, recargar la página
                    location.reload();
                } else {
                    return response.text();
                }
            })
            .then(data => {
                if (data) {
                    console.log('Respuesta:', data);
                }
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
                location.reload();
            });
        }
    </script>
</body>
</html> 
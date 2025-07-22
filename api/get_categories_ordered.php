<?php
/**
 * API para obtener categorías y opciones ordenadas
 * Usado por el cotizador ordenado
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Archivo de configuración no encontrado en ninguna ubicación'
    ]);
    exit;
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Archivo de base de datos no encontrado en ninguna ubicación'
    ]);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    // Obtener categorías ordenadas por campo orden
    $categorias = [];
    $query = "SELECT * FROM categorias ORDER BY orden ASC, nombre ASC";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categorias[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'orden' => (int)($row['orden'] ?? 0)
            ];
        }
    } else {
        throw new Exception('Error al obtener categorías: ' . $conn->error);
    }
    
    // Obtener todas las opciones y sus precios asociados
    $opciones = [];
    $query = "
        SELECT 
            o.id, o.categoria_id, o.nombre, o.descuento, o.orden,
            c.nombre as categoria_nombre, c.orden as categoria_orden,
            p.id as plazo_id, p.dias as plazo_dias, op.precio
        FROM opciones o
        LEFT JOIN categorias c ON o.categoria_id = c.id
        LEFT JOIN opciones_precios op ON o.id = op.opcion_id
        LEFT JOIN plazos_entrega p ON op.plazo_id = p.id AND p.activo = 1
        ORDER BY c.orden ASC, o.orden ASC, o.nombre ASC, p.orden ASC
    ";
    
    $result = $conn->query($query);
    
    if ($result) {
        $temp_opciones = [];
        while ($row = $result->fetch_assoc()) {
            $opcion_id = (int)$row['id'];
            if (!isset($temp_opciones[$opcion_id])) {
                $temp_opciones[$opcion_id] = [
                    'id' => $opcion_id,
                    'categoria_id' => (int)$row['categoria_id'],
                    'categoria_nombre' => $row['categoria_nombre'],
                    'categoria_orden' => (int)($row['categoria_orden'] ?? 0),
                    'nombre' => $row['nombre'],
                    'descuento' => (int)($row['descuento'] ?? 0),
                    'orden' => (int)($row['orden'] ?? 0),
                    'precios' => [] // Inicializar array de precios
                ];
            }
            // Añadir precio si existe para un plazo activo
            if ($row['plazo_id'] && $row['precio'] !== null) {
                $temp_opciones[$opcion_id]['precios'][$row['plazo_dias']] = (float)$row['precio'];
            }
        }
        
        // Convertir el array asociativo a uno indexado
        $opciones = array_values($temp_opciones);
        
        // Log de depuración
        error_log("API: Total opciones devueltas: " . count($opciones));
        
    } else {
        throw new Exception('Error al obtener opciones: ' . $conn->error);
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'categorias' => $categorias,
        'opciones' => $opciones,
        'total_categorias' => count($categorias),
        'total_opciones' => count($opciones),
        'ordenado' => true
    ]);
    
} catch (Exception $e) {
    error_log('Error en get_categories_ordered.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'debug' => $e->getMessage()
    ]);
}
?> 
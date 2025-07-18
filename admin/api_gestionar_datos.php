<?php
session_start();

// Log de depuración inicial
error_log("=== API GESTIONAR DATOS DEBUG ===");
error_log("Action: " . ($_GET['action'] ?? 'No action'));
error_log("ID: " . ($_GET['id'] ?? 'No ID'));
error_log("__DIR__: " . __DIR__);

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    error_log("API: Usuario no autorizado");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Detectar si estamos en Railway
$isRailway = isset($_ENV['RAILWAY_ENVIRONMENT']) || getenv('RAILWAY_ENVIRONMENT') !== false;
error_log("Entorno detectado: " . ($isRailway ? 'Railway' : 'Local'));

// Cargar configuración - rutas específicas por entorno
if ($isRailway) {
    // En Railway, usar la configuración desde la raíz
    $configPaths = [
        '/app/config.php',
        dirname(__DIR__) . '/config.php',
        __DIR__ . '/../config.php',
    ];
    $dbPaths = [
        '/app/includes/db.php',
        dirname(__DIR__) . '/includes/db.php',
        __DIR__ . '/../includes/db.php',
    ];
} else {
    // En local, buscar en las ubicaciones habituales
    $configPaths = [
        __DIR__ . '/../config.php',
        __DIR__ . '/../sistema/config.php',
    ];
    $dbPaths = [
        __DIR__ . '/../sistema/includes/db.php',
        __DIR__ . '/../includes/db.php',
    ];
}

// Intentar cargar configuración
$configLoaded = false;
foreach ($configPaths as $configPath) {
    error_log("Intentando configuración: " . $configPath);
    if (file_exists($configPath)) {
        require_once $configPath;
        $configLoaded = true;
        error_log("✅ Configuración cargada desde: " . $configPath);
        break;
    } else {
        error_log("❌ No existe: " . $configPath);
    }
}

if (!$configLoaded) {
    error_log("ERROR: No se pudo cargar configuración");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Archivo de configuración no encontrado']);
    exit;
}

// Intentar cargar DB
$dbLoaded = false;
foreach ($dbPaths as $dbPath) {
    error_log("Intentando DB: " . $dbPath);
    if (file_exists($dbPath)) {
        require_once $dbPath;
        $dbLoaded = true;
        error_log("✅ DB cargada desde: " . $dbPath);
        break;
    } else {
        error_log("❌ No existe: " . $dbPath);
    }
}

if (!$dbLoaded) {
    error_log("ERROR: No se pudo cargar DB");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Archivo de base de datos no encontrado']);
    exit;
}

// Verificar si tenemos las funciones necesarias
if (!function_exists('getDBConnection') && !class_exists('Database')) {
    error_log("ERROR: No se encontraron funciones de conexión DB");
    
    // Intentar conexión directa usando constantes
    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                throw new Exception("Error de conexión: " . $conn->connect_error);
            }
            error_log("✅ Conexión directa MySQLi exitosa");
        } catch (Exception $e) {
            error_log("ERROR conexión directa: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
            exit;
        }
    } else {
        error_log("ERROR: Constantes de DB no definidas");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Constantes de base de datos no definidas']);
        exit;
    }
} else {
    // Usar el método normal
    try {
        if (class_exists('Database')) {
            $db = Database::getInstance();
            $conn = $db->getConnection();
        } else {
            $conn = getDBConnection();
        }
        error_log("✅ Conexión normal exitosa");
    } catch (Exception $e) {
        error_log("ERROR conexión normal: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
}

// Bloquear acciones de modificación para usuario demo
if (!empty($_SESSION['is_demo'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'El usuario demo no puede modificar datos.']);
    exit;
}

// Configurar cabeceras para JSON
header('Content-Type: application/json');

// Procesar acciones
$action = $_GET['action'] ?? '';
$response = ['success' => false];

try {
    switch ($action) {
        case 'get_opcion':
            $id = $_GET['id'] ?? 0;
            
            if (!$id) {
                $response = ['success' => false, 'error' => 'ID no proporcionado'];
                break;
            }
            
            $stmt = $conn->prepare("SELECT * FROM opciones WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($opcion = $result->fetch_assoc()) {
                $response = [
                    'success' => true,
                    'opcion' => $opcion
                ];
                error_log("✅ Opción encontrada ID: " . $id);
            } else {
                $response = ['success' => false, 'error' => 'Opción no encontrada'];
                error_log("❌ Opción no encontrada ID: " . $id);
            }
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Acción no válida: ' . $action];
            error_log("❌ Acción no válida: " . $action);
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ];
    error_log("ERROR en switch: " . $e->getMessage());
}

// Log de respuesta
error_log("Respuesta final: " . json_encode($response));

// Devolver respuesta
echo json_encode($response); 
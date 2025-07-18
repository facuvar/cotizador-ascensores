<?php
/**
 * Configuración Universal - Railway y Local
 * (Forzando deploy para actualizar variables)
 * 
 * Este archivo maneja la configuración de la base de datos y otras
 * variables de entorno, adaptándose automáticamente si se ejecuta
 * en Railway o en un entorno local.
 */

// --- 1. CONFIGURACIÓN DE LA BASE DE DATOS ---

// Logging inicial para diagnóstico
error_log("=== INICIO DE CONFIGURACIÓN ===");
error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'No definido'));
error_log("SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'No definido'));
error_log("DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'No definido'));

// Detectar Railway de manera más robusta
$isRailway = (getenv('RAILWAY_ENVIRONMENT') === 'production' || 
             getenv('RAILWAY_ENVIRONMENT') === 'true' || 
             isset($_ENV['RAILWAY_ENVIRONMENT']) ||
             strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false);

// Log detallado del entorno
error_log("=== VARIABLES DE ENTORNO ===");
error_log("RAILWAY_ENVIRONMENT: " . (getenv('RAILWAY_ENVIRONMENT') ?: 'No definido'));
error_log("HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'No definido'));
error_log("Detección final - isRailway: " . ($isRailway ? 'true' : 'false'));

// Definir constantes de entorno
define('ENVIRONMENT', $isRailway ? 'railway' : 'local');
error_log("Entorno definido como: " . ENVIRONMENT);

// Definir rutas base (solo si no están definidas)
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', $isRailway ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : __DIR__);
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', PROJECT_ROOT);
}
if (!defined('SITE_URL')) {
    define('SITE_URL', $isRailway ? 'https://' . $_SERVER['HTTP_HOST'] : 'http://localhost');
}

// Log de rutas
error_log("=== RUTAS DEL SISTEMA ===");
error_log("PROJECT_ROOT: " . PROJECT_ROOT);
error_log("BASE_PATH: " . BASE_PATH);
error_log("SITE_URL: " . SITE_URL);

// Configuración de la base de datos según el entorno
if ($isRailway) {
    // Entorno de producción en Railway
    $db_host = getenv('MYSQLHOST');
    $db_user = getenv('MYSQLUSER');
    $db_pass = getenv('MYSQLPASSWORD');
    $db_name = getenv('MYSQLDATABASE');
    $db_port = getenv('MYSQLPORT');

    // Validar variables de entorno
    if (empty($db_host) || empty($db_user) || empty($db_pass) || empty($db_name) || empty($db_port)) {
        error_log("ERROR: Faltan variables de entorno de la base de datos en Railway");
        die('Error Crítico de Configuración: Faltan variables de entorno de la base de datos en Railway');
    }
} else {
    // Entorno de desarrollo local
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'cotizador_ascensores';
    $db_port = 3306;
}

// Definir constantes de base de datos
define('DB_HOST', 'mysql.railway.internal');
define('DB_USER', 'root');
define('DB_PASS', 'bnTRdfPtPcxXnGEawcKoPxfzQSkIClhs');
define('DB_NAME', 'railway');
define('DB_PORT', 3306);

// ========================================
// FUNCIÓN DE CONEXIÓN PDO
// ========================================

function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Log de conexión exitosa (solo en debug)
        if (DEBUG_MODE) {
            error_log("Conexión exitosa a " . ENVIRONMENT . " - Host: " . DB_HOST . " - DB: " . DB_NAME);
        }
        
        return $pdo;
        
    } catch (PDOException $e) {
        // Log del error
        error_log("Error de conexión DB (" . ENVIRONMENT . "): " . $e->getMessage());
        
        if (DEBUG_MODE) {
            die("Error de conexión: " . $e->getMessage());
        } else {
            die("Error de conexión a la base de datos. Por favor, inténtelo más tarde.");
        }
    }
}

// ========================================
// FUNCIÓN DE CONEXIÓN MYSQLI (ALTERNATIVA)
// ========================================

function getMySQLiConnection() {
    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        if ($mysqli->connect_error) {
            throw new Exception("Error de conexión: " . $mysqli->connect_error);
        }
        
        $mysqli->set_charset("utf8mb4");
        
        return $mysqli;
        
    } catch (Exception $e) {
        error_log("Error de conexión MySQLi (" . ENVIRONMENT . "): " . $e->getMessage());
        
        if (DEBUG_MODE) {
            die("Error de conexión MySQLi: " . $e->getMessage());
        } else {
            die("Error de conexión a la base de datos.");
        }
    }
}

// ========================================
// FUNCIONES DE UTILIDAD
// ========================================

/**
 * Obtener información del entorno actual
 */
function getEnvironmentInfo() {
    return [
        'environment' => ENVIRONMENT,
        'host' => DB_HOST,
        'database' => DB_NAME,
        'port' => DB_PORT,
        'debug' => DEBUG_MODE,
        'base_url' => BASE_URL,
        'is_railway' => ENVIRONMENT === 'railway'
    ];
}

/**
 * Verificar si la conexión está funcionando
 */
function testConnection() {
    try {
        $pdo = getDBConnection();
        $result = $pdo->query("SELECT 1 as test")->fetch();
        return $result['test'] === 1;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtener estadísticas de la base de datos
 */
function getDatabaseStats() {
    try {
        $pdo = getDBConnection();
        
        $stats = [];
        
        // Contar categorías
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM categorias");
        $stats['categorias'] = $stmt->fetch()['count'];
        
        // Contar opciones
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM opciones");
        $stats['opciones'] = $stmt->fetch()['count'];
        
        // Contar presupuestos
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM presupuestos");
        $stats['presupuestos'] = $stmt->fetch()['count'];
        
        return $stats;
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

// ========================================
// CONFIGURACIÓN ESPECÍFICA POR ENTORNO
// ========================================

if (ENVIRONMENT === 'railway') {
    // Configuración específica de Railway
    define('UPLOAD_MAX_SIZE', '10M');
    define('SESSION_LIFETIME', 3600); // 1 hora
    
} else {
    // Configuración específica de Local
    define('UPLOAD_MAX_SIZE', '50M');
    define('SESSION_LIFETIME', 7200); // 2 horas
}

// ========================================
// CONSTANTES ADICIONALES
// ========================================

define('APP_NAME', 'Cotizador Company');
define('APP_VERSION', '2.0.0');
define('CURRENCY', 'ARS');

// Rutas de archivos
define('UPLOAD_DIR', PROJECT_ROOT . '/uploads/');
define('PDF_DIR', PROJECT_ROOT . '/presupuestos/');
define('LOG_DIR', PROJECT_ROOT . '/logs/');

// Log de rutas completas
error_log("=== Rutas de Directorios ===");
error_log("UPLOAD_DIR: " . UPLOAD_DIR);
error_log("PDF_DIR: " . PDF_DIR);
error_log("LOG_DIR: " . LOG_DIR);

// Crear directorios si no existen
$dirs = [UPLOAD_DIR, PDF_DIR, LOG_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// --- 2. CONFIGURACIÓN DE LA APLICACIÓN ---

// URL base de la aplicación (útil para generar enlaces absolutos)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
define('BASE_URL', $protocol . $host);
error_log("BASE_URL definida como: " . BASE_URL);

// Configuración de errores y depuración
define('DEBUG_MODE', !$isRailway);
if (DEBUG_MODE) {
    error_log("Modo DEBUG activado");
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_log("Modo DEBUG desactivado");
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Configuración de la zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de sesión (solo si no hay sesión activa)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if ($isRailway) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Configuración de directorios
$directories = [
    'uploads' => PROJECT_ROOT . '/uploads',
    'presupuestos' => PROJECT_ROOT . '/presupuestos',
    'logs' => PROJECT_ROOT . '/logs',
    'temp' => PROJECT_ROOT . '/temp',
    'includes' => PROJECT_ROOT . '/includes',
    'assets' => PROJECT_ROOT . '/assets'
];

// Crear y verificar directorios necesarios
foreach ($directories as $name => $path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true)) {
            error_log("ERROR: No se pudo crear el directorio: " . $path);
        } else {
            error_log("Directorio creado: " . $path);
        }
    }
    $const_name = strtoupper($name) . '_DIR';
    if (!defined($const_name)) {
        define($const_name, $path);
    }
}

// Verificar permisos de directorios críticos
foreach ($directories as $name => $path) {
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        error_log("Permisos de $name: $perms");
        
        // Intentar establecer permisos si es necesario
        if (!is_writable($path)) {
            @chmod($path, 0755);
            error_log("Intentando establecer permisos 0755 en: " . $path);
        }
    }
}

// Buffer de salida
ob_start();

?> 
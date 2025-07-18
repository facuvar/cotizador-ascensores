<?php
// Permitir acceso tanto en Railway como en localhost
if (!getenv('RAILWAY_ENVIRONMENT') && $_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_ADDR'] !== '127.0.0.1') {
    die("Este script solo puede ejecutarse en el entorno de Railway o en localhost");
}

// Asegurarse de que no haya salida antes de los headers
ob_start();

// Configuración de sesión (debe ir antes de iniciar la sesión)
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (getenv('RAILWAY_ENVIRONMENT')) {
    ini_set('session.cookie_secure', 1);
}

// Definir la ruta base del proyecto
if (getenv('RAILWAY_ENVIRONMENT')) {
    define('ADMIN_BASE_PATH', '/app');
    define('INCLUDES_PATH', '/app/sistema/includes');
    define('ASSETS_PATH', '/assets');
    define('ADMIN_URL', '/admin');
    define('SITE_ROOT', '/app');
} else {
    $projectRoot = str_replace('\\', '/', dirname(dirname(__FILE__)));
    define('ADMIN_BASE_PATH', $projectRoot);
    define('INCLUDES_PATH', $projectRoot . '/sistema/includes');
    define('ASSETS_PATH', '/assets');
    define('ADMIN_URL', '/admin');
    define('SITE_ROOT', $projectRoot);
}

// Log de diagnóstico inicial
error_log("=== DIAGNÓSTICO DE RUTAS ADMIN (PRE-CONFIG) ===");
error_log("ADMIN_BASE_PATH: " . ADMIN_BASE_PATH);
error_log("INCLUDES_PATH: " . INCLUDES_PATH);
error_log("ASSETS_PATH: " . ASSETS_PATH);
error_log("ADMIN_URL: " . ADMIN_URL);
error_log("SITE_ROOT: " . SITE_ROOT);
error_log("__FILE__: " . __FILE__);
error_log("__DIR__: " . __DIR__);
error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'No definido'));

// Incluir el archivo de configuración principal
$configFile = dirname(dirname(__FILE__)) . '/config.php';
if (!file_exists($configFile)) {
    error_log("Error: No se encuentra el archivo de configuración principal en: " . $configFile);
    die("Error: Archivo de configuración no encontrado");
}
require_once $configFile;

// Verificar y cargar archivos necesarios
$requiredFiles = [
    'db' => INCLUDES_PATH . '/db.php',
    'functions' => INCLUDES_PATH . '/functions.php'
];

foreach ($requiredFiles as $name => $path) {
    if (!file_exists($path)) {
        error_log("Error: No se encuentra el archivo {$name} en: {$path}");
        die("Error: Archivo {$name} no encontrado");
    }
    require_once $path;
}

// Credenciales de administrador
if (!defined('ADMIN_USER')) define('ADMIN_USER', 'admin');
if (!defined('ADMIN_PASS')) define('ADMIN_PASS', password_hash('admin123', PASSWORD_DEFAULT));

// Credenciales de usuario demo
if (!defined('DEMO_USER')) define('DEMO_USER', 'demo');
if (!defined('DEMO_PASS')) define('DEMO_PASS', '$2y$10$rU/bkB5/GlpwPLDO.1WG.ecco44nlZ0P6S1QlfmeIZggmxr74AefW');

// Configuración de zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Configuración de caracteres
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', ADMIN_BASE_PATH . '/logs/error.log');

// Asegurar que existe el directorio de logs
$logDir = ADMIN_BASE_PATH . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Función para obtener la URL base del sitio
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . $host;
}

// Función para construir URLs de assets
function asset($path) {
    $baseUrl = getBaseUrl();
    return $baseUrl . ASSETS_PATH . '/' . ltrim($path, '/');
}

// Log de diagnóstico final
error_log("=== DIAGNÓSTICO DE RUTAS ADMIN (POST-CONFIG) ===");
error_log("ADMIN_BASE_PATH: " . ADMIN_BASE_PATH);
error_log("INCLUDES_PATH: " . INCLUDES_PATH);
error_log("ASSETS_PATH: " . ASSETS_PATH);
error_log("Base URL: " . getBaseUrl());
error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'No definido'));
error_log("DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'No definido'));
?> 
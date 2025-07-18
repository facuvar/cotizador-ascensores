<?php
// Detectar entorno automáticamente
$isRailway = isset($_ENV['RAILWAY_ENVIRONMENT']) || 
             isset($_ENV['PORT']) || 
             strpos($_SERVER['HTTP_HOST'] ?? '', 'railway.app') !== false ||
             strpos($_SERVER['HTTP_HOST'] ?? '', 'up.railway.app') !== false;

define('IS_RAILWAY', $isRailway);

if ($isRailway) {
    // 🚂 Configuración Railway (automática)
    define('DB_HOST', 'mysql.railway.internal');
    define('DB_USER', 'root');
    define('DB_PASS', 'bnTRdfPtPcxXnGEawcKoPxfzQSkIClhs');
    define('DB_NAME', 'railway');
    define('DB_PORT', 3306);
    define('SITE_URL', 'https://cotizador-ascensores-production.up.railway.app/sistema');
} else {
    // 🏠 Configuración Local (XAMPP)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'company_presupuestos');
    define('DB_PORT', 3306);
    define('SITE_URL', 'http://localhost/company-presupuestos-online-2/sistema');
}

// Configuración de directorios (adaptable)
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('XLS_DIR', __DIR__ . '/uploads/xls');

// Color principal de la empresa
define('MAIN_COLOR', '#e50009');

// Credenciales del administrador
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', '$2y$10$szOr0zBbR/0iUpJbHGzVgOyMS3vr7/3DbqFnOJTJRKZOwjyWO/vjm'); // admin123

// Configuración de email (opcional)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('FROM_EMAIL', '');
define('FROM_NAME', 'Sistema de Presupuestos');

// Configuración de Google Sheets (opcional)
define('GOOGLE_SHEETS_API_KEY', '');
define('GOOGLE_SHEETS_ID', '');

// Función de logging para Railway
if (!function_exists('railway_log')) {
    function railway_log($message) {
        if (IS_RAILWAY) {
            error_log("[RAILWAY] " . $message);
        }
    }
}

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?> 
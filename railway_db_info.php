<?php
/**
 * 🔍 OBTENER CREDENCIALES PÚBLICAS DE RAILWAY
 * Este script debe ejecutarse EN Railway para obtener las credenciales públicas
 */

// Cargar configuración
require_once 'config.php';

$isRailway = isset($_ENV['RAILWAY_ENVIRONMENT']) || isset($_ENV['PORT']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 Credenciales DB Railway</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .credential { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; font-family: monospace; margin: 10px 0; word-break: break-all; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .copy-btn { background: #28a745; border: none; color: white; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-left: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Información de Base de Datos Railway</h1>
        
        <?php if (!$isRailway): ?>
            
            <div class="warning">
                <h3>⚠️ Ejecutar en Railway</h3>
                <p>Este script debe ejecutarse <strong>desde Railway</strong> para obtener las credenciales públicas.</p>
                <p>Accede a: <code>https://cotizador-elevadores.up.railway.app/railway_db_info.php</code></p>
            </div>
            
        <?php else: ?>
            
            <div class="success">
                <h3>✅ Ejecutándose en Railway</h3>
                <p>Obteniendo credenciales de base de datos...</p>
            </div>
            
            <h2>🔧 Variables de Entorno Railway</h2>
            
            <?php
            // Obtener todas las variables de entorno relacionadas con MySQL
            $mysql_vars = [];
            foreach ($_ENV as $key => $value) {
                if (strpos($key, 'MYSQL') !== false || strpos($key, 'DATABASE') !== false) {
                    $mysql_vars[$key] = $value;
                }
            }
            
            if (!empty($mysql_vars)): ?>
                <div class="info">
                    <h4>📋 Variables MySQL encontradas:</h4>
                    <?php foreach ($mysql_vars as $key => $value): ?>
                        <div class="credential">
                            <strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($value) ?>
                            <button class="copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($value) ?>')">📋</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="warning">
                    <p>No se encontraron variables MySQL específicas. Usando configuración por defecto:</p>
                </div>
            <?php endif; ?>
            
            <h2>🎯 Credenciales Actuales (configuradas en config.php)</h2>
            <div class="credential">
                <strong>Host:</strong> <?= DB_HOST ?>
                <button class="copy-btn" onclick="copyToClipboard('<?= DB_HOST ?>')">📋</button>
            </div>
            <div class="credential">
                <strong>Usuario:</strong> <?= DB_USER ?>
                <button class="copy-btn" onclick="copyToClipboard('<?= DB_USER ?>')">📋</button>
            </div>
            <div class="credential">
                <strong>Contraseña:</strong> <?= DB_PASS ?>
                <button class="copy-btn" onclick="copyToClipboard('<?= DB_PASS ?>')">📋</button>
            </div>
            <div class="credential">
                <strong>Base de datos:</strong> <?= DB_NAME ?>
                <button class="copy-btn" onclick="copyToClipboard('<?= DB_NAME ?>')">📋</button>
            </div>
            <div class="credential">
                <strong>Puerto:</strong> <?= DB_PORT ?>
                <button class="copy-btn" onclick="copyToClipboard('<?= DB_PORT ?>')">📋</button>
            </div>
            
            <h2>🌐 Obtener Credenciales Públicas</h2>
            
            <?php
            // Intentar obtener credenciales públicas
            try {
                $pdo = getDBConnection();
                
                // Obtener host real desde la conexión
                $stmt = $pdo->query("SELECT @@hostname as hostname, @@port as port");
                $server_info = $stmt->fetch();
                
                echo '<div class="success">';
                echo '<h4>✅ Conexión exitosa</h4>';
                echo '<p><strong>Servidor MySQL:</strong> ' . htmlspecialchars($server_info['hostname'] ?? 'N/A') . '</p>';
                echo '<p><strong>Puerto:</strong> ' . htmlspecialchars($server_info['port'] ?? 'N/A') . '</p>';
                echo '</div>';
                
                // Verificar si hay credenciales públicas en variables de entorno
                $public_vars = [];
                foreach ($_ENV as $key => $value) {
                    if (strpos($key, 'PUBLIC') !== false || 
                        strpos($key, 'EXTERNAL') !== false ||
                        strpos($key, 'HOST') !== false ||
                        strpos($key, 'URL') !== false) {
                        $public_vars[$key] = $value;
                    }
                }
                
                if (!empty($public_vars)) {
                    echo '<h3>🌐 Variables públicas encontradas:</h3>';
                    foreach ($public_vars as $key => $value) {
                        echo '<div class="credential">';
                        echo '<strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value);
                        echo '<button class="copy-btn" onclick="copyToClipboard(\'' . htmlspecialchars($value) . '\')">📋</button>';
                        echo '</div>';
                    }
                }
                
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h4>❌ Error de conexión</h4>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            ?>
            
            <h2>📋 Para usar desde local (XAMPP)</h2>
            <div class="info">
                <p>Para conectarte desde tu XAMPP local, Railway debería proporcionar credenciales públicas.</p>
                <p>Busca en tu dashboard de Railway:</p>
                <ul>
                    <li>Variables de entorno que contengan <code>PUBLIC</code> o <code>EXTERNAL</code></li>
                    <li>Una URL de conexión externa</li>
                    <li>Host público (no .internal)</li>
                </ul>
            </div>
            
            <h2>🔧 Siguiente paso</h2>
            <div class="warning">
                <p><strong>Opción 1:</strong> Buscar credenciales públicas en Railway Dashboard</p>
                <p><strong>Opción 2:</strong> Usar el método de exportación SQL desde Railway</p>
                <p><strong>Opción 3:</strong> Configurar la migración para ejecutarse desde Railway</p>
            </div>
            
        <?php endif; ?>
        
        <p>
            <a href="cotizador.php" class="btn">🎯 Cotizador</a>
            <a href="export_database.php" class="btn">🔄 Scripts de Migración</a>
        </p>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Copiado al portapapeles: ' + text);
            }).catch(function(err) {
                console.error('Error al copiar: ', err);
            });
        }
    </script>
</body>
</html> 
<?php
// Muestra todos los errores para facilitar la depuración.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "-- Script de exportación de precios generado el " . date('Y-m-d H:i:s') . "\n";
echo "-- Por favor, ejecute este script en la base de datos de producción (Railway).\n\n";

// Cargar la configuración de la base de datos local
$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    die("ERROR: No se encontró el archivo de configuración 'config.php'. Asegúrese de que este script está en la carpeta 'admin'.\n");
}
require_once $configPath;

// Cargar el gestor de la base de datos
$dbPath = __DIR__ . '/../includes/db.php';
if (!file_exists($dbPath)) {
    die("ERROR: No se encontró el archivo 'includes/db.php'.\n");
}
require_once $dbPath;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    echo "-- Conexión a la base de datos local exitosa.\n";

    // 1. Verificar si la tabla 'opciones_precios' existe y tiene datos
    $checkResult = $conn->query("SELECT COUNT(*) as total FROM opciones_precios");
    if (!$checkResult) {
        die("-- ERROR: La tabla 'opciones_precios' no parece existir en su base de datos local.\n");
    }
    $count = $checkResult->fetch_assoc()['total'];
    if ($count == 0) {
        die("-- AVISO: La tabla 'opciones_precios' en su base de datos local está vacía. No hay precios para exportar.\n");
    }

    echo "-- Se encontraron {$count} registros de precios para exportar.\n\n";
    
    // 2. Limpiar la tabla en producción antes de insertar para evitar conflictos.
    echo "-- Limpiando la tabla 'opciones_precios' en el destino...\n";
    echo "TRUNCATE TABLE `opciones_precios`;\n\n";
    
    // 3. Obtener todos los datos de la tabla de precios local
    $result = $conn->query("SELECT opcion_id, plazo_id, precio FROM opciones_precios");
    if (!$result) {
        die("-- ERROR: No se pudieron obtener los datos de la tabla 'opciones_precios'.\n");
    }

    // 4. Generar los comandos INSERT
    echo "-- Insertando los nuevos registros de precios...\n";
    $batchSize = 100; // Insertar en lotes de 100
    $rowCount = 0;
    $totalRows = $result->num_rows;

    $values = [];
    while ($row = $result->fetch_assoc()) {
        $opcion_id = (int)$row['opcion_id'];
        $plazo_id = (int)$row['plazo_id'];
        $precio = (float)$row['precio'];
        $values[] = "({$opcion_id}, {$plazo_id}, {$precio})";
        $rowCount++;

        if (count($values) >= $batchSize || $rowCount === $totalRows) {
            echo "INSERT INTO `opciones_precios` (opcion_id, plazo_id, precio) VALUES\n";
            echo implode(",\n", $values);
            echo ";\n\n";
            $values = []; // Resetear el lote
        }
    }

    echo "-- Proceso de exportación completado.\n";

} catch (Exception $e) {
    die("-- ERROR CRÍTICO: " . $e->getMessage() . "\n");
} 
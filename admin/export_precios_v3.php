<?php
// Muestra todos los errores para facilitar la depuración.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "-- Script de exportación de precios v3 (Súper Robusto) - Generado el " . date('Y-m-d H:i:s') . "\n";
echo "-- Este script utiliza los DÍAS de los plazos en lugar de los nombres o IDs para máxima compatibilidad.\n";
echo "-- Por favor, ejecute este script en la base de datos de producción (Railway).\n\n";

// Cargar la configuración de la base de datos local
$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    die("ERROR: No se encontró el archivo de configuración 'config.php'.\n");
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

    // 1. Obtener todos los precios junto con los DÍAS del plazo
    $query = "
        SELECT op.opcion_id, p.dias as plazo_dias, op.precio
        FROM opciones_precios op
        JOIN plazos_entrega p ON op.plazo_id = p.id
    ";
    $result = $conn->query($query);

    if (!$result || $result->num_rows === 0) {
        die("-- AVISO: No se encontraron precios en la base de datos local para exportar.\n");
    }

    $totalPrecios = $result->num_rows;
    echo "-- Se encontraron {$totalPrecios} registros de precios para exportar.\n\n";
    
    // 2. Limpiar la tabla en producción antes de insertar
    echo "-- Limpiando la tabla 'opciones_precios' en el destino...\n";
    echo "TRUNCATE TABLE `opciones_precios`;\n\n";
    
    // 3. Generar los comandos INSERT robustos
    echo "-- Insertando los nuevos registros de precios...\n";
    
    $precios = $result->fetch_all(MYSQLI_ASSOC);
    $batchSize = 100;
    $rowCount = 0;
    $totalRows = count($precios);
    $values = [];

    foreach ($precios as $precio_info) {
        $opcion_id = (int)$precio_info['opcion_id'];
        $plazo_dias = (int)$precio_info['plazo_dias'];
        $precio = (float)$precio_info['precio'];
        
        // La subconsulta busca el plazo_id en el servidor de destino usando el número de días.
        $values[] = "({$opcion_id}, (SELECT id FROM plazos_entrega WHERE dias = {$plazo_dias} LIMIT 1), {$precio})";
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
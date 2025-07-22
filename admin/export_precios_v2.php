<?php
// Muestra todos los errores para facilitar la depuración.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "-- Script de exportación de precios v2 (Robusto) - Generado el " . date('Y-m-d H:i:s') . "\n";
echo "-- Este script utiliza los NOMBRES de los plazos en lugar de los IDs para asegurar la compatibilidad.\n";
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

    // 1. Obtener todos los precios junto con el NOMBRE del plazo
    $query = "
        SELECT op.opcion_id, p.nombre as plazo_nombre, op.precio
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
    
    // Agrupar por nombre de plazo para generar menos consultas
    $precios_por_plazo = [];
    foreach ($precios as $precio_info) {
        $precios_por_plazo[$precio_info['plazo_nombre']][] = $precio_info;
    }

    foreach ($precios_por_plazo as $plazo_nombre => $datos_precios) {
        echo "-- Precios para el plazo: '{$plazo_nombre}'\n";
        
        $values = [];
        foreach ($datos_precios as $dato) {
            $opcion_id = (int)$dato['opcion_id'];
            $precio = (float)$dato['precio'];
            $values[] = "({$opcion_id}, @plazo_id_{$plazo_id}, {$precio})";
        }
        
        // Escapar el nombre del plazo para usarlo en la consulta SQL
        $plazo_nombre_escapado = $conn->real_escape_string($plazo_nombre);
        
        echo "SELECT id INTO @plazo_id FROM plazos_entrega WHERE nombre = '{$plazo_nombre_escapado}';\n";
        echo "INSERT IGNORE INTO `opciones_precios` (opcion_id, plazo_id, precio) VALUES\n";
        
        $insert_values = [];
        foreach ($datos_precios as $dato) {
            $opcion_id = (int)$dato['opcion_id'];
            $precio = (float)$dato['precio'];
            // Usamos una sub-selección para encontrar el ID del plazo en el servidor de destino
            $insert_values[] = "({$opcion_id}, (SELECT id FROM plazos_entrega WHERE nombre = '{$plazo_nombre_escapado}'), {$precio})";
        }
        
        echo implode(",\n", $insert_values);
        echo ";\n\n";
    }

    echo "-- Proceso de exportación completado.\n";

} catch (Exception $e) {
    die("-- ERROR CRÍTICO: " . $e->getMessage() . "\n");
} 
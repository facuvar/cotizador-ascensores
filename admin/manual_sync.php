<?php
// Muestra todos los errores para facilitar la depuración.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "-- ====================================================================\n";
echo "-- SCRIPT DE SINCRONIZACIÓN MANUAL - " . date('Y-m-d H:i:s') . "\n";
echo "-- ====================================================================\n";
echo "-- ATENCIÓN: Copie y ejecute CADA BLOQUE de código por separado.\n";
echo "-- ====================================================================\n\n";

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

    echo "-- Conexión a la base de datos local exitosa.\n\n";

    // --- BLOQUE 1: Preparación ---
    echo "-- PASO 1: Preparar la base de datos. Copie y ejecute este bloque completo.\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n";
    echo "DELETE FROM `opciones_precios`;\n";
    echo "DELETE FROM `opciones`;\n";
    echo "DELETE FROM `plazos_entrega`;\n";
    echo "DELETE FROM `categorias`;\n\n";

    // --- BLOQUE 2: Insertar Categorías ---
    $categoriasResult = $conn->query("SELECT id, nombre, orden FROM categorias");
    if ($categoriasResult && $categoriasResult->num_rows > 0) {
        echo "-- PASO 2: Insertar datos en 'categorias'. Copie y ejecute este bloque completo.\n";
        $categorias = $categoriasResult->fetch_all(MYSQLI_ASSOC);
        $values = [];
        foreach ($categorias as $item) {
            $values[] = sprintf("(%d, '%s', %d)", (int)$item['id'], $conn->real_escape_string($item['nombre']), (int)$item['orden']);
        }
        echo "INSERT INTO `categorias` (id, nombre, orden) VALUES\n" . implode(",\n", $values) . ";\n\n";
    }

    // --- BLOQUE 3: Insertar Plazos de Entrega ---
    $plazosResult = $conn->query("SELECT id, nombre, dias, activo, orden FROM plazos_entrega");
    if ($plazosResult && $plazosResult->num_rows > 0) {
        echo "-- PASO 3: Insertar datos en 'plazos_entrega'. Copie y ejecute este bloque completo.\n";
        $plazos = $plazosResult->fetch_all(MYSQLI_ASSOC);
        $values = [];
        foreach ($plazos as $item) {
            $values[] = sprintf("(%d, '%s', %d, %d, %d)", (int)$item['id'], $conn->real_escape_string($item['nombre']), (int)$item['dias'], (int)$item['activo'], (int)$item['orden']);
        }
        echo "INSERT INTO `plazos_entrega` (id, nombre, dias, activo, orden) VALUES\n" . implode(",\n", $values) . ";\n\n";
    }

    // --- BLOQUE 4: Insertar Opciones ---
    $opcionesResult = $conn->query("SELECT id, categoria_id, nombre, descuento, orden FROM opciones");
    if ($opcionesResult && $opcionesResult->num_rows > 0) {
        echo "-- PASO 4: Insertar datos en 'opciones'. Copie y ejecute este bloque completo.\n";
        $opciones = $opcionesResult->fetch_all(MYSQLI_ASSOC);
        $values = [];
        foreach ($opciones as $item) {
            $values[] = sprintf("(%d, %d, '%s', %f, %d)", (int)$item['id'], (int)$item['categoria_id'], $conn->real_escape_string($item['nombre']), (float)$item['descuento'], (int)$item['orden']);
        }
        echo "INSERT INTO `opciones` (id, categoria_id, nombre, descuento, orden) VALUES\n" . implode(",\n", $values) . ";\n\n";
    }

    // --- BLOQUE 5: Insertar Precios ---
    $preciosResult = $conn->query("SELECT opcion_id, plazo_id, precio FROM opciones_precios");
    if ($preciosResult && $preciosResult->num_rows > 0) {
        echo "-- PASO 5: Insertar datos en 'opciones_precios'. Este es el paso más importante.\n";
        $precios = $preciosResult->fetch_all(MYSQLI_ASSOC);
        $values = [];
        foreach ($precios as $item) {
            $values[] = sprintf("(%d, %d, %f)", (int)$item['opcion_id'], (int)$item['plazo_id'], (float)$item['precio']);
        }
        echo "INSERT INTO `opciones_precios` (opcion_id, plazo_id, precio) VALUES\n" . implode(",\n", $values) . ";\n\n";
    }

    // --- BLOQUE 6: Finalización ---
    echo "-- PASO 6: Reactivar la seguridad de la base de datos. Copie y ejecute este comando final.\n";
    echo "SET FOREIGN_KEY_CHECKS=1;\n\n";
    
    echo "-- Proceso de sincronización completado.\n";

} catch (Exception $e) {
    echo "SET FOREIGN_KEY_CHECKS=1;\n"; // Asegurarse de reactivar en caso de error
    die("-- ERROR CRÍTICO: " . $e->getMessage() . "\n");
} 
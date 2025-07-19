<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'cotizador_ascensores');
define('DB_USER', 'root');
define('DB_PASS', '');

require_once __DIR__ . '/../includes/db.php';

try {
    // Obtener todas las reglas de exclusión activas
    $sql = "SELECT 
                oe.id,
                oe.opcion_id,
                oe.opcion_excluida_id,
                oe.mensaje_error,
                oe.activo,
                o1.nombre as opcion_nombre,
                o2.nombre as opcion_excluida_nombre,
                c1.nombre as categoria_nombre,
                c2.nombre as categoria_excluida_nombre
            FROM opciones_excluyentes oe
            INNER JOIN opciones o1 ON oe.opcion_id = o1.id
            INNER JOIN opciones o2 ON oe.opcion_excluida_id = o2.id
            INNER JOIN categorias c1 ON o1.categoria_id = c1.id
            INNER JOIN categorias c2 ON o2.categoria_id = c2.id
            WHERE oe.activo = 1
            ORDER BY oe.opcion_id, oe.opcion_excluida_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar las reglas por opción para facilitar el uso en JavaScript
    $reglas_organizadas = [];
    foreach ($reglas as $regla) {
        $opcion_id = $regla['opcion_id'];
        if (!isset($reglas_organizadas[$opcion_id])) {
            $reglas_organizadas[$opcion_id] = [];
        }
        
        $reglas_organizadas[$opcion_id][] = [
            'id' => $regla['id'],
            'opcion_excluida_id' => $regla['opcion_excluida_id'],
            'mensaje_error' => $regla['mensaje_error'],
            'opcion_excluida_nombre' => $regla['opcion_excluida_nombre'],
            'categoria_excluida_nombre' => $regla['categoria_excluida_nombre']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'reglas' => $reglas_organizadas,
        'total_reglas' => count($reglas)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener reglas de exclusión: ' . $e->getMessage()
    ]);
}
?> 
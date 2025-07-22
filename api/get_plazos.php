<?php
header('Content-Type: application/json');

// Cargar configuración y DB de forma robusta
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT * FROM plazos_entrega WHERE activo = 1 ORDER BY orden ASC, dias ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $plazos = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'plazos' => $plazos]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al obtener los plazos de entrega: ' . $e->getMessage()]);
} 
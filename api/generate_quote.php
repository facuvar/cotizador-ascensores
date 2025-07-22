<?php
/**
 * API para generar presupuestos
 * Usado por el cotizador moderno
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

// Cargar configuración - buscar en múltiples ubicaciones
$configPaths = [
    __DIR__ . '/../config.php',           // Railway (raíz del proyecto)
    __DIR__ . '/../sistema/config.php',   // Local (dentro de sistema)
];

$configLoaded = false;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Archivo de configuración no encontrado en ninguna ubicación'
    ]);
    exit;
}

// Cargar DB - buscar en múltiples ubicaciones
$dbPaths = [
    __DIR__ . '/../sistema/includes/db.php',   // Local
    __DIR__ . '/../includes/db.php',           // Railway alternativo
];

$dbLoaded = false;
foreach ($dbPaths as $dbPath) {
    if (file_exists($dbPath)) {
        require_once $dbPath;
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Archivo de base de datos no encontrado en ninguna ubicación'
    ]);
    exit;
}

// Cargar el manejador de correos
$emailPaths = [
    __DIR__ . '/../includes/email_handler.php',   // Ubicación principal
    __DIR__ . '/../sistema/includes/email_handler.php',   // Ubicación alternativa
];

$emailLoaded = false;
foreach ($emailPaths as $emailPath) {
    if (file_exists($emailPath)) {
        require_once $emailPath;
        $emailLoaded = true;
        break;
    }
}

if (!$emailLoaded) {
    error_log('Advertencia: No se pudo cargar el manejador de correos');
}

try {
    // Validar datos requeridos
    $nombre = $_POST['nombre'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $empresa = $_POST['empresa'] ?? '';
    $ubicacion_obra = $_POST['ubicacion_obra'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';
    $opciones_json = $_POST['opciones'] ?? '';
    $plazo = $_POST['plazo'] ?? '90';
    
    if (empty($nombre) || empty($email)) {
        throw new Exception('Nombre y email son requeridos');
    }
    
    if (empty($ubicacion_obra)) {
        throw new Exception('La ubicación de la obra es requerida');
    }
    
    if (empty($opciones_json)) {
        throw new Exception('Debe seleccionar al menos una opción');
    }
    
    $opciones_ids = json_decode($opciones_json, true);
    if (!is_array($opciones_ids) || empty($opciones_ids)) {
        throw new Exception('Opciones inválidas');
    }
    
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    // Verificar que la tabla presupuestos existe
    $table_check = $conn->query("SHOW TABLES LIKE 'presupuestos'");
    
    if ($table_check->num_rows == 0) {
        // La tabla no existe, la creamos
        $create_table = "CREATE TABLE presupuestos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numero_presupuesto VARCHAR(20) NOT NULL,
            cliente_nombre VARCHAR(100) NOT NULL,
            cliente_email VARCHAR(100) NOT NULL,
            cliente_telefono VARCHAR(20),
            cliente_empresa VARCHAR(100),
            ubicacion_obra TEXT,
            observaciones TEXT,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            descuento_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0,
            descuento_monto DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            plazo_entrega VARCHAR(10),
            estado VARCHAR(20) DEFAULT 'pendiente',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($create_table)) {
            throw new Exception('Error al crear la tabla presupuestos: ' . $conn->error);
        }
    } else {
        // Verificamos si la columna plazo_entrega existe
        $column_check = $conn->query("SHOW COLUMNS FROM presupuestos LIKE 'plazo_entrega'");
        
        if ($column_check->num_rows == 0) {
            // La columna no existe, la creamos
            $alter_table = "ALTER TABLE presupuestos ADD COLUMN plazo_entrega VARCHAR(10) AFTER total";
            if (!$conn->query($alter_table)) {
                throw new Exception('Error al agregar la columna plazo_entrega: ' . $conn->error);
            }
        }
        
        // Verificamos si la columna ubicacion_obra existe
        $column_check = $conn->query("SHOW COLUMNS FROM presupuestos LIKE 'ubicacion_obra'");
        
        if ($column_check->num_rows == 0) {
            // La columna no existe, la creamos
            $alter_table = "ALTER TABLE presupuestos ADD COLUMN ubicacion_obra TEXT AFTER cliente_empresa";
            if (!$conn->query($alter_table)) {
                throw new Exception('Error al agregar la columna ubicacion_obra: ' . $conn->error);
            }
        }
        
        // Verificamos si la columna observaciones existe
        $column_check = $conn->query("SHOW COLUMNS FROM presupuestos LIKE 'observaciones'");
        
        if ($column_check->num_rows == 0) {
            // La columna no existe, la creamos
            $alter_table = "ALTER TABLE presupuestos ADD COLUMN observaciones TEXT AFTER ubicacion_obra";
            if (!$conn->query($alter_table)) {
                throw new Exception('Error al agregar la columna observaciones: ' . $conn->error);
            }
        }
    }
    
    // Obtener detalles de las opciones seleccionadas
    $placeholders = str_repeat('?,', count($opciones_ids) - 1) . '?';
    $query = "SELECT o.*, c.nombre as categoria_nombre 
              FROM opciones o 
              LEFT JOIN categorias c ON o.categoria_id = c.id 
              WHERE o.id IN ($placeholders)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }
    
    $types = str_repeat('i', count($opciones_ids));
    $stmt->bind_param($types, ...$opciones_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $opciones_detalle = [];
    while ($row = $result->fetch_assoc()) {
        $opciones_detalle[] = $row;
    }
    
    if (empty($opciones_detalle)) {
        throw new Exception('No se encontraron las opciones seleccionadas');
    }
    
    // Calcular totales de forma eficiente
    $subtotal = 0;
    $descuento_porcentaje = 0;
    $plazo_id = null;

    // Obtener el ID del plazo seleccionado
    $stmt_plazo = $conn->prepare("SELECT id FROM plazos_entrega WHERE dias = ? LIMIT 1");
    if (!$stmt_plazo) {
        throw new Exception('Error al preparar consulta de plazo: ' . $conn->error);
    }
    $stmt_plazo->bind_param('i', $plazo);
    $stmt_plazo->execute();
    $result_plazo = $stmt_plazo->get_result();
    if ($row = $result_plazo->fetch_assoc()) {
        $plazo_id = $row['id'];
    }
    if (!$plazo_id) {
        throw new Exception('El plazo de entrega seleccionado no es válido.');
    }

    // Obtener todos los precios necesarios con UNA SOLA CONSULTA
    $precios_map = [];
    $opciones_con_precio_ids = [];
    foreach ($opciones_detalle as $opcion) {
        // Excluir descuentos de la consulta de precios
        if ($opcion['categoria_id'] != 3) {
            $opciones_con_precio_ids[] = $opcion['id'];
        }
    }

    if (!empty($opciones_con_precio_ids)) {
        $placeholders = str_repeat('?,', count($opciones_con_precio_ids) - 1) . '?';
        $query_precios = "SELECT opcion_id, precio FROM opciones_precios WHERE plazo_id = ? AND opcion_id IN ($placeholders)";
        
        $stmt_precios = $conn->prepare($query_precios);
        $types = 'i' . str_repeat('i', count($opciones_con_precio_ids));
        $params = array_merge([$plazo_id], $opciones_con_precio_ids);
        $stmt_precios->bind_param($types, ...$params);
        $stmt_precios->execute();
        $result_precios = $stmt_precios->get_result();
        
        while ($row = $result_precios->fetch_assoc()) {
            $precios_map[$row['opcion_id']] = (float)$row['precio'];
        }
    }

    // Ahora, calcular totales usando los datos en memoria
    foreach ($opciones_detalle as $opcion) {
        if ($opcion['categoria_id'] == 3 && isset($opcion['descuento']) && $opcion['descuento'] > 0) {
            $descuento_porcentaje = max($descuento_porcentaje, $opcion['descuento']);
        } else {
            $precio = $precios_map[$opcion['id']] ?? 0;
            if (stripos($opcion['nombre'], 'restar') !== false) {
                $subtotal -= $precio;
            } else {
                $subtotal += $precio;
            }
        }
    }
    
    $descuento = $subtotal * ($descuento_porcentaje / 100);
    $total = $subtotal - $descuento;
    
    // Generar número de presupuesto único
    $numero_presupuesto = 'PRES-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Verificar que el número no exista
    $check_query = "SELECT id FROM presupuestos WHERE numero_presupuesto = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('s', $numero_presupuesto);
    $stmt->execute();
    
    // Si existe, generar otro
    while ($stmt->get_result()->num_rows > 0) {
        $numero_presupuesto = 'PRES-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt->bind_param('s', $numero_presupuesto);
        $stmt->execute();
    }
    
    // Guardar presupuesto en la base de datos usando los nombres correctos de columnas
    $insert_query = "INSERT INTO presupuestos (
        numero_presupuesto,
        cliente_nombre, 
        cliente_email, 
        cliente_telefono, 
        cliente_empresa,
        ubicacion_obra,
        observaciones,
        subtotal,
        descuento_porcentaje,
        descuento_monto,
        total,
        plazo_entrega,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($insert_query);
    if (!$stmt) {
        throw new Exception('Error preparando inserción: ' . $conn->error);
    }
    
    $stmt->bind_param(
        'sssssssdddds',
        $numero_presupuesto,
        $nombre,
        $email,
        $telefono,
        $empresa,
        $ubicacion_obra,
        $observaciones,
        $subtotal,
        $descuento_porcentaje,
        $descuento,
        $total,
        $plazo
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Error al guardar presupuesto: ' . $stmt->error);
    }
    
    $presupuesto_id = $conn->insert_id;
    
    // Guardar las opciones seleccionadas en la tabla de detalles
    // Primero verificamos si la tabla existe
    $table_check = $conn->query("SHOW TABLES LIKE 'presupuesto_detalles'");
    
    if ($table_check->num_rows == 0) {
        // La tabla no existe, la creamos
        $create_table = "CREATE TABLE presupuesto_detalles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            presupuesto_id INT NOT NULL,
            opcion_id INT NOT NULL,
            precio DECIMAL(10,2) NOT NULL DEFAULT 0,
            FOREIGN KEY (presupuesto_id) REFERENCES presupuestos(id) ON DELETE CASCADE,
            FOREIGN KEY (opcion_id) REFERENCES opciones(id)
        )";
        
        if (!$conn->query($create_table)) {
            throw new Exception('Error al crear la tabla presupuesto_detalles: ' . $conn->error);
        }
    }
    
    // Ahora insertamos cada opción seleccionada
    $insert_detail = "INSERT INTO presupuesto_detalles (presupuesto_id, opcion_id, precio) VALUES (?, ?, ?)";
    $stmt_detail = $conn->prepare($insert_detail);
    
    if (!$stmt_detail) {
        throw new Exception('Error preparando consulta de detalles: ' . $conn->error);
    }
    
    foreach ($opciones_detalle as $opcion) {
        // Para descuentos, el precio es 0. Para otros, lo buscamos en el mapa.
        $precio_guardar = $precios_map[$opcion['id']] ?? 0;
        
        $stmt_detail->bind_param('iid', $presupuesto_id, $opcion['id'], $precio_guardar);
        $stmt_detail->execute();
    }
    
    // Preparar datos para el correo de notificación
    $presupuesto_data = [
        'quote_id' => $presupuesto_id,
        'numero_presupuesto' => $numero_presupuesto,
        'totals' => [
            'subtotal' => $subtotal,
            'descuento_porcentaje' => $descuento_porcentaje,
            'descuento_monto' => $descuento,
            'total' => $total
        ],
        'customer' => [
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'empresa' => $empresa
        ],
        'ubicacion_obra' => $ubicacion_obra,
        'observaciones' => $observaciones,
        'plazo_entrega' => $plazo,
        'opciones_count' => count($opciones_detalle)
    ];
    
    // Enviar correo de notificación si el manejador está disponible
    $email_enviado = false;
    if ($emailLoaded && class_exists('EmailHandler')) {
        try {
            $emailHandler = new EmailHandler();
            $email_enviado = $emailHandler->enviarNotificacionPresupuesto($presupuesto_data);
            
            if ($email_enviado) {
                error_log("Correo de notificación enviado exitosamente para presupuesto: " . $numero_presupuesto);
            } else {
                error_log("Error al enviar correo de notificación para presupuesto: " . $numero_presupuesto);
            }
        } catch (Exception $e) {
            error_log("Error enviando correo para presupuesto {$numero_presupuesto}: " . $e->getMessage());
        }
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Presupuesto generado exitosamente',
        'quote_id' => $presupuesto_id,
        'numero_presupuesto' => $numero_presupuesto,
        'email_enviado' => $email_enviado,
        'totals' => [
            'subtotal' => $subtotal,
            'descuento_porcentaje' => $descuento_porcentaje,
            'descuento_monto' => $descuento,
            'total' => $total
        ],
        'customer' => [
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'empresa' => $empresa
        ],
        'opciones_count' => count($opciones_detalle)
    ]);
    
} catch (Exception $e) {
    // Registrar el error para depuración
    error_log('Error en generate_quote.php: ' . $e->getMessage() . ' en línea ' . $e->getLine());
    
    // Mostrar un mensaje de error más detallado
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString()
    ]);
}
?> 
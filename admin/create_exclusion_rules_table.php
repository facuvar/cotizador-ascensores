<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'cotizador_ascensores');
define('DB_USER', 'root');
define('DB_PASS', '');

require_once __DIR__ . '/../includes/db.php';

try {
    // Crear tabla de reglas de exclusión
    $sql = "CREATE TABLE IF NOT EXISTS opciones_excluyentes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        opcion_id INT NOT NULL,
        opcion_excluida_id INT NOT NULL,
        mensaje_error VARCHAR(255) DEFAULT 'Esta opción no es compatible con la selección anterior',
        activo BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (opcion_id) REFERENCES opciones(id) ON DELETE CASCADE,
        FOREIGN KEY (opcion_excluida_id) REFERENCES opciones(id) ON DELETE CASCADE,
        UNIQUE KEY unique_exclusion (opcion_id, opcion_excluida_id)
    )";
    
    $pdo->exec($sql);
    echo "✅ Tabla 'opciones_excluyentes' creada exitosamente\n";
    
    // Crear índices para mejor rendimiento
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_opcion_id ON opciones_excluyentes(opcion_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_opcion_excluida_id ON opciones_excluyentes(opcion_excluida_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_activo ON opciones_excluyentes(activo)");
    
    echo "✅ Índices creados exitosamente\n";
    
    // Insertar algunas reglas de ejemplo (basadas en las que ya tienes hardcodeadas)
    $reglas_ejemplo = [
        // Puertas de Ascensores Electromecánicos
        ['ascensores electromecanicos adicional puertas de 900', 'ascensores electromecanicos adicional puertas de 1000'],
        ['ascensores electromecanicos adicional puertas de 900', 'ascensores electromecanicos adicional puertas de 1300'],
        ['ascensores electromecanicos adicional puertas de 900', 'ascensores electromecanicos adicional puertas de 1800'],
        ['ascensores electromecanicos adicional puertas de 1000', 'ascensores electromecanicos adicional puertas de 900'],
        ['ascensores electromecanicos adicional puertas de 1000', 'ascensores electromecanicos adicional puertas de 1300'],
        ['ascensores electromecanicos adicional puertas de 1000', 'ascensores electromecanicos adicional puertas de 1800'],
        ['ascensores electromecanicos adicional puertas de 1300', 'ascensores electromecanicos adicional puertas de 900'],
        ['ascensores electromecanicos adicional puertas de 1300', 'ascensores electromecanicos adicional puertas de 1000'],
        ['ascensores electromecanicos adicional puertas de 1300', 'ascensores electromecanicos adicional puertas de 1800'],
        ['ascensores electromecanicos adicional puertas de 1800', 'ascensores electromecanicos adicional puertas de 900'],
        ['ascensores electromecanicos adicional puertas de 1800', 'ascensores electromecanicos adicional puertas de 1000'],
        ['ascensores electromecanicos adicional puertas de 1800', 'ascensores electromecanicos adicional puertas de 1300'],
        
        // Puertas de Ascensores Hidráulicos
        ['ascensores hidraulicos adicional puertas de 900', 'ascensores hidraulicos adicional puertas de 1000'],
        ['ascensores hidraulicos adicional puertas de 900', 'ascensores hidraulicos adicional puertas de 1200'],
        ['ascensores hidraulicos adicional puertas de 900', 'ascensores hidraulicos adicional puertas de 1800'],
        ['ascensores hidraulicos adicional puertas de 1000', 'ascensores hidraulicos adicional puertas de 900'],
        ['ascensores hidraulicos adicional puertas de 1000', 'ascensores hidraulicos adicional puertas de 1200'],
        ['ascensores hidraulicos adicional puertas de 1000', 'ascensores hidraulicos adicional puertas de 1800'],
        ['ascensores hidraulicos adicional puertas de 1200', 'ascensores hidraulicos adicional puertas de 900'],
        ['ascensores hidraulicos adicional puertas de 1200', 'ascensores hidraulicos adicional puertas de 1000'],
        ['ascensores hidraulicos adicional puertas de 1200', 'ascensores hidraulicos adicional puertas de 1800'],
        ['ascensores hidraulicos adicional puertas de 1800', 'ascensores hidraulicos adicional puertas de 900'],
        ['ascensores hidraulicos adicional puertas de 1800', 'ascensores hidraulicos adicional puertas de 1000'],
        ['ascensores hidraulicos adicional puertas de 1800', 'ascensores hidraulicos adicional puertas de 1200'],
        
        // Indicadores de Ascensores Electromecánicos
        ['ascensores electromecanicos adicional indicador led alfa num 1, 2', 'ascensores electromecanicos adicional indicador led alfa num 0, 8'],
        ['ascensores electromecanicos adicional indicador led alfa num 1, 2', 'ascensores electromecanicos adicional indicador lcd color 5'],
        ['ascensores electromecanicos adicional indicador led alfa num 0, 8', 'ascensores electromecanicos adicional indicador led alfa num 1, 2'],
        ['ascensores electromecanicos adicional indicador led alfa num 0, 8', 'ascensores electromecanicos adicional indicador lcd color 5'],
        ['ascensores electromecanicos adicional indicador lcd color 5', 'ascensores electromecanicos adicional indicador led alfa num 1, 2'],
        ['ascensores electromecanicos adicional indicador lcd color 5', 'ascensores electromecanicos adicional indicador led alfa num 0, 8'],
        
        // Capacidades de carga
        ['ascensores electromecanicos adicional 750kg maquina - cabina 2,25m3', 'ascensores electromecanicos adicional 1000kg maquina cabina 2,66'],
        ['ascensores electromecanicos adicional 1000kg maquina cabina 2,66', 'ascensores electromecanicos adicional 750kg maquina - cabina 2,25m3'],
        ['ascensores hidraulicos adicional 750kg central y piston, cabina 2,25m3', 'ascensores hidraulicos adicional 1000kg central y piston, cabina de 2.66m3'],
        ['ascensores hidraulicos adicional 1000kg central y piston, cabina de 2.66m3', 'ascensores hidraulicos adicional 750kg central y piston, cabina 2,25m3']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO opciones_excluyentes (opcion_id, opcion_excluida_id, mensaje_error) VALUES (?, ?, ?)");
    
    foreach ($reglas_ejemplo as $regla) {
        // Buscar IDs de las opciones por nombre
        $stmt_buscar = $pdo->prepare("SELECT id FROM opciones WHERE nombre LIKE ?");
        
        $stmt_buscar->execute(['%' . $regla[0] . '%']);
        $opcion1 = $stmt_buscar->fetch(PDO::FETCH_ASSOC);
        
        $stmt_buscar->execute(['%' . $regla[1] . '%']);
        $opcion2 = $stmt_buscar->fetch(PDO::FETCH_ASSOC);
        
        if ($opcion1 && $opcion2) {
            $mensaje = "No puedes seleccionar '{$regla[1]}' cuando ya tienes seleccionado '{$regla[0]}'";
            $stmt->execute([$opcion1['id'], $opcion2['id'], $mensaje]);
        }
    }
    
    echo "✅ Reglas de ejemplo insertadas exitosamente\n";
    echo "✅ Sistema de reglas de exclusión configurado completamente\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 
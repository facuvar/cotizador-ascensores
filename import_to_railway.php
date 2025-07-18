<?php
// import_to_railway.php
// Script para importar un archivo .sql a la base de datos Railway

// Mostrar errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Buscar el archivo de configuración correcto
if (file_exists(__DIR__ . '/sistema/config.php')) {
    require_once __DIR__ . '/sistema/config.php';
} else {
    require_once __DIR__ . '/config.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    $fileTmpPath = $_FILES['sql_file']['tmp_name'];
    $fileName = $_FILES['sql_file']['name'];
    $fileSize = $_FILES['sql_file']['size'];
    $fileType = $_FILES['sql_file']['type'];

    if ($fileSize > 0 && pathinfo($fileName, PATHINFO_EXTENSION) === 'sql') {
        $sqlContent = file_get_contents($fileTmpPath);

        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($mysqli->connect_error) {
            die('Error de conexión: ' . $mysqli->connect_error);
        }
        $mysqli->set_charset('utf8mb4');

        // Separar las sentencias por punto y coma
        $queries = array_filter(array_map('trim', explode(';', $sqlContent)));
        $errores = [];
        foreach ($queries as $query) {
            if ($query !== '') {
                if (!$mysqli->query($query)) {
                    $errores[] = $mysqli->error . "\nQuery: $query";
                }
            }
        }
        if (empty($errores)) {
            echo '<div style="color:green;">Importación completada correctamente.</div>';
        } else {
            echo '<div style="color:red;">Errores durante la importación:<br><pre>' . implode("\n", $errores) . '</pre></div>';
        }
        $mysqli->close();
    } else {
        echo '<div style="color:red;">Por favor, sube un archivo .sql válido.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar SQL a Railway</title>
</head>
<body style="font-family:sans-serif;max-width:600px;margin:40px auto;">
    <h2>Importar archivo .sql a la base de datos Railway</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="sql_file" accept=".sql" required>
        <button type="submit">Importar</button>
    </form>
    <p>Selecciona el archivo <b>.sql</b> exportado de tu base local y súbelo aquí para importarlo a Railway.</p>
</body>
</html> 
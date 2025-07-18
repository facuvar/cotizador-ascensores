<?php
require_once '../config.php';
require_once '../includes/functions.php';

// Si ya está logueado, redirigir al panel de administración
if (isAdminLoggedIn()) {
    redirect(SITE_URL . '/admin/index.php');
}

$error = '';

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Verificar credenciales
    if ($username === ADMIN_USER && password_verify($password, ADMIN_PASS)) {
        // Login exitoso
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['is_demo'] = false;
        redirect(SITE_URL . '/admin/index.php');
    } elseif ($username === DEMO_USER && password_verify($password, DEMO_PASS)) {
        // Login demo solo lectura
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['is_demo'] = true;
        redirect(SITE_URL . '/admin/index.php');
    } else {
        // Credenciales incorrectas
        $error = 'Usuario o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Administración</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>Acceso Administrador</h2>
            <p>Ingresa tus credenciales para acceder al panel de administración</p>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="flash-message flash-error">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Ingresar</button>
            </div>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="../index.php">Volver al sitio principal</a>
        </div>
    </div>
</body>
</html> 
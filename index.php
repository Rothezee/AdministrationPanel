<?php
// Página de inicio (login)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/conn/config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id_admin, username, password_hash FROM usuarios_admin WHERE username = :username");
    $stmt->bindValue(':username', $username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $hashed_password = $user['password_hash'];

        $loginOk = false;

        // Caso especial: superadmin inicial con contraseña fija sin hash
        if ($user['username'] === 'admin' && $hashed_password === '') {
            if ($password === '39090169aA') {
                $loginOk = true;
            }
        } elseif (password_verify($password, $hashed_password)) {
            $loginOk = true;
        }

        if ($loginOk) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['id_admin'] = $user['id_admin'];
            header("Location: src/administracion/dashboard.php");
            exit();
        } else {
            $error = "Credenciales inválidas.";
        }
    } else {
        $error = "Usuario no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Login</title>
</head>
<body>
    <div class="container-login">
        <div class="login-container">
            <h2>Login</h2>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="input-group">
                    <label for="username">Usuario</label>
                    <input id="username" type="text" name="username" required>
                </div>
                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <input id="password" type="password" name="password" required>
                </div>
                <button type="submit">Login</button>
            </form>
            <div style="margin-top:1rem;font-size:0.8rem;color:var(--text-muted);text-align:center;">
                ¿No tenés usuario? <a href="src/administracion/register.php" style="color:var(--blue);text-decoration:none;">Crear cuenta con clave</a>
            </div>
        </div>
    </div>
</body>
</html>


<?php
// Registro de administradores con clave única de activación
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/config.php';
session_start();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invite_code = trim($_POST['invite_code'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $dni         = trim($_POST['dni'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $password    = $_POST['password'] ?? '';
    $password2   = $_POST['password2'] ?? '';

    // Validaciones básicas
    if ($invite_code === '') {
        $errors[] = 'Debes ingresar la clave única que te proporcionamos.';
    }
    if ($username === '') {
        $errors[] = 'El nombre de usuario es obligatorio.';
    } elseif (strlen($username) < 4) {
        $errors[] = 'El usuario debe tener al menos 4 caracteres.';
    }

    if ($dni === '') {
        $errors[] = 'El DNI es obligatorio.';
    } elseif (!preg_match('/^[0-9]{6,20}$/', preg_replace('/\D+/', '', $dni))) {
        $errors[] = 'El DNI no tiene un formato válido.';
    }

    if ($password === '' || $password2 === '') {
        $errors[] = 'Debes ingresar y confirmar la contraseña.';
    } elseif ($password !== $password2) {
        $errors[] = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
    }

    if ($email === '') {
        $errors[] = 'El correo electrónico es obligatorio.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo electrónico no es válido.';
    }

    if ($phone === '') {
        $errors[] = 'El teléfono de contacto es obligatorio.';
    } elseif (strlen($phone) < 6) {
        $errors[] = 'El teléfono parece demasiado corto.';
    }

    if (!$errors) {
        try {
            // 1) Verificar clave de invitación
            $stmt = $conn->prepare("
                SELECT id_key, code, is_used 
                FROM invite_keys 
                WHERE code = :code
                LIMIT 1
            ");
            $stmt->execute([':code' => $invite_code]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invite) {
                $errors[] = 'La clave ingresada no es válida.';
            } elseif (!empty($invite['is_used'])) {
                $errors[] = 'Esa clave ya fue utilizada para crear una cuenta.';
            }

            // 2) Verificar que el usuario, email o DNI no existan en usuarios_admin
            if (!$errors) {
                $stmt = $conn->prepare("
                    SELECT id_admin, username, email, dni 
                    FROM usuarios_admin 
                    WHERE username = :username OR email = :email OR dni = :dni
                    LIMIT 1
                ");
                $stmt->execute([
                    ':username' => $username,
                    ':email'    => $email,
                    ':dni'      => $dni,
                ]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (strcasecmp($row['username'], $username) === 0) {
                        $errors[] = 'Ese nombre de usuario ya está en uso.';
                    }
                    if (strcasecmp($row['email'] ?? '', $email) === 0) {
                        $errors[] = 'Ese correo electrónico ya está registrado.';
                    }
                    if (strcasecmp($row['dni'] ?? '', $dni) === 0) {
                        $errors[] = 'Ese DNI ya está registrado.';
                    }
                }
            }

            // 3) Crear usuario admin y marcar clave como usada
            if (!$errors) {
                $conn->beginTransaction();

                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO usuarios_admin (dni, username, password_hash, email, phone, is_superadmin)
                    VALUES (:dni, :username, :password_hash, :email, :phone, 0)
                ");
                $stmt->execute([
                    ':dni'          => $dni,
                    ':username'     => $username,
                    ':password_hash'=> $hash,
                    ':email'        => $email,
                    ':phone'        => $phone,
                ]);
                $newAdminId = (int)$conn->lastInsertId();

                $stmt = $conn->prepare("
                    UPDATE invite_keys 
                    SET is_used = 1, used_at = NOW(), used_by_admin = :used_by
                    WHERE id_key = :id_key
                ");
                $stmt->execute([
                    ':used_by' => $newAdminId,
                    ':id_key'  => $invite['id_key'],
                ]);

                $conn->commit();

                $success = 'Cuenta creada correctamente. Ya puedes iniciar sesión.';
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            // Mostrar también el mensaje real para depurar mejor
            $errors[] = 'Ocurrió un error al registrar el usuario. Detalle: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container-login">
        <div class="login-container">
            <h2>Crear cuenta</h2>

            <?php if ($errors): ?>
                <div class="error-message" style="margin-bottom:1rem;text-align:left;">
                    <?php foreach ($errors as $e): ?>
                        <div><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="delete-preview-ok" style="margin-bottom:1rem;">
                    <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="input-group">
                    <label for="invite_code">Clave única de activación</label>
                    <input 
                        type="text" 
                        id="invite_code" 
                        name="invite_code" 
                        required 
                        placeholder="Ej: GOLDDIGGER-2025-XXXX"
                        value="<?php echo htmlspecialchars($_POST['invite_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="input-group">
                    <label for="username">Usuario</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        minlength="4"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="input-group">
                    <label for="dni">DNI</label>
                    <input
                        type="text"
                        id="dni"
                        name="dni"
                        required
                        placeholder="Solo números"
                        value="<?php echo htmlspecialchars($_POST['dni'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="input-group">
                    <label for="email">Correo electrónico</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        placeholder="tu_correo@ejemplo.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="input-group">
                    <label for="phone">Teléfono de contacto</label>
                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        required
                        placeholder="+54 9 ..."
                        value="<?php echo htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >
                </div>

                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        minlength="6"
                    >
                </div>

                <div class="input-group">
                    <label for="password2">Repetir contraseña</label>
                    <input 
                        type="password" 
                        id="password2" 
                        name="password2" 
                        required 
                        minlength="6"
                    >
                </div>

                <button type="submit">Crear cuenta</button>
            </form>

            <div style="margin-top:1rem;font-size:0.8rem;color:var(--text-muted);text-align:center;">
                Ya tenés usuario? <a href="../../index.php" style="color:var(--blue);text-decoration:none;">Iniciar sesión</a>
            </div>
        </div>
    </div>
</body>
</html>


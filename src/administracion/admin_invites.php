<?php
// Gestión de claves de activación y días de uso (solo superadmin)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__, 2) . '/conn/config.php';
session_start();

if (empty($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

// Por ahora consideramos superadmin al usuario "admin"
$isSuperAdmin = ($_SESSION['username'] === 'admin');
if (!$isSuperAdmin) {
    http_response_code(403);
    echo "Acceso restringido.";
    exit();
}

$errors = [];

// Generar nueva clave
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_key'])) {
    try {
        $period = $_POST['subscription_period'] === 'anual' ? 'anual' : 'mensual';
        // Generar un código relativamente único
        $random = random_int(1000, 9999);
        $code = 'GOLD-' . date('Ymd') . '-' . $random;

        $stmt = $conn->prepare("
            INSERT INTO invite_keys (code, is_used, subscription_period, created_at)
            VALUES (:code, 0, :period, NOW())
        ");
        $stmt->execute([
            ':code'   => $code,
            ':period' => $period,
        ]);
    } catch (Exception $e) {
        $errors[] = 'No se pudo generar la clave: ' . $e->getMessage();
    }
}

// Pausar / reanudar servicio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_pause'], $_POST['id_key'])) {
    $idKey = (int)$_POST['id_key'];
    try {
        $stmt = $conn->prepare("UPDATE invite_keys SET paused = 1 - paused WHERE id_key = :id");
        $stmt->execute([':id' => $idKey]);
    } catch (Exception $e) {
        $errors[] = 'No se pudo cambiar el estado de pausa: ' . $e->getMessage();
    }
}

// Eliminar usuario (baja definitiva)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'], $_POST['id_admin'])) {
    $idAdmin = (int)$_POST['id_admin'];
    // Nunca borrar al superadmin admin
    if ($idAdmin > 0 && $idAdmin !== ($_SESSION['id_admin'] ?? 0)) {
        try {
            $stmt = $conn->prepare("DELETE FROM usuarios_admin WHERE id_admin = :id");
            $stmt->execute([':id' => $idAdmin]);
        } catch (Exception $e) {
            $errors[] = 'No se pudo eliminar el usuario: ' . $e->getMessage();
        }
    }
}

// Listar claves con info de uso y suscripción
$stmt = $conn->query("
    SELECT 
        id_key,
        code,
        is_used,
        used_at,
        created_at,
        subscription_period,
        paused,
        used_by_admin,
        CASE 
          WHEN is_used = 1 AND used_at IS NOT NULL
          THEN DATEDIFF(CURDATE(), DATE(used_at))
          ELSE NULL
        END AS dias_uso
    FROM invite_keys
    ORDER BY created_at DESC
");
$keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claves &amp; suscripciones</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="icon" type="image/png" href="../../img/LOGO BONUS.png">
</head>
<body>
    <main style="max-width:900px;margin:2rem auto;padding:1.5rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);">
        <h1 class="page-title" style="margin-bottom:1rem;font-size:1.1rem;">Claves de activación &amp; días de uso</h1>
        <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:1.5rem;">
            Desde esta pantalla podés generar claves nuevas para clientes y ver cuántos días llevan usando el sistema desde que activaron su clave.
        </p>

        <?php if ($errors): ?>
            <div class="error-message" style="margin-bottom:1rem;text-align:left;">
                <?php foreach ($errors as $e): ?>
                    <div><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" style="margin-bottom:1.5rem;display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;min-width:180px;">
                <label for="subscription_period">Tipo de suscripción</label>
                <select id="subscription_period" name="subscription_period" style="min-width:0;">
                    <option value="mensual">Mensual (30 días)</option>
                    <option value="anual">Anual (365 días)</option>
                </select>
            </div>
            <button type="submit" name="generate_key" class="btn-secondary" style="width:auto;">
                + Generar nueva clave
            </button>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Clave</th>
                        <th>Creada</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Usada en</th>
                        <th>Días de uso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$keys): ?>
                    <tr>
                        <td class="table-empty" colspan="5">Aún no hay claves generadas.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($keys as $k): ?>
                        <?php
                        $rowClass = '';
                        $statusPago = '-';
                        $diasUso = $k['dias_uso'] !== null ? (int)$k['dias_uso'] : null;
                        $limite = ($k['subscription_period'] === 'anual') ? 365 : 30;

                        if ($k['is_used'] && !$k['paused'] && $diasUso !== null) {
                            if ($diasUso > $limite + 5) {
                                $rowClass = 'row-low'; // rojo fuerte: dado de baja
                                $statusPago = 'Dado de baja';
                            } elseif ($diasUso > $limite) {
                                $rowClass = 'row-high'; // amarillo: periodo de gracia
                                $statusPago = 'En periodo de gracia';
                            } else {
                                $statusPago = 'Al día';
                            }
                        } elseif ($k['is_used'] && $k['paused']) {
                            $rowClass = 'row-low';
                            $statusPago = 'Pausado';
                        } elseif (!$k['is_used']) {
                            $statusPago = 'Pendiente de uso';
                        }
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><?php echo htmlspecialchars($k['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($k['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $k['subscription_period'] === 'anual' ? 'Anual' : 'Mensual'; ?></td>
                            <td><?php echo htmlspecialchars($statusPago, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($k['used_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php 
                                if ($k['is_used'] && $diasUso !== null) {
                                    echo $diasUso . ' día(s)';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($k['is_used']): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="id_key" value="<?php echo (int)$k['id_key']; ?>">
                                        <button type="submit" name="toggle_pause" class="btn-secondary" style="width:auto;font-size:0.7rem;padding:0.3rem 0.6rem;">
                                            <?php echo $k['paused'] ? 'Reanudar' : 'Pausar'; ?>
                                        </button>
                                    </form>
                                    <?php if (!empty($k['used_by_admin'])): ?>
                                        <form method="post" style="display:inline;margin-left:4px;" onsubmit="return confirm('¿Eliminar este usuario admin? Esta acción no se puede deshacer.');">
                                            <input type="hidden" name="id_admin" value="<?php echo (int)$k['used_by_admin']; ?>">
                                            <button type="submit" name="delete_user" class="btn-danger" style="width:auto;font-size:0.7rem;padding:0.3rem 0.6rem;">
                                                Eliminar usuario
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:1rem;font-size:0.8rem;">
            <a href="dashboard.php" style="color:var(--blue);">← Volver al dashboard</a>
        </div>
    </main>
</body>
</html>


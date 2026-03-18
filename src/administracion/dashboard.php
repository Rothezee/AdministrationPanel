<?php
session_start();
if (empty($_SESSION['username'])) {
  header('Location: ../../index.php');
  exit();
}
$isSuperAdmin = ($_SESSION['username'] === 'admin');

// Avisos de suscripción para el admin actual
require_once dirname(__DIR__, 2) . '/conn/config.php';
$subscriptionBanner = null;
$navbarBadge = null;

if (!empty($_SESSION['id_admin'])) {
  $stmt = $conn->prepare("
      SELECT 
        subscription_period,
        paused,
        is_used,
        used_at,
        CASE 
          WHEN is_used = 1 AND used_at IS NOT NULL
          THEN DATEDIFF(CURDATE(), DATE(used_at))
          ELSE NULL
        END AS dias_uso
      FROM invite_keys
      WHERE used_by_admin = :id_admin
      ORDER BY used_at DESC
      LIMIT 1
  ");
  $stmt->execute([':id_admin' => $_SESSION['id_admin']]);
  if ($info = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dias = $info['dias_uso'];
    $limite = ($info['subscription_period'] === 'anual') ? 365 : 30;

    if ($info['is_used'] && $dias !== null) {
      if ($dias > $limite + 5) {
        // Auto-pausar si aún no está pausado
        if (!$info['paused']) {
          $upd = $conn->prepare("UPDATE invite_keys SET paused = 1 WHERE used_by_admin = :id_admin");
          $upd->execute([':id_admin' => $_SESSION['id_admin']]);
        }
        $subscriptionBanner = [
          'type' => 'danger',
          'title' => 'Servicio pausado por falta de pago',
          'text'  => 'Tu período de suscripción ha vencido y se ha agotado el período de gracia. Contactanos para regularizar el pago y reactivar el servicio.',
        ];
        $navbarBadge = 'Servicio pausado';
      } elseif ($dias > $limite) {
        $resto = ($limite + 5) - $dias;
        if ($resto < 0) $resto = 0;
        $subscriptionBanner = [
          'type' => 'warn',
          'title' => 'Tu suscripción está por vencer',
          'text'  => "Tu período de suscripción ya se cumplió. Tenés {$resto} día(s) de gracia para realizar el pago antes de que el servicio se pause.",
        ];
        $navbarBadge = 'Suscripción próxima a vencer';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <title>Panel de Control</title>
</head>
<body>

<!-- ════════════════════════════════════════ NAVBAR -->
<header>
  <nav class="navbar">
    <div class="navbar-brand">
      <img src="../../img/ChatGPT Image 1 abr 2025, 21_59_11-Photoroom.png" alt="Logo" id="logo">
      <span class="brand-name">Panel de Control <span class="brand-sub">Gestión de Máquinas</span></span>
    </div>
    <div style="display:flex;align-items:center;gap:0.5rem;">
      <?php if ($navbarBadge): ?>
        <span class="report-badge" style="background:var(--amber-dim);border-color:var(--amber-border);font-size:0.68rem;">
          <?php echo htmlspecialchars($navbarBadge, ENT_QUOTES, 'UTF-8'); ?>
        </span>
      <?php endif; ?>
      <?php if ($isSuperAdmin): ?>
      <a href="./admin_invites.php" class="btn-secondary" style="text-decoration:none;">Claves &amp; suscripciones</a>
      <?php endif; ?>
      <button class="btn-danger-outline" id="btn-borrado-global" title="Eliminar registros de todas las máquinas">
        🗑 Limpiar datos
      </button>
      <button class="navbar-toggler" id="navbar-toggler">
        <span></span><span></span><span></span>
      </button>
    </div>
    <div class="navbar-menu" id="open-navbar1">
      <ul class="navbar-nav">
        <li class="active"><a href="dashboard.php">Dashboard</a></li>
      </ul>
    </div>
  </nav>
</header>

<!-- ════════════════════════════════════════ MAIN -->
<main id="dashboard-root">
  <?php if ($subscriptionBanner): ?>
    <section style="max-width:800px;margin:0 auto 1rem;">
      <div class="delete-preview-msg <?php echo $subscriptionBanner['type']==='danger' ? 'delete-preview-danger' : 'delete-preview-warn'; ?>">
        <strong><?php echo htmlspecialchars($subscriptionBanner['title'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
        <span><?php echo htmlspecialchars($subscriptionBanner['text'], ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
    </section>
  <?php endif; ?>
  <!-- Cards generadas dinámicamente por main.js -->
</main>

<!-- ════════════════════════════════════════ MODAL BORRADO GLOBAL -->
<div id="global-delete-modal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <h2 class="modal-title" style="color:var(--red)">🗑 Limpiar datos globalmente</h2>
      <button class="modal-close" id="global-delete-close">✕</button>
    </div>
    <div class="modal-body">

      <p style="font-size:.82rem;color:var(--text-secondary);line-height:1.6;margin-bottom:1rem">
        Elimina los registros de <strong style="color:var(--text-primary)">todas las máquinas</strong>
        hasta la fecha que indiques. Útil para hacer limpieza periódica de datos antiguos.
      </p>

      <div class="form-group">
        <label for="global-del-hasta">Eliminar todos los registros hasta (inclusive)</label>
        <input type="date" id="global-del-hasta" style="width:100%;min-width:unset">
      </div>

      <div id="global-delete-preview" class="delete-preview-msg" style="margin-top:.5rem"></div>

    </div>
    <div class="modal-footer">
      <button class="btn-modal-cancel" id="global-delete-cancel">Cancelar</button>
      <button id="btn-global-preview"  class="btn-secondary">Ver previsualización</button>
      <button id="btn-global-confirm"  class="btn-danger" disabled>Eliminar todo</button>
    </div>
  </div>
</div>

<script src="../../assets/js/main.js"></script>
<script>
  /* ── Navbar mobile ── */
  document.getElementById('navbar-toggler').addEventListener('click', () => {
    document.getElementById('open-navbar1').classList.toggle('active');
  });

  /* ── Modal borrado global ── */
  const globalModal   = document.getElementById('global-delete-modal');
  const globalClose   = () => {
    globalModal.style.display = 'none';
    document.getElementById('global-del-hasta').value = '';
    document.getElementById('global-delete-preview').textContent = '';
    document.getElementById('btn-global-confirm').disabled = true;
  };

  document.getElementById('btn-borrado-global') .addEventListener('click', () => { globalModal.style.display = 'flex'; });
  document.getElementById('global-delete-close').addEventListener('click', globalClose);
  document.getElementById('global-delete-cancel').addEventListener('click', globalClose);
  globalModal.addEventListener('click', e => { if (e.target === globalModal) globalClose(); });

  document.getElementById('global-del-hasta').addEventListener('change', () => {
    document.getElementById('btn-global-confirm').disabled = true;
    document.getElementById('global-delete-preview').textContent = '';
  });

  /* Preview: contar cuántos registros hay hasta esa fecha */
  document.getElementById('btn-global-preview').addEventListener('click', function () {
    const hasta = document.getElementById('global-del-hasta').value;
    const info  = document.getElementById('global-delete-preview');
    if (!hasta) {
      info.textContent = 'Seleccioná una fecha primero.';
      info.className   = 'delete-preview-msg delete-preview-warn';
      return;
    }
    this.disabled    = true;
    this.textContent = 'Consultando…';
    info.textContent = '';

    // Contar registros de cada máquina hasta esa fecha
    fetch(`get_all_devices.php`)
      .then(r => r.json())
      .then(async devData => {
        if (!devData.devices) throw new Error('Sin datos');
        // Fetch count for each device
        let total = 0;
        const proms = devData.devices.map(d =>
          fetch(`get_report.php?device_id=${encodeURIComponent(d.device_id)}&fechaFin=${hasta}`)
            .then(r => r.json())
            .then(data => { total += (data.reports || []).length; })
            .catch(() => {})
        );
        await Promise.all(proms);
        if (total === 0) {
          info.textContent = 'No hay registros hasta esa fecha.';
          info.className   = 'delete-preview-msg delete-preview-warn';
          document.getElementById('btn-global-confirm').disabled = true;
        } else {
          info.innerHTML = `⚠ Se eliminarán <strong>${total}</strong> registro${total > 1 ? 's' : ''} de todas las máquinas hasta <strong>${hasta}</strong>.`;
          info.className = 'delete-preview-msg delete-preview-danger';
          document.getElementById('btn-global-confirm').disabled = false;
        }
      })
      .catch(err => {
        info.textContent = 'Error: ' + err.message;
        info.className   = 'delete-preview-msg delete-preview-warn';
      })
      .finally(() => {
        this.disabled    = false;
        this.textContent = 'Ver previsualización';
      });
  });

  /* Confirmar borrado global */
  document.getElementById('btn-global-confirm').addEventListener('click', function () {
    const hasta = document.getElementById('global-del-hasta').value;
    const info  = document.getElementById('global-delete-preview');
    if (!hasta) return;

    const ok = confirm(
      `¿Eliminar TODOS los registros de TODAS las máquinas hasta ${hasta}?\n\nEsta acción no se puede deshacer.`
    );
    if (!ok) return;

    this.disabled    = true;
    this.textContent = 'Eliminando…';

    fetch('delete_reports.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ mode: 'global', fecha_hasta: hasta })
    })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          info.innerHTML = `✓ ${data.message}`;
          info.className = 'delete-preview-msg delete-preview-ok';
          this.textContent = 'Eliminar todo';
        } else {
          info.textContent = 'Error: ' + (data.error || 'desconocido');
          info.className   = 'delete-preview-msg delete-preview-warn';
          this.disabled    = false;
          this.textContent = 'Eliminar todo';
        }
      })
      .catch(err => {
        info.textContent = 'Error de red: ' + err.message;
        info.className   = 'delete-preview-msg delete-preview-warn';
        this.disabled    = false;
        this.textContent = 'Eliminar todo';
      });
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') globalClose();
  });
</script>
</body>
</html>
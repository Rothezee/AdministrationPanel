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
$subscriptionData = null;

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
    $subscriptionData = [
      'tipo' => $info['subscription_period'] === 'anual' ? 'Anual' : 'Mensual',
      'fecha_activacion' => $info['used_at'] ?? null,
      'dias_uso' => $info['dias_uso'] ?? null,
      'paused' => (bool)($info['paused'] ?? false),
      'limite' => ($info['subscription_period'] === 'anual') ? 365 : 30,
      'estado' => '-',
      'dias_restantes' => null,
    ];
    if ($info['is_used'] && $info['dias_uso'] !== null) {
      $d = (int)$info['dias_uso'];
      $l = $subscriptionData['limite'];
      if ($info['paused']) {
        $subscriptionData['estado'] = 'Pausado';
      } elseif ($d > $l + 5) {
        $subscriptionData['estado'] = 'Pausado (vencido)';
      } elseif ($d > $l) {
        $subscriptionData['estado'] = 'En periodo de gracia';
        $subscriptionData['dias_restantes'] = max(0, ($l + 5) - $d);
      } else {
        $subscriptionData['estado'] = 'Al día';
        $subscriptionData['dias_restantes'] = $l - $d;
      }
    } elseif (!$info['is_used']) {
      $subscriptionData['estado'] = 'Pendiente de activación';
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
  <link rel="icon" type="image/png" href="../../img/LOGO BONUS.png">
  <title>Panel de Control</title>
</head>
<body>

<!-- ════════════════════════════════════════ NAVBAR -->
<header>
  <nav class="navbar">
    <div class="navbar-left">
      <button class="btn-icon" id="btn-sidebar-toggle" title="Abrir/cerrar menú">☰</button>
      <div class="navbar-brand">
        <img src="../../img/LOGO BONUS.png" alt="Logo" id="logo">
        <span class="brand-name">Panel de Control <span class="brand-sub">Gestión de Máquinas</span></span>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:0.5rem;">
      <?php if ($navbarBadge): ?>
        <span class="report-badge" style="background:var(--amber-dim);border-color:var(--amber-border);font-size:0.68rem;">
          <?php echo htmlspecialchars($navbarBadge, ENT_QUOTES, 'UTF-8'); ?>
        </span>
      <?php endif; ?>
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

<!-- ════════════════════════════════════════ LAYOUT + SIDEBAR -->
<div class="app-layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <img src="../../img/LOGO BONUS.png" alt="Logo" class="sidebar-logo">
      <span class="sidebar-title">Menú</span>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="sidebar-link active">📊 Dashboard</a>
      <div class="sidebar-section">
        <button class="sidebar-link sidebar-toggle" data-target="admin-menu">📁 Administración</button>
        <ul class="sidebar-submenu open" id="admin-menu">
          <li><button class="sidebar-link" id="btn-borrado-global">🗑 Limpiar datos (global)</button></li>
          <li><button class="sidebar-link" id="btn-borrar-maquina">🗑 Borrar máquina</button></li>
        </ul>
      </div>
      <?php if ($isSuperAdmin): ?>
      <a href="./admin_invites.php" class="sidebar-link">🔑 Claves &amp; suscripciones</a>
      <?php else: ?>
      <button class="sidebar-link" id="btn-suscripcion">📋 Suscripción</button>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
      <button class="btn-icon sidebar-close" id="sidebar-close" title="Cerrar menú">✕</button>
    </div>
  </aside>
  <main class="main-content">
  <?php if ($subscriptionBanner): ?>
    <section style="max-width:800px;margin:0 auto 1rem;">
      <div class="delete-preview-msg <?php echo $subscriptionBanner['type']==='danger' ? 'delete-preview-danger' : 'delete-preview-warn'; ?>">
        <strong><?php echo htmlspecialchars($subscriptionBanner['title'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
        <span><?php echo htmlspecialchars($subscriptionBanner['text'], ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
    </section>
  <?php endif; ?>
  <div id="dashboard-root"><!-- Cards generadas dinámicamente por main.js --></div>
  </main>
</div>

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

<!-- ════════════════════════════════════════ MODAL SUSCRIPCIÓN -->
<div id="subscription-modal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <h2 class="modal-title">📋 Mi suscripción</h2>
      <button class="modal-close" id="subscription-modal-close">✕</button>
    </div>
    <div class="modal-body" id="subscription-modal-body">
      <?php if ($subscriptionData): ?>
      <div class="subscription-detail">
        <div class="subscription-row"><span class="subscription-label">Tipo</span><span><?php echo htmlspecialchars($subscriptionData['tipo'], ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="subscription-row"><span class="subscription-label">Fecha de activación</span><span><?php echo htmlspecialchars($subscriptionData['fecha_activacion'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="subscription-row"><span class="subscription-label">Días de uso</span><span><?php echo $subscriptionData['dias_uso'] !== null ? (int)$subscriptionData['dias_uso'] . ' día(s)' : '-'; ?></span></div>
        <div class="subscription-row"><span class="subscription-label">Estado</span><span><?php echo htmlspecialchars($subscriptionData['estado'], ENT_QUOTES, 'UTF-8'); ?></span></div>
        <?php if ($subscriptionData['dias_restantes'] !== null && $subscriptionData['estado'] === 'Al día'): ?>
        <div class="subscription-row"><span class="subscription-label">Días restantes</span><span><?php echo (int)$subscriptionData['dias_restantes']; ?> día(s)</span></div>
        <?php elseif ($subscriptionData['dias_restantes'] !== null && $subscriptionData['estado'] === 'En periodo de gracia'): ?>
        <div class="subscription-row"><span class="subscription-label">Días de gracia restantes</span><span><?php echo (int)$subscriptionData['dias_restantes']; ?> día(s)</span></div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <p style="color:var(--text-muted);">No hay datos de suscripción.</p>
      <?php endif; ?>
    </div>
    <div class="modal-footer">
      <button class="btn-modal-cancel" id="subscription-modal-cancel">Cerrar</button>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════ MODAL BORRAR MÁQUINA -->
<div id="delete-device-modal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:520px">
    <div class="modal-header">
      <h2 class="modal-title" style="color:var(--red)">🗑 Borrar máquina</h2>
      <button class="modal-close" id="delete-device-close">✕</button>
    </div>
    <div class="modal-body">
      <p style="font-size:.82rem;color:var(--text-secondary);line-height:1.6;margin-bottom:1rem">
        Elimina la máquina y <strong>todos sus reportes</strong>. Esta acción no se puede deshacer.
      </p>
      <div id="delete-device-list" class="delete-device-list"></div>
    </div>
    <div class="modal-footer">
      <button class="btn-modal-cancel" id="delete-device-cancel">Cancelar</button>
    </div>
  </div>
</div>

<script src="../../assets/js/main.js"></script>
<script>
  /* ── Sidebar (mismo botón abre/cierra) ── */
  const sidebar = document.getElementById('sidebar');
  document.getElementById('btn-sidebar-toggle').addEventListener('click', () => sidebar.classList.toggle('open'));
  document.getElementById('sidebar-close').addEventListener('click', () => sidebar.classList.remove('open'));
  document.querySelectorAll('.sidebar-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.target);
      if (target) target.classList.toggle('open');
    });
  });

  /* ── Modal suscripción ── */
  const subModal = document.getElementById('subscription-modal');
  const btnSub = document.getElementById('btn-suscripcion');
  if (btnSub) {
    btnSub.addEventListener('click', () => { subModal.style.display = 'flex'; });
  }
  document.getElementById('subscription-modal-close')?.addEventListener('click', () => { subModal.style.display = 'none'; });
  document.getElementById('subscription-modal-cancel')?.addEventListener('click', () => { subModal.style.display = 'none'; });
  subModal?.addEventListener('click', e => { if (e.target === subModal) subModal.style.display = 'none'; });

  /* ── Modal borrar máquina ── */
  const deviceModal = document.getElementById('delete-device-modal');
  document.getElementById('btn-borrar-maquina').addEventListener('click', () => {
    fetch('../devices/get_all_devices.php').then(r => r.json()).then(data => {
      const list = document.getElementById('delete-device-list');
      list.innerHTML = '';
      (data.devices || []).forEach(d => {
        const row = document.createElement('div');
        row.className = 'delete-device-row';
        row.innerHTML = `<span>${escapeHtml(d.device_id)}</span><button class="btn-danger btn-sm" data-device="${escapeHtml(d.device_id)}">Eliminar</button>`;
        row.querySelector('button').addEventListener('click', function() {
          const id = this.dataset.device;
          if (!confirm(`¿Eliminar la máquina ${id} y todos sus reportes?`)) return;
          this.disabled = true;
          fetch('delete_device.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ device_id: id }) })
            .then(r => r.json())
            .then(res => {
              if (res.success) {
                row.remove();
                if (typeof loadConfig === 'function' && typeof saveConfig === 'function') {
                  const cfg = loadConfig();
                  delete cfg[id];
                  saveConfig(cfg);
                }
                if (typeof buildDashboard === 'function') buildDashboard();
              }
              else alert('Error: ' + (res.error || 'desconocido'));
            })
            .catch(() => alert('Error de red'))
            .finally(() => { this.disabled = false; });
        });
        list.appendChild(row);
      });
      if (list.children.length === 0) list.innerHTML = '<p style="color:var(--text-muted)">No hay máquinas registradas.</p>';
      deviceModal.style.display = 'flex';
    });
  });
  document.getElementById('delete-device-close').addEventListener('click', () => { deviceModal.style.display = 'none'; });
  document.getElementById('delete-device-cancel').addEventListener('click', () => { deviceModal.style.display = 'none'; });
  deviceModal.addEventListener('click', e => { if (e.target === deviceModal) deviceModal.style.display = 'none'; });
  function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

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
    fetch(`../devices/get_all_devices.php`)
      .then(r => r.json())
      .then(async devData => {
        if (!devData.devices) throw new Error('Sin datos');
        // Fetch count for each device
        let total = 0;
        const proms = devData.devices.map(d =>
          fetch(`get_report.php?device_id=${encodeURIComponent(d.device_id)}&fechaFin=${hasta}`)
            .then(r => r.json())
            .then(data => { total += (data.count ?? (data.reports || []).length); })
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
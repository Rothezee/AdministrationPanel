/**
 * ══════════════════════════════════════════════════════════════════════
 *  PANEL DE CONTROL — main.js
 *
 *  CONFIG SYSTEM
 *  ─────────────
 *  La configuración de cada máquina se guarda en localStorage bajo la
 *  clave "machine_config":
 *    {
 *      [deviceId]: {
 *        displayName : string   // nombre visible
 *        localName   : string   // local al que pertenece
 *        groupName   : string   // grupo dentro del local
 *        type        : string   // 'maquina' | 'expendedora' | 'videojuego' | 'ticket'
 *        description : string   // descripción libre, visible en el reporte
 *      }
 *    }
 *
 *  LOCALS define la configuración DEFAULT que se carga la primera vez.
 *  Los devices que lleguen desde la DB sin config van al box "No configuradas".
 * ══════════════════════════════════════════════════════════════════════
 */

/* ══════════════════════════════════════════════════
   LOCALES Y GRUPOS DEFAULT
══════════════════════════════════════════════════ */
const LOCALS_DEFAULT = {
  'San Luis': {
    icon: '📍',
    groups: {
      'Máquinas':     ['ESP32_001','ESP32_002','ESP32_003','ESP32_004','ESP32_005'],
      'Expendedoras': ['EXPENDEDORA_1','EXPENDEDORA_2'],
      'Videojuegos':  ['Videojuego_1','Videojuego_2'],
      'Ticketeras':   ['Ticket_1','Ticket_2'],
    }
  },
  'Miramar (Córdoba)': {
    icon: '📍',
    groups: {
      'Máquinas':     ['ESP32_006','ESP32_007','ESP32_008'],
      'Expendedoras': ['EXPENDEDORA_3'],
      'Videojuegos':  ['Videojuego_3'],
      'Ticketeras':   ['Ticket_3'],
    }
  },
  'Villa Gesell': {
    icon: '📍',
    groups: {
      'Máquinas':     ['ESP32_009','ESP32_010'],
      'Expendedoras': ['EXPENDEDORA_4','EXPENDEDORA_5'],
      'Videojuegos':  ['Videojuego_4','Videojuego_5'],
      'Ticketeras':   ['Ticket_4','Ticket_5'],
    }
  },
};

/* Config por device_id en LOCALS_DEFAULT */
const DEVICE_DEFAULT_META = {
  // Maquinas
  ESP32_001:  { displayName:'Máquina 1',    type:'maquina'     },
  ESP32_002:  { displayName:'Máquina 2',    type:'maquina'     },
  ESP32_003:  { displayName:'Máquina 3',    type:'maquina'     },
  ESP32_004:  { displayName:'Máquina 4',    type:'maquina'     },
  ESP32_005:  { displayName:'Máquina 5',    type:'maquina'     },
  ESP32_006:  { displayName:'Máquina 6',    type:'maquina'     },
  ESP32_007:  { displayName:'Máquina 7',    type:'maquina'     },
  ESP32_008:  { displayName:'Máquina 8',    type:'maquina'     },
  ESP32_009:  { displayName:'Máquina 9',    type:'maquina'     },
  ESP32_010:  { displayName:'Máquina 10',   type:'maquina'     },
  // Expendedoras
  EXPENDEDORA_1: { displayName:'Expendedora 1', type:'expendedora' },
  EXPENDEDORA_2: { displayName:'Expendedora 2', type:'expendedora' },
  EXPENDEDORA_3: { displayName:'Expendedora 3', type:'expendedora' },
  EXPENDEDORA_4: { displayName:'Expendedora 4', type:'expendedora' },
  EXPENDEDORA_5: { displayName:'Expendedora 5', type:'expendedora' },
  // Videojuegos
  Videojuego_1: { displayName:'Videojuego 1', type:'videojuego' },
  Videojuego_2: { displayName:'Videojuego 2', type:'videojuego' },
  Videojuego_3: { displayName:'Videojuego 3', type:'videojuego' },
  Videojuego_4: { displayName:'Videojuego 4', type:'videojuego' },
  Videojuego_5: { displayName:'Videojuego 5', type:'videojuego' },
  // Ticketeras
  Ticket_1: { displayName:'Ticketera 1', type:'ticket' },
  Ticket_2: { displayName:'Ticketera 2', type:'ticket' },
  Ticket_3: { displayName:'Ticketera 3', type:'ticket' },
  Ticket_4: { displayName:'Ticketera 4', type:'ticket' },
  Ticket_5: { displayName:'Ticketera 5', type:'ticket' },
};

/* ══════════════════════════════════════════════════
   DEVICE TYPES — fields + report URL
══════════════════════════════════════════════════ */
const DEVICE_TYPES = {
  maquina: {
    label: 'Máquina',
    fields: [
      { label:'Pesos',   dataKey:'dato1', elSuffix:'pesos'   },
      { label:'Coin',    dataKey:'dato2', elSuffix:'coin'    },
      { label:'Premios', dataKey:'dato3', elSuffix:'premios' },
      { label:'Banco',   dataKey:'dato4', elSuffix:'banco', isBanco:true },
    ],
    reportUrl: id => `report.php?device_id=${id}&tipo=maquina`,
  },
  expendedora: {
    label: 'Expendedora',
    fields: [
      { label:'Fichas', dataKey:'dato1', elSuffix:'fichas' },
      { label:'Dinero', dataKey:'dato2', elSuffix:'dinero' },
    ],
    reportUrl: id => `report.php?device_id=${id}&tipo=expendedora`,
  },
  videojuego: {
    label: 'Videojuego',
    fields: [
      { label:'Coin', dataKey:'dato2', elSuffix:'coin' },
    ],
    reportUrl: id => `report.php?device_id=${id}&tipo=videojuego`,
  },
  ticket: {
    label: 'Ticketera',
    fields: [
      { label:'Coin',    dataKey:'dato2', elSuffix:'coin'   },
      { label:'Tickets', dataKey:'dato5', elSuffix:'ticket' },
    ],
    reportUrl: id => `report.php?device_id=${id}&tipo=ticket`,
  },
};

/* ══════════════════════════════════════════════════
   LOCAL STORAGE CONFIG
══════════════════════════════════════════════════ */
const LS_KEY = 'machine_config';

function loadConfig() {
  try {
    return JSON.parse(localStorage.getItem(LS_KEY) || '{}');
  } catch { return {}; }
}

function saveConfig(cfg) {
  localStorage.setItem(LS_KEY, JSON.stringify(cfg));
}

/**
 * Merge LOCALS_DEFAULT into localStorage on first load.
 * Existing localStorage entries are NOT overwritten.
 */
function initConfig() {
  const cfg = loadConfig();
  let changed = false;

  for (const [localName, localData] of Object.entries(LOCALS_DEFAULT)) {
    for (const [groupName, ids] of Object.entries(localData.groups)) {
      for (const id of ids) {
        if (!cfg[id]) {
          const meta = DEVICE_DEFAULT_META[id] || {};
          cfg[id] = {
            displayName : meta.displayName || id,
            localName,
            groupName,
            type        : meta.type || 'maquina',
            description : '',
          };
          changed = true;
        }
      }
    }
  }

  if (changed) saveConfig(cfg);
  return cfg;
}

/* Get the list of all LOCAL names that are currently in use */
function getKnownLocals(cfg) {
  const set = new Set(Object.values(cfg).map(c => c.localName).filter(Boolean));
  return Array.from(set);
}

/* ══════════════════════════════════════════════════
   ID HELPERS
══════════════════════════════════════════════════ */
function safeId(s)            { return s.replace(/[^a-zA-Z0-9_-]/g, '_'); }
function statusElId(id)       { return `status__${safeId(id)}`; }
function badgeElId(id)        { return `badge__${safeId(id)}`; }
function fieldElId(id, sfx)   { return `${sfx}__${safeId(id)}`; }

/* ══════════════════════════════════════════════════
   DASHBOARD BUILDER
══════════════════════════════════════════════════ */
function buildDashboard() {
  const root = document.getElementById('dashboard-root');
  if (!root) return;
  root.innerHTML = '';

  const cfg = loadConfig();

  /* ── Group configured devices by local → group ── */
  const byLocal = {}; // { localName: { groupName: [deviceId, ...] } }

  for (const [id, info] of Object.entries(cfg)) {
    const ln = info.localName || '_unconfigured_';
    const gn = info.groupName || 'Sin grupo';
    if (!byLocal[ln]) byLocal[ln] = {};
    if (!byLocal[ln][gn]) byLocal[ln][gn] = [];
    byLocal[ln][gn].push(id);
  }

  /* ── Render each local box ── */
  for (const [localName, groups] of Object.entries(byLocal)) {
    if (localName === '_unconfigured_') continue;
    const totalDevices = Object.values(groups).reduce((a, arr) => a + arr.length, 0);
    root.appendChild(buildLocalBox(localName, groups, cfg, totalDevices));
  }

  /* ── Unconfigured box (only if there are any) ── */
  if (byLocal['_unconfigured_'] && Object.keys(byLocal['_unconfigured_']).length) {
    root.appendChild(buildUnconfiguredBox(byLocal['_unconfigured_'], cfg));
  }
}

function buildLocalBox(localName, groups, cfg, totalDevices) {
  const box = document.createElement('div');
  box.className = 'local-box';
  box.dataset.local = localName;

  box.innerHTML = `
    <div class="local-header">
      <div class="local-header-left">
        <span class="local-icon">📍</span>
        <span class="local-name">${localName}</span>
        <span class="local-count">${totalDevices} dispositivos</span>
      </div>
      <div class="local-meta">
        <div class="meta-item">
          <span class="meta-label">Online</span>
          <span class="meta-value" id="online-count__${safeId(localName)}">—</span>
        </div>
        <div class="meta-item">
          <span class="meta-label">Offline</span>
          <span class="meta-value" id="offline-count__${safeId(localName)}">—</span>
        </div>
      </div>
    </div>
  `;

  for (const [groupName, ids] of Object.entries(groups)) {
    const groupEl = document.createElement('div');
    groupEl.className = 'device-group';
    groupEl.innerHTML = `
      <div class="group-heading">
        <span class="group-heading-text">${groupName}</span>
        <div class="group-heading-line"></div>
      </div>
    `;
    const grid = document.createElement('div');
    grid.className = 'card-grid';
    for (const id of ids) grid.appendChild(buildCard(id, cfg[id]));
    groupEl.appendChild(grid);
    box.appendChild(groupEl);
  }

  return box;
}

function buildUnconfiguredBox(groups, cfg) {
  const box = document.createElement('div');
  box.className = 'local-box unconfigured-box';
  box.id = 'unconfigured-box';

  const allIds = Object.values(groups).flat();

  box.innerHTML = `
    <div class="local-header" style="border-left: 3px solid var(--amber);">
      <div class="local-header-left">
        <span class="local-icon">⚠️</span>
        <span class="local-name" style="color:var(--amber)">No configuradas</span>
        <span class="local-count">${allIds.length} dispositivo${allIds.length > 1 ? 's' : ''} sin asignar</span>
      </div>
      <div class="local-meta">
        <span class="meta-label" style="color:var(--text-muted);font-size:.7rem;">
          Configurá cada máquina con el botón ⚙
        </span>
      </div>
    </div>
  `;

  const group = document.createElement('div');
  group.className = 'device-group';
  const grid = document.createElement('div');
  grid.className = 'card-grid';

  for (const id of allIds) {
    grid.appendChild(buildCard(id, cfg[id] || { displayName: id, type: 'maquina', description: '' }));
  }

  group.appendChild(grid);
  box.appendChild(group);
  return box;
}

/* ══════════════════════════════════════════════════
   CARD BUILDER
══════════════════════════════════════════════════ */
function buildCard(deviceId, info) {
  if (!info) info = { displayName: deviceId, type: 'maquina', description: '' };

  const typeDef = DEVICE_TYPES[info.type] || DEVICE_TYPES['maquina'];

  const fieldsHtml = typeDef.fields.map(f => `
    <div class="stat-row">
      <span class="stat-label">${f.label}</span>
      <span class="stat-value" id="${fieldElId(deviceId, f.elSuffix)}">—</span>
    </div>
  `).join('');

  const card = document.createElement('div');
  card.className = 'item';
  card.dataset.deviceId = deviceId;

  card.innerHTML = `
    <div class="card-top">
      <span class="card-name">${info.displayName}</span>
      <div style="display:flex;align-items:center;gap:5px;">
        <div class="status-badge" id="${badgeElId(deviceId)}">
          <div class="status-dot"></div>
          <span class="status" id="${statusElId(deviceId)}">Offline</span>
        </div>
        <button class="btn-config" title="Configurar" onclick="openConfigModal('${deviceId.replace(/'/g,"\\'")}')">⚙</button>
      </div>
    </div>
    <div class="card-divider"></div>
    <div class="machine-stats">${fieldsHtml}</div>
    ${info.description ? `<p class="card-description">${info.description}</p>` : `<p class="card-description" id="desc__${safeId(deviceId)}" style="display:none"></p>`}
    <button class="btn-report" onclick="location.href='${typeDef.reportUrl(deviceId)}'">Ver Reporte →</button>
  `;

  return card;
}

/* ══════════════════════════════════════════════════
   CONFIG MODAL
══════════════════════════════════════════════════ */
function buildModal() {
  if (document.getElementById('config-modal')) return;

  const overlay = document.createElement('div');
  overlay.id = 'config-modal';
  overlay.className = 'modal-overlay';

  overlay.innerHTML = `
    <div class="modal-box" role="dialog" aria-modal="true">
      <div class="modal-header">
        <h2 class="modal-title">Configurar máquina</h2>
        <button class="modal-close" id="modal-btn-close" aria-label="Cerrar">✕</button>
      </div>
      <div class="modal-body">
        <div class="modal-device-id" id="modal-device-id-label"></div>
        <div class="form-group">
          <label for="cfg-name">Nombre visible</label>
          <input type="text" id="cfg-name" placeholder="Ej: Máquina Sala A">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="cfg-local">Local</label>
            <input type="text" id="cfg-local" list="cfg-local-list" placeholder="Ej: San Luis">
            <datalist id="cfg-local-list"></datalist>
          </div>
          <div class="form-group">
            <label for="cfg-group">Grupo</label>
            <input type="text" id="cfg-group" list="cfg-group-list" placeholder="Ej: Máquinas">
            <datalist id="cfg-group-list"></datalist>
          </div>
        </div>
        <div class="form-group">
          <label for="cfg-type">Tipo de dispositivo</label>
          <select id="cfg-type">
            <option value="maquina">Máquina (Pesos / Coin / Premios / Banco)</option>
            <option value="expendedora">Expendedora (Fichas / Dinero)</option>
            <option value="videojuego">Videojuego (Coin)</option>
            <option value="ticket">Ticketera (Coin / Tickets)</option>
          </select>
        </div>
        <div class="form-group">
          <label for="cfg-description">
            Descripción
            <span style="color:var(--text-muted);font-weight:400;text-transform:none;letter-spacing:0">
              — opcional, visible en el reporte
            </span>
          </label>
          <textarea id="cfg-description" rows="3"
            placeholder="Ej: Máquina de la entrada, lado izquierdo. Revisada en marzo 2025."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-modal-cancel" id="modal-btn-cancel">Cancelar</button>
        <button class="btn-modal-save"   id="modal-btn-save">Guardar cambios</button>
      </div>
    </div>
  `;

  // Append first, then attach listeners (elements must exist in DOM)
  document.body.appendChild(overlay);

  document.getElementById('modal-btn-close') .addEventListener('click', closeConfigModal);
  document.getElementById('modal-btn-cancel').addEventListener('click', closeConfigModal);
  document.getElementById('modal-btn-save')  .addEventListener('click', saveModalConfig);

  // Click outside box → close
  overlay.addEventListener('click', e => {
    if (e.target === overlay) closeConfigModal();
  });
}

let _currentModalDeviceId = null;

function openConfigModal(deviceId) {
  buildModal(); // no-op if already built

  _currentModalDeviceId = deviceId;

  const cfg  = loadConfig();
  const info = cfg[deviceId] || { displayName: deviceId, localName: '', groupName: '', type: 'maquina', description: '' };

  const modal = document.getElementById('config-modal');

  document.getElementById('modal-device-id-label').textContent = 'ID: ' + deviceId;
  document.getElementById('cfg-name').value        = info.displayName || '';
  document.getElementById('cfg-local').value       = info.localName   || '';
  document.getElementById('cfg-group').value       = info.groupName   || '';
  document.getElementById('cfg-type').value        = info.type        || 'maquina';
  document.getElementById('cfg-description').value = info.description || '';

  // Populate datalists
  const knownLocals = getKnownLocals(cfg);
  document.getElementById('cfg-local-list').innerHTML =
    knownLocals.map(l => `<option value="${l}">`).join('');

  const usedGroups = new Set(
    Object.values(cfg)
      .filter(c => c.localName === info.localName)
      .map(c => c.groupName)
      .filter(Boolean)
  );
  document.getElementById('cfg-group-list').innerHTML =
    Array.from(usedGroups).map(g => `<option value="${g}">`).join('');

  // Show modal — use style directly (most reliable cross-browser)
  modal.style.display = 'flex';
  // Trigger CSS animation by forcing reflow
  modal.offsetHeight; // eslint-disable-line
  document.getElementById('cfg-name').focus();
}

function closeConfigModal() {
  const modal = document.getElementById('config-modal');
  if (modal) modal.style.display = 'none';
  _currentModalDeviceId = null;
}

function saveModalConfig() {
  if (!_currentModalDeviceId) return;

  const id = _currentModalDeviceId;

  const displayName = (document.getElementById('cfg-name').value.trim())        || id;
  const localName   = (document.getElementById('cfg-local').value.trim())       || '';
  const groupName   = (document.getElementById('cfg-group').value.trim())       || '';
  const type        = (document.getElementById('cfg-type').value)               || 'maquina';
  const description = (document.getElementById('cfg-description').value.trim()) || '';

  // Require both local and group — otherwise device stays unconfigured
  const isConfigured = localName !== '' && groupName !== '';

  const cfg = loadConfig();
  cfg[id] = {
    displayName,
    localName  : isConfigured ? localName : '',
    groupName  : isConfigured ? groupName : '',
    type,
    description,
  };
  saveConfig(cfg);

  closeConfigModal();   // hide modal first
  buildDashboard();     // full re-render with updated config
  fetchAllDevices();    // repopulate data values

  // If still unconfigured, show a hint
  if (!isConfigured) {
    const box = document.getElementById('unconfigured-box');
    if (box) {
      const hint = box.querySelector('.unconfigured-hint') || document.createElement('p');
      hint.className = 'unconfigured-hint';
      hint.style.cssText = 'color:var(--amber);font-size:.72rem;padding:.5rem 1.25rem;margin:0';
      hint.textContent = '⚠ Completá Local y Grupo para asignar la máquina.';
      if (!hint.parentNode) box.insertBefore(hint, box.querySelector('.device-group'));
      setTimeout(() => hint.remove(), 3000);
    }
  }
}


/* ══════════════════════════════════════════════════
   FETCH: all known devices from DB
   Adds any unknown device_id to config as unconfigured
══════════════════════════════════════════════════ */
function syncUnknownDevices() {
  fetch('../devices/get_all_devices.php')
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(data => {
      if (data.error || !Array.isArray(data.devices)) return;

      const cfg = loadConfig();
      let changed = false;

      for (const { device_id } of data.devices) {
        if (!cfg[device_id]) {
          // Unknown device — add with no local assignment
          cfg[device_id] = {
            displayName : device_id,
            localName   : '',    // empty = unconfigured
            groupName   : '',
            type        : 'maquina',
            description : '',
          };
          changed = true;
        }
      }

      if (changed) {
        saveConfig(cfg);
        buildDashboard();   // re-render with the new unconfigured box
        fetchAllDevices();
      }
    })
    .catch(err => console.warn('[syncUnknownDevices]', err.message));
}

/* ══════════════════════════════════════════════════
   FETCH DEVICE DATA
══════════════════════════════════════════════════ */
function fetchAllDevices() {
  const cfg = loadConfig();
  for (const deviceId of Object.keys(cfg)) {
    fetchDevice(deviceId, cfg[deviceId]);
  }
}

function fetchDevice(deviceId, info) {
  if (!deviceId || String(deviceId).trim() === '') return;
  const typeDef = DEVICE_TYPES[(info && info.type) || 'maquina'];

  fetch('../devices/get_data.php?device_id=' + encodeURIComponent(deviceId))
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(data => {
      if (data.error) {
        setAllFieldsNA(deviceId, typeDef);
        setStatus(deviceId, 'offline');
        return;
      }
      for (const f of typeDef.fields) {
        const raw = data[f.dataKey];
        const val = (raw !== null && raw !== undefined && raw !== '') ? raw : '—';
        const el  = document.getElementById(fieldElId(deviceId, f.elSuffix));
        if (el) el.innerText = val;

        if (f.isBanco) {
          const card = document.querySelector(`[data-device-id="${deviceId}"]`);
          if (card) {
            const num = parseFloat(raw);
            card.classList.toggle('banco-alert', !isNaN(num) && num <= -10);
          }
        }
      }
      checkStatus(deviceId);
    })
    .catch(err => {
      console.warn(`[fetchDevice] ${deviceId}:`, err.message);
      setAllFieldsNA(deviceId, typeDef);
      setStatus(deviceId, 'offline');
    });
}

function setAllFieldsNA(deviceId, typeDef) {
  for (const f of typeDef.fields) {
    const el = document.getElementById(fieldElId(deviceId, f.elSuffix));
    if (el) el.innerText = '—';
  }
  const card = document.querySelector(`[data-device-id="${deviceId}"]`);
  if (card) card.classList.remove('banco-alert');
}

function checkStatus(deviceId) {
  fetch('check_status.php?device_id=' + encodeURIComponent(deviceId))
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(data => {
      setStatus(deviceId, (!data.error && data.status === 'online') ? 'online' : 'offline');
    })
    .catch(() => setStatus(deviceId, 'offline'));
}

function setStatus(deviceId, state) {
  const statusEl = document.getElementById(statusElId(deviceId));
  const badgeEl  = document.getElementById(badgeElId(deviceId));

  if (statusEl) statusEl.innerText = state === 'online' ? 'Online' : 'Offline';
  if (badgeEl)  badgeEl.classList.toggle('online', state === 'online');

  updateLocalCounters();
}

/* ══════════════════════════════════════════════════
   LOCAL COUNTERS
══════════════════════════════════════════════════ */
function updateLocalCounters() {
  const cfg = loadConfig();

  // aggregate by localName
  const counts = {}; // { localName: { online:0, total:0 } }
  for (const [id, info] of Object.entries(cfg)) {
    const ln = info.localName || '_unconfigured_';
    if (!counts[ln]) counts[ln] = { online: 0, total: 0 };
    counts[ln].total++;
    const el = document.getElementById(statusElId(id));
    if (el && el.innerText === 'Online') counts[ln].online++;
  }

  for (const [ln, c] of Object.entries(counts)) {
    const oEl = document.getElementById(`online-count__${safeId(ln)}`);
    const fEl = document.getElementById(`offline-count__${safeId(ln)}`);
    if (oEl) oEl.innerText = c.online;
    if (fEl) fEl.innerText = c.total - c.online;
  }
}

/* ══════════════════════════════════════════════════
   LEGACY COMPAT (report.js uses these)
══════════════════════════════════════════════════ */
function updateElementIfExists(id, value) {
  if (!id) return;
  const el = document.getElementById(id);
  if (el) el.innerText = value;
}

function setBancoValue(bancoId, value) {
  const el   = document.getElementById(bancoId);
  const card = el ? el.closest('.item') : null;
  if (!el || !card) return;
  el.innerText = value;
  const num = parseFloat(value);
  card.classList.toggle('banco-alert', !isNaN(num) && num <= -10);
}

/* ══════════════════════════════════════════════════
   KEYBOARD: close modal with Escape
══════════════════════════════════════════════════ */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeConfigModal();
});

/* ══════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  if (!document.getElementById('dashboard-root')) return;

  initConfig();       // seed localStorage from LOCALS_DEFAULT if first run
  buildDashboard();   // render all local boxes + cards
  fetchAllDevices();  // populate data values
  syncUnknownDevices(); // check DB for any new devices not yet configured

  // Refresh data every 60s; re-check DB for new devices every 5 min
  setInterval(fetchAllDevices, 60_000);
  setInterval(syncUnknownDevices, 5 * 60_000);
});


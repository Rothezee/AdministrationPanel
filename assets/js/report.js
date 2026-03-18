/**
 * report.js — Lógica unificada para todos los tipos de reportes (grúa, videojuego, ticketera, expendedora)
 */
const TIPO_CONFIG = {
    maquina: {
        tipoMaquina: 2,
        colsReporte: ['timestamp', 'dato1', 'dato2', 'dato3', 'dato4'],
        colsCierres: ['fecha', 'pesos', 'coin', 'premios', 'banco'],
        mapResponse: r => ({ timestamp: r.fecha_registro, dato1: r.pago, dato2: r.coin, dato3: r.premios, dato4: r.banco }),
        emptyCierre: () => ({ pesos: 0, coin: 0, premios: 0, banco: 0 }),
        calcCierre: (primer, ultimo, len) => len === 1
            ? { pesos: primer.dato1, coin: primer.dato2, premios: primer.dato3, banco: primer.dato4 }
            : { pesos: ultimo.dato1 - primer.dato1, coin: ultimo.dato2 - primer.dato2, premios: ultimo.dato3 - primer.dato3, banco: ultimo.dato4 - primer.dato4 },
        graphLabel: 'Coin'
    },
    videojuego: {
        tipoMaquina: 4,
        colsReporte: ['timestamp', 'dato2'],
        colsCierres: ['fecha', 'coin'],
        mapResponse: r => ({ timestamp: r.fecha_registro, dato2: r.fichas }),
        emptyCierre: () => ({ coin: 0 }),
        calcCierre: (primer, ultimo, len) => len === 1
            ? { coin: primer.dato2 }
            : { coin: ultimo.dato2 - primer.dato2 },
        graphLabel: 'Fichas'
    },
    ticket: {
        tipoMaquina: 3,
        colsReporte: ['timestamp', 'dato2', 'dato3'],
        colsCierres: ['fecha', 'coin', 'premios'],
        mapResponse: r => ({ timestamp: r.fecha_registro, dato2: r.fichas, dato3: r.tickets }),
        emptyCierre: () => ({ coin: 0, premios: 0 }),
        calcCierre: (primer, ultimo, len) => len === 1
            ? { coin: primer.dato2, premios: primer.dato3 }
            : { coin: ultimo.dato2 - primer.dato2, premios: ultimo.dato3 - primer.dato3 },
        graphLabel: 'Coin'
    },
    expendedora: {
        tipoMaquina: 1,
        colsReporte: ['timestamp', 'dato1', 'dato2'],
        colsCierres: ['fecha', 'fichas', 'dinero'],
        mapResponse: r => ({ timestamp: r.timestamp || r.fecha_registro, dato1: r.dato1, dato2: r.dato2 }),
        emptyCierre: () => ({ fichas: 0, dinero: 0, p1: 0, p2: 0, p3: 0, fichas_devolucion: 0, fichas_normales: 0, fichas_promocion: 0 }),
        calcCierre: (primer, ultimo, len) => len === 1
            ? { fichas: primer.dato1, dinero: primer.dato2 }
            : { fichas: ultimo.dato1 - primer.dato1, dinero: ultimo.dato2 - primer.dato2 },
        graphLabel: 'Fichas',
        /** La expendedora procesa los cierres en la máquina; se obtienen de endpoints específicos */
        cierresRemotos: true,
        colsSemanales: ['fecha', 'fichas', 'dinero', 'p1', 'p2', 'p3'],
        reportUrl: () => `../expendedora/get_report_expendedora.php`,
        cierresUrl: () => `../expendedora/get_close_expendedora.php`,
        subcierresUrl: () => `../expendedora/get_subcierre_expendedora.php`
    }
};

const datosCargados = { reportes: false, diarios: false, semanales: false, mensuales: false, graficas: false };
let deviceId, idDispositivo, tipoActual, configTipo;
let datosDiarios = [], datosSemanales = [], datosMensuales = [], allReports = [];

document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    deviceId = urlParams.get('device_id');
    tipoActual = (window.REPORT_CONFIG?.tipo) || urlParams.get('tipo') || urlParams.get('tipo_maquina') || 'maquina';
    configTipo = TIPO_CONFIG[tipoActual] || TIPO_CONFIG.maquina;
    idDispositivo = window.REPORT_CONFIG?.idDispositivo ?? null;

    const machineName = document.getElementById('machine_name');
    if (machineName) machineName.innerText = `${tipoActual.charAt(0).toUpperCase() + tipoActual.slice(1)} ${deviceId}`;

    if (!deviceId) {
        console.error("El device_id es requerido.");
        return;
    }

    document.querySelectorAll('.navbar-nav a').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href') || '';
            if (href.startsWith('#')) {
                e.preventDefault();
                mostrarSeccion(href.substring(1));
            }
            // Cerrar menú al seleccionar cualquier opción (móvil)
            document.querySelector('.navbar-menu')?.classList.remove('active');
        });
    });

    const style = document.createElement('style');
    style.textContent = '.table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }';
    document.head.appendChild(style);

    document.querySelectorAll('.table-container').forEach(container => {
        let isDown = false, startX, scrollLeft;
        container.addEventListener('mousedown', e => { isDown = true; startX = e.pageX - container.offsetLeft; scrollLeft = container.scrollLeft; });
        container.addEventListener('mouseleave', () => { isDown = false; });
        container.addEventListener('mouseup', () => { isDown = false; });
        container.addEventListener('mousemove', e => {
            if (!isDown) return;
            e.preventDefault();
            container.scrollLeft = scrollLeft - (e.pageX - container.offsetLeft - startX) * 2;
        });
    });

    initFlatpickr();
    mostrarSeccion('reportes');
});

function cargarReportes() {
    if (datosCargados.reportes) return Promise.resolve();

    if (configTipo.cierresRemotos) {
        return fetch(`${configTipo.reportUrl()}?device_id=${encodeURIComponent(deviceId)}`)
            .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
            .then(data => {
                if (data.error) { console.error(data.error); return; }
                const reports = (data.reports || []).map(r => ({ timestamp: r.timestamp, dato1: r.dato1, dato2: r.dato2 }));
                allReports = reports;
                const reversed = [...reports].reverse();
                cargarTabla('report_table', reversed, configTipo.colsReporte);
                datosCargados.reportes = true;
            })
            .catch(e => console.error('Error al obtener reportes:', e));
    }

    if (!idDispositivo) {
        console.warn('No se encontró id_dispositivo.');
        return Promise.resolve();
    }

    return fetch(`../devices/get_reports.php?id_dispositivo=${encodeURIComponent(idDispositivo)}&tipo_maquina=${configTipo.tipoMaquina}`)
        .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
        .then(data => {
            if (!data.success) { console.error(data.error); return; }
            allReports = (data.reports || []).map(configTipo.mapResponse);
            const reversed = [...allReports].reverse();
            cargarTabla('report_table', reversed, configTipo.colsReporte);
            datosCargados.reportes = true;
        })
        .catch(e => console.error('Error al obtener reportes:', e));
}

function calcularCierresDiarios(reports) {
    const cierresPorDia = {};
    reports.forEach(report => {
        const fecha = (report.timestamp || '').split(" ")[0];
        if (!fecha) return;
        if (!cierresPorDia[fecha]) cierresPorDia[fecha] = [];
        cierresPorDia[fecha].push(report);
    });

    datosDiarios = Object.entries(cierresPorDia).map(([fecha, list]) => {
        list.sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
        const primer = list[0];
        const ultimo = list[list.length - 1];
        const cierre = configTipo.calcCierre(primer, ultimo, list.length);
        return { fecha, ...cierre };
    });
    datosDiarios.sort((a, b) => new Date(b.fecha) - new Date(a.fecha));
}

function cargarCierresDiarios() {
    if (datosCargados.diarios) return Promise.resolve();

    if (configTipo.cierresRemotos) {
        const idExp = deviceId;
        const fetchCierres = fetch(`${configTipo.cierresUrl()}?id_expendedora=${encodeURIComponent(idExp)}`).then(r => r.json());
        const fetchSubcierres = fetch(`${configTipo.subcierresUrl()}?id_expendedora=${encodeURIComponent(idExp)}`).then(r => r.json());
        return Promise.all([fetchCierres, fetchSubcierres])
            .then(([cierresData, subcierresData]) => {
                const cierres = (cierresData.reports && Array.isArray(cierresData.reports)) ? cierresData.reports : [];
                const subcierres = (subcierresData?.partial_reports && Array.isArray(subcierresData.partial_reports))
                    ? subcierresData.partial_reports
                    : (subcierresData?.reports && Array.isArray(subcierresData.reports)) ? subcierresData.reports : [];
                datosDiarios = cierres.map(c => ({
                    fecha: c.fecha_dia || (c.timestamp || '').split(' ')[0],
                    fichas: c.fichas, dinero: c.dinero,
                    p1: c.p1, p2: c.p2, p3: c.p3,
                    fichas_devolucion: c.fichas_devolucion, fichas_normales: c.fichas_normales, fichas_promocion: c.fichas_promocion
                }));
                fusionarYRenderizarCierresExpendedora(cierres, subcierres);
                datosCargados.diarios = true;
            })
            .catch(e => console.error('Error al obtener cierres:', e));
    }

    if (!allReports.length) {
        return cargarReportes().then(() => {
            if (allReports.length > 0) {
                calcularCierresDiarios(allReports);
                cargarTabla('tabla-diarios', datosDiarios, configTipo.colsCierres);
            }
            datosCargados.diarios = true;
        });
    }

    calcularCierresDiarios(allReports);
    cargarTabla('tabla-diarios', datosDiarios, configTipo.colsCierres);
    datosCargados.diarios = true;
    return Promise.resolve();
}

function fusionarYRenderizarCierresExpendedora(cierres, subcierres) {
    const tbody = document.querySelector('#tabla-diarios tbody');
    if (!tbody) return;
    tbody.innerHTML = '';

    const datosAgrupados = {};
    cierres.forEach(cierre => {
        if (cierre) {
            const fecha = cierre.fecha_dia || (typeof cierre.timestamp === 'string' ? cierre.timestamp.split(' ')[0] : null);
            if (fecha) {
                if (!datosAgrupados[fecha]) datosAgrupados[fecha] = { cierre: null, subcierres: [] };
                datosAgrupados[fecha].cierre = cierre;
            }
        }
    });
    subcierres.forEach(sub => {
        // Agrupar por día que inició el turno (fecha_dia o fecha_apertura_turno), nunca por día que terminó (created_at)
        const fecha = sub.fecha_dia || (typeof sub.fecha_apertura_turno === 'string' ? sub.fecha_apertura_turno.split(' ')[0] : null) || (typeof (sub.created_at || sub.timestamp || sub.fecha) === 'string' ? (sub.created_at || sub.timestamp || sub.fecha).split(' ')[0] : null);
        if (fecha) {
            if (!datosAgrupados[fecha]) datosAgrupados[fecha] = { cierre: null, subcierres: [] };
            datosAgrupados[fecha].subcierres.push(sub);
        }
    });

    const fechasOrdenadas = Object.keys(datosAgrupados).sort((a, b) => new Date(b) - new Date(a));

    fechasOrdenadas.forEach(fecha => {
        const { cierre, subcierres: subDelDia } = datosAgrupados[fecha];
        const cierreData = cierre || { timestamp: `${fecha} (Sin cierre)`, fichas: 0, dinero: 0, p1: 0, p2: 0, p3: 0, fichas_devolucion: 0, fichas_normales: 0, fichas_promocion: 0 };
        const getVal = (obj, key) => obj[`partial_${key}`] !== undefined ? obj[`partial_${key}`] : (obj[key] !== undefined ? obj[key] : 0);

        const trCierre = document.createElement('tr');
        const btnCell = document.createElement('td');
        const btn = document.createElement('button');
        btn.textContent = 'Extender';
        btn.onclick = () => toggleParcialesExpendedora(fecha);
        btnCell.appendChild(btn);

        ['timestamp', 'fichas', 'dinero', 'p1', 'p2', 'p3', 'fichas_devolucion', 'fichas_normales', 'fichas_promocion'].forEach(k => {
            const td = document.createElement('td');
            td.textContent = cierreData[k] ?? '—';
            trCierre.appendChild(td);
        });
        trCierre.appendChild(btnCell);
        tbody.appendChild(trCierre);

        const trParciales = document.createElement('tr');
        trParciales.id = `parciales-${fecha}`;
        trParciales.style.display = 'none';
        const cellParciales = document.createElement('td');
        cellParciales.colSpan = 10;
        const container = document.createElement('div');
        container.className = 'table-container';
        const subTable = document.createElement('table');
        const subHead = document.createElement('thead');
        subHead.innerHTML = '<tr><th>Fecha</th><th>Fichas</th><th>Dinero</th><th>P1</th><th>P2</th><th>P3</th><th>Devolución</th><th>Normales</th><th>Promoción</th><th>Empleado</th></tr>';
        const subBody = document.createElement('tbody');
        subBody.id = `subcierres-${fecha}`;

        if (subDelDia.length > 0) {
            subDelDia.sort((a, b) => new Date(a.created_at || a.timestamp || a.fecha) - new Date(b.created_at || b.timestamp || b.fecha));
            subDelDia.forEach(parcial => {
                const tr = document.createElement('tr');
                const vals = [
                    parcial.created_at || parcial.timestamp || parcial.fecha,
                    getVal(parcial, 'fichas'), getVal(parcial, 'dinero'),
                    getVal(parcial, 'p1'), getVal(parcial, 'p2'), getVal(parcial, 'p3'),
                    getVal(parcial, 'devolucion'), getVal(parcial, 'normales'), getVal(parcial, 'promocion'),
                    parcial.employee_id || parcial.empleado || '—'
                ];
                vals.forEach(v => { const td = document.createElement('td'); td.textContent = v ?? '—'; tr.appendChild(td); });
                subBody.appendChild(tr);
            });
        } else {
            btn.disabled = true;
            btn.textContent = 'No hay';
        }

        subTable.appendChild(subHead);
        subTable.appendChild(subBody);
        container.appendChild(subTable);
        cellParciales.appendChild(container);
        trParciales.appendChild(cellParciales);
        tbody.appendChild(trParciales);
    });
}

function toggleParcialesExpendedora(fecha) {
    const fila = document.getElementById(`parciales-${fecha}`);
    if (fila) fila.style.display = fila.style.display === 'none' ? 'table-row' : 'none';
}

function calcularCierreSemanalDesdeFecha(fechaInicio) {
    datosSemanales = [];
    if (!datosDiarios.length) return;

    let fecha = new Date(fechaInicio);
    fecha.setHours(0, 0, 0, 0);
    const ultimaFecha = new Date(datosDiarios[datosDiarios.length - 1].fecha);
    const empty = configTipo.emptyCierre();

    while (fecha <= ultimaFecha || datosSemanales.length === 0) {
        const semana = [];
        for (let i = 0; i < 7; i++) {
            const fechaDia = new Date(fecha.getTime() + i * 24 * 60 * 60 * 1000);
            const fechaStr = fechaDia.toISOString().split('T')[0];
            const cierre = datosDiarios.find(d => d.fecha === fechaStr);
            semana.push(cierre ? { fecha: fechaStr, ...cierre } : { fecha: fechaStr, ...empty });
        }

        const hasData = Object.keys(empty).some(k => semana.some(d => (d[k] || 0) !== 0));
        if (hasData) {
            const total = semana.reduce((acc, dia) => {
                Object.keys(empty).forEach(k => { acc[k] = (acc[k] || 0) + (dia[k] || 0); });
                return acc;
            }, { fecha: `Semana del ${fecha.toLocaleDateString()}`, ...empty });
            datosSemanales.push(total);
        }
        fecha = new Date(fecha.getTime() + 7 * 24 * 60 * 60 * 1000);
    }
    const colsSem = configTipo.colsSemanales || ['fecha', ...Object.keys(empty)];
    cargarTabla('tabla-semanales', datosSemanales, colsSem);
}

function calcularCierreMensual(fechaInicio) {
    datosMensuales = [];
    const inicioMes = new Date(fechaInicio.getFullYear(), fechaInicio.getMonth(), 1, 0, 0, 0);
    const finMes = new Date(fechaInicio.getFullYear(), fechaInicio.getMonth() + 1, 0, 23, 59, 59);
    const diasDelMes = datosDiarios.filter(d => {
        const d2 = new Date(d.fecha);
        return d2 >= inicioMes && d2 <= finMes;
    });

    if (diasDelMes.length === 0) return;

    diasDelMes.sort((a, b) => new Date(a.fecha) - new Date(b.fecha));
    const primer = diasDelMes[0];
    const ultimo = diasDelMes[diasDelMes.length - 1];
    const keys = Object.keys(configTipo.emptyCierre());
    const cierre = {};
    keys.forEach(k => { cierre[k] = (ultimo[k] || 0) - (primer[k] || 0); });
    datosMensuales.push({ fecha: `${inicioMes.toLocaleDateString()} - ${finMes.toLocaleDateString()}`, ...cierre });
    const colsMes = configTipo.colsSemanales || ['fecha', ...keys];
    cargarTabla('tabla-mensuales', datosMensuales, colsMes);
}

function cargarTabla(idTabla, datos, columnas) {
    const table = document.getElementById(idTabla);
    const tbody = table ? table.querySelector("tbody") : null;
    if (!tbody) {
        console.warn(`No se encontró tbody para ${idTabla}`);
        return;
    }
    tbody.innerHTML = "";
    datos.forEach(fila => {
        const tr = document.createElement("tr");
        columnas.forEach(col => {
            const td = document.createElement("td");
            td.textContent = fila[col] ?? '—';
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
}

function initFlatpickr() {
    const selSemana = document.getElementById("selector-inicio-semana");
    const selMes = document.getElementById("selector-inicio-mes");
    if (selSemana && typeof flatpickr !== 'undefined') {
        flatpickr(selSemana, {
            dateFormat: "Y-m-d",
            onClose: function(selectedDates) {
                if (selectedDates.length === 1) calcularCierreSemanalDesdeFecha(selectedDates[0]);
            }
        });
    }
    if (selMes && typeof flatpickr !== 'undefined' && typeof monthSelectPlugin !== 'undefined') {
        flatpickr(selMes, {
            dateFormat: "Y-m",
            altInput: true,
            altFormat: "F Y",
            plugins: [new monthSelectPlugin({ shorthand: true, dateFormat: "Y-m", altFormat: "F Y" })],
            onClose: function(selectedDates) {
                if (selectedDates.length === 1) {
                    calcularCierreMensual(selectedDates[0]);
                    cargarTabla('tabla-mensuales', datosMensuales, ['fecha', ...Object.keys(configTipo.emptyCierre())]);
                }
            }
        });
    }
}

function mostrarSeccion(seccionId) {
    document.querySelectorAll(".seccion").forEach(s => s.classList.remove("active"));
    const el = document.getElementById(seccionId);
    if (el) el.classList.add("active");

    if (seccionId === 'reportes') cargarReportes();
    else if (seccionId === 'diarios') cargarCierresDiarios();
    else if ((seccionId === 'semanales' || seccionId === 'mensuales') && configTipo.cierresRemotos && !datosCargados.diarios) {
        cargarCierresDiarios();
    }
    else if (seccionId === 'graficas' && !graficasCargadas.comparativa) {
        const p = cargarCierresDiarios();
        (p && p.then ? p : Promise.resolve()).then(() => {
            generarGraficaDiarias(datosDiarios);
            generarGraficaSemanales(datosSemanales);
            generarGraficaMensuales(datosMensuales);
            generarGraficaComparativa(datosMensuales);
            graficasCargadas.comparativa = true;
        });
    }
}

let graficasCargadas = { diarios: false, semanales: false, mensuales: false, comparativa: false };

function getGraphKeys() {
    const cols = configTipo?.colsCierres || [];
    const primary = cols.includes('coin') ? 'coin' : cols.includes('fichas') ? 'fichas' : cols[1] || 'coin';
    const secondary = cols.includes('premios') ? 'premios' : cols.includes('dinero') ? 'dinero' : cols[2];
    return { primary, secondary };
}

function generarGraficaDiarias(datos) {
    const ctx = document.getElementById('grafica-ganancias-diarias')?.getContext('2d');
    if (!ctx || !datos.length) return;
    const { primary, secondary } = getGraphKeys();
    const datasets = [
        { label: (primary === 'coin' ? 'Coin' : configTipo.graphLabel) + ' diario', data: datos.map(d => d[primary] || 0), backgroundColor: 'rgba(75, 192, 192, 0.2)', borderColor: 'rgba(75, 192, 192, 1)', borderWidth: 1 }
    ];
    if (secondary && secondary === 'premios' && (datos[0] || {})[secondary] !== undefined) {
        datasets.push({ label: 'Premios', data: datos.map(d => d[secondary] || 0), backgroundColor: 'rgba(255, 99, 132, 0.2)', borderColor: 'rgba(255, 99, 132, 1)', borderWidth: 1 });
    }
    new Chart(ctx, {
        type: 'bar',
        data: { labels: datos.map(d => d.fecha), datasets },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
}

function generarGraficaSemanales(datos) {
    const ctx = document.getElementById('grafica-ganancias-semanales')?.getContext('2d');
    if (!ctx || !datos.length) return;
    const { primary, secondary } = getGraphKeys();
    const datasets = [
        { label: (primary === 'coin' ? 'Coin' : configTipo.graphLabel) + ' semanal', data: datos.map(d => d[primary] || 0), borderColor: 'rgba(153, 102, 255, 1)', fill: false }
    ];
    if (secondary && secondary === 'premios' && (datos[0] || {})[secondary] !== undefined) {
        datasets.push({ label: 'Premios', data: datos.map(d => d[secondary] || 0), borderColor: 'rgba(255, 99, 132, 1)', fill: false });
    }
    new Chart(ctx, {
        type: 'line',
        data: { labels: datos.map(d => d.fecha), datasets },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
}

function generarGraficaMensuales(datos) {
    const ctx = document.getElementById('grafica-ganancias-mensuales')?.getContext('2d');
    if (!ctx || !datos.length) return;
    const { primary, secondary } = getGraphKeys();
    const datasets = [
        { label: (primary === 'coin' ? 'Coin' : configTipo.graphLabel) + ' mensual', data: datos.map(d => d[primary] || 0), backgroundColor: 'rgba(255, 206, 86, 0.2)', borderColor: 'rgba(255, 206, 86, 1)', borderWidth: 1 }
    ];
    if (secondary && secondary === 'premios' && (datos[0] || {})[secondary] !== undefined) {
        datasets.push({ label: 'Premios', data: datos.map(d => d[secondary] || 0), backgroundColor: 'rgba(255, 99, 132, 0.2)', borderColor: 'rgba(255, 99, 132, 1)', borderWidth: 1 });
    }
    new Chart(ctx, {
        type: 'bar',
        data: { labels: datos.map(d => d.fecha), datasets },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
}

function generarGraficaComparativa(datos) {
    const ctx = document.getElementById('grafica-comparativa')?.getContext('2d');
    if (!ctx || !datos.length) return;
    const { primary, secondary } = getGraphKeys();
    const hasSecondary = secondary && (datos[0] || {})[secondary] !== undefined;
    const datasets = [
        { label: primary === 'coin' ? 'Coin' : primary, data: datos.map(d => d[primary] || 0), borderColor: 'rgba(75, 192, 192, 1)', fill: false }
    ];
    if (hasSecondary) datasets.push({ label: secondary === 'premios' ? 'Premios' : secondary, data: datos.map(d => d[secondary] || 0), borderColor: 'rgba(255, 99, 132, 1)', fill: false });
    new Chart(ctx, {
        type: 'line',
        data: { labels: datos.map(d => d.fecha), datasets },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
}

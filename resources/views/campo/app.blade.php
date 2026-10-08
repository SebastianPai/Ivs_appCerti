<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#dc2626">
    <title>Inspección sin conexión · IVS</title>
    <link rel="manifest" href="/manifest-campo.webmanifest">
    <link rel="icon" href="/images/logo-sm.png">
    <link rel="apple-touch-icon" href="/images/icono-192.png">
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f4f5; --card: #ffffff; --text: #18181b; --muted: #71717a; --line: #e4e4e7;
            --primary: #dc2626; --primary-ink: #ffffff; --ok: #15803d; --ok-bg: #dcfce7; --warn: #a16207; --warn-bg: #fef9c3;
            --bad: #b91c1c; --bad-bg: #fee2e2; --info: #1d4ed8; --info-bg: #dbeafe; --chip: #f4f4f5;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --bg: #09090b; --card: #18181b; --text: #fafafa; --muted: #a1a1aa; --line: #27272a;
                --primary: #ef4444; --ok: #4ade80; --ok-bg: #14532d; --warn: #facc15; --warn-bg: #422006;
                --bad: #f87171; --bad-bg: #450a0a; --info: #93c5fd; --info-bg: #172554; --chip: #27272a;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; -webkit-text-size-adjust: 100%; }
        header { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 10px; padding: 10px 16px; padding-top: max(10px, env(safe-area-inset-top)); background: var(--card); border-bottom: 1px solid var(--line); }
        header img { width: 32px; height: 34px; }
        header .titulo { flex: 1; min-width: 0; }
        header .titulo strong { display: block; font-size: 15px; }
        header .titulo span { display: block; font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        main { max-width: 760px; margin: 0 auto; padding: 16px 16px 120px; }
        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .pill::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .pill.ok { background: var(--ok-bg); color: var(--ok); }
        .pill.off { background: var(--warn-bg); color: var(--warn); }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 16px; margin-bottom: 14px; }
        .card h2 { margin: 0 0 4px; font-size: 17px; }
        .card h3 { margin: 0 0 10px; font-size: 15px; display: flex; align-items: center; gap: 8px; }
        .muted { color: var(--muted); font-size: 14px; }
        .small { font-size: 13px; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .grow { flex: 1; min-width: 0; }
        button, .btn { appearance: none; border: 1px solid var(--line); background: var(--card); color: var(--text); border-radius: 10px; padding: 11px 14px; font: inherit; font-size: 15px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; min-height: 44px; }
        button.primary, .btn.primary { background: var(--primary); border-color: var(--primary); color: var(--primary-ink); }
        button:disabled { opacity: .5; cursor: not-allowed; }
        button.block, .btn.block { width: 100%; }
        .lista { display: grid; gap: 12px; }
        .solicitud { display: block; width: 100%; text-align: left; font-weight: normal; padding: 14px; border-radius: 14px; }
        .solicitud .placa { font-size: 20px; font-weight: 800; letter-spacing: .5px; }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .chip { font-size: 12px; padding: 2px 8px; border-radius: 999px; background: var(--chip); color: var(--muted); font-weight: 600; }
        .chip.ok { background: var(--ok-bg); color: var(--ok); }
        .chip.warn { background: var(--warn-bg); color: var(--warn); }
        .chip.bad { background: var(--bad-bg); color: var(--bad); }
        .chip.info { background: var(--info-bg); color: var(--info); }
        .aviso { border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; font-size: 14px; }
        .aviso.warn { background: var(--warn-bg); color: var(--warn); }
        .aviso.bad { background: var(--bad-bg); color: var(--bad); }
        .aviso.ok { background: var(--ok-bg); color: var(--ok); }
        .aviso.info { background: var(--info-bg); color: var(--info); }
        .aviso a { color: inherit; font-weight: 700; }
        label.check { display: flex; gap: 10px; align-items: flex-start; font-size: 15px; }
        label.check input { width: 22px; height: 22px; margin-top: 1px; accent-color: var(--primary); }
        input[type=text], input[type=number], textarea { width: 100%; font: inherit; font-size: 16px; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--line); background: var(--card); color: var(--text); }
        textarea { min-height: 90px; resize: vertical; }
        .item { padding: 12px 0; border-top: 1px solid var(--line); }
        .item:first-of-type { border-top: 0; padding-top: 0; }
        .numeral { display: inline-block; font-size: 11px; font-weight: 700; padding: 1px 6px; border-radius: 6px; background: var(--info-bg); color: var(--info); margin-right: 4px; }
        .seg { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-top: 8px; }
        .seg.dos { grid-template-columns: repeat(2, 1fr); }
        .seg button { padding: 9px 6px; font-size: 14px; min-height: 42px; }
        .seg button[aria-pressed=true][data-v=c], .seg button[aria-pressed=true][data-v=si] { background: var(--ok-bg); color: var(--ok); border-color: var(--ok); }
        .seg button[aria-pressed=true][data-v=nc] { background: var(--bad-bg); color: var(--bad); border-color: var(--bad); }
        .seg button[aria-pressed=true][data-v=na], .seg button[aria-pressed=true][data-v=no] { background: var(--chip); color: var(--text); border-color: var(--muted); }
        .medicion { margin-top: 8px; display: flex; gap: 8px; align-items: center; }
        .medicion input { max-width: 160px; }
        .error { color: var(--bad); font-size: 13px; margin-top: 4px; }
        .fotos { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .foto { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
        .foto .img { aspect-ratio: 4/3; background: var(--chip); display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 13px; text-align: center; padding: 6px; }
        .foto img { width: 100%; height: 100%; object-fit: cover; }
        .foto .pie { padding: 8px; font-size: 13px; font-weight: 600; }
        .foto label.btn { margin: 0 8px 8px; padding: 8px; min-height: 40px; font-size: 14px; }
        .foto input[type=file] { display: none; }
        .ubicaciones { display: grid; gap: 6px; margin-top: 8px; }
        .barra { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10; background: var(--card); border-top: 1px solid var(--line); padding: 10px 16px; padding-bottom: max(10px, env(safe-area-inset-bottom)); }
        .barra .dentro { max-width: 760px; margin: 0 auto; display: flex; gap: 10px; }
        .barra .dentro > * { flex: 1; }
        .volver { margin-bottom: 12px; padding: 8px 12px; min-height: 40px; }
        .progreso { height: 6px; border-radius: 99px; background: var(--chip); overflow: hidden; margin-top: 10px; }
        .progreso > div { height: 100%; background: var(--primary); }
        .oculto { display: none !important; }
        @media (min-width: 640px) { .fotos { grid-template-columns: repeat(3, 1fr); } }
    </style>
</head>
<body>
    <header>
        <img src="/images/logo-sm.png" alt="">
        <div class="titulo">
            <strong>Inspección sin conexión</strong>
            <span>{{ $user->name }}</span>
        </div>
        <span id="conexion" class="pill ok">En línea</span>
    </header>

    <main id="app" aria-live="polite">
        <div class="card"><p class="muted">Cargando…</p></div>
    </main>

    <div id="barra" class="barra oculto"><div class="dentro"></div></div>

    <script>
        window.IVS_CAMPO = {{ Js::from($config) }};
    </script>
    @verbatim
    <script>
    (() => {
        'use strict';

        const CFG = window.IVS_CAMPO;
        const app = document.getElementById('app');
        const barra = document.getElementById('barra');
        let csrf = document.querySelector('meta[name=csrf-token]').content;
        let def = null;          // definición del checklist y lista de fotos (llega con la descarga)
        let vista = { tipo: 'inicio' };
        let mensaje = null;      // { tipo: 'ok'|'warn'|'bad'|'info', html }
        const urlsFotos = new Map();

        // ------------------------------------------------------------------
        // Almacenamiento local (IndexedDB). Una base por usuario: si otra persona
        // entra en el mismo celular no ve las inspecciones de la anterior.
        // ------------------------------------------------------------------
        const db = (() => {
            let conexion;
            const abrir = () => conexion ??= new Promise((ok, mal) => {
                const r = indexedDB.open('ivs-campo-u' + CFG.usuario, 1);
                r.onupgradeneeded = () => {
                    const d = r.result;
                    d.createObjectStore('kv');
                    d.createObjectStore('trabajos', { keyPath: 'id' });
                    d.createObjectStore('fotos');
                };
                r.onsuccess = () => ok(r.result);
                r.onerror = () => mal(r.error);
            });
            const tx = async (store, modo, fn) => {
                const d = await abrir();
                return new Promise((ok, mal) => {
                    const t = d.transaction(store, modo);
                    const res = fn(t.objectStore(store));
                    t.oncomplete = () => ok(res?.result);
                    t.onerror = () => mal(t.error);
                });
            };
            return {
                get: (s, k) => tx(s, 'readonly', (o) => o.get(k)),
                put: (s, v, k) => tx(s, 'readwrite', (o) => k === undefined ? o.put(v) : o.put(v, k)),
                del: (s, k) => tx(s, 'readwrite', (o) => o.delete(k)),
                todos: (s) => tx(s, 'readonly', (o) => o.getAll()),
            };
        })();

        const h = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const enLinea = () => navigator.onLine;
        const fecha = (iso) => iso ? new Date(iso).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' }) : '—';
        const claveFoto = (id, nombre) => id + ':' + nombre;

        // ------------------------------------------------------------------
        // Descarga de solicitudes
        // ------------------------------------------------------------------
        async function descargar() {
            const r = await fetch(CFG.datos, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (r.status === 401 || r.redirected) throw new Error('sesion');
            if (! r.ok) throw new Error((await r.json().catch(() => ({}))).message || 'No se pudo descargar (' + r.status + ')');
            const datos = await r.json();
            csrf = datos.csrf;

            def = { secciones: datos.secciones, ubicaciones: datos.ubicaciones, fotos_obligatorias: datos.fotos_obligatorias, chip_obligatorio: datos.chip_obligatorio };
            await db.put('kv', def, 'definicion');
            await db.put('kv', datos.generado, 'ultima_descarga');

            const locales = new Map((await db.todos('trabajos')).map((t) => [t.id, t]));
            const ids = new Set();

            for (const s of datos.solicitudes) {
                ids.add(s.id);
                const t = locales.get(s.id);
                if (t && t.estado !== 'sincronizada') {
                    t.solicitud = s;
                    t.fuera_de_lista = false;
                    await db.put('trabajos', t);
                } else {
                    // Nueva (o ya sincronizada antes): se parte de lo que hay en el servidor
                    await db.put('trabajos', {
                        id: s.id,
                        solicitud: s,
                        declaracion: s.guardado.declaracion,
                        gps: null,
                        chip: s.guardado.chip || '',
                        observaciones: s.guardado.observaciones || '',
                        checklist: Array.isArray(s.guardado.checklist) ? {} : (s.guardado.checklist || {}),
                        fotos: [],
                        estado: 'borrador',
                        cambios: false,
                    });
                }
            }

            for (const [id, t] of locales) {
                if (ids.has(id)) continue;
                if (t.estado === 'sincronizada' || ! t.cambios) {
                    await borrarTrabajo(id);
                } else {
                    t.fuera_de_lista = true;
                    await db.put('trabajos', t);
                }
            }

            return datos.solicitudes.length;
        }

        async function borrarTrabajo(id) {
            const t = await db.get('trabajos', id);
            for (const f of t?.fotos ?? []) await db.del('fotos', claveFoto(id, f.nombre));
            await db.del('trabajos', id);
        }

        // ------------------------------------------------------------------
        // Progreso de una inspección
        // ------------------------------------------------------------------
        function progreso(t) {
            const items = def ? def.secciones.flatMap((s) => s.items) : [];
            const cil = t.solicitud.cilindros || [];
            const vc = t.checklist.verificacion_cilindros || [];
            const cilOk = cil.filter((serie) => {
                const c = vc.find((x) => x.numero_serie === serie);
                return c && c.ultra_liviano && (c.ubicacion || []).length;
            }).length;
            const respondidos = items.filter((i) => ['c', 'nc', 'na'].includes(t.checklist[i.id])).length + cilOk;
            const total = items.length + cil.length;
            const tieneFoto = (n) => t.fotos.some((f) => f.nombre === n) || (t.solicitud.guardado.fotos || []).includes(n);
            const obligatorias = def ? def.fotos_obligatorias : [];

            return {
                filtro: !! t.declaracion && !! t.gps,
                chip: !! t.chip,
                fotos: obligatorias.filter(tieneFoto).length,
                fotosTotal: obligatorias.length,
                respondidos,
                total,
                tieneFoto,
            };
        }

        // ------------------------------------------------------------------
        // Pantallas
        // ------------------------------------------------------------------
        async function pintar() {
            def ??= await db.get('kv', 'definicion');
            barra.classList.add('oculto');

            if (vista.tipo === 'detalle') {
                const t = await db.get('trabajos', vista.id);
                if (t) return pintarDetalle(t);
                vista = { tipo: 'inicio' };
            }

            return pintarInicio();
        }

        function bloqueMensaje() {
            return mensaje ? `<div class="aviso ${mensaje.tipo}">${mensaje.html}</div>` : '';
        }

        async function pintarInicio() {
            const trabajos = (await db.todos('trabajos')).sort((a, b) => a.solicitud.placa.localeCompare(b.solicitud.placa));
            const ultima = await db.get('kv', 'ultima_descarga');
            const enCola = trabajos.filter((t) => t.estado === 'en_cola').length;

            app.innerHTML = `
                ${bloqueMensaje()}
                <div class="card">
                    <h2>Sus inspecciones</h2>
                    <p class="muted">Descargue las solicitudes con señal. En el taller, diligencie todo aunque no haya internet:
                    queda guardado en este celular y se envía al volver la señal.</p>
                    <p class="muted small">Última descarga: ${h(ultima ? fecha(ultima) : 'nunca')}</p>
                    <div class="row">
                        <button class="primary grow" data-accion="descargar" ${enLinea() ? '' : 'disabled'}>⬇ Descargar solicitudes</button>
                        ${enCola ? `<button class="grow" data-accion="sincronizar-todo" ${enLinea() ? '' : 'disabled'}>⬆ Enviar ${enCola} pendiente(s)</button>` : ''}
                    </div>
                    ${enLinea() ? '' : '<p class="muted small" style="margin-bottom:0">Sin señal: puede seguir trabajando con lo descargado.</p>'}
                </div>
                <div class="lista">
                    ${trabajos.length ? trabajos.map(tarjeta).join('') : '<div class="card"><p class="muted" style="margin:0">No hay solicitudes descargadas en este celular.</p></div>'}
                </div>
                <p class="muted small" style="text-align:center;margin-top:24px">
                    <a href="${h(CFG.panel)}" style="color:inherit">Volver al panel</a>
                </p>`;
        }

        function tarjeta(t) {
            const p = progreso(t);
            const s = t.solicitud;
            const estado = {
                borrador: t.cambios ? '<span class="chip warn">Sin enviar</span>' : '<span class="chip">Sin empezar</span>',
                en_cola: '<span class="chip info">Se envía al volver la señal</span>',
                sincronizada: '<span class="chip ok">Enviada ✓</span>',
                conflicto: '<span class="chip bad">No se pudo aplicar</span>',
            }[t.estado] || '';

            return `
                <button class="card solicitud" data-abrir="${t.id}">
                    <div class="row"><span class="placa grow">${h(s.placa)}</span>${estado}</div>
                    <div class="muted">${h(s.vehiculo || 'Vehículo')} · ${h(s.taller)}</div>
                    <div class="muted small">${h(s.ciudad)}${s.direccion ? ' · ' + h(s.direccion) : ''}</div>
                    ${t.fuera_de_lista ? '<div class="chip bad" style="margin-top:8px">Ya no aparece en su bandeja</div>' : ''}
                    <div class="chips">
                        <span class="chip ${p.filtro ? 'ok' : ''}">${p.filtro ? '✓' : '○'} Declaración y GPS</span>
                        <span class="chip ${p.chip ? 'ok' : ''}">${p.chip ? '✓' : '○'} Chip</span>
                        <span class="chip ${p.fotos === p.fotosTotal ? 'ok' : ''}">Fotos ${p.fotos}/${p.fotosTotal}</span>
                        <span class="chip ${p.total && p.respondidos === p.total ? 'ok' : ''}">Checklist ${p.respondidos}/${p.total}</span>
                    </div>
                </button>`;
        }

        function pintarDetalle(t) {
            const s = t.solicitud;
            const p = progreso(t);
            const soloLectura = t.estado === 'sincronizada';
            const dis = soloLectura ? 'disabled' : '';

            const gps = t.gps
                ? `<p class="small" style="margin:8px 0 0">📍 ${t.gps.lat.toFixed(6)}, ${t.gps.lng.toFixed(6)} · ±${Math.round(t.gps.precision ?? 0)} m · ${h(fecha(t.gps.capturado_en))}</p>
                   ${t.gps.precision > 100 ? '<p class="error">Precisión baja. Si puede, salga a cielo abierto y capture de nuevo.</p>' : ''}`
                : '<p class="muted small" style="margin:8px 0 0">Funciona sin internet: usa el GPS del celular.</p>';

            const fotos = (def?.fotos_obligatorias || []).map((n) => fotoHtml(t, n, true, dis)).join('')
                + t.fotos.filter((f) => ! (def?.fotos_obligatorias || []).includes(f.nombre)).map((f) => fotoHtml(t, f.nombre, false, dis)).join('');

            const cilindros = (s.cilindros || []).map((serie) => {
                const c = (t.checklist.verificacion_cilindros || []).find((x) => x.numero_serie === serie) || {};
                return `
                    <div class="item">
                        <strong>Cilindro serie ${h(serie)}</strong>
                        <div class="muted small" style="margin-top:6px">¿Es ultraliviano?</div>
                        <div class="seg dos">
                            ${['si', 'no'].map((v) => `<button type="button" ${dis} data-cil="${h(serie)}" data-campo="ultra_liviano" data-v="${v}" aria-pressed="${c.ultra_liviano === v}">${v === 'si' ? 'Sí' : 'No'}</button>`).join('')}
                        </div>
                        <div class="muted small" style="margin-top:8px">Ubicación</div>
                        <div class="ubicaciones">
                            ${Object.entries(def?.ubicaciones || {}).map(([k, txt]) => `
                                <label class="check small"><input type="checkbox" ${dis} data-cil="${h(serie)}" data-ubic="${k}" ${(c.ubicacion || []).includes(k) ? 'checked' : ''}> ${h(txt)}</label>`).join('')}
                        </div>
                    </div>`;
            }).join('');

            const secciones = (def?.secciones || []).map((sec) => `
                <div class="card">
                    <h3>${h(sec.titulo)} <span class="muted small" style="font-weight:normal">${h(sec.descripcion)}</span></h3>
                    ${sec.items.map((i) => itemHtml(t, i, dis)).join('')}
                </div>`).join('');

            app.innerHTML = `
                <button class="volver" data-accion="inicio">← Inspecciones</button>
                ${bloqueMensaje()}
                <div class="card">
                    <div class="row"><h2 class="grow" style="font-size:22px">${h(s.placa)}</h2><span class="chip">${h(s.estado)}</span></div>
                    <div class="muted">${h(s.vehiculo)} · ${h(s.taller)}</div>
                    <div class="muted small">${h(s.ciudad)}${s.direccion ? ' · ' + h(s.direccion) : ''}</div>
                    ${s.observacion_revisor ? `<div class="aviso bad" style="margin:10px 0 0"><strong>Corrección pedida por el revisor:</strong> ${h(s.observacion_revisor)}</div>` : ''}
                    <div class="progreso" aria-label="Avance del checklist"><div style="width:${p.total ? Math.round(p.respondidos * 100 / p.total) : 0}%"></div></div>
                    <div class="muted small" style="margin-top:4px">Checklist ${p.respondidos}/${p.total} · Fotos ${p.fotos}/${p.fotosTotal}</div>
                </div>

                <div class="card">
                    <h3>1. Filtro de seguridad</h3>
                    <label class="check"><input type="checkbox" ${dis} data-campo-t="declaracion" ${t.declaracion ? 'checked' : ''}>
                        <span>Declaro que no tengo conflicto de interés: no tengo vínculos comerciales, laborales ni personales con este taller ni con el propietario.</span></label>
                    <div class="row" style="margin-top:12px"><button type="button" class="${t.gps ? '' : 'primary'} grow" data-accion="gps" ${dis}>📍 ${t.gps ? 'Volver a capturar ubicación' : 'Capturar ubicación'}</button></div>
                    ${gps}
                </div>

                <div class="card">
                    <h3>2. Chip</h3>
                    <input type="text" id="chip" ${dis} value="${h(t.chip)}" placeholder="Acerque el chip o digite el código" autocomplete="off" autocapitalize="characters" style="text-transform:uppercase">
                    ${'NDEFReader' in window ? `<div class="row" style="margin-top:8px"><button type="button" class="grow" data-accion="nfc" ${dis}>📶 Leer con NFC</button></div>` : ''}
                    <p class="muted small" style="margin:8px 0 0">${def?.chip_obligatorio ? 'Obligatorio.' : 'Opcional según la configuración.'} El código se valida al sincronizar.</p>
                </div>

                <div class="card">
                    <h3>3. Fotos</h3>
                    <div class="fotos">${fotos}</div>
                    <div class="row" style="margin-top:10px"><button type="button" class="grow" data-accion="foto-extra" ${dis}>＋ Foto adicional</button></div>
                </div>

                ${cilindros ? `<div class="card"><h3>4. Cilindros</h3>${cilindros}</div>` : ''}
                ${secciones}

                <div class="card">
                    <h3>Observaciones</h3>
                    <textarea id="observaciones" ${dis} maxlength="3000" placeholder="Hallazgos, aclaraciones…">${h(t.observaciones)}</textarea>
                </div>

                ${soloLectura ? '' : `<p class="muted small" style="text-align:center">Todo se guarda solo en este celular mientras no se envíe.</p>
                <p style="text-align:center"><button type="button" data-accion="descartar" class="small">Descartar lo diligenciado</button></p>`}`;

            // Barra inferior
            barra.classList.remove('oculto');
            const dentro = barra.querySelector('.dentro');
            if (t.estado === 'sincronizada' && t.resultado?.siguiente) {
                dentro.innerHTML = `<a class="btn primary" href="${h(t.resultado.siguiente)}">Continuar en línea →</a>`;
            } else {
                dentro.innerHTML = `<button type="button" class="primary" data-accion="enviar">${enLinea() ? '⬆ Enviar al sistema' : '⬆ Enviar al volver la señal'}</button>`;
            }

            pintarMiniaturas(t);
        }

        function itemHtml(t, i, dis) {
            const v = t.checklist[i.id];
            const valor = t.checklist[i.id + '_valor'];
            const bajo = i.minimo != null && valor !== undefined && valor !== '' && Number(valor) < i.minimo;
            return `
                <div class="item">
                    <div><span class="numeral">${h(i.numeral)}</span> ${h(i.texto)}</div>
                    <div class="seg">
                        ${[['c', 'Cumple'], ['nc', 'No cumple'], ['na', 'N/A']].map(([k, txt]) => `<button type="button" ${dis} data-item="${i.id}" data-v="${k}" aria-pressed="${v === k}">${txt}</button>`).join('')}
                    </div>
                    ${i.medicion && v === 'c' ? `
                        <div class="medicion"><input type="number" inputmode="decimal" min="0" step="0.1" ${dis} data-valor="${i.id}" value="${h(valor ?? '')}" placeholder="${i.minimo ? 'Mínimo ' + i.minimo : 'Medición'}"> <span class="muted">cm</span></div>
                        ${bajo ? `<div class="error">${h(valor)} cm es menor al mínimo de ${i.minimo} cm. Si no cumple, márquelo como «No cumple».</div>` : ''}` : ''}
                </div>`;
        }

        function fotoHtml(t, nombre, obligatoria, dis) {
            const local = t.fotos.find((f) => f.nombre === nombre);
            const enServidor = (t.solicitud.guardado.fotos || []).includes(nombre);
            const id = 'f' + Math.random().toString(36).slice(2);
            return `
                <div class="foto">
                    <div class="img" data-mini="${h(nombre)}">${local ? '' : enServidor ? '✓ Ya cargada en el sistema' : 'Sin foto'}</div>
                    <div class="pie">${h(nombre)}${obligatoria ? ' *' : ''}</div>
                    <label class="btn" for="${id}" ${dis ? 'style="pointer-events:none;opacity:.5"' : ''}>📷 ${local || enServidor ? 'Cambiar' : 'Tomar foto'}</label>
                    <input id="${id}" type="file" accept="image/*" capture="environment" data-foto="${h(nombre)}" ${dis}>
                </div>`;
        }

        async function pintarMiniaturas(t) {
            for (const f of t.fotos) {
                const caja = app.querySelector(`[data-mini="${CSS.escape(f.nombre)}"]`);
                if (! caja) continue;
                const k = claveFoto(t.id, f.nombre);
                if (! urlsFotos.has(k)) {
                    const reg = await db.get('fotos', k);
                    if (! reg) continue;
                    urlsFotos.set(k, URL.createObjectURL(reg.blob));
                }
                caja.innerHTML = `<img src="${urlsFotos.get(k)}" alt="${h(f.nombre)}">`;
            }
        }

        // ------------------------------------------------------------------
        // Edición (todo se guarda al instante en el celular)
        // ------------------------------------------------------------------
        async function modificar(id, fn, repintar = true) {
            const t = await db.get('trabajos', id);
            if (! t || t.estado === 'sincronizada') return;
            // Lo escrito en los campos de texto que aún no se ha guardado
            const chip = document.getElementById('chip');
            const obs = document.getElementById('observaciones');
            if (chip) t.chip = chip.value.trim().toUpperCase();
            if (obs) t.observaciones = obs.value;
            fn(t);
            t.cambios = true;
            if (t.estado === 'conflicto') t.estado = 'borrador';
            await db.put('trabajos', t);
            if (repintar) {
                const y = window.scrollY;
                await pintar();
                window.scrollTo(0, y);
            }
        }

        function cilindro(t, serie) {
            t.checklist.verificacion_cilindros ??= [];
            let c = t.checklist.verificacion_cilindros.find((x) => x.numero_serie === serie);
            if (! c) {
                c = { numero_serie: serie, ultra_liviano: null, ubicacion: [] };
                t.checklist.verificacion_cilindros.push(c);
            }
            return c;
        }

        /** Reduce la foto (máx. 1920 px, JPEG) para ahorrar espacio y datos. */
        async function comprimir(archivo) {
            try {
                const bmp = await createImageBitmap(archivo);
                const escala = Math.min(1, 1920 / Math.max(bmp.width, bmp.height));
                const lienzo = document.createElement('canvas');
                lienzo.width = Math.round(bmp.width * escala);
                lienzo.height = Math.round(bmp.height * escala);
                lienzo.getContext('2d').drawImage(bmp, 0, 0, lienzo.width, lienzo.height);
                return await new Promise((ok) => lienzo.toBlob((b) => ok(b || archivo), 'image/jpeg', 0.82));
            } catch {
                return archivo; // formato que el navegador no sabe abrir (p. ej. HEIC): se guarda tal cual
            }
        }

        async function guardarFoto(id, nombre, archivo) {
            const blob = await comprimir(archivo);
            const k = claveFoto(id, nombre);
            await db.put('fotos', { blob, tomada_en: new Date().toISOString() }, k);
            if (urlsFotos.has(k)) { URL.revokeObjectURL(urlsFotos.get(k)); urlsFotos.delete(k); }
            await modificar(id, (t) => {
                t.fotos = t.fotos.filter((f) => f.nombre !== nombre);
                t.fotos.push({ nombre, tipo: blob.type || 'image/jpeg', subida: false });
            });
        }

        function capturarGps(id) {
            if (! navigator.geolocation) return avisar('bad', 'Este navegador no permite obtener la ubicación.');
            avisar('info', 'Buscando señal GPS…');
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    mensaje = null;
                    modificar(id, (t) => {
                        t.gps = { lat: pos.coords.latitude, lng: pos.coords.longitude, precision: pos.coords.accuracy, capturado_en: new Date(pos.timestamp).toISOString() };
                    });
                },
                (err) => avisar('bad', ({
                    1: 'Permiso de ubicación denegado. Habilítelo para este sitio en la configuración del navegador.',
                    2: 'No se pudo determinar la ubicación. Active el GPS del teléfono.',
                    3: 'El GPS tardó demasiado. Intente de nuevo, idealmente a cielo abierto.',
                })[err.code] || 'No se pudo obtener la ubicación.'),
                { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 },
            );
        }

        async function leerNfc(id) {
            try {
                const lector = new NDEFReader();
                const corte = new AbortController();
                await lector.scan({ signal: corte.signal });
                avisar('info', 'Acerque el chip a la parte trasera del celular…');
                lector.onreading = ({ message, serialNumber }) => {
                    const texto = [...message.records].filter((r) => r.recordType === 'text')
                        .map((r) => new TextDecoder(r.encoding || 'utf-8').decode(r.data))[0];
                    const codigo = String(texto || (serialNumber || '').replaceAll(':', '')).trim().toUpperCase();
                    corte.abort();
                    if (navigator.vibrate) navigator.vibrate(150);
                    mensaje = { tipo: 'ok', html: 'Chip leído: ' + h(codigo) };
                    modificar(id, (t) => { t.chip = codigo; });
                };
            } catch (e) {
                avisar('bad', e.name === 'NotAllowedError' ? 'Permiso de NFC denegado.' : 'No fue posible iniciar el NFC: ' + h(e.message));
            }
        }

        function avisar(tipo, html) {
            mensaje = { tipo, html };
            pintar().then(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        }

        // ------------------------------------------------------------------
        // Sincronización
        // ------------------------------------------------------------------
        async function enviar(id, reintento = false) {
            const t = await db.get('trabajos', id);
            if (! t) return;

            if (! t.declaracion || ! t.gps) {
                throw new Error('Falta la declaración de no conflicto de interés o la ubicación GPS.');
            }

            if (! enLinea()) {
                t.estado = 'en_cola';
                t.cambios = true;
                await db.put('trabajos', t);
                return { enCola: true };
            }

            const form = new FormData();
            form.append('declaracion', '1');
            form.append('lat', t.gps.lat);
            form.append('lng', t.gps.lng);
            form.append('precision', t.gps.precision ?? '');
            form.append('capturado_en', t.gps.capturado_en);
            form.append('chip_codigo', t.chip || '');
            form.append('observaciones', t.observaciones || '');
            form.append('checklist', JSON.stringify(t.checklist || {}));

            let n = 0;
            for (const f of t.fotos.filter((x) => ! x.subida)) {
                const reg = await db.get('fotos', claveFoto(id, f.nombre));
                if (! reg) continue;
                form.append(`fotos[${n}][nombre]`, f.nombre);
                form.append(`fotos[${n}][archivo]`, reg.blob, `foto-${n}.${(reg.blob.type || 'image/jpeg').split('/')[1] || 'jpg'}`);
                n++;
            }

            let r;
            try {
                r = await fetch(CFG.sincronizar + '/' + id, {
                    method: 'POST',
                    body: form,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                });
            } catch {
                t.estado = 'en_cola';
                await db.put('trabajos', t);
                return { enCola: true };
            }

            if (r.status === 419 && ! reintento) {
                await descargar().catch(() => null); // token vencido: se pide uno nuevo
                return enviar(id, true);
            }
            if (r.status === 401 || r.status === 419 || r.redirected) {
                t.estado = 'en_cola';
                await db.put('trabajos', t);
                throw new Error(`Su sesión se cerró. <a href="${h(CFG.login)}">Inicie sesión</a> y vuelva a esta pantalla: los datos siguen guardados en el celular.`);
            }

            const cuerpo = await r.json().catch(() => ({}));

            if (r.status === 422) {
                t.estado = 'borrador';
                await db.put('trabajos', t);
                throw new Error(Object.values(cuerpo.errors || {}).flat().map(h).join('<br>') || 'Hay datos inválidos.');
            }
            if (r.status === 409 || r.status === 404 || r.status === 403) {
                t.estado = 'conflicto';
                await db.put('trabajos', t);
                throw new Error(h(cuerpo.mensaje || cuerpo.message || 'El sistema no aceptó la inspección.'));
            }
            if (! r.ok) {
                t.estado = 'en_cola';
                await db.put('trabajos', t);
                throw new Error('El servidor respondió con un error (' + r.status + '). Se intentará de nuevo más tarde.');
            }

            // Listo: las fotos ya están en el servidor, se libera espacio del celular
            for (const f of t.fotos) {
                await db.del('fotos', claveFoto(id, f.nombre));
                const k = claveFoto(id, f.nombre);
                if (urlsFotos.has(k)) { URL.revokeObjectURL(urlsFotos.get(k)); urlsFotos.delete(k); }
            }
            t.solicitud.guardado.fotos = [...new Set([...(t.solicitud.guardado.fotos || []), ...t.fotos.map((f) => f.nombre)])];
            t.fotos = [];
            t.estado = 'sincronizada';
            t.sincronizada_en = new Date().toISOString();
            t.resultado = cuerpo;
            await db.put('trabajos', t);
            return cuerpo;
        }

        async function enviarCola() {
            if (! enLinea()) return;
            const cola = (await db.todos('trabajos')).filter((t) => t.estado === 'en_cola');
            if (! cola.length) return;

            let ok = 0;
            const errores = [];
            for (const t of cola) {
                try {
                    const r = await enviar(t.id);
                    if (! r.enCola) ok++;
                } catch (e) {
                    errores.push(`<strong>${h(t.solicitud.placa)}:</strong> ${e.message}`);
                }
            }
            mensaje = errores.length
                ? { tipo: 'bad', html: (ok ? `${ok} inspección(es) enviada(s).<br>` : '') + errores.join('<br>') }
                : { tipo: 'ok', html: `${ok} inspección(es) enviada(s) al sistema. Ábralas en línea para revisar y enviar al revisor.` };
            await pintar();
        }

        // ------------------------------------------------------------------
        // Eventos
        // ------------------------------------------------------------------
        app.addEventListener('click', async (e) => {
            const abrir = e.target.closest('[data-abrir]');
            if (abrir) {
                vista = { tipo: 'detalle', id: Number(abrir.dataset.abrir) };
                mensaje = null;
                await pintar();
                return window.scrollTo(0, 0);
            }

            const item = e.target.closest('[data-item]');
            if (item && vista.id) {
                return modificar(vista.id, (t) => {
                    const actual = t.checklist[item.dataset.item];
                    t.checklist[item.dataset.item] = actual === item.dataset.v ? null : item.dataset.v;
                });
            }

            const cil = e.target.closest('[data-cil][data-campo]');
            if (cil && vista.id) {
                return modificar(vista.id, (t) => { cilindro(t, cil.dataset.cil).ultra_liviano = cil.dataset.v; });
            }

            const btn = e.target.closest('[data-accion]');
            if (! btn) return;

            switch (btn.dataset.accion) {
                case 'inicio':
                    vista = { tipo: 'inicio' };
                    mensaje = null;
                    await pintar();
                    return window.scrollTo(0, 0);

                case 'descargar':
                    btn.disabled = true;
                    btn.textContent = 'Descargando…';
                    try {
                        const n = await descargar();
                        mensaje = { tipo: 'ok', html: `Listo: ${n} solicitud(es) disponibles sin conexión.` };
                    } catch (err) {
                        mensaje = { tipo: 'bad', html: err.message === 'sesion' ? `Su sesión se cerró. <a href="${h(CFG.login)}">Inicie sesión</a> y vuelva a esta pantalla.` : h(err.message) };
                    }
                    return pintar();

                case 'sincronizar-todo':
                    btn.disabled = true;
                    return enviarCola();

                case 'gps':
                    return capturarGps(vista.id);

                case 'nfc':
                    return leerNfc(vista.id);

                case 'foto-extra': {
                    const nombre = (prompt('Descripción de la foto adicional') || '').trim().slice(0, 120);
                    if (! nombre) return;
                    if ((def?.fotos_obligatorias || []).includes(nombre)) return avisar('bad', 'Use otra descripción: esa corresponde a una foto obligatoria.');
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = 'image/*';
                    input.capture = 'environment';
                    input.onchange = () => input.files[0] && guardarFoto(vista.id, nombre, input.files[0]);
                    return input.click();
                }

                case 'descartar':
                    if (! confirm('¿Borrar lo diligenciado en este celular para esta solicitud? Lo que ya esté en el sistema no se toca.')) return;
                    await borrarTrabajo(vista.id);
                    vista = { tipo: 'inicio' };
                    mensaje = { tipo: 'info', html: 'Se borró del celular. Descargue de nuevo para retomarla.' };
                    return pintar();
            }
        });

        barra.addEventListener('click', async (e) => {
            if (! e.target.closest('[data-accion=enviar]') || ! vista.id) return;
            await guardarTextos();
            try {
                const r = await enviar(vista.id);
                mensaje = r.enCola
                    ? { tipo: 'info', html: 'Sin señal: quedó en cola y se enviará sola cuando vuelva la conexión (mantenga esta pantalla abierta o vuelva a abrirla).' }
                    : { tipo: (r.avisos || []).length ? 'warn' : 'ok', html: h(r.mensaje) + ((r.avisos || []).length ? '<br>' + r.avisos.map(h).join('<br>') : '') };
            } catch (err) {
                mensaje = { tipo: 'bad', html: err.message };
            }
            await pintar();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        app.addEventListener('change', async (e) => {
            const el = e.target;
            if (! vista.id) return;

            if (el.dataset.campoT === 'declaracion') {
                return modificar(vista.id, (t) => { t.declaracion = el.checked; });
            }
            if (el.dataset.ubic) {
                return modificar(vista.id, (t) => {
                    const c = cilindro(t, el.dataset.cil);
                    c.ubicacion = el.checked ? [...new Set([...c.ubicacion, el.dataset.ubic])] : c.ubicacion.filter((u) => u !== el.dataset.ubic);
                });
            }
            if (el.dataset.foto && el.files?.[0]) {
                return guardarFoto(vista.id, el.dataset.foto, el.files[0]);
            }
            if (el.dataset.valor) {
                return modificar(vista.id, (t) => { t.checklist[el.dataset.valor + '_valor'] = el.value === '' ? null : Number(el.value); });
            }
        });

        // Los textos se guardan mientras se escribe, sin repintar (no se pierde el foco)
        let temporizador;
        app.addEventListener('input', (e) => {
            if (! ['chip', 'observaciones'].includes(e.target.id)) return;
            clearTimeout(temporizador);
            temporizador = setTimeout(guardarTextos, 400);
        });

        async function guardarTextos() {
            if (vista.id && document.getElementById('chip')) {
                await modificar(vista.id, () => {}, false); // modificar() toma los textos del formulario
            }
        }

        function estadoConexion() {
            const pill = document.getElementById('conexion');
            pill.className = 'pill ' + (enLinea() ? 'ok' : 'off');
            pill.textContent = enLinea() ? 'En línea' : 'Sin conexión';
        }

        window.addEventListener('online', async () => { estadoConexion(); await enviarCola(); await pintar(); });
        window.addEventListener('offline', () => { estadoConexion(); pintar(); });

        // ------------------------------------------------------------------
        // Arranque
        // ------------------------------------------------------------------
        (async () => {
            estadoConexion();

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/campo-sw.js', { scope: '/campo' }).catch(() => null);
            }
            // Pide al navegador no borrar los datos guardados si el celular se queda sin espacio
            navigator.storage?.persist?.().catch(() => null);

            try {
                def = await db.get('kv', 'definicion');
                const hay = (await db.todos('trabajos')).length;
                if (! hay && enLinea()) {
                    const n = await descargar();
                    mensaje = { tipo: 'ok', html: `Se descargaron ${n} solicitud(es). Ya puede trabajar sin conexión.` };
                }
            } catch (err) {
                mensaje = { tipo: 'bad', html: err.message === 'sesion' ? `Su sesión se cerró. <a href="${h(CFG.login)}">Inicie sesión</a>.` : h(err.message) };
            }

            await pintar();
            await enviarCola();
        })();
    })();
    </script>
    @endverbatim
</body>
</html>

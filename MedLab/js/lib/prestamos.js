(() => {
    'use strict';
    const app = document.querySelector('#prestamosApp');
    if (!app) return;
    const admin = app.dataset.administrador === '1';
    const userId = Number(app.dataset.usuario);
    const api = 'api/admin/C_Prestamos.php';
    const notificationsApi = 'api/admin/C_Notificaciones.php';
    const el = (id) => document.getElementById(id);
    const h = escapeHtml;
    const names = { PENDIENTE: 'Pendiente', APROBADO: 'Aprobado', RECHAZADO: 'Rechazado', ENTREGADO: 'Entregado', DEVUELTO: 'Devuelto', RESERVA_VENCIDA: 'Reserva vencida', CANCELADO: 'Cancelado' };
    let loans = [], equipment = [], conditions = [], maximumDate = '', lineSequence = 0, currentAction = null;
    const triggers = new Map();
    const today = () => new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Guatemala', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    function nextMonthLimit() {
        const [year, month, day] = today().split('-').map(Number);
        const targetYear = month === 12 ? year + 1 : year;
        const targetMonth = month === 12 ? 1 : month + 1;
        const lastDay = new Date(Date.UTC(targetYear, targetMonth, 0)).getUTCDate();
        return `${targetYear}-${String(targetMonth).padStart(2, '0')}-${String(Math.min(day, lastDay)).padStart(2, '0')}`;
    }
    const limitDate = () => maximumDate || nextMonthLimit();
    // Dates are server dates in Guatemala. Display the supplied date without browser timezone conversion.
    function date(value) {
        if (!value) return '—';
        const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/);
        return match ? `${match[3]}/${match[2]}/${match[1]}${match[4] ? ` ${match[4]}` : ''}` : String(value);
    }
    const state = (loan) => String(loan.estado_efectivo || loan.estado || '').toUpperCase();
    const pendingQuantity = (detail) => Math.max(0, Number(detail.cantidad) - Number(detail.cantidad_devuelta || 0));
    function showError(id, message) { const box = el(id); box.textContent = message; box.hidden = false; box.focus?.(); }
    function clearError(id) { el(id).hidden = true; el(id).textContent = ''; }
    function openDialog(id, trigger) {
        const dialog = el(id); triggers.set(dialog, trigger || document.activeElement); dialog.showModal();
        const first = dialog.querySelector('input:not([type="hidden"]), select, textarea, button'); first?.focus();
    }
    function busy(form, active) {
        form.dataset.busy = active ? '1' : '0'; form.setAttribute('aria-busy', String(active));
        form.querySelectorAll('button').forEach((button) => { button.disabled = active; });
    }
    for (const id of ['dialogoSolicitud', 'dialogoAccionPrestamo']) {
        const dialog = el(id);
        dialog.addEventListener('cancel', (event) => { if (dialog.querySelector('form').dataset.busy === '1') event.preventDefault(); });
        dialog.addEventListener('close', () => { const trigger = triggers.get(dialog); if (trigger?.isConnected) trigger.focus(); else el('refrescarPrestamos').focus(); });
    }
    document.querySelectorAll('[data-cerrar-dialogo]').forEach((button) => button.addEventListener('click', () => el(button.dataset.cerrarDialogo).close()));
    function actionButton(action, text, loan) { return `<button type="button" class="btn btn--secundario btn--sm" data-loan-action="${action}" data-id="${h(loan.id_prestamo)}" aria-label="${h(text)} préstamo ${h(loan.id_prestamo)}">${h(text)}</button>`; }
    function actions(loan) {
        const s = state(loan), list = [];
        if (admin && s === 'PENDIENTE') list.push(actionButton('aprobar', 'Aprobar', loan), actionButton('rechazar', 'Rechazar', loan));
        if (admin && s === 'APROBADO') list.push(actionButton('entregar', 'Registrar entrega', loan));
        if (admin && s === 'ENTREGADO' && (loan.detalles || []).some((d) => pendingQuantity(d) > 0)) list.push(actionButton('devolver', 'Registrar devolución', loan));
        if ((admin || Number(loan.id_usuario) === userId) && ['PENDIENTE', 'APROBADO'].includes(s)) list.push(actionButton('cancelar', 'Cancelar', loan));
        return list.join('') || '<span class="loan-meta">Sin acciones pendientes</span>';
    }
    function render() {
        const query = el('buscarPrestamo').value.trim().toLocaleLowerCase('es');
        const filter = el('filtrarEstadoPrestamo').value;
        const list = loans.filter((loan) => (!filter || state(loan) === filter) && `${loan.id_prestamo} ${loan.solicitante || ''} ${(loan.detalles || []).map((d) => d.nombre).join(' ')}`.toLocaleLowerCase('es').includes(query));
        el('prestamosConteo').textContent = `${list.length} de ${loans.length} préstamos`;
        el('tablaPrestamos').innerHTML = list.map((loan) => {
            const s = state(loan), overdue = s === 'ENTREGADO' && loan.fecha_limite && String(loan.fecha_limite).slice(0, 10) < today();
            const detail = (loan.detalles || []).map((d) => `<li>${h(d.nombre)} · ${h(d.cantidad)} unidad(es)${Number(d.cantidad_devuelta) ? ` · ${h(d.cantidad_devuelta)} devuelta(s)` : ''}</li>`).join('');
            return `<tr><td><strong>#${h(loan.id_prestamo)}</strong><span class="loan-meta">${h(date(loan.fecha_solicitud))}</span></td><td>${h(loan.solicitante || '—')}</td><td><ul>${detail}</ul><span class="loan-meta">Devolución solicitada: ${h(date(loan.fecha_devolucion_solicitada))}</span>${loan.fecha_limite ? `<span class="loan-meta">Fecha límite: ${h(date(loan.fecha_limite))}</span>` : ''}${s === 'APROBADO' && loan.fecha_reserva_hasta ? `<span class="loan-meta">Reserva hasta: ${h(date(loan.fecha_reserva_hasta))}</span>` : ''}<details><summary>Más información</summary>${loan.fecha_aprobacion ? `<p class="loan-meta">Aprobado: ${h(date(loan.fecha_aprobacion))}</p>` : ''}${loan.fecha_prestamo ? `<p class="loan-meta">Entregado: ${h(date(loan.fecha_prestamo))}</p>` : ''}<p class="loan-note">${h(loan.observaciones || 'Sin observaciones.')}</p>${loan.motivo_rechazo ? `<p class="loan-note">Motivo del rechazo: ${h(loan.motivo_rechazo)}</p>` : ''}</details></td><td><span class="loan-state">${h(names[s] || loan.estado_nombre || s)}</span>${overdue ? '<span class="loan-late">Devolución atrasada</span>' : ''}</td><td><div class="loan-actions">${actions(loan)}</div></td></tr>`;
        }).join('') || '<tr><td colspan="5" class="tabla-vacia">No hay préstamos que coincidan. Puedes cambiar los filtros o solicitar un préstamo.</td></tr>';
    }
    async function loadLoans() {
        clearError('prestamosError');
        try { const result = await peticion(api); loans = Array.isArray(result.data) ? result.data : []; maximumDate = result.fecha_maxima_devolucion || ''; render(); }
        catch (error) { showError('prestamosError', error.message); el('prestamosConteo').textContent = 'No se pudieron cargar los préstamos.'; }
    }
    async function loadEquipment() {
        const result = await peticion('api/admin/C_Equipos.php');
        equipment = (Array.isArray(result.data) ? result.data : []).filter((item) => Number(item.activo) === 1 && Number(item.cantidad_disponible) > 0);
    }
    async function loadConditions() {
        if (!conditions.length) { const result = await peticion('api/admin/C_Catalogos.php?tipo=condiciones'); conditions = Array.isArray(result.data) ? result.data : []; }
    }
    function addRequestLine() {
        if (el('filasSolicitud').children.length >= 50) { showError('solicitudError', 'Puedes incluir hasta 50 filas de equipos por solicitud.'); return; }
        const id = ++lineSequence, row = document.createElement('div'); row.className = 'loan-request-line';
        row.innerHTML = `<div class="campo"><label for="equipoSolicitud${id}">Equipo</label><select id="equipoSolicitud${id}" class="request-equipment" required><option value="">Selecciona un equipo</option>${equipment.map((item) => `<option value="${h(item.id_equipo)}">${h(item.codigo_inventario || '')} · ${h(item.nombre)} (${h(item.cantidad_disponible)} disponible(s))</option>`).join('')}</select></div><div class="campo"><label for="cantidadSolicitud${id}">Cantidad</label><input id="cantidadSolicitud${id}" class="request-quantity" type="number" min="1" step="1" value="1" required></div><button class="btn btn--secundario" type="button" aria-label="Quitar equipo de la solicitud">Quitar</button>`;
        row.querySelector('select').addEventListener('change', () => { const item = equipment.find((e) => Number(e.id_equipo) === Number(row.querySelector('select').value)); const quantity = row.querySelector('input'); quantity.max = item ? String(item.cantidad_disponible) : ''; quantity.setCustomValidity(''); });
        row.querySelector('button').addEventListener('click', () => { row.remove(); el('agregarEquipoSolicitud').focus(); });
        el('filasSolicitud').append(row); row.querySelector('select').focus();
    }
    el('solicitarPrestamo').addEventListener('click', async (event) => {
        const button = event.currentTarget; if (button.disabled) return; button.disabled = true; clearError('prestamosError');
        try { await loadEquipment(); if (!equipment.length) throw new Error('No hay equipos disponibles para solicitar en este momento.'); el('formSolicitud').reset(); el('filasSolicitud').replaceChildren(); clearError('solicitudError'); el('fechaSolicitada').min = today(); el('fechaSolicitada').max = limitDate(); openDialog('dialogoSolicitud', button); addRequestLine(); }
        catch (error) { showError('prestamosError', error.message); } finally { button.disabled = false; }
    });
    el('agregarEquipoSolicitud').addEventListener('click', addRequestLine);
    el('formSolicitud').addEventListener('submit', async (event) => {
        event.preventDefault(); const form = event.currentTarget; if (form.dataset.busy === '1') return; clearError('solicitudError');
        const grouped = new Map();
        for (const row of el('filasSolicitud').children) { const id = Number(row.querySelector('select').value), quantity = Number(row.querySelector('input').value); if (!Number.isInteger(id) || id < 1 || !Number.isInteger(quantity) || quantity < 1) return showError('solicitudError', 'Selecciona cada equipo e indica una cantidad entera mayor que cero.'); grouped.set(id, (grouped.get(id) || 0) + quantity); }
        if (!grouped.size) return showError('solicitudError', 'Añade al menos un equipo.');
        for (const [id, quantity] of grouped) { const item = equipment.find((e) => Number(e.id_equipo) === id); if (!item || quantity > Number(item.cantidad_disponible)) return showError('solicitudError', 'La cantidad total de un equipo supera su disponibilidad. Revisa las filas repetidas.'); }
        busy(form, true);
        try { await peticion(api, 'POST', { accion: 'solicitar', detalles: JSON.stringify([...grouped].map(([id_equipo, cantidad]) => ({ id_equipo, cantidad }))), fecha_devolucion_solicitada: el('fechaSolicitada').value, observaciones: el('observacionesSolicitud').value }); el('dialogoSolicitud').close(); mostrarAviso('Solicitud enviada para aprobación.'); await loadLoans(); await loadNotifications(); el('refrescarPrestamos').focus(); }
        catch (error) { showError('solicitudError', error.message); } finally { busy(form, false); }
    });
    function conditionOptions() { return '<option value="">Selecciona la condición</option>' + conditions.map((c) => `<option value="${h(c.id_condicion)}">${h(c.nombre)}</option>`).join(''); }
    async function prepareAction(action, loan, button) {
        const title = { aprobar: 'Aprobar solicitud', rechazar: 'Rechazar solicitud', entregar: 'Registrar entrega', devolver: 'Registrar devolución', cancelar: 'Cancelar solicitud' }[action];
        if (!title) return;
        const deliveryLimit = loan.fecha_maxima_devolucion || limitDate();
        if (action === 'devolver') { await loadConditions(); if (!conditions.length) throw new Error('No hay condiciones de recepción configuradas.'); }
        currentAction = { action, loan }; el('formAccionPrestamo').reset(); clearError('accionPrestamoError'); el('tituloAccionPrestamo').textContent = `${title} #${loan.id_prestamo}`; el('confirmarAccionPrestamo').textContent = title;
        let content = `<p>Solicitante: ${h(loan.solicitante || '—')}</p>`;
        if (action === 'aprobar') content += '<p>La aprobación reservará los equipos disponibles. La entrega se registra después.</p>';
        if (action === 'cancelar') content += '<p>La solicitud quedará cancelada. Si tenía una reserva vigente, sus equipos volverán a estar disponibles.</p>';
        if (action === 'rechazar') content += '<div class="campo"><label for="motivoRechazoPrestamo">Motivo del rechazo</label><textarea id="motivoRechazoPrestamo" rows="3" maxlength="500" required></textarea></div>';
        if (action === 'entregar') content += `<p>Confirma que los equipos se entregan al solicitante. El plazo máximo es un mes a partir de hoy.</p><div class="campo"><label for="fechaLimitePrestamo">Fecha límite de devolución</label><input id="fechaLimitePrestamo" type="date" min="${h(today())}" max="${h(deliveryLimit)}" required value="${h(String(loan.fecha_devolucion_solicitada || '').slice(0, 10) >= today() && String(loan.fecha_devolucion_solicitada).slice(0, 10) <= deliveryLimit ? String(loan.fecha_devolucion_solicitada).slice(0, 10) : '')}"></div>`;
        if (action === 'devolver') content += '<p>Registra solo lo recibido ahora. Puedes completar el préstamo con devoluciones posteriores. Indica cuántas unidades recibidas deben salir de servicio.</p>' + (loan.detalles || []).filter((d) => pendingQuantity(d) > 0).map((d) => `<fieldset data-return-detail="${h(d.id_detalle)}" data-remaining="${h(pendingQuantity(d))}"><legend>${h(d.nombre)} · ${h(pendingQuantity(d))} pendiente(s)</legend><div class="loan-return-grid"><div class="campo"><label for="recibidas${h(d.id_detalle)}">Unidades recibidas ahora</label><input id="recibidas${h(d.id_detalle)}" class="return-quantity" type="number" min="0" max="${h(pendingQuantity(d))}" step="1" value="0" required></div><div class="campo"><label for="fueraServicio${h(d.id_detalle)}">De las recibidas, fuera de servicio</label><input id="fueraServicio${h(d.id_detalle)}" class="return-unserviceable" type="number" min="0" max="${h(pendingQuantity(d))}" step="1" value="0" required></div><div class="campo campo--completo"><label for="condicion${h(d.id_detalle)}">Condición de recepción</label><select id="condicion${h(d.id_detalle)}" class="return-condition">${conditionOptions()}</select></div><div class="campo campo--completo"><label for="notaDevolucion${h(d.id_detalle)}">Observaciones de recepción (opcional)</label><textarea id="notaDevolucion${h(d.id_detalle)}" class="return-note" maxlength="500" rows="2"></textarea></div></div></fieldset>`).join('');
        el('camposAccionPrestamo').innerHTML = content;
        el('camposAccionPrestamo').querySelectorAll('[data-return-detail]').forEach((field) => field.querySelector('.return-quantity').addEventListener('input', () => { const value = Number(field.querySelector('.return-quantity').value); field.querySelector('.return-condition').required = value > 0; field.querySelector('.return-unserviceable').max = String(Math.max(0, value)); }));
        openDialog('dialogoAccionPrestamo', button);
    }
    el('tablaPrestamos').addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-loan-action]'); if (!button || button.disabled) return;
        const loan = loans.find((l) => Number(l.id_prestamo) === Number(button.dataset.id)); if (!loan) return; button.disabled = true;
        try { await prepareAction(button.dataset.loanAction, loan, button); } catch (error) { showError('prestamosError', error.message); } finally { button.disabled = false; }
    });
    el('formAccionPrestamo').addEventListener('submit', async (event) => {
        event.preventDefault(); const form = event.currentTarget; if (form.dataset.busy === '1' || !currentAction) return; clearError('accionPrestamoError');
        const { action, loan } = currentAction, data = { accion: action, id_prestamo: loan.id_prestamo };
        if (action === 'rechazar') data.motivo = el('motivoRechazoPrestamo').value;
        if (action === 'entregar') data.fecha_limite = el('fechaLimitePrestamo').value;
        if (action === 'devolver') {
            const returns = [];
            for (const field of el('camposAccionPrestamo').querySelectorAll('[data-return-detail]')) {
                const cantidad = Number(field.querySelector('.return-quantity').value), fuera = Number(field.querySelector('.return-unserviceable').value), condition = Number(field.querySelector('.return-condition').value);
                if (!Number.isInteger(cantidad) || cantidad < 0 || cantidad > Number(field.dataset.remaining) || !Number.isInteger(fuera) || fuera < 0 || fuera > cantidad) return showError('accionPrestamoError', 'Revisa las cantidades recibidas y las unidades fuera de servicio.');
                if (cantidad === 0) continue;
                if (!Number.isInteger(condition) || condition < 1) return showError('accionPrestamoError', 'Selecciona una condición para cada equipo recibido.');
                returns.push({ id_detalle: Number(field.dataset.returnDetail), cantidad, id_condicion_recepcion: condition, cantidad_fuera_servicio: fuera, observaciones: field.querySelector('.return-note').value });
            }
            if (!returns.length) return showError('accionPrestamoError', 'Indica al menos una unidad recibida.');
            data.devoluciones = JSON.stringify(returns);
        }
        busy(form, true);
        try { await peticion(api, 'POST', data); el('dialogoAccionPrestamo').close(); mostrarAviso('Préstamo actualizado.'); await loadLoans(); await loadNotifications(); el('refrescarPrestamos').focus(); }
        catch (error) { showError('accionPrestamoError', error.message); } finally { busy(form, false); }
    });
    async function loadNotifications() {
        clearError('notificacionesError');
        try {
            const response = await peticion(notificationsApi), notifications = Array.isArray(response.data) ? response.data : [];
            const isRead = (notification) => Boolean(notification.fecha_lectura) || Number(notification.leida) === 1;
            const unread = notifications.filter((n) => !isRead(n)); el('notificacionesConteo').textContent = unread.length ? `(${unread.length} sin leer)` : '';
            el('listaNotificaciones').innerHTML = notifications.map((n) => `<li>${n.titulo ? `<strong>${h(n.titulo)}</strong>` : ''}<p>${h(n.mensaje || '')}</p><span class="loan-meta">${h(date(n.fecha_creacion))}</span>${!isRead(n) ? `<button type="button" class="btn btn--secundario btn--sm" data-notification="${h(n.id_notificacion)}">Marcar como leída</button>` : '<span class="loan-meta">Leída</span>'}</li>`).join('') || '<li>No tienes notificaciones.</li>';
        } catch (error) { el('notificacionesError').textContent = error.message; el('notificacionesError').hidden = false; el('listaNotificaciones').replaceChildren(); }
    }
    el('listaNotificaciones').addEventListener('click', async (event) => {
        const button = event.target.closest('[data-notification]'); if (!button || button.disabled) return; button.disabled = true;
        try { await peticion(notificationsApi, 'POST', { id_notificacion: button.dataset.notification }); await loadNotifications(); el('panelNotificaciones').querySelector('summary').focus(); }
        catch (error) { el('notificacionesError').textContent = error.message; el('notificacionesError').hidden = false; button.disabled = false; }
    });
    el('buscarPrestamo').addEventListener('input', render); el('filtrarEstadoPrestamo').addEventListener('change', render);
    el('refrescarPrestamos').addEventListener('click', async (event) => { const button = event.currentTarget; if (button.disabled) return; button.disabled = true; try { await Promise.all([loadLoans(), loadNotifications()]); } finally { button.disabled = false; } });
    loadLoans(); loadNotifications();
})();

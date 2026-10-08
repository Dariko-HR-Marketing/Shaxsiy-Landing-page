/* DARIKO admin panel — Bron kalendari (v12: admin-ext.js dan ajratildi). Bog'liqlik: admin.js, admin-core.js. */
(() => {
  'use strict';
  const { A, $, esc, toast, get, post, dt, day, SRC, ST, srcTag, stTag, fail, uploadMedia, addTimer, shared } = window.DARIKO_ADMIN_EXT;
  /* ============================== Bron kalendari ============================== */
  let calMonth = new Date().toISOString().slice(0, 7), calData = null, calSel = null;
  const monthName = m => { const [y, mm] = m.split('-').map(Number); return ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'][mm - 1] + ' ' + y; };
  async function loadCal() {
    try { calData = await get('admin/bookings?month=' + calMonth); } catch (e) { return fail(e); }
    $('#calTitle').textContent = monthName(calMonth);
    const [y, m] = calMonth.split('-').map(Number);
    const first = new Date(Date.UTC(y, m - 1, 1)), days = new Date(Date.UTC(y, m, 0)).getUTCDate();
    const lead = (first.getUTCDay() + 6) % 7;
    const closed = new Set(calData.closed.map(c => c.date));
    const today = new Date().toISOString().slice(0, 10);
    let h = ['Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh', 'Ya'].map(d => `<div class="cal-h">${d}</div>`).join('') + '<div class="cal-empty"></div>'.repeat(lead);
    for (let d = 1; d <= days; d++) {
      const iso = `${calMonth}-${String(d).padStart(2, '0')}`;
      const wd = String(((lead + d - 1) % 7) + 1);
      const off = closed.has(iso) || !(calData.rules.week[wd] || []).length;
      const bs = calData.bookings.filter(b => b.requested_date === iso);
      h += `<button type="button" class="cal-d${off ? ' off' : ''}${iso === today ? ' today' : ''}${iso === calSel ? ' sel' : ''}" data-d="${iso}"><span class="n">${d}</span>
        ${bs.slice(0, 3).map(b => `<span class="chip ${esc(b.status)}">${esc(b.requested_time_slot)}</span>`).join('')}${bs.length > 3 ? `<span class="chip more">+${bs.length - 3}</span>` : ''}</button>`;
    }
    $('#calGrid').innerHTML = h;
    $('#calGrid').querySelectorAll('.cal-d').forEach(b => b.addEventListener('click', () => { calSel = b.dataset.d; loadCal(); }));
    renderCalDay();
  }
  function renderCalDay() {
    if (!calSel || !calData || !calSel.startsWith(calMonth)) { $('#calDayTitle').textContent = 'Kun tanlanmagan'; $('#calDay').innerHTML = '<p class="muted">Kalendardan kunni tanlang.</p>'; return; }
    const closedRow = calData.closed.find(c => c.date === calSel);
    const bs = calData.bookings.filter(b => b.requested_date === calSel);
    $('#calDayTitle').textContent = calSel;
    $('#calDay').innerHTML = (bs.length ? `<div class="table-wrap"><table class="table"><thead><tr><th>Vaqt</th><th>Mijoz</th><th>Holat</th><th></th></tr></thead><tbody>${bs.map(b => `<tr>
        <td><b>${esc(b.requested_time_slot)}</b></td><td>${esc(b.name)}<br><a class="small" href="tel:${esc(b.phone)}">${esc(b.phone)}</a>${b.service ? `<br><span class="muted small">${esc(b.service)}</span>` : ''}</td>
        <td><span class="tag ${b.status === 'confirmed' ? 'ok' : b.status === 'cancelled' ? 'bad' : 'warn'}">${esc({ pending: 'Kutilmoqda', confirmed: 'Tasdiqlangan', cancelled: 'Bekor' }[b.status])}</span></td>
        <td class="actions">${b.status !== 'confirmed' ? `<button type="button" class="btn primary sm" data-bs="confirmed" data-id="${b.id}">Tasdiqlash</button>` : ''}
          ${b.status !== 'cancelled' ? `<button type="button" class="btn ghost sm" data-bs="cancelled" data-id="${b.id}">Bekor qilish</button>` : ''}
          <button type="button" class="btn ghost sm" data-lead="${b.lead_id}">CRM</button></td></tr>`).join('')}</tbody></table></div>` : '<p class="muted">Bu kunda bron yo‘q.</p>')
      + `<hr><label class="check"><input type="checkbox" id="dayClosed"${closedRow ? ' checked' : ''}> Bu kun yopiq (bayram / dam olish)</label>
         <label for="dayNote">Izoh</label><input id="dayNote" maxlength="120" value="${esc(closedRow ? closedRow.note : '')}"><div class="row"><button type="button" class="btn ghost" id="daySave">Saqlash</button></div>`;
    $('#calDay').querySelectorAll('[data-bs]').forEach(b => b.addEventListener('click', async () => {
      try { await post('admin/booking/status', { id: Number(b.dataset.id), status: b.dataset.bs }); toast('Holat yangilandi'); loadCal(); } catch (e) { fail(e); }
    }));
    $('#calDay').querySelectorAll('[data-lead]').forEach(b => b.addEventListener('click', () => { shared.openLeadId = Number(b.dataset.lead); location.hash = 'crm'; document.querySelector('.nav button[data-tab="crm"]').click(); }));
    $('#daySave').addEventListener('click', async () => {
      try { await post('admin/availability/override', { date: calSel, closed: $('#dayClosed').checked, note: $('#dayNote').value }); toast('Saqlandi'); loadCal(); } catch (e) { fail(e); }
    });
  }
  $('#calPrev').addEventListener('click', () => { const [y, m] = calMonth.split('-').map(Number); const d = new Date(Date.UTC(y, m - 2, 1)); calMonth = d.toISOString().slice(0, 7); loadCal(); });
  $('#calNext').addEventListener('click', () => { const [y, m] = calMonth.split('-').map(Number); const d = new Date(Date.UTC(y, m, 1)); calMonth = d.toISOString().slice(0, 7); loadCal(); });
  async function loadAvail() {
    let d; try { d = await get('admin/availability'); } catch (e) { return fail(e); }
    const r = d.rules, names = ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba', 'Yakshanba'];
    $('#availForm').innerHTML = names.map((n, i) => `<div class="av-row"><label for="av${i + 1}">${n}</label><input id="av${i + 1}" data-wd="${i + 1}" value="${esc((r.week[String(i + 1)] || []).map(w => w.join('-')).join(', '))}" placeholder="dam olish"></div>`).join('')
      + `<div class="grid3 tight"><label>Slot (daqiqa)<select id="avSlot">${[15, 20, 30, 45, 60, 90, 120].map(v => `<option${v === r.slot_minutes ? ' selected' : ''}>${v}</option>`).join('')}</select></label>
         <label>Eng erta (soat oldin)<input id="avLead" type="number" min="0" max="72" value="${r.lead_hours}"></label><label>Necha kun oldinga<input id="avHor" type="number" min="1" max="120" value="${r.horizon_days}"></label>
         <label title="Himoya: 24 soatda shuncha tasdiqlanmagan bron to‘plansa, onlayn bron vaqtincha yopiladi (spam-flood). Bronlarni tasdiqlash/bekor qilish hisobni kamaytiradi.">Kunlik bron chegarasi<input id="avCap" type="number" min="10" max="500" value="${r.daily_cap || 60}"></label></div>
         <div class="row"><button type="button" class="btn primary" id="avSave">Ish vaqtini saqlash</button></div>
         ${d.closed.length ? `<p class="small"><b>Yopiq kunlar:</b> ${d.closed.map(c => esc(c.date) + (c.note ? ' (' + esc(c.note) + ')' : '')).join(', ')}</p>` : ''}`;
    $('#avSave').addEventListener('click', async () => {
      const week = {};
      try {
        document.querySelectorAll('[data-wd]').forEach(i => {
          week[i.dataset.wd] = i.value.split(',').map(s => s.trim()).filter(Boolean).map(s => { const m = s.match(/^(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})$/); if (!m) throw new Error('Format: 09:00-19:00 — ' + s); return [m[1].padStart(5, '0'), m[2].padStart(5, '0')]; });
        });
        await post('admin/availability/save', { rules: { slot_minutes: Number($('#avSlot').value), lead_hours: Number($('#avLead').value), horizon_days: Number($('#avHor').value), daily_cap: Number($('#avCap').value), week } });
        toast('Ish vaqti saqlandi'); loadCal();
      } catch (e) { fail(e); }
    });
  }

  Object.assign(window.DARIKO_ADMIN_TABS, { calendar: () => { loadCal(); loadAvail(); } });
})();

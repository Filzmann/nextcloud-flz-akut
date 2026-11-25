(function () {
    'use strict'
    const root = document.getElementById('flz-akut-app')
    if (!root) return
    const status = document.getElementById('flz-akut-status')
    const body = document.getElementById('flz-akut-shifts')
    const form = document.getElementById('flz-akut-create')
    const dialog = document.getElementById('flz-akut-create-dialog')
    const actionDialog = document.getElementById('flz-akut-action-dialog')
    const actionForm = document.getElementById('flz-akut-action-form')
    const actionTitle = document.getElementById('flz-akut-action-title')
    const actionFields = document.getElementById('flz-akut-action-fields')
    const rowsBody = document.getElementById('flz-akut-create-rows')
    let currentAction = null

    function rowHtml(values, index) {
        const options = state.teams.map(item => `<option value="${esc(item.teamCode)}" data-bureau="${esc(item.bureauKey)}" ${values.teamCode === item.teamCode ? 'selected' : ''} ${can('create', item.bureauKey) ? '' : 'disabled'}>${esc(item.displayName)}</option>`).join('')
        return `<tr><td><select name="teamCode" aria-label="ASN-Team Zeile ${index + 1}">${options}</select></td><td><input name="date" type="date" required value="${esc(values.date || '')}"></td><td><input name="startTime" type="time" required value="${esc(values.startTime || '')}"></td><td><input name="endTime" type="time" required value="${esc(values.endTime || '')}"></td><td><input name="comment" maxlength="2000" value="${esc(values.comment || '')}"></td><td><button type="button" data-row-remove data-index="${index}" aria-label="Zeile ${index + 1} entfernen">Entfernen</button></td></tr>`
    }

    function renderRows(values) { rowsBody.innerHTML = values.map((values, index) => rowHtml(values, index)).join('') }

    function rowValues(row) {
        const select = row.querySelector('select[name=teamCode]')
        const option = select.selectedOptions[0]
        return {
            teamCode: select.value, bureau: option ? option.dataset.bureau : undefined,
            date: row.querySelector('[name=date]').value, startTime: row.querySelector('[name=startTime]').value,
            endTime: row.querySelector('[name=endTime]').value, comment: row.querySelector('[name=comment]').value,
        }
    }
    let state = {teams: [], shifts: [], permissions: {}}
    const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot', "'": '&#039;'}[character]))
    const url = path => OC.generateUrl('/apps/flz_akut' + path)
    const can = (capability, bureauKey) => Array.isArray(state.permissions?.[capability]) && state.permissions[capability].includes(bureauKey)

    async function api(path, options = {}) {
        const response = await fetch(url(path), {credentials: 'same-origin', headers: {'Content-Type': 'application/json', requesttoken: OC.requestToken}, ...options})
        const data = await response.json()
        if (!response.ok || data.ok === false) throw new Error(data.error || 'Aktion fehlgeschlagen.')
        return data
    }

    function team(code) { return state.teams.find(item => item.teamCode === code) }
    function urgency(shift) {
        if (shift.urgency === 'sz') return ['urgency-sz', 'SZ – heute bis nächster Büroarbeitstag']
        if (shift.urgency === 'week') return ['urgency-week', 'Offene Schicht innerhalb der kommenden Woche']
        if (shift.urgency === 'unavailable') return ['', 'SZ-Prüfung nicht verfügbar']
        return ['', 'Keine besondere Dringlichkeitsmarkierung']
    }
    function actionButton(capability, bureauKey, action, id, label) {
        return can(capability, bureauKey) ? `<button type="button" data-action="${action}" data-id="${id}">${label}</button>` : ''
    }

    const fmtDate = value => { const date = new Date(value); return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('de-DE', {day: '2-digit', month: '2-digit'}) }
    const fmtTime = value => { const date = new Date(value); return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'}) }

    function render() {
        if (rowsBody && !rowsBody.innerHTML) renderRows([{}])
        if (!body) {
            const params = new URLSearchParams(location.search)
            if (params.get('team')) renderSearch(params.get('team'))
            return
        }
        const groups = [...state.teams]
        for (const shift of state.shifts) if (!groups.some(item => item.teamCode === shift.team_code)) groups.push({teamCode: shift.team_code, displayName: shift.team_code, bureauKey: shift.bureau_key, assistants: []})
        groups.sort((a, b) => a.displayName.localeCompare(b.displayName, 'de'))
        body.innerHTML = groups.map(group => {
            const shifts = state.shifts.filter(shift => shift.team_code === group.teamCode)
            if (!shifts.length) return ''
            const rows = shifts.map(shift => {
                const urgencyState = urgency(shift)
                const dateClass = shift.hycare_at ? 'documented-hycare' : ''
                const nameClass = shift.state === 'mediated' && !shift.paper_at ? 'pending-paper' : urgencyState[0]
                const actions = shift.state === 'open'
                    ? [actionButton('assign', shift.bureau_key, 'accept', shift.id, 'AS eintragen'), actionButton('split', shift.bureau_key, 'split', shift.id, 'Teilen')].filter(Boolean).join(' ')
                    : actionButton('reopen', shift.bureau_key, 'reopen', shift.id, 'Erneut öffnen')
                const documentation = [
                    actionButton('confirmHycare', shift.bureau_key, 'hycare', shift.id, shift.hycare_at ? 'HyCare ✓' : 'In HyCare eingetragen'),
                    actionButton('confirmPaper', shift.bureau_key, 'paper', shift.id, shift.paper_at ? 'Akte ✓' : 'In Akte eingetragen'),
                ].filter(Boolean).join(' ')
                const status = `${esc(shift.assigned_uid || 'Noch offen')} · ${esc(shift.state)}${shift.urgency === 'sz' ? ' · SZ' : ''}`
                return `<tr><td class="${dateClass}" title="${esc(shift.hycare_at ? 'In HyCare eingetragen' : urgencyState[1])}">${fmtDate(shift.starts_at)}</td><td>${fmtTime(shift.starts_at)}</td><td>${fmtTime(shift.ends_at)}</td><td class="${nameClass}" title="${esc(shift.paper_at ? 'In Papierakte eingetragen' : urgencyState[1])}">${status}<br>${actions || '—'} ${documentation || ''}</td></tr>`
            }).join('')
            return `<tr class="asn-group"><th colspan="3">${esc(group.displayName)}</th><td><button type="button" data-action="search" data-team="${esc(group.teamCode)}">Suchbogen öffnen</button></td></tr>${rows}`
        }).join('')
        const params = new URLSearchParams(location.search)
        if (params.get('team')) renderSearch(params.get('team'))
    }

    function renderSearch(code) {
        const selectedTeam = team(code)
        const section = document.getElementById('flz-akut-search-sheet')
        if (!selectedTeam || !section) return
        section.hidden = false
        document.getElementById('search-sheet-title').textContent = 'Suchbogen ' + selectedTeam.displayName
        const shifts = state.shifts.filter(shift => shift.team_code === code && shift.state === 'open')
        document.getElementById('flz-akut-search-head').innerHTML = '<tr><th>Assistenz</th><th>Telefon/SMS und Bemerkung</th>' + shifts.map(shift => `<th>${esc(shift.starts_at)}</th>`).join('') + '</tr>'
        document.getElementById('flz-akut-search-body').innerHTML = selectedTeam.assistants.map(assistant => `<tr><th>${esc(assistant.displayName)}</th><td>${shifts.map(shift => can('contact', shift.bureau_key) ? `<button type="button" data-action="contact" data-id="${shift.id}" data-uid="${esc(assistant.uid)}">Weiteren Versuch eintragen</button>` : '—').join(' ')}</td>${shifts.map(() => '<td>Status offen</td>').join('')}</tr>`).join('')
    }

    async function load() {
        try {
            const data = await api('/api/state')
            state = data
            render()
            status.textContent = data.providerStatus === 'AVAILABLE' ? 'Bereit.' : 'Planerdaten sind nicht verfügbar.'
        } catch (error) { status.textContent = error.message }
    }

    if (form) form.addEventListener('submit', async event => {
        event.preventDefault()
        const entries = []
        for (const row of Array.from(rowsBody.querySelectorAll('tr'))) {
            const values = rowValues(row)
            if (!values.bureau || !can('create', values.bureau)) continue
            if (!/^\d{4}-\d{2}-\d{2}$/.test(values.date) || !values.startTime || !values.endTime) { status.textContent = 'Jede Zeile braucht Datum und beide Zeitfelder.'; return }
            entries.push({teamCode: values.teamCode, bureauKey: values.bureau, startsAt: new Date(values.date + 'T' + values.startTime).toISOString(), endsAt: new Date(values.date + 'T' + values.endTime).toISOString(), comment: values.comment})
        }
        if (!entries.length) { status.textContent = 'Mindestens eine Zeile mit ASN-Team des eigenen Büros ist erforderlich.'; return }
        try {
            for (const entry of entries) await api('/api/shifts', {method: 'POST', body: JSON.stringify(entry)})
            renderRows([{}])
            dialog.close()
            await load()
        } catch (error) { status.textContent = error.message }
    })

    function openActionDialog(type, id, uid) {
        currentAction = {type, id, uid}
        actionTitle.textContent = {split: 'Schicht teilen', accept: 'AS eintragen', contact: 'Kontaktversuch eintragen'}[type] || 'Aktion'
        if (type === 'split') {
            actionFields.innerHTML = '<label>Teilungsdatum <input name="date" type="date" required></label><label>Teilungszeit <input name="time" type="time" required></label>'
        } else if (type === 'accept') {
            const shift = state.shifts.find(item => Number(item.id) === id)
            const assignedTeam = shift ? team(shift.team_code) : null
            const options = assignedTeam ? assignedTeam.assistants.map(assistant => `<option value="${esc(assistant.uid)}">${esc(assistant.displayName)} (${esc(assistant.uid)})</option>`).join('') : ''
            actionFields.innerHTML = `<label>Assistenz <select name="assistantUid" required>${options}</select></label><label>MA-Kürzel / Beteiligte <input name="maLabel" maxlength="255"></label>`
        } else if (type === 'contact') {
            actionFields.innerHTML = '<label>Kanal <select name="channel" required><option value="phone">Telefon</option><option value="sms">SMS</option></select></label><label>Ergebnis <select name="outcome" required><option value="open">offen</option><option value="accepted">angenommen</option><option value="declined">abgelehnt</option></select></label><label>Bemerkung <input name="note" maxlength="500"></label>'
        }
        actionDialog.showModal()
    }

    actionForm.addEventListener('submit', async event => {
        event.preventDefault()
        if (!currentAction) return
        const data = new FormData(actionForm)
        try {
            if (currentAction.type === 'split') {
                const date = String(data.get('date') || '')
                const time = String(data.get('time') || '')
                if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !time) { status.textContent = 'Teilungsdatum und -zeit sind erforderlich.'; return }
                await api(`/api/shifts/${currentAction.id}/split`, {method: 'POST', body: JSON.stringify({cutPointsJson: JSON.stringify([new Date(date + 'T' + time).toISOString()])})})
            } else if (currentAction.type === 'accept') {
                await api(`/api/shifts/${currentAction.id}/accept`, {method: 'POST', body: JSON.stringify({assistantUid: data.get('assistantUid'), maLabel: data.get('maLabel') || '', mediatedAt: new Date().toISOString(), informedAsn: false})})
            } else if (currentAction.type === 'contact') {
                await api(`/api/shifts/${currentAction.id}/contacts`, {method: 'POST', body: JSON.stringify({assistantUid: currentAction.uid, channel: data.get('channel'), outcome: data.get('outcome'), contactedAt: new Date().toISOString(), reason: '', note: data.get('note') || ''})})
            }
            actionForm.reset()
            actionDialog.close()
            await load()
        } catch (error) { status.textContent = error.message }
    })

    root.addEventListener('click', async event => {
        if (event.target.closest('#flz-akut-open-create')) { dialog.showModal(); return }
        if (event.target.closest('#flz-akut-close-create')) { dialog.close(); return }
        if (event.target.closest('#flz-akut-action-close')) { actionDialog.close(); return }
        if (event.target.closest('#flz-akut-add-row')) {
            const rows = Array.from(rowsBody.querySelectorAll('tr'))
            const values = rows.map(rowValues)
            values.push({...(values[values.length - 1] || {})})
            renderRows(values)
            return
        }
        const removeButton = event.target.closest('[data-row-remove]')
        if (removeButton) {
            const rows = Array.from(rowsBody.querySelectorAll('tr'))
            if (rows.length > 1) rows[Number(removeButton.dataset.index)].remove()
            return
        }
        const button = event.target.closest('button[data-action]')
        if (!button) return
        if (button.dataset.action === 'search') { window.open(OC.generateUrl('/apps/flz_akut/suchbogen?team=' + encodeURIComponent(button.dataset.team)), '_blank', 'noopener'); return }
        const id = Number(button.dataset.id)
        if (button.dataset.action === 'split' || button.dataset.action === 'accept' || button.dataset.action === 'contact') { openActionDialog(button.dataset.action, id, button.dataset.uid); return }
        try {
            if (button.dataset.action === 'reopen') {
                await api(`/api/shifts/${id}/reopen`, {method: 'POST', body: '{}'})
            } else if (button.dataset.action === 'hycare' || button.dataset.action === 'paper') {
                await api(`/api/shifts/${id}/confirm`, {method: 'POST', body: JSON.stringify({kind: button.dataset.action, at: new Date().toISOString()})})
            }
            await load()
        } catch (error) { status.textContent = error.message }
    })
    load()
})()

(function () {
    'use strict'
    const root = document.getElementById('flz-akut-app')
    if (!root) return
    const status = document.getElementById('flz-akut-status')
    const body = document.getElementById('flz-akut-shifts')
    const form = document.getElementById('flz-akut-create')
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

    function render() {
        const select = form.elements.teamCode
        select.innerHTML = state.teams.map(item => `<option value="${esc(item.teamCode)}" data-bureau="${esc(item.bureauKey)}" ${can('create', item.bureauKey) ? '' : 'disabled'}>${esc(item.displayName)}</option>`).join('')
        const submit = form.querySelector('button[type="submit"]')
        if (submit) submit.disabled = !state.teams.some(item => can('create', item.bureauKey))
        body.innerHTML = state.shifts.map(shift => {
            const assignedTeam = team(shift.team_code) || {displayName: shift.team_code}
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
            return `<tr><td>${esc(assignedTeam.displayName)}</td><td class="${dateClass}" title="${esc(shift.hycare_at ? 'In HyCare eingetragen' : urgencyState[1])}">${esc(shift.starts_at)} – ${esc(shift.ends_at)}</td><td class="${nameClass}" title="${esc(shift.paper_at ? 'In Papierakte eingetragen' : urgencyState[1])}">${esc(shift.assigned_uid || 'Noch offen')}<br>${actions || '—'}</td><td>${esc(shift.state)}${shift.urgency === 'sz' ? ' · SZ' : ''}</td><td>${documentation || '—'}</td><td><a target="_blank" rel="noopener" href="?team=${encodeURIComponent(shift.team_code)}#suchbogen">Suchbogen öffnen</a></td></tr>`
        }).join('')
        const params = new URLSearchParams(location.search)
        if (params.get('team')) renderSearch(params.get('team'))
    }

    function renderSearch(code) {
        const selectedTeam = team(code)
        if (!selectedTeam) return
        const section = document.getElementById('flz-akut-search-sheet')
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

    form.addEventListener('submit', async event => {
        event.preventDefault()
        const data = new FormData(form)
        const selected = form.elements.teamCode.selectedOptions[0]
        if (!selected || !can('create', selected.dataset.bureau)) return
        try {
            await api('/api/shifts', {method: 'POST', body: JSON.stringify({teamCode: data.get('teamCode'), bureauKey: selected.dataset.bureau, startsAt: new Date(data.get('startsAt')).toISOString(), endsAt: new Date(data.get('endsAt')).toISOString(), comment: data.get('comment')})})
            form.reset()
            await load()
        } catch (error) { status.textContent = error.message }
    })

    root.addEventListener('click', async event => {
        const button = event.target.closest('button[data-action]')
        if (!button) return
        const id = Number(button.dataset.id)
        try {
            if (button.dataset.action === 'split') {
                const cut = prompt('Teilungszeitpunkt als Datum und Uhrzeit (ISO):')
                if (!cut) return
                await api(`/api/shifts/${id}/split`, {method: 'POST', body: JSON.stringify({cutPointsJson: JSON.stringify([new Date(cut).toISOString()])})})
            } else if (button.dataset.action === 'accept') {
                const shift = state.shifts.find(item => Number(item.id) === id)
                const assignedTeam = team(shift.team_code)
                const uid = prompt('UID der Assistenz:\n' + assignedTeam.assistants.map(assistant => `${assistant.uid} – ${assistant.displayName}`).join('\n'))
                if (!uid) return
                const maLabel = prompt('MA-Kürzel / Beteiligte:', '') || ''
                await api(`/api/shifts/${id}/accept`, {method: 'POST', body: JSON.stringify({assistantUid: uid, maLabel, mediatedAt: new Date().toISOString(), informedAsn: false})})
            } else if (button.dataset.action === 'reopen') {
                await api(`/api/shifts/${id}/reopen`, {method: 'POST', body: '{}'})
            } else if (button.dataset.action === 'hycare' || button.dataset.action === 'paper') {
                await api(`/api/shifts/${id}/confirm`, {method: 'POST', body: JSON.stringify({kind: button.dataset.action, at: new Date().toISOString()})})
            } else if (button.dataset.action === 'contact') {
                const channel = prompt('Kanal: phone oder sms', 'phone')
                if (!channel) return
                const outcome = prompt('Ergebnis: open, accepted oder declined', 'open')
                if (!outcome) return
                const note = prompt('Optionale kurze Bemerkung', '') || ''
                await api(`/api/shifts/${id}/contacts`, {method: 'POST', body: JSON.stringify({assistantUid: button.dataset.uid, channel, outcome, contactedAt: new Date().toISOString(), reason: '', note})})
            }
            await load()
        } catch (error) { status.textContent = error.message }
    })
    load()
})()

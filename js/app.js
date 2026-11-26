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
    const mailDialog = document.getElementById('flz-akut-mail-dialog')
    const smsDialog = document.getElementById('flz-akut-sms-dialog')
    const smsText = document.getElementById('flz-akut-sms-text')
    const asinfoDialog = document.getElementById('flz-akut-asinfo-dialog')
    const asinfoForm = document.getElementById('flz-akut-asinfo-form')
    const asinfoTitle = document.getElementById('flz-akut-asinfo-title')
    const asinfoNote = document.getElementById('flz-akut-asinfo-note')
    const mailText = document.getElementById('flz-akut-mail-text')
    const mailRecipients = document.getElementById('flz-akut-mail-recipients')
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
    // Live-Polling: die Akutvermittlung wird permanent von mehreren Personen bedient, deshalb wird der Zustand regelmäßig neu geladen (vgl. Nextcloud Calc). Pausiert bei verstecktem Tab, offenem Dialog oder Feld-Fokus; Re-Render nur bei geändertem Zustand.
    const POLL_INTERVAL_MS = 10000
    let lastStateSignature = ''
    let statusBase = ''
    const editingActive = () => {
        const el = document.activeElement
        return Boolean(el && /^(INPUT|TEXTAREA|SELECT)$/.test(String(el.tagName || '')) && (!root.contains || root.contains(el)))
    }
    const pollPaused = () => Boolean(document.hidden) || Boolean(document.querySelector && document.querySelector('dialog[open]')) || editingActive()
    async function poll() {
        if (pollPaused()) return
        try {
            const data = await api('/api/state')
            const signature = JSON.stringify(data)
            if (signature !== lastStateSignature) {
                lastStateSignature = signature
                state = data
                render()
            }
            statusBase = data.providerStatus === 'AVAILABLE' ? 'Bereit.' : 'Planerdaten sind nicht verfügbar.'
            const time = new Intl.DateTimeFormat(locale(), {timeZone: tz(), hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'}).format(new Date())
            status.textContent = `${statusBase} · Live alle ${Math.round(POLL_INTERVAL_MS / 1000)} s, zuletzt ${time}`
        } catch (error) { /* Poll-Fehler brechen die Live-Aktualisierung nicht ab */ }
    }
    const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot', "'": '&#039;'}[character]))
    const url = path => OC.generateUrl('/apps/flz_akut' + path)
    const can = (capability, bureauKey) => Array.isArray(state.permissions?.[capability]) && state.permissions[capability].includes(bureauKey)
    const minutesToTime = minutes => String(Math.floor((minutes % 1440) / 60)).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0')
    // Zeitzone: Instanz-Zeitzone aus den Nextcloud-Systemeinstellungen (über /api/state als state.timezone), Fallback UTC
    const tz = () => String(state.timezone || 'UTC')
    // Locale: Nextcloud-Locale (OC.getLocale(), z. B. de_DE), Fallback de-DE — keine eigene Locale-Einstellung
    const locale = () => String(typeof OC !== 'undefined' && OC.getLocale ? OC.getLocale() : 'de_DE').replace('_', '-')
    const pad = n => String(n).padStart(2, '0')
    const tzParts = date => { const p = {}; for (const part of new Intl.DateTimeFormat('en-US', {timeZone: tz(), hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'}).formatToParts(date)) p[part.type] = part.value; return {year: Number(p.year), month: Number(p.month), day: Number(p.day), hour: Number(p.hour) % 24, minute: Number(p.minute)} }
    // Wandelt eine in der Instanz-Zeitzone liegende Wandzeit (y-mo-d h:mi) in den absoluten UTC-Zeitpunkt um
    const zonedToUtc = (y, mo, d, h, mi) => { const guess = Date.UTC(y, mo - 1, d, h, mi); const p = tzParts(new Date(guess)); const asUtc = Date.UTC(p.year, p.month - 1, p.day, p.hour, p.minute); return new Date(guess - (asUtc - guess)) }
    const parseZoned = value => { const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(value); return m ? zonedToUtc(Number(m[1]), Number(m[2]), Number(m[3]), Number(m[4]), Number(m[5])) : new Date(value) }
    const today = () => { const p = tzParts(new Date()); return `${p.year}-${pad(p.month)}-${pad(p.day)}` }
    const shiftById = id => state.shifts.find(item => Number(item.id) === Number(id))

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
    function actionButton(capability, bureauKey, action, id, label, title = '', pressed = false) {
        return can(capability, bureauKey) ? `<button type="button" data-action="${action}" data-id="${id}"${title ? ` title="${esc(title)}"` : ''}${pressed ? ' aria-pressed="true"' : ''}>${label}</button>` : ''
    }

    const fmtDate = value => { const date = new Date(value); return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat(locale(), {timeZone: tz(), day: '2-digit', month: '2-digit'}).format(date) }
    const fmtTime = value => { const date = new Date(value); return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat(locale(), {timeZone: tz(), hour: '2-digit', minute: '2-digit', hourCycle: 'h23'}).format(date) }
    const fmtDateTime = value => { const date = new Date(value); return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat(locale(), {timeZone: tz(), day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23'}).format(date) }
    // Kurze Zeit für SMS: volle Stunden ohne Minuten (10:00 -> 10)
    const fmtTimeShort = value => { const t = fmtTime(value); return t.endsWith(':00') ? t.slice(0, -3).replace(/^0(?=\d)/, '') : t }
    const firstName = name => { const first = String(name || '').trim().split(/\s+/)[0]; return first || String(name || '') }
    const TAG_LABELS = {tag: 'nur Tag', nacht: 'nur Nacht', frueh: 'nur Früh', spaet: 'nur Spät'}
    // Hover-Text des AS-Info-Icons: Verfügbarkeit + Freitext
    function infoTitleText(assistant) {
        const info = assistant.info || {tags: [], note: ''}
        const tags = (info.tags || []).map(tag => TAG_LABELS[tag] || tag).join(', ')
        const note = info.note ? ` — ${info.note}` : ''
        return tags || note ? `AS-Info: ${tags}${note}` : 'Keine AS-Info hinterlegt'
    }
    // Vorname/Spitzname der eingeloggten Person als Absender-Signatur
    function senderName() {
        const user = typeof OC !== 'undefined' && typeof OC.getCurrentUser === 'function' ? OC.getCurrentUser() : null
        return user ? firstName(user.displayName || user.uid || '') : ''
    }
    const fmtMediated = value => {
        const date = new Date(value)
        if (Number.isNaN(date.getTime())) return String(value)
        const p = tzParts(date)
        return `${pad(p.day)}.${pad(p.month)}/${pad(p.hour)}:${pad(p.minute)}`
    }

    // Tag/Nacht-Icon: Nachtschicht = mehr als die Hälfte der Schichtdauer liegt zwischen 00:00 und 06:00 Uhr (Instanz-Zeitzone)
    const dayNight = shift => {
        const start = new Date(shift.starts_at), end = new Date(shift.ends_at)
        if (isNaN(start.getTime()) || isNaN(end.getTime()) || end <= start) return {night: false, label: 'Tag'}
        let nightMs = 0
        let cursor = start
        while (cursor < end) {
            const d = tzParts(cursor)
            const nightStart = zonedToUtc(d.year, d.month, d.day, 0, 0)
            const nightEnd = zonedToUtc(d.year, d.month, d.day, 6, 0)
            const overlap = Math.min(end.getTime(), nightEnd.getTime()) - Math.max(start.getTime(), nightStart.getTime())
            if (overlap > 0) nightMs += overlap
            cursor = zonedToUtc(d.year, d.month, d.day + 1, 0, 0)
        }
        const night = nightMs * 2 > (end.getTime() - start.getTime())
        const sp = tzParts(start), ep = tzParts(end)
        const crosses = sp.year !== ep.year || sp.month !== ep.month || sp.day !== ep.day
        return {night, label: (night ? 'Nacht' : 'Tag') + (crosses ? ' — endet am Folgetag' : '')}
    }
    const dayNightIcon = shift => {
        const dn = dayNight(shift)
        return dn.night
            ? `<svg class="daynight-icon" viewBox="0 0 24 24" role="img" aria-label="${esc(dn.label)}"><title>${esc(dn.label)}</title><path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"/></svg>`
            : `<svg class="daynight-icon" viewBox="0 0 24 24" role="img" aria-label="${esc(dn.label)}"><title>${esc(dn.label)}</title><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>`
    }
    // Kapazitäten aus flz_kapazitaet (read-only V1): Kalendertage (Instanz-Zeitzone), die eine Schicht überlappt
    const shiftDays = shift => {
        const start = new Date(shift.starts_at), end = new Date(shift.ends_at)
        if (isNaN(start.getTime()) || isNaN(end.getTime()) || end <= start) return []
        const days = []
        let cursor = start
        while (cursor < end) {
            const d = tzParts(cursor)
            const key = d.year + '-' + pad(d.month) + '-' + pad(d.day)
            if (!days.includes(key)) days.push(key)
            cursor = zonedToUtc(d.year, d.month, d.day + 1, 0, 0)
        }
        return days
    }
    // Abgleichregel wie OCA\FlzKapazitaet\Domain\CapacityMatching: '' = alle Schichten des Tages; Komma-Liste (z. B. 'frueh,nacht') mit ODER-Semantik; nacht = mehr als die Hälfte in 00–06 Uhr, tag = nicht nacht, frueh/spaet nach Schichtbeginn vor/ab 12:00 Uhr
    const restrictionMatches = (restriction, shift) => {
        const r = String(restriction || '').toLowerCase()
        if (r === '') return true
        const night = dayNight(shift).night
        const hour = tzParts(new Date(shift.starts_at)).hour
        return r.split(',').some(part => {
            part = part.trim()
            if (part === 'nacht') return night
            if (part === 'tag') return !night
            if (part === 'frueh') return hour < 12
            if (part === 'spaet') return hour >= 12
            return false
        })
    }
    const capacityFor = (uid, shift) => (state.capacities || []).find(cap => cap.uid === uid && shiftDays(shift).includes(cap.day) && restrictionMatches(cap.restriction, shift)) || null
    const capacityBadge = (uid, shift) => {
        const cap = capacityFor(uid, shift)
        if (!cap) return ''
        const parts = String(cap.day).split('-')
        const r = String(cap.restriction || '')
        const label = r === '' ? 'alle Schichten' : r.split(',').map(p => ({frueh: 'Früh', spaet: 'Spät', tag: 'Tag', nacht: 'Nacht'})[p] || p).join(' + ')
        const letter = r === '' ? '•' : r.split(',').map(p => ({frueh: 'F', spaet: 'S', tag: 'T', nacht: 'N'})[p] || '?').join('+')
        return `<span class="capacity-badge" title="Kapazität ${parts[2]}.${parts[1]}.${parts[0]} (${label})${cap.note ? ' – ' + esc(cap.note) : ''}">${letter}</span>`
    }

    // Split-Ursprünge und Schichten, die vor Bürotagbeginn (konfiguriert, Standard 6:00 Uhr in der Instanz-Zeitzone) beginnen, werden nicht mehr angezeigt — besetzt werden muss eine Schicht vor ihrem Beginn, deshalb zählt starts_at, nicht ends_at
    const officeDayStart = () => { const p = tzParts(new Date()); const m = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(String(state.officeDayStart || '06:00')); return zonedToUtc(p.year, p.month, p.day, m ? Number(m[1]) : 6, m ? Number(m[2]) : 0) }
    const isListed = shift => shift.state !== 'split' && new Date(shift.starts_at) >= officeDayStart()

    function render() {
        if (rowsBody && !rowsBody.innerHTML) renderRows([{date: today()}])
        if (!body) {
            const params = new URLSearchParams(location.search)
            if (params.get('team')) renderSearch(params.get('team'))
            return
        }
        const visibleShifts = state.shifts.filter(isListed)
        const groups = [...state.teams]
        for (const shift of visibleShifts) if (!groups.some(item => item.teamCode === shift.team_code)) groups.push({teamCode: shift.team_code, displayName: shift.team_code, bureauKey: shift.bureau_key, assistants: []})
        groups.sort((a, b) => a.displayName.localeCompare(b.displayName, 'de'))
        body.innerHTML = groups.map(group => {
            const shifts = visibleShifts.filter(shift => shift.team_code === group.teamCode)
            if (!shifts.length) return ''
            const rows = shifts.map(shift => {
                const urgencyState = urgency(shift)
                const dateClass = shift.hycare_at ? 'documented-hycare' : ''
                const nameClass = shift.state === 'mediated' && !shift.paper_at ? 'pending-paper' : urgencyState[0]
                const started = new Date(shift.starts_at) <= new Date()
                const editable = (shift.state === 'open' || shift.state === 'mediated') && !started
                const locked = Boolean(shift.hycare_at && shift.paper_at)
                const canAssign = can('assign', shift.bureau_key)
                const canCreate = can('create', shift.bureau_key)
                const assignedTeam = team(shift.team_code)
                const own = assignedTeam ? assignedTeam.assistants : []
                const substitutes = state.teams.filter(item => item.teamCode !== shift.team_code).flatMap(item => item.assistants)
                const options = [...own, ...substitutes].map(assistant => `<option value="${esc(assistant.uid)}">${esc(assistant.displayName)} (${esc(assistant.uid)})</option>`).join('')
                const listId = `flz-akut-asn-suggestions-${shift.id}`
                const asInput = `<input type="search" name="assistantUid" data-field="assistantUid" data-id="${shift.id}" list="${listId}" value="${esc(shift.assigned_uid || '')}" placeholder="AS oder Text (z. B. abgesagt)" aria-label="Assistenz Schicht ${shift.id}"${editable && canAssign && !locked ? '' : ' disabled'}><datalist id="${listId}">${options}</datalist>`
                const maInput = `<input name="maLabel" data-field="maLabel" data-id="${shift.id}" maxlength="6" pattern="[A-Za-zÄÖÜäöüß]{3,6}" size="6" value="${esc(shift.ma_label || '')}" aria-label="Kürzel Schicht ${shift.id}" title="Kürzel: 3–6 Buchstaben"${editable && canAssign ? '' : ' disabled'}>`
                const commentInput = `<input name="comment" data-field="comment" data-id="${shift.id}" maxlength="2000" value="${esc(shift.comment || '')}" aria-label="Kommentar Schicht ${shift.id}"${editable && canCreate ? '' : ' disabled'}>`
                const actions = shift.state === 'open' ? actionButton('split', shift.bureau_key, 'split', shift.id, 'Teilen') : ''
                const reopenIcon = shift.state === 'mediated' && shift.assigned_uid && can('reopen', shift.bureau_key)
                    ? `<button type="button" class="reopen-icon" data-action="reopen" data-id="${shift.id}" title="Erneut öffnen" aria-label="Erneut öffnen"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button>`
                    : ''
                const hycareButton = shift.assigned_uid ? actionButton('confirmHycare', shift.bureau_key, 'hycare', shift.id, 'HyCare', shift.hycare_at ? 'HyCare eingetragen' : 'Noch nicht in HyCare eingetragen', Boolean(shift.hycare_at)) : ''
                const paperButton = shift.assigned_uid ? actionButton('confirmPaper', shift.bureau_key, 'paper', shift.id, 'Akte', shift.paper_at ? 'In Akte eingetragen' : 'Noch nicht in Akte eingetragen', Boolean(shift.paper_at)) : ''
                const szBadge = shift.urgency === 'sz' ? `<span class="sz-badge" title="${esc(urgencyState[1])}">SZ</span>` : ''
                const mediatedCell = shift.mediated_at ? `<span class="mediated-at" title="Vermittelt am ${esc(fmtDateTime(shift.mediated_at))} Uhr">${esc(fmtMediated(shift.mediated_at))}</span>` : '—'
                const startDate = new Date(shift.starts_at), endDate = new Date(shift.ends_at)
                const endCell = `${fmtTime(shift.ends_at)} ${dayNightIcon(shift)}`
                return `<tr><td class="${dateClass}" title="${esc(shift.hycare_at ? 'In HyCare eingetragen' : urgencyState[1])}">${fmtDate(shift.starts_at)}</td><td class="${dateClass}">${fmtTime(shift.starts_at)}</td><td class="${dateClass}" title="${esc(dayNight(shift).label)}">${endCell}</td><td class="${nameClass} as-cell" title="${esc(shift.paper_at ? 'In Papierakte eingetragen' : urgencyState[1])}"><div class="as-line">${szBadge}<span class="as-field">${asInput}${reopenIcon}</span>${actions}</div></td><td class="mediated-cell">${mediatedCell}</td><td>${maInput}</td><td>${hycareButton || '—'}</td><td>${paperButton || '—'}</td><td>${commentInput}</td></tr>`
            }).join('')
            const mailButton = can('read', group.bureauKey) ? `<button type="button" data-action="mail" data-team="${esc(group.teamCode)}">Mail</button>` : ''
            return `<tr class="asn-group"><th colspan="7">${esc(group.displayName)}</th><td colspan="2"><button type="button" data-action="search" data-team="${esc(group.teamCode)}">Suchbogen öffnen</button> ${mailButton}</td></tr>${rows}`
        }).join('')
        const params = new URLSearchParams(location.search)
        if (params.get('team')) renderSearch(params.get('team'))
    }

    let searchTeamCode = null
    let manualRows = [] // manuell ergänzte AS-Zeilen im Suchbogen (nur Sitzung)
    function renderSearch(code) {
        const selectedTeam = team(code)
        const section = document.getElementById('flz-akut-search-sheet')
        if (!selectedTeam || !section) return
        searchTeamCode = code
        section.hidden = false
        document.getElementById('search-sheet-title').textContent = 'Suchbogen ' + selectedTeam.displayName
        // Nur offene, sichtbare Schichten des Teams; besetzte (vermittelte) Schichten verschwinden aus dem Suchbogen
        const shifts = state.shifts.filter(shift => shift.team_code === code && shift.state === 'open' && isListed(shift))
        // Sammelanzeige aller offenen Schichten über der Tabelle
        const summary = document.getElementById('flz-akut-search-summary')
        if (summary) summary.innerHTML = shifts.length
            ? shifts.map(shift => `<span class="search-shift-chip">${esc(fmtDate(shift.starts_at))} ${esc(fmtTime(shift.starts_at))}–${esc(fmtTime(shift.ends_at))} ${dayNightIcon(shift)}</span>`).join(' ')
            : '<span class="search-empty">Keine offenen Schichten.</span>'
        // Spalten: Name links fixiert, dann die offenen Schichten, Kontakt rechts fixiert
        document.getElementById('flz-akut-search-head').innerHTML = '<tr><th class="sticky-left">Assistenz</th>' + shifts.map(shift => `<th>${esc(fmtDate(shift.starts_at))} ${esc(fmtTime(shift.starts_at))}</th>`).join('') + '<th class="sticky-right sticky-tel">Tel</th><th class="sticky-right sticky-sms">SMS</th><th class="sticky-right sticky-comment">Kommentar</th></tr>'
        const assistants = [...(selectedTeam.assistants || []), ...manualRows]
        document.getElementById('flz-akut-search-body').innerHTML = assistants.map(assistant => {
            // Nur Assistentinnen des Teams; Urlaub/Abwesenheit (falls Provider verfügbar) wird markiert
            const absent = (state.absences || []).some(a => a.uid === assistant.uid && shifts.some(shift => new Date(a.startsAt) < new Date(shift.ends_at) && new Date(a.endsAt) > new Date(shift.starts_at)))
            const badge = absent ? ` <span class="absence-badge" title="Abwesenheit (z. B. Urlaub) überlappt offene Schichten">Urlaub</span>` : ''
            // Ja/Nein sitzen in jeder Schicht-Zelle und buchen genau diese Schicht; Tel (n.e./AB) und SMS sind Checkboxen rechts und gelten schichtübergreifend
            const canContact = can('contact', selectedTeam.bureauKey)
            const shiftCell = shift => !canContact ? `<td>${capacityBadge(assistant.uid, shift)}Status offen</td>` : `<td class="shift-contact">${capacityBadge(assistant.uid, shift)}<button type="button" data-action="contact-yes" data-id="${shift.id}" data-uid="${esc(assistant.uid)}" title="Ja (${esc(fmtDate(shift.starts_at))} ${esc(fmtTime(shift.starts_at))})">Ja</button> <button type="button" data-action="contact-no" data-id="${shift.id}" data-uid="${esc(assistant.uid)}" title="Nein (${esc(fmtDate(shift.starts_at))} ${esc(fmtTime(shift.starts_at))})">Nein</button></td>`
            const telCell = !canContact ? '<td class="sticky-right sticky-tel">—</td>' : `<td class="sticky-right sticky-tel">${contactCheckbox('tel-ne', 'n.e.', assistant.uid, c => c.channel === 'phone' && c.outcome === 'not_reached')}${contactCheckbox('tel-ab', 'AB', assistant.uid, c => c.channel === 'phone' && c.outcome === 'ab')}</td>`
            const smsCell = !canContact ? '<td class="sticky-right sticky-sms">—</td>' : `<td class="sticky-right sticky-sms">${contactCheckbox('sms', 'SMS', assistant.uid, c => c.channel === 'sms')}</td>`
            // Info-Icon je AS (Hover zeigt die Info, Stift öffnet das Bearbeitungs-Overlay); Kommentarfeld rechts für interne Notizen
            const infoTitle = infoTitleText(assistant)
            // Info-Icon (größer, klickbar) öffnet das Overlay direkt; das Overlay ist normal schreibgeschützt, der Stift-Button im Overlay schaltet die Bearbeitung frei
            const infoIcon = canContact ? `<button type="button" class="as-info" data-action="as-info" data-uid="${esc(assistant.uid)}" title="${esc(infoTitle)}" aria-label="AS-Info anzeigen für ${esc(assistant.displayName)}"><svg class="as-info-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg></button>` : ''
            const commentCell = !canContact ? '<td class="sticky-right sticky-comment">—</td>' : `<td class="sticky-right sticky-comment"><input type="text" class="as-comment" data-field="asComment" data-uid="${esc(assistant.uid)}" value="${esc(assistant.comment || '')}" maxlength="2000" title="Interner Kommentar zu ${esc(assistant.displayName)}" aria-label="Interner Kommentar zu ${esc(assistant.displayName)}"></td>`
            const manualRemove = manualRows.some(row => row.uid === assistant.uid) ? `<button type="button" class="manual-remove" data-action="manual-remove" data-uid="${esc(assistant.uid)}" title="Manuelle Zeile entfernen" aria-label="Manuelle Zeile ${esc(assistant.displayName)} entfernen">Entf.</button>` : ''
            return `<tr><th class="sticky-left">${esc(assistant.displayName)}${badge}${infoIcon}${manualRemove}</th>${shifts.map(shiftCell).join('')}${telCell}${smsCell}${commentCell}</tr>`
        }).join('')
    }

    function composeSms(assistant) {
        const selectedTeam = team(searchTeamCode)
        const shifts = state.shifts.filter(shift => shift.team_code === searchTeamCode && shift.state === 'open' && isListed(shift))
        // Kurz gehalten (unter 160 Zeichen): Empfänger mit Namen, Zeiten ohne :00-Minuten, Ende mit LG <Vorname>
        const lines = shifts.map(shift => `- ${fmtDate(shift.starts_at)} ${fmtTimeShort(shift.starts_at)}–${fmtTimeShort(shift.ends_at)} Uhr`).join('\n')
        return `Hallo ${firstName(assistant.displayName)}, offene Schichten im ASN-Team ${selectedTeam ? selectedTeam.displayName : searchTeamCode}:\n${lines || '- keine offenen Schichten'}\nBitte melde dich, wenn du eine übernehmen kannst.\nLG ${senderName()}`
    }

    // Letzter gespeicherter Kontaktzeitpunkt der Assistenz über die offenen Schichten des Teams
    function lastContactTime(uid, predicate) {
        if (!searchTeamCode) return null
        const openIds = new Set(state.shifts.filter(shift => shift.team_code === searchTeamCode && shift.state === 'open' && isListed(shift)).map(shift => Number(shift.id)))
        const hits = (state.contacts || []).filter(c => c.assistantUid === uid && openIds.has(Number(c.shiftId)) && predicate(c))
        if (!hits.length) return null
        return hits.reduce((a, b) => new Date(a.contactedAt) >= new Date(b.contactedAt) ? a : b).contactedAt
    }

    // Checkbox für schichtübergreifende Kontaktversuche; die gespeicherte Zeit ist als Hover (title) sichtbar
    function contactCheckbox(kind, label, uid, predicate) {
        const at = lastContactTime(uid, predicate)
        const checked = at ? ' checked' : ''
        const title = at ? `${label} — ${fmtDateTime(at)} Uhr (alle offenen Schichten)` : `${label} (alle offenen Schichten)`
        return `<label class="contact-check"><input type="checkbox" data-action="contact-${kind}" data-uid="${esc(uid)}"${checked} title="${esc(title)}"> ${esc(label)}</label>`
    }

    async function recordContact(kind, uid, shiftId) {
        if (!searchTeamCode) return []
        const selectedTeam = team(searchTeamCode)
        if (!selectedTeam) return []
        const mapping = {yes: ['phone', 'accepted'], no: ['phone', 'declined'], 'tel-ab': ['phone', 'ab'], 'tel-ne': ['phone', 'not_reached'], sms: ['sms', 'open']}
        const [channel, outcome] = mapping[kind] || ['phone', 'open']
        const post = async id => {
            const result = await api(`/api/shifts/${id}/contacts`, {method: 'POST', body: JSON.stringify({assistantUid: uid, channel, outcome, contactedAt: new Date().toISOString(), reason: '', note: ''})})
            const attempt = result && result.attempt ? result.attempt : null
            if (attempt) state.contacts = (state.contacts || []).concat([{shiftId: Number(attempt.shift_id), assistantUid: String(attempt.assistant_uid), channel: String(attempt.channel), outcome: String(attempt.outcome), contactedAt: String(attempt.contacted_at)}])
            return attempt
        }
        if (kind === 'tel-ab' || kind === 'tel-ne' || kind === 'sms') {
            // Tel (AB/n.e.) und SMS gelten schichtübergreifend für alle offenen Schichten des Teams
            const shifts = state.shifts.filter(shift => shift.team_code === searchTeamCode && shift.state === 'open' && isListed(shift))
            const attempts = []
            for (const shift of shifts) attempts.push(await post(shift.id))
            if (kind === 'sms') {
                // SMS öffnet zusätzlich das Vorformular-Overlay
                if (smsText) smsText.value = composeSms(selectedTeam.assistants.find(a => a.uid === uid) || {uid, displayName: uid})
                if (smsDialog) smsDialog.showModal()
            }
            return attempts
        }
        // Ja/Nein buchen genau die angeklickte Schicht
        return [await post(shiftId)]
    }

    let asInfoUid = null
    let asInfoEditMode = false
    const asinfoEdit = document.getElementById('flz-akut-asinfo-edit')
    const asinfoSave = document.getElementById('flz-akut-asinfo-save')
    function setAsInfoEditable(editable) {
        asInfoEditMode = editable
        for (const input of Array.from(asinfoForm ? asinfoForm.querySelectorAll('input[name="asinfo-tag"]') : [])) input.disabled = !editable
        if (asinfoNote) { asinfoNote.disabled = !editable; asinfoNote.readOnly = !editable }
        if (asinfoSave) asinfoSave.disabled = !editable
        if (asinfoEdit) asinfoEdit.disabled = editable
    }
    function openAsInfoDialog(uid) {
        asInfoUid = uid
        const selectedTeam = team(searchTeamCode)
        const assistant = selectedTeam ? (selectedTeam.assistants || []).find(a => a.uid === uid) : null
        if (!assistant || !asinfoDialog || !asinfoForm) return
        const info = assistant.info || {tags: [], note: ''}
        if (asinfoTitle) asinfoTitle.textContent = 'AS-Info ' + assistant.displayName
        for (const input of Array.from(asinfoForm.querySelectorAll('input[name="asinfo-tag"]'))) input.checked = (info.tags || []).includes(input.value)
        if (asinfoNote) asinfoNote.value = info.note || ''
        setAsInfoEditable(false) // Overlay startet schreibgeschützt; Stift-Button im Overlay schaltet frei
        asinfoDialog.showModal()
    }

    if (asinfoForm) asinfoForm.addEventListener('submit', async event => {
        event.preventDefault()
        if (!asInfoUid || !asInfoEditMode) return
        const tags = Array.from(asinfoForm.querySelectorAll('input[name="asinfo-tag"]:checked')).map(input => input.value)
        const note = asinfoNote ? asinfoNote.value : ''
        try {
            // AS-Info wird direkt beim AS-Nutzer gespeichert
            const result = await api(`/api/assistants/${encodeURIComponent(asInfoUid)}/meta`, {method: 'PUT', body: JSON.stringify({tagsJson: JSON.stringify(tags), note})})
            const selectedTeam = team(searchTeamCode)
            const assistant = selectedTeam ? (selectedTeam.assistants || []).find(a => a.uid === asInfoUid) : null
            if (assistant && result && result.info) assistant.info = result.info
            asinfoDialog.close()
            renderSearch(searchTeamCode)
            status.textContent = 'AS-Info gespeichert.'
        } catch (error) { status.textContent = error.message }
    })

    async function load() {
        try {
            const data = await api('/api/state')
            state = data
            lastStateSignature = JSON.stringify(data)
            render()
            statusBase = data.providerStatus === 'AVAILABLE' ? 'Bereit.' : 'Planerdaten sind nicht verfügbar.'
            status.textContent = statusBase
        } catch (error) { status.textContent = error.message }
    }

    if (form) form.addEventListener('submit', async event => {
        event.preventDefault()
        const entries = []
        for (const row of Array.from(rowsBody.querySelectorAll('tr'))) {
            const values = rowValues(row)
            if (!values.bureau || !can('create', values.bureau)) continue
            if (!/^\d{4}-\d{2}-\d{2}$/.test(values.date) || !values.startTime || !values.endTime) { status.textContent = 'Jede Zeile braucht Datum und beide Zeitfelder.'; return }
            entries.push({teamCode: values.teamCode, bureauKey: values.bureau, startsAt: parseZoned(values.date + 'T' + values.startTime).toISOString(), endsAt: parseZoned(values.date + 'T' + values.endTime).toISOString(), comment: values.comment})
        }
        if (!entries.length) { status.textContent = 'Mindestens eine Zeile mit ASN-Team des eigenen Büros ist erforderlich.'; return }
        try {
            for (const entry of entries) await api('/api/shifts', {method: 'POST', body: JSON.stringify(entry)})
            renderRows([{date: today()}])
            dialog.close()
            await load()
        } catch (error) { status.textContent = error.message }
    })

    function mailRecipientsFor(teamCode) {
        const assignedTeam = team(teamCode)
        const own = assignedTeam ? assignedTeam.assistants : []
        const substitutes = state.teams.filter(item => item.teamCode !== teamCode).flatMap(item => item.assistants)
        return [...own, ...substitutes].map(assistant => `${assistant.displayName} (${assistant.uid})`).join(', ')
    }

    function composeMail(teamCode) {
        const assignedTeam = team(teamCode)
        const openShifts = state.shifts.filter(shift => shift.team_code === teamCode && shift.state === 'open' && isListed(shift))
        const lines = openShifts.map(shift => `- ${shift.urgency === 'sz' ? 'SZ ' : ''}${fmtDate(shift.starts_at)} ${fmtTime(shift.starts_at)}–${fmtTime(shift.ends_at)} Uhr${shift.comment ? ' – ' + shift.comment : ''}`)
        return String(state.mailTemplate || '')
            .replaceAll('{team}', assignedTeam ? assignedTeam.displayName : teamCode)
            .replaceAll('{recipients}', mailRecipientsFor(teamCode))
            .replaceAll('{shifts}', lines.length ? lines.join('\n') : '- keine offenen Schichten')
            .replaceAll('{date}', new Intl.DateTimeFormat(locale(), {timeZone: tz(), day: '2-digit', month: '2-digit', year: 'numeric'}).format(new Date()))
            .replaceAll('{sender}', senderName())
    }

    // "Mail versendet": MTV-Marker (MTV <dd.mm> <Kürzel des MA>|) ganz am Anfang des Kommentars aller sichtbaren Schichten
    async function markMailSent() {
        const visible = state.shifts.filter(shift => isListed(shift))
        let marked = 0
        for (const shift of visible) {
            try {
                const result = await api(`/api/shifts/${shift.id}/mail-sent`, {method: 'POST', body: '{}'})
                if (result && result.shift) {
                    const index = state.shifts.findIndex(item => Number(item.id) === Number(shift.id))
                    if (index >= 0) state.shifts[index] = result.shift
                    marked++
                }
            } catch (error) { status.textContent = error.message }
        }
        if (mailDialog) mailDialog.close()
        render()
        status.textContent = marked ? `Mailversand bei ${marked} sichtbaren Schicht(en) vermerkt.` : 'Keine sichtbaren Schichten zum Vermerken.'
    }

    function openMailDialog(teamCode) {
        if (mailText) mailText.value = composeMail(teamCode)
        if (mailRecipients) mailRecipients.textContent = 'An Team und Vertretung: ' + mailRecipientsFor(teamCode)
        if (mailDialog) mailDialog.showModal()
    }

    function openActionDialog(type, id, uid) {
        currentAction = {type, id, uid}
        actionTitle.textContent = {split: 'Schicht teilen', contact: 'Kontaktversuch eintragen', reopenConfirm: 'Schicht erneut öffnen?'}[type] || 'Aktion'
        const submitButton = actionForm.querySelector('button[type=submit]')
        if (submitButton) submitButton.textContent = type === 'reopenConfirm' ? 'Erneut öffnen' : 'Speichern'
        if (type === 'reopenConfirm') {
            actionFields.innerHTML = '<p class="reopen-hint">Sicherheitsabfrage: Die eingetragene Assistenz, das Kürzel, der Besetzungszeitpunkt sowie die HyCare- und Akte-Markierungen dieser Schicht werden entfernt. Wirklich erneut öffnen?</p>'
        } else if (type === 'split') {
            const shift = shiftById(id)
            const start = shift ? new Date(shift.starts_at) : null
            const end = shift ? new Date(shift.ends_at) : null
            let dateValue = '', startMin = 0, endMin = 0
            if (start && end && !isNaN(start) && !isNaN(end)) {
                const sp = tzParts(start)
                dateValue = `${sp.year}-${pad(sp.month)}-${pad(sp.day)}`
                startMin = sp.hour * 60 + sp.minute
                endMin = tzParts(end).hour * 60 + tzParts(end).minute
                if (endMin <= startMin) endMin += 1440
            }
            const midMin = Math.round((startMin + endMin) / 2 / 15) * 15
            const rangeText = shift && start && end && !isNaN(start) && !isNaN(end) ? `${fmtDateTime(shift.starts_at)} – ${fmtDateTime(shift.ends_at)} Uhr` : ''
            actionFields.innerHTML = `<p class="shift-range">Schichtzeitraum: ${esc(rangeText)}</p><label>Teilungsdatum <input name="date" type="date" required value="${esc(dateValue)}"></label><label>Schnittzeit <input name="time" type="time" required value="${minutesToTime(midMin)}"></label><label>Schieberegler <input name="cutRange" type="range" min="${startMin}" max="${endMin}" step="15" value="${midMin}"></label>`
        } else if (type === 'contact') {
            actionFields.innerHTML = '<label>Kanal <select name="channel" required><option value="phone">Telefon</option><option value="sms">SMS</option></select></label><label>Ergebnis <select name="outcome" required><option value="open">offen</option><option value="accepted">angenommen</option><option value="declined">abgelehnt</option></select></label><label>Bemerkung <input name="note" maxlength="500"></label>'
        }
        actionDialog.showModal()
    }

    actionForm.addEventListener('input', event => {
        if (event.target.name !== 'cutRange') return
        const timeField = actionForm.querySelector('[name=time]')
        if (timeField) timeField.value = minutesToTime(Number(event.target.value))
    })

    actionForm.addEventListener('submit', async event => {
        event.preventDefault()
        if (!currentAction) return
        const data = new FormData(actionForm)
        try {
            if (currentAction.type === 'reopenConfirm') {
                await api(`/api/shifts/${currentAction.id}/reopen`, {method: 'POST', body: '{}'})
            } else if (currentAction.type === 'split') {
                const date = String(data.get('date') || '')
                const time = String(data.get('time') || '')
                if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !time) { status.textContent = 'Teilungsdatum und -zeit sind erforderlich.'; return }
                let cut = parseZoned(date + 'T' + time)
                const shift = shiftById(currentAction.id)
                if (shift && cut < new Date(shift.starts_at)) { const p = tzParts(cut); cut = zonedToUtc(p.year, p.month, p.day + 1, p.hour, p.minute) }
                await api(`/api/shifts/${currentAction.id}/split`, {method: 'POST', body: JSON.stringify({cutPointsJson: JSON.stringify([cut.toISOString()])})})
            } else if (currentAction.type === 'contact') {
                await api(`/api/shifts/${currentAction.id}/contacts`, {method: 'POST', body: JSON.stringify({assistantUid: currentAction.uid, channel: data.get('channel'), outcome: data.get('outcome'), contactedAt: new Date().toISOString(), reason: '', note: data.get('note') || ''})})
            }
            actionForm.reset()
            actionDialog.close()
            await load()
        } catch (error) { status.textContent = error.message }
    })

    root.addEventListener('focusout', async event => {
        const field = event.target && event.target.closest ? event.target.closest('input[data-field]') : null
        if (!field) return
        // Interner Kommentar zur AS-Zeile: wird direkt beim AS-Nutzer gespeichert
        if (field.dataset.field === 'asComment') {
            const uid = String(field.dataset.uid || '')
            const selectedTeam = team(searchTeamCode)
            const assistant = selectedTeam ? (selectedTeam.assistants || []).find(a => a.uid === uid) : null
            if (!assistant || field.value === (assistant.comment || '')) return
            try {
                const result = await api(`/api/assistants/${encodeURIComponent(uid)}/meta`, {method: 'PUT', body: JSON.stringify({comment: field.value})})
                if (result && result.comment !== undefined) assistant.comment = result.comment
                status.textContent = 'Kommentar gespeichert.'
            } catch (error) { status.textContent = error.message }
            return
        }
        const id = Number(field.dataset.id)
        const shift = shiftById(id)
        if (!shift) return
        const row = field.closest('tr')
        const read = name => {
            const input = row ? row.querySelector(`input[data-field=${name}]`) : null
            return input ? input.value : ''
        }
        const payload = {}
        if (field.dataset.field === 'comment') {
            if (read('comment') !== (shift.comment || '')) payload.comment = read('comment')
        } else {
            if (read('assistantUid') !== (shift.assigned_uid || '')) payload.assistantUid = read('assistantUid')
            if (read('maLabel') !== (shift.ma_label || '')) payload.maLabel = read('maLabel')
        }
        if (!Object.keys(payload).length) return
        const active = document.activeElement
        const restore = active && active.matches && active.matches('input[data-field]') && active.dataset
            ? {field: active.dataset.field, id: active.dataset.id, value: active.value, start: active.selectionStart ?? null, end: active.selectionEnd ?? null}
            : null
        const changedAssignment = payload.assistantUid !== undefined && Boolean(payload.assistantUid) !== Boolean(shift.assigned_uid)
        try {
            const result = await api(`/api/shifts/${id}/update`, {method: 'POST', body: JSON.stringify(payload)})
            const index = state.shifts.findIndex(item => Number(item.id) === id)
            if (index >= 0 && result.shift) state.shifts[index] = result.shift
            status.textContent = 'Gespeichert.'
            if (changedAssignment) {
                render()
                if (restore && typeof root.querySelector === 'function') {
                    const el = root.querySelector(`input[data-field="${restore.field}"][data-id="${restore.id}"]`)
                    if (el) {
                        el.value = restore.value
                        el.focus()
                        if (typeof el.setSelectionRange === 'function' && restore.start !== null) el.setSelectionRange(restore.start, restore.end)
                    }
                }
            }
        } catch (error) { status.textContent = error.message }
    })

    root.addEventListener('change', async event => {
        const box = event.target.closest('input[type="checkbox"][data-action]')
        if (!box) return
        const action = box.dataset.action || ''
        if (!/^contact-(tel-ab|tel-ne|sms)$/.test(action)) return
        if (!box.checked) { renderSearch(searchTeamCode); return } // Abwählen entfernt keine dokumentierten Kontaktversuche
        try {
            await recordContact(action.slice('contact-'.length), String(box.dataset.uid || ''))
            renderSearch(searchTeamCode)
            status.textContent = 'Kontaktversuch gespeichert.'
        } catch (error) { status.textContent = error.message; renderSearch(searchTeamCode) }
    })

    root.addEventListener('click', async event => {
        if (event.target.closest('#flz-akut-open-create')) { dialog.showModal(); return }
        if (event.target.closest('#flz-akut-close-create')) { dialog.close(); return }
        if (event.target.closest('#flz-akut-action-close')) { actionDialog.close(); return }
        if (event.target.closest('#flz-akut-mail-close')) { if (mailDialog) mailDialog.close(); return }
        if (event.target.closest('#flz-akut-sms-close')) { if (smsDialog) smsDialog.close(); return }
        if (event.target.closest('#flz-akut-asinfo-close')) { if (asinfoDialog) asinfoDialog.close(); return }
        if (event.target.closest('#flz-akut-asinfo-edit')) { setAsInfoEditable(true); if (asinfoNote) asinfoNote.focus(); return }
        if (event.target.closest('#flz-akut-sms-copy')) {
            const text = smsText ? smsText.value : ''
            if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => { status.textContent = 'SMS-Text liegt in der Zwischenablage.' }, () => { status.textContent = 'Kopieren nicht möglich; bitte Text manuell markieren und kopieren.' })
            } else if (smsText) {
                smsText.focus(); smsText.select()
                status.textContent = 'Bitte Text manuell kopieren (Strg+C).'
            }
            return
        }
        if (event.target.closest('#flz-akut-mail-sent')) { await markMailSent(); return }
        if (event.target.closest('#flz-akut-mail-copy')) {
            const text = mailText ? mailText.value : ''
            if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => { status.textContent = 'Mail-Text liegt in der Zwischenablage.' }, () => { status.textContent = 'Kopieren nicht möglich; bitte Text manuell markieren und kopieren.' })
            } else if (mailText) {
                mailText.focus(); mailText.select()
                status.textContent = 'Bitte Text manuell kopieren (Strg+C).'
            }
            return
        }
        if (event.target.closest('#flz-akut-manual-add')) {
            const input = document.getElementById('flz-akut-manual-input')
            const value = input ? String(input.value || '').trim() : ''
            if (value && searchTeamCode && !manualRows.some(row => row.uid === value) && !(team(searchTeamCode).assistants || []).some(a => a.uid === value)) {
                manualRows.push({uid: value, displayName: value})
                renderSearch(searchTeamCode)
            }
            return
        }
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
        if (/^contact-(yes|no)$/.test(button.dataset.action || '')) {
            try { await recordContact(button.dataset.action.slice('contact-'.length), String(button.dataset.uid || ''), Number(button.dataset.id || 0)) ; status.textContent = 'Kontaktversuch gespeichert.' } catch (error) { status.textContent = error.message }
            return
        }
        if (button.dataset.action === 'mail') { openMailDialog(button.dataset.team); return }
        if (button.dataset.action === 'as-info') { openAsInfoDialog(String(button.dataset.uid || '')); return }
        if (button.dataset.action === 'manual-remove') { manualRows = manualRows.filter(row => row.uid !== String(button.dataset.uid || '')); if (searchTeamCode) renderSearch(searchTeamCode); return }
        const id = Number(button.dataset.id)
        if (button.dataset.action === 'split' || button.dataset.action === 'contact') { openActionDialog(button.dataset.action, id, button.dataset.uid); return }
        if (button.dataset.action === 'reopen') { openActionDialog('reopenConfirm', id); return }
        try {
            if (button.dataset.action === 'hycare' || button.dataset.action === 'paper') {
                await api(`/api/shifts/${id}/confirm`, {method: 'POST', body: JSON.stringify({kind: button.dataset.action, at: new Date().toISOString()})})
            }
            await load()
        } catch (error) { status.textContent = error.message }
    })
    load()
    if (typeof setInterval === 'function') setInterval(poll, POLL_INTERVAL_MS)
    if (document.addEventListener) document.addEventListener('visibilitychange', () => { if (!document.hidden) poll() })
})()

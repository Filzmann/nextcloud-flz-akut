import assert from 'node:assert/strict'
import fs from 'node:fs'
import vm from 'node:vm'
import {fileURLToPath} from 'node:url'

// Der App-Szenario-State nutzt die Organisationszeitzone Europe/Berlin; ohne gepinnte
// Systemzeitzone wären die Date-/toLocaleString-Vergleiche maschinenabhängig (CI läuft in UTC).
process.env.TZ = 'Europe/Berlin'

function node() {
    return {innerHTML: '', textContent: '', value: '', hidden: true, disabled: false, id: '', handlers: {}, addEventListener(type, fn) { this.handlers[type] = fn }, focus() {}, select() {}}
}
function response(data, ok = true) { return {ok, async json() { return data }} }
const tick = () => new Promise(resolve => setImmediate(resolve))

async function appScenario() {
    const root = node(), status = node(), body = node(), form = node()
    const search = node(), title = node(), searchHead = node(), searchBody = node()
    const dialog = node(), openButton = node(), closeButton = node(), rowsBody = node(), addRow = node()
    const actionDialog = node(), actionForm = node(), actionTitle = node(), actionFields = node(), actionClose = node()
    const mailDialog = node(), mailText = node(), mailRecipients = node(), mailCopy = node(), mailClose = node()
    let modalShown = 0, modalClosed = 0, actionShown = 0, actionClosed = 0, mailShown = 0, mailClosed = 0
    dialog.showModal = () => { modalShown++ }
    dialog.close = () => { modalClosed++ }
    actionDialog.showModal = () => { actionShown++ }
    actionDialog.close = () => { actionClosed++ }
    mailDialog.showModal = () => { mailShown++ }
    mailDialog.close = () => { mailClosed++ }
    form.id = 'flz-akut-create'
    form.reset = () => { form.resetCalled = true }
    actionForm.id = 'flz-akut-action-form'
    actionForm.elements = {}
    actionForm.reset = () => { actionForm.resetCalled = true }
    const timeField = {value: ''}
    const submitButton = node()
    actionForm.querySelector = selector => selector === '[name=time]' ? timeField : (selector === 'button[type=submit]' ? submitButton : null)
    let mockRows = []
    const makeRow = values => {
        const row = {values, removed: false, remove() { row.removed = true; mockRows = mockRows.filter(item => item !== row) }, querySelector(selector) {
            if (selector === 'select[name=teamCode]') return {value: values.teamCode || '', selectedOptions: values.bureau ? [{dataset: {bureau: values.bureau}}] : []}
            const match = selector.match(/name=([a-zA-Z]+)/)
            return {value: values[match ? match[1] : selector] || ''}
        }}
        return row
    }
    rowsBody.querySelectorAll = () => mockRows
    const nodes = {'flz-akut-app': root, 'flz-akut-status': status, 'flz-akut-shifts': body, 'flz-akut-create': form, 'flz-akut-create-dialog': dialog, 'flz-akut-open-create': openButton, 'flz-akut-close-create': closeButton, 'flz-akut-create-rows': rowsBody, 'flz-akut-add-row': addRow, 'flz-akut-action-dialog': actionDialog, 'flz-akut-action-form': actionForm, 'flz-akut-action-title': actionTitle, 'flz-akut-action-fields': actionFields, 'flz-akut-action-close': actionClose, 'flz-akut-search-sheet': search, 'search-sheet-title': title, 'flz-akut-search-head': searchHead, 'flz-akut-search-body': searchBody, 'flz-akut-mail-dialog': mailDialog, 'flz-akut-mail-text': mailText, 'flz-akut-mail-recipients': mailRecipients, 'flz-akut-mail-copy': mailCopy, 'flz-akut-mail-close': mailClose}
    const calls = []
    let actionValues = {}
    // deterministische Uhrzeit (morgen 12:00 lokal): so sind Bürotagbeginn (06:00) und "Schicht begonnen" zu jeder Tageszeit stabil prüfbar
    const FIXED_NOW = new Date(); FIXED_NOW.setDate(FIXED_NOW.getDate() + 1); FIXED_NOW.setHours(12, 0, 0, 0)
    class FakeDate extends Date { constructor(...args) { super(...(args.length ? args : [FIXED_NOW.getTime()])) } static now() { return FIXED_NOW.getTime() } }
    const at = (h, m = 0) => { const d = new Date(FIXED_NOW.getTime()); d.setHours(h, m, 0, 0); return d }
    const state = {ok: true, providerStatus: 'AVAILABLE', mailTemplate: 'Hallo {recipients}\nTeam: {team}\nDatum: {date}\nAbsender: {sender}\nOffen:\n{shifts}', officeDayStart: '06:00', timezone: 'Europe/Berlin', teams: [{teamCode: 'T1', bureauKey: 'west', displayName: '<Team>', assistants: [{uid: 'as1', displayName: 'A & B'}]}, {teamCode: 'T2', bureauKey: 'east', displayName: 'Other', assistants: [{uid: 'as2', displayName: 'Vertretung V'}]}], permissions: {create: ['west'], split: ['west'], assign: ['west'], contact: ['west'], reopen: ['west'], confirmHycare: ['west'], confirmPaper: ['west'], read: ['west']}, shifts: [{id: 1, team_code: 'T1', bureau_key: 'west', state: 'open', urgency: 'sz', starts_at: '2030-01-01T10:00:00Z', ends_at: '2030-01-01T12:00:00Z'}, {id: 2, team_code: 'T2', bureau_key: 'east', state: 'mediated', urgency: 'week', starts_at: at(10).toISOString(), ends_at: at(12).toISOString(), assigned_uid: '<uid>', mediated_at: '2026-01-01T09:30:00Z'}, {id: 3, team_code: 'T1', bureau_key: 'west', state: 'mediated', urgency: 'unavailable', starts_at: at(13).toISOString(), ends_at: at(15).toISOString(), assigned_uid: 'as1', mediated_at: '2026-01-02T08:15:00Z', hycare_at: 'yes', paper_at: 'yes'}, {id: 4, team_code: 'T1', bureau_key: 'west', state: 'mediated', urgency: '', starts_at: at(7).toISOString(), ends_at: at(11).toISOString(), assigned_uid: 'as1', mediated_at: '2020-01-01T09:00:00Z'}, {id: 5, team_code: 'T1', bureau_key: 'west', state: 'split', urgency: '', starts_at: '2030-01-02T10:00:00Z', ends_at: '2030-01-02T12:00:00Z'}, {id: 6, team_code: 'T1', bureau_key: 'west', state: 'open', urgency: '', starts_at: new Date(at(0).getTime() - 7200e3).toISOString(), ends_at: new Date(at(0).getTime() - 3600e3).toISOString()}, {id: 7, team_code: 'T1', bureau_key: 'west', state: 'open', urgency: '', starts_at: at(7).toISOString(), ends_at: at(12).toISOString()}, {id: 8, team_code: 'T1', bureau_key: 'west', state: 'open', urgency: '', starts_at: at(20).toISOString(), ends_at: new Date(at(20).getTime() + 6 * 3600e3).toISOString()}, {id: 9, team_code: 'T1', bureau_key: 'west', state: 'open', urgency: '', starts_at: at(22).toISOString(), ends_at: new Date(at(22).getTime() + 8 * 3600e3).toISOString()}]}
    let failure = null
    const openedUrls = []
    const doc = {getElementById: id => nodes[id], activeElement: null}
    const restoredEl = node()
    restoredEl.focus = () => { restoredEl.focused = true }
    restoredEl.setSelectionRange = (a, b) => { restoredEl.sel = [a, b] }
    root.querySelector = selector => selector.includes('data-field') ? restoredEl : null
    const context = {document: doc, location: {search: ''}, URLSearchParams, Date: FakeDate, OC: {requestToken: 'synthetic-token', generateUrl: path => path, getCurrentUser: () => ({uid: 'dispo', displayName: 'Tom Dispatcher'})}, window: {open: url => { openedUrls.push(url); return null }}, FormData: class {constructor(f) { this.form = f } get(name) { return actionValues[name] }}, fetch: async (url, options) => { calls.push({url, options}); if (failure) return response({ok: false, error: failure}, false); if (url.endsWith('/api/state')) return response(state); if (url.includes('/update')) { const found = state.shifts.find(s => url.includes(`/shifts/${s.id}/`)) || {}; const body = JSON.parse(options.body); const mapped = {...found, id: found.id}; if (body.assistantUid !== undefined) mapped.assigned_uid = body.assistantUid; if (body.maLabel !== undefined) mapped.ma_label = body.maLabel === '' ? null : body.maLabel; if (body.comment !== undefined) mapped.comment = body.comment; return response({ok: true, shift: mapped}) } if (url.includes('/mail-sent')) { const found = state.shifts.find(s => url.includes(`/shifts/${s.id}/`)) || {}; return response({ok: true, shift: {...found, comment: 'MTV 26.11. DI|' + (found.comment || '')}}) } return response({ok: true}) }}
    const path = fileURLToPath(new URL('../js/app.js', import.meta.url))
    vm.runInNewContext(fs.readFileSync(path, 'utf8'), context, {filename: path})
    await tick()
    assert.equal(status.textContent, 'Bereit.')
    assert.match(rowsBody.innerHTML, /&lt;Team&gt;/)
    assert.match(rowsBody.innerHTML, /disabled/)
    assert.match(rowsBody.innerHTML, /<tr>/)
    const today = new Date(FIXED_NOW.getTime()).toLocaleDateString('sv-SE')
    assert.ok(rowsBody.innerHTML.includes(`value="${today}"`), 'Erste Zeile muss mit dem aktuellen Datum vorbefüllt sein.')
    assert.match(body.innerHTML, /urgency-sz/)
    assert.match(body.innerHTML, /&lt;uid&gt;/)
    assert.match(body.innerHTML, /data-action="split"/)
    // Inline-Reihe: AS-Suchfeld, Kürzel, HyCare/Akte-Buttons, Kommentar ganz rechts
    assert.match(body.innerHTML, /type="search"/)
    assert.match(body.innerHTML, /data-field="assistantUid"/)
    assert.match(body.innerHTML, /data-field="maLabel"/)
    assert.match(body.innerHTML, /data-field="maLabel" data-id="1" maxlength="6" pattern="[^"]*"[^>]*title="Kürzel/)
    assert.match(body.innerHTML, /data-field="comment"/)
    assert.equal((body.innerHTML.match(/class="mediated-at"/g) || []).length, 3, 'Vermittelte Schichten zeigen die Besetzungszeit an.')
    assert.match(body.innerHTML, /class="mediated-at"[^>]*>\d{2}\.\d{2}\/\d{2}:\d{2}<\/span>/, 'Besetzungszeit wird kurz als dd.mm/hh:mm angezeigt.')
    assert.ok(!body.innerHTML.includes('besetzt am'), 'Das Label „besetzt am“ ist entfernt.')
    assert.match(body.innerHTML, /class="mediated-at" title="[^"]*\d{4}[^"]*Uhr"/, 'Voller Zeitstempel bleibt als title erhalten.')
    assert.match(body.innerHTML, /class="mediated-cell"><span class="mediated-at"[^>]*>\d{2}\.\d{2}\/\d{2}:\d{2}<\/span><\/td><td><input name="maLabel"/, 'Vermittlungs-Datetime steht in eigener Spalte links vom Kürzel.')
    assert.equal((body.innerHTML.match(/<td class="mediated-cell">—<\/td>/g) || []).length, 4, 'Offene Schichten ohne Besetzung (1, 7, 8 und 9) zeigen Strich in der Vermittlungs-Spalte.')
    assert.match(body.innerHTML, /flz-akut-asn-suggestions-1/)
    assert.match(body.innerHTML, /<datalist id="flz-akut-asn-suggestions-1">/)
    assert.match(body.innerHTML, /as1/)
    assert.match(body.innerHTML, /as2/, 'Vertretungsvorschläge müssen im Datalist erscheinen.')
    assert.match(body.innerHTML, />HyCare</)
    assert.match(body.innerHTML, />Akte</)
    assert.ok(!body.innerHTML.includes('>In HyCare eingetragen<') && !body.innerHTML.includes('>HyCare ✓<'), 'Dokumentations-Buttons heißen nur HyCare/Akte.')
    assert.equal((body.innerHTML.match(/data-action="hycare"/g) || []).length, 2, 'HyCare-Button nur für Schichten mit Assistenz im eigenen Büro.')
    assert.equal((body.innerHTML.match(/documented-hycare/g) || []).length, 3, 'Lila-Markierung muss Datum, Von und Bis betreffen.')
    assert.match(body.innerHTML, /data-id="3"[^>]*value="as1"/, 'Die eingetragene Assistenz muss im AS-Suchfeld stehen.')
    assert.match(body.innerHTML, /<div class="as-line"><span class="sz-badge"[^>]*>SZ<\/span><span class="as-field"><input type="search"[^>]*data-id="1"/, 'SZ-Badge und Suchfeld liegen in einer Flex-Zeile, das Suchfeld wird kürzer.')
    assert.ok(!/>SZ<\/span><input[^>]*data-id="3"/.test(body.innerHTML), 'Nur SZ-Schichten tragen das SZ-Badge.')
    assert.ok(!body.innerHTML.includes('shift-status'), 'Die übrige Statuszeile (Name · Status) entfällt.')
    assert.equal((body.innerHTML.match(/data-action="reopen"/g) || []).length, 2, 'Erneut öffnen als Stift-Icon je vermittelter Schicht mit AS.')
    assert.match(body.innerHTML, /<button type="button" class="reopen-icon" data-action="reopen" data-id="3" title="Erneut öffnen"/, 'Stift-Icon im AS-Feld für Erneut öffnen.')
    assert.ok(!body.innerHTML.includes('>Erneut öffnen<'), 'Kein Text-Button „Erneut öffnen“ mehr.')
    assert.match(body.innerHTML, /<input type="search"[^>]*data-id="4"[^>]*disabled/, 'Ab Schichtbeginn ist das AS-Suchfeld gesperrt.')
    assert.match(body.innerHTML, /class="reopen-icon" data-action="reopen" data-id="4"/, 'Stift-Icon bleibt auch an der begonnenen Schicht.')
    assert.match(body.innerHTML, /<input type="search"[^>]*data-id="3"[^>]*disabled/, 'AS-Feld muss nach HyCare- UND Akteneintrag deaktiviert sein.')
    assert.doesNotMatch(body.innerHTML, /<input type="search"[^>]*data-id="1"[^>]*disabled/, 'AS-Feld bleibt vor HyCare/Akte bearbeitbar.')
    assert.match(body.innerHTML, /class="asn-group"/)
    assert.ok(body.innerHTML.indexOf('&lt;Team&gt;') < body.innerHTML.indexOf('Other'), 'ASN-Gruppen müssen alphabetisch sortiert sein.')
    assert.match(body.innerHTML, /data-action="search" data-team="T1"/)
    assert.match(body.innerHTML, /data-action="mail" data-team="T1"/, 'ASN-Gruppenzeile braucht einen Mail-Button.')
    assert.ok(!body.innerHTML.includes('data-id="5"'), 'Split-Ursprung (state split) darf nicht gerendert werden; nur die Teile bleiben übrig.')
    assert.ok(!body.innerHTML.includes('data-id="6"'), 'Schichten, die vor Bürotagbeginn (heute 6:00 Uhr) beginnen, dürfen nicht mehr angezeigt werden.')
    assert.ok(body.innerHTML.includes('data-id="7"'), 'Schichten, die ab Bürotagbeginn (heute 6:00 Uhr) beginnen, bleiben sichtbar.')
    assert.ok(body.innerHTML.includes('data-id="8"'), 'Eine heute um 20:00 Uhr beginnende Schicht (endet morgen 02:00 Uhr) bleibt sichtbar.')
    assert.match(body.innerHTML, /\d{2}\.\d{2}/)
    assert.match(body.innerHTML, /\d{2}:\d{2}/)
    assert.match(body.innerHTML, /<td class="[^"]*" title="Nacht — endet am Folgetag">\d{2}:\d{2} <svg class="daynight-icon"[^>]*aria-label="Nacht — endet am Folgetag">/, 'Nachtschicht (22:00–06:00 Uhr: 6 von 8 h zwischen 0 und 6 Uhr): Bis-Spalte zeigt Zeit + Mond-Icon.')
    assert.ok(!/<td class="[^"]*"[^>]*>\d{2}\.\d{2}\. \d{2}:\d{2}<\/td>/.test(body.innerHTML), 'Die Bis-Spalte zeigt kein Datum mehr, nur Zeit + Tag/Nacht-Icon.')
    assert.match(body.innerHTML, /<td class="[^"]*" title="Tag">\d{2}:\d{2} <svg class="daynight-icon"[^>]*aria-label="Tag">/, 'Tagschicht (07:00–12:00 Uhr): Bis-Spalte zeigt Zeit + Sonnen-Icon.')
    assert.match(body.innerHTML, /<td class="[^"]*" title="Tag — endet am Folgetag">\d{2}:\d{2} <svg class="daynight-icon"[^>]*aria-label="Tag — endet am Folgetag">/, 'Grenzfall 20:00–02:00 Uhr (nur 2 von 6 h zwischen 0 und 6 Uhr = nicht überwiegend): Sonnen-Icon mit Folgetag-Hinweis, kein Mond.')
    assert.equal(calls[0].options.headers.requesttoken, 'synthetic-token')
    await root.handlers.click({target: {closest: selector => selector === 'button[data-action]' ? {dataset: {action: 'search', team: 'T1'}} : null}})
    assert.ok(openedUrls.some(url => url.includes('/suchbogen?team=T1')), 'Suchbogen muss im neuen Tab geöffnet werden.')
    await root.handlers.click({target: {closest: selector => selector === '#flz-akut-open-create' ? openButton : null}})
    assert.equal(modalShown, 1)
    await root.handlers.click({target: {closest: selector => selector === '#flz-akut-close-create' ? closeButton : null}})
    assert.equal(modalClosed, 1)
    mockRows = [makeRow({teamCode: 'T1', bureau: 'west', date: '2026-01-01', startTime: '22:00', endTime: '06:00', comment: 'test'})]
    await root.handlers.click({target: {closest: selector => selector === '#flz-akut-add-row' ? addRow : null}})
    assert.equal((rowsBody.innerHTML.match(/<tr>/g) || []).length, 2, 'Zeile hinzufügen muss eine zweite Zeile rendern')
    assert.ok((rowsBody.innerHTML.match(/value="2026-01-01"/g) || []).length >= 2, 'Neue Zeile muss Datum der Vorzeile übernehmen')
    assert.ok((rowsBody.innerHTML.match(/value="22:00"/g) || []).length >= 2, 'Neue Zeile muss Von-Zeit der Vorzeile übernehmen')
    assert.ok((rowsBody.innerHTML.match(/value="T1"/g) || []).length >= 2, 'Neue Zeile muss ASN der Vorzeile übernehmen')
    await root.handlers.click({target: {closest: selector => selector === '[data-row-remove]' ? {dataset: {index: '1'}} : null}})
    assert.equal(mockRows.length, 1, 'Entfernen muss die Zeile aus dem DOM entfernen')
    await form.handlers.submit({preventDefault() {}})
    const createCall = calls.find(call => call.url.endsWith('/api/shifts'))
    assert.equal(createCall.options.method, 'POST')
    const payload = JSON.parse(createCall.options.body)
    assert.equal(payload.teamCode, 'T1')
    assert.equal(payload.startsAt, new Date('2026-01-01T22:00').toISOString())
    assert.equal(payload.endsAt, new Date('2026-01-01T06:00').toISOString())
    assert.equal(payload.bureauKey, 'west')
    assert.equal(modalClosed, 2)
    assert.ok(rowsBody.innerHTML.includes(`value="${today}"`), 'Auch nach dem Anlegen muss die erste Zeile mit dem aktuellen Datum vorbefüllt sein.')
    const clickAction = async (action, id = 1, uid = 'as1') => root.handlers.click({target: {closest: selector => selector === 'button[data-action]' ? {dataset: {action, id: String(id), uid}} : null}})
    const submitAction = async values => { actionValues = values; await actionForm.handlers.submit({preventDefault() {}}) }
    await clickAction('split')
    assert.equal(actionShown, 1)
    const fmtTestDateTime = value => new Date(value).toLocaleString('de-DE', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'})
    assert.ok(actionFields.innerHTML.includes('shift-range'), 'Split-Dialog muss den Schichtzeitraum anzeigen.')
    assert.ok(actionFields.innerHTML.includes(fmtTestDateTime('2030-01-01T10:00:00Z')), 'Split-Dialog muss Schichtbeginn mit Datum und Uhrzeit anzeigen.')
    assert.ok(actionFields.innerHTML.includes(fmtTestDateTime('2030-01-01T12:00:00Z')), 'Split-Dialog muss Schichtende mit Datum und Uhrzeit anzeigen.')
    assert.match(actionFields.innerHTML, /type="date"/)
    assert.match(actionFields.innerHTML, /type="time"/)
    assert.match(actionFields.innerHTML, /type="range"/)
    assert.match(actionFields.innerHTML, /step="15"/)
    assert.match(actionFields.innerHTML, /value="2030-01-01"/)
    await actionForm.handlers.input({target: {name: 'cutRange', value: '630'}})
    assert.equal(timeField.value, '10:30', 'Schieberegler muss das Zeitfeld im 15-Minuten-Raster setzen.')
    await submitAction({date: '2030-01-01', time: '11:00'})
    const splitCall = calls.find(call => call.url.endsWith('/split'))
    assert.equal(JSON.parse(splitCall.options.body).cutPointsJson, JSON.stringify([new Date('2030-01-01T11:00').toISOString()]))
    await clickAction('split')
    await submitAction({date: '2030-01-01', time: '08:00'})
    const nightCutCall = calls.filter(call => call.url.endsWith('/split')).pop()
    assert.equal(JSON.parse(nightCutCall.options.body).cutPointsJson, JSON.stringify([new Date('2030-01-02T08:00').toISOString()]), 'Schnitt vor Schichtbeginn (Nachtschicht) muss auf den Folgetag fallen.')
    assert.ok(actionClosed >= 1)
    // Inline-Saves beim Verlassen der Felder (focusout)
    const makeFocusTarget = (field, id, values) => {
        const row = {querySelector: selector => { const m = /data-field=([A-Za-z]+)/.exec(selector); return m && values[m[1]] !== undefined ? {value: values[m[1]]} : null }}
        const target = {dataset: {field, id: String(id)}, closest: selector => selector === 'input[data-field]' ? target : (selector === 'tr' ? row : null)}
        return target
    }
    const updateCalls = () => calls.filter(call => call.url.endsWith('/update'))
    doc.activeElement = {matches: () => true, dataset: {field: 'maLabel', id: '1'}, value: 'EBA', selectionStart: 3, selectionEnd: 3}
    await root.handlers.focusout({target: makeFocusTarget('assistantUid', 1, {assistantUid: 'as1', maLabel: 'EBA', comment: ''})})
    assert.equal(updateCalls().length, 1, 'AS-/Kürzel-Blur muss genau ein Update senden.')
    assert.equal(updateCalls()[0].options.method, 'POST')
    assert.deepEqual(JSON.parse(updateCalls()[0].options.body), {assistantUid: 'as1', maLabel: 'EBA'})
    assert.match(body.innerHTML, /data-action="hycare" data-id="1"/, 'Nach Besetzung muss der HyCare-Button ohne Reload erscheinen.')
    assert.match(body.innerHTML, /data-action="paper" data-id="1"/, 'Nach Besetzung muss der Akte-Button ohne Reload erscheinen.')
    assert.equal((body.innerHTML.match(/data-action="hycare"/g) || []).length, 3, 'HyCare-Buttons erscheinen für alle besetzten Schichten.')
    assert.equal(restoredEl.focused, true, 'Fokus wird nach dem Re-Render ins neu fokussierte Feld zurückgesetzt.')
    assert.equal(restoredEl.value, 'EBA', 'Eingegebener Wert im Fokus-Feld bleibt erhalten.')
    assert.deepEqual(restoredEl.sel, [3, 3], 'Cursorposition bleibt erhalten.')
    await root.handlers.focusout({target: makeFocusTarget('maLabel', 1, {assistantUid: 'as1', maLabel: 'EBA', comment: ''})})
    assert.equal(updateCalls().length, 1, 'Unverändertes Feld darf kein Update senden.')
    await root.handlers.focusout({target: makeFocusTarget('comment', 1, {assistantUid: '', maLabel: '', comment: 'abgesagt im Kalender'})})
    assert.equal(updateCalls().length, 2, 'Kommentar-Blur muss ein Update senden.')
    assert.deepEqual(JSON.parse(updateCalls()[1].options.body), {comment: 'abgesagt im Kalender'})
    await root.handlers.focusout({target: makeFocusTarget('assistantUid', 1, {assistantUid: 'abgesagt', maLabel: 'EBA', comment: ''})})
    assert.deepEqual(JSON.parse(updateCalls()[2].options.body), {assistantUid: 'abgesagt'}, 'Freitext-AS (z. B. abgesagt) muss ans Update gehen.')
    // Konfigurierter Bürotagbeginn aus dem State wird als Stichtag verwendet
    state.officeDayStart = '08:00'
    await root.handlers.focusout({target: makeFocusTarget('assistantUid', 1, {assistantUid: '', maLabel: 'EBA', comment: ''})})
    assert.ok(!body.innerHTML.includes('data-id="7"'), 'Mit Bürotagbeginn 08:00 darf die um 07:00 Uhr beginnende Schicht nicht mehr angezeigt werden.')
    assert.ok(body.innerHTML.includes('data-id="1"'), 'Spätere Schichten bleiben mit Bürotagbeginn 08:00 sichtbar.')
    state.officeDayStart = '06:00'
    await root.handlers.focusout({target: makeFocusTarget('assistantUid', 1, {assistantUid: 'as1', maLabel: 'EBA', comment: ''})})
    assert.ok(body.innerHTML.includes('data-id="7"'), 'Nach Zurücksetzen auf 06:00 ist die Schicht wieder sichtbar.')
    // Mail-Overlay je ASN
    await root.handlers.click({target: {closest: selector => selector === 'button[data-action]' ? {dataset: {action: 'mail', team: 'T1'}} : null}})
    assert.equal(mailShown, 1, 'Mail-Button muss das Overlay öffnen.')
    assert.match(mailText.value, /Team: <Team>/)
    assert.match(mailText.value, /Hallo A & B \(as1\), Vertretung V \(as2\)/, 'Mail geht an Team und Vertretung.')
    assert.match(mailText.value, /\d{2}\.\d{2}\. \d{2}:\d{2}/, 'Mail listet die offenen Schichten des ASN.')
    assert.match(mailText.value, /- SZ \d{2}\.\d{2}\. \d{2}:\d{2}–\d{2}:\d{2} Uhr/, 'SZ-Schichten tragen SZ in der Mail-Schichtzeile.')
    assert.equal((mailText.value.match(/- SZ /g) || []).length, 1, 'Nur die SZ-Schicht ist mit SZ markiert.')
    assert.match(mailText.value, /Absender: Tom/, 'Mail-Signatur enthält den Vornamen der eingeloggten Person ({sender}).')
    assert.ok(!mailText.value.includes('{shifts}') && !mailText.value.includes('{recipients}') && !mailText.value.includes('{sender}'), 'Platzhalter müssen ersetzt sein.')
    assert.match(mailRecipients.textContent, /A & B \(as1\)/)
    await root.handlers.click({target: {closest: selector => selector === '#flz-akut-mail-copy' ? mailCopy : (selector === 'button[data-action]' ? null : null)}})
    assert.match(status.textContent, /kopieren/i)
    await root.handlers.click({target: {closest: selector => selector === '#flz-akut-mail-close' ? mailClose : null}})
    assert.equal(mailClosed, 1)
    // "Mail versendet": MTV-Marker (MTV <dd.mm> <Kürzel des Mitarbeiters>|) im Kommentar aller aktuell sichtbaren Schichten
    const beforeSent = calls.length
    await root.handlers.click({target: {closest: selector => selector === '#flz-akut-mail-sent' ? {} : null}})
    const sentCalls = calls.slice(beforeSent).filter(call => call.url.includes('/mail-sent'))
    const dayStart = new Date(FIXED_NOW.getTime()); dayStart.setHours(6, 0, 0, 0)
    const expectedVisible = state.shifts.filter(shift => shift.state !== 'split' && new Date(shift.starts_at) >= dayStart)
    assert.ok(expectedVisible.length > 0, 'Szenario braucht sichtbare Schichten.')
    assert.equal(sentCalls.length, expectedVisible.length, '"Mail versendet" vermerkt genau die aktuell sichtbaren Schichten.')
    assert.ok(sentCalls.every(call => call.options.method === 'POST' && call.options.body === '{}'), 'MTV-Markierung geht als POST an /api/shifts/{id}/mail-sent.')
    assert.ok(body.innerHTML.includes('MTV 26.11. DI|'), 'MTV-Marker erscheint im Kommentar der sichtbaren Schichten.')
    await clickAction('contact', 1, 'as1')
    await submitAction({channel: 'phone', outcome: 'accepted', note: 'note'})
    const contactCall = calls.find(call => call.url.endsWith('/contacts'))
    assert.equal(JSON.parse(contactCall.options.body).assistantUid, 'as1')
    assert.equal(JSON.parse(contactCall.options.body).channel, 'phone')
    const reopenCalls = () => calls.filter(call => call.url.endsWith('/reopen'))
    await clickAction('reopen', 3)
    assert.equal(reopenCalls().length, 0, 'Wiederöffnung darf ohne Sicherheitsabfrage nicht gesendet werden.')
    assert.match(actionTitle.textContent, /erneut öffnen/i, 'Sicherheitsabfrage wird als eigener Dialog geöffnet.')
    assert.match(actionFields.innerHTML, /reopen-hint/)
    assert.equal(submitButton.textContent, 'Erneut öffnen')
    await submitAction({})
    assert.equal(reopenCalls().length, 1, 'Erst die Bestätigung in der Sicherheitsabfrage sendet /reopen.')
    await clickAction('hycare'); await clickAction('paper')
    for (const suffix of ['/split', '/update', '/reopen', '/confirm', '/contacts']) assert.ok(calls.some(call => call.url.endsWith(suffix)), `missing ${suffix}`)
    const before = calls.length; await clickAction('split'); await actionForm.handlers.submit({preventDefault() {}}); assert.equal(calls.length, before, 'Aktionsdialog ohne Pflichtwerte darf nicht senden')
    failure = 'synthetic failure'; await clickAction('hycare'); assert.equal(status.textContent, 'synthetic failure')
    failure = null; mockRows = [makeRow({})]; const previous = calls.length; await form.handlers.submit({preventDefault() {}}); assert.equal(calls.length, previous)
    await root.handlers.click({target: {closest: () => null}})
}

async function searchScenario() {
    const root = node(), status = node()
    const search = node(), title = node(), searchHead = node(), searchBody = node(), searchSummary = node()
    const manualInput = node()
    const smsDialog = node(), smsText = node()
    let smsShown = 0
    smsDialog.showModal = () => { smsShown++ }
    smsDialog.close = () => {}
    const asinfoDialog = node(), asinfoForm = node(), asinfoTitle = node(), asinfoNote = node(), asinfoEdit = node(), asinfoSave = node()
    let asinfoShown = 0, asinfoClosed = 0
    asinfoDialog.showModal = () => { asinfoShown++ }
    asinfoDialog.close = () => { asinfoClosed++ }
    asinfoForm.id = 'flz-akut-asinfo-form'
    const tagInputs = [{value: 'tag', checked: false, disabled: false}, {value: 'nacht', checked: false, disabled: false}, {value: 'frueh', checked: false, disabled: false}, {value: 'spaet', checked: false, disabled: false}]
    asinfoForm.querySelectorAll = selector => selector === 'input[name="asinfo-tag"]' ? tagInputs : (selector === 'input[name="asinfo-tag"]:checked' ? tagInputs.filter(t => t.checked) : [])
    const actionDialog = node(), actionForm = node(), actionTitle = node(), actionFields = node(), actionClose = node()
    actionDialog.showModal = () => {}
    actionDialog.close = () => {}
    actionForm.id = 'flz-akut-action-form'
    actionForm.elements = {}
    actionForm.reset = () => {}
    actionForm.querySelector = () => null
    const nodes = {'flz-akut-app': root, 'flz-akut-status': status, 'flz-akut-search-sheet': search, 'search-sheet-title': title, 'flz-akut-search-head': searchHead, 'flz-akut-search-body': searchBody, 'flz-akut-search-summary': searchSummary, 'flz-akut-manual-input': manualInput, 'flz-akut-sms-dialog': smsDialog, 'flz-akut-sms-text': smsText, 'flz-akut-asinfo-dialog': asinfoDialog, 'flz-akut-asinfo-form': asinfoForm, 'flz-akut-asinfo-title': asinfoTitle, 'flz-akut-asinfo-note': asinfoNote, 'flz-akut-asinfo-edit': asinfoEdit, 'flz-akut-asinfo-save': asinfoSave, 'flz-akut-action-dialog': actionDialog, 'flz-akut-action-form': actionForm, 'flz-akut-action-title': actionTitle, 'flz-akut-action-fields': actionFields, 'flz-akut-action-close': actionClose}
    const calls = []
    const state = {ok: true, providerStatus: 'AVAILABLE', mailTemplate: '{shifts}', timezone: 'Europe/Berlin', capacities: [{uid: 'as1', day: '2030-01-01', restriction: '', note: 'alles'}, {uid: 'as1', day: '2030-01-02', restriction: 'frueh,nacht', note: 'nur nachts'}, {uid: 'as1', day: '2030-01-03', restriction: 'tag', note: null}, {uid: 'as2', day: '2030-01-01', restriction: '', note: null}], contacts: [{id: 9, shiftId: 1, assistantUid: 'as1', channel: 'phone', outcome: 'ab', contactedAt: '2030-01-01T09:30:00+00:00'}], absences: [{uid: 'as1', startsAt: '2029-12-31T00:00:00+00:00', endsAt: '2030-01-02T00:00:00+00:00', status: 'approved', label: 'Urlaub'}], teams: [{teamCode: 'T1', bureauKey: 'west', displayName: '<Team>', assistants: [{uid: 'as1', displayName: 'A & B', info: {tags: ['nacht'], note: 'nur nachts'}, comment: 'bevorzugt Vormittag'}]}, {teamCode: 'T2', bureauKey: 'east', displayName: 'Other', assistants: [{uid: 'as2', displayName: 'Vertretung V'}]}], permissions: {contact: ['west']}, shifts: [{id: 1, team_code: 'T1', bureau_key: 'west', state: 'open', urgency: 'sz', starts_at: '2030-01-01T10:00:00Z', ends_at: '2030-01-01T12:00:00Z'}, {id: 2, team_code: 'T1', bureau_key: 'west', state: 'open', urgency: '', starts_at: '2030-01-02T22:00:00Z', ends_at: '2030-01-03T06:00:00Z'}, {id: 3, team_code: 'T1', bureau_key: 'west', state: 'mediated', urgency: '', starts_at: '2030-01-04T10:00:00Z', ends_at: '2030-01-04T12:00:00Z', assigned_uid: 'as1'}]}
    const intervals = []
    const docListeners = {}
    const document = {
        getElementById: id => nodes[id],
        hidden: false,
        activeElement: null,
        querySelector: () => null,
        addEventListener: (type, fn) => { (docListeners[type] = docListeners[type] || []).push(fn) },
    }
    const context = {document, location: {search: '?team=T1'}, URLSearchParams, Date, OC: {requestToken: 'synthetic-token', generateUrl: path => path, getCurrentUser: () => ({uid: 'dispo', displayName: 'Tom Dispatcher'})}, window: {open: () => null}, FormData: class {get() { return null }}, setInterval: fn => { intervals.push(fn); return intervals.length }, clearInterval: () => {}, setTimeout, clearTimeout, fetch: async (url, options) => { calls.push({url, options}); if (url.endsWith('/api/state')) return response(state); if (url.endsWith('/contacts')) { const body = JSON.parse(options.body); return response({ok: true, attempt: {id: 100 + calls.length, shift_id: Number((url.match(/\/shifts\/(\d+)\/contacts/) || [])[1]), assistant_uid: body.assistantUid, channel: body.channel, outcome: body.outcome, contacted_at: body.contactedAt, reason: null, note: null}}) } if (url.includes('/assistants/') && url.endsWith('/meta')) { const body = JSON.parse(options.body); const info = body.tagsJson !== undefined ? {tags: JSON.parse(body.tagsJson), note: body.note ?? ''} : {tags: ['nacht'], note: 'nur nachts'}; return response({ok: true, info, comment: body.comment ?? 'bevorzugt Vormittag'}) } return response({ok: true}) }}
    const path = fileURLToPath(new URL('../js/app.js', import.meta.url))
    vm.runInNewContext(fs.readFileSync(path, 'utf8'), context, {filename: path})
    await tick()
    assert.equal(title.textContent, 'Suchbogen <Team>')
    // Live-Polling: genau ein Intervall registriert, Status nach Poll mit Live-Zeitstempel
    assert.equal(intervals.length, 1, 'Genau ein Poll-Intervall (setInterval) wird registriert.')
    {
        calls.length = 0
        await intervals[0]() // unveränderter Zustand -> kein Re-Render, aber Live-Status
        await tick()
        assert.ok(calls.some(c => c.url.endsWith('/api/state')), 'Poll ruft /api/state ab.')
        assert.match(status.textContent, /Live alle 10 s, zuletzt \d{2}:\d{2}:\d{2}/, 'Status zeigt Live-Aktualisierung mit letzter Poll-Uhrzeit.')
        assert.match(status.textContent, /^Bereit\./, 'Basis-Status bleibt erhalten.')
        // Änderung im State -> Re-Render (neue offene Schicht erscheint in der Sammelanzeige)
        state.shifts.push({id: 4, team_code: 'T1', bureau_key: 'west', state: 'open', urgency: '', starts_at: '2030-01-05T08:00:00Z', ends_at: '2030-01-05T12:00:00Z'})
        await intervals[0]()
        await tick()
        assert.equal((searchSummary.innerHTML.match(/search-shift-chip/g) || []).length, 3, 'Geänderter Zustand wird live nachgerendert (3 offene Schichten).')
        // Pausiert bei hidden / offenem Dialog / Feld-Fokus
        calls.length = 0
        document.hidden = true
        await intervals[0](); await tick()
        assert.ok(!calls.some(c => c.url.endsWith('/api/state')), 'Kein Poll bei hidden=true.')
        document.hidden = false
        document.querySelector = () => ({open: true})
        await intervals[0](); await tick()
        assert.ok(!calls.some(c => c.url.endsWith('/api/state')), 'Kein Poll bei offenem Dialog.')
        document.querySelector = () => null
        root.contains = () => true
        document.activeElement = {tagName: 'INPUT'}
        await intervals[0](); await tick()
        assert.ok(!calls.some(c => c.url.endsWith('/api/state')), 'Kein Poll während ein Feld fokussiert ist.')
        document.activeElement = null
        delete root.contains
        // visibilitychange -> sofortiger Poll
        await (docListeners['visibilitychange'] || [])[0]()
        await tick()
        assert.ok(calls.some(c => c.url.endsWith('/api/state')), 'Sichtbarwerden löst sofortigen Poll aus.')
        state.shifts = state.shifts.filter(shift => shift.id !== 4)
        await intervals[0](); await tick()
        assert.equal((searchSummary.innerHTML.match(/search-shift-chip/g) || []).length, 2, 'Wieder entfernter Zustand wird live nachgerendert (zurück auf 2 offene Schichten).')
    }
    assert.equal(search.hidden, false)
    // Sammelanzeige aller offenen Schichten über der Tabelle
    assert.match(searchSummary.innerHTML, /search-shift-chip[^>]*>\d{2}\.\d{2}\. \d{2}:\d{2}–\d{2}:\d{2}/, 'Über der Tabelle werden alle offenen Schichten gesammelt angezeigt.')
    assert.equal((searchSummary.innerHTML.match(/search-shift-chip/g) || []).length, 2, 'Genau die beiden offenen Schichten erscheinen in der Sammelanzeige.')
    assert.ok(searchSummary.innerHTML.includes('daynight-icon'), 'Sammelanzeige zeigt Tag/Nacht-Icon je Schicht.')
    // Spalten: Name links fixiert, offene Schichten direkt danach, Kontakt rechts fixiert
    assert.match(searchHead.innerHTML, /<tr><th class="sticky-left">Assistenz<\/th><th>\d{2}\.\d{2}\. \d{2}:\d{2}<\/th><th>\d{2}\.\d{2}\. \d{2}:\d{2}<\/th><th class="sticky-right sticky-tel">Tel<\/th><th class="sticky-right sticky-sms">SMS<\/th><th class="sticky-right sticky-comment">Kommentar<\/th><\/tr>/, 'Spaltenreihenfolge: Name (links fixiert), offene Schichten, Tel/SMS/Kommentar (rechts fixiert).')
    assert.ok(!searchHead.innerHTML.includes('+00:00') && !/T\d{2}:\d{2}:\d{2}/.test(searchHead.innerHTML), 'Suchbogen darf keine UTC-Rohstrings anzeigen.')
    // Besetzte Schicht verschwindet aus dem Suchbogen
    assert.ok(!searchHead.innerHTML.includes('04.01.') && !searchBody.innerHTML.includes('data-id="3"'), 'Vermittelte (besetzte) Schichten erscheinen nicht im Suchbogen.')
    // Nur Team-Assistentinnen als Zeilen; Urlaubs-Badge wegen bekannter Abwesenheit
    assert.match(searchBody.innerHTML, /<th class="sticky-left">A &amp; B <span class="absence-badge"[^>]*>Urlaub<\/span><button type="button" class="as-info"/, 'Urlaub wird markiert, wenn eine Abwesenheit überlappt.')
    assert.ok(!searchBody.innerHTML.includes('Vertretung V'), 'Nur Assistentinnen des Teams werden als Zeilen geführt.')
    // Ja/Nein sitzen in jeder Schicht-Zelle und buchen genau diese Schicht
    for (const id of ['1', '2']) {
        assert.match(searchBody.innerHTML, new RegExp(`<td class="shift-contact">(?:<span class="capacity-badge"[^>]*>[^<]*</span>)?<button type="button" data-action="contact-yes" data-id="${id}" data-uid="as1"[^>]*>Ja</button> <button type="button" data-action="contact-no" data-id="${id}" data-uid="as1"[^>]*>Nein</button></td>`), `Schichtzelle ${id} enthält Ja/Nein (ggf. mit Kapazitäts-Badge davor) mit data-id.`)
    }
    assert.ok(!/data-action="contact-(tel-ab|tel-ne|sms)"[^>]*data-id=/.test(searchBody.innerHTML), 'Tel/SMS sitzen nicht mehr in den Schichtzellen.')
    // Tel-Spalte rechts fixiert mit Checkboxen für n.e. und AB; gespeicherte AB-Zeit ist als Hover (title) sichtbar
    assert.match(searchBody.innerHTML, /<td class="sticky-right sticky-tel"><label class="contact-check"><input type="checkbox" data-action="contact-tel-ne" data-uid="as1" title="n\.e\. \(alle offenen Schichten\)"> n\.e\.<\/label><label class="contact-check"><input type="checkbox" data-action="contact-tel-ab" data-uid="as1" checked title="AB — 01\.01\.2030, 10:30 Uhr \(alle offenen Schichten\)"> AB<\/label><\/td>/, 'Tel-Spalte mit Checkboxen n.e./AB; AB gehakt mit gespeicherter Zeit als title.')
    // SMS-Spalte rechts fixiert mit einer Checkbox
    assert.match(searchBody.innerHTML, /<td class="sticky-right sticky-sms"><label class="contact-check"><input type="checkbox" data-action="contact-sms" data-uid="as1" title="SMS \(alle offenen Schichten\)"> SMS<\/label><\/td>/, 'SMS-Spalte rechts fixiert mit einer Checkbox.')
    // Kapazitäts-Badges aus flz_kapazitaet in den Schichtzellen (Abgleichregel wie CapacityMatching)
    assert.match(searchBody.innerHTML, /<td class="shift-contact"><span class="capacity-badge" title="Kapazität 01\.01\.2030 \(alle Schichten\) – alles">•<\/span><button type="button" data-action="contact-yes" data-id="1"/, 'Tagschicht 1 zeigt Badge „alle Schichten“ (•) für as1.')
    assert.match(searchBody.innerHTML, /<span class="capacity-badge" title="Kapazität 02\.01\.2030 \(Früh \+ Nacht\) – nur nachts">F\+N<\/span><button type="button" data-action="contact-yes" data-id="2"/, 'Nachtschicht 2 zeigt Kombinations-Badge F+N (frueh,nacht greift über den Nacht-Anteil; ODER-Semantik; tag-Kapazität vom Folgetag nicht).')
    assert.ok(!/data-id="1"[^>]*>[^<]*<span class="capacity-badge" title="Kapazität 02\.01\.2030/.test(searchBody.innerHTML), 'Nacht-Kapazität darf nicht an der Tagschicht hängen.')
    // Manuelle AS-Zeile ergänzen und wieder entfernen
    manualInput.value = 'as99'
    await root.handlers.click({target: {closest: selector => selector === '#flz-akut-manual-add' ? {} : null}})
    assert.match(searchBody.innerHTML, /<th class="sticky-left">as99 <button type="button" class="as-info"|<th class="sticky-left">as99/, 'Manuell ergänzte AS-Zeile erscheint im Suchbogen.')
    assert.match(searchBody.innerHTML, /data-action="manual-remove" data-uid="as99"/, 'Manuelle Zeile hat Entfernen-Button.')
    assert.match(searchBody.innerHTML, /data-action="contact-yes" data-id="1" data-uid="as99"/, 'Manuelle Zeile hat Ja/Nein je offener Schicht.')
    await root.handlers.click({target: {closest: selector => selector === 'button[data-action]' ? {dataset: {action: 'manual-remove', uid: 'as99'}} : null}})
    assert.ok(!searchBody.innerHTML.includes('as99'), 'Entfernen löscht die manuelle Zeile wieder.')
    assert.equal(nodes['flz-akut-shifts'], undefined)
    // Ja bucht genau die angeklickte Schicht (1 POST, outcome accepted)
    const beforeYes = calls.length
    await root.handlers.click({target: {closest: selector => selector === 'button[data-action]' ? {dataset: {action: 'contact-yes', uid: 'as1', id: '1'}} : null}})
    const yesCalls = calls.slice(beforeYes).filter(call => call.url.endsWith('/contacts'))
    assert.equal(yesCalls.length, 1, 'Ja bucht genau eine Schicht.')
    assert.ok(yesCalls[0].url.endsWith('/shifts/1/contacts'), 'Ja bucht die angeklickte Schicht 1.')
    assert.ok(JSON.parse(yesCalls[0].options.body).outcome === 'accepted' && JSON.parse(yesCalls[0].options.body).channel === 'phone', 'Ja bucht phone/accepted.')
    // Nein bucht phone/declined auf genau einer Schicht
    const beforeNo = calls.length
    await root.handlers.click({target: {closest: selector => selector === 'button[data-action]' ? {dataset: {action: 'contact-no', uid: 'as1', id: '2'}} : null}})
    const noCalls = calls.slice(beforeNo).filter(call => call.url.endsWith('/contacts'))
    assert.equal(noCalls.length, 1, 'Nein bucht genau eine Schicht.')
    assert.ok(noCalls[0].url.endsWith('/shifts/2/contacts') && JSON.parse(noCalls[0].options.body).outcome === 'declined', 'Nein bucht Schicht 2 mit phone/declined.')
    // Tel n.e. (Checkbox) bucht schichtübergreifend für alle offenen Schichten (2 POSTs, phone/not_reached)
    const beforeNe = calls.length
    await root.handlers.change({target: {closest: selector => selector === 'input[type="checkbox"][data-action]' ? {dataset: {action: 'contact-tel-ne', uid: 'as1'}, checked: true} : null}})
    const neCalls = calls.slice(beforeNe).filter(call => call.url.endsWith('/contacts'))
    assert.equal(neCalls.length, 2, 'Tel n.e. bucht für alle offenen Schichten.')
    assert.ok(neCalls.every(call => JSON.parse(call.options.body).outcome === 'not_reached' && JSON.parse(call.options.body).channel === 'phone'), 'Tel n.e. bucht phone/not_reached.')
    assert.ok(neCalls.some(call => call.url.endsWith('/shifts/1/contacts')) && neCalls.some(call => call.url.endsWith('/shifts/2/contacts')), 'Tel n.e. bucht Schicht 1 und 2.')
    // Nach dem Buchen ist die n.e.-Checkbox gehakt und zeigt die gespeicherte Zeit als Hover (title)
    assert.match(searchBody.innerHTML, /data-action="contact-tel-ne" data-uid="as1" checked title="n\.e\. — \d{2}\.\d{2}\.\d{4}, \d{2}:\d{2} Uhr \(alle offenen Schichten\)"/, 'n.e.-Checkbox gehakt mit gespeicherter Zeit als title.')
    // Abwählen bucht keinen weiteren Kontaktversuch
    const beforeUncheck = calls.length
    await root.handlers.change({target: {closest: selector => selector === 'input[type="checkbox"][data-action]' ? {dataset: {action: 'contact-tel-ne', uid: 'as1'}, checked: false} : null}})
    assert.equal(calls.slice(beforeUncheck).filter(call => call.url.endsWith('/contacts')).length, 0, 'Abwählen bucht nichts nach.')
    // Tel AB (Checkbox) bucht schichtübergreifend phone/ab
    const beforeAb = calls.length
    await root.handlers.change({target: {closest: selector => selector === 'input[type="checkbox"][data-action]' ? {dataset: {action: 'contact-tel-ab', uid: 'as1'}, checked: true} : null}})
    const abCalls = calls.slice(beforeAb).filter(call => call.url.endsWith('/contacts'))
    assert.equal(abCalls.length, 2, 'Tel AB bucht für alle offenen Schichten.')
    assert.ok(abCalls.every(call => JSON.parse(call.options.body).outcome === 'ab' && JSON.parse(call.options.body).channel === 'phone'), 'Tel AB bucht phone/ab.')
    // SMS bucht sms für alle offenen Schichten und öffnet das Overlay mit vorformulierten Text
    const beforeSms = calls.length
    await root.handlers.change({target: {closest: selector => selector === 'input[type="checkbox"][data-action]' ? {dataset: {action: 'contact-sms', uid: 'as1'}, checked: true} : null}})
    const smsCalls = calls.slice(beforeSms).filter(call => call.url.endsWith('/contacts'))
    assert.equal(smsCalls.length, 2, 'SMS bucht für alle offenen Schichten.')
    assert.ok(smsCalls.every(call => JSON.parse(call.options.body).channel === 'sms'), 'SMS bucht Kanal sms.')
    assert.equal(smsShown, 1, 'SMS öffnet das Vorformular-Overlay.')
    assert.match(smsText.value, /Hallo A, offene Schichten im ASN-Team <Team>:/, 'SMS spricht den Empfänger mit Namen an und bleibt kompakt.')
    assert.match(smsText.value, /- 01\.01\. 11–13 Uhr\n- 02\.01\. 23–7 Uhr/, 'SMS listet die offenen Schichten kompakt (volle Stunden ohne :00-Minuten).')
    assert.ok(smsText.value.length <= 160, `SMS-Vorschlag bleibt unter 160 Zeichen (${smsText.value.length}).`)
    assert.match(smsText.value, /\nLG Tom$/, 'SMS endet mit LG <Vorname der eingeloggten Person>.')
    // Info-Icon je AS: klickbarer Button mit Hover-Titel (größere Ikone via CSS)
    assert.match(searchBody.innerHTML, /<button type="button" class="as-info" data-action="as-info" data-uid="as1" title="AS-Info: nur Nacht — nur nachts"[^>]*><svg class="as-info-icon"/, 'Info-Icon ist klickbarer Button mit Hover-Titel.')
    // Kommentarspalte rechts fixiert mit vorbefülltem Textfeld
    assert.match(searchBody.innerHTML, /<td class="sticky-right sticky-comment"><input type="text" class="as-comment" data-field="asComment" data-uid="as1" value="bevorzugt Vormittag"/, 'Kommentarfeld ist mit dem beim AS-Nutzer gespeicherten Kommentar vorbefüllt.')
    // Klick auf das Info-Icon öffnet das Overlay — im Normalzustand schreibgeschützt
    await root.handlers.click({target: {closest: selector => selector === 'button[data-action]' ? {dataset: {action: 'as-info', uid: 'as1'}} : null}})
    assert.equal(asinfoShown, 1, 'Info-Icon öffnet das AS-Info-Overlay.')
    assert.equal(asinfoTitle.textContent, 'AS-Info A & B')
    assert.deepEqual(tagInputs.map(t => t.value + '=' + t.checked), ['tag=false', 'nacht=true', 'frueh=false', 'spaet=false'], 'Overlay ist mit der gespeicherten Info vorbefüllt (inkl. nur Spät).')
    assert.equal(asinfoNote.value, 'nur nachts', 'Freitext ist vorbefüllt.')
    assert.ok(tagInputs.every(t => t.disabled), 'Overlay startet schreibgeschützt (Tags deaktiviert).')
    assert.equal(asinfoNote.disabled, true, 'Freitext ist deaktiviert.')
    assert.equal(asinfoSave.disabled, true, 'Speichern ist deaktiviert.')
    assert.equal(asinfoEdit.disabled, false, 'Stift-Button im Overlay ist aktiv.')
    // Submit ohne Edit-Modus darf nichts senden
    { const before = calls.length; await asinfoForm.handlers.submit({preventDefault() {}}); assert.equal(calls.length, before, 'Submit im schreibgeschützten Modus sendet nichts.') }
    // Stift im Overlay schaltet die Bearbeitung frei
    await root.handlers.click({target: {closest: selector => selector === '#flz-akut-asinfo-edit' ? {} : null}})
    assert.ok(tagInputs.every(t => !t.disabled), 'Nach Stift-Klick sind die Tags bearbeitbar.')
    assert.equal(asinfoNote.disabled, false, 'Nach Stift-Klick ist der Freitext bearbeitbar.')
    assert.equal(asinfoSave.disabled, false, 'Nach Stift-Klick ist Speichern aktiv.')
    assert.equal(asinfoEdit.disabled, true, 'Stift-Button ist im Edit-Modus deaktiviert.')
    // Info bearbeiten und speichern: ein PUT meta mit tagsJson + note, danach Hover aktualisiert
    tagInputs[0].checked = true
    asinfoNote.value = 'nur nachts, montags'
    const beforeInfoSave = calls.length
    await asinfoForm.handlers.submit({preventDefault() {}})
    const infoCalls = calls.slice(beforeInfoSave).filter(call => call.url.endsWith('/meta'))
    assert.equal(infoCalls.length, 1, 'Speichern sendet genau ein PUT auf /api/assistants/{uid}/meta.')
    assert.equal(infoCalls[0].options.method, 'PUT')
    assert.ok(infoCalls[0].url.endsWith('/api/assistants/as1/meta'), 'AS-Info wird direkt beim AS-Nutzer gespeichert.')
    assert.deepEqual(JSON.parse(infoCalls[0].options.body), {tagsJson: '["tag","nacht"]', note: 'nur nachts, montags'}, 'Tags und Freitext gehen als tagsJson/note an den Server.')
    assert.equal(asinfoClosed, 1, 'Overlay schließt nach dem Speichern.')
    assert.match(searchBody.innerHTML, /title="AS-Info: nur Tag, nur Nacht — nur nachts, montags"/, 'Hover-Titel zeigt die aktualisierte AS-Info.')
    // Kommentar wird beim Verlassen des Feldes direkt beim AS-Nutzer gespeichert
    const beforeComment = calls.length
    await root.handlers.focusout({target: {closest: selector => selector === 'input[data-field]' ? {dataset: {field: 'asComment', uid: 'as1'}, value: 'ruft montags zurück'} : null}})
    const commentCalls = calls.slice(beforeComment).filter(call => call.url.endsWith('/meta'))
    assert.equal(commentCalls.length, 1, 'Kommentar wird beim Verlassen genau einmal gespeichert.')
    assert.deepEqual(JSON.parse(commentCalls[0].options.body), {comment: 'ruft montags zurück'}, 'Nur das Kommentar-Feld geht als comment an den Server.')
}

async function adminScenario() {
    const permForm = node(), permStatus = node(), templateForm = node(), templateStatus = node(), templateArea = node()
    const nodes = {'flz-akut-permission-settings': permForm, 'flz-akut-permission-settings-status': permStatus, 'flz-akut-mail-template-form': templateForm, 'flz-akut-mail-template-status': templateStatus, 'flz-akut-mail-template': templateArea}
    const document = {handler: null, addEventListener(type, fn) { this.handler = fn }, getElementById: id => nodes[id]}
    const calls = []
    let result = response({ok: true})
    let formValues = {'read[]': ['role-a']}
    class FD { getAll(name) { return formValues[name] || [] } get(name) { return formValues[name] ?? null } }
    const path = fileURLToPath(new URL('../js/admin-settings.js', import.meta.url))
    const context = {document, window: {}, OC: {requestToken: 'synthetic-token', generateUrl: path => path, getCurrentUser: () => ({uid: 'dispo', displayName: 'Tom Dispatcher'})}, FormData: FD, fetch: async (...args) => {calls.push(args); return result}, Error}
    vm.runInNewContext(fs.readFileSync(path, 'utf8'), context, {filename: path})
    document.handler()
    await permForm.handlers.submit({preventDefault() {}})
    assert.equal(permStatus.textContent, 'Die Berechtigungszuordnung wurde gespeichert.')
    assert.equal(calls[0][1].method, 'PUT')
    assert.equal(calls[0][1].headers.requesttoken, 'synthetic-token')
    assert.deepEqual(JSON.parse(calls[0][1].body).permissions.read, ['role-a'])
    result = response({error: 'Nicht erlaubt'}, false)
    await permForm.handlers.submit({preventDefault() {}})
    assert.equal(permStatus.textContent, 'Nicht erlaubt')
    formValues = {template: 'Hallo {recipients}\n{shifts}'}
    result = response({ok: true, template: 'Hallo {recipients}\n{shifts}'})
    await templateForm.handlers.submit({preventDefault() {}})
    assert.equal(templateStatus.textContent, 'Die Mail-Vorlage wurde gespeichert.')
    const templateCall = calls.find(call => String(call[0]).includes('/api/admin/mail-template'))
    assert.equal(templateCall[1].method, 'PUT')
    assert.equal(templateCall[1].headers.requesttoken, 'synthetic-token')
    assert.equal(JSON.parse(templateCall[1].body).template, 'Hallo {recipients}\n{shifts}')
    result = response({ok: false, error: 'Zu lang'}, false)
    await templateForm.handlers.submit({preventDefault() {}})
    assert.equal(templateStatus.textContent, 'Zu lang')
}

async function profileScenario() {
    const form = node(), status = node(), input = node(), suggestionsBox = node()
    input.value = 'DI'
    form.requestSubmit = () => form.handlers.submit({preventDefault() {}})
    const document = {getElementById: id => ({'flz-akut-profile-form': form, 'flz-akut-profile-status': status, 'flz-akut-profile-kuerzel': input, 'flz-akut-profile-suggestions': suggestionsBox})[id]}
    const calls = []
    let conflict = true
    const context = {document, window: {}, OC: {requestToken: 'synthetic-token', generateUrl: path => path}, fetch: async (...args) => { calls.push(args); if (conflict) return response({ok: false, error: 'Dieses Kürzel ist bereits vergeben.', suggestions: ['DI1', 'D.I']}, false); return response({ok: true, kuerzel: 'DI1'}) }}
    const path = fileURLToPath(new URL('../js/profile-personal.js', import.meta.url))
    vm.runInNewContext(fs.readFileSync(path, 'utf8'), context, {filename: path})
    // Kollision: Server antwortet mit Vorschlägen, die als anklickbare Buttons erscheinen
    await form.handlers.submit({preventDefault() {}})
    assert.equal(calls.length, 1)
    assert.ok(calls[0][0].endsWith('/api/profile/kuerzel') && calls[0][1].method === 'PUT', 'Kürzel wird per PUT an /api/profile/kuerzel gespeichert.')
    assert.deepEqual(JSON.parse(calls[0][1].body), {kuerzel: 'DI'})
    assert.equal(status.textContent, 'Dieses Kürzel ist bereits vergeben.')
    assert.match(suggestionsBox.innerHTML, /data-suggestion="DI1"/, 'Vorschläge werden als anklickbare Buttons angeboten.')
    assert.match(suggestionsBox.innerHTML, /data-suggestion="D\.I"/, 'Punkt-Vorschlag ist dabei.')
    // Vorschlag anklicken: wird in das Feld übernommen und erneut gespeichert
    conflict = false
    await suggestionsBox.handlers.click({target: {closest: selector => selector === '[data-suggestion]' ? {dataset: {suggestion: 'DI1'}} : null}})
    await tick()
    assert.equal(input.value, 'DI1', 'Angeklickter Vorschlag landet im Kürzelfeld.')
    assert.equal(suggestionsBox.innerHTML, '', 'Vorschläge verschwinden nach der Übernahme.')
    assert.equal(calls.length, 2, 'Vorschlag wird erneut gespeichert.')
    assert.deepEqual(JSON.parse(calls[1][1].body), {kuerzel: 'DI1'})
    assert.equal(status.textContent, 'Kürzel gespeichert.')
}

await appScenario()
await searchScenario()
await adminScenario()
await profileScenario()
console.log('flz_akut JavaScript runtime behavior passed')

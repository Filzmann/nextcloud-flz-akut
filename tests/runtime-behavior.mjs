import assert from 'node:assert/strict'
import fs from 'node:fs'
import vm from 'node:vm'
import {fileURLToPath} from 'node:url'

function node() {
    return {innerHTML: '', textContent: '', hidden: true, disabled: false, id: '', handlers: {}, addEventListener(type, fn) { this.handlers[type] = fn }}
}
function response(data, ok = true) { return {ok, async json() { return data }} }
const tick = () => new Promise(resolve => setImmediate(resolve))

async function appScenario() {
    const root = node(), status = node(), body = node(), form = node()
    const search = node(), title = node(), searchHead = node(), searchBody = node()
    const dialog = node(), openButton = node(), closeButton = node(), rowsBody = node(), addRow = node()
    const actionDialog = node(), actionForm = node(), actionTitle = node(), actionFields = node(), actionClose = node()
    let modalShown = 0, modalClosed = 0, actionShown = 0, actionClosed = 0
    dialog.showModal = () => { modalShown++ }
    dialog.close = () => { modalClosed++ }
    actionDialog.showModal = () => { actionShown++ }
    actionDialog.close = () => { actionClosed++ }
    form.id = 'flz-akut-create'
    form.reset = () => { form.resetCalled = true }
    actionForm.id = 'flz-akut-action-form'
    actionForm.elements = {}
    actionForm.reset = () => { actionForm.resetCalled = true }
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
    const nodes = {'flz-akut-app': root, 'flz-akut-status': status, 'flz-akut-shifts': body, 'flz-akut-create': form, 'flz-akut-create-dialog': dialog, 'flz-akut-open-create': openButton, 'flz-akut-close-create': closeButton, 'flz-akut-create-rows': rowsBody, 'flz-akut-add-row': addRow, 'flz-akut-action-dialog': actionDialog, 'flz-akut-action-form': actionForm, 'flz-akut-action-title': actionTitle, 'flz-akut-action-fields': actionFields, 'flz-akut-action-close': actionClose, 'flz-akut-search-sheet': search, 'search-sheet-title': title, 'flz-akut-search-head': searchHead, 'flz-akut-search-body': searchBody}
    const calls = []
    let actionValues = {}
    const state = {ok: true, providerStatus: 'AVAILABLE', teams: [{teamCode: 'T1', bureauKey: 'west', displayName: '<Team>', assistants: [{uid: 'as1', displayName: 'A & B'}]}, {teamCode: 'T2', bureauKey: 'east', displayName: 'Other', assistants: []}], permissions: {create: ['west'], split: ['west'], assign: ['west'], contact: ['west'], reopen: ['west'], confirmHycare: ['west'], confirmPaper: ['west']}, shifts: [{id: 1, team_code: 'T1', bureau_key: 'west', state: 'open', urgency: 'sz', starts_at: '2026-01-01T10:00:00Z', ends_at: '2026-01-01T12:00:00Z'}, {id: 2, team_code: 'T2', bureau_key: 'east', state: 'mediated', urgency: 'week', starts_at: 'start', ends_at: 'end', assigned_uid: '<uid>'}, {id: 3, team_code: 'T1', bureau_key: 'west', state: 'mediated', urgency: 'unavailable', starts_at: 'start', ends_at: 'end', hycare_at: 'yes', paper_at: 'yes'}]}
    let failure = null
    const openedUrls = []
    const context = {document: {getElementById: id => nodes[id]}, location: {search: ''}, URLSearchParams, Date, OC: {requestToken: 'synthetic-token', generateUrl: path => path}, window: {open: url => { openedUrls.push(url); return null }}, FormData: class {constructor(f) { this.form = f } get(name) { return actionValues[name] }}, fetch: async (url, options) => { calls.push({url, options}); if (failure) return response({ok: false, error: failure}, false); return response(url.endsWith('/api/state') ? state : {ok: true}) }}
    const path = fileURLToPath(new URL('../js/app.js', import.meta.url))
    vm.runInNewContext(fs.readFileSync(path, 'utf8'), context, {filename: path})
    await tick()
    assert.equal(status.textContent, 'Bereit.')
    assert.match(rowsBody.innerHTML, /&lt;Team&gt;/)
    assert.match(rowsBody.innerHTML, /disabled/)
    assert.match(rowsBody.innerHTML, /<tr>/)
    assert.match(body.innerHTML, /urgency-sz/)
    assert.match(body.innerHTML, /&lt;uid&gt;/)
    assert.match(body.innerHTML, /data-action="split"/)
    assert.match(body.innerHTML, /class="asn-group"/)
    assert.ok(body.innerHTML.indexOf('&lt;Team&gt;') < body.innerHTML.indexOf('Other'), 'ASN-Gruppen müssen alphabetisch sortiert sein.')
    assert.match(body.innerHTML, /data-action="search" data-team="T1"/)
    assert.match(body.innerHTML, /\d{2}\.\d{2}/)
    assert.match(body.innerHTML, /\d{2}:\d{2}/)
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
    const clickAction = async (action, id = 1, uid = 'as1') => root.handlers.click({target: {closest: selector => selector === 'button[data-action]' ? {dataset: {action, id: String(id), uid}} : null}})
    const submitAction = async values => { actionValues = values; await actionForm.handlers.submit({preventDefault() {}}) }
    await clickAction('split')
    assert.equal(actionShown, 1)
    assert.match(actionFields.innerHTML, /type="date"/)
    assert.match(actionFields.innerHTML, /type="time"/)
    await submitAction({date: '2026-01-01', time: '11:00'})
    const splitCall = calls.find(call => call.url.endsWith('/split'))
    assert.equal(JSON.parse(splitCall.options.body).cutPointsJson, JSON.stringify([new Date('2026-01-01T11:00').toISOString()]))
    assert.ok(actionClosed >= 1)
    await clickAction('accept')
    assert.match(actionFields.innerHTML, /as1/)
    await submitAction({assistantUid: 'as1', maLabel: 'M'})
    const acceptCall = calls.find(call => call.url.endsWith('/accept'))
    assert.equal(JSON.parse(acceptCall.options.body).assistantUid, 'as1')
    assert.equal(JSON.parse(acceptCall.options.body).maLabel, 'M')
    await clickAction('contact', 1, 'as1')
    await submitAction({channel: 'phone', outcome: 'accepted', note: 'note'})
    const contactCall = calls.find(call => call.url.endsWith('/contacts'))
    assert.equal(JSON.parse(contactCall.options.body).assistantUid, 'as1')
    assert.equal(JSON.parse(contactCall.options.body).channel, 'phone')
    await clickAction('reopen', 3); await clickAction('hycare'); await clickAction('paper')
    for (const suffix of ['/split', '/accept', '/reopen', '/confirm', '/contacts']) assert.ok(calls.some(call => call.url.endsWith(suffix)), `missing ${suffix}`)
    const before = calls.length; await clickAction('split'); await actionForm.handlers.submit({preventDefault() {}}); assert.equal(calls.length, before, 'Aktionsdialog ohne Pflichtwerte darf nicht senden')
    failure = 'synthetic failure'; await clickAction('hycare'); assert.equal(status.textContent, 'synthetic failure')
    failure = null; mockRows = [makeRow({})]; const previous = calls.length; await form.handlers.submit({preventDefault() {}}); assert.equal(calls.length, previous)
    await root.handlers.click({target: {closest: () => null}})
}

async function searchScenario() {
    const root = node(), status = node()
    const search = node(), title = node(), searchHead = node(), searchBody = node()
    const actionDialog = node(), actionForm = node(), actionTitle = node(), actionFields = node(), actionClose = node()
    let actionShown = 0
    actionDialog.showModal = () => { actionShown++ }
    actionDialog.close = () => {}
    actionForm.id = 'flz-akut-action-form'
    actionForm.elements = {}
    actionForm.reset = () => {}
    const nodes = {'flz-akut-app': root, 'flz-akut-status': status, 'flz-akut-search-sheet': search, 'search-sheet-title': title, 'flz-akut-search-head': searchHead, 'flz-akut-search-body': searchBody, 'flz-akut-action-dialog': actionDialog, 'flz-akut-action-form': actionForm, 'flz-akut-action-title': actionTitle, 'flz-akut-action-fields': actionFields, 'flz-akut-action-close': actionClose}
    const calls = []
    let actionValues = {}
    const state = {ok: true, providerStatus: 'AVAILABLE', teams: [{teamCode: 'T1', bureauKey: 'west', displayName: '<Team>', assistants: [{uid: 'as1', displayName: 'A & B'}]}], permissions: {contact: ['west']}, shifts: [{id: 1, team_code: 'T1', bureau_key: 'west', state: 'open', urgency: 'sz', starts_at: '2026-01-01T10:00:00Z', ends_at: '2026-01-01T12:00:00Z'}]}
    const context = {document: {getElementById: id => nodes[id]}, location: {search: '?team=T1'}, URLSearchParams, Date, OC: {requestToken: 'synthetic-token', generateUrl: path => path}, window: {open: () => null}, FormData: class {get(name) { return actionValues[name] }}, fetch: async (url, options) => { calls.push({url, options}); return response(url.endsWith('/api/state') ? state : {ok: true}) }}
    const path = fileURLToPath(new URL('../js/app.js', import.meta.url))
    vm.runInNewContext(fs.readFileSync(path, 'utf8'), context, {filename: path})
    await tick()
    assert.equal(title.textContent, 'Suchbogen <Team>')
    assert.equal(search.hidden, false)
    assert.match(searchBody.innerHTML, /A &amp; B/)
    assert.equal(nodes['flz-akut-shifts'], undefined)
    await root.handlers.click({target: {closest: selector => selector === 'button[data-action]' ? {dataset: {action: 'contact', id: '1', uid: 'as1'}} : null}})
    assert.equal(actionShown, 1)
    actionValues = {channel: 'sms', outcome: 'open', note: ''}
    await actionForm.handlers.submit({preventDefault() {}})
    assert.ok(calls.some(call => call.url.endsWith('/contacts')), 'Kontaktversuch auf der Suchbogen-Seite fehlt.')
}

async function adminScenario() {
    const form = node(), status = node(), document = {handler: null, addEventListener(type, fn) { this.handler = fn }, getElementById(id) { return id.endsWith('-status') ? status : form }}
    const calls = []; let result = response({ok: true})
    const path = fileURLToPath(new URL('../js/admin-settings.js', import.meta.url))
    const context = {document, OC: {requestToken: 'synthetic-token', generateUrl: path => path}, FormData: class {getAll(name) { return name === 'read[]' ? ['role-a'] : [] }}, fetch: async (...args) => {calls.push(args); return result}, Error}
    vm.runInNewContext(fs.readFileSync(path, 'utf8'), context, {filename: path})
    document.handler()
    await form.handlers.submit({preventDefault() {}})
    assert.equal(status.textContent, 'Die Akutberechtigungen wurden gespeichert.')
    assert.equal(calls[0][1].method, 'PUT')
    assert.equal(calls[0][1].headers.requesttoken, 'synthetic-token')
    assert.deepEqual(JSON.parse(calls[0][1].body).permissions.read, ['role-a'])
    result = response({error: 'Nicht erlaubt'}, false)
    await form.handlers.submit({preventDefault() {}})
    assert.equal(status.textContent, 'Nicht erlaubt')
}

await appScenario()
await searchScenario()
await adminScenario()
console.log('flz_akut JavaScript runtime behavior passed')

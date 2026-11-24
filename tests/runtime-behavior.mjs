import assert from 'node:assert/strict'
import fs from 'node:fs'
import vm from 'node:vm'
import {fileURLToPath} from 'node:url'

function node() {
    return {innerHTML: '', textContent: '', hidden: true, disabled: false, handlers: {}, addEventListener(type, fn) { this.handlers[type] = fn }}
}
function response(data, ok = true) { return {ok, async json() { return data }} }
const tick = () => new Promise(resolve => setImmediate(resolve))

async function appScenario() {
    const root = node(), status = node(), body = node(), form = node(), submit = node(), select = node()
    const search = node(), title = node(), searchHead = node(), searchBody = node()
    select.selectedOptions = [{dataset: {bureau: 'west'}}]
    form.elements = {teamCode: select}
    form.querySelector = () => submit
    form.reset = () => { form.resetCalled = true }
    const nodes = {'flz-akut-app': root, 'flz-akut-status': status, 'flz-akut-shifts': body, 'flz-akut-create': form, 'flz-akut-search-sheet': search, 'search-sheet-title': title, 'flz-akut-search-head': searchHead, 'flz-akut-search-body': searchBody}
    const calls = [], prompts = []
    const state = {ok: true, providerStatus: 'AVAILABLE', teams: [{teamCode: 'T1', bureauKey: 'west', displayName: '<Team>', assistants: [{uid: 'as1', displayName: 'A & B'}]}, {teamCode: 'T2', bureauKey: 'east', displayName: 'Other', assistants: []}], permissions: {create: ['west'], split: ['west'], assign: ['west'], contact: ['west'], reopen: ['west'], confirmHycare: ['west'], confirmPaper: ['west']}, shifts: [{id: 1, team_code: 'T1', bureau_key: 'west', state: 'open', urgency: 'sz', starts_at: '2026-01-01T10:00:00Z', ends_at: '2026-01-01T12:00:00Z'}, {id: 2, team_code: 'T2', bureau_key: 'east', state: 'mediated', urgency: 'week', starts_at: 'start', ends_at: 'end', assigned_uid: '<uid>'}, {id: 3, team_code: 'T1', bureau_key: 'west', state: 'mediated', urgency: 'unavailable', starts_at: 'start', ends_at: 'end', hycare_at: 'yes', paper_at: 'yes'}]}
    let failure = null
    const context = {document: {getElementById: id => nodes[id]}, location: {search: '?team=T1'}, URLSearchParams, Date, OC: {requestToken: 'synthetic-token', generateUrl: path => path}, FormData: class {get(name) { return {teamCode: 'T1', startsAt: '2026-01-01T10:00:00Z', endsAt: '2026-01-01T12:00:00Z', comment: 'test'}[name] }}, prompt: () => prompts.shift(), fetch: async (url, options) => { calls.push({url, options}); if (failure) return response({ok: false, error: failure}, false); return response(url.endsWith('/api/state') ? state : {ok: true}) }}
    const path = fileURLToPath(new URL('../js/app.js', import.meta.url))
    vm.runInNewContext(fs.readFileSync(path, 'utf8'), context, {filename: path})
    await tick()
    assert.equal(status.textContent, 'Bereit.')
    assert.match(select.innerHTML, /&lt;Team&gt;/)
    assert.match(select.innerHTML, /disabled/)
    assert.match(body.innerHTML, /urgency-sz/)
    assert.match(body.innerHTML, /&lt;uid&gt;/)
    assert.match(body.innerHTML, /data-action="split"/)
    assert.equal(search.hidden, false)
    assert.equal(title.textContent, 'Suchbogen <Team>')
    assert.match(searchBody.innerHTML, /A &amp; B/)
    assert.equal(calls[0].options.headers.requesttoken, 'synthetic-token')
    await form.handlers.submit({preventDefault() {}})
    assert.equal(calls.find(call => call.url.endsWith('/api/shifts')).options.method, 'POST')
    assert.equal(form.resetCalled, true)
    const click = async (action, id = 1, uid = 'as1') => root.handlers.click({target: {closest: () => ({dataset: {action, id: String(id), uid}})}})
    prompts.push('2026-01-01T11:00:00Z'); await click('split')
    prompts.push('as1', 'M'); await click('accept')
    await click('reopen', 3); await click('hycare'); await click('paper')
    prompts.push('phone', 'accepted', 'note'); await click('contact')
    for (const suffix of ['/split', '/accept', '/reopen', '/confirm', '/contacts']) assert.ok(calls.some(call => call.url.endsWith(suffix)), `missing ${suffix}`)
    const before = calls.length; prompts.push(null); await click('split'); assert.equal(calls.length, before)
    failure = 'synthetic failure'; await click('hycare'); assert.equal(status.textContent, 'synthetic failure')
    failure = null; select.selectedOptions = []; const previous = calls.length; await form.handlers.submit({preventDefault() {}}); assert.equal(calls.length, previous)
    await root.handlers.click({target: {closest: () => null}})
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
await adminScenario()
console.log('flz_akut JavaScript runtime behavior passed')

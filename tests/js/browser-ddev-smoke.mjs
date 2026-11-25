import assert from 'node:assert/strict';
import { writeFile } from 'node:fs/promises';

const required = ['AKUT_CDP_PORT', 'AKUT_BASE_URL', 'AKUT_BROWSER_USER', 'AKUT_BROWSER_PASSWORD'];
for (const name of required) {
    if (!process.env[name]) throw new Error(`${name} fehlt.`);
}

const port = Number(process.env.AKUT_CDP_PORT);
const baseUrl = process.env.AKUT_BASE_URL.replace(/\/$/, '');
const user = process.env.AKUT_BROWSER_USER;
const password = process.env.AKUT_BROWSER_PASSWORD;
const screenshotPath = process.env.AKUT_BROWSER_SCREENSHOT || '';
const smokeComment = `Browser-Smoke Nachtschicht ${Date.now()}`;
const smokeDate = '2026-11-25';
const expectedEndDate = '2026-11-26';

const targetResponse = await fetch(`http://127.0.0.1:${port}/json/new?about%3Ablank`, { method: 'PUT' });
if (!targetResponse.ok) throw new Error(`Chrome-Ziel konnte nicht angelegt werden: HTTP ${targetResponse.status}`);
const target = await targetResponse.json();

class CdpClient {
    constructor(url) {
        this.nextId = 1;
        this.pending = new Map();
        this.socket = new WebSocket(url);
    }

    async connect() {
        await new Promise((resolve, reject) => {
            this.socket.addEventListener('open', resolve, { once: true });
            this.socket.addEventListener('error', reject, { once: true });
        });
        this.socket.addEventListener('message', event => {
            const message = JSON.parse(String(event.data));
            if (!message.id || !this.pending.has(message.id)) return;
            const { resolve, reject } = this.pending.get(message.id);
            this.pending.delete(message.id);
            if (message.error) reject(new Error(`${message.error.message} (${message.error.code})`));
            else resolve(message.result || {});
        });
    }

    call(method, params = {}) {
        const id = this.nextId++;
        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.socket.send(JSON.stringify({ id, method, params }));
        });
    }

    close() {
        this.socket.close();
    }
}

const cdp = new CdpClient(target.webSocketDebuggerUrl);
await cdp.connect();

const sleep = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
async function evaluate(expression) {
    const result = await cdp.call('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (result.exceptionDetails) {
        throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text || 'JavaScript-Auswertung fehlgeschlagen.');
    }
    return result.result?.value;
}

async function waitFor(expression, label, timeout = 20000) {
    const deadline = Date.now() + timeout;
    let lastError = null;
    while (Date.now() < deadline) {
        try {
            if (await evaluate(`Boolean(${expression})`)) return;
        } catch (error) {
            lastError = error;
        }
        await sleep(100);
    }
    const pageState = await evaluate(`({url: location.href, title: document.title, status: document.getElementById('flz-akut-status')?.textContent || ''})`);
    throw new Error(`${label} wurde nicht erreicht. Zustand: ${JSON.stringify(pageState)}${lastError ? `; letzter Fehler: ${lastError.message}` : ''}`);
}

async function fetchStateShifts() {
    return evaluate(`(async () => { const response = await fetch('/index.php/apps/flz_akut/api/state'); const data = await response.json(); return data.shifts || []; })()`);
}

try {
    await cdp.call('Runtime.enable');
    await cdp.call('Page.enable');
    await cdp.call('Network.enable');
    await cdp.call('Emulation.setDeviceMetricsOverride', { width: 1200, height: 800, deviceScaleFactor: 1, mobile: false });
    await cdp.call('Page.addScriptToEvaluateOnNewDocument', {
        source: `window.__akutBrowserErrors = []; window.addEventListener('error', event => window.__akutBrowserErrors.push(String(event.error || event.message))); window.addEventListener('unhandledrejection', event => window.__akutBrowserErrors.push(String(event.reason)));`,
    });

    await cdp.call('Network.clearBrowserCookies');
    await cdp.call('Network.clearBrowserCache');
    await cdp.call('Page.navigate', { url: `${baseUrl}/index.php/login?browser-smoke=${Date.now()}` });
    await waitFor(`document.readyState === 'complete' && document.querySelector('form[name=login] #user') && document.querySelector('form[name=login] #password')`, 'Loginformular');
    await evaluate(`(() => {
        const form = document.querySelector('form[name=login]');
        const userInput = form.querySelector('#user');
        const passwordInput = form.querySelector('#password');
        userInput.value = ${JSON.stringify(user)};
        passwordInput.value = ${JSON.stringify(password)};
        userInput.dispatchEvent(new Event('input', {bubbles: true}));
        passwordInput.dispatchEvent(new Event('input', {bubbles: true}));
        form.requestSubmit();
        return true;
    })()`);
    await waitFor(`!location.pathname.endsWith('/login') && !document.querySelector('form[name=login]')`, 'Anmeldung', 30000);

    await cdp.call('Page.navigate', { url: `${baseUrl}/index.php/apps/flz_akut/?browser-smoke=${Date.now()}` });
    await waitFor(`document.readyState === 'complete' && document.getElementById('flz-akut-app')`, 'App-Dokument');
    await waitFor(`document.getElementById('flz-akut-status')?.textContent === 'Bereit.'`, 'geladener App-Zustand');

    assert.equal(await evaluate(`Boolean(document.getElementById('flz-akut-open-create'))`), true, 'Der "+ Neue Lücke"-Button fehlt.');
    assert.equal(await evaluate(`document.getElementById('flz-akut-create-dialog')?.tagName`), 'DIALOG', 'Das Lücke-Formular muss ein Dialog-Overlay sein.');
    assert.equal(await evaluate(`document.getElementById('flz-akut-create-dialog')?.open`), false, 'Das Overlay muss initial geschlossen sein.');
    assert.equal(await evaluate(`Boolean(document.querySelector('#flz-akut-create input[name=date][type=date]'))`), true, 'Das Datumsfeld fehlt.');
    assert.equal(await evaluate(`document.querySelectorAll('#flz-akut-create input[type=time]').length`), 2, 'Es müssen zwei Zeitfelder (Von/Bis) existieren.');
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('#flz-akut-create select[name=teamCode] option')).some(option => option.dataset.bureau && !option.disabled)`), true, 'Es muss mindestens eine ASN-Option des eigenen Büros auswählbar sein.');

    await evaluate(`document.getElementById('flz-akut-open-create').click()`);
    await waitFor(`document.getElementById('flz-akut-create-dialog')?.open === true`, 'Overlay nach Klick auf "+ Neue Lücke"');
    {
        const layout = await evaluate(`(() => { const dialog = document.getElementById('flz-akut-create-dialog'); const table = document.getElementById('flz-akut-create-table'); const title = dialog.querySelector('h2'); return {dialogWidth: dialog.clientWidth, tableWidth: table.getBoundingClientRect().width, titleBelowTable: title.getBoundingClientRect().bottom <= table.getBoundingClientRect().top, hint: Boolean(dialog.querySelector('.hint'))}; })()`);
        assert.ok(layout.tableWidth >= layout.dialogWidth * 0.9, `Tabelle muss die volle Overlay-Breite nutzen (${layout.tableWidth} < 90% von ${layout.dialogWidth})`);
        assert.equal(layout.titleBelowTable, true, 'Überschrift muss über der Tabelle stehen, nicht daneben.');
        assert.equal(layout.hint, false, 'Der Hint-Satz darf nicht mehr im Overlay stehen.');
    }

    if (screenshotPath) {
        const screenshot = await cdp.call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
        await writeFile(screenshotPath, Buffer.from(screenshot.data, 'base64'));
    }

    await evaluate(`(() => {
        const row = document.querySelector('#flz-akut-create-rows tr');
        const select = row.querySelector('select[name=teamCode]');
        const option = Array.from(select.options).find(item => item.dataset.bureau && !item.disabled);
        select.value = option.value;
        select.dispatchEvent(new Event('change', {bubbles: true}));
        row.querySelector('[name=date]').value = ${JSON.stringify(smokeDate)};
        row.querySelector('[name=startTime]').value = '22:00';
        row.querySelector('[name=endTime]').value = '06:00';
        row.querySelector('[name=comment]').value = ${JSON.stringify(smokeComment)};
        return true;
    })()`);
    await evaluate(`document.getElementById('flz-akut-add-row').click()`);
    await waitFor(`document.querySelectorAll('#flz-akut-create-rows tr').length === 2`, 'zweite Zeile nach "+ Zeile hinzufügen"');
    const copied = await evaluate(`(() => { const rows = document.querySelectorAll('#flz-akut-create-rows tr'); const first = rows[0], second = rows[1]; return {team: second.querySelector('select[name=teamCode]').value === first.querySelector('select[name=teamCode]').value, date: second.querySelector('[name=date]').value === ${JSON.stringify(smokeDate)} && second.querySelector('[name=date]').value === first.querySelector('[name=date]').value, start: second.querySelector('[name=startTime]').value === '22:00', end: second.querySelector('[name=endTime]').value === '06:00'}; })()`);
    assert.deepEqual(copied, {team: true, date: true, start: true, end: true}, 'Neue Zeile muss ASN, Datum und Zeiten der Vorzeile übernehmen.');
    await evaluate(`(() => {
        const row = document.querySelectorAll('#flz-akut-create-rows tr')[1];
        row.querySelector('[name=endTime]').value = '07:00';
        row.querySelector('[name=comment]').value = ${JSON.stringify(smokeComment + '-2')};
        document.getElementById('flz-akut-create').requestSubmit();
        return true;
    })()`);
    await waitFor(`document.getElementById('flz-akut-create-dialog')?.open === false`, 'Overlay schließt nach erfolgreichem Anlegen');

    let created = null;
    {
        const deadline = Date.now() + 20000;
        while (Date.now() < deadline && !created) {
            const shifts = await fetchStateShifts();
            const found = shifts.find(item => item.comment === smokeComment);
            const second = shifts.find(item => item.comment === smokeComment + '-2');
            if (found && second) created = {first: found, second};
            else await sleep(150);
        }
    }
    if (!created) throw new Error('Die Browser-Smoke-Shifts wurden nicht im State gefunden.');
    created = created.first;
    assert.ok(String(created.ends_at).startsWith(expectedEndDate), `Nachtschicht (bis 06:00 < von 22:00) muss am ${expectedEndDate} enden, Ist: ${created.ends_at}`);
    assert.equal(created.state, 'open');
    await waitFor(`Boolean(document.querySelector('#flz-akut-shifts button[data-action="split"][data-id="${created.id}"]'))`, 'Schicht-Zeile des erzeugten Falls in der ASN-Gruppe');
    {
        const groups = await evaluate(`Array.from(document.querySelectorAll('#flz-akut-shifts tr.asn-group')).map(row => ({name: row.querySelector('th')?.textContent, hasSearch: Boolean(row.querySelector('button[data-action="search"]'))}))`);
        assert.ok(groups.length >= 1, 'ASN-Gruppenzeilen fehlen.');
        assert.ok(groups.every(group => group.hasSearch), 'Jede ASN-Gruppe braucht einen Suchbogen-Button.');
        const names = groups.map(group => group.name);
        assert.deepEqual(names, [...names].sort((a, b) => a.localeCompare(b, 'de')), 'ASN-Gruppen müssen alphabetisch sortiert sein.');
        await evaluate(`document.querySelector('#flz-akut-shifts button[data-action="search"]').click()`);
        await waitFor(`document.getElementById('flz-akut-search-sheet') && !document.getElementById('flz-akut-search-sheet').hidden`, 'Suchbogen öffnet über Gruppen-Button');
    }

    await evaluate(`document.querySelector('button[data-action="split"][data-id="${created.id}"]').click()`);
    await waitFor(`document.getElementById('flz-akut-action-dialog')?.open === true`, 'Aktionsdialog zum Teilen');
    assert.equal(await evaluate(`document.getElementById('flz-akut-action-title')?.textContent`), 'Schicht teilen');
    await evaluate(`(() => {
        const form = document.getElementById('flz-akut-action-form');
        form.elements.date.value = ${JSON.stringify(smokeDate)};
        form.elements.time.value = '23:30';
        form.requestSubmit();
        return true;
    })()`);
    await waitFor(`document.getElementById('flz-akut-action-dialog')?.open === false`, 'Aktionsdialog schließt nach dem Teilen');
    {
        const deadline = Date.now() + 20000;
        let splitState = null;
        while (Date.now() < deadline && !splitState) {
            const shifts = await fetchStateShifts();
            const parent = shifts.find(item => Number(item.id) === Number(created.id));
            const parts = shifts.filter(item => Number(item.parent_shift_id) === Number(created.id));
            if (parent && parent.state === 'split' && parts.length === 2) splitState = {parent, parts};
            else await sleep(150);
        }
        if (!splitState) throw new Error('Die Teilung über den Aktionsdialog wurde nicht persistiert.');
        assert.ok(splitState.parts.every(part => String(part.starts_at).startsWith(smokeDate) || String(part.ends_at).startsWith(expectedEndDate)), 'Teile müssen lückenfrei innerhalb der Nachtschicht liegen.');
    }

    await evaluate(`document.getElementById('flz-akut-open-create').click()`);
    await waitFor(`document.getElementById('flz-akut-create-dialog')?.open === true`, 'Overlay für Negativfall');
    await evaluate(`(() => {
        const row = document.querySelector('#flz-akut-create-rows tr');
        row.querySelector('[name=date]').value = ${JSON.stringify(smokeDate)};
        row.querySelector('[name=startTime]').value = '08:00';
        row.querySelector('[name=endTime]').value = '08:00';
        document.getElementById('flz-akut-create').requestSubmit();
        return true;
    })()`);
    await waitFor(`document.getElementById('flz-akut-status')?.textContent.includes('Schichtende muss nach dem Beginn liegen.')`, 'Fehlermeldung bei gleicher Von-/Bis-Zeit');
    assert.equal(await evaluate(`document.getElementById('flz-akut-create-dialog')?.open`), true, 'Das Overlay muss bei einem Fehler offen bleiben.');
    const smokeShifts = await fetchStateShifts();
    assert.equal(smokeShifts.filter(item => item.comment === smokeComment && !item.parent_shift_id).length, 1, 'Ein abgelehnter Fall darf keinen Datensatz anlegen.');
    await evaluate(`document.getElementById('flz-akut-close-create').click()`);
    await waitFor(`document.getElementById('flz-akut-create-dialog')?.open === false`, 'Overlay schließt über "Schließen"');

    const browserErrors = await evaluate(`window.__akutBrowserErrors || []`);
    assert.deepEqual(browserErrors, [], `Die Oberfläche erzeugte Browserfehler: ${browserErrors.join('; ')}`);
    console.log('flz_akut DDEV-Browser-Smoke (Overlay, Nachtschicht, Negativfall): OK');
} finally {
    cdp.close();
}

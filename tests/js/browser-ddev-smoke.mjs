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
// Dynamisches Datum (morgen), damit die Smoke-Schicht nie "vergangen" ist und aus dem State herausgefiltert würde
const pad = n => String(n).padStart(2, '0');
const tomorrow = new Date(Date.now() + 86400000);
const smokeDate = `${tomorrow.getFullYear()}-${pad(tomorrow.getMonth() + 1)}-${pad(tomorrow.getDate())}`;
const dayAfter = new Date(tomorrow.getTime() + 86400000);
const expectedEndDate = `${dayAfter.getFullYear()}-${pad(dayAfter.getMonth() + 1)}-${pad(dayAfter.getDate())}`;

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
        source: `window.__akutBrowserErrors = []; window.addEventListener('error', event => window.__akutBrowserErrors.push(String(event.error || event.message))); window.addEventListener('unhandledrejection', event => window.__akutBrowserErrors.push(String(event.reason))); window.__openedUrls = []; window.open = url => { window.__openedUrls.push(String(url)); return null; };`,
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
    await waitFor(`(document.getElementById('flz-akut-status')?.textContent || '').startsWith('Bereit.')`, 'geladener App-Zustand');
    await waitFor(`(document.getElementById('flz-akut-status')?.textContent || '').includes('Live alle 10 s, zuletzt')`, 'Live-Polling-Status nach dem ersten Poll', 25000);
    {
        const width = await evaluate(`(() => { const app = document.getElementById('flz-akut-app'); const content = document.getElementById('content'); return {app: Math.round(app.getBoundingClientRect().width), content: content ? content.clientWidth : 0}; })()`);
        assert.ok(width.app >= width.content * 0.95, `App-Root muss die volle Fensterbreite nutzen (${width.app} < 95% von ${width.content})`);
    }

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
        const controls = await evaluate(`(() => { const row = document.querySelector('#flz-akut-create-rows tr'); const rect = el => { const r = el.getBoundingClientRect(); return {top: Math.round(r.top), width: Math.round(r.width)} }; return {buttons: ['#flz-akut-add-row', '#flz-akut-create button[type=submit]', '#flz-akut-close-create'].map(s => rect(document.querySelector(s))), date: rect(row.querySelector('[name=date]')), time: rect(row.querySelector('[name=startTime]'))}; })()`);
        const tops = controls.buttons.map(button => button.top);
        assert.ok(Math.max(...tops) - Math.min(...tops) <= 1, `Buttons müssen nebeneinander liegen (tops: ${tops})`);
        assert.ok(controls.date.width <= 150, `Datumsfeld muss schmal bleiben (${controls.date.width}px)`);
        assert.ok(controls.time.width <= 100, `Zeitfeld muss schmal bleiben (${controls.time.width}px)`);
        const gaps = await evaluate(`(() => { const row = document.querySelector('#flz-akut-create-rows tr'); const inputs = Array.from(row.querySelectorAll('input,select')); const rects = inputs.map(el => el.getBoundingClientRect()); return rects.slice(1).map((r, i) => Math.round(r.left - rects[i].right)); })()`);
        assert.ok(gaps.every(gap => gap <= 16), `Zwischen den Feldern darf kaum Leerraum bleiben (gaps: ${gaps})`);
        const scroll = await evaluate(`(() => { const wrap = document.querySelector('#flz-akut-create .table-scroll'); return {scrollWidth: wrap.scrollWidth, clientWidth: wrap.clientWidth}; })()`);
        assert.ok(scroll.scrollWidth <= scroll.clientWidth + 1, `Kein horizontaler Scrollbalken unter der Tabelle (scrollWidth ${scroll.scrollWidth} > clientWidth ${scroll.clientWidth})`);
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
        await waitFor(`(window.__openedUrls || []).some(url => url.includes('/suchbogen?team='))`, 'Suchbogen öffnet im neuen Tab');
        assert.equal(await evaluate(`Boolean(document.getElementById('flz-akut-search-sheet'))`), false, 'Die Hauptseite darf keinen Suchbogen mehr enthalten.');
        const searchPath = await evaluate(`window.__openedUrls.find(url => url.includes('/suchbogen?team='))`);
        await cdp.call('Page.navigate', { url: new URL(searchPath, baseUrl).href });
        await waitFor(`document.readyState === 'complete' && document.getElementById('flz-akut-search-sheet')`, 'Suchbogen-Seite');
        assert.equal(await evaluate(`Boolean(document.getElementById('flz-akut-shifts'))`), false, 'Die Suchbogen-Seite darf keine Schichttabelle zeigen.');
        assert.equal(await evaluate(`document.getElementById('flz-akut-search-sheet').hidden`), false, 'Suchbogen muss auf seiner Seite sichtbar sein.');
        assert.ok(await evaluate(`document.getElementById('flz-akut-search-body').textContent.length > 0`), 'Suchbogen muss Zeilen rendern.');
        await cdp.call('Page.navigate', { url: `${baseUrl}/index.php/apps/flz_akut/?browser-smoke=${Date.now()}` });
        await waitFor(`(document.getElementById('flz-akut-status')?.textContent || '').startsWith('Bereit.')`, 'Zurück zur Hauptseite');
    }

    {
        const second = (await fetchStateShifts()).find(item => item.comment === smokeComment + '-2');
        assert.ok(second, 'Zweiter Smoke-Shift fehlt.');
        assert.equal(await evaluate(`Boolean(document.querySelector('button[data-action="hycare"][data-id="${created.id}"]'))`), false, 'Ohne Assistent darf es keinen HyCare-Button geben.');
        // AS direkt über das Suchfeld in der Zeile eintragen (kein Dialog), Speichern beim Verlassen des Feldes
        assert.equal(await evaluate(`Boolean(document.querySelector('input[name=assistantUid][data-field="assistantUid"][data-id="${second.id}"][list="flz-akut-asn-suggestions-${second.id}"]'))`), true, 'AS-Eintrag muss ein Suchfeld mit Datalist sein.');
        assert.ok(await evaluate(`document.querySelectorAll('#flz-akut-asn-suggestions-${second.id} option').length >= 1`), 'Datalist muss Team-Vorschläge enthalten.');
        await evaluate(`(() => {
            const input = document.querySelector('input[name=assistantUid][data-id="${second.id}"]');
            const ma = document.querySelector('input[name=maLabel][data-id="${second.id}"]');
            input.value = document.querySelector('#flz-akut-asn-suggestions-${second.id} option').value;
            ma.value = 'EBT';
            input.dispatchEvent(new FocusEvent('focusout', {bubbles: true}));
            return true;
        })()`);
        await waitFor(`(async () => { const r = await fetch('/index.php/apps/flz_akut/api/state'); const d = await r.json(); const s = (d.shifts || []).find(i => Number(i.id) === ${second.id}); return s && s.state === 'mediated' && s.assigned_uid; })()`, 'Shift ist vermittelt (AS-Feld + focusout)');
        await waitFor(`(() => {
            const row = Array.from(document.querySelectorAll('#flz-akut-shifts tr')).find(tr => tr.querySelector('button[data-action="reopen"][data-id="${second.id}"]'));
            if (!row) return false;
            const asInput = row.querySelector('input[name=assistantUid][data-id="${second.id}"]');
            const mediated = row.querySelector('td.mediated-cell')?.textContent.trim();
            return asInput && asInput.value && mediated && mediated !== '—';
        })()`, 'Vermittelter AS (Feldwert gesetzt) mit Vermittlungszeit und Erneut-öffnen-Button sichtbar');
        await waitFor(`Boolean(document.querySelector('button[data-action="hycare"][data-id="${second.id}"]'))`, 'HyCare-Button nach Vermittlung');
        await evaluate(`document.querySelector('button[data-action="hycare"][data-id="${second.id}"]').click()`);
        await waitFor(`(async () => { const r = await fetch('/index.php/apps/flz_akut/api/state'); const d = await r.json(); const s = (d.shifts || []).find(i => Number(i.id) === ${second.id}); return s && s.hycare_at; })()`, 'HyCare gesetzt');
        await waitFor(`(() => { const row = Array.from(document.querySelectorAll('#flz-akut-shifts tr')).find(tr => tr.querySelector('button[data-action="hycare"][data-id="${second.id}"]')); if (!row) return false; return Array.from(row.querySelectorAll('td')).slice(0, 3).every(td => td.className.includes('documented-hycare')); })()`, 'Datum, Von und Bis lila markiert');
    }

    await evaluate(`document.querySelector('button[data-action="split"][data-id="${created.id}"]').click()`);
    await waitFor(`document.getElementById('flz-akut-action-dialog')?.open === true`, 'Aktionsdialog zum Teilen');
    assert.equal(await evaluate(`document.getElementById('flz-akut-action-title')?.textContent`), 'Schicht teilen');
    assert.equal(await evaluate(`document.querySelector('#flz-akut-action-fields [name=date]')?.value`), smokeDate, 'Teilungsdatum ist mit dem Schichtdatum vorbefüllt.');
    assert.equal(await evaluate(`document.querySelector('#flz-akut-action-fields [name=cutRange]')?.type`), 'range', 'Schieberegler im 15-Minuten-Raster vorhanden.');
    assert.equal(await evaluate(`document.querySelector('#flz-akut-action-fields [name=cutRange]')?.step`), '15', 'Schieberegler im 15-Minuten-Raster.');
    await evaluate(`(() => {
        const form = document.getElementById('flz-akut-action-form');
        const range = form.querySelector('[name=cutRange]');
        range.value = '1410';
        range.dispatchEvent(new Event('input', {bubbles: true}));
        if (form.elements.time.value !== '23:30') throw new Error('Schieberegler setzt das Zeitfeld nicht: ' + form.elements.time.value);
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
            const parts = shifts.filter(item => item.comment === smokeComment && Number(item.id) !== Number(created.id));
            // Die alte Schicht wird durch die Teile ersetzt: Ursprung (state=split) darf nicht mehr im State sichtbar sein
            if (!parent && parts.length === 2) splitState = {parts};
            else await sleep(150);
        }
        if (!splitState) throw new Error('Die Teilung wurde nicht persistiert (Ursprung verschwindet, zwei Teile bleiben).');
        assert.ok(splitState.parts.every(part => part.parent_shift_id === null || part.parent_shift_id === 0), 'Geteilte Schichten sind zwei komplett getrennte Schichten ohne parent_shift_id.');
        assert.ok(splitState.parts.every(part => String(part.starts_at).startsWith(smokeDate) || String(part.ends_at).startsWith(expectedEndDate)), 'Teile müssen lückenfrei innerhalb der Nachtschicht liegen.');
    }

    const smokeCountBefore = (await fetchStateShifts()).filter(item => item.comment === smokeComment).length;
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
    assert.equal(smokeShifts.filter(item => item.comment === smokeComment).length, smokeCountBefore, 'Ein abgelehnter Fall darf keinen Datensatz anlegen.');
    await evaluate(`document.getElementById('flz-akut-close-create').click()`);
    await waitFor(`document.getElementById('flz-akut-create-dialog')?.open === false`, 'Overlay schließt über "Schließen"');

    // Persönliche Einstellungen (/settings/user/flz_akut): Kürzel hinterlegen, validieren, wiederherstellen
    await cdp.call('Page.navigate', { url: `${baseUrl}/index.php/settings/user/flz_akut?browser-smoke=${Date.now()}` });
    await waitFor(`document.readyState === 'complete' && document.getElementById('flz-akut-profile')`, 'Akutdienst-Sektion in /settings/user', 30000);
    await sleep(1000); // profile-personal.js initialisiert nach DOMContentLoaded
    const originalKuerzel = await evaluate(`document.getElementById('flz-akut-profile-kuerzel')?.value || ''`);
    await evaluate(`(() => {
        const input = document.getElementById('flz-akut-profile-kuerzel');
        input.value = 'SMK';
        input.dispatchEvent(new Event('input', {bubbles: true}));
        document.getElementById('flz-akut-profile-form').requestSubmit();
        return true;
    })()`);
    await waitFor(`document.getElementById('flz-akut-profile-status')?.textContent === 'Kürzel gespeichert.'`, 'Kürzel speichern', 20000);
    // Ungültiges Kürzel (Ziffer) wird clientseitig vom pattern blockiert, serverseitig mit 400 abgelehnt
    assert.equal(await evaluate(`(() => { const i = document.getElementById('flz-akut-profile-kuerzel'); i.value = 'AB1'; return i.checkValidity(); })()`), false, 'Kürzel mit Ziffer darf clientseitig nicht valide sein.');
    const invalidPut = await evaluate(`(async () => { const r = await fetch('/index.php/apps/flz_akut/api/profile/kuerzel', {method: 'PUT', credentials: 'same-origin', headers: {'Content-Type': 'application/json', requesttoken: window.OC.requestToken}, body: JSON.stringify({kuerzel: 'AB1'})}); const d = await r.json(); return { status: r.status, error: d.error || '' }; })()`);
    assert.equal(invalidPut.status, 400, 'Ungültiges Kürzel muss serverseitig 400 liefern.');
    assert.ok(invalidPut.error.includes('höchstens'), 'Fehlermeldung muss die Zeichenregel nennen.');
    await evaluate(`(() => {
        const input = document.getElementById('flz-akut-profile-kuerzel');
        input.value = ${JSON.stringify(originalKuerzel)};
        input.dispatchEvent(new Event('input', {bubbles: true}));
        document.getElementById('flz-akut-profile-form').requestSubmit();
        return true;
    })()`);
    await waitFor(`document.getElementById('flz-akut-profile-status')?.textContent === 'Kürzel gespeichert.'`, 'Kürzel wiederherstellen', 20000);

    const browserErrors = await evaluate(`window.__akutBrowserErrors || []`);
    assert.deepEqual(browserErrors, [], `Die Oberfläche erzeugte Browserfehler: ${browserErrors.join('; ')}`);
    console.log('flz_akut DDEV-Browser-Smoke (Overlay, Nachtschicht, Negativfall): OK');
} finally {
    cdp.close();
}

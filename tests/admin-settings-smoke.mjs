import fs from 'node:fs'

const template = fs.readFileSync(new URL('../templates/admin.php', import.meta.url), 'utf8')
const script = fs.readFileSync(new URL('../js/admin-settings.js', import.meta.url), 'utf8')
const info = fs.readFileSync(new URL('../appinfo/info.xml', import.meta.url), 'utf8')

for (const expected of ['<table', '<caption', 'scope="col"', 'scope="row"', 'Berechtigungsmatrix', 'type="checkbox"', 'data-capability', 'aria-label', 'role="status"', 'Berechtigungen speichern', 'id="flz-akut-mail-template-form"', 'id="flz-akut-mail-template"', 'Mail-Vorlage speichern', '{team}', '{recipients}', '{shifts}', '{date}', '<textarea', 'id="flz-akut-office-day-form"', 'id="flz-akut-office-day-start"', 'name="officeDayStart"', 'type="time"', 'Bürotagbeginn', '06:00']) {
    if (!template.includes(expected)) throw new Error(`Admin-Oberfläche fehlt: ${expected}`)
}
for (const expected of ['permissions', 'requesttoken', "method: 'PUT'", 'OC.generateUrl', '/api/admin/mail-template', 'template', 'flz-akut-mail-template-form', '/api/admin/office-day-start', 'officeDayStart', 'flz-akut-office-day-form']) {
    if (!script.includes(expected)) throw new Error(`Admin-Speichervertrag fehlt: ${expected}`)
}
for (const expected of ['OCA\\FlzAkut\\Settings\\Admin', 'OCA\\FlzAkut\\Settings\\AdminSection']) {
    if (!info.includes(expected)) throw new Error(`Nextcloud-Adminregistrierung fehlt: ${expected}`)
}

console.log('flz_akut admin settings smoke passed')

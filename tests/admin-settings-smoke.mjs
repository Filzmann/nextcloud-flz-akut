import fs from 'node:fs'

const template = fs.readFileSync(new URL('../templates/admin.php', import.meta.url), 'utf8')
const script = fs.readFileSync(new URL('../js/admin-settings.js', import.meta.url), 'utf8')
const info = fs.readFileSync(new URL('../appinfo/info.xml', import.meta.url), 'utf8')

for (const expected of ['<fieldset', '<legend', 'type="checkbox"', 'role="status"', 'Berechtigungen speichern']) {
    if (!template.includes(expected)) throw new Error(`Admin-Oberfläche fehlt: ${expected}`)
}
for (const expected of ['permissions', 'requesttoken', "method: 'PUT'", 'OC.generateUrl']) {
    if (!script.includes(expected)) throw new Error(`Admin-Speichervertrag fehlt: ${expected}`)
}
for (const expected of ['OCA\\FlzAkut\\Settings\\Admin', 'OCA\\FlzAkut\\Settings\\AdminSection']) {
    if (!info.includes(expected)) throw new Error(`Nextcloud-Adminregistrierung fehlt: ${expected}`)
}

console.log('flz_akut admin settings smoke passed')

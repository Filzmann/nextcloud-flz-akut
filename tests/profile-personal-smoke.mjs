import fs from 'node:fs'

const template = fs.readFileSync(new URL('../templates/profile-personal.php', import.meta.url), 'utf8')
const script = fs.readFileSync(new URL('../js/profile-personal.js', import.meta.url), 'utf8')
const info = fs.readFileSync(new URL('../appinfo/info.xml', import.meta.url), 'utf8')

for (const expected of ['flz-akut-profile-form', 'flz-akut-profile-kuerzel', 'name="kuerzel"', 'Kürzel speichern', 'role="status"', 'flz-akut-profile-suggestions', 'maxlength="4"', '{1,4}', 'eindeutig', "script('flz_akut', 'profile-personal')"]) {
    if (!template.includes(expected)) throw new Error(`Profil-Template fehlt: ${expected}`)
}
for (const expected of ['requesttoken', "method: 'PUT'", '/api/profile/kuerzel', 'flz-akut-profile-form', 'flz-akut-profile-kuerzel', 'flz-akut-profile-suggestions', 'data-suggestion', 'suggestions', 'requestSubmit']) {
    if (!script.includes(expected)) throw new Error(`Profil-Speichervertrag fehlt: ${expected}`)
}
for (const expected of ['OCA\\FlzAkut\\Settings\\PersonalProfile', 'OCA\\FlzAkut\\Settings\\PersonalProfileSection', '<personal>', '<personal-section>']) {
    if (!info.includes(expected)) throw new Error(`Personal-Settings-Registrierung fehlt: ${expected}`)
}

console.log('flz_akut profile personal smoke passed')

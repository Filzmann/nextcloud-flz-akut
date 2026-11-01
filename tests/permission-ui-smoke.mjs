import fs from 'node:fs'

const source = fs.readFileSync(new URL('../js/app.js', import.meta.url), 'utf8')

for (const capability of ['create', 'split', 'contact', 'assign', 'reopen', 'confirmHycare', 'confirmPaper']) {
    if (!source.includes(`can('${capability}'`) && !source.includes(`actionButton('${capability}'`)) {
        throw new Error(`UI wertet die Einzelberechtigung ${capability} nicht aus.`)
    }
}
if (!source.includes('state.permissions') || !source.includes('shift.bureau_key')) {
    throw new Error('UI bindet Einzelrechte nicht an den Bürobereich der Schicht.')
}

console.log('flz_akut permission UI smoke passed')

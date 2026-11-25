import fs from 'node:fs'
const js=fs.readFileSync(new URL('../js/app.js',import.meta.url),'utf8')
const template=fs.readFileSync(new URL('../templates/index.php',import.meta.url),'utf8')
for(const contract of ['requesttoken','target="_blank"',"actionButton('split'", "actionButton('assign'", "can('contact'",'SZ – heute bis nächster Büroarbeitstag','showModal','flz-akut-create-dialog','flz-akut-open-create'])if(!js.includes(contract)&&!template.includes(contract))throw new Error(`UI-Vertrag fehlt: ${contract}`)
for(const contract of ['<main','aria-labelledby','role="status"','<table','<dialog','id="flz-akut-open-create"','aria-haspopup="dialog"','type="date"','type="time"','data-orgsuite','data-suite="flz"','data-current-app="flz_akut"'])if(!template.includes(contract))throw new Error(`Accessibility-/Suite-Vertrag fehlt: ${contract}`)
console.log('flz_akut JavaScript smoke passed')

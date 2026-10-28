import fs from 'node:fs'
const js=fs.readFileSync(new URL('../js/app.js',import.meta.url),'utf8')
const template=fs.readFileSync(new URL('../templates/index.php',import.meta.url),'utf8')
for(const contract of ['requesttoken','target="_blank"','data-action="split"','data-action="accept"','data-action="contact"','SZ – heute bis nächster Büroarbeitstag'])if(!js.includes(contract)&&!template.includes(contract))throw new Error(`UI-Vertrag fehlt: ${contract}`)
for(const contract of ['<main','aria-labelledby','role="status"','<table','type="datetime-local"'])if(!template.includes(contract))throw new Error(`Accessibility-Vertrag fehlt: ${contract}`)
console.log('flz_akut JavaScript smoke passed')

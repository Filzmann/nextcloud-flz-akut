import {spawnSync} from 'node:child_process'
import fs from 'node:fs'
const files=fs.readdirSync(new URL('../js/',import.meta.url)).filter(file=>file.endsWith('.js'))
for(const file of files){const check=spawnSync(process.execPath,['--check',new URL('../js/'+file,import.meta.url).pathname],{stdio:'inherit'});if(check.status!==0)process.exit(check.status??1)}
await import('./js-smoke.mjs')
await import('./admin-settings-smoke.mjs')
await import('./profile-personal-smoke.mjs')
await import('./permission-ui-smoke.mjs')
await import('./runtime-behavior.mjs')
console.log('flz_akut JavaScript tests passed')

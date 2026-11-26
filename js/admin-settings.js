document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('flz-akut-permission-settings')
    const status = document.getElementById('flz-akut-permission-settings-status')
    if (!form) return

    const capabilities = ['read', 'create', 'split', 'contact', 'assign', 'reopen', 'confirmHycare', 'confirmPaper']
    form.addEventListener('submit', async event => {
        event.preventDefault()
        const data = new FormData(form)
        const permissions = {}
        for (const capability of capabilities) permissions[capability] = data.getAll(`${capability}[]`).map(String)
        if (status) status.textContent = 'Berechtigungen werden gespeichert …'
        try {
            const response = await fetch(OC.generateUrl('/apps/flz_akut/api/admin/permissions'), {
                method: 'PUT',
                headers: {'Content-Type': 'application/json', requesttoken: OC.requestToken},
                body: JSON.stringify({permissions}),
            })
            const result = await response.json()
            if (!response.ok) throw new Error(result.error || 'Die Akutberechtigungen konnten nicht gespeichert werden.')
            if (status) status.textContent = 'Die Akutberechtigungen wurden gespeichert.'
        } catch (error) {
            if (status) status.textContent = error instanceof Error ? error.message : 'Die Akutberechtigungen konnten nicht gespeichert werden.'
        }
    })

    const officeDayForm = document.getElementById('flz-akut-office-day-form')
    const officeDayStatus = document.getElementById('flz-akut-office-day-status')
    if (officeDayForm) officeDayForm.addEventListener('submit', async event => {
        event.preventDefault()
        const data = new FormData(officeDayForm)
        if (officeDayStatus) officeDayStatus.textContent = 'Der Bürotagbeginn wird gespeichert …'
        try {
            const response = await fetch(OC.generateUrl('/apps/flz_akut/api/admin/office-day-start'), {
                method: 'PUT',
                headers: {'Content-Type': 'application/json', requesttoken: OC.requestToken},
                body: JSON.stringify({officeDayStart: String(data.get('officeDayStart') || '')}),
            })
            const result = await response.json()
            if (!response.ok || result.ok === false) throw new Error(result.error || 'Der Bürotagbeginn konnte nicht gespeichert werden.')
            if (officeDayStatus) officeDayStatus.textContent = 'Der Bürotagbeginn wurde gespeichert.'
        } catch (error) {
            if (officeDayStatus) officeDayStatus.textContent = error instanceof Error ? error.message : 'Der Bürotagbeginn konnte nicht gespeichert werden.'
        }
    })

    const templateForm = document.getElementById('flz-akut-mail-template-form')
    const templateStatus = document.getElementById('flz-akut-mail-template-status')
    if (templateForm) templateForm.addEventListener('submit', async event => {
        event.preventDefault()
        const data = new FormData(templateForm)
        if (templateStatus) templateStatus.textContent = 'Die Mail-Vorlage wird gespeichert …'
        try {
            const response = await fetch(OC.generateUrl('/apps/flz_akut/api/admin/mail-template'), {
                method: 'PUT',
                headers: {'Content-Type': 'application/json', requesttoken: OC.requestToken},
                body: JSON.stringify({template: String(data.get('template') || '')}),
            })
            const result = await response.json()
            if (!response.ok || result.ok === false) throw new Error(result.error || 'Die Mail-Vorlage konnte nicht gespeichert werden.')
            if (templateStatus) templateStatus.textContent = 'Die Mail-Vorlage wurde gespeichert.'
        } catch (error) {
            if (templateStatus) templateStatus.textContent = error instanceof Error ? error.message : 'Die Mail-Vorlage konnte nicht gespeichert werden.'
        }
    })
})

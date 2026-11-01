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
})

(function () {
    'use strict'
    const translate = typeof window.t === 'function' ? window.t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)

    const form = document.getElementById('flz-akut-profile-form')
    const status = document.getElementById('flz-akut-profile-status')
    const input = document.getElementById('flz-akut-profile-kuerzel')
    const suggestionsBox = document.getElementById('flz-akut-profile-suggestions')
    if (!form || !input) return

    const clearSuggestions = () => { if (suggestionsBox) suggestionsBox.innerHTML = '' }

    if (suggestionsBox) suggestionsBox.addEventListener('click', event => {
        const button = event.target && event.target.closest ? event.target.closest('[data-suggestion]') : null
        if (!button) return
        input.value = String(button.dataset.suggestion || '')
        clearSuggestions()
        if (typeof form.requestSubmit === 'function') form.requestSubmit()
    })

    form.addEventListener('submit', async event => {
        event.preventDefault()
        clearSuggestions()
        try {
            const response = await fetch(OC.generateUrl('/apps/flz_akut/api/profile/kuerzel'), {
                method: 'PUT',
                headers: {'Content-Type': 'application/json', requesttoken: OC.requestToken},
                body: JSON.stringify({kuerzel: input.value.trim()})
            })
            const data = await response.json()
            // Kollision: freie Vorschläge als anklickbare Buttons anbieten
            if (Array.isArray(data.suggestions) && data.suggestions.length && suggestionsBox) {
                status.textContent = data.error || translate('flz_akut', 'Kürzel bereits vergeben.')
                suggestionsBox.innerHTML = data.suggestions.map(s => `<button type="button" class="flz-akut-suggestion" data-suggestion="${s}">${s}</button>`).join(' ')
                return
            }
            if (!response.ok || data.ok === false) throw new Error(data.error || translate('flz_akut', 'Speichern fehlgeschlagen.'))
            status.textContent = translate('flz_akut', 'Kürzel gespeichert.')
        } catch (error) {
            status.textContent = error.message
        }
    })
})()

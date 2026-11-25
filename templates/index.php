<?php
script('flz_akut','app');
style('flz_akut','app');
?>
<main id="flz-akut-app" aria-labelledby="flz-akut-title">
  <div class="orgsuite-host" data-orgsuite data-suite="flz" data-current-app="flz_akut"></div>
  <header><h1 id="flz-akut-title">Akutschichtvermittlung</h1><button type="button" id="flz-akut-open-create" aria-haspopup="dialog">+ Neue Lücke</button><p id="flz-akut-status" role="status">Daten werden geladen …</p></header>
  <dialog id="flz-akut-create-dialog" aria-labelledby="new-shift-title">
    <form id="flz-akut-create">
      <h2 id="new-shift-title">Offene Schicht aufnehmen</h2>
      <label>ASN-Team <select name="teamCode" required></select></label>
      <label>Datum <input name="date" type="date" required></label>
      <label>Von <input name="startTime" type="time" required></label>
      <label>Bis <input name="endTime" type="time" required></label>
      <p class="hint">Liegt „Bis“ vor „Von“, handelt es sich um eine Nachtschicht, die am nächsten Tag endet.</p>
      <label>Kommentar <textarea name="comment" rows="2"></textarea></label>
      <button type="submit">Schicht aufnehmen</button>
      <button type="button" id="flz-akut-close-create">Schließen</button>
    </form>
  </dialog>
  <section aria-labelledby="open-shifts-title"><h2 id="open-shifts-title">Offene und vermittelte Schichten</h2><div class="table-scroll"><table><thead><tr><th>ASN</th><th>Datum / Uhrzeit</th><th>Assistenz</th><th>Status</th><th>Dokumentation</th><th>Suchbogen</th></tr></thead><tbody id="flz-akut-shifts"></tbody></table></div></section>
  <section id="flz-akut-search-sheet" hidden aria-labelledby="search-sheet-title"><h2 id="search-sheet-title">Suchbogen</h2><p>Kontaktversuche werden mehrzeilig je Assistenz und Schicht geführt.</p><div class="table-scroll"><table><thead id="flz-akut-search-head"></thead><tbody id="flz-akut-search-body"></tbody></table></div></section>
</main>

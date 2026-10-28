<?php
script('flz_akut','app');
style('flz_akut','app');
?>
<main id="flz-akut-app" aria-labelledby="flz-akut-title">
  <header><h1 id="flz-akut-title">Akutschichtvermittlung</h1><p id="flz-akut-status" role="status">Daten werden geladen …</p></header>
  <section aria-labelledby="new-shift-title"><h2 id="new-shift-title">Offene Schicht aufnehmen</h2>
    <form id="flz-akut-create">
      <label>ASN-Team <select name="teamCode" required></select></label>
      <label>Beginn <input name="startsAt" type="datetime-local" required></label>
      <label>Ende <input name="endsAt" type="datetime-local" required></label>
      <label>Kommentar <textarea name="comment" rows="2"></textarea></label>
      <button type="submit">Schicht aufnehmen</button>
    </form>
  </section>
  <section aria-labelledby="open-shifts-title"><h2 id="open-shifts-title">Offene und vermittelte Schichten</h2><div class="table-scroll"><table><thead><tr><th>ASN</th><th>Datum / Uhrzeit</th><th>Assistenz</th><th>Status</th><th>Dokumentation</th><th>Suchbogen</th></tr></thead><tbody id="flz-akut-shifts"></tbody></table></div></section>
  <section id="flz-akut-search-sheet" hidden aria-labelledby="search-sheet-title"><h2 id="search-sheet-title">Suchbogen</h2><p>Kontaktversuche werden mehrzeilig je Assistenz und Schicht geführt.</p><div class="table-scroll"><table><thead id="flz-akut-search-head"></thead><tbody id="flz-akut-search-body"></tbody></table></div></section>
</main>

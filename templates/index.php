<?php
script('flz_akut','app');
style('flz_akut','app');
?>
<main id="flz-akut-app" aria-labelledby="flz-akut-title">
  <div class="orgsuite-host" data-orgsuite data-suite="flz" data-current-app="flz_akut"></div>
  <header><h1 id="flz-akut-title">Akutschichtvermittlung</h1><button type="button" id="flz-akut-open-create" aria-haspopup="dialog">+ Neue Lücke</button><p id="flz-akut-status" role="status">Daten werden geladen …</p></header>
  <dialog id="flz-akut-create-dialog" aria-labelledby="new-shift-title">
    <form id="flz-akut-create">
      <h2 id="new-shift-title">Offene Schichten aufnehmen</h2>
      <div class="table-scroll"><table id="flz-akut-create-table"><thead><tr><th>ASN-Team</th><th>Datum</th><th>Von</th><th>Bis</th><th>Kommentar</th><th><span class="hidden-visually">Zeile entfernen</span></th></tr></thead><tbody id="flz-akut-create-rows"></tbody></table></div>
      <div class="button-row">
        <button type="button" id="flz-akut-add-row">+ Zeile hinzufügen</button>
        <button type="submit">Schichten aufnehmen</button>
        <button type="button" id="flz-akut-close-create">Schließen</button>
      </div>
    </form>
  </dialog>
  <dialog id="flz-akut-action-dialog" aria-labelledby="flz-akut-action-title">
    <form id="flz-akut-action-form">
      <h2 id="flz-akut-action-title"></h2>
      <div id="flz-akut-action-fields"></div>
      <button type="submit">Speichern</button>
      <button type="button" id="flz-akut-action-close">Abbrechen</button>
    </form>
  </dialog>
  <section aria-labelledby="open-shifts-title"><h2 id="open-shifts-title">Offene und vermittelte Schichten</h2><div class="table-scroll"><table><thead><tr><th>Datum</th><th>Von</th><th>Bis</th><th>AS / Aktionen</th></tr></thead><tbody id="flz-akut-shifts"></tbody></table></div></section>
</main>

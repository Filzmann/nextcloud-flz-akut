<?php
script('flz_akut','app');
style('flz_akut','app');
?>
<main id="flz-akut-app" aria-labelledby="flz-akut-title">
  <div class="orgsuite-host" data-orgsuite data-suite="flz" data-current-app="flz_akut"></div>
  <header><h1 id="flz-akut-title">Suchbogen</h1><p id="flz-akut-status" role="status">Daten werden geladen …</p></header>
  <section id="flz-akut-search-sheet" aria-labelledby="search-sheet-title"><h2 id="search-sheet-title">Suchbogen</h2><p>Kontaktversuche werden mehrzeilig je Assistenz und Schicht geführt.</p><div class="table-scroll"><table><thead id="flz-akut-search-head"></thead><tbody id="flz-akut-search-body"></tbody></table></div></section>
  <dialog id="flz-akut-action-dialog" aria-labelledby="flz-akut-action-title">
    <form id="flz-akut-action-form">
      <h2 id="flz-akut-action-title"></h2>
      <div id="flz-akut-action-fields"></div>
      <button type="submit">Speichern</button>
      <button type="button" id="flz-akut-action-close">Abbrechen</button>
    </form>
  </dialog>
</main>

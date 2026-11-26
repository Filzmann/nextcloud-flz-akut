<?php
script('flz_akut','app');
style('flz_akut','app');
?>
<main id="flz-akut-app" aria-labelledby="flz-akut-title">
  <div class="orgsuite-host" data-orgsuite data-suite="flz" data-current-app="flz_akut"></div>
  <header><h1 id="flz-akut-title">Suchbogen</h1><p id="flz-akut-status" role="status">Daten werden geladen …</p></header>
  <section id="flz-akut-search-sheet" aria-labelledby="search-sheet-title"><h2 id="search-sheet-title">Suchbogen</h2><p id="flz-akut-search-summary" class="search-summary" aria-label="Alle offenen Schichten des Teams"></p><div class="flz-akut-manual-add"><label for="flz-akut-manual-input">Weitere AS-Zeile (manuell)</label><input type="text" id="flz-akut-manual-input" maxlength="64" placeholder="UID oder Name"><button type="button" id="flz-akut-manual-add">+ Zeile hinzufügen</button></div><p>Nur Assistentinnen des Teams werden geführt; Ja/Nein wird je offener Schicht gebucht, Tel/SMS gelten als grundsätzliches Erreichen für alle offenen Schichten. Das Info-Icon je AS zeigt per Hover die hinterlegte AS-Info (nur Tag / nur Nacht / nur Früh + Freitext), der Stift bearbeitet sie; das Kommentarfeld speichert interne Notizen direkt beim AS-Nutzer.</p><div class="table-scroll"><table><thead id="flz-akut-search-head"></thead><tbody id="flz-akut-search-body"></tbody></table></div></section>
  <dialog id="flz-akut-sms-dialog" aria-labelledby="flz-akut-sms-title">
    <h2 id="flz-akut-sms-title">SMS vorformulieren</h2>
    <p>Der Text wird zusätzlich als SMS-Kontaktversuch gebucht und liegt zum Rauskopieren bereit.</p>
    <label for="flz-akut-sms-text">SMS-Text</label>
    <textarea id="flz-akut-sms-text" rows="8" readonly></textarea>
    <div class="button-row">
      <button type="button" id="flz-akut-sms-copy">In Zwischenablage kopieren</button>
      <button type="button" id="flz-akut-sms-close">Schließen</button>
    </div>
  </dialog>
  <dialog id="flz-akut-asinfo-dialog" aria-labelledby="flz-akut-asinfo-title">
    <form id="flz-akut-asinfo-form">
      <h2 id="flz-akut-asinfo-title">AS-Info</h2>
      <fieldset>
        <legend>Verfügbarkeit</legend>
        <label><input type="checkbox" name="asinfo-tag" value="tag"> nur Tag</label>
        <label><input type="checkbox" name="asinfo-tag" value="nacht"> nur Nacht</label>
        <label><input type="checkbox" name="asinfo-tag" value="frueh"> nur Früh</label>
      </fieldset>
      <label for="flz-akut-asinfo-note">Freitext</label>
      <textarea id="flz-akut-asinfo-note" rows="4" maxlength="1000"></textarea>
      <div class="button-row">
        <button type="submit">Speichern</button>
        <button type="button" id="flz-akut-asinfo-close">Abbrechen</button>
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
</main>

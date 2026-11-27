<?php
script('flz_akut','app');
style('flz_akut','app');
?>
<main id="flz-akut-app" aria-labelledby="flz-akut-title">
  <div class="orgsuite-host" data-orgsuite data-suite="flz" data-current-app="flz_akut"></div>
  <header><h1 id="flz-akut-title"><?php p($l->t('Suchbogen')); ?></h1><p id="flz-akut-status" role="status"><?php p($l->t('Daten werden geladen …')); ?></p></header>
  <section id="flz-akut-search-sheet" aria-labelledby="search-sheet-title"><h2 id="search-sheet-title"><?php p($l->t('Suchbogen')); ?></h2><p id="flz-akut-search-summary" class="search-summary" aria-label="<?php p($l->t('Alle offenen Schichten des Teams')); ?>"></p><div class="flz-akut-manual-add"><label for="flz-akut-manual-input"><?php p($l->t('Weitere AS-Zeile (manuell)')); ?></label><input type="text" id="flz-akut-manual-input" maxlength="64" placeholder="<?php p($l->t('UID oder Name')); ?>"><button type="button" id="flz-akut-manual-add"><?php p($l->t('+ Zeile hinzufügen')); ?></button></div><p><?php p($l->t('Nur Assistentinnen des Teams werden geführt; Ja/Nein wird je offener Schicht gebucht, Tel/SMS gelten als grundsätzliches Erreichen für alle offenen Schichten. Das Info-Icon je AS öffnet per Klick das Overlay (zunächst schreibgeschützt, Hover zeigt die Info: nur Tag / nur Nacht / nur Früh / nur Spät + Freitext); der Stift-Button im Overlay schaltet die Bearbeitung frei; das Kommentarfeld speichert interne Notizen direkt beim AS-Nutzer.')); ?></p><div class="table-scroll"><table><thead id="flz-akut-search-head"></thead><tbody id="flz-akut-search-body"></tbody></table></div></section>
  <dialog id="flz-akut-sms-dialog" aria-labelledby="flz-akut-sms-title">
    <h2 id="flz-akut-sms-title"><?php p($l->t('SMS vorformulieren')); ?></h2>
    <p><?php p($l->t('Der Text wird zusätzlich als SMS-Kontaktversuch gebucht und liegt zum Rauskopieren bereit.')); ?></p>
    <label for="flz-akut-sms-text"><?php p($l->t('SMS-Text')); ?></label>
    <textarea id="flz-akut-sms-text" rows="8" readonly></textarea>
    <div class="button-row">
      <button type="button" id="flz-akut-sms-copy"><?php p($l->t('In Zwischenablage kopieren')); ?></button>
      <button type="button" id="flz-akut-sms-close"><?php p($l->t('Schließen')); ?></button>
    </div>
  </dialog>
  <dialog id="flz-akut-asinfo-dialog" aria-labelledby="flz-akut-asinfo-title">
    <form id="flz-akut-asinfo-form">
      <h2 id="flz-akut-asinfo-title"><?php p($l->t('AS-Info')); ?></h2>
      <fieldset>
        <legend><?php p($l->t('Verfügbarkeit')); ?></legend>
        <label><input type="checkbox" name="asinfo-tag" value="tag"><?php p($l->t(' nur Tag')); ?></label>
        <label><input type="checkbox" name="asinfo-tag" value="nacht"><?php p($l->t(' nur Nacht')); ?></label>
        <label><input type="checkbox" name="asinfo-tag" value="frueh"><?php p($l->t(' nur Früh')); ?></label>
        <label><input type="checkbox" name="asinfo-tag" value="spaet"><?php p($l->t(' nur Spät')); ?></label>
      </fieldset>
      <label for="flz-akut-asinfo-note"><?php p($l->t('Freitext')); ?></label>
      <textarea id="flz-akut-asinfo-note" rows="4" maxlength="1000"></textarea>
      <div class="button-row">
        <button type="button" id="flz-akut-asinfo-edit" title="<?php p($l->t('Bearbeiten freischalten')); ?>" aria-label="<?php p($l->t('AS-Info bearbeiten freischalten')); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" width="16" height="16"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button>
        <button type="submit" id="flz-akut-asinfo-save" disabled><?php p($l->t('Speichern')); ?></button>
        <button type="button" id="flz-akut-asinfo-close"><?php p($l->t('Abbrechen')); ?></button>
      </div>
    </form>
  </dialog>
  <dialog id="flz-akut-action-dialog" aria-labelledby="flz-akut-action-title">
    <form id="flz-akut-action-form">
      <h2 id="flz-akut-action-title"></h2>
      <div id="flz-akut-action-fields"></div>
      <button type="submit"><?php p($l->t('Speichern')); ?></button>
      <button type="button" id="flz-akut-action-close"><?php p($l->t('Abbrechen')); ?></button>
    </form>
  </dialog>
</main>

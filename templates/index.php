<?php
script('flz_akut','app');
style('flz_akut','app');
?>
<main id="flz-akut-app" aria-labelledby="flz-akut-title">
  <div class="orgsuite-host" data-orgsuite data-suite="flz" data-current-app="flz_akut"></div>
  <header><h1 id="flz-akut-title"><?php p($l->t('Akutschichtvermittlung')); ?></h1><button type="button" id="flz-akut-open-create" aria-haspopup="dialog"><?php p($l->t('+ Neue Lücke')); ?></button><p id="flz-akut-status" role="status"><?php p($l->t('Daten werden geladen …')); ?></p></header>
  <dialog id="flz-akut-create-dialog" aria-labelledby="new-shift-title">
    <form id="flz-akut-create">
      <h2 id="new-shift-title"><?php p($l->t('Offene Schichten aufnehmen')); ?></h2>
      <div class="table-scroll"><table id="flz-akut-create-table"><thead><tr><th><?php p($l->t('ASN-Team')); ?></th><th><?php p($l->t('Datum')); ?></th><th><?php p($l->t('Von')); ?></th><th><?php p($l->t('Bis')); ?></th><th><?php p($l->t('Kommentar')); ?></th><th><span class="hidden-visually"><?php p($l->t('Zeile entfernen')); ?></span></th></tr></thead><tbody id="flz-akut-create-rows"></tbody></table></div>
      <div class="button-row">
        <button type="button" id="flz-akut-add-row"><?php p($l->t('+ Zeile hinzufügen')); ?></button>
        <button type="submit"><?php p($l->t('Schichten aufnehmen')); ?></button>
        <button type="button" id="flz-akut-close-create"><?php p($l->t('Schließen')); ?></button>
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
  <dialog id="flz-akut-mail-dialog" aria-labelledby="flz-akut-mail-title">
    <form id="flz-akut-mail-form">
      <h2 id="flz-akut-mail-title"><?php p($l->t('Mail an Team und Vertretung')); ?></h2>
      <p id="flz-akut-mail-recipients"></p>
      <label for="flz-akut-mail-text"><?php p($l->t('Mail-Text (zum Rauskopieren)')); ?></label>
      <textarea id="flz-akut-mail-text" readonly rows="14"></textarea>
      <div class="button-row">
        <button type="button" id="flz-akut-mail-sent"><?php p($l->t('Mail versendet')); ?></button>
        <button type="button" id="flz-akut-mail-copy"><?php p($l->t('In Zwischenablage kopieren')); ?></button>
        <button type="button" id="flz-akut-mail-close"><?php p($l->t('Schließen')); ?></button>
      </div>
    </form>
  </dialog>
  <section aria-labelledby="open-shifts-title"><h2 id="open-shifts-title"><?php p($l->t('Offene und vermittelte Schichten')); ?></h2><div class="table-scroll"><table><thead><tr><th><?php p($l->t('Datum')); ?></th><th><?php p($l->t('Von')); ?></th><th><?php p($l->t('Bis')); ?></th><th><?php p($l->t('AS')); ?></th><th><?php p($l->t('Vermittelt')); ?></th><th><?php p($l->t('Kürzel')); ?></th><th><?php p($l->t('HyCare')); ?></th><th><?php p($l->t('Akte')); ?></th><th><?php p($l->t('Kommentar')); ?></th></tr></thead><tbody id="flz-akut-shifts"></tbody></table></div></section>
</main>

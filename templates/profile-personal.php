<?php
script('flz_akut', 'profile-personal');
?>
<section class="section" id="flz-akut-profile" aria-labelledby="flz-akut-profile-heading">
    <h2 id="flz-akut-profile-heading"><?php p($l->t('Akutdienst — eigenes Profil')); ?></h2>
    <p><?php p($l->t('Das persönliche Kürzel wird beim MTV-Vermerk („Mail versendet“) in Schichtkommentaren verwendet. Es muss eineindeutig sein (Groß-/Kleinschreibung wird nicht unterschieden), darf höchstens 4 Zeichen haben (Buchstaben und Punkt). Vorbelegung: erste zwei Buchstaben des Nachnamens, sonst Initialen (1. Buchstabe Vorname + 1. Buchstabe Nachname). Ist das Wunschkürzel bereits vergeben, werden freie Vorschläge angeboten.')); ?></p>
    <form id="flz-akut-profile-form">
        <label for="flz-akut-profile-kuerzel"><?php p($l->t('Mein Kürzel')); ?></label>
        <input type="text" id="flz-akut-profile-kuerzel" name="kuerzel" value="<?php p($_['kuerzel'] ?? ''); ?>" maxlength="4" pattern="[A-Za-zÄÖÜäöüß.]{1,4}" size="4" title="<?php p($l->t('Kürzel: 1–4 Zeichen (Buchstaben und Punkt)')); ?>" aria-describedby="flz-akut-profile-status">
        <button type="submit"><?php p($l->t('Kürzel speichern')); ?></button>
    </form>
    <p id="flz-akut-profile-status" role="status"></p>
    <div id="flz-akut-profile-suggestions" class="flz-akut-suggestions" aria-label="<?php p($l->t('Kürzel-Vorschläge')); ?>"></div>
</section>

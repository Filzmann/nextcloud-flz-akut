<?php

declare(strict_types=1);

use OCA\FlzAkut\AppInfo\Application;
use OCA\FlzAkut\Service\AkutCapability;

script(Application::APP_ID, 'admin-settings');
style(Application::APP_ID, 'app');

$settings = is_array($_['permissionSettings'] ?? null) ? $_['permissionSettings'] : [];
$selection = is_array($_['roleSelection'] ?? null) ? $_['roleSelection'] : [];
$options = is_array($selection['options'] ?? null) ? $selection['options'] : [];
$mailTemplate = is_string($_['mailTemplate'] ?? null) ? $_['mailTemplate'] : '';
$officeDayStart = is_string($_['officeDayStart'] ?? null) ? $_['officeDayStart'] : '06:00';
?>
<section id="flz-akut-admin" class="section" aria-labelledby="flz-akut-admin-heading">
    <h2 id="flz-akut-admin-heading"><?php p($l->t('Akutschichtvermittlung')); ?></h2>
    <p><?php p($l->t('Ordne jede fachliche Aktion getrennt den passenden Organigrammrollen zu. Die aktuellen Nextcloud-Gruppen und Bürobereiche werden aus dem LocalBase-Organigramm ermittelt und nicht als zweite Rollenquelle gespeichert.')); ?></p>
    <p><strong><?php p($l->t('Standard:')); ?></strong><?php p($l->t(' Büroorganisation, Einsatzbegleitung, Büroleitung und Stellvertretende Büroleitung erhalten alle acht Rechte jeweils nur in ihrem Bürobereich.')); ?></p>

    <?php if (($selection['message'] ?? '') !== ''): ?>
        <p role="alert"><?php p((string)$selection['message']); ?></p>
    <?php endif; ?>

    <form id="flz-akut-permission-settings">
        <?php foreach (AkutCapability::DEFINITIONS as $capability => [$permissionId, $label, $description]): ?>
            <?php $selectedRoles = is_array($settings[$capability] ?? null) ? $settings[$capability] : []; ?>
            <fieldset>
                <legend><?php p($label); ?></legend>
                <p><?php p($description); ?></p>
                <?php foreach ($options as $option): ?>
                    <?php $roleKey = (string)($option['roleKey'] ?? ''); $exists = (bool)($option['exists'] ?? false); ?>
                    <label>
                        <input type="checkbox" name="<?php p((string)$capability); ?>[]" value="<?php p($roleKey); ?>" <?php if (in_array($roleKey, $selectedRoles, true)): ?>checked<?php endif; ?> <?php if (!$exists): ?>disabled<?php endif; ?>>
                        <?php p((string)($option['label'] ?? $roleKey)); ?>
                        <small><?php p((string)($option['groupId'] ?? '')); ?><?php if (!$exists): ?> – Gruppe fehlt; diese Rechtezuordnung bleibt deaktiviert<?php endif; ?></small>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>
        <button type="submit" class="primary" <?php if (!($selection['available'] ?? false)): ?>disabled<?php endif; ?>><?php p($l->t('Berechtigungen speichern')); ?></button>
        <p id="flz-akut-permission-settings-status" role="status" aria-live="polite"></p>
    </form>

    <form id="flz-akut-mail-template-form">
        <h3><?php p($l->t('Mail-Vorlage für offene Schichten')); ?></h3>
        <p><?php p($l->t('Die Vorlage wird beim Mail-Button je ASN mit den offenen Schichten des Teams vorbefüllt. Platzhalter: ')); ?><code>{team}</code>, <code>{recipients}</code>, <code>{shifts}</code>, <code>{date}</code>, <code>{sender}</code><?php p($l->t(' (Vorname der eingeloggten Person). Leer lassen setzt die Standardvorlage.')); ?></p>
        <label for="flz-akut-mail-template"><?php p($l->t('Vorlagentext')); ?></label>
        <textarea id="flz-akut-mail-template" name="template" rows="10" maxlength="4000"><?php p($mailTemplate); ?></textarea>
        <button type="submit" class="primary"><?php p($l->t('Mail-Vorlage speichern')); ?></button>
        <p id="flz-akut-mail-template-status" role="status" aria-live="polite"></p>
    </form>

    <form id="flz-akut-office-day-form">
        <h3><?php p($l->t('Bürotagbeginn')); ?></h3>
        <p><?php p($l->t('Der Bürotag beginnt um diese Uhrzeit (Standard 06:00 Uhr in der Instanz-Zeitzone aus den Nextcloud-Einstellungen). Schichten, die vor diesem Zeitpunkt enden, werden in Übersicht, Suchbogen und Mail nicht mehr angezeigt; Schichten des aktuellen Bürotags bleiben bis zum nächsten Bürotagbeginn sichtbar. Leer lassen setzt den Standard 06:00 Uhr.')); ?></p>
        <label for="flz-akut-office-day-start"><?php p($l->t('Bürotagbeginn (HH:MM)')); ?></label>
        <input type="time" id="flz-akut-office-day-start" name="officeDayStart" value="<?php p($officeDayStart); ?>" min="00:00" max="23:59" required>
        <button type="submit" class="primary"><?php p($l->t('Bürotagbeginn speichern')); ?></button>
        <p id="flz-akut-office-day-status" role="status" aria-live="polite"></p>
    </form>
</section>

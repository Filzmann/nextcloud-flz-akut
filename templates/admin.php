<?php

declare(strict_types=1);

use OCA\FlzAkut\AppInfo\Application;
use OCA\FlzAkut\Service\AkutCapability;

script(Application::APP_ID, 'admin-settings');
style(Application::APP_ID, 'app');

$settings = is_array($_['permissionSettings'] ?? null) ? $_['permissionSettings'] : [];
$selection = is_array($_['roleSelection'] ?? null) ? $_['roleSelection'] : [];
$options = is_array($selection['options'] ?? null) ? $selection['options'] : [];
?>
<section id="flz-akut-admin" class="section" aria-labelledby="flz-akut-admin-heading">
    <h2 id="flz-akut-admin-heading">Akutschichtvermittlung</h2>
    <p>Ordne jede fachliche Aktion getrennt den passenden Organigrammrollen zu. Die aktuellen Nextcloud-Gruppen und Bürobereiche werden aus dem LocalBase-Organigramm ermittelt und nicht als zweite Rollenquelle gespeichert.</p>
    <p><strong>Standard:</strong> Büroorganisation, Einsatzbegleitung, Büroleitung und Stellvertretende Büroleitung erhalten alle acht Rechte jeweils nur in ihrem Bürobereich.</p>

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
        <button type="submit" class="primary" <?php if (!($selection['available'] ?? false)): ?>disabled<?php endif; ?>>Berechtigungen speichern</button>
        <p id="flz-akut-permission-settings-status" role="status" aria-live="polite"></p>
    </form>
</section>

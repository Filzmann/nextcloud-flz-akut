# Akutschichtvermittlung

Nextcloud-App `flz_akut` zur bürobezogenen Vermittlung kurzfristig offener Assistenzschichten.

Der aktuelle Entwicklungsstand umfasst manuelle Anlage mit veränderlichen FlzPlaner-Schichtvorschlägen, beliebige lückenfreie Teilung, genau eine vermittelte Assistenz je Teil, mehrere Telefon-/SMS-Kontaktversuche, Wiederöffnung, HyCare-/Papieraktenbestätigung, SZ-/Wochenhinweise und gemeinsame automatische Löschung nach konfigurierbarer Frist.

Lesen, Anlegen, Teilen, Kontaktdokumentation, Vermittlung, Wiederöffnung sowie HyCare- und Papieraktenbestätigung sind acht getrennte Berechtigungen. Standardmäßig gelten sie für Büroorganisation, Einsatzbegleitung, Büroleitung und Stellvertretende Büroleitung, jeweils nur zusammen mit dem aktuellen Bürobereich. Native Nextcloud-Admins können diese Zuordnung im Adminbereich der App rollenbasiert anpassen; freie Gruppennamen werden nicht gespeichert.

Laufzeitvoraussetzungen sind die getrennten Kategorie-B-Apps LocalBase für Organisation und Feiertage sowie FlzPlaner für ASN-Team, Einzelbüro, Team-AS und Schichtvorlagen. Fehlende oder inkompatible Verträge führen zu einem sichtbaren, nicht berechtigenden Zustand.

## Entwicklung

```bash
php tests/run.php
node tests/run-js.mjs
```

## Dokumentation

- [Architektur](docs/architecture.md)
- [Manuelle Abnahme](docs/manual-acceptance.md)
- [Offene Arbeit](ROADMAP.md)
- [Änderungen](CHANGELOG.md)

# Akutschichtvermittlung

Nextcloud-App `flz_akut` zur bürobezogenen Vermittlung kurzfristig offener Assistenzschichten.

Der aktuelle Entwicklungsstand umfasst manuelle Anlage mit veränderlichen FlzPlaner-Schichtvorschlägen, beliebige lückenfreie Teilung, genau eine vermittelte Assistenz je Teil, mehrere Telefon-/SMS-Kontaktversuche, Wiederöffnung, HyCare-/Papieraktenbestätigung, SZ-/Wochenhinweise und gemeinsame automatische Löschung nach konfigurierbarer Frist.

Lesen, Anlegen, Teilen, Kontaktdokumentation, Vermittlung, Wiederöffnung sowie HyCare- und Papieraktenbestätigung sind acht getrennte Berechtigungen. Standardmäßig gelten sie für Büroorganisation, Einsatzbegleitung, Büroleitung und Stellvertretende Büroleitung, jeweils nur zusammen mit dem aktuellen Bürobereich. Native Nextcloud-Admins können diese Zuordnung im Adminbereich der App rollenbasiert anpassen; freie Gruppennamen werden nicht gespeichert.

Laufzeitvoraussetzungen sind die getrennten Kategorie-B-Apps flz_organization für Organisation und Feiertage sowie FlzPlaner für ASN-Team, Einzelbüro, Team-AS und Schichtvorlagen. Fehlende, deaktivierte, inkompatible oder fehlerhafte Verträge führen zu einem sichtbaren, nicht berechtigenden Zustand. Für Organisation und Feiertage gibt es keinen LocalBase-Fallback; veraltete Organisationswerte können dadurch keine Rechte oder Dringlichkeit begründen.

Der noch nicht migrierte gemeinsame Navigationskatalog liegt weiterhin in LocalBase; dies ist eine getrennte Infrastrukturabhängigkeit und keine Quelle für Organisations- oder Feiertagsdaten. LocalBase führt die App als navigierbares Entwicklungsprodukt. Bei aktiver
OrgSuite erscheint sie im gemeinsamen FLZ-Menü; ohne OrgSuite bleibt der
eigenständige Einstieg verfügbar. Die Navigation erteilt keine Fachrechte.

## Entwicklung

```bash
php tests/run.php
node tests/run-js.mjs
```

Opt-in authentifizierter Browser-Smoke gegen die laufende DDEV-Umgebung (braucht
laufendes DDEV und google-chrome auf dem Host, legt einen synthetischen
Nachtschicht-Testdatensatz an):

```bash
tests/browser-ddev-smoke.sh
```

## Dokumentation

- [Architektur](docs/architecture.md)
- [Manuelle Abnahme](docs/manual-acceptance.md)
- [Offene Arbeit](ROADMAP.md)
- [Änderungen](CHANGELOG.md)

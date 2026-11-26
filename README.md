# Akutschichtvermittlung

Nextcloud-App `flz_akut` zur bürobezogenen Vermittlung kurzfristig offener Assistenzschichten.

Der aktuelle Entwicklungsstand umfasst manuelle Anlage mit veränderlichen FlzPlaner-Schichtvorschlägen, beliebige lückenfreie Teilung, genau eine vermittelte Assistenz je Teil (inline per AS-Suchfeld mit Team-/Vertretungsvorschlägen und Freitext wie „abgesagt“/„privat“, Sperre nach HyCare- und Akteneintrag), Kürzel- und Kommentarfelder je Schichtzeile mit Speicherung beim Verlassen, mehrere Telefon-/SMS-Kontaktversuche, Wiederöffnung, HyCare-/Papieraktenbestätigung, ein Mail-Overlay je ASN mit adminseitig bearbeitbarer Vorlage, SZ-/Wochenhinweise, Live-Aktualisierung per Polling von `GET /api/state` alle 10 s (pausiert bei verstecktem Tab, offenem Dialog oder Feld-Fokus) und gemeinsame automatische Löschung nach konfigurierbarer Frist.

Lesen, Anlegen, Teilen, Kontaktdokumentation, Vermittlung, Wiederöffnung sowie HyCare- und Papieraktenbestätigung sind acht getrennte Berechtigungen. Standardmäßig gelten sie für Büroorganisation, Einsatzbegleitung, Büroleitung und Stellvertretende Büroleitung, jeweils nur zusammen mit dem aktuellen Bürobereich. Native Nextcloud-Admins können diese Zuordnung im Adminbereich der App rollenbasiert anpassen; freie Gruppennamen werden nicht gespeichert.

Laufzeitvoraussetzungen sind die getrennten Kategorie-B-Apps flz_organization für Organisation und Feiertage sowie FlzPlaner für ASN-Team, Einzelbüro, Team-AS und Schichtvorlagen. Fehlende, deaktivierte, inkompatible oder fehlerhafte Verträge führen zu einem sichtbaren, nicht berechtigenden Zustand. Für Organisation und Feiertage gibt es keinen LocalBase-Fallback; veraltete Organisationswerte können dadurch keine Rechte oder Dringlichkeit begründen.

Der noch nicht migrierte gemeinsame Navigationskatalog liegt weiterhin in LocalBase; dies ist eine getrennte Infrastrukturabhängigkeit und keine Quelle für Organisations- oder Feiertagsdaten. LocalBase führt die App als navigierbares Entwicklungsprodukt. Bei aktiver
OrgSuite erscheint sie im gemeinsamen FLZ-Menü; ohne OrgSuite bleibt der
eigenständige Einstieg verfügbar. Die Navigation erteilt keine Fachrechte.

## Kapazitätsmeldungen (flz_kapazitaet)

Ist die optionale App `flz_kapazitaet` installiert, liest flz_akut deren Kapazitätsmeldungen read-only über den öffentlichen V1-Eventvertrag (`CapacityQueryEvent`, fail-closed ohne Provider) und zeigt passende Kapazitäten im Suchbogen als Badge in der jeweiligen Schichtzelle an (• alle Schichten, F früh, S spät, T Tag, N Nacht; Abgleich über Kalendertag in der Instanz-Zeitzone und Tag/Nacht-Regel). Zusätzlich können im Suchbogen manuell weitere AS-Zeilen ergänzt werden.

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

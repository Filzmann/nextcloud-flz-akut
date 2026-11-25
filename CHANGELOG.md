# Changelog

## Unreleased

- Opt-in DDEV-Browser-Smoke (`tests/browser-ddev-smoke.sh`, headless Chrome via CDP) prüft E2E: Login, Overlay per „+ Neue Lücke“, Datums-/Zeitfelder, Nachtschicht endet am nächsten Tag, Teilen per Aktionsdialog, Negativfall gleiche Zeiten, kein Browserfehler.
- Alle API-Aktionen der Oberfläche laufen ohne Browser-Prompts: Teilen, AS eintragen und Kontaktversuch nutzen einen eigenen Aktions-Dialog.
- „Offene Schichten aufnehmen“ ist eine editierbare Tabelle: „+ Zeile hinzufügen“ legt dynamisch weitere Zeilen an und übernimmt ASN, Datum und Zeiten der Vorzeile; geschlossene Dialoge überlagern den Inhalt nicht mehr (`dialog:not([open]){display:none}`).
- Lücke-Overlay nutzt die volle Breite: Formular als Flex-Spalte statt Grid, Tabelle über die gesamte Dialogbreite, kompaktes Tabellen-Padding, Hinweissatz entfernt.
- Übersicht ist zweistufig nach ASN gruppiert (alphabetisch, mit „Suchbogen öffnen“ je Gruppe); Schichtzeilen zeigen dd.mm | Von | Bis | AS/Aktionen.
- App-Root nutzt die volle Fensterbreite (width:100%; max-width:none); der Suchbogen liegt nicht mehr auf der Hauptseite, sondern öffnet über „Suchbogen öffnen“ die eigene Seite `/suchbogen?team=…` in einem neuen Browser-Tab.
- Lücke-Overlay: Buttons in einer Reihe (.button-row), Tabelle mit festem Spaltenlayout (ASN 11rem, Datum 8.5rem, Von/Bis 5.5rem, Entfernen 6.5rem, Kommentar Rest mit min-width 8rem), Zellen-Padding 2px 4px – kein horizontaler Scrollbalken mehr unter der Tabelle.
- Neue Lücke wird über „+ Neue Lücke“ in einem Overlay aufgenommen: ASN-Auswahl des eigenen Büros, ein Datumsfeld und zwei Zeitfelder. Liegt „Bis“ vor „Von“, endet die Nachtschicht serverseitig am nächsten Tag; gleiche Start- und Endzeit bleibt ungültig.
- Organisation und Feiertage ausschließlich an den öffentlichen flz_organization-V1-Vertrag gebunden. Aktivierungs-/Versionsprüfung, datensparsame Fehlerdiagnose und negative Gatewaytests verhindern den bisherigen LocalBase-Fallback auch bei fehlerhaftem kanonischen Provider.
- Browser-/DDEV-Abnahme einschließlich Migration, DI, Hintergrundjob, CSRF und Rollenmatrix abgeschlossen.
- Acht bürobezogene Akutberechtigungen getrennt, standardmäßig BO, EB,
  Büroleitung und Stellvertretender Büroleitung zugeordnet und über eine
  rollenbasierte Nextcloud-Adminoberfläche konfigurierbar gemacht. API,
  Nutzungsoberfläche und PermissionProvider werten jedes Recht unabhängig und
  bei fehlendem Organigrammvertrag fail-closed aus.
- Lesenden App-Einstieg von der CSRF-Prüfung ausgenommen, während alle
  Schreibzugriffe tokenpflichtig bleiben, und den wirkungslosen Host für die
  zentrale OrgSuite-Navigation ergänzt. Der katalogisierte Standalone-Einstieg
  wird bei aktiver OrgSuite unterdrückt.
- Initiale Kategorie-B-App für Akutschichtvermittlung angelegt.
- Öffentliche read-only V1-Consumer für LocalBase und FlzPlaner angebunden.
- Büroautorisierung, Vermittlungsworkflow, Kontaktverlauf, Dokumentationsmarker, Dringlichkeitsregeln und Retention-Grundvertrag implementiert.
- PersonalData-, ProcessingMetadata-, Retention- und PermissionProvider ergänzt.

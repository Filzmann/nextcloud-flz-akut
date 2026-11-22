# Changelog

## Unreleased

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

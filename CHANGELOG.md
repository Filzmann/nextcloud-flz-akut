# Changelog

## Unreleased

- Integration mit flz_organization als primäre Quelle für Organisations- und Feiertagsdaten vorbereitet. Die Gateways nutzen flz_organization zuerst und fallen bei Nichtverfügbarkeit auf LocalBase zurück.
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

# Changelog

## Unreleased

- Acht bürobezogene Akutberechtigungen getrennt, standardmäßig BO, EB,
  Büroleitung und Stellvertretender Büroleitung zugeordnet und über eine
  rollenbasierte Nextcloud-Adminoberfläche konfigurierbar gemacht. API,
  Nutzungsoberfläche und PermissionProvider werten jedes Recht unabhängig und
  bei fehlendem Organigrammvertrag fail-closed aus.
- Initiale Kategorie-B-App für Akutschichtvermittlung angelegt.
- Öffentliche read-only V1-Consumer für LocalBase und FlzPlaner angebunden.
- Büroautorisierung, Vermittlungsworkflow, Kontaktverlauf, Dokumentationsmarker, Dringlichkeitsregeln und Retention-Grundvertrag implementiert.
- PersonalData-, ProcessingMetadata-, Retention- und PermissionProvider ergänzt.

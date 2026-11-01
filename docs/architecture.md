# Architektur

`flz_akut` ist eine eigenständige Nextcloud-Laufzeit-App der Kategorie B. Sie besitzt Vermittlungsfälle, Kontakte, Audit und automatische Retention selbst. Sie liest ausschließlich öffentliche V1-Verträge aus LocalBase und FlzPlaner; direkte Fremdtabellen-, private AppConfig-, Controller-, Asset- oder Dateipfadzugriffe sind verboten.

LocalBase bleibt kanonische Quelle für die semantischen Rollen Büroorganisation, Einsatzbegleitung, Büroleitung und Stellvertretende Büroleitung, deren Bürobereiche, Mehrbüro-Mitgliedschaften und den gecachten gesetzlichen Feiertagskalender. Rollen- und Büro-Zuordnungen werden ausschließlich in der zentralen FLZ-Organisationsverwaltung verändert. `flz_akut` liest sie nur.

FlzPlaner ist kanonische Quelle für `flz-ASN-<Teamcode>`, genau einen Bürobereich je ASN-Team, aktive Team-AS und veränderliche Schichtzeitvorschläge. Die Auswahl einer Vorlage ist nur eine Herkunftsreferenz; Beginn und Ende des Akutfalls bleiben app-lokal veränderlich. AS besitzen keine Bürozuordnung; Kandidatensichtbarkeit und Zuweisung folgen ausschließlich ihrer Mitgliedschaft im gewählten ASN-Team.

Die App führt acht voneinander unabhängige Fachberechtigungen: Akutschichten lesen, offene Schicht anlegen, Schicht aufteilen, Kontaktversuche dokumentieren, AS vermitteln, vermittelte Schicht erneut öffnen sowie HyCare- und Papierakten-Dokumentation bestätigen. Standardmäßig sind jeder Berechtigung die vier genannten Organigrammrollen zugeordnet. Native Nextcloud-Admins können die Zuordnung im App-Adminbereich je Berechtigung verändern. Gespeichert werden ausschließlich semantische Rollenschlüssel; die aktuellen Gruppen-IDs werden bei der Auswertung aus dem öffentlichen LocalBase-V1-Snapshot aufgelöst.

Eine Fachaktion ist nur erlaubt, wenn sowohl die dafür konfigurierte Rolle als auch der aktuelle LocalBase-Bürobereich vorliegen. Eine fremde Bürozuordnung erteilt weder Sicht- noch Schreibzugriff. Ein fehlender oder inkompatibler Organisationsvertrag, eine fehlende konfigurierte Rollengruppe oder eine ungültige Konfiguration führt zum Entzug der betroffenen Berechtigung. UI-Sichtbarkeit erteilt keine Rechte; jeder Controllerpfad prüft Aktion, Objekt und Bürobereich serverseitig. Die Zustände sind `open`, `mediated` und der technische, nicht vermittelbare Ursprungszustand `split`.

Die Datenbanktabellen `flz_akut_shifts`, `flz_akut_contacts` und `flz_akut_audit` werden gemeinsam je Fall gelöscht. Standard sind 180 Tage ab Schichtende, jedoch nie vor einem Monat nach Monatsende; Datenschutzbeauftragte können 30 bis 365 Tage konfigurieren. Die Wirkung einer späteren Änderung auf bestehende Ablaufzeitpunkte ist noch nicht entschieden und deshalb nicht rückwirkend implementiert.

Gesetzliche Feiertage kommen aus dem LocalBase-Cache. `unavailable` ist niemals eine leere Feiertagsliste und erzeugt sichtbar keine SZ-Aussage. Die fachliche Zeitzone kommt aus dem Provider und ist regulär `Europe/Berlin`.

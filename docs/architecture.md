# Architektur

`flz_akut` ist eine eigenständige Nextcloud-Laufzeit-App der Kategorie B. Sie besitzt Vermittlungsfälle, Kontakte, Audit und automatische Retention selbst. Sie liest ausschließlich öffentliche V1-Verträge aus LocalBase und FlzPlaner; direkte Fremdtabellen-, private AppConfig-, Controller-, Asset- oder Dateipfadzugriffe sind verboten.

LocalBase bleibt kanonische Quelle für semantische BO-/EB-Rollen, deren Bürobereiche, Mehrbüro-Mitgliedschaften und den gecachten gesetzlichen Feiertagskalender. BO-/EB-Büro-Zuordnungen werden ausschließlich in der zentralen FLZ-Organisationsverwaltung verändert. `flz_akut` liest sie nur.

FlzPlaner ist kanonische Quelle für `flz-ASN-<Teamcode>`, genau einen Bürobereich je ASN-Team, aktive Team-AS und veränderliche Schichtzeitvorschläge. Die Auswahl einer Vorlage ist nur eine Herkunftsreferenz; Beginn und Ende des Akutfalls bleiben app-lokal veränderlich. AS besitzen keine Bürozuordnung; Kandidatensichtbarkeit und Zuweisung folgen ausschließlich ihrer Mitgliedschaft im gewählten ASN-Team.

BO und EB besitzen innerhalb ihrer aktuellen Bürobereiche dieselben Vermittlungsrechte. Eine fremde Bürozuordnung erteilt weder Sicht- noch Schreibzugriff. UI-Sichtbarkeit erteilt keine Rechte. Die Zustände sind `open`, `mediated` und der technische, nicht vermittelbare Ursprungszustand `split`. Wiederöffnung ist für berechtigte BO/EB möglich.

Die Datenbanktabellen `flz_akut_shifts`, `flz_akut_contacts` und `flz_akut_audit` werden gemeinsam je Fall gelöscht. Standard sind 180 Tage ab Schichtende, jedoch nie vor einem Monat nach Monatsende; Datenschutzbeauftragte können 30 bis 365 Tage konfigurieren. Die Wirkung einer späteren Änderung auf bestehende Ablaufzeitpunkte ist noch nicht entschieden und deshalb nicht rückwirkend implementiert.

Gesetzliche Feiertage kommen aus dem LocalBase-Cache. `unavailable` ist niemals eine leere Feiertagsliste und erzeugt sichtbar keine SZ-Aussage. Die fachliche Zeitzone kommt aus dem Provider und ist regulär `Europe/Berlin`.

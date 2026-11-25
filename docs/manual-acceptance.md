# Manuelle Abnahme

- [ ] App lässt sich frisch installieren und die drei Tabellen sowie der tägliche Retention-Job existieren.
- [ ] BO, EB, Büroleitung und Stellvertretende Büroleitung erhalten im Ausgangszustand alle acht Einzelrechte ausschließlich in ihren Bürobereichen; Mehrbüro-Konten sehen nur ihre zugeordneten Bereiche.
- [ ] Im Nextcloud-Adminbereich lässt sich jede der acht Berechtigungen unabhängig einer oder mehreren der vier Organigrammrollen zuordnen; es gibt keine Freitexteingabe für Gruppen.
- [ ] Entzug eines einzelnen Rechts entfernt nur die zugehörige Aktion aus der Oberfläche und wird am API-Endpunkt serverseitig verweigert, ohne Daten zu ändern.
- [ ] Eine fachlich berechtigte Person mit fremdem Bürobereich kann einen Fall weder lesen noch verändern.
- [ ] Fehlende flz_organization-Verträge, Rollen oder Nextcloud-Gruppen führen sichtbar und serverseitig zum Rechteentzug.
- [ ] Ausschließlich aktive Mitglieder des ASN-Teams sind als AS auswählbar; für AS wird keine Bürozuordnung vorausgesetzt oder ausgewertet.
- [ ] „+ Neue Lücke“ öffnet ein Overlay mit editierbarer Tabelle (ASN, Datum, Von, Bis, Kommentar je Zeile); „+ Zeile hinzufügen“ übernimmt die Werte der Vorzeile; geschlossene Overlays überlagern den Inhalt nicht.
- [ ] Teilen, AS eintragen und Kontaktversuch arbeiten ohne Browser-Prompts über eigene Dialoge; Tastaturbedienung und Fokus sind geprüft.
- [ ] Liegt „Bis“ vor „Von“, endet die Nachtschicht am nächsten Tag; gleiche Start- und Endzeit wird mit Fehler abgelehnt.
- [ ] Schichtvorschlag kann übernommen und danach verändert werden.
- [ ] Eine Schicht kann an beliebigen inneren Zeitpunkten lückenfrei geteilt werden; der Ursprung bleibt unvermittelbar. Der Teilen-Dialog vorbefüllt das Schichtdatum, bietet einen 15-Minuten-Schieberegler zwischen Anfang und Ende und die entstehenden Teile sind zwei komplett getrennte offene Schichten.
- [ ] Mehrere Telefon-/SMS-Versuche je AS und Schicht bleiben mehrzeilig erhalten; optionale Gründe und kurze Bemerkungen funktionieren.
- [ ] Vermittlung, Wiederöffnung, HyCare und Papierakte funktionieren; Text erklärt jede Farbmarkierung. HyCare/Akte ist erst nach Vermittlung möglich; HyCare färbt Datum, Von und Bis lila. Vermittelter AS ist mit Anzeigename sichtbar; „Erneut öffnen“ nur mit AS; AS-Eintrag per Suchfeld (Team- und Vertretungsvorschläge, freie Eingabe).
- [ ] Freitag, Wochenende und Feiertag ergeben den nächsten Büroarbeitstag korrekt; fehlende Feiertage zeigen keine falsche SZ-Aussage.
- [ ] Nur `Datenschutzbeauftragte` können 30 bis 365 Tage konfigurieren.
- [ ] Abgelaufene Fälle, Kontakte und Auditdaten werden gemeinsam gelöscht.
- [ ] Die Übersicht gruppiert alphabetisch nach ASN (Gruppenzeile mit „Suchbogen öffnen“); Schichtzeilen zeigen dd.mm, Von, Bis sowie AS und Aktionen.
- [ ] Die App nutzt die komplette Fensterbreite; „Suchbogen öffnen“ legt die Suchbogen-Seite in einem neuen Browser-Tab an (dort ohne Schichttabelle, mit Kontaktversuchen).
- [ ] Tastatur, Fokus, responsives Layout, vertikaler App-Scroll und horizontaler Tabellenscroll sind geprüft.

## Automatischer Gateway-Nachweis

`php tests/Integration/OrganizationGatewayIntegrationTest.php` führt beide echten Gatewayklassen mit synthetischen öffentlichen Providerdoubles aus. Gültige Rollen und Feiertage bleiben verfügbar; deaktivierte, ungültige, inkompatible und fehlerhafte Provider liefern keine Ersatzdaten aus LocalBase. Fehler werden ohne Exceptionpayload protokolliert. Der Test war vor Entfernung des Fallbacks rot (Exit 255) und besteht danach (Exit 0). Er ersetzt keinen Browser- oder Provider-Lifecycle-Test.

Der lokale read-only DI-Nachweis wird ausschließlich aus dem dokumentierten `nextcloud-dev`-Root gestartet:

```bash
bash ../flz_akut/tests/runtime/validate-flz-organization-integration.sh
```

Bei der letzten lokalen DDEV-Prüfung meldete der Organisationsgateway `AVAILABLE`; der `HolidayGateway` ließ sich per Nextcloud-DI auflösen. Der Nachweis ruft den Feiertagsprovider nicht ab und prüft weder HTTP-Abruf noch Cacheinhalt. Browserbedienung und Provider-Aktivierungs-/Deaktivierungs-Lifecycle wurden damit nicht erneut abgenommen.

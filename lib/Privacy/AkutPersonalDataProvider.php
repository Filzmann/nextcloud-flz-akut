<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Privacy;

use OCA\FlzDataProtection\PublicApi\V1\PersonalDataEntry;
use OCA\FlzDataProtection\PublicApi\V1\PersonalDataPage;
use OCA\FlzDataProtection\PublicApi\V1\PersonalDataProvider;
use OCA\FlzDataProtection\PublicApi\V1\PersonalDataRequest;
use OCA\FlzDataProtection\PublicApi\V1\ProviderDescriptor;

final class AkutPersonalDataProvider implements PersonalDataProvider {
    public function __construct(private AkutPrivacySource $source){}
    public function descriptor():ProviderDescriptor{return new ProviderDescriptor('flz_akut','Akutschichtvermittlung','1.0',['nextcloud-user'],['personal-data'],500);}
    public function collect(PersonalDataRequest $request):PersonalDataPage{if($request->subject()->subjectType()!=='nextcloud-user')return new PersonalDataPage('not_applicable');if($request->cursor()!==null)throw new \InvalidArgumentException('Cursor paging is not supported.');$rows=$this->source->forSubject($request->subject()->subjectId(),$request->pageLimit()+1);$partial=count($rows)>$request->pageLimit();if($partial)$rows=array_slice($rows,0,$request->pageLimit());$entries=array_map(fn(array$row):PersonalDataEntry=>$this->entry($row),$rows);return$entries===[]?new PersonalDataPage('not_applicable'):new PersonalDataPage($partial?'partial':'complete',$entries,$partial?['Ausgabelimit erreicht.']:[]);}
    private function entry(array$row):PersonalDataEntry{$contact=$row['kind']==='contact';return new PersonalDataEntry(categoryId:$contact?'contact-attempt':'acute-shift',categoryLabel:$contact?'Kontaktversuch':'Akutschicht',reference:($contact?'contact:':'shift:').(int)$row['id'],summary:$contact?'Kontaktversuch per '.(string)$row['channel']:'Akutschicht '.(string)$row['team_code'],purpose:'Kurzfristige Besetzung offener Assistenzschichten',source:'Eingaben fachlich berechtigter Bürorollen und read-only Planer-Teambezug',recipientCategories:['Für die konkrete Aktion berechtigte Bürorollen des zuständigen Büros'],retention:'Standard 180 Tage; durch Datenschutzbeauftragte innerhalb 30 bis 365 Tagen konfigurierbar, jedoch nie vor einem Monat nach Monatsende.',thirdCountryTransfer:'Die App selbst übermittelt keine Daten an Drittländer.',automatedDecision:'Keine automatisierte Personalentscheidung; Vermittlung erfolgt durch berechtigte Mitarbeitende.',thirdPartyContentNotice:'Freie Kommentare und Angaben anderer Personen werden nicht ausgegeben.',attributes:$contact?['Kanal'=>(string)$row['channel'],'Ergebnis'=>(string)$row['outcome'],'Zeitpunkt'=>(string)$row['contacted_at']]:['Team'=>(string)$row['team_code'],'Beginn'=>(string)$row['starts_at'],'Ende'=>(string)$row['ends_at'],'Status'=>(string)$row['state']]);}
}

<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Privacy;

use OCA\FlzAkut\Repository\ShiftStore;
use OCA\FlzAkut\Service\RetentionSettingsService;
use OCA\FlzDataProtection\PublicApi\V1\RetentionCandidate;
use OCA\FlzDataProtection\PublicApi\V1\RetentionPolicy;
use OCA\FlzDataProtection\PublicApi\V1\RetentionPreviewPage;
use OCA\FlzDataProtection\PublicApi\V1\RetentionPreviewRequest;
use OCA\FlzDataProtection\PublicApi\V1\RetentionProvider;
use OCA\FlzDataProtection\PublicApi\V1\RetentionProviderDescriptor;

final class AkutRetentionProvider implements RetentionProvider {
    private const POLICY='acute_shift_case_retention';
    public function __construct(private ShiftStore $store,private RetentionSettingsService $settings){}
    public function descriptor():RetentionProviderDescriptor{return new RetentionProviderDescriptor('flz_akut','Akutschichtvermittlung','1.0',500);}
    public function policies():array{return[new RetentionPolicy(self::POLICY,'Akutschichten, Kontaktversuche und Audit','Vergütungsnachweis und Vermittlungsdokumentation','COMPLETED_AT',$this->settings->days(),'REVIEW','1.0')];}
    public function preview(RetentionPreviewRequest $request):RetentionPreviewPage{if($request->policyId()!==self::POLICY)throw new \InvalidArgumentException('Unbekannte Retention-Richtlinie.');if($request->cursor()!==null)throw new \InvalidArgumentException('Cursor-Paging wird nicht unterstützt.');$rows=$this->store->retentionCandidates($request->evaluatedAt(),$request->limit());$items=array_map(static fn(array$row):RetentionCandidate=>new RetentionCandidate(self::POLICY,'shift:'.$row['id'],$row['expires_at'],'REVIEW','App-lokale automatische Löschung ist ab diesem Zeitpunkt vorgesehen.',['Status'=>$row['state']]),$rows);return new RetentionPreviewPage($items===[]?'not_applicable':'complete',$items);}
}

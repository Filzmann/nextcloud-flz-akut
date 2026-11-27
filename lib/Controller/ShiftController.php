<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Controller;

use OCA\FlzAkut\AppInfo\Application;
use OCA\FlzAkut\Domain\ShiftWorkflow;
use OCA\FlzAkut\Domain\UrgencyClassifier;
use OCA\FlzAkut\Repository\ShiftStore;
use OCA\FlzAkut\Service\AbsenceGateway;
use OCA\FlzAkut\Service\CapacityGateway;
use OCA\FlzAkut\Service\AssistantInfoService;
use OCA\FlzAkut\Service\MaKuerzelService;
use OCA\FlzAkut\Service\AccessService;
use OCA\FlzAkut\Service\AkutCapability;
use OCA\FlzAkut\Service\HolidayGateway;
use OCA\FlzAkut\Service\MailTemplateService;
use OCA\FlzAkut\Service\OfficeDayService;
use OCA\FlzAkut\Service\PlanerCatalogGateway;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

final class ShiftController extends Controller {
    public function __construct(IRequest $request,private AccessService $access,private PlanerCatalogGateway $catalog,private HolidayGateway $holidays,private ShiftStore $store,private ShiftWorkflow $workflow,private MailTemplateService $mailTemplates,private OfficeDayService $officeDay,private AbsenceGateway $absences,private CapacityGateway $capacities,private AssistantInfoService $assistantInfo,private MaKuerzelService $maKuerzel,private \OCP\IUserSession $userSession,private IL10N $l10n){parent::__construct(Application::APP_ID,$request);}
    #[NoAdminRequired,NoCSRFRequired]
    public function state():DataResponse{return$this->respond(function():array{$bureaux=$this->access->bureauKeys(AkutCapability::READ);if($bureaux===[])throw new \DomainException('Keine Leseberechtigung für einen Bürobereich.');$catalog=$this->catalog->teams($bureaux);foreach($catalog['teams'] as &$catalogTeam){foreach($catalogTeam['assistants'] ?? [] as &$assistant){$assistant['info']=$this->assistantInfo->info((string)$assistant['uid']);$assistant['comment']=$this->assistantInfo->comment((string)$assistant['uid']);}unset($assistant);}unset($catalogTeam);$shifts=$this->store->listForBureaux($bureaux);foreach($shifts as &$shift){$year=(int)substr((string)$shift['starts_at'],0,4);$calendar=$this->holidays->year($year);$shift['urgency']=$calendar['status']==='AVAILABLE'?(new UrgencyClassifier($this->officeDay->timezone()))->classify((string)$shift['starts_at'],(new \DateTimeImmutable())->format(DATE_ATOM),$calendar['dates']):'unavailable';}unset($shift);$teamUids=array_values(array_unique(array_merge(...array_map(static fn(array $t):array=>array_column($t['assistants'] ?? [],'uid'),$catalog['teams'] ?? []))));$permissions=[];foreach(AkutCapability::actionCapabilities()as$capability)$permissions[$capability]=$this->access->bureauKeys($capability);return['bureaux'=>$bureaux,'providerStatus'=>$catalog['status'],'permissions'=>$permissions,'teams'=>$catalog['teams'],'shifts'=>$shifts,'mailTemplate'=>$this->mailTemplates->template(),'officeDayStart'=>$this->officeDay->start(),'timezone'=>$this->officeDay->timezone(),'contacts'=>$this->contactsOf($shifts),'absences'=>$this->absences->forShifts($shifts,$teamUids),'capacities'=>$this->capacities->forShifts($shifts,$teamUids)];});}
    #[NoAdminRequired]
    public function create(string $teamCode,string $bureauKey,string $startsAt,string $endsAt,string $comment='',string $sourceTemplateKey=''):DataResponse{return$this->respond(function()use($teamCode,$bureauKey,$startsAt,$endsAt,$comment,$sourceTemplateKey):array{$this->access->assertBureau(AkutCapability::CREATE,$bureauKey);$this->team($teamCode,$bureauKey);return['shift'=>$this->workflow->create($teamCode,$bureauKey,$startsAt,$endsAt,$comment,$sourceTemplateKey===''?null:$sourceTemplateKey,$this->access->currentUid())];});}
    #[NoAdminRequired]
    public function split(int $id,string $cutPointsJson):DataResponse{return$this->respond(function()use($id,$cutPointsJson):array{$this->authorizedShift($id,AkutCapability::SPLIT);$cuts=json_decode($cutPointsJson,true);if(!is_array($cuts)||!array_is_list($cuts))throw new \InvalidArgumentException('Teilungszeiten sind ungültig.');return['parts'=>$this->workflow->split($id,array_map('strval',$cuts),$this->access->currentUid())];});}
    #[NoAdminRequired]
    public function accept(int $id,string $assistantUid,string $maLabel,string $mediatedAt,bool $informedAsn=false):DataResponse{return$this->respond(function()use($id,$assistantUid,$maLabel,$mediatedAt,$informedAsn):array{$this->authorizedShift($id,AkutCapability::ASSIGN);return['shift'=>$this->workflow->accept($id,$assistantUid,$maLabel,$mediatedAt,$informedAsn,$this->access->currentUid())];});}
    #[NoAdminRequired]
    public function update(int $id,?string $assistantUid=null,?string $maLabel=null,?string $comment=null):DataResponse{return$this->respond(function()use($id,$assistantUid,$maLabel,$comment):array{$shift=$this->store->get($id);if($shift===null)throw new \DomainException('Schicht nicht gefunden.');$bureau=(string)$shift['bureau_key'];if($assistantUid!==null||$maLabel!==null)$this->access->assertBureau(AkutCapability::ASSIGN,$bureau);if($comment!==null)$this->access->assertBureau(AkutCapability::CREATE,$bureau);return['shift'=>$this->workflow->update($id,$assistantUid,$maLabel,$comment,$this->access->currentUid())];});}
    #[NoAdminRequired]
    public function reopen(int $id):DataResponse{return$this->respond(function()use($id):array{$this->authorizedShift($id,AkutCapability::REOPEN);return['shift'=>$this->workflow->reopen($id,$this->access->currentUid())];});}
    #[NoAdminRequired]
    public function contact(int $id,string $assistantUid,string $channel,string $outcome,string $contactedAt,string $reason='',string $note=''):DataResponse{return$this->respond(function()use($id,$assistantUid,$channel,$outcome,$contactedAt,$reason,$note):array{$shift=$this->authorizedShift($id,AkutCapability::CONTACT);$team=$this->team($shift['team_code'],$shift['bureau_key']);if(!in_array($assistantUid,array_column($team['assistants'],'uid'),true))throw new \DomainException('Assistenz gehört nicht zum ASN-Team.');return['attempt'=>$this->workflow->addContact($id,$assistantUid,$channel,$outcome,$contactedAt,$this->access->currentUid(),$reason===''?null:$reason,$note===''?null:$note)];});}
    #[NoAdminRequired]
    public function saveAssistantMeta(string $assistantUid,?string $tagsJson=null,?string $note=null,?string $comment=null):DataResponse{return$this->respond(function()use($assistantUid,$tagsJson,$note,$comment):array{$bureaux=$this->access->bureauKeys(AkutCapability::CONTACT);if($bureaux===[])throw new \DomainException('Keine Kontaktberechtigung für einen Bürobereich.');$catalog=$this->catalog->teams($bureaux);$team=null;foreach($catalog['teams'] ?? [] as $t){if(in_array($assistantUid,array_column($t['assistants'] ?? [],'uid'),true)){$team=$t;break;}}if($team===null)throw new \DomainException('Assistenz gehört zu keinem ASN-Team mit Kontaktberechtigung.');$this->access->assertBureau(AkutCapability::CONTACT,(string)$team['bureauKey']);$info=null;if($tagsJson!==null){$tags=json_decode($tagsJson,true);if(!is_array($tags))throw new \InvalidArgumentException('Tags sind ungültig.');$info=$this->assistantInfo->saveInfo($assistantUid,array_map('strval',$tags),$note ?? $this->assistantInfo->info($assistantUid)['note']);}$savedComment=$comment!==null?$this->assistantInfo->saveComment($assistantUid,$comment):null;return['info'=>$info ?? $this->assistantInfo->info($assistantUid),'comment'=>$savedComment ?? $this->assistantInfo->comment($assistantUid)];});}
    #[NoAdminRequired]
    public function mailSent(int $id):DataResponse{return$this->respond(function()use($id):array{$this->authorizedShift($id,AkutCapability::CREATE);$user=$this->userSession->getUser();$uid=$this->access->currentUid();$kuerzel=$this->maKuerzel->effective($uid,$user?$user->getDisplayName():$uid);$day=(new \DateTimeImmutable('now',new \DateTimeZone($this->officeDay->timezone())))->format('d.m.');return['shift'=>$this->workflow->markMailSent($id,$day,$kuerzel,$uid)];});}
    #[NoAdminRequired]
    public function confirm(int $id,string $kind,string $at):DataResponse{return$this->respond(function()use($id,$kind,$at):array{$capability=match($kind){'hycare'=>AkutCapability::CONFIRM_HYCARE,'paper'=>AkutCapability::CONFIRM_PAPER,default=>throw new \InvalidArgumentException('Dokumentationsziel ist ungültig.')};$this->authorizedShift($id,$capability);return['shift'=>$this->workflow->confirmDocumentation($id,$kind,$at,$this->access->currentUid())];});}
    /** @param list<array> $shifts @return list<array> */
    private function contactsOf(array $shifts):array{return array_map(static fn(array $c):array=>['id'=>(int)$c['id'],'shiftId'=>(int)$c['shift_id'],'assistantUid'=>(string)$c['assistant_uid'],'channel'=>(string)$c['channel'],'outcome'=>(string)$c['outcome'],'contactedAt'=>(string)$c['contacted_at'],'reason'=>$c['reason']===null?null:(string)$c['reason'],'note'=>$c['note']===null?null:(string)$c['note']],$this->store->listContactsForShifts(array_map(static fn(array $s):int=>(int)$s['id'],$shifts)));}
    private function authorizedShift(int $id,string $capability):array{$shift=$this->store->get($id);if($shift===null)throw new \DomainException('Schicht nicht gefunden.');$this->access->assertBureau($capability,(string)$shift['bureau_key']);return$shift;}
    private function team(string $teamCode,string $bureauKey):array{$catalog=$this->catalog->teams([$bureauKey]);if($catalog['status']!=='AVAILABLE')throw new \DomainException('Planer-Vertrag ist nicht verfügbar.');foreach($catalog['teams']as$team)if($team['teamCode']===$teamCode&&$team['bureauKey']===$bureauKey)return$team;throw new \DomainException('ASN-Team ist diesem Büro nicht zugeordnet.');}
    private function respond(callable $callback):DataResponse{try{return new DataResponse(['ok'=>true]+$callback());}catch(\InvalidArgumentException $e){return new DataResponse(['ok'=>false,'error'=>$this->l10n->t($e->getMessage())],400);}catch(\DomainException $e){return new DataResponse(['ok'=>false,'error'=>$this->l10n->t($e->getMessage())],403);}catch(\Throwable){return new DataResponse(['ok'=>false,'error'=>$this->l10n->t('Akutschichtvermittlung ist vorübergehend nicht verfügbar.')],503);}}
}

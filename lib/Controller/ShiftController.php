<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Controller;

use OCA\FlzAkut\AppInfo\Application;
use OCA\FlzAkut\Domain\ShiftWorkflow;
use OCA\FlzAkut\Domain\UrgencyClassifier;
use OCA\FlzAkut\Repository\ShiftStore;
use OCA\FlzAkut\Service\AccessService;
use OCA\FlzAkut\Service\AkutCapability;
use OCA\FlzAkut\Service\HolidayGateway;
use OCA\FlzAkut\Service\PlanerCatalogGateway;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

final class ShiftController extends Controller {
    public function __construct(IRequest $request,private AccessService $access,private PlanerCatalogGateway $catalog,private HolidayGateway $holidays,private ShiftStore $store,private ShiftWorkflow $workflow){parent::__construct(Application::APP_ID,$request);}
    #[NoAdminRequired,NoCSRFRequired]
    public function state():DataResponse{return$this->respond(function():array{$bureaux=$this->access->bureauKeys(AkutCapability::READ);if($bureaux===[])throw new \DomainException('Keine Leseberechtigung für einen Bürobereich.');$catalog=$this->catalog->teams($bureaux);$shifts=$this->store->listForBureaux($bureaux);foreach($shifts as &$shift){$year=(int)substr((string)$shift['starts_at'],0,4);$calendar=$this->holidays->year($year);$shift['urgency']=$calendar['status']==='AVAILABLE'?(new UrgencyClassifier((string)$calendar['timezone']))->classify((string)$shift['starts_at'],(new \DateTimeImmutable())->format(DATE_ATOM),$calendar['dates']):'unavailable';}unset($shift);$permissions=[];foreach(AkutCapability::actionCapabilities()as$capability)$permissions[$capability]=$this->access->bureauKeys($capability);return['bureaux'=>$bureaux,'providerStatus'=>$catalog['status'],'permissions'=>$permissions,'teams'=>$catalog['teams'],'shifts'=>$shifts];});}
    #[NoAdminRequired]
    public function create(string $teamCode,string $bureauKey,string $startsAt,string $endsAt,string $comment='',string $sourceTemplateKey=''):DataResponse{return$this->respond(function()use($teamCode,$bureauKey,$startsAt,$endsAt,$comment,$sourceTemplateKey):array{$this->access->assertBureau(AkutCapability::CREATE,$bureauKey);$this->team($teamCode,$bureauKey);return['shift'=>$this->workflow->create($teamCode,$bureauKey,$startsAt,$endsAt,$comment,$sourceTemplateKey===''?null:$sourceTemplateKey,$this->access->currentUid())];});}
    #[NoAdminRequired]
    public function split(int $id,string $cutPointsJson):DataResponse{return$this->respond(function()use($id,$cutPointsJson):array{$this->authorizedShift($id,AkutCapability::SPLIT);$cuts=json_decode($cutPointsJson,true);if(!is_array($cuts)||!array_is_list($cuts))throw new \InvalidArgumentException('Teilungszeiten sind ungültig.');return['parts'=>$this->workflow->split($id,array_map('strval',$cuts),$this->access->currentUid())];});}
    #[NoAdminRequired]
    public function accept(int $id,string $assistantUid,string $maLabel,string $mediatedAt,bool $informedAsn=false):DataResponse{return$this->respond(function()use($id,$assistantUid,$maLabel,$mediatedAt,$informedAsn):array{$shift=$this->authorizedShift($id,AkutCapability::ASSIGN);$team=$this->team($shift['team_code'],$shift['bureau_key']);$uids=array_column($team['assistants'],'uid');if(!in_array($assistantUid,$uids,true))throw new \DomainException('Assistenz gehört nicht zum ASN-Team.');return['shift'=>$this->workflow->accept($id,$assistantUid,$maLabel,$mediatedAt,$informedAsn,$this->access->currentUid())];});}
    #[NoAdminRequired]
    public function reopen(int $id):DataResponse{return$this->respond(function()use($id):array{$this->authorizedShift($id,AkutCapability::REOPEN);return['shift'=>$this->workflow->reopen($id,$this->access->currentUid())];});}
    #[NoAdminRequired]
    public function contact(int $id,string $assistantUid,string $channel,string $outcome,string $contactedAt,string $reason='',string $note=''):DataResponse{return$this->respond(function()use($id,$assistantUid,$channel,$outcome,$contactedAt,$reason,$note):array{$shift=$this->authorizedShift($id,AkutCapability::CONTACT);$team=$this->team($shift['team_code'],$shift['bureau_key']);if(!in_array($assistantUid,array_column($team['assistants'],'uid'),true))throw new \DomainException('Assistenz gehört nicht zum ASN-Team.');return['attempt'=>$this->workflow->addContact($id,$assistantUid,$channel,$outcome,$contactedAt,$this->access->currentUid(),$reason===''?null:$reason,$note===''?null:$note)];});}
    #[NoAdminRequired]
    public function confirm(int $id,string $kind,string $at):DataResponse{return$this->respond(function()use($id,$kind,$at):array{$capability=match($kind){'hycare'=>AkutCapability::CONFIRM_HYCARE,'paper'=>AkutCapability::CONFIRM_PAPER,default=>throw new \InvalidArgumentException('Dokumentationsziel ist ungültig.')};$this->authorizedShift($id,$capability);return['shift'=>$this->workflow->confirmDocumentation($id,$kind,$at,$this->access->currentUid())];});}
    private function authorizedShift(int $id,string $capability):array{$shift=$this->store->get($id);if($shift===null)throw new \DomainException('Schicht nicht gefunden.');$this->access->assertBureau($capability,(string)$shift['bureau_key']);return$shift;}
    private function team(string $teamCode,string $bureauKey):array{$catalog=$this->catalog->teams([$bureauKey]);if($catalog['status']!=='AVAILABLE')throw new \DomainException('Planer-Vertrag ist nicht verfügbar.');foreach($catalog['teams']as$team)if($team['teamCode']===$teamCode&&$team['bureauKey']===$bureauKey)return$team;throw new \DomainException('ASN-Team ist diesem Büro nicht zugeordnet.');}
    private function respond(callable $callback):DataResponse{try{return new DataResponse(['ok'=>true]+$callback());}catch(\InvalidArgumentException $e){return new DataResponse(['ok'=>false,'error'=>$e->getMessage()],400);}catch(\DomainException $e){return new DataResponse(['ok'=>false,'error'=>$e->getMessage()],403);}catch(\Throwable){return new DataResponse(['ok'=>false,'error'=>'Akutschichtvermittlung ist vorübergehend nicht verfügbar.'],503);}}
}

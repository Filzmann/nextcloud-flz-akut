<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Privacy;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final class NextcloudAkutPrivacySource implements AkutPrivacySource {
    public function __construct(private IDBConnection $db){}
    public function forSubject(string $uid,int $limit):array{$rows=[];$qb=$this->db->getQueryBuilder();$p=$qb->createNamedParameter($uid);$result=$qb->select('id','team_code','starts_at','ends_at','state','assigned_uid','created_by','hycare_by','paper_by')->from('flz_akut_shifts')->where($qb->expr()->orX($qb->expr()->eq('assigned_uid',$p),$qb->expr()->eq('created_by',$p),$qb->expr()->eq('hycare_by',$p),$qb->expr()->eq('paper_by',$p)))->setMaxResults($limit)->executeQuery()->fetchAllAssociative();foreach($result as$row)$rows[]=['kind'=>'shift']+$row;$remaining=$limit-count($rows);if($remaining>0){$qb=$this->db->getQueryBuilder();$p=$qb->createNamedParameter($uid);$result=$qb->select('id','shift_id','channel','outcome','contacted_at','assistant_uid','actor_uid')->from('flz_akut_contacts')->where($qb->expr()->orX($qb->expr()->eq('assistant_uid',$p),$qb->expr()->eq('actor_uid',$p)))->setMaxResults($remaining)->executeQuery()->fetchAllAssociative();foreach($result as$row)$rows[]=['kind'=>'contact']+$row;}$remaining=$limit-count($rows);if($remaining>0){$qb=$this->db->getQueryBuilder();$p=$qb->createNamedParameter($uid);$result=$qb->select('configkey','configvalue')->from('preferences')->where($qb->expr()->eq('userid',$p))->andWhere($qb->expr()->eq('appid',$qb->createNamedParameter(\OCA\FlzAkut\Service\AssistantInfoService::APP_ID)))->andWhere($qb->expr()->in('configkey',$qb->createNamedParameter([\OCA\FlzAkut\Service\AssistantInfoService::KEY_INFO,\OCA\FlzAkut\Service\AssistantInfoService::KEY_COMMENT,\OCA\FlzAkut\Service\MaKuerzelService::KEY],IQueryBuilder::PARAM_STR_ARRAY)))->setMaxResults($remaining)->executeQuery()->fetchAllAssociative();foreach($result as$row)$rows[]=['kind'=>'user-config','id'=>0,'key'=>(string)$row['configkey'],'value'=>(string)$row['configvalue']];}return$rows;}
}

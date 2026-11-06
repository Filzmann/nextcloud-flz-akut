<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Repository;

use DateTimeImmutable;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final class DbShiftStore implements ShiftStore {
    public function __construct(private IDBConnection $db) {}

    public function insert(array $shift): array {
        $qb=$this->db->getQueryBuilder(); $values=[];
        foreach($shift as $key=>$value)$values[$key]=$qb->createNamedParameter($this->dbValue($key,$value),$this->dbType($key));
        $qb->insert('flz_akut_shifts')->values($values)->executeStatement();
        return $this->get((int)$this->db->lastInsertId('flz_akut_shifts')) ?? throw new \RuntimeException('Schicht konnte nicht gelesen werden.');
    }
    public function get(int $id): ?array { $qb=$this->db->getQueryBuilder();$row=$qb->select('*')->from('flz_akut_shifts')->where($qb->expr()->eq('id',$qb->createNamedParameter($id,IQueryBuilder::PARAM_INT)))->executeQuery()->fetchAssociative();return $row===false?null:$this->normalize($row); }
    public function update(int $id,array $changes):array{$qb=$this->db->getQueryBuilder();$qb->update('flz_akut_shifts');foreach($changes as $key=>$value)$qb->set($key,$qb->createNamedParameter($this->dbValue($key,$value),$this->dbType($key)));$qb->where($qb->expr()->eq('id',$qb->createNamedParameter($id,IQueryBuilder::PARAM_INT)))->executeStatement();return $this->get($id)??throw new \DomainException('Schicht nicht gefunden.');}
    public function addContact(array $attempt):array{$qb=$this->db->getQueryBuilder();$values=[];foreach($attempt as $key=>$value)$values[$key]=$qb->createNamedParameter($this->dbValue($key,$value),$this->dbType($key));$qb->insert('flz_akut_contacts')->values($values)->executeStatement();return ['id'=>(int)$this->db->lastInsertId('flz_akut_contacts')]+$attempt;}
    public function addAudit(array $event):void{$qb=$this->db->getQueryBuilder();$values=[];foreach($event as $key=>$value)$values[$key]=$qb->createNamedParameter($this->dbValue($key,$value),$this->dbType($key));$qb->insert('flz_akut_audit')->values($values)->executeStatement();}
    public function listForBureaux(array $bureauKeys):array{if($bureauKeys===[])return[];$qb=$this->db->getQueryBuilder();$rows=$qb->select('*')->from('flz_akut_shifts')->where($qb->expr()->in('bureau_key',$qb->createNamedParameter(array_values($bureauKeys),IQueryBuilder::PARAM_STR_ARRAY)))->orderBy('starts_at','ASC')->executeQuery()->fetchAllAssociative();return array_map(fn(array $row):array=>$this->normalize($row),$rows);}
    public function deleteExpired(string $cutoff):int{$qb=$this->db->getQueryBuilder();$rows=$qb->select('id')->from('flz_akut_shifts')->where($qb->expr()->lt('expires_at',$qb->createNamedParameter(new DateTimeImmutable($cutoff),IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))->executeQuery()->fetchFirstColumn();$count=0;$this->db->beginTransaction();try{foreach($rows as $raw){$id=(int)$raw;foreach(['flz_akut_contacts','flz_akut_audit']as$table){$d=$this->db->getQueryBuilder();$d->delete($table)->where($d->expr()->eq('shift_id',$d->createNamedParameter($id,IQueryBuilder::PARAM_INT)))->executeStatement();}$d=$this->db->getQueryBuilder();$count+=$d->delete('flz_akut_shifts')->where($d->expr()->eq('id',$d->createNamedParameter($id,IQueryBuilder::PARAM_INT)))->executeStatement();}$this->db->commit();return$count;}catch(\Throwable $e){$this->db->rollBack();throw$e;}}
    public function retentionCandidates(string $evaluatedAt,int $limit):array{$qb=$this->db->getQueryBuilder();$rows=$qb->select('id','expires_at','state')->from('flz_akut_shifts')->where($qb->expr()->lte('expires_at',$qb->createNamedParameter(new DateTimeImmutable($evaluatedAt),IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))->orderBy('id','ASC')->setMaxResults($limit)->executeQuery()->fetchAllAssociative();return array_map(static fn(array $row):array=>['id'=>(int)$row['id'],'expires_at'=>$row['expires_at'] instanceof \DateTimeInterface?$row['expires_at']->format(DATE_ATOM):(string)$row['expires_at'],'state'=>(string)$row['state']],$rows);}
    private function normalize(array $row):array{foreach(['id','parent_shift_id']as$key)if(isset($row[$key]))$row[$key]=(int)$row[$key];$row['informed_asn']=(bool)$row['informed_asn'];foreach(['starts_at','ends_at','mediated_at','hycare_at','paper_at','expires_at']as$key)if($row[$key] instanceof \DateTimeInterface)$row[$key]=$row[$key]->format(DATE_ATOM);return$row;}
    private function dbValue(string $key,mixed $value):mixed{if($value!==null&&in_array($key,['starts_at','ends_at','mediated_at','hycare_at','paper_at','expires_at','contacted_at','occurred_at'],true))return new DateTimeImmutable((string)$value);return$value;}
    private function dbType(string $key):int|string|null{return match(true){in_array($key,['shift_id','parent_shift_id'],true)=>IQueryBuilder::PARAM_INT,$key==='informed_asn'=>IQueryBuilder::PARAM_BOOL,in_array($key,['starts_at','ends_at','mediated_at','hycare_at','paper_at','expires_at','contacted_at','occurred_at'],true)=>IQueryBuilder::PARAM_DATETIME_IMMUTABLE,default=>null};}
}

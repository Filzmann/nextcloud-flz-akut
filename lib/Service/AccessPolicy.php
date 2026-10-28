<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

final class AccessPolicy {
    public function bureauKeys(array $actorGroups, array $roles, array $areas): array {
        $allowedRoles=[];
        foreach(['office','eb'] as $key)if(isset($roles[$key]['groupId']))$allowedRoles[]=$roles[$key]['groupId'];
        if(array_intersect($actorGroups,$allowedRoles)===[])return[];
        $keys=[];foreach($areas as $key=>$area)if(in_array($area['groupId']??null,$actorGroups,true))$keys[]=(string)$key;
        sort($keys);return$keys;
    }
}

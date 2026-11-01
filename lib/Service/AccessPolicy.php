<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

final class AccessPolicy {
    public function bureauKeys(array $actorGroups, array $permissionRoleGroups, array $areas): array {
        if(array_intersect($actorGroups,$permissionRoleGroups)===[])return[];
        $keys=[];foreach($areas as $key=>$area)if(in_array($area['groupId']??null,$actorGroups,true))$keys[]=(string)$key;
        sort($keys);return$keys;
    }
}

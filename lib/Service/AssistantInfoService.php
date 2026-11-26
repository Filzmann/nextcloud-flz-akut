<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\Config\IUserConfig;

/**
 * AS-Info (Verfügbarkeit: nur Tag / nur Nacht / nur Früh + Freitext) und interner
 * Kommentar werden direkt beim AS-Nutzer in der nativen Nextcloud-UserConfig gespeichert.
 */
final class AssistantInfoService {
    public const APP_ID = 'flz_akut';
    public const KEY_INFO = 'as_info';
    public const KEY_COMMENT = 'as_comment';
    public const TAGS = ['tag', 'nacht', 'frueh'];
    public const MAX_NOTE = 1000;
    public const MAX_COMMENT = 2000;

    public function __construct(private IUserConfig $config) {}

    /** @return array{tags:list<string>,note:string} */
    public function info(string $uid): array {
        $raw = $this->config->getValueString($uid, self::APP_ID, self::KEY_INFO, '');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return ['tags' => [], 'note' => ''];
        $tags = array_values(array_intersect(is_array($decoded['tags'] ?? null) ? $decoded['tags'] : [], self::TAGS));
        $note = is_string($decoded['note'] ?? null) ? $decoded['note'] : '';
        return ['tags' => $tags, 'note' => $note];
    }

    /** @param list<string> $tags @return array{tags:list<string>,note:string} */
    public function saveInfo(string $uid, array $tags, string $note): array {
        $info = ['tags' => array_values(array_intersect($tags, self::TAGS)), 'note' => substr($note, 0, self::MAX_NOTE)];
        $this->config->setValueString($uid, self::APP_ID, self::KEY_INFO, (string)json_encode($info, JSON_UNESCAPED_UNICODE));
        return $info;
    }

    public function comment(string $uid): string {
        return $this->config->getValueString($uid, self::APP_ID, self::KEY_COMMENT, '');
    }

    public function saveComment(string $uid, string $comment): string {
        $comment = substr($comment, 0, self::MAX_COMMENT);
        $this->config->setValueString($uid, self::APP_ID, self::KEY_COMMENT, $comment);
        return $comment;
    }
}

<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\Config\IUserConfig;

/**
 * Persönliches, eineindeutiges Kürzel des Mitarbeiters (Eintrag in den Profileinstellungen),
 * gespeichert in der nativen Nextcloud-UserConfig. Maximal 4 Zeichen (Buchstaben groß/klein
 * und Punkt, mindestens ein Buchstabe). Vorbelegung: erste zwei Buchstaben des Nachnamens,
 * sonst Initialen (1. Buchstabe Vorname + Nachname). Eindeutigkeit über den normalisierten
 * Index-Schlüssel (kleingeschrieben) per getUsersForUserValue.
 */
final class MaKuerzelService {
    public const APP_ID = 'flz_akut';
    public const KEY = 'ma_kuerzel';
    public const KEY_NORM = 'ma_kuerzel_norm';
    public const MAX = 4;

    public function __construct(private IUserConfig $config) {}

    public function valid(string $kuerzel): bool {
        return preg_match('/^(?=.*\p{L})[\p{L}.]{1,' . self::MAX . '}$/u', $kuerzel) === 1;
    }

    public function normalize(string $kuerzel): string {
        return strtolower($kuerzel);
    }

    /** @return list<string> UIDs anderer Mitarbeiter mit diesem Kürzel (case-insensitiv) */
    public function takenBy(string $kuerzel, string $exceptUid): array {
        return array_values(array_filter(
            $this->config->getUsersForUserValue(self::APP_ID, self::KEY_NORM, $this->normalize($kuerzel)),
            static fn(string $uid): bool => $uid !== $exceptUid
        ));
    }

    /** Default-Ableitung aus dem Anzeigenamen: erste zwei Buchstaben des Nachnamens, sonst Initialen. */
    public function derive(string $displayName): string {
        $words = preg_split('/\s+/u', trim($displayName)) ?: [];
        $words = array_values(array_filter($words, static fn(string $w): bool => $w !== ''));
        if ($words === []) return '';
        $last = (string)end($words);
        if (strlen($last) >= 2) return strtoupper(substr($last, 0, 2));
        $first = (string)$words[0];
        return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
    }

    public function stored(string $uid): string {
        return trim($this->config->getValueString($uid, self::APP_ID, self::KEY, ''));
    }

    /** Gespeichertes Kürzel gewinnt; sonst Vorbelegung aus dem Anzeigenamen. */
    public function effective(string $uid, string $displayName): string {
        $stored = $this->stored($uid);
        return $stored !== '' ? $stored : $this->derive($displayName);
    }

    /**
     * @return list<string> bis zu 5 freie, gültige Kürzel-Vorschläge
     */
    public function suggest(string $base, string $displayName, string $exceptUid): array {
        $candidates = [];
        $base = trim($base);
        if ($base !== '') {
            for ($i = 1; $i <= 9; $i++) $candidates[] = $base . $i;
        }
        $words = preg_split('/\s+/u', trim($displayName)) ?: [];
        $words = array_values(array_filter($words, static fn(string $w): bool => $w !== ''));
        if (count($words) >= 2) {
            $initials = strtoupper(substr((string)$words[0], 0, 1)) . '.' . strtoupper(substr((string)end($words), 0, 1));
            $candidates[] = $initials;
            $candidates[] = strtoupper(substr((string)$words[0], 0, 1) . substr((string)end($words), 0, 1)) . '1';
            $candidates[] = $initials . '1';
        }
        $out = [];
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '' || !$this->valid($candidate) || $this->normalize($candidate) === $this->normalize($base)) continue;
            if ($this->takenBy($candidate, $exceptUid) !== [] || in_array($candidate, $out, true)) continue;
            $out[] = $candidate;
            if (count($out) >= 5) break;
        }
        return $out;
    }

    /**
     * @throws DuplicateKuerzelException wenn das Kürzel bereits einem anderen Mitarbeiter gehört
     */
    public function save(string $uid, string $kuerzel, string $displayName = ''): string {
        $kuerzel = trim($kuerzel);
        if ($kuerzel !== '' && !$this->valid($kuerzel)) {
            throw new \InvalidArgumentException('Das Kürzel darf höchstens ' . self::MAX . ' Zeichen (Buchstaben und Punkt, mindestens ein Buchstabe) enthalten.');
        }
        if ($kuerzel !== '' && $this->takenBy($kuerzel, $uid) !== []) {
            throw new DuplicateKuerzelException($this->suggest($kuerzel, $displayName, $uid));
        }
        $this->config->setValueString($uid, self::APP_ID, self::KEY, $kuerzel);
        $this->config->setValueString($uid, self::APP_ID, self::KEY_NORM, $kuerzel === '' ? '' : $this->normalize($kuerzel));
        return $kuerzel;
    }
}

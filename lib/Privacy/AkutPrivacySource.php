<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Privacy;

interface AkutPrivacySource { public function forSubject(string $uid,int $limit):array; }

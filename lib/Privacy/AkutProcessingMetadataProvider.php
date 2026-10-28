<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Privacy;

use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataCatalog;
use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataProvider;
use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataProviderDescriptor;

final class AkutProcessingMetadataProvider implements ProcessingMetadataProvider {
    public function descriptor():ProcessingMetadataProviderDescriptor{return new ProcessingMetadataProviderDescriptor('flz_akut','Akutschichtvermittlung','1.0');}
    public function catalog():ProcessingMetadataCatalog{$payload=json_decode((string)file_get_contents(dirname(__DIR__,2).'/resources/privacy-processing.json'),true,512,JSON_THROW_ON_ERROR);return ProcessingMetadataCatalog::fromArray($payload);}
}

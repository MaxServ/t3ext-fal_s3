<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\SourceScheme;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use TYPO3\CMS\Core\Type\Map;

$s3PublicBaseUrls = (static function (): array {
    $uriValues = [];

    foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'] ?? [] as $config) {
        if (!empty(trim($config['publicBaseUrl'] ?? ''))) {
            $uriValues[] = new UriValue($config['publicBaseUrl']);
        }
    }

    return $uriValues;
})();

$externalMediaCollection = new MutationCollection(
    new Mutation(
        MutationMode::Extend,
        Directive::ImgSrc,
        ...$s3PublicBaseUrls
    ),
    new Mutation(
        MutationMode::Extend,
        Directive::ConnectSrc,
        ...$s3PublicBaseUrls
    ),
    new Mutation(
        MutationMode::Extend,
        Directive::MediaSrc,
        ...$s3PublicBaseUrls
    )
);

return Map::fromEntries(
    [Scope::backend(), $externalMediaCollection],
    [Scope::frontend(), $externalMediaCollection],
);

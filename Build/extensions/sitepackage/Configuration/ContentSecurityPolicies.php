<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\SourceKeyword;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use TYPO3\CMS\Core\Type\Map;

$backendMediaCollection = new MutationCollection(
    new Mutation(
        MutationMode::Set,
        Directive::DefaultSrc,
        SourceKeyword::self
    ),
    new Mutation(
        MutationMode::Set,
        Directive::ScriptSrc,
        SourceKeyword::self,
        SourceKeyword::strictDynamic
    ),
    new Mutation(
        MutationMode::Set,
        Directive::ScriptSrcElem,
        SourceKeyword::self,
        SourceKeyword::strictDynamic
    ),
    new Mutation(
        MutationMode::Extend,
        Directive::ImgSrc,
        new UriValue($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations']['contentStorage']['publicBaseUrl']),
    )
);

return Map::fromEntries(
    [Scope::backend(), $backendMediaCollection],
);

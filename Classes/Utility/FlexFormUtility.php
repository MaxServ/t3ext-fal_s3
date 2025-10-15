<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Utility;

class FlexFormUtility
{
    public function getStorageConfigurations(array &$parameters): void
    {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'] ?? null)) {
            return;
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'] as $configurationKey => $storageConfiguration) {
            if (empty($storageConfiguration['title'] ?? '')) {
                continue;
            }
            $parameters['items'][] = [$storageConfiguration['title'], $configurationKey];
        }
    }
}

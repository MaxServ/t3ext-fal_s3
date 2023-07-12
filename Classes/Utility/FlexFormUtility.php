<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Utility;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Class FlexFormUtility
 */
class FlexFormUtility
{
    /**
     * @param array $parameters
     */
    public function getStorageConfigurations(array &$parameters): void
    {
        if (
            isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'])
        ) {
            // phpcs:ignore
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'] as $configurationKey => $storageConfiguration) {
                if (
                    is_array($storageConfiguration)
                    && array_key_exists('title', $storageConfiguration)
                    && !empty($storageConfiguration['title'])
                ) {
                    $parameters['items'][] = [$storageConfiguration['title'], $configurationKey];
                }
            }
        }
    }
}

<?php
namespace MaxServ\FalS3\Configuration;

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

use MaxServ\Deployer;

/**
 * Class ConfigurationConverter
 *
 * @package MaxServ\FalS3\Configuration
 */
class ConfigurationConverter implements Deployer\Configuration\Converter\ConfigurationConverterInterface
{

    /**
     * Convert/translate an array with configuration directives to the TYPO3_CONF_VARS equivalent
     *
     * @param array $processedConfiguration
     * @return array
     */
    public function convertToLocalConfigurationFormat($processedConfiguration)
    {
        return array(
            'EXTCONF' => array(
                'fal_s3' => array(
                    'storageConfigurations' => $processedConfiguration
                )
            )
        );
    }
}

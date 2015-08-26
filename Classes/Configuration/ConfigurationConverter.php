<?php
namespace MaxServ\FalS3\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Arno Schoon <arno@maxserv.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use MaxServ\Deployer;

/**
 * Class ConfigurationConverter
 *
 * @package MaxServ\FalS3\Configuration
 */
class ConfigurationConverter implements Deployer\Configuration\Converter\ConfigurationConverterInterface {

	/**
	 * Convert/translate an array with configuration directives to the TYPO3_CONF_VARS equivalent
	 *
	 * @param array $processedConfiguration
	 * @return array
	 */
	public function convertToLocalConfigurationFormat($processedConfiguration) {
		return array(
			'EXTCONF' => array(
				'fal_s3' => array(
					'storageConfigurations' => $processedConfiguration
				)
			)
		);
	}
}
<?php
namespace MaxServ\FalS3\Utility;

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

/**
 * Class FlexFormUtility
 *
 * @package MaxServ\FalS3\Utility
 */
class FlexFormUtility {

	/**
	 * @param array $parameters
	 * @return string
	 */
	public function getStorageConfigurations(array $parameters) {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']) && array_key_exists('fal_s3', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'])
			&& is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']) && array_key_exists('storageConfigurations', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3'])
			&& is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'])
		) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'] as $configurationKey => $storageConfiguration) {
				if (is_array($storageConfiguration) && array_key_exists('title', $storageConfiguration) && !empty($storageConfiguration['title'])) {
					$parameters['items'][] = array($storageConfiguration['title'], $configurationKey);
				}
			}
		}
	}

}
<?php
namespace MaxServ\FalS3\MetaData;

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

use MaxServ\FalS3;
use TYPO3\CMS\Core\Resource;
use TYPO3;

/**
 * Class ImageDimensionsExtractor
 *
 * @package MaxServ\FalS3\MetaData
 */
class ImageDimensionsExtractor implements TYPO3\CMS\Core\Resource\Index\ExtractorInterface {

	/**
	 * Returns an array of supported file types;
	 * An empty array indicates all filetypes
	 *
	 * @return array
	 */
	public function getFileTypeRestrictions() {
		return array(TYPO3\CMS\Core\Resource\File::FILETYPE_IMAGE);
	}

	/**
	 * Get all supported DriverClasses
	 *
	 * Since some extractors may only work for local files, and other extractors
	 * are especially made for grabbing data from remote.
	 *
	 * Returns array of string with driver names of Drivers which are supported,
	 * If the driver did not register a name, it's the classname.
	 * empty array indicates no restrictions
	 *
	 * @return array
	 */
	public function getDriverRestrictions() {
		return array(FalS3\Driver\AmazonS3Driver::DRIVER_KEY);
	}

	/**
	 * Returns the data priority of the extraction Service.
	 * Defines the precedence of Data if several extractors
	 * extracted the same property.
	 *
	 * Should be between 1 and 100, 100 is more important than 1
	 *
	 * @return int
	 */
	public function getPriority() {
		return 50;
	}

	/**
	 * Returns the execution priority of the extraction Service
	 * Should be between 1 and 100, 100 means runs as first service, 1 runs at last service
	 *
	 * @return int
	 */
	public function getExecutionPriority() {
		return 50;
	}

	/**
	 * Checks if the given file can be processed by this Extractor
	 *
	 * @param Resource\File $file
	 * @return bool
	 */
	public function canProcess(Resource\File $file) {
		return $file->getType() === Resource\File::FILETYPE_IMAGE
			&& $file->getStorage()->getDriverType() === FalS3\Driver\AmazonS3Driver::DRIVER_KEY;
	}

	/**
	 * The actual processing TASK
	 *
	 * Should return an array with database properties for sys_file_metadata to write
	 *
	 * @param Resource\File $file
	 * @param array $previousExtractedData optional, contains the array of already extracted data
	 * @return array
	 */
	public function extractMetaData(Resource\File $file, array $previousExtractedData = array()) {
		if (is_array($previousExtractedData)
			&& !(array_key_exists('width', $previousExtractedData) && !empty($previousExtractedData['width']))
		) {
			/* @var $graphicalFunctionsObject \TYPO3\CMS\Core\Imaging\GraphicalFunctions */
			$graphicalFunctionsObject = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
				'TYPO3\\CMS\\Core\\Imaging\\GraphicalFunctions'
			);

			$temporaryFilePath = $file->getForLocalProcessing();

			$imageDimensions = $graphicalFunctionsObject->getImageDimensions($temporaryFilePath);

			if (is_array($imageDimensions) && array_key_exists(0, $imageDimensions) && array_key_exists(1, $imageDimensions)) {
				$previousExtractedData['width'] = $imageDimensions[0];
				$previousExtractedData['height'] = $imageDimensions[1];
			}

			unlink($temporaryFilePath);
		}

		return $previousExtractedData;
	}
}
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
use TYPO3;

/**
 * Class RecordMonitor
 *
 * @package MaxServ\FalS3\MetaData
 */
class RecordMonitor {

	/**
	 * Update the dimension of an image directly after creation
	 *
	 * @param array $data
	 * @return void|null
	 */
	public function recordUpdatedOrCreated(array $data) {
		$metaDataRepository = NULL;
		$storage = NULL;

		if ($data['type'] === TYPO3\CMS\Core\Resource\File::FILETYPE_IMAGE) {
			/* @var $storage \TYPO3\CMS\Core\Resource\ResourceStorage */
			$storage = TYPO3\CMS\Core\Resource\ResourceFactory::getInstance()->getStorageObject($data['storage']);

			if ($storage->getDriverType() === FalS3\Driver\AmazonS3Driver::DRIVER_KEY) {
				$storage->setEvaluatePermissions(FALSE);

				$metaDataRepository = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
					'TYPO3\\CMS\\Core\\Resource\\Index\\MetaDataRepository'
				);
			}
		}

		if ($metaDataRepository instanceof TYPO3\CMS\Core\Resource\Index\MetaDataRepository) {
			$file = $storage->getFile($data['identifier']);
			$record = $metaDataRepository->findByFileUid($data['uid']);

				// directly after creation of a file the dimensions are not set,
				// but must be added for the system to function properly
			if (is_array($record) && !array_key_exists('width', $record) && $file instanceof TYPO3\CMS\Core\Resource\File) {
				/* @var $graphicalFunctionsObject \TYPO3\CMS\Core\Imaging\GraphicalFunctions */
				$graphicalFunctionsObject = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
					'TYPO3\\CMS\\Core\\Imaging\\GraphicalFunctions'
				);

				$temporaryFilePath = $file->getForLocalProcessing(FALSE);

				$imageDimensions = $graphicalFunctionsObject->getImageDimensions($temporaryFilePath);

				if (is_array($imageDimensions) && array_key_exists(0, $imageDimensions) && array_key_exists(1, $imageDimensions)) {
					$record['width'] = $imageDimensions[0];
					$record['height'] = $imageDimensions[1];

					$metaDataRepository->update($file->getUid(), $record);
				}

				unlink($temporaryFilePath);
			}
		}
	}

}
<?php
namespace MaxServ\FalS3\Driver;

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

use Aws;
use GuzzleHttp;
use TYPO3;

/**
 * Class AmazonS3Driver
 *
 * @package MaxServ\FalS3\Driver
 */
class AmazonS3Driver extends TYPO3\CMS\Core\Resource\Driver\AbstractHierarchicalFilesystemDriver {

	/**
	 * @var string
	 */
	const DRIVER_KEY = 'MaxServ.FalS3';

	/**
	 * @var Aws\S3\S3Client
	 */
	protected $s3Client;

	/**
	 * @var \TYPO3\CMS\Core\Resource\ResourceStorage
	 */
	protected $storage;

	/**
	 * Initialize this driver and expose the capabilities for the repository to use
	 * 
	 * @param array $configuration
	 */
	public function __construct(array $configuration = array()) {
		parent::__construct($configuration);

		$this->capabilities = TYPO3\CMS\Core\Resource\ResourceStorage::CAPABILITY_BROWSABLE |
			TYPO3\CMS\Core\Resource\ResourceStorage::CAPABILITY_PUBLIC |
			TYPO3\CMS\Core\Resource\ResourceStorage::CAPABILITY_WRITABLE;
	}

	/**
	 * Processes the configuration for this driver.
	 *
	 * @return void
	 * @throws TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException
	 */
	public function processConfiguration() {
			// check if a configurationKey is set in the configuration of this storage
			// next check if the key references to a storageConfiguration for this driver
			// if this storageConfiguration contains the mandatory key, secret and region properties
			// merge the configuration with the local array
		if (is_array($this->configuration) && array_key_exists('configurationKey', $this->configuration)) {
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'])
				&& array_key_exists('fal_s3', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'])
				&& is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3'])
				&& array_key_exists('storageConfigurations', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3'])
				&& is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'])
				&& array_key_exists($this->configuration['configurationKey'], $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'])
				&& is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$this->configuration['configurationKey']])
				&& array_key_exists('key', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$this->configuration['configurationKey']])
				&& !empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$this->configuration['configurationKey']]['key'])
				&& array_key_exists('region', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$this->configuration['configurationKey']])
				&& !empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$this->configuration['configurationKey']]['region'])
				&& array_key_exists('secret', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$this->configuration['configurationKey']])
				&& !empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$this->configuration['configurationKey']]['secret'])
			) {
				TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule(
					$this->configuration,
					$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$this->configuration['configurationKey']]
				);
			} else {
					// throw an InvalidConfigurationException to trigger the storage to mark itself as offline
				throw new TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException(
					'Missing configuration for "' . $this->configuration['configurationKey'] . '"',
					1438785908
				);
			}
		} else {
				// throw an InvalidConfigurationException to trigger the storage to mark itself as offline
			throw new TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException(
				'Unable to resolve a configurationKey for this driver instance',
				1438785477
			);
		}
	}

	/**
	 * Initializes this object. This is called by the storage after the driver
	 * has been attached.
	 *
	 * @return void
	 */
	public function initialize() {
		if (is_array($this->configuration) && array_key_exists('region', $this->configuration)
			&& array_key_exists('key', $this->configuration) && array_key_exists('secret', $this->configuration)
			) {
			$this->s3Client = new Aws\S3\S3Client(array(
				'version' => '2006-03-01',
				'region' => $this->configuration['region'],
				'credentials' => array(
					'key' => $this->configuration['key'],
					'secret' => $this->configuration['secret']
				)
			));

				// strip the s3 protocol prefix from the bucket name
			if (strpos($this->configuration['bucket'], 's3://') === 0) {
				$this->configuration['bucket'] = substr($this->configuration['bucket'], 5);
			}

				// to prevent collisions between multiple S3 drivers using a stream_wrapper use a unique protocol key
			$this->configuration['stream_protocol'] = strtolower(self::DRIVER_KEY . '.' . $this->configuration['configurationKey']);

			Aws\S3\StreamWrapper::register($this->s3Client, $this->configuration['stream_protocol']);
		}
	}

	/**
	 * Merges the capabilities merged by the user at the storage
	 * configuration into the actual capabilities of the driver
	 * and returns the result.
	 *
	 * @param int $capabilities
	 * @return int
	 */
	public function mergeConfigurationCapabilities($capabilities) {
		$this->capabilities &= $capabilities;
		return $this->capabilities;
	}

	/**
	 * Returns the identifier of the root level folder of the storage.
	 *
	 * @return string
	 */
	public function getRootLevelFolder() {
		return '/';
	}

	/**
	 * Returns the identifier of the default folder new files should be put into.
	 *
	 * @return string
	 */
	public function getDefaultFolder() {
		return $this->getRootLevelFolder();
	}

	/**
	 * Returns the public URL to a file.
	 * Either fully qualified URL or relative to PATH_site (rawurlencoded).
	 *
	 * @param string $identifier
	 * @return string
	 */
	public function getPublicUrl($identifier) {
		$identifier = $this->canonicalizeAndCheckFileIdentifier($identifier);

		$publicUrl = '';

		if (is_array($this->configuration) && array_key_exists('bucket', $this->configuration) && !empty($this->configuration['bucket'])) {
			$uriParts = explode('/', $identifier);
			$uriParts = array_map('rawurlencode', $uriParts);

			$publicUrl = 'https://' .
				$this->configuration['bucket'] .
				'.s3.amazonaws.com' .
				implode('/', $uriParts);
		}

		return $publicUrl;
	}

	/**
	 * Creates a folder, within a parent folder.
	 * If no parent folder is given, a root level folder will be created
	 *
	 * @param string $newFolderName
	 * @param string $parentFolderIdentifier
	 * @param bool $recursive
	 * @return string the Identifier of the new folder
	 */
	public function createFolder($newFolderName, $parentFolderIdentifier = '', $recursive = FALSE) {
		$parentFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($parentFolderIdentifier);
		$newFolderName = trim($newFolderName, '/');

		if ($recursive === FALSE) {
			$newFolderName = $this->sanitizeFileName($newFolderName);
			$identifier = $parentFolderIdentifier . $newFolderName . '/';
		} else {
			$parts = TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode('/', $newFolderName);
			$parts = array_map(array($this, 'sanitizeFileName'), $parts);
			$newFolderName = implode('/', $parts);
			$identifier = $parentFolderIdentifier . $newFolderName . '/';
		}
		$path = $this->getStreamWrapperPath($identifier);

		mkdir($path, $GLOBALS['TYPO3_CONF_VARS']['BE']['folderCreateMask'], $recursive);

		return $identifier;
	}

	/**
	 * Renames a folder in this storage.
	 *
	 * @param string $folderIdentifier
	 * @param string $newName
	 * @return array A map of old to new file identifiers of all affected resources
	 */
	public function renameFolder($folderIdentifier, $newName) {
		$folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
		$newName = $this->sanitizeFileName($newName);
		$newName = trim($newName, '/');

		$parentFolderName = dirname($folderIdentifier);

		if ($parentFolderName === '.') {
			$parentFolderName = '';
		} else {
			$parentFolderName .= '/';
		}

		$parentFolderName = $this->canonicalizeAndCheckFolderIdentifier($parentFolderName);

		$newIdentifier = $parentFolderName . $newName . '/';

		return $this->moveFolderWithinStorage($folderIdentifier, $newIdentifier, '');
	}

	/**
	 * Removes a folder in filesystem.
	 *
	 * @param string $folderIdentifier
	 * @param bool $deleteRecursively
	 * @return bool
	 */
	public function deleteFolder($folderIdentifier, $deleteRecursively = FALSE) {
		$folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
		$path = $this->getStreamWrapperPath($folderIdentifier);

		return unlink($path);
	}

	/**
	 * Checks if a file exists.
	 *
	 * @param string $fileIdentifier
	 * @return bool
	 */
	public function fileExists($fileIdentifier) {
		$fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);

		$path = $this->getStreamWrapperPath($fileIdentifier);
		return is_file($path);
	}

	/**
	 * Checks if a folder exists.
	 *
	 * @param string $folderIdentifier
	 * @return bool
	 */
	public function folderExists($folderIdentifier) {
		$folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);

		$path = $this->getStreamWrapperPath($folderIdentifier);

			// it seems like is_dir does not return the expected result if the folder is created outside this system
			// implement a fallback to determine if a path "looks" like a folder and issuing a file_exists
		return is_dir($path) || (substr($path, -1) === '/' && file_exists($path));
	}

	/**
	 * Checks if a folder contains files and (if supported) other folders.
	 *
	 * @param string $folderIdentifier
	 * @return bool TRUE if there are no files and folders within $folder
	 */
	public function isFolderEmpty($folderIdentifier) {
		return $this->countFilesInFolder($folderIdentifier) === 0 && $this->countFoldersInFolder($folderIdentifier) === 0;
	}

	/**
	 * Adds a file from the local server hard disk to a given path in TYPO3s
	 * virtual file system. This assumes that the local file exists, so no
	 * further check is done here! After a successful the original file must
	 * not exist anymore.
	 *
	 * @param string $localFilePath (within PATH_site)
	 * @param string $targetFolderIdentifier
	 * @param string $newFileName optional, if not given original name is used
	 * @param bool $removeOriginal if set the original file will be removed
	 *                                after successful operation
	 * @return string the identifier of the new file
	 */
	public function addFile($localFilePath, $targetFolderIdentifier, $newFileName = '', $removeOriginal = TRUE) {
		// TODO: Implement addFile() method.
	}

	/**
	 * Creates a new (empty) file and returns the identifier.
	 *
	 * @param string $fileName
	 * @param string $parentFolderIdentifier
	 * @return string
	 */
	public function createFile($fileName, $parentFolderIdentifier) {
		// TODO: Implement createFile() method.
	}

	/**
	 * Copies a file *within* the current storage.
	 * Note that this is only about an inner storage copy action,
	 * where a file is just copied to another folder in the same storage.
	 *
	 * @param string $fileIdentifier
	 * @param string $targetFolderIdentifier
	 * @param string $fileName
	 * @return string the Identifier of the new file
	 */
	public function copyFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $fileName) {
		$fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
		$targetFileIdentifier = $this->canonicalizeAndCheckFileIdentifier($targetFolderIdentifier . $fileName);

		$sourcePath = $this->getStreamWrapperPath($fileIdentifier);
		$targetPath = $this->getStreamWrapperPath($targetFileIdentifier);

		copy($sourcePath, $targetPath);

		return $targetFileIdentifier;
	}

	/**
	 * Renames a file in this storage.
	 *
	 * @param string $fileIdentifier
	 * @param string $newName The target path (including the file name!)
	 * @return string The identifier of the file after renaming
	 */
	public function renameFile($fileIdentifier, $newName) {
		$fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
		$newName = $this->sanitizeFileName($newName);
		$newName = trim($newName, '/');

		$parentFolderName = dirname($fileIdentifier);

		if ($parentFolderName === '.') {
			$parentFolderName = '';
		} else {
			$parentFolderName .= '/';
		}

		$parentFolderName = $this->canonicalizeAndCheckFolderIdentifier($parentFolderName);

		$newIdentifier = $parentFolderName . $newName;

		$oldPath = $this->getStreamWrapperPath($fileIdentifier);
		$newPath = $this->getStreamWrapperPath($newIdentifier);

		rename($oldPath, $newPath);

		return $newIdentifier;
	}

	/**
	 * Replaces a file with file in local file system.
	 *
	 * @param string $fileIdentifier
	 * @param string $localFilePath
	 * @return bool TRUE if the operation succeeded
	 */
	public function replaceFile($fileIdentifier, $localFilePath) {
		// TODO: Implement replaceFile() method.
	}

	/**
	 * Removes a file from the filesystem. This does not check if the file is
	 * still used or if it is a bad idea to delete it for some other reason
	 * this has to be taken care of in the upper layers (e.g. the Storage)!
	 *
	 * @param string $fileIdentifier
	 * @return bool TRUE if deleting the file succeeded
	 */
	public function deleteFile($fileIdentifier) {
		$fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
		$path = $this->getStreamWrapperPath($fileIdentifier);

		return unlink($path);
	}

	/**
	 * Creates a hash for a file.
	 *
	 * @param string $fileIdentifier
	 * @param string $hashAlgorithm The hash algorithm to use
	 * @return string
	 */
	public function hash($fileIdentifier, $hashAlgorithm) {
		$fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);

		return sha1($fileIdentifier);
	}

	/**
	 * Moves a file *within* the current storage.
	 * Note that this is only about an inner-storage move action,
	 * where a file is just moved to another folder in the same storage.
	 *
	 * @param string $fileIdentifier
	 * @param string $targetFolderIdentifier
	 * @param string $newFileName
	 * @return string
	 */
	public function moveFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $newFileName) {
		$fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
		$targetFileIdentifier = $this->canonicalizeAndCheckFileIdentifier($targetFolderIdentifier . $newFileName);

		$sourcePath = $this->getStreamWrapperPath($fileIdentifier);
		$targetPath = $this->getStreamWrapperPath($targetFileIdentifier);

		rename($sourcePath, $targetPath);

		return $targetFileIdentifier;
	}

	/**
	 * Folder equivalent to moveFileWithinStorage().
	 *
	 * @param string $sourceFolderIdentifier
	 * @param string $targetFolderIdentifier
	 * @param string $newFolderName
	 * @return array All files which are affected, map of old => new file identifiers
	 */
	public function moveFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName) {
		$sourceFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($sourceFolderIdentifier);
		$targetFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($targetFolderIdentifier . $newFolderName);

		$oldPath = $this->getStreamWrapperPath($sourceFolderIdentifier);
		$newPath = $this->getStreamWrapperPath($targetFolderIdentifier);

		$renamedEntries = array_flip($this->resolveFolderEntries($sourceFolderIdentifier, TRUE));

		foreach ($renamedEntries as $oldEntryIdentifier => $newEntryIdentifier) {
			$newEntryIdentifier = str_replace(
				$sourceFolderIdentifier,
				$targetFolderIdentifier,
				$oldEntryIdentifier
			);

			$oldEntryPath = $this->getStreamWrapperPath($oldEntryIdentifier);
			$newEntryPath = $this->getStreamWrapperPath($newEntryIdentifier);

			rename($oldEntryPath, $newEntryPath);

			$renamedEntries[$oldEntryIdentifier] = $newEntryIdentifier;
		}

		rename($oldPath, $newPath);

		$renamedEntries[$sourceFolderIdentifier] = $targetFolderIdentifier;

		return $renamedEntries;
	}

	/**
	 * Folder equivalent to copyFileWithinStorage().
	 *
	 * @param string $sourceFolderIdentifier
	 * @param string $targetFolderIdentifier
	 * @param string $newFolderName
	 * @return bool
	 */
	public function copyFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName) {
		// TODO: Implement copyFolderWithinStorage() method.
	}

	/**
	 * Returns the contents of a file. Beware that this requires to load the
	 * complete file into memory and also may require fetching the file from an
	 * external location. So this might be an expensive operation (both in terms
	 * of processing resources and money) for large files.
	 *
	 * @param string $fileIdentifier
	 * @return string The file contents
	 */
	public function getFileContents($fileIdentifier) {
		// TODO: Implement getFileContents() method.
	}

	/**
	 * Sets the contents of a file to the specified value.
	 *
	 * @param string $fileIdentifier
	 * @param string $contents
	 * @return int The number of bytes written to the file
	 */
	public function setFileContents($fileIdentifier, $contents) {
		// TODO: Implement setFileContents() method.
	}

	/**
	 * Checks if a file inside a folder exists
	 *
	 * @param string $fileName
	 * @param string $folderIdentifier
	 * @return bool
	 */
	public function fileExistsInFolder($fileName, $folderIdentifier) {
		$folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
		$fileName = $this->canonicalizeAndCheckFileIdentifier($fileName);

		return $this->fileExists($folderIdentifier . $fileName);
	}

	/**
	 * Checks if a folder inside a folder exists.
	 *
	 * @param string $folderName
	 * @param string $folderIdentifier
	 * @return bool
	 */
	public function folderExistsInFolder($folderName, $folderIdentifier) {
		$folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
		$folderName = $this->canonicalizeAndCheckFolderIdentifier($folderName);

		return $this->folderExists($folderIdentifier . $folderName);
	}

	/**
	 * Returns a path to a local copy of a file for processing it. When changing the
	 * file, you have to take care of replacing the current version yourself!
	 *
	 * @param string $fileIdentifier
	 * @param bool $writable Set this to FALSE if you only need the file for read
	 *                       operations. This might speed up things, e.g. by using
	 *                       a cached local version. Never modify the file if you
	 *                       have set this flag!
	 * @return string The path to the file on the local disk
	 */
	public function getFileForLocalProcessing($fileIdentifier, $writable = TRUE) {
		// TODO: Implement getFileForLocalProcessing() method.
	}

	/**
	 * Returns the permissions of a file/folder as an array
	 * (keys r, w) of boolean flags
	 *
	 * @param string $identifier
	 * @return array
	 */
	public function getPermissions($identifier) {
		$identifier = $this->canonicalizeAndCheckFolderIdentifier($identifier);

		$path = $this->getStreamWrapperPath(rtrim($identifier, '/'));

		$permissions = array(
			'r' => is_readable($path),
			'w' => is_writable($path)
		);

		return $permissions;
	}

	/**
	 * Directly output the contents of the file to the output
	 * buffer. Should not take care of header files or flushing
	 * buffer before. Will be taken care of by the Storage.
	 *
	 * @param string $identifier
	 * @return void
	 */
	public function dumpFileContents($identifier) {
		// TODO: Implement dumpFileContents() method.
	}

	/**
	 * Checks if a given identifier is within a container, e.g. if
	 * a file or folder is within another folder.
	 * This can e.g. be used to check for web-mounts.
	 *
	 * Hint: this also needs to return TRUE if the given identifier
	 * matches the container identifier to allow access to the root
	 * folder of a filemount.
	 *
	 * @param string $folderIdentifier
	 * @param string $identifier identifier to be checked against $folderIdentifier
	 * @return bool TRUE if $content is within or matches $folderIdentifier
	 */
	public function isWithin($folderIdentifier, $identifier) {
		$folderIdentifier = $this->canonicalizeAndCheckFileIdentifier($folderIdentifier);
		$identifier = $this->canonicalizeAndCheckFileIdentifier($identifier);
		return $folderIdentifier === $identifier
			|| ($folderIdentifier !== $identifier && $folderIdentifier !== '' && strpos($identifier, $folderIdentifier) === 0);
	}

	/**
	 * Returns information about a file.
	 *
	 * @param string $fileIdentifier
	 * @param array $propertiesToExtract Array of properties which are be extracted
	 *                                   If empty all will be extracted
	 * @return array
	 */
	public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = array()) {
		$fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);

		$path = $this->getStreamWrapperPath($fileIdentifier);

		return array(
			'name' => basename($fileIdentifier),
			'identifier' => $fileIdentifier,
			'ctime' => filectime($path),
			'mtime' => filemtime($path),
			'mimetype' => GuzzleHttp\Psr7\mimetype_from_filename($path),
			'size' => (int) filesize($path),
			'identifier_hash' => $this->hashIdentifier($fileIdentifier),
			'folder_hash' => $this->hashIdentifier(TYPO3\CMS\Core\Utility\PathUtility::dirname($fileIdentifier)),
			'storage' => $this->storageUid
		);
	}

	/**
	 * Returns information about a file.
	 *
	 * @param string $folderIdentifier
	 * @return array
	 */
	public function getFolderInfoByIdentifier($folderIdentifier) {
		$folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);

		return array(
			'identifier' => $folderIdentifier,
			'name' => basename($folderIdentifier),
			'storage' => $this->storageUid
		);
	}

	/**
	 * Returns a list of files inside the specified path
	 *
	 * @param string $folderIdentifier
	 * @param int $start
	 * @param int $numberOfItems
	 * @param bool $recursive
	 * @param array $filenameFilterCallbacks callbacks for filtering the items
	 * @param string $sort Property name used to sort the items.
	 *                     Among them may be: '' (empty, no sorting), name,
	 *                     fileext, size, tstamp and rw.
	 *                     If a driver does not support the given property, it
	 *                     should fall back to "name".
	 * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
	 * @return array of FileIdentifiers
	 */
	public function getFilesInFolder($folderIdentifier, $start = 0, $numberOfItems = 0, $recursive = FALSE, array $filenameFilterCallbacks = array(), $sort = '', $sortRev = FALSE) {
		$fileIdentifiers = array();
		$folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
		$folderIdentifier = rtrim($folderIdentifier, '/');

		$directoryContents = new \DirectoryIterator($this->getStreamWrapperPath($folderIdentifier));

		foreach ($directoryContents as $file) {
			$newFileIdentifier = $folderIdentifier . $this->canonicalizeAndCheckFileIdentifier($file->getFilename());
			$path = $this->getStreamWrapperPath($newFileIdentifier);

			if (!$file->isDot() && is_file($path)) {
				$fileIdentifiers[] = $newFileIdentifier;
			}
		}

		return $fileIdentifiers;
	}

	/**
	 * Returns a list of folders inside the specified path
	 *
	 * @param string $folderIdentifier
	 * @param int $start
	 * @param int $numberOfItems
	 * @param bool $recursive
	 * @param array $folderNameFilterCallbacks callbacks for filtering the items
	 * @param string $sort Property name used to sort the items.
	 *                     Among them may be: '' (empty, no sorting), name,
	 *                     fileext, size, tstamp and rw.
	 *                     If a driver does not support the given property, it
	 *                     should fall back to "name".
	 * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
	 * @return array of Folder Identifier
	 */
	public function getFoldersInFolder($folderIdentifier, $start = 0, $numberOfItems = 0, $recursive = FALSE, array $folderNameFilterCallbacks = array(), $sort = '', $sortRev = FALSE) {
		$folderIdentifiers = array();
		$folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
		$folderIdentifier = rtrim($folderIdentifier, '/');

		$directoryContents = new \DirectoryIterator($this->getStreamWrapperPath($folderIdentifier));

		foreach ($directoryContents as $dir) {
			$newFolderIdentifier = $folderIdentifier . $this->canonicalizeAndCheckFileIdentifier($dir->getFilename());
			$path = $this->getStreamWrapperPath($newFolderIdentifier);

			if (!$dir->isDot() && $dir->getFilename() !== $this->getProcessingFolder() && is_dir($path)) {
				$folderIdentifiers[] = $newFolderIdentifier;
			}
		}

		return $folderIdentifiers;
	}

	/**
	 * Returns the number of files inside the specified path
	 *
	 * @param string $folderIdentifier
	 * @param boolean $recursive
	 * @param array $filenameFilterCallbacks callbacks for filtering the items
	 * @return integer Number of files in folder
	 */
	public function countFilesInFolder($folderIdentifier, $recursive = FALSE, array $filenameFilterCallbacks = array()) {
		return count($this->getFilesInFolder($folderIdentifier, 0, 0, $recursive, $filenameFilterCallbacks));
	}

	/**
	 * Returns the number of folders inside the specified path
	 *
	 * @param string $folderIdentifier
	 * @param boolean $recursive
	 * @param array $folderNameFilterCallbacks callbacks for filtering the items
	 * @return integer Number of folders in folder
	 */
	public function countFoldersInFolder($folderIdentifier, $recursive = FALSE, array $folderNameFilterCallbacks = array()) {
		return count($this->getFoldersInFolder($folderIdentifier, 0, 0, $recursive, $folderNameFilterCallbacks));
	}

	/**
	 * Returns the StreamWrapper path of a file or folder.
	 *
	 * @param \TYPO3\CMS\Core\Resource\FileInterface|\TYPO3\CMS\Core\Resource\Folder|string $file
	 * @return string
	 * @throws \RuntimeException
	 */
	protected function getStreamWrapperPath($file) {
		$basePath = $this->configuration['stream_protocol'] . '://' . $this->configuration['bucket'];

		if ($file instanceof TYPO3\CMS\Core\Resource\FileInterface) {
			$identifier = $file->getIdentifier();
		} elseif ($file instanceof TYPO3\CMS\Core\Resource\Folder) {
			$identifier = $file->getIdentifier();
		} elseif (is_string($file)) {
			$identifier = $file;
		} else {
			throw new \RuntimeException('Type "' . gettype($file) . '" is not supported.', 1325191178);
		}

		return $basePath . $identifier;
	}

	/**
	 * @param $path
	 * @return string
	 */
	protected function stripStreamWrapperPath($path) {
		return str_replace(
			$this->configuration['stream_protocol'] . '://' . $this->configuration['bucket'],
			'',
			$path
		);
	}

	/**
	 * @param string $folderIdentifier
	 * @param bool $recursive
	 * @param bool $includeFiles
	 * @param bool $includeDirectories
	 * @return array
	 */
	protected function resolveFolderEntries($folderIdentifier, $recursive = FALSE, $includeFiles = TRUE, $includeDirectories = TRUE) {
		$directoryEntries = array();
		$folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
		$path = $this->getStreamWrapperPath($folderIdentifier);

		$iteratorMode = \FilesystemIterator::UNIX_PATHS |
			\FilesystemIterator::SKIP_DOTS |
			\FilesystemIterator::CURRENT_AS_FILEINFO;

		if ($recursive) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($path, $iteratorMode),
				\RecursiveIteratorIterator::SELF_FIRST
			);
		} else {
			$iterator = new \RecursiveDirectoryIterator($path, $iteratorMode);
		}

		while ($iterator->valid()) {
			/** @var $entry \SplFileInfo */
			$entry = $iterator->current();

			if ((($entry->isFile() && $includeFiles) || ($entry->isDir() && $includeDirectories)) && $entry->getFilename() !== '') {
				$entryIdentifier = $this->stripStreamWrapperPath($entry->getPathname());

				if ($entry->isDir()) {
					$entryIdentifier = $this->canonicalizeAndCheckFolderIdentifier($entryIdentifier);
				} else {
					$entryIdentifier = $this->canonicalizeAndCheckFileIdentifier($entryIdentifier);
				}

				$directoryEntries[$entryIdentifier] = $entryIdentifier;
			}

			$iterator->next();
		}

		return array_values($directoryEntries);
	}

	/**
	 * @return \TYPO3\CMS\Core\Resource\ResourceStorage
	 */
	protected function getStorage(){
		if(!$this->storage){
			/** @var $storageRepository \TYPO3\CMS\Core\Resource\StorageRepository */
			$storageRepository = TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Resource\StorageRepository');
			$this->storage = $storageRepository->findByUid($this->storageUid);
		}

		return $this->storage;
	}

	/**
	 * @return string
	 */
	protected function getProcessingFolder(){
		return $this->getStorage()->getProcessingFolder()->getName();
	}
}

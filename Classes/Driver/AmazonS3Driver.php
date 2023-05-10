<?php

namespace MaxServ\FalS3\Driver;

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

use Aws\S3\S3Client;
use Aws\S3\StreamWrapper;
use TYPO3\CMS\Core\Resource\Driver\AbstractHierarchicalFilesystemDriver;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

use function GuzzleHttp\Psr7\mimetype_from_extension;

/**
 * Class AmazonS3Driver
 *
 * @package MaxServ\FalS3\Driver
 */
class AmazonS3Driver extends AbstractHierarchicalFilesystemDriver
{
    /**
     * @var string
     */
    const DRIVER_KEY = 'MaxServ.FalS3';

    /**
     * @var S3Client
     */
    protected $s3Client;

    /**
     * @var ResourceStorage
     */
    protected $storage;

    /**
     * List of temporary files
     *
     * @var array
     */
    protected $temporaryFiles = [];

    /**
     * Simple runtime cache to prevent numerous calls to S3 or the Caching Framework
     *
     * @var array
     */
    protected $fileExistsCache = [];

    /**
     * Simple runtime cache to prevent numerous calls to S3 or the Caching Framework
     *
     * @var array
     */
    protected $folderExistsCache = [];

    /**
     * Initialize this driver and expose the capabilities for the repository to use
     *
     * @param array $configuration
     */
    public function __construct(array $configuration = [])
    {
        parent::__construct($configuration);

        $this->capabilities = ResourceStorage::CAPABILITY_BROWSABLE |
            ResourceStorage::CAPABILITY_PUBLIC |
            ResourceStorage::CAPABILITY_WRITABLE;
    }

    /**
     * Remove all temporary created files when the object is destroyed.
     *
     * @return void
     */
    public function __destruct()
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            if (file_exists($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }

    /**
     * Processes the configuration for this driver.
     *
     * @return void
     * @throws InvalidConfigurationException
     */
    public function processConfiguration()
    {
        // check if a configurationKey is set in the configuration of this storage
        // next check if the key references to a storageConfiguration for this driver
        // if this storageConfiguration contains the mandatory key, secret and region properties
        // merge the configuration with the local array
        if (
            !is_array($this->configuration) || !array_key_exists('configurationKey', $this->configuration)
        ) {
            // throw an InvalidConfigurationException to trigger the storage to mark itself as offline
            throw new InvalidConfigurationException(
                'Unable to resolve a configurationKey for this driver instance',
                1438785477
            );
        }

        // phpcs:ignore
        $storageConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$this->configuration['configurationKey']] ?? [];

        // Region may be an empty string for custom endpoints, so we do not want to check empty() on the region setting
        if (
            !isset($storageConfiguration['region'])
            || !is_string($storageConfiguration['region'])
            || empty($storageConfiguration['key'])
            || !is_string($storageConfiguration['key'])
            || empty($storageConfiguration['secret'])
            || !is_string($storageConfiguration['secret'])
        ) {
            // throw an InvalidConfigurationException to trigger the storage to mark itself as offline
            throw new InvalidConfigurationException(
                'Missing configuration for "' . $this->configuration['configurationKey'] . '"',
                1438785908
            );
        }

        ArrayUtility::mergeRecursiveWithOverrule($this->configuration, $storageConfiguration);

        $this->configuration['excludedFolders'] = $this->configuration['excludedFolders'] ?? [];
    }

    /**
     * Initializes this object. This is called by the storage after the driver
     * has been attached.
     *
     * @return void
     * @throws InvalidConfigurationException
     */
    public function initialize()
    {
        if (
            !isset($this->configuration['region'])
            || !is_string($this->configuration['region'])
            || empty($this->configuration['key'])
            || !is_string($this->configuration['key'])
            || empty($this->configuration['secret'])
            || !is_string($this->configuration['secret'])
        ) {
            // throw an InvalidConfigurationException to trigger the storage to mark itself as offline
            throw new InvalidConfigurationException(
                'Missing configuration for "' . $this->configuration['configurationKey'] . '"',
                1644836749
            );
        }

        $clientConfiguration = [
            'version' => '2006-03-01',
            'region' => $this->configuration['region'],
            'credentials' => [
                'key' => $this->configuration['key'],
                'secret' => $this->configuration['secret']
            ]
        ];

        // Custom client endpoint. If set, apply the custom endpoint
        if (isset($this->configuration['endpoint']) && is_string($this->configuration['endpoint'])) {
            $clientConfiguration['endpoint'] = $this->configuration['endpoint'];
        }

        if (
            isset($this->configuration['use_path_style_endpoint'])
            && is_bool($this->configuration['use_path_style_endpoint'])
        ) {
            $clientConfiguration['use_path_style_endpoint'] = $this->configuration['use_path_style_endpoint'];
        }

        $this->s3Client = new S3Client($clientConfiguration);

        // strip the s3 protocol prefix from the bucket name
        if (strpos($this->configuration['bucket'], 's3://') === 0) {
            $this->configuration['bucket'] = substr($this->configuration['bucket'], 5);
        }

        // to prevent collisions between multiple S3 drivers using a stream_wrapper use a unique protocol key
        $this->configuration['stream_protocol'] = 's3.'
            . GeneralUtility::shortMD5(self::DRIVER_KEY . '.' . $this->configuration['configurationKey']);

        StreamWrapper::register($this->s3Client, $this->configuration['stream_protocol'], new Cache());
    }

    /**
     * Merges the capabilities merged by the user at the storage
     * configuration into the actual capabilities of the driver
     * and returns the result.
     *
     * @param int $capabilities
     * @return int
     */
    public function mergeConfigurationCapabilities($capabilities)
    {
        $this->capabilities &= $capabilities;
        return $this->capabilities;
    }

    /**
     * Returns the identifier of the root level folder of the storage.
     *
     * @return string
     */
    public function getRootLevelFolder()
    {
        return '/';
    }

    /**
     * Returns the identifier of the default folder new files should be put into.
     *
     * @return string
     */
    public function getDefaultFolder()
    {
        $defaultFolder = null;

        if (array_key_exists('defaultFolder', $this->configuration)) {
            if (!$this->folderExists($this->configuration['defaultFolder'])) {
                $defaultFolder = $this->createFolder($this->configuration['defaultFolder']);
            } else {
                $defaultFolder = $this->canonicalizeAndCheckFolderIdentifier($this->configuration['defaultFolder']);
            }
        }

        return $defaultFolder !== null ? $defaultFolder : $this->getRootLevelFolder();
    }

    /**
     * Returns the public URL to a file.
     * Either fully qualified URL or relative to PATH_site (rawurlencoded).
     *
     * @param string $identifier
     * @return string
     * @throws InvalidPathException
     */
    public function getPublicUrl($identifier)
    {
        $identifier = $this->canonicalizeAndCheckFileIdentifier($identifier);

        // if a basePath is configured prepend it to the file identifier
        // keep in mind that the basePath is appended to the public baseUrl
        if (
            array_key_exists('basePath', $this->configuration)
            && !empty($this->configuration['basePath'])
        ) {
            $identifier = '/' . trim($this->configuration['basePath'], '/') . $identifier;
        }

        $publicUrl = '';

        if (
            is_array($this->configuration)
            && (
                (array_key_exists('bucket', $this->configuration) && !empty($this->configuration['bucket']))
                || (array_key_exists('publicBaseUrl', $this->configuration)
                    && !empty($this->configuration['publicBaseUrl']))
            )
        ) {
            $uriParts = GeneralUtility::trimExplode('/', $identifier, true);
            $uriParts = array_map('rawurlencode', $uriParts);

            if (
                array_key_exists('publicBaseUrl', $this->configuration)
                && !empty($this->configuration['publicBaseUrl'])
            ) {
                $publicUrl = rtrim($this->configuration['publicBaseUrl'], '/') . '/' .
                    implode('/', $uriParts);
            } else {
                $publicUrl = 'https://' .
                    $this->configuration['bucket'] .
                    '.s3.amazonaws.com/' .
                    implode('/', $uriParts);
            }
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
    public function createFolder($newFolderName, $parentFolderIdentifier = '', $recursive = false)
    {
        $parentFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($parentFolderIdentifier);
        $newFolderName = trim($newFolderName, '/');

        if ($recursive === false) {
            $newFolderName = $this->sanitizeFileName($newFolderName);
            $identifier = $parentFolderIdentifier . $newFolderName . '/';
        } else {
            $parts = GeneralUtility::trimExplode('/', $newFolderName);
            $parts = array_map([$this, 'sanitizeFileName'], $parts);
            $newFolderName = implode('/', $parts);
            $identifier = $parentFolderIdentifier . $newFolderName . '/';
        }
        $path = $this->getStreamWrapperPath($identifier);

        /**
         * We do not care about the directory permissions by ourselves, but let the Amazon S3 StreamWrapper for
         * mkdir decide the correct ACL for the directory. The StreamWrapper will execute decoct() with the
         * permissions value. If we set it to null or 0, it will output 0 and will go with the default ACL set.
         * The value null can not be used because it will throw errors when PHP is set to strict types.
         * @see https://github.com/aws/aws-sdk-php/blob/master/src/S3/StreamWrapper.php#L873-L880
         */
        mkdir($path, 0, $recursive);

        $this->flushCacheEntriesForFolder($parentFolderIdentifier);

        return $identifier;
    }

    /**
     * Renames a folder in this storage.
     *
     * @param string $folderIdentifier
     * @param string $newName
     * @return array A map of old to new file identifiers of all affected resources
     * @throws InvalidPathException
     */
    public function renameFolder($folderIdentifier, $newName)
    {
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
     * @throws InvalidPathException
     */
    public function deleteFolder($folderIdentifier, $deleteRecursively = false)
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $path = $this->getStreamWrapperPath($folderIdentifier);

        if ($deleteRecursively) {
            $foldersInFolder = $this->resolveFolderEntries($folderIdentifier, true, false, true);

            array_map([$this, 'deleteFolder'], $foldersInFolder);
        }

        $this->flushCacheEntriesForFolder($folderIdentifier);
        $this->flushCacheEntriesForFolder(dirname($folderIdentifier));

        return unlink($path);
    }

    /**
     * Checks if a file exists.
     *
     * @param string $fileIdentifier
     * @return bool
     * @throws InvalidPathException
     */
    public function fileExists($fileIdentifier)
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);

        $path = rtrim(
            $this->getStreamWrapperPath($fileIdentifier),
            '/'
        );

        /** @var \TYPO3\CMS\Core\Http\ServerRequest $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $modifyingRequestMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];
        // Prevent duplicate calls to redis (e.g. in the filelist, which calls fileExists _a lot_ for the same file)
        // by caching the result in memory.
        // However, in some request methods it can happen that a file doesn't exist at the beginning of the request,
        // but is created during the request. Therefore, when the request method is one of the modifying ones (or there
        // is no request, e.g. in CLI context) bypass the cache.
        if (!array_key_exists($path, $this->fileExistsCache)
            || $request === null
            || in_array($request->getMethod(), $modifyingRequestMethods, true)
        ) {
            $this->fileExistsCache[$path] = is_file($path);
        }

        return $this->fileExistsCache[$path];
    }

    /**
     * Checks if a folder exists.
     *
     * @param string $folderIdentifier
     * @return bool
     */
    public function folderExists($folderIdentifier)
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);

        $path = $this->getStreamWrapperPath($folderIdentifier);

        if (!array_key_exists($path, $this->folderExistsCache)) {
            $this->folderExistsCache[$path] = is_dir($path);
        }

        return $this->folderExistsCache[$path];
    }

    /**
     * Checks if a folder contains files and (if supported) other folders.
     *
     * @param string $folderIdentifier
     * @return bool TRUE if there are no files and folders within $folder
     * @throws InvalidPathException
     */
    public function isFolderEmpty($folderIdentifier)
    {
        return $this->countFilesInFolder($folderIdentifier) === 0
            && $this->countFoldersInFolder($folderIdentifier) === 0;
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
     * @throws InvalidPathException
     */
    public function addFile($localFilePath, $targetFolderIdentifier, $newFileName = '', $removeOriginal = true)
    {
        $targetFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($targetFolderIdentifier);
        $targetFileIdentifier = rtrim($targetFolderIdentifier, '/')
            . $this->canonicalizeAndCheckFileIdentifier($newFileName);
        $targetFilePath = $this->getStreamWrapperPath($targetFileIdentifier);

        copy($localFilePath, $targetFilePath);

        if ($removeOriginal) {
            $this->temporaryFiles[$targetFileIdentifier] = $localFilePath;
        }

        $this->flushCacheEntriesForFolder($targetFolderIdentifier);
        unset($this->fileExistsCache[rtrim($targetFilePath, '/')]);

        return $targetFileIdentifier;
    }

    /**
     * Creates a new (empty) file and returns the identifier.
     *
     * @param string $fileName
     * @param string $parentFolderIdentifier
     * @return string
     * @throws InvalidPathException
     */
    public function createFile($fileName, $parentFolderIdentifier)
    {
        $parentFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($parentFolderIdentifier);
        $targetFileIdentifier = rtrim($parentFolderIdentifier, '/')
            . $this->canonicalizeAndCheckFileIdentifier($fileName);
        $absolutePath = $this->getStreamWrapperPath($targetFileIdentifier);

        // create an empty file using the putObject method instead of the wrapper
        // file_put_contents() without data or touch() yield unexpected results
        $this->s3Client->putObject(
            [
                'Bucket' => $this->configuration['bucket'],
                'Key' => ltrim($this->getBasePath() . $targetFileIdentifier, '/'),
                'Body' => ''
            ]
        );

        $this->flushCacheEntriesForFolder($parentFolderIdentifier);
        unset($this->fileExistsCache[rtrim($absolutePath, '/')]);

        return $targetFileIdentifier;
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
     * @throws InvalidPathException
     */
    public function copyFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $fileName)
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $targetFileIdentifier = $this->canonicalizeAndCheckFileIdentifier($targetFolderIdentifier . $fileName);

        $sourcePath = $this->getStreamWrapperPath($fileIdentifier);
        $targetPath = $this->getStreamWrapperPath($targetFileIdentifier);

        copy($sourcePath, $targetPath);

        $this->flushCacheEntriesForFolder($targetFolderIdentifier);

        return $targetFileIdentifier;
    }

    /**
     * Renames a file in this storage.
     *
     * @param string $fileIdentifier
     * @param string $newName The target path (including the file name!)
     * @return string The identifier of the file after renaming
     * @throws InvalidPathException
     */
    public function renameFile($fileIdentifier, $newName)
    {
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

        $this->flushCacheEntriesForFolder($parentFolderName);

        return $newIdentifier;
    }

    /**
     * Replaces a file with file in local file system.
     *
     * @param string $fileIdentifier
     * @param string $localFilePath
     * @return bool TRUE if the operation succeeded
     * @throws InvalidPathException
     */
    public function replaceFile($fileIdentifier, $localFilePath)
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $filePath = $this->getStreamWrapperPath($fileIdentifier);

        $this->flushCacheEntriesForFolder(dirname($fileIdentifier));

        $this->temporaryFiles[$fileIdentifier] = $localFilePath;
        return copy($localFilePath, $filePath);
    }

    /**
     * Removes a file from the filesystem. This does not check if the file is
     * still used or if it is a bad idea to delete it for some other reason
     * this has to be taken care of in the upper layers (e.g. the Storage)!
     *
     * @param string $fileIdentifier
     * @return bool TRUE if deleting the file succeeded
     * @throws InvalidPathException
     */
    public function deleteFile($fileIdentifier)
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $path = $this->getStreamWrapperPath($fileIdentifier);

        $this->flushCacheEntriesForFolder(dirname($fileIdentifier));

        return unlink($path);
    }

    /**
     * Creates a hash for a file.
     *
     * @param string $fileIdentifier
     * @param string $hashAlgorithm The hash algorithm to use
     * @return string
     * @throws InvalidPathException
     */
    public function hash($fileIdentifier, $hashAlgorithm)
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $path = $this->getStreamWrapperPath($fileIdentifier);
        if (!$this->fileExists($fileIdentifier)) {
            // The ResourceStorage catches an empty hash and handles
            return '';
        }

        switch ($hashAlgorithm) {
            case 'sha1':
                $hash = sha1_file($path);
                break;
            case 'md5':
                $hash = md5_file($path);
                break;
            default:
                throw new \RuntimeException('Hash algorithm ' . $hashAlgorithm . ' is not implemented.', 1329644451);
        }

        return $hash;
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
     * @throws InvalidPathException
     */
    public function moveFileWithinStorage($fileIdentifier, $targetFolderIdentifier, $newFileName)
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $targetFileIdentifier = $this->canonicalizeAndCheckFileIdentifier($targetFolderIdentifier . $newFileName);

        $sourcePath = $this->getStreamWrapperPath($fileIdentifier);
        $targetPath = $this->getStreamWrapperPath($targetFileIdentifier);

        $this->flushCacheEntriesForFolder(dirname($fileIdentifier));
        $this->flushCacheEntriesForFolder($targetFolderIdentifier);

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
     * @throws InvalidPathException
     */
    public function moveFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        $sourceFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($sourceFolderIdentifier);
        $targetFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($targetFolderIdentifier . $newFolderName);

        $oldPath = $this->getStreamWrapperPath($sourceFolderIdentifier);
        $newPath = $this->getStreamWrapperPath($targetFolderIdentifier);

        $renamedEntries = array_flip($this->resolveFolderEntries($sourceFolderIdentifier, true));

        foreach ($renamedEntries as $oldEntryIdentifier => $newEntryIdentifier) {
            $newEntryIdentifier = str_replace(
                $sourceFolderIdentifier,
                $targetFolderIdentifier,
                $oldEntryIdentifier
            );

            $oldEntryPath = $this->getStreamWrapperPath($oldEntryIdentifier);
            $newEntryPath = $this->getStreamWrapperPath($newEntryIdentifier);

            if (is_dir($oldEntryPath)) {
                $this->flushCacheEntriesForFolder($oldEntryIdentifier);
            }

            rename($oldEntryPath, $newEntryPath);

            $renamedEntries[$oldEntryIdentifier] = $newEntryIdentifier;
        }

        rename($oldPath, $newPath);

        $renamedEntries[$sourceFolderIdentifier] = $targetFolderIdentifier;

        $this->flushCacheEntriesForFolder($sourceFolderIdentifier);
        $this->flushCacheEntriesForFolder(dirname($sourceFolderIdentifier));
        $this->flushCacheEntriesForFolder(dirname($targetFolderIdentifier));

        return $renamedEntries;
    }

    /**
     * Folder equivalent to copyFileWithinStorage().
     *
     * @param string $sourceFolderIdentifier
     * @param string $targetFolderIdentifier
     * @param string $newFolderName
     * @return bool
     * @throws InvalidPathException
     */
    public function copyFolderWithinStorage($sourceFolderIdentifier, $targetFolderIdentifier, $newFolderName)
    {
        $sourceFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($sourceFolderIdentifier);
        $targetFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($targetFolderIdentifier) .
            ltrim($this->canonicalizeAndCheckFolderIdentifier($newFolderName), '/');

        $sourceDirectoryContents = $this->resolveFolderEntries($sourceFolderIdentifier, true, true, true);

        foreach ($sourceDirectoryContents as $sourceEntry) {
            $sourcePath = $this->getStreamWrapperPath($sourceEntry);
            $targetPath = $this->getStreamWrapperPath(
                str_replace(
                    $sourceFolderIdentifier,
                    $targetFolderIdentifier,
                    $sourceEntry
                )
            );

            // use mkdir to create a new directory instead of copying the resource
            if (substr($sourcePath, -1) === '/') {
                /**
                 * We do not care about the directory permissions by ourselves, but let the Amazon S3 StreamWrapper for
                 * mkdir decide the correct ACL for the directory. The StreamWrapper will execute decoct() with the
                 * permissions value. If we set it to null or 0, it will output 0 and will go with the default ACL.
                 * The value null can not be used because it will throw errors when PHP is set to strict types.
                 * @see https://github.com/aws/aws-sdk-php/blob/master/src/S3/StreamWrapper.php#L873-L880
                 */
                mkdir($targetPath, 0, true);
            } else {
                copy($sourcePath, $targetPath);
            }
        }

        $this->flushCacheEntriesForFolder(dirname($targetFolderIdentifier));

        return true;
    }

    /**
     * Returns the contents of a file. Beware that this requires to load the
     * complete file into memory and also may require fetching the file from an
     * external location. So this might be an expensive operation (both in terms
     * of processing resources and money) for large files.
     *
     * @param string $fileIdentifier
     * @return string The file contents
     * @throws InvalidPathException
     */
    public function getFileContents($fileIdentifier)
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);

        return file_get_contents($this->getStreamWrapperPath($fileIdentifier));
    }

    /**
     * Sets the contents of a file to the specified value.
     *
     * @param string $fileIdentifier
     * @param string $contents
     * @return int The number of bytes written to the file
     * @throws InvalidPathException
     */
    public function setFileContents($fileIdentifier, $contents)
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);

        return file_put_contents($this->getStreamWrapperPath($fileIdentifier), $contents);
    }

    /**
     * Checks if a file inside a folder exists
     *
     * @param string $fileName
     * @param string $folderIdentifier
     * @return bool
     * @throws InvalidPathException
     */
    public function fileExistsInFolder($fileName, $folderIdentifier)
    {
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
    public function folderExistsInFolder($folderName, $folderIdentifier)
    {
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
     * @throws InvalidPathException
     * @throws \RuntimeException
     */
    public function getFileForLocalProcessing($fileIdentifier, $writable = true)
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);

        if (!$this->fileExists($fileIdentifier)) {
            // LocalDriver throws a RuntimeException if the file does not exist. We want the same behaviour.
            throw new \RuntimeException(
                'File "' . $fileIdentifier . '" does no longer exist on the S3 storage',
                1654008397
            );
        }

        $temporaryFilePath = $this->getTemporaryPathForFile($fileIdentifier);
        $path = $this->getStreamWrapperPath($fileIdentifier);
        copy($path, $temporaryFilePath);

        if (!$writable) {
            $this->temporaryFiles[$fileIdentifier] = $temporaryFilePath;
        }

        return $temporaryFilePath;
    }

    /**
     * Returns the permissions of a file/folder as an array
     * (keys r, w) of boolean flags
     *
     * @param string $identifier
     * @return array
     */
    public function getPermissions($identifier)
    {
        $identifier = $this->canonicalizeAndCheckFolderIdentifier($identifier);

        $path = $this->getStreamWrapperPath(rtrim($identifier, '/'));

        return [
            'r' => is_readable($path),
            'w' => is_writable($path)
        ];
    }

    /**
     * Directly output the contents of the file to the output
     * buffer. Should not take care of header files or flushing
     * buffer before. Will be taken care of by the Storage.
     *
     * @param string $identifier
     *
     * @return void
     * @throws InvalidPathException
     */
    public function dumpFileContents($identifier)
    {
        readfile($this->getStreamWrapperPath($this->canonicalizeAndCheckFileIdentifier($identifier)), 0);
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
     * @throws InvalidPathException
     */
    public function isWithin($folderIdentifier, $identifier)
    {
        $folderIdentifier = $this->canonicalizeAndCheckFileIdentifier($folderIdentifier);
        $identifier = $this->canonicalizeAndCheckFileIdentifier($identifier);
        return $folderIdentifier === $identifier
            || ($folderIdentifier !== $identifier
                && $folderIdentifier !== ''
                && strpos($identifier, $folderIdentifier) === 0);
    }

    /**
     * Returns information about a file.
     *
     * @param string $fileIdentifier
     * @param array $propertiesToExtract Array of properties which are be extracted
     *                                   If empty all will be extracted
     * @return array
     * @throws InvalidPathException
     */
    public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = [])
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $path = $this->getStreamWrapperPath($fileIdentifier);
        return $this->fileExists($fileIdentifier)
            ? $this->extractFileInformation($fileIdentifier, $path, $propertiesToExtract)
            : [];
    }

    /**
     * Extracts information about a file from the filesystem.
     *
     * @param string $fileIdentifier The fileIdentifier
     * @param string $path The path to the file
     * @param array $propertiesToExtract array of properties which should be returned, if empty all will be extracted
     * @return array
     */
    protected function extractFileInformation(string $fileIdentifier, string $path, array $propertiesToExtract = [])
    {
        if (empty($propertiesToExtract)) {
            $propertiesToExtract = [
                'size',
                'atime',
                'mtime',
                'ctime',
                'mimetype',
                'name',
                'extension',
                'identifier',
                'identifier_hash',
                'storage',
                'folder_hash'
            ];
        }
        $fileInformation = [];
        foreach ($propertiesToExtract as $property) {
            $fileInformation[$property] = $this->getSpecificFileInformation($fileIdentifier, $path, $property);
        }
        return $fileInformation;
    }

    /**
     * Extracts a specific FileInformation from the FileSystems.
     *
     * @param string $fileIdentifier
     * @param string $path
     * @param string $property
     *
     * @return bool|int|string
     * @throws \InvalidArgumentException
     */
    public function getSpecificFileInformation(string $fileIdentifier, string $path, string $property)
    {
        switch ($property) {
            case 'size':
                return (int)filesize($path);
            case 'atime':
                return fileatime($path);
            case 'mtime':
                return filemtime($path);
            case 'ctime':
                return filectime($path);
            case 'name':
                return basename($fileIdentifier);
            case 'extension':
                return pathinfo($fileIdentifier, PATHINFO_EXTENSION);
            case 'mimetype':
                return $this->getFileMimeType($path);
            case 'identifier':
                return $fileIdentifier;
            case 'storage':
                return $this->storageUid;
            case 'identifier_hash':
                return $this->hashIdentifier($fileIdentifier);
            case 'folder_hash':
                return $this->hashIdentifier(PathUtility::dirname($fileIdentifier));
            default:
                throw new \InvalidArgumentException(
                    sprintf('The information "%s" is not available.', $property),
                    1597926187
                );
        }
    }

    /**
     * @param string $path
     * @return string
     */
    public function getFileMimeType(string $path)
    {
        $fileExtensionToMimeTypeMapping = $GLOBALS['TYPO3_CONF_VARS']['SYS']['FileInfo']['fileExtensionToMimeType'];
        $lowercaseFileExtension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = mimetype_from_extension($lowercaseFileExtension);

        if ($mimeType === null && !empty($fileExtensionToMimeTypeMapping[$lowercaseFileExtension])) {
            $mimeType = $fileExtensionToMimeTypeMapping[$lowercaseFileExtension];
        }

        return $mimeType !== null ? $mimeType : 'application/octet-stream';
    }

    /**
     * Returns information about a file.
     *
     * @param string $folderIdentifier
     * @return array
     */
    public function getFolderInfoByIdentifier($folderIdentifier)
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);

        return [
            'identifier' => $folderIdentifier,
            'name' => basename($folderIdentifier),
            'storage' => $this->storageUid
        ];
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
     * @throws InvalidPathException
     */
    public function getFilesInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 0,
        $recursive = false,
        array $filenameFilterCallbacks = [],
        $sort = '',
        $sortRev = false
    ) {
        if ($start === false && $numberOfItems === false) {
            return [];
        }

        $folderEntries = $this->resolveFolderEntries(
            $folderIdentifier,
            $recursive,
            true,
            false,
            $filenameFilterCallbacks
        );

        if (!$recursive) {
            $folderEntries = $this->sortFolderEntries($folderEntries, $sort, $sortRev);
        }

        return array_slice(
            $folderEntries,
            $start,
            ($numberOfItems > 0 ? $numberOfItems : null)
        );
    }

    /**
     * Returns the identifier of a file inside the folder
     *
     * @param string $fileName
     * @param string $folderIdentifier
     * @return string file identifier
     * @throws InvalidPathException
     */
    public function getFileInFolder($fileName, $folderIdentifier)
    {
        return $this->canonicalizeAndCheckFileIdentifier($folderIdentifier . '/' . $fileName);
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
     * @throws InvalidPathException
     */
    public function getFoldersInFolder(
        $folderIdentifier,
        $start = 0,
        $numberOfItems = 0,
        $recursive = false,
        array $folderNameFilterCallbacks = [],
        $sort = '',
        $sortRev = false
    ) {
        if ($start === false && $numberOfItems === false) {
            return [];
        }

        $processingFolder = $this->getProcessingFolder();
        $excludedFolders = $this->configuration['excludedFolders'];
        $this->configuration['excludedFolders'][] = $processingFolder;

        $folderEntries = $this->resolveFolderEntries($folderIdentifier, $recursive, false, true);

        if (!$recursive) {
            $folderEntries = $this->sortFolderEntries($folderEntries);
        }

        $folderIdentifiers = array_slice(
            $folderEntries,
            $start,
            ($numberOfItems > 0 ? $numberOfItems : null)
        );

        $this->configuration['excludedFolders'] = $excludedFolders;
        return $folderIdentifiers;
    }

    /**
     * Returns the identifier of a folder inside the folder
     *
     * @param string $folderName The name of the target folder
     * @param string $folderIdentifier
     * @return string folder identifier
     */
    public function getFolderInFolder($folderName, $folderIdentifier)
    {
        return $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier . '/' . $folderName);
    }

    /**
     * Returns the number of files inside the specified path
     *
     * @param string $folderIdentifier
     * @param boolean $recursive
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @return integer Number of files in folder
     * @throws InvalidPathException
     */
    public function countFilesInFolder($folderIdentifier, $recursive = false, array $filenameFilterCallbacks = [])
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $path = $this->getStreamWrapperPath($folderIdentifier);

        $cacheEntryIdentifier = Cache::buildEntryIdentifier(
            $path,
            'count_files'
        );

        $count = Cache::getCacheFrontend()->get($cacheEntryIdentifier);
        if ($count === false) {
            $count = count($this->getFilesInFolder($folderIdentifier, 0, 0, $recursive, $filenameFilterCallbacks));
            $cacheTags = [Cache::buildEntryIdentifier($path, 'd')];
            Cache::getCacheFrontend()->set($cacheEntryIdentifier, $count, $cacheTags, 0);
        }

        return (int)$count;
    }


    /**
     * Returns the number of folders inside the specified path
     *
     * @param string $folderIdentifier
     * @param boolean $recursive
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @return integer Number of folders in folder
     * @throws InvalidPathException
     */
    public function countFoldersInFolder($folderIdentifier, $recursive = false, array $folderNameFilterCallbacks = [])
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $path = $this->getStreamWrapperPath($folderIdentifier);

        $cacheEntryIdentifier = Cache::buildEntryIdentifier(
            $path,
            'count_folders'
        );

        $count = Cache::getCacheFrontend()->get($cacheEntryIdentifier);
        if ($count === false) {
            $count = count($this->getFoldersInFolder($folderIdentifier, 0, 0, $recursive, $folderNameFilterCallbacks));
            $cacheTags = [Cache::buildEntryIdentifier($path, 'd')];
            Cache::getCacheFrontend()->set($cacheEntryIdentifier, $count, $cacheTags, 0);
        }

        return (int)$count;
    }

    /**
     * Returns the StreamWrapper path of a file or folder.
     *
     * @param FileInterface|Folder|string $file
     * @return string
     * @throws \RuntimeException
     */
    protected function getStreamWrapperPath($file)
    {
        $basePath = $this->configuration['stream_protocol'] . '://' . $this->configuration['bucket'];

        if (array_key_exists('basePath', $this->configuration) && !empty($this->configuration['basePath'])) {
            $basePath .= '/' . trim($this->configuration['basePath'], '/');
        }

        if ($file instanceof FileInterface) {
            $identifier = $file->getIdentifier();
        } elseif ($file instanceof Folder) {
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
    protected function stripStreamWrapperPath($path)
    {
        $basePath = $this->configuration['stream_protocol'] . '://' . $this->configuration['bucket'];

        if (array_key_exists('basePath', $this->configuration) && !empty($this->configuration['basePath'])) {
            $basePath .= '/' . trim($this->configuration['basePath'], '/');
        }
        return str_replace(
            $basePath,
            '',
            $path
        );
    }

    /**
     * @param string $folderIdentifier
     * @param bool $recursive
     * @param bool $includeFiles
     * @param bool $includeDirectories
     * @param array $filterMethods
     *
     * @return array
     * @throws InvalidPathException
     */
    protected function resolveFolderEntries(
        $folderIdentifier,
        $recursive = false,
        $includeFiles = true,
        $includeDirectories = true,
        array $filterMethods = []
    ) {
        $excludedFolders = $this->configuration['excludedFolders'] ?? [];
        if (in_array($folderIdentifier, $excludedFolders)) {
            return [];
        }
        $cacheFrontend = Cache::getCacheFrontend();
        $directoryEntries = [];
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $path = $this->getStreamWrapperPath($folderIdentifier);

        $iteratorMode = \FilesystemIterator::UNIX_PATHS |
            \FilesystemIterator::SKIP_DOTS |
            \FilesystemIterator::CURRENT_AS_FILEINFO;

        $iterator = new CachedDirectoryIterator(
            $path,
            $iteratorMode,
            $cacheFrontend,
            function (\SplFileInfo $fileInfo) {
                $entryIdentifier = $this->stripStreamWrapperPath($fileInfo->getPathname());
                if ($fileInfo->isDir()) {
                    $entryIdentifier = $this->canonicalizeAndCheckFolderIdentifier($entryIdentifier);
                } else {
                    $entryIdentifier = $this->canonicalizeAndCheckFileIdentifier($entryIdentifier);
                }
                return $entryIdentifier;
            },
            function (\SplFileInfo $fileInfo) use ($excludedFolders) {
                return ($fileInfo->getFilename() === '')
                    || ($fileInfo->isDir() && in_array($fileInfo->getFilename(), $excludedFolders, true));
            }
        );

        if ($recursive) {
            $processingFolder = $this->getProcessingFolder();
            $excludedFolders = $this->configuration['excludedFolders'];
            $this->configuration['excludedFolders'][] = $processingFolder;

            $iterator = new \RecursiveIteratorIterator(
                $iterator,
                \RecursiveIteratorIterator::SELF_FIRST
            );
        }

        while ($iterator->valid()) {
            $entry = $iterator->current();
            $directoryEntries[$entry] = $entry;
            $isDirectory = substr($entry, -1) === '/';
            if ($isDirectory && !$includeDirectories) {
                unset($directoryEntries[$entry]);
                $iterator->next();
                continue;
            }
            if (!$isDirectory && !$includeFiles) {
                unset($directoryEntries[$entry]);
                $iterator->next();
                continue;
            }
            $iterator->next();
            $file = $this->getFileInfoByIdentifier($entry);
            if (
                !empty($file) &&
                !$this->applyFilterMethodsToDirectoryItem(
                    $filterMethods,
                    $file['name'],
                    $file['identifier'],
                    $this->getParentFolderIdentifierOfIdentifier($file['identifier'])
                )
            ) {
                unset($directoryEntries[$entry]);
            }
        }

        $this->configuration['excludedFolders'] = $excludedFolders;
        return array_values($directoryEntries);
    }

    /**
     * Applies a set of filter methods to a file name to find out if it should be used or not. This is e.g. used by
     * directory listings.
     *
     * @param array $filterMethods The filter methods to use
     * @param string $itemName
     * @param string $itemIdentifier
     * @param string $parentIdentifier
     * @return bool
     * @throws \RuntimeException
     */
    protected function applyFilterMethodsToDirectoryItem(
        array $filterMethods,
        $itemName,
        $itemIdentifier,
        $parentIdentifier
    ) {
        foreach ($filterMethods as $filter) {
            if (
                is_callable($filter)
                && is_string($itemName) && $itemName !== ''
                && is_string($itemIdentifier) && $itemIdentifier !== ''
                && is_string($parentIdentifier) && $parentIdentifier !== ''
            ) {
                $result = call_user_func($filter, $itemName, $itemIdentifier, $parentIdentifier, [], $this);
                // We have to use -1 as the „don't include“ return value, as call_user_func() will return FALSE
                // If calling the method succeeded and thus we can't use that as a return value.
                if ($result === -1) {
                    return false;
                }
                if ($result === false) {
                    throw new \RuntimeException(
                        'Could not apply file/folder name filter ' . $filter[0] . '::' . $filter[1],
                        1476046425
                    );
                }
            }
        }
        return true;
    }

    /**
     * Sort the directory entries by a certain key
     *
     * @param array $folderEntries
     * @param string $method
     * @param bool $reverse
     * @return array
     */
    protected function sortFolderEntries($folderEntries, $method = '', $reverse = false)
    {
        $sortableEntries = [];

        foreach ($folderEntries as $key => $identifier) {
            $sortingValue = null;

            if ($method === 'fileext') {
                $sortingValue = pathinfo($identifier, PATHINFO_EXTENSION);
            }

            if ($method === 'rw') {
                // should be checked with the storage rather than the driver,
                // the underlying might allow more than a user in TYPO3
                $permissions = $this->getPermissions($identifier);

                $sortingValue = '';
                $sortingValue .= ($permissions['r'] ? 'R' : '');
                $sortingValue .= ($permissions['w'] ? 'W' : '');
            }

            if ($method === 'size') {
                $sortingValue = filesize($this->getStreamWrapperPath($identifier));
            }

            if ($method === 'tstamp') {
                $sortingValue = filemtime($this->getStreamWrapperPath($identifier));
            }

            if ($sortingValue !== null) {
                $sortableEntries[$identifier] = $sortingValue;
            }
        }

        // if sorting should be performed by name use the native PHP natcasesort() method
        if (!empty($sortableEntries)) {
            natcasesort($sortableEntries);
            $sortableEntries = array_keys($sortableEntries);
            $folderEntries = $sortableEntries;
        } else {
            natcasesort($folderEntries);
        }

        if ($reverse) {
            $folderEntries = array_reverse($folderEntries);
        }

        return $folderEntries;
    }

    /**
     * @return ResourceStorage
     */
    protected function getStorage()
    {
        if (!$this->storage) {
            /** @var StorageRepository $storageRepository */
            $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
            $this->storage = $storageRepository->findByUid($this->storageUid);
        }

        return $this->storage;
    }

    /**
     * @return string
     */
    protected function getProcessingFolder()
    {
        return $this->getStorage()->getProcessingFolder()->getName();
    }

    /**
     * @return string
     */
    protected function getBasePath()
    {
        if (array_key_exists('basePath', $this->configuration) && !empty($this->configuration['basePath'])) {
            return '/' . trim($this->configuration['basePath'], '/');
        }

        return '';
    }

    /**
     * @param string $folderIdentifier
     *
     * @return void
     */
    protected function flushCacheEntriesForFolder($folderIdentifier)
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $path = $this->getStreamWrapperPath($folderIdentifier);

        // see resolveFolderEntries(), cache entries are tagged with the path of the parent folder
        Cache::getCacheFrontend()->flushByTag(Cache::buildEntryIdentifier($path, 'd'));
    }
}

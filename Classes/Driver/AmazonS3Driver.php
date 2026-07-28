<?php

declare(strict_types=1);

namespace MaxServ\FalS3\Driver;

use Aws\S3\S3Client;
use Aws\S3\StreamWrapper;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Resource\Capabilities;
use TYPO3\CMS\Core\Resource\Driver\AbstractHierarchicalFilesystemDriver;
use TYPO3\CMS\Core\Resource\Driver\StreamableDriverInterface;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileNameException;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Type\File\FileInfo;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class AmazonS3Driver extends AbstractHierarchicalFilesystemDriver implements StreamableDriverInterface
{
    public const DRIVER_KEY = 'MaxServ.FalS3';
    protected S3Client $s3Client;
    protected ?ResourceStorage $storage = null;
    protected array $temporaryFiles = [];
    protected array $fileExistsCache = [];
    protected array $folderExistsCache = [];
    protected array $recursiveFolderEntriesCache = [];

    public function __construct(array $configuration = [])
    {
        parent::__construct($configuration);

        $this->capabilities = new Capabilities(
            Capabilities::CAPABILITY_BROWSABLE
            | Capabilities::CAPABILITY_PUBLIC
            | Capabilities::CAPABILITY_WRITABLE
        );
    }

    /**
     * Remove all temporary created files when the object is destroyed.
     */
    public function __destruct()
    {
        foreach ($this->temporaryFiles as $temporaryFile) {
            if (file_exists($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }

    public function mergeConfigurationCapabilities(Capabilities $capabilities): Capabilities
    {
        $this->capabilities->and($capabilities);
        return $this->capabilities;
    }

    /**
     * Processes the configuration for this driver.
     *
     * @throws InvalidConfigurationException
     */
    public function processConfiguration(): void
    {
        // check if a configurationKey is set in the configuration of this storage
        // next check if the key references to a storageConfiguration for this driver
        // if this storageConfiguration contains the mandatory key, secret and region properties
        // merge the configuration with the local array
        if (empty($this->configuration['configurationKey'] ?? '')) {
            // throw an InvalidConfigurationException to trigger the storage to mark itself as offline
            throw new InvalidConfigurationException(
                'Unable to resolve a configurationKey for this driver instance',
                1438785477
            );
        }

        $storageConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['fal_s3']['storageConfigurations'][$this->configuration['configurationKey']] ?? [];

        // Region may be an empty string for custom endpoints, so we do not want to check empty() on the region setting
        if (!is_string($storageConfiguration['region'] ?? false)
            || empty($storageConfiguration['key'] ?? '')
            || empty($storageConfiguration['secret'] ?? '')
        ) {
            // throw an InvalidConfigurationException to trigger the storage to mark itself as offline
            throw new InvalidConfigurationException(
                sprintf('Missing configuration for "%s"', $this->configuration['configurationKey']),
                1438785908
            );
        }

        ArrayUtility::mergeRecursiveWithOverrule($this->configuration, $storageConfiguration);

        $this->configuration['excludedFolders'] ??= [];
    }

    /**
     * Initializes this object. This is called by the storage after the driver
     * has been attached.
     */
    public function initialize(): void
    {
        if (!is_string($this->configuration['region'] ?? false)
            || empty($this->configuration['key'] ?? '')
            || empty($this->configuration['secret'] ?? '')
        ) {
            return;
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
        if (!empty($this->configuration['endpoint'] ?? '')) {
            $clientConfiguration['endpoint'] = $this->configuration['endpoint'];
        }

        // Custom path style endpoint setting. If set, use a path style endpoint
        if (is_bool($this->configuration['use_path_style_endpoint'] ?? '')) {
            $clientConfiguration['use_path_style_endpoint'] = $this->configuration['use_path_style_endpoint'];
        }

        $this->s3Client = new S3Client($clientConfiguration);

        // strip the s3 protocol prefix from the bucket name
        if (str_starts_with($this->configuration['bucket'], 's3://')) {
            $this->configuration['bucket'] = substr($this->configuration['bucket'], 5);
        }

        // to prevent collisions between multiple S3 drivers using a stream_wrapper use a unique protocol key
        $this->configuration['stream_protocol'] = 's3.'
            . md5(self::DRIVER_KEY . '.' . $this->configuration['configurationKey']);

        StreamWrapper::register($this->s3Client, $this->configuration['stream_protocol'], new Cache());
    }

    public function getRootLevelFolder(): string
    {
        return '/';
    }

    /**
     * Returns the identifier of the default folder new files should be put into.
     *
     * @throws NoSuchCacheException
     */
    public function getDefaultFolder(): string
    {
        $defaultFolder = null;

        if (array_key_exists('defaultFolder', $this->configuration)) {
            if (!$this->folderExists($this->configuration['defaultFolder'])) {
                $defaultFolder = $this->createFolder($this->configuration['defaultFolder']);
            } else {
                $defaultFolder = $this->canonicalizeAndCheckFolderIdentifier($this->configuration['defaultFolder']);
            }
        }

        return $defaultFolder ?? $this->getRootLevelFolder();
    }

    /**
     * Returns the public URL to a file.
     * Either fully qualified URL or relative to PATH_site (rawurlencoded).
     *
     * @throws InvalidPathException
     */
    public function getPublicUrl(string $identifier): ?string
    {
        $identifier = $this->canonicalizeAndCheckFileIdentifier($identifier);

        // if a basePath is configured prepend it to the file identifier
        // keep in mind that the basePath is appended to the public baseUrl
        if (!empty($this->configuration['basePath'] ?? '')) {
            $identifier = '/' . trim($this->configuration['basePath'], '/') . $identifier;
        }

        if (empty($this->configuration['publicBaseUrl'] ?? '') && empty($this->configuration['bucket'] ?? '')) {
            return null;
        }

        $uriParts = GeneralUtility::trimExplode('/', $identifier, true);
        $uriParts = array_map(rawurlencode(...), $uriParts);

        if (!empty($this->configuration['publicBaseUrl'] ?? '')) {
            return rtrim($this->configuration['publicBaseUrl'], '/') . '/' . implode('/', $uriParts);
        }

        return 'https://' . $this->configuration['bucket'] . '.s3.amazonaws.com/' . implode('/', $uriParts);
    }

    /**
     * Creates a folder, within a parent folder.
     * If no parent folder is given, a root level folder will be created
     *
     * @return string the Identifier of the new folder
     * @throws NoSuchCacheException
     * @throws InvalidFileNameException
     * @throws InvalidPathException
     * @throws ExistingTargetFolderException
     */
    public function createFolder(string $newFolderName, string $parentFolderIdentifier = '', bool $recursive = false): string
    {
        $parentFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($parentFolderIdentifier);
        $newFolderName = trim($newFolderName, '/');

        if ($recursive === false) {
            $newFolderName = $this->sanitizeFileName($newFolderName);
        } else {
            $parts = GeneralUtility::trimExplode('/', $newFolderName);
            $parts = array_map($this->sanitizeFileName(...), $parts);
            $newFolderName = implode('/', $parts);
        }
        $identifier = $this->canonicalizeAndCheckFolderIdentifier($parentFolderIdentifier . $newFolderName . '/');

        if ($this->fileExists($identifier)) {
            throw new ExistingTargetFolderException('A file with the name of the created folder already exists', 1689241878);
        }

        $path = $this->getStreamWrapperPath($identifier);

        /**
         * We do not care about the directory permissions by ourselves, but let the Amazon S3 StreamWrapper for
         * mkdir decide the correct ACL for the directory. The StreamWrapper will execute decoct() with the
         * permissions value. If we set it to null or 0, it will output 0 and will go with the default ACL set.
         * The value null can not be used because it will throw errors when PHP is set to strict types.
         * @see https://github.com/aws/aws-sdk-php/blob/master/src/S3/StreamWrapper.php#L873-L880
         */
        if (!mkdir($path, 0, $recursive) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        $this->flushCacheEntriesForFolder($parentFolderIdentifier);
        unset($this->folderExistsCache[rtrim($path, '/')]);

        return $identifier;
    }

    /**
     * Renames a folder in this storage.
     *
     * @return array A map of old to new file identifiers of all affected resources
     * @throws InvalidPathException
     * @throws InvalidFileNameException
     * @throws NoSuchCacheException
     * @throws ExistingTargetFileNameException
     * @throws ExistingTargetFolderException
     */
    public function renameFolder(string $folderIdentifier, string $newName): array
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

        $newIdentifier = $this->canonicalizeAndCheckFolderIdentifier($parentFolderName . $newName . '/');

        if ($this->fileExists($newIdentifier) || $this->folderExists($newIdentifier)) {
            throw new ExistingTargetFileNameException('A file or folder with the name of the moved folder already exists', 1689242245);
        }

        return $this->moveFolderWithinStorage($folderIdentifier, $newIdentifier, '');
    }

    /**
     * Removes a folder in filesystem.
     *
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     */
    public function deleteFolder(string $folderIdentifier, bool $deleteRecursively = false): bool
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $path = $this->getStreamWrapperPath($folderIdentifier);

        if ($deleteRecursively) {
            $foldersInFolder = $this->resolveFolderEntries($folderIdentifier, true, false, true);

            array_map($this->deleteFolder(...), $foldersInFolder);
        }

        $this->flushCacheEntriesForFolder($folderIdentifier);
        $this->flushCacheEntriesForFolder(dirname($folderIdentifier));
        unset($this->folderExistsCache[rtrim($folderIdentifier, '/')]);

        return unlink($path);
    }

    /**
     * Checks if a file exists.
     *
     * @throws InvalidPathException
     */
    public function fileExists(string $fileIdentifier): bool
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);

        $path = rtrim(
            $this->getStreamWrapperPath($fileIdentifier),
            '/'
        );

        /** @var \TYPO3\CMS\Core\Http\ServerRequest $request */
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
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
     */
    public function folderExists(string $folderIdentifier): bool
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);

        $path = $this->getStreamWrapperPath($folderIdentifier);

        if (!array_key_exists(rtrim($path, '/'), $this->folderExistsCache)) {
            $this->folderExistsCache[rtrim($path, '/')] = is_dir($path);
        }

        return $this->folderExistsCache[rtrim($path, '/')];
    }

    /**
     * Checks if a folder contains files and (if supported) other folders.
     *
     * @return bool TRUE if there are no files and folders within $folder
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     */
    public function isFolderEmpty(string $folderIdentifier): bool
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
     * @param string $newFileName optional, if not given original name is used
     * @param bool $removeOriginal if set the original file will be removed
     *                                after successful operation
     * @return string the identifier of the new file
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     * @throws ExistingTargetFileNameException
     */
    public function addFile(string $localFilePath, string $targetFolderIdentifier, string $newFileName = '', bool $removeOriginal = true): string
    {
        $targetFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($targetFolderIdentifier);
        $targetFileIdentifier = rtrim($targetFolderIdentifier, '/')
            . $this->canonicalizeAndCheckFileIdentifier($newFileName);
        $targetFilePath = $this->getStreamWrapperPath($targetFileIdentifier);

        if ($this->folderExists($targetFileIdentifier)) {
            throw new ExistingTargetFileNameException('A folder with the name of the added file already exists', 1689242007);
        }

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
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     * @throws ExistingTargetFileNameException
     */
    public function createFile(string $fileName, string $parentFolderIdentifier): string
    {
        $parentFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($parentFolderIdentifier);
        $targetFileIdentifier = rtrim($parentFolderIdentifier, '/')
            . $this->canonicalizeAndCheckFileIdentifier($fileName);
        $absolutePath = $this->getStreamWrapperPath($targetFileIdentifier);

        if ($this->folderExists($targetFileIdentifier)) {
            throw new ExistingTargetFileNameException('A folder with the name of the created file already exists', 1689242076);
        }

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
     * @return string the Identifier of the new file
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     * @throws ExistingTargetFileNameException
     */
    public function copyFileWithinStorage(string $fileIdentifier, string $targetFolderIdentifier, string $fileName): string
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $targetFileIdentifier = $this->canonicalizeAndCheckFileIdentifier($targetFolderIdentifier . $fileName);

        $sourcePath = $this->getStreamWrapperPath($fileIdentifier);
        $targetPath = $this->getStreamWrapperPath($targetFileIdentifier);

        if ($this->folderExists($targetFileIdentifier)) {
            throw new ExistingTargetFileNameException('A folder with the name of the copied file already exists', 1689242141);
        }

        copy($sourcePath, $targetPath);

        $this->flushCacheEntriesForFolder($targetFolderIdentifier);
        unset($this->fileExistsCache[rtrim($sourcePath, '/')], $this->fileExistsCache[rtrim($targetPath, '/')]);

        return $targetFileIdentifier;
    }

    /**
     * Renames a file in this storage.
     *
     * @param string $newName The target path (including the file name!)
     * @return string The identifier of the file after renaming
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     * @throws ExistingTargetFileNameException
     */
    public function renameFile(string $fileIdentifier, string $newName): string
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

        $newIdentifier = $this->canonicalizeAndCheckFileIdentifier($parentFolderName . $newName);

        if ($this->fileExists($newIdentifier) || $this->folderExists($newIdentifier)) {
            throw new ExistingTargetFileNameException('A file or folder with the name of the renamed file already exists');
        }

        $oldPath = $this->getStreamWrapperPath($fileIdentifier);
        $newPath = $this->getStreamWrapperPath($newIdentifier);

        rename($oldPath, $newPath);

        $this->flushCacheEntriesForFolder($parentFolderName);
        unset($this->fileExistsCache[rtrim($oldPath, '/')], $this->fileExistsCache[rtrim($newPath, '/')]);

        return $newIdentifier;
    }

    /**
     * Replaces a file with file in local file system.
     *
     * @return bool TRUE if the operation succeeded
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     */
    public function replaceFile(string $fileIdentifier, string $localFilePath): bool
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $filePath = $this->getStreamWrapperPath($fileIdentifier);

        $this->flushCacheEntriesForFolder(dirname($fileIdentifier));
        unset($this->fileExistsCache[rtrim($filePath, '/')]);

        $this->temporaryFiles[$fileIdentifier] = $localFilePath;
        return copy($localFilePath, $filePath);
    }

    /**
     * Removes a file from the filesystem. This does not check if the file is
     * still used or if it is a bad idea to delete it for some other reason
     * this has to be taken care of in the upper layers (e.g. the Storage)!
     *
     * @return bool TRUE if deleting the file succeeded
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     */
    public function deleteFile(string $fileIdentifier): bool
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $path = $this->getStreamWrapperPath($fileIdentifier);

        $this->flushCacheEntriesForFolder(dirname($fileIdentifier));
        unset($this->fileExistsCache[rtrim($path, '/')]);

        return unlink($path);
    }

    /**
     * Creates a hash for a file.
     *
     * @param string $hashAlgorithm The hash algorithm to use
     * @throws InvalidPathException
     */
    public function hash(string $fileIdentifier, string $hashAlgorithm): string
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $path = $this->getStreamWrapperPath($fileIdentifier);
        if (!$this->fileExists($fileIdentifier)) {
            // The ResourceStorage catches an empty hash and handles
            return '';
        }

        $hash = match ($hashAlgorithm) {
            'sha1' => sha1_file($path),
            'md5' => md5_file($path),
            default => throw new \RuntimeException(
                sprintf('Hash algorithm "%s" is not implemented.', $hashAlgorithm),
                1329644451
            ),
        };

        if ($hash === false) {
            throw new \RuntimeException(
                sprintf('Could not hash file "%s" with hash algorithm "%s".', $fileIdentifier, $hashAlgorithm),
                1685440788
            );
        }

        return $hash;
    }

    /**
     * Moves a file *within* the current storage.
     * Note that this is only about an inner-storage move action,
     * where a file is just moved to another folder in the same storage.
     *
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     * @throws ExistingTargetFileNameException
     */
    public function moveFileWithinStorage(string $fileIdentifier, string $targetFolderIdentifier, string $newFileName): string
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $targetFileIdentifier = $this->canonicalizeAndCheckFileIdentifier($targetFolderIdentifier . $newFileName);

        if ($this->folderExists($targetFileIdentifier)) {
            throw new ExistingTargetFileNameException('A folder with the name of the moved file already exists', 1689242183);
        }

        $sourcePath = $this->getStreamWrapperPath($fileIdentifier);
        $targetPath = $this->getStreamWrapperPath($targetFileIdentifier);

        $this->flushCacheEntriesForFolder(dirname($fileIdentifier));
        $this->flushCacheEntriesForFolder($targetFolderIdentifier);

        rename($sourcePath, $targetPath);
        unset($this->fileExistsCache[rtrim($sourcePath, '/')], $this->fileExistsCache[rtrim($targetPath, '/')]);

        return $targetFileIdentifier;
    }

    /**
     * Folder equivalent to moveFileWithinStorage().
     *
     * @return array All files which are affected, map of old => new file identifiers
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     * @throws ExistingTargetFolderException
     */
    public function moveFolderWithinStorage(string $sourceFolderIdentifier, string $targetFolderIdentifier, string $newFolderName): array
    {
        $sourceFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($sourceFolderIdentifier);
        $targetFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($targetFolderIdentifier . $newFolderName);

        if ($this->fileExists($targetFolderIdentifier) || $this->folderExists($targetFolderIdentifier)) {
            throw new ExistingTargetFolderException('A file or folder with the name of the moved folder already exists', 1689242245);
        }

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
            unset(
                $this->folderExistsCache[rtrim($oldEntryPath, '/')],
                $this->folderExistsCache[rtrim($newEntryPath, '/')]
            );

            $renamedEntries[$oldEntryIdentifier] = $newEntryIdentifier;
        }

        rename($oldPath, $newPath);

        $renamedEntries[$sourceFolderIdentifier] = $targetFolderIdentifier;

        $this->flushCacheEntriesForFolder($sourceFolderIdentifier);
        $this->flushCacheEntriesForFolder(dirname($sourceFolderIdentifier));
        $this->flushCacheEntriesForFolder(dirname($targetFolderIdentifier));
        unset($this->folderExistsCache[rtrim($oldPath, '/')], $this->folderExistsCache[rtrim($newPath, '/')]);

        return $renamedEntries;
    }

    /**
     * Folder equivalent to copyFileWithinStorage().
     *
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     * @throws ExistingTargetFileNameException
     */
    public function copyFolderWithinStorage(string $sourceFolderIdentifier, string $targetFolderIdentifier, string $newFolderName): bool
    {
        $sourceFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($sourceFolderIdentifier);
        $targetFolderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($targetFolderIdentifier) .
            ltrim($this->canonicalizeAndCheckFolderIdentifier($newFolderName), '/');

        $sourceDirectoryContents = $this->resolveFolderEntries($sourceFolderIdentifier, true, true, true);

        if ($this->fileExists($targetFolderIdentifier) || $this->folderExists($targetFolderIdentifier)) {
            throw new ExistingTargetFileNameException('A file or folder with the name of the copied folder already exists');
        }

        /**
         * Make sure the target folder exists before trying to copy folders.
         * The TYPO3 ResourceDriver will throw an exception when copying files in the filelist or at processing images.
         */
        if (!$this->folderExists($targetFolderIdentifier)) {
            $this->createFolder($targetFolderIdentifier);
        }

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
            if (str_ends_with($sourcePath, '/')) {
                /**
                 * We do not care about the directory permissions by ourselves, but let the Amazon S3 StreamWrapper for
                 * mkdir decide the correct ACL for the directory. The StreamWrapper will execute decoct() with the
                 * permissions value. If we set it to null or 0, it will output 0 and will go with the default ACL.
                 * The value null can not be used because it will throw errors when PHP is set to strict types.
                 * @see https://github.com/aws/aws-sdk-php/blob/master/src/S3/StreamWrapper.php#L873-L880
                 */
                if (!mkdir($targetPath, 0, true) && !is_dir($targetPath)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $targetPath));
                }
            } else {
                copy($sourcePath, $targetPath);
            }

            unset($this->folderExistsCache[rtrim($sourcePath, '/')], $this->folderExistsCache[rtrim($targetPath, '/')]);
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
     * @return string The file contents
     * @throws InvalidPathException
     */
    public function getFileContents(string $fileIdentifier): string
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $path = $this->getStreamWrapperPath($fileIdentifier);

        /**
         * Contents of online media files are cached because TYPO3 core reads
         * them (.youtube, .vimeo, ...) through getFileContents() on every render
         * of the file list or a media element: the only cache around it is an
         * instance property in AbstractOnlineMediaHelper::getOnlineMediaId()
         * and OnlineMediaHelperRegistry creates a fresh helper instance per call,
         * so nothing is reused, not even within a single request (verified up to
         * the TYPO3 v14 main branch, June 2026). On local storage that read is
         * free, on S3 it is a remote call per file per render.
         *
         * This cache can be removed once all supported TYPO3 versions stop
         * calling getFileContents() per render, i.e. when core persists the
         * online media id (e.g. in sys_file_metadata) or caches it across
         * requests. The 2048 byte limit matches the limit core applies to media
         * identifiers in AbstractOnlineMediaHelper::getOnlineMediaId().
         */
        $isOnlineMedia = $this->isOnlineMediaFile($fileIdentifier);

        if ($isOnlineMedia) {
            $cacheEntryIdentifier = Cache::buildEntryIdentifier($path, Cache::PREFIX_FILE_CONTENTS);
            $contents = Cache::getCacheFrontend()->get($cacheEntryIdentifier);
            if ($contents !== false) {
                return (string)$contents;
            }
        }

        $contents = file_get_contents($path);

        if ($isOnlineMedia && $contents !== false && strlen($contents) <= 2048) {
            // tagged with the parent folder so every write path that calls
            // flushCacheEntriesForFolder() invalidates it as well
            $parentFolderPath = $this->getStreamWrapperPath(
                $this->getParentFolderIdentifierOfIdentifier($fileIdentifier)
            );
            Cache::getCacheFrontend()->set(
                $cacheEntryIdentifier,
                $contents,
                [Cache::buildEntryIdentifier($parentFolderPath, Cache::TAG_FOLDER_CONTENTS)],
                0
            );
        }

        return $contents === false ? '' : $contents;
    }

    /**
     * Sets the contents of a file to the specified value.
     *
     * @return int The number of bytes written to the file
     * @throws InvalidPathException
     */
    public function setFileContents(string $fileIdentifier, string $contents): int
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
        $path = $this->getStreamWrapperPath($fileIdentifier);

        $file = file_put_contents($path, $contents);

        if ($file === false) {
            throw new \RuntimeException(sprintf('File "%s" was not created', $fileIdentifier));
        }

        // keep the online media contents cache in sync, see getFileContents()
        Cache::getCacheFrontend()->remove(Cache::buildEntryIdentifier($path, Cache::PREFIX_FILE_CONTENTS));

        return $file;
    }

    /**
     * Checks if a file extension has a registered online media helper, only
     * those files are eligible for the contents cache in getFileContents().
     */
    protected function isOnlineMediaFile(string $fileIdentifier): bool
    {
        $fileExtension = strtolower(pathinfo($fileIdentifier, PATHINFO_EXTENSION));

        return $fileExtension !== ''
            && GeneralUtility::makeInstance(OnlineMediaHelperRegistry::class)->hasOnlineMediaHelper($fileExtension);
    }

    /**
     * Checks if a file inside a folder exists
     *
     * @throws InvalidPathException
     */
    public function fileExistsInFolder(string $fileName, string $folderIdentifier): bool
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $fileName = $this->canonicalizeAndCheckFileIdentifier($fileName);

        return $this->fileExists($folderIdentifier . $fileName);
    }

    /**
     * Checks if a folder inside a folder exists.
     */
    public function folderExistsInFolder(string $folderName, string $folderIdentifier): bool
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $folderName = $this->canonicalizeAndCheckFolderIdentifier($folderName);

        return $this->folderExists($folderIdentifier . $folderName);
    }

    /**
     * Returns a path to a local copy of a file for processing it. When changing the
     * file, you have to take care of replacing the current version yourself!
     *
     * @param bool $writable Set this to FALSE if you only need the file for read
     *                       operations. This might speed up things, e.g. by using
     *                       a cached local version. Never modify the file if you
     *                       have set this flag!
     * @return string The path to the file on the local disk
     * @throws InvalidPathException
     * @throws \RuntimeException
     */
    public function getFileForLocalProcessing(string $fileIdentifier, bool $writable = true): string
    {
        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);

        if (!$this->fileExists($fileIdentifier)) {
            // LocalDriver throws a RuntimeException if the file does not exist. We want the same behaviour.
            throw new \RuntimeException(
                sprintf('File "%s" does no longer exist on the S3 storage', $fileIdentifier),
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
     */
    public function getPermissions(string $identifier): array
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
     *
     * @throws InvalidPathException
     */
    public function dumpFileContents(string $identifier): void
    {
        readfile($this->getStreamWrapperPath($this->canonicalizeAndCheckFileIdentifier($identifier)), false);
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
     * @param string $identifier identifier to be checked against $folderIdentifier
     * @return bool TRUE if $content is within or matches $folderIdentifier
     * @throws InvalidPathException
     */
    public function isWithin(string $folderIdentifier, string $identifier): bool
    {
        $folderIdentifier = $this->canonicalizeAndCheckFileIdentifier($folderIdentifier);
        $identifier = $this->canonicalizeAndCheckFileIdentifier($identifier);
        return $folderIdentifier === $identifier
            || ($folderIdentifier !== '' && str_starts_with($identifier, $folderIdentifier));
    }

    /**
     * Returns information about a file.
     *
     * @param array $propertiesToExtract Array of properties which are be extracted
     *                                   If empty all will be extracted
     * @throws InvalidPathException
     */
    public function getFileInfoByIdentifier(string $fileIdentifier, array $propertiesToExtract = []): array
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
     */
    protected function extractFileInformation(
        string $fileIdentifier,
        string $path,
        array $propertiesToExtract = []
    ): array {
        if ($propertiesToExtract === []) {
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
     * @throws \InvalidArgumentException
     */
    public function getSpecificFileInformation(string $fileIdentifier, string $path, string $property): string|int|array|false|null
    {
        return match ($property) {
            'size' => (int)filesize($path),
            'atime' => fileatime($path),
            'mtime' => filemtime($path),
            'ctime' => filectime($path),
            'name' => basename($fileIdentifier),
            'extension' => pathinfo($fileIdentifier, PATHINFO_EXTENSION),
            'mimetype' => $this->getFileMimeType($path),
            'identifier' => $fileIdentifier,
            'storage' => $this->storageUid,
            'identifier_hash' => $this->hashIdentifier($fileIdentifier),
            'folder_hash' => $this->hashIdentifier(PathUtility::dirname($fileIdentifier)),
            default => throw new \InvalidArgumentException(
                sprintf('The information "%s" is not available.', $property),
                1597926187
            ),
        };
    }

    public function getFileMimeType(string $path): string
    {
        $fileInfo = GeneralUtility::makeInstance(FileInfo::class, $path);
        return $fileInfo->getMimeType() ?: 'application/octet-stream';
    }

    /**
     * Returns information about a file.
     */
    public function getFolderInfoByIdentifier(string $folderIdentifier): array
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);

        return [
            'identifier' => $folderIdentifier,
            'name' => basename($folderIdentifier),
            // S3 does not implement mtime or ctime on 'folder' objects. To prevent warnings, just return 0 as timestamp
            'mtime' => 0,
            'ctime' => 0,
            'storage' => $this->storageUid
        ];
    }

    /**
     * Returns a list of files inside the specified path
     *
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                     Among them may be: '' (empty, no sorting), name,
     *                     fileext, size, tstamp and rw.
     *                     If a driver does not support the given property, it
     *                     should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     * @return array of FileIdentifiers
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     */
    public function getFilesInFolder(
        string $folderIdentifier,
        int $start = 0,
        int $numberOfItems = 0,
        bool $recursive = false,
        array $filenameFilterCallbacks = [],
        string $sort = '',
        bool $sortRev = false
    ): array {
        if ($start === false && $numberOfItems === false) {
            return [];
        }

        $folderEntries = $this->resolveFolderEntries(
            $folderIdentifier,
            $recursive,
            true,
            false,
            $filenameFilterCallbacks,
            true
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
     * @return string file identifier
     * @throws InvalidPathException
     */
    public function getFileInFolder(string $fileName, string $folderIdentifier): string
    {
        return $this->canonicalizeAndCheckFileIdentifier($folderIdentifier . '/' . $fileName);
    }

    /**
     * Returns a list of folders inside the specified path
     *
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @param string $sort Property name used to sort the items.
     *                     Among them may be: '' (empty, no sorting), name,
     *                     fileext, size, tstamp and rw.
     *                     If a driver does not support the given property, it
     *                     should fall back to "name".
     * @param bool $sortRev TRUE to indicate reverse sorting (last to first)
     * @return array of Folder Identifier
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     */
    public function getFoldersInFolder(
        string $folderIdentifier,
        int $start = 0,
        int $numberOfItems = 0,
        bool $recursive = false,
        array $folderNameFilterCallbacks = [],
        string $sort = '',
        bool $sortRev = false
    ): array {
        if ($start === false && $numberOfItems === false) {
            return [];
        }

        $folderEntries = $this->resolveFolderEntries(
            $folderIdentifier,
            $recursive,
            false,
            true,
            $folderNameFilterCallbacks,
            true
        );

        if (!$recursive) {
            $folderEntries = $this->sortFolderEntries($folderEntries);
        }

        return array_slice(
            $folderEntries,
            $start,
            ($numberOfItems > 0 ? $numberOfItems : null)
        );
    }

    /**
     * Returns the identifier of a folder inside the folder
     *
     * @param string $folderName The name of the target folder
     * @return string folder identifier
     */
    public function getFolderInFolder(string $folderName, string $folderIdentifier): string
    {
        return $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier . '/' . $folderName);
    }

    /**
     * Returns the number of files inside the specified path
     *
     * @param array $filenameFilterCallbacks callbacks for filtering the items
     * @return int Number of files in folder
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     */
    public function countFilesInFolder(string $folderIdentifier, bool $recursive = false, array $filenameFilterCallbacks = []): int
    {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $path = $this->getStreamWrapperPath($folderIdentifier);

        $cacheEntryIdentifier = Cache::buildEntryIdentifier(
            $path,
            $recursive ? Cache::PREFIX_RECURSIVE_FILE_COUNT : Cache::PREFIX_FILE_COUNT
        );

        $count = Cache::getCacheFrontend()->get($cacheEntryIdentifier);
        if ($count === false) {
            $count = count($this->getFilesInFolder($folderIdentifier, 0, 0, $recursive, $filenameFilterCallbacks));
            $cacheTags = [Cache::buildEntryIdentifier(
                $path,
                $recursive ? Cache::TAG_FOLDER_SUBTREE : Cache::TAG_FOLDER_CONTENTS
            )];
            Cache::getCacheFrontend()->set($cacheEntryIdentifier, $count, $cacheTags, 0);
        }

        return (int)$count;
    }

    /**
     * Returns the number of folders inside the specified path
     *
     * @param array $folderNameFilterCallbacks callbacks for filtering the items
     * @return int Number of folders in folder
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     */
    public function countFoldersInFolder(
        string $folderIdentifier,
        bool $recursive = false,
        array $folderNameFilterCallbacks = []
    ): int {
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $path = $this->getStreamWrapperPath($folderIdentifier);

        $cacheEntryIdentifier = Cache::buildEntryIdentifier(
            $path,
            $recursive ? Cache::PREFIX_RECURSIVE_FOLDER_COUNT : Cache::PREFIX_FOLDER_COUNT
        );

        $count = Cache::getCacheFrontend()->get($cacheEntryIdentifier);
        if ($count === false) {
            $count = count($this->getFoldersInFolder($folderIdentifier, 0, 0, $recursive, $folderNameFilterCallbacks));
            $cacheTags = [Cache::buildEntryIdentifier(
                $path,
                $recursive ? Cache::TAG_FOLDER_SUBTREE : Cache::TAG_FOLDER_CONTENTS
            )];
            Cache::getCacheFrontend()->set($cacheEntryIdentifier, $count, $cacheTags, 0);
        }

        return (int)$count;
    }

    /**
     * @throws InvalidPathException
     */
    public function streamFile(string $identifier, array $properties): ResponseInterface
    {
        $fileInfo = $this->getFileInfoByIdentifier($identifier, ['name', 'mimetype', 'mtime', 'size']);
        $downloadName = $properties['filename_overwrite'] ?? $fileInfo['name'] ?? '';
        $mimeType = $properties['mimetype_overwrite'] ?? $fileInfo['mimetype'] ?? '';
        $contentDisposition = ($properties['as_download'] ?? false) ? 'attachment' : 'inline';

        $stream = new Stream('php://temp', 'rw');
        $stream->write($this->getFileContents($identifier));
        $stream->rewind();

        return new Response(
            $stream,
            200,
            [
                'Content-Disposition' => $contentDisposition . '; filename="' . $downloadName . '"',
                'Content-Type' => $mimeType,
                'Content-Length' => (string)$fileInfo['size'],
                'Last-Modified' => gmdate('D, d M Y H:i:s', $fileInfo['mtime']) . ' GMT',
                // Cache-Control header is needed here to solve an issue with browser IE8 and lower
                // See for more information: http://support.microsoft.com/kb/323308
                'Cache-Control' => '',
            ]
        );
    }

    /**
     * @param $fileName
     * @throws InvalidFileNameException
     */
    public function sanitizeFileName(string $fileName): string
    {
        $fileName = \Normalizer::normalize($fileName) ?: $fileName;

        // Unlike the LocalDriver, we don't need an exception for UTF-8 here since we use S3 as storage.
        // Strip the filename from unwanted characters, replace them with an underscore
        $cleanFileName = (string)preg_replace('/[#$%^&*+=\[\]\'`;,\/{}|":<>?~\\\\]/', '_', trim($fileName));
        $cleanFileName = rtrim($cleanFileName, '.');
        if ($cleanFileName === '') {
            throw new InvalidFileNameException(
                'File name ' . $fileName . ' is invalid.',
                1320288991
            );
        }

        return $cleanFileName;
    }

    /**
     * Returns the StreamWrapper path of a file or folder.
     *
     * @param FileInterface|Folder|string $file
     * @throws \RuntimeException
     */
    protected function getStreamWrapperPath($file): string
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
            throw new \RuntimeException(sprintf('Type "%s" is not supported.', gettype($file)), 1325191178);
        }

        return $basePath . $identifier;
    }

    /**
     * @param $path
     */
    protected function stripStreamWrapperPath($path): string
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
     *
     * @throws InvalidPathException
     * @throws NoSuchCacheException
     */
    protected function resolveFolderEntries(
        string $folderIdentifier,
        bool $recursive = false,
        bool $includeFiles = true,
        bool $includeDirectories = true,
        array $filterMethods = [],
        bool $excludeProcessingFolder = false
    ): array {
        $excludedFolders = $this->configuration['excludedFolders'] ?? [];
        if (in_array($folderIdentifier, $excludedFolders, true)) {
            return [];
        }
        if ($excludeProcessingFolder) {
            $excludedFolders[] = $this->getProcessingFolder();
        }
        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);

        $entries = $recursive
            ? $this->getRecursiveFolderEntries($folderIdentifier)
            : $this->getDirectFolderEntries($folderIdentifier);

        $directoryEntries = [];
        foreach ($entries as $entry) {
            $isDirectory = str_ends_with($entry, '/');
            if ($isDirectory && !$includeDirectories) {
                continue;
            }
            if (!$isDirectory && !$includeFiles) {
                continue;
            }
            if ($this->isWithinExcludedFolder($entry, $folderIdentifier, $excludedFolders)) {
                continue;
            }
            if (!$this->applyFilterMethodsToDirectoryItem(
                $filterMethods,
                basename($entry),
                $entry,
                $this->getParentFolderIdentifierOfIdentifier($entry)
            )) {
                continue;
            }
            $directoryEntries[$entry] = $entry;
        }

        return array_values($directoryEntries);
    }

    /**
     * Lists the direct children of a folder, using one cache entry per folder.
     *
     * @return string[] file and folder identifiers, folders end with a slash
     * @throws NoSuchCacheException
     */
    protected function getDirectFolderEntries(string $folderIdentifier): array
    {
        $path = $this->getStreamWrapperPath($folderIdentifier);

        $iteratorMode = \FilesystemIterator::UNIX_PATHS |
            \FilesystemIterator::SKIP_DOTS |
            \FilesystemIterator::CURRENT_AS_FILEINFO;

        $listing = new CachedDirectoryListing(
            Cache::getCacheFrontend(),
            function (\SplFileInfo $fileInfo): string {
                $entryIdentifier = $this->stripStreamWrapperPath($fileInfo->getPathname());
                if ($fileInfo->isDir()) {
                    return $this->canonicalizeAndCheckFolderIdentifier($entryIdentifier);
                }
                return $this->canonicalizeAndCheckFileIdentifier($entryIdentifier);
            }
        );

        return $listing->getEntries($path, $iteratorMode);
    }

    /**
     * Lists a complete folder subtree: files directly in the folder as-is,
     * each subfolder via its own cached (or freshly scanned) subtree. Caching
     * per subfolder means a write only invalidates the subtrees of its
     * ancestor folders while sibling subtrees stay warm; the composed result
     * itself is therefore only memoized per request. Excluded and processing
     * folders are skipped without fetching their keys from S3.
     *
     * @return string[] file and folder identifiers, folders end with a slash
     * @throws NoSuchCacheException
     */
    protected function getRecursiveFolderEntries(string $folderIdentifier): array
    {
        if (array_key_exists($folderIdentifier, $this->recursiveFolderEntriesCache)) {
            return $this->recursiveFolderEntriesCache[$folderIdentifier];
        }

        $cacheFrontend = Cache::getCacheFrontend();
        $path = $this->getStreamWrapperPath($folderIdentifier);

        $entries = $cacheFrontend->get(Cache::buildEntryIdentifier($path, Cache::PREFIX_RECURSIVE_LISTING));
        if ($entries === false) {
            $excludedFolderNames = $this->configuration['excludedFolders'] ?? [];
            $excludedFolderNames[] = $this->getProcessingFolder();
            $excludedFolderNames = array_unique($excludedFolderNames);

            $entries = [];

            foreach ($this->getDirectFolderEntries($folderIdentifier) as $childIdentifier) {
                if (!str_ends_with($childIdentifier, '/')) {
                    $entries[] = $childIdentifier;
                    continue;
                }

                if (in_array(basename($childIdentifier), $excludedFolderNames, true)) {
                    continue;
                }

                $entries[] = $childIdentifier;
                $childPath = $this->getStreamWrapperPath($childIdentifier);
                $childEntries = $cacheFrontend->get(
                    Cache::buildEntryIdentifier($childPath, Cache::PREFIX_RECURSIVE_LISTING)
                );

                if ($childEntries === false) {
                    $childEntries = $this->scanFolderSubtree($childIdentifier);
                }

                foreach ($childEntries as $childEntry) {
                    $entries[] = $childEntry;
                }
            }
        }

        $this->recursiveFolderEntriesCache[$folderIdentifier] = $entries;

        return $entries;
    }

    /**
     * Scans a complete folder subtree with one paginated ListObjectsV2 call.
     * Folders are derived from the object keys, marker objects are optional.
     * The result is cached per subtree, and the response also fills the
     * per-folder listing and folder stat caches. Excluded and processing
     * folders are skipped.
     *
     * @return string[] file and folder identifiers, folders end with a slash
     * @throws NoSuchCacheException
     */
    protected function scanFolderSubtree(string $folderIdentifier): array
    {
        $cacheFrontend = Cache::getCacheFrontend();
        $path = $this->getStreamWrapperPath($folderIdentifier);

        $prefix = ltrim($this->getBasePath() . $folderIdentifier, '/');
        $entries = [];
        $childrenPerFolder = [$folderIdentifier => []];
        $statCache = new Cache();

        // contents of the processing folder and configured excluded folders are
        // skipped during the scan, both sets are static per storage so the cache
        // entry stays deterministic
        $excludedFolderNames = $this->configuration['excludedFolders'] ?? [];
        $excludedFolderNames[] = $this->getProcessingFolder();
        $excludedPathParts = [];
        foreach (array_unique($excludedFolderNames) as $excludedFolderName) {
            $excludedPathParts[] = '/' . $excludedFolderName . '/';
        }

        $paginator = $this->s3Client->getPaginator('ListObjectsV2', [
            'Bucket' => $this->configuration['bucket'],
            'Prefix' => $prefix,
        ]);

        foreach ($paginator as $result) {
            foreach (($result['Contents'] ?? []) as $object) {
                $relativeKey = substr((string)$object['Key'], strlen($prefix));
                if ($relativeKey === '') {
                    // the marker object of the listed folder itself
                    continue;
                }

                $relativePath = '/' . $relativeKey;
                foreach ($excludedPathParts as $excludedPathPart) {
                    if (str_contains($relativePath, $excludedPathPart)) {
                        continue 2;
                    }
                }

                // identifiers are built by concatenation, canonicalization is
                // expensive and only needed for anomalous object keys
                $requiresCanonicalization = str_contains($relativeKey, '//') || str_contains($relativeKey, './');

                $isFolderMarker = str_ends_with($relativeKey, '/');
                $segments = explode('/', trim($relativeKey, '/'));
                $fileName = $isFolderMarker ? null : array_pop($segments);

                $parentIdentifier = $folderIdentifier;
                foreach ($segments as $segment) {
                    $currentIdentifier = $parentIdentifier . $segment . '/';
                    if ($requiresCanonicalization) {
                        $currentIdentifier = $this->canonicalizeAndCheckFolderIdentifier($currentIdentifier);
                    }
                    if (!isset($childrenPerFolder[$currentIdentifier])) {
                        $childrenPerFolder[$currentIdentifier] = [];
                        $childrenPerFolder[$parentIdentifier][] = $currentIdentifier;
                        $entries[$currentIdentifier] = $currentIdentifier;
                        $statCache->set($this->getStreamWrapperPath($currentIdentifier), $this->buildFolderStat());
                    }
                    $parentIdentifier = $currentIdentifier;
                }

                if ($fileName !== null && $fileName !== '') {
                    $fileIdentifier = $parentIdentifier . $fileName;
                    if ($requiresCanonicalization) {
                        $fileIdentifier = $this->canonicalizeAndCheckFileIdentifier($fileIdentifier);
                    }
                    $entries[$fileIdentifier] = $fileIdentifier;
                    $childrenPerFolder[$parentIdentifier][] = $fileIdentifier;
                }
            }
        }

        $entries = array_values($entries);

        foreach ($childrenPerFolder as $currentFolderIdentifier => $children) {
            $folderPath = $this->getStreamWrapperPath($currentFolderIdentifier);
            $cacheFrontend->set(
                Cache::buildEntryIdentifier($folderPath, Cache::PREFIX_LISTING),
                $children,
                [Cache::buildEntryIdentifier($folderPath, Cache::TAG_FOLDER_CONTENTS)],
                0
            );
        }

        $cacheFrontend->set(
            Cache::buildEntryIdentifier($path, Cache::PREFIX_RECURSIVE_LISTING),
            $entries,
            [Cache::buildEntryIdentifier($path, Cache::TAG_FOLDER_SUBTREE)],
            0
        );

        return $entries;
    }

    /**
     * Checks if an entry is in or below a folder excluded by name, only
     * looking at path segments below the folder being listed.
     */
    protected function isWithinExcludedFolder(
        string $entryIdentifier,
        string $rootFolderIdentifier,
        array $excludedFolders
    ): bool {
        if ($excludedFolders === []) {
            return false;
        }

        $relativeIdentifier = substr($entryIdentifier, strlen($rootFolderIdentifier));
        $segments = explode('/', rtrim($relativeIdentifier, '/'));
        if (!str_ends_with($entryIdentifier, '/')) {
            // the file name itself is not a folder segment
            array_pop($segments);
        }

        foreach ($segments as $segment) {
            if (in_array($segment, $excludedFolders, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stat array for a folder in the format the AWS StreamWrapper caches
     * and expects on url_stat lookups.
     *
     * @see \Aws\S3\StreamWrapper::formatUrlStat()
     */
    protected function buildFolderStat(): array
    {
        $stat = $this->getStatTemplate();
        $stat['mode'] = $stat[2] = 0040777;

        return $stat;
    }

    /**
     * @see \Aws\S3\StreamWrapper::getStatTemplate()
     *
     * Copied because the SDK method is private; S3 has no metadata for
     * folders (they are only inferred from object keys), so the all-zeros
     * template matching PHP's stat() format is the only possible content.
     */
    protected function getStatTemplate(): array
    {
        return [
            0 => 0, 'dev' => 0,
            1 => 0, 'ino' => 0,
            2 => 0, 'mode' => 0,
            3 => 0, 'nlink' => 0,
            4 => 0, 'uid' => 0,
            5 => 0, 'gid' => 0,
            6 => -1, 'rdev' => -1,
            7 => 0, 'size' => 0,
            8 => 0, 'atime' => 0,
            9 => 0, 'mtime' => 0,
            10 => 0, 'ctime' => 0,
            11 => -1, 'blksize' => -1,
            12 => -1, 'blocks' => -1,
        ];
    }

    /**
     * Applies a set of filter methods to a file name to find out if it should be used or not. This is e.g. used by
     * directory listings.
     *
     * @param array $filterMethods The filter methods to use
     * @throws \RuntimeException
     */
    protected function applyFilterMethodsToDirectoryItem(
        array $filterMethods,
        string $itemName,
        string $itemIdentifier,
        string $parentIdentifier
    ): bool {
        foreach ($filterMethods as $filter) {
            if (
                is_callable($filter) && $itemName !== '' && $itemIdentifier !== '' && $parentIdentifier !== ''
            ) {
                $result = $filter($itemName, $itemIdentifier, $parentIdentifier, [], $this);
                // We have to use -1 as the „don't include“ return value, as call_user_func() will return FALSE
                // If calling the method succeeded and thus we can't use that as a return value.
                if ($result === -1) {
                    return false;
                }
                if ($result === false) {
                    throw new \RuntimeException(
                        sprintf('Could not apply file/folder name filter %s::%s', $filter[0], $filter[1]),
                        1476046425
                    );
                }
            }
        }
        return true;
    }

    /**
     * Sort the directory entries by a certain key
     */
    protected function sortFolderEntries(array $folderEntries, string $method = '', bool $reverse = false): array
    {
        $sortableEntries = [];

        foreach ($folderEntries as $identifier) {
            $sortingValue = null;

            if ($method === 'fileext') {
                $sortingValue = pathinfo((string) $identifier, PATHINFO_EXTENSION);
            }

            if ($method === 'rw') {
                // should be checked with the storage rather than the driver,
                // the underlying might allow more than a user in TYPO3
                $permissions = $this->getPermissions($identifier);

                $sortingValue = ($permissions['r'] ? 'R' : '');
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
        if ($sortableEntries !== []) {
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

    protected function getStorage(): ResourceStorage
    {
        if (!$this->storage) {
            /** @var StorageRepository $storageRepository */
            $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
            $this->storage = $storageRepository->findByUid($this->storageUid);
        }

        return $this->storage;
    }

    protected function getProcessingFolder(): string
    {
        return $this->getStorage()->getProcessingFolder()->getName();
    }

    protected function getBasePath(): string
    {
        if (!empty($this->configuration['basePath'] ?? '')) {
            return '/' . trim($this->configuration['basePath'], '/');
        }

        return '';
    }

    /**
     * @throws NoSuchCacheException
     */
    protected function flushCacheEntriesForFolder(string $folderIdentifier): void
    {
        $this->recursiveFolderEntriesCache = [];

        $folderIdentifier = $this->canonicalizeAndCheckFolderIdentifier($folderIdentifier);
        $path = $this->getStreamWrapperPath($folderIdentifier);

        // direct listings and counts are tagged with the path of the folder itself,
        // recursive listings rooted at this folder or at any ancestor contain this
        // folder's entries as well and carry a subtree tag per subtree root
        $tags = [
            Cache::buildEntryIdentifier($path, Cache::TAG_FOLDER_CONTENTS),
            Cache::buildEntryIdentifier($path, Cache::TAG_FOLDER_SUBTREE),
        ];

        $currentIdentifier = $folderIdentifier;
        while ($currentIdentifier !== '/') {
            $parentIdentifier = dirname(rtrim($currentIdentifier, '/'));
            $currentIdentifier = (in_array($parentIdentifier, ['/', '.', ''], true))
                ? '/'
                : $parentIdentifier . '/';

            $tags[] = Cache::buildEntryIdentifier(
                $this->getStreamWrapperPath($currentIdentifier),
                Cache::TAG_FOLDER_SUBTREE
            );
        }

        Cache::getCacheFrontend()->flushByTags($tags);
    }
}

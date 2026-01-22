# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [UNRELEASED]

### Added
- Support for TYPO3 13
- DDEV Setup for development
- Automatic CSP registration for the configured buckets

### Changed
- Updated Documentation to the new documentation rendering standards

### Removed
- Support for TYPO3 11
- Support for PHP 7 and 8.0

## [2.3.1] - 2025-12-19
### Changed
- Re-order if conditions in `RemoteObjectUpdateEvent` listener to prevent unnecessary redis calls when an original file is used for processed files

## [2.3.0] - 2025-12-18
### Changed
- Use the `FileInfo` class to determine mime types instead of the (in a core security patch) removed `fileExtensionToMimeType` array
- In the filelist, prevent an unnecessary redis call per file in a folder. This results in a significant performance improvement when listing folders with >1000 files.

## [2.2.0] - 2024-10-25
### Added
- Support for php 8.3

### Changed
- Updated `aws/aws-sdk-php` dependency requirement from 3.269.1 to 3.324.5 to make sure a non-vulnerable version of the sdk is being used

## [2.1.2] - 2024-08-09
### Fixed
- Prevent undefined array key access
- Only call updateCacheControlDirectivesForRemoteObject for processed files that don't use the original

## [2.1.1] - 2023-08-30
### Fixed
- use correct middleware name instead of a copied docs example
- file name sanitization was too strict, we now allow more special characters

## [2.1.0] - 2023-07-31
### Added
- FalS3PreconnectMiddleware that automatically adds preconnect headers for online S3 storages

## [2.0.2] - 2023-07-19
### Fixed
- Spaces and parenthesis should not be sanitized

## [2.0.1] - 2023-07-13
### Fixed
- Some file action methods fail because the check if a file or folder is too generic. Each action needs its own checks on existing files or folders.

## [2.0.0] - 2023-07-12
### Added
- PSR-14 `FlushCacheActionEvent` to add the cache flush action item (TYPO3 v11+)
- PSR-14 `RemoteObjectUpdateEvent` to set remote object cache for after a file has been uploaded, replaced or processed (TYPO3 v10+)
- New Icons API implementation for the S3 driver icons (TYPO3 v11+)
- Strict typing in classes and methods if possible
- Implemented the `DriverInterface::sanitizeFileName` method, so we escape invalid characters in folder and file names (resolves [#56](https://github.com/MaxServ/t3ext-fal_s3/issues/56))

### Changed
- `.gitignore` structure to only include specified files
- Refactored `ToolbarItem` hook implementation for TYPO3 v10 to extend the newly implemented `FlushCacheActionEvent` so these share the same code base
- Refactored `AmazonS3Driver` to use the `MimeType::fromExtension` method instead of the removed `mimetype_from_extension` function
- Simplified `AmazonS3Driver` methods `processConfiguration` and `initialize` to make it better to read, debug and test
- Minimum version of `php` to v7.2
- Minimum version of `aws/aws-sdk-php` to v3.199 to match TYPO3 requirements

### Removed
- TYPO3 v6, v7, v8 and v9 compatibility
- `RecordMonitor` class since this has been replaced by the `ImageDimensionsExtraction` extractor but was kept for backwards compatibility

## [1.14.2] - 2023-09-30
### Fixed
- Error when TYPO3_REQUEST is null

## [1.14.1] - 2023-03-29
### Fixed
- 'Replace' option in the filelist didn't work due to excessive caching

## [1.14.0] - 2022-09-30
### Added
- Caching to `countFilesInFolder` and `countFoldersInFolder` methods

## [1.13.3] - 2022-09-07
### Fixed
- Prevent filelist exception when a directory contains a file of type 'Octet-stream' + directory with the same name

## [1.13.2] - 2022-08-05
### Fixed
- Partly reverted 1.13.1, since it caused performance issues. The calls to `is_file` in `AmazonS3Driver::fileExists` were cached by the StreamWrapper (`StreamWrapper::url_stat`). HeadObject calls however are not cached.

## [1.13.1] - 2022-06-20
### Fixed
- Correctly implement the `fileExists` method by adding a method which requests the `HEAD` of the object in the S3 storage

## [1.13.0] - 2022-02-22
### Added
- Suggestion for `causal/extractor` extension for extended metadata extraction
- Override implementations for Extractor classes so these also work with the `AmazonS3Driver`

## [1.12.1] - 2021-12-30
### Fixed
- mkdir not working reliable with the octdec permissions, let S3 determine the permissions (ACL) inside the StreamWrapper

## [1.12.0] - 2021-12-07
### Added
- Extractor for extracting metadata used by Indexer

## Fixed
- Incorrect width and height metadata after file replace in TYPO3 v10

## [1.11.0] - 2021-08-31
### Added
- TYPO3 V10 support

## [1.10.0] - 2020-12-26
### Added
- .gitattributes file
- Implement flush cache button to clear caches for S3 extension only

### Fixed
- folderCreateMask read from old configuration path

## [1.9.3] - 2019-10-23
### Fixed
- Removed rtrim in folderExists method to prevent files with the same name as a folder

## [1.9.2] - 2019-09-26
### Fixed
- File list showing all files when number of folder exceeds number of items shown
- Metadata update even if no cache control has been set

## [1.9.1] - 2019-03-04
### Fixed
- Set cacheConfiguration after initializing new CacheManager

## [1.9.0] - 2019-01-31
### Added
- TYPO3 V9 support
- Added Unit tests for processConfiguration and getPublicUrl methods
### Fixed
- Unset fileExistsCache when a file is newly created, making sure checks are correct in case a check was done before creating the file

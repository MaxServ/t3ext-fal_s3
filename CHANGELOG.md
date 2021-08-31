# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

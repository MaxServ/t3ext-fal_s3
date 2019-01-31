# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.9.0] - 2019-01-31
### Added
- TYPO3 V9 support
- Added Unit tests for processConfiguration and getPublicUrl methods
### Fixed
- Unset fileExistsCache when a file is newly created, making sure checks are correct in case a check was done before creating the file

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Changed

- Modernized the plugin according to the SolveBeam WordPress Plugin Boilerplate.
- Requires PHP 8.3 and WordPress 6.7 or higher.
- Moved PHP classes from `src/` to `psr-4/` and added `declare(strict_types=1);`.
- `Plugin` is now a singleton (`Plugin::instance( __FILE__ )`) and uses first-class callable syntax for hooks.
- `Period` is now a `readonly` class.
- Replaced the Pronamic CLI build with the Composer/rsync build from the boilerplate.

### Added

- `Requires Plugins: woocommerce` plugin header.
- Rector configuration and PHPStan at level `max`.

## 1.0.0

### Added

- Initial release.

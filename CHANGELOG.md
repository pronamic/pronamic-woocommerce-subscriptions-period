# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-11-03

This is a major release: the plugin now requires PHP 8.3 and WordPress 6.8 or higher, and the PHP classes were restructured.

### Changed

- Modernized the plugin according to the SolveBeam WordPress Plugin Boilerplate.
- Requires PHP 8.3 and WordPress 6.8 or higher.
- Removed `load_plugin_textdomain()` call, WordPress 6.8+ loads translations just-in-time based on the `Text Domain` and `Domain Path` headers.
- Moved PHP classes from `src/` to `psr-4/` and added `declare(strict_types=1);`.
- `Plugin` is now a singleton (`Plugin::instance( __FILE__ )`) and uses first-class callable syntax for hooks.
- `Period` is now a `readonly` class.
- Replaced the Pronamic CLI build with the Composer/rsync build from the boilerplate.

### Added

- `Requires Plugins: woocommerce, woocommerce-subscriptions` plugin header.
- Rector configuration and PHPStan at level `max`.

### Composer

- Changed `automattic/jetpack-autoloader` from `v3.0.8` to `v6.0.3`. Raises the minimum PHP version (7.2 in 4.0, 7.4 in 6.0), drops support for Composer < 2.2 and WordPress 6.5 and earlier (5.0), adds debugging for conflicting autoloaders, and hardens path handling (6.0.3). The package publishes no GitHub releases; changelog: https://github.com/Automattic/jetpack-autoloader/blob/v6.0.3/CHANGELOG.md
- Changed `pronamic/pronamic-wp-updater` from `v1.0.2` to `v1.0.4`. Allows Jetpack Autoloader 3.x through 6.x. Release notes: https://github.com/pronamic/pronamic-wp-updater/releases/tag/v1.0.3, https://github.com/pronamic/pronamic-wp-updater/releases/tag/v1.0.4
- Added `composer/installers` `v2.3.0`, used to install Pronamic packages in the `packages/` folder. Release notes: https://github.com/composer/installers/releases/tag/v2.3.0

Full set of changes: [`1.0.0...2.0.0`](https://github.com/pronamic/pronamic-woocommerce-subscriptions-period/compare/v1.0.0...v2.0.0)

[2.0.0]: https://github.com/pronamic/pronamic-woocommerce-subscriptions-period/releases/tag/v2.0.0

## 1.0.0

### Added

- Initial release.

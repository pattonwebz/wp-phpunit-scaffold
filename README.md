# wp-phpunit-scaffold

Docker-based PHPUnit test scaffold for WordPress plugin development. When installed via Composer, this package automatically copies a `docker-compose.yml`, `phpunit.xml.dist`, test bootstrap, and helper shell scripts into your plugin project — ready to run against a real WordPress + MySQL environment inside Docker with no manual setup.

## Requirements

- PHP 7.4+
- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/) (v2)
- [Composer](https://getcomposer.org/)

## Installation

```bash
composer require pattonwebz/wp-phpunit-scaffold
```

The scaffold runs automatically on `post-install-cmd` and `post-update-cmd`. It detects your plugin slug from the project directory name, derives the plugin constant, and writes the following files into your project (skipping any that already exist):

| Generated file | Purpose |
|---|---|
| `docker-compose.yml` | MySQL + PHPUnit containers |
| `scripts/setup-phpunit.sh` | Orchestrate setup + run |
| `phpunit.xml.dist` | PHPUnit configuration |
| `.env.example` | DB credential template |
| `tests/bootstrap.php` | WP test bootstrap |
| `tests/bin/install-wp-tests.sh` | WP core + test suite downloader |
| `.github/workflows/phpunit.yml` | GitHub Actions CI workflow |

It also adds four convenience scripts to your `composer.json`.

## Usage

### First-time setup

```bash
composer run phpunit:setup
```

This starts the Docker stack, waits for MySQL, downloads the WordPress test suite, and runs PHPUnit.

### Running tests

```bash
composer run phpunit:run
```

### Stopping containers

```bash
composer run phpunit:stop
```

### Opening a shell in the PHPUnit container

```bash
composer run phpunit:shell
```

## Configuration

### Database credentials

The scaffold copies a `.env.example` file into your project root. Copy it to `.env` and adjust as needed:

```bash
cp .env.example .env
```

```dotenv
DB_NAME=wordpress
DB_USER=wordpress
DB_PASSWORD=wordpress
```

If no `.env` is present the defaults `wordpress` / `wordpress` / `wordpress` are used.

### Docker image tag

The PHPUnit container image defaults to `pattonwebz/phpunit-wordpress:1.0.0`. Override it at runtime:

```bash
PHPUNIT_IMAGE_TAG=2.0.0 composer run phpunit:setup
```

Or set it permanently in your shell environment / CI pipeline.

## Running a specific WordPress version

Pass `WP_VERSION` when running setup:

```bash
WP_VERSION=6.4 composer run phpunit:setup
```

The `install-wp-tests.sh` script accepts `latest`, a branch (`6.4`), an exact version (`6.4.2`), or `trunk`.

## GitHub Actions CI

The scaffold copies a ready-to-use workflow to `.github/workflows/phpunit.yml`. It runs PHPUnit against a native MySQL service (no Docker required in CI) across a PHP version matrix.

```
PHP 7.4 ✓   PHP 8.0 ✓   PHP 8.1 ✓   PHP 8.2 ✓
```

The workflow:
- Triggers on pushes and pull requests to `main`/`master`
- Spins up MySQL 5.7 as a service
- Installs SVN and the WordPress test suite directly
- Runs `vendor/bin/phpunit`

No additional configuration is needed — it uses the same DB credentials as your `docker-compose.yml`.

To test against a different PHP version range, edit the `matrix.php-version` array in `.github/workflows/phpunit.yml`.

## Docker image

The `pattonwebz/phpunit-wordpress` image bundles PHP, PHPUnit, Composer, SVN, and the WP CLI toolchain needed to install the WordPress test suite. See the image repository for available tags and PHP version variants.

## Customising generated files

All generated files are standard files in your project — edit them freely after scaffolding. The installer skips any file that already exists, so your modifications are never overwritten on subsequent `composer install` or `composer update` runs.

To re-run the scaffold explicitly:

```bash
composer run scaffold
```

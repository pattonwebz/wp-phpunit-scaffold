# wp-phpunit-scaffold

> Docker-based PHPUnit testing scaffold for WordPress plugins. Copy it in, run setup.sh, start testing.

## What this is

A set of files you copy directly into your WordPress plugin project to get PHPUnit running via Docker. No Composer dependency required — just clone (or download) this repo and copy the `scaffold/` contents into your plugin.

The included `setup.sh` helper replaces the placeholder values (`my-plugin`, `wordpress`, etc.) with your actual plugin details so you're ready to run tests in minutes.

## Requirements

- Docker + Docker Compose
- A WordPress plugin project
- PHPUnit in your project's `vendor/` (add it via `composer require --dev phpunit/phpunit`)

## Quick Start

```bash
# 1. Clone this repo (or download a zip)
git clone https://github.com/pattonwebz/wp-phpunit-scaffold.git

# 2. Copy scaffold files into your plugin project root
cp -r wp-phpunit-scaffold/scaffold/. /path/to/your-plugin/
cp wp-phpunit-scaffold/setup.sh /path/to/your-plugin/

# 3. Run setup.sh from your plugin project root (optional but recommended)
cd /path/to/your-plugin
bash setup.sh

# 4. Copy .env.example → .env and set your actual DB credentials
cp .env.example .env

# 5. Spin up Docker, install WordPress, and run your tests
bash setup-phpunit.sh
```

## What gets copied in

| File | Purpose |
|------|---------|
| `docker-compose.yml` | Defines a MySQL service and a PHPUnit container based on `pattonwebz/phpunit-wordpress` |
| `setup-phpunit.sh` | Starts the Docker stack, waits for MySQL, installs the WordPress test suite, then runs PHPUnit |
| `phpunit.xml.dist` | PHPUnit configuration — test discovery, coverage settings |
| `.env.example` | Template for database credentials used by Docker Compose |
| `tests/bootstrap.php` | WordPress test bootstrap — loads the test framework and your plugin |
| `tests/scripts/install-wp-tests.sh` | Downloads WordPress core and the test library via SVN |
| `.github/workflows/phpunit.yml` | GitHub Actions CI workflow using the native MySQL service (no Docker needed in CI) |

## Manual setup (without setup.sh)

If you prefer to edit files yourself, replace the following placeholder values across the copied files:

| Placeholder | Replace with |
|-------------|-------------|
| `my-plugin` | Your plugin slug (kebab-case, e.g. `my-awesome-plugin`) |
| `my-plugin.php` | Your plugin's main PHP file (e.g. `my-awesome-plugin.php`) |
| `MY_PLUGIN` | Your plugin constant prefix (e.g. `MY_AWESOME_PLUGIN`) |
| `My Plugin` | Your plugin's human-readable name |
| `wordpress` (DB credentials) | Your test database name, user, and password |

Files that contain placeholders:
- `docker-compose.yml` — plugin volume path and DB credentials
- `setup-phpunit.sh` — DB credentials
- `tests/bootstrap.php` — plugin name, main file, constant
- `.github/workflows/phpunit.yml` — DB credentials

Files that need no changes:
- `phpunit.xml.dist`
- `tests/scripts/install-wp-tests.sh`
- `.env.example` (edit `.env` instead)

## GitHub Actions CI

The included `.github/workflows/phpunit.yml` runs PHPUnit on push and pull request using GitHub Actions' native MySQL service — no Docker needed in CI. It tests against PHP 7.4, 8.0, 8.1, and 8.2 by default.

## Docker Image

The `phpunit` service uses [`pattonwebz/phpunit-wordpress`](https://hub.docker.com/r/pattonwebz/phpunit-wordpress) (tag `1.0.0`): PHP 8.4 with SVN, Xdebug, and all dependencies needed to run the WordPress test suite. PHPUnit itself comes from your plugin's own `vendor/bin/phpunit`.

## Running Tests

Once the Docker stack is up (after running `bash setup-phpunit.sh`), run tests from your plugin root:

```bash
# Run the full test suite
composer test
# or directly:
vendor/bin/phpunit

# Coverage report in the terminal
composer test:coverage

# HTML coverage report — output goes to coverage/ directory, open coverage/index.html
composer test:coverage-html

# Clover XML coverage report (for CI tools like Codecov / Coveralls)
composer test:coverage-clover
```

> **Xdebug is bundled** in the `pattonwebz/phpunit-wordpress` Docker image, so coverage commands work out of the box — no extra setup needed.

> **Tip:** Add `coverage/` to your plugin's `.gitignore` to avoid committing HTML reports.

Reference scripts are provided in `scaffold/composer.json.example` (Composer) and `scaffold/package.json.example` (npm). Copy the `scripts` block from `composer.json.example` into your plugin's `composer.json` to enable the `composer test:*` shortcuts.

## Updating

To pick up changes from this scaffold in future:

```bash
# Pull latest from this repo
cd wp-phpunit-scaffold && git pull

# Diff the scaffold against your plugin and apply what you want
diff -rq scaffold/ /path/to/your-plugin/ --exclude='.env' --exclude='vendor'
```

## License

GPL-2.0-or-later

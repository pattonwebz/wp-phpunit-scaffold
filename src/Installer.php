<?php
/**
 * WP PHPUnit Scaffold Installer
 *
 * Runs as a Composer post-install/post-update hook. Copies template files into
 * the consuming project, performs token substitution, and optionally adds
 * convenience scripts to the project's composer.json.
 *
 * @package Pattonwebz\WpPhpunitScaffold
 */

namespace Pattonwebz\WpPhpunitScaffold;

use Composer\Script\Event;

/**
 * Installer class — entry point for Composer script hooks.
 */
class Installer {

	/**
	 * Template files and their destination paths (relative to project root).
	 *
	 * Key   = path inside templates/ directory.
	 * Value = destination path relative to the consuming project root.
	 *
	 * @var array<string,string>
	 */
	private static $templates = [
		'docker-compose.yml'                     => 'docker-compose.yml',
		'setup-phpunit.sh'                       => 'scripts/setup-phpunit.sh',
		'phpunit.xml.dist'                       => 'phpunit.xml.dist',
		'.env.example'                           => '.env.example',
		'tests/bootstrap.php'                    => 'tests/bootstrap.php',
		'tests/bin/install-wp-tests.sh'          => 'tests/bin/install-wp-tests.sh',
		'.github/workflows/phpunit.yml'          => '.github/workflows/phpunit.yml',
	];

	/**
	 * Convenience Composer scripts to inject into the consuming project.
	 *
	 * @var array<string,string>
	 */
	private static $composer_scripts = [
		'phpunit:setup' => '@php -r "passthru(\'bash scripts/setup-phpunit.sh\');"',
		'phpunit:run'   => 'docker compose run --rm phpunit vendor/bin/phpunit',
		'phpunit:stop'  => 'docker compose down',
		'phpunit:shell' => 'docker compose run --rm phpunit bash',
	];

	/**
	 * Entry point called by Composer post-install and post-update hooks.
	 *
	 * @param Event|null $event Composer event, or null when called directly.
	 * @return void
	 */
	public static function run( $event = null ): void {
		$project_root = self::find_project_root();

		if ( null === $project_root ) {
			self::log( 'ERROR: Could not locate project root (no composer.json found up the directory tree).' );
			return;
		}

		$slug      = self::derive_slug( $project_root );
		$constant  = self::slug_to_constant( $slug );
		$name      = self::slug_to_name( $slug );
		$main_file = self::detect_main_file( $project_root, $slug );
		$db        = self::read_db_credentials( $project_root );
		$image_tag = getenv( 'PHPUNIT_IMAGE_TAG' ) ?: '1.0.0';

		$tokens = [
			'{{PLUGIN_SLUG}}'      => $slug,
			'{{PLUGIN_MAIN_FILE}}' => $main_file,
			'{{PLUGIN_CONSTANT}}'  => $constant,
			'{{PLUGIN_NAME}}'      => $name,
			'{{DB_USER}}'          => $db['user'],
			'{{DB_PASSWORD}}'      => $db['password'],
			'{{DB_NAME}}'          => $db['name'],
			'{{DOCKER_IMAGE_TAG}}' => $image_tag,
		];

		self::log( '' );
		self::log( '=== WP PHPUnit Scaffold ===' );
		self::log( sprintf( 'Project root : %s', $project_root ) );
		self::log( sprintf( 'Plugin slug  : %s', $slug ) );
		self::log( sprintf( 'Main file    : %s', $main_file ) );
		self::log( sprintf( 'Constant     : %s', $constant ) );
		self::log( '' );

		$templates_dir = __DIR__ . '/../templates';
		$created       = [];
		$skipped       = [];

		foreach ( self::$templates as $template_path => $dest_path ) {
			$src  = realpath( $templates_dir . '/' . $template_path );
			$dest = $project_root . '/' . $dest_path;

			if ( false === $src || ! file_exists( $src ) ) {
				self::log( sprintf( '  MISSING template: %s', $template_path ) );
				continue;
			}

			if ( file_exists( $dest ) ) {
				self::log( sprintf( '  SKIP (exists): %s', $dest_path ) );
				$skipped[] = $dest_path;
				continue;
			}

			$dir = dirname( $dest );
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0755, true );
			}

			$content = file_get_contents( $src );
			$content = str_replace( array_keys( $tokens ), array_values( $tokens ), $content );

			file_put_contents( $dest, $content );

			// Set executable bit for shell scripts.
			if ( 'sh' === pathinfo( $dest, PATHINFO_EXTENSION ) ) {
				chmod( $dest, 0755 );
			}

			self::log( sprintf( '  CREATED: %s', $dest_path ) );
			$created[] = $dest_path;
		}

		self::inject_composer_scripts( $project_root );

		self::log( '' );
		self::log( sprintf( 'Done. Created: %d file(s), Skipped: %d file(s).', count( $created ), count( $skipped ) ) );
		self::log( '' );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Walk up the directory tree from __DIR__ to locate the consuming project
	 * root — identified by the presence of a composer.json that is NOT this
	 * package's own composer.json.
	 *
	 * Falls back to the COMPOSER_HOME environment variable, and then to the
	 * directory containing the vendor/ folder.
	 *
	 * @return string|null Absolute path to project root, or null if not found.
	 */
	private static function find_project_root(): ?string {
		// Composer sets $_composer_autoload_path in the generated autoload file.
		// vendor/autoload.php lives at {project_root}/vendor/autoload.php.
		if ( isset( $GLOBALS['_composer_autoload_path'] ) ) {
			$autoload_path = $GLOBALS['_composer_autoload_path'];
			// Walk up: autoload_path → vendor/ → project_root.
			$vendor = dirname( $autoload_path );
			$root   = dirname( $vendor );
			if ( file_exists( $root . '/composer.json' ) ) {
				return $root;
			}
		}

		// Walk up from __DIR__ looking for vendor/autoload.php alongside composer.json.
		$dir = __DIR__;
		for ( $i = 0; $i < 10; $i++ ) {
			$parent = dirname( $dir );
			if ( $parent === $dir ) {
				break;
			}
			// If this parent contains vendor/autoload.php and composer.json it
			// is (likely) the consuming project root, not our own package root.
			if ( file_exists( $parent . '/vendor/autoload.php' ) && file_exists( $parent . '/composer.json' ) ) {
				$json = json_decode( file_get_contents( $parent . '/composer.json' ), true );
				if ( is_array( $json ) && ( ! isset( $json['name'] ) || 'pattonwebz/wp-phpunit-scaffold' !== $json['name'] ) ) {
					return $parent;
				}
			}
			$dir = $parent;
		}

		return null;
	}

	/**
	 * Derive the plugin slug from the project root directory name.
	 *
	 * WordPress plugins live in a folder matching their slug, so the directory
	 * name is the most reliable source (e.g. /var/www/wp-content/plugins/my-plugin
	 * → "my-plugin").
	 *
	 * @param string $project_root Absolute path to the project root.
	 * @return string Plugin slug, defaulting to "my-plugin" if undetectable.
	 */
	private static function derive_slug( string $project_root ): string {
		$slug = basename( $project_root );

		// Sanitise: lowercase, only alphanumerics and hyphens.
		$slug = strtolower( preg_replace( '/[^a-zA-Z0-9-]/', '-', $slug ) );
		$slug = trim( $slug, '-' );

		return '' !== $slug ? $slug : 'my-plugin';
	}

	/**
	 * Convert a plugin slug to an uppercase PHP constant name.
	 * Hyphens and spaces are replaced with underscores.
	 *
	 * @param string $slug Plugin slug (e.g. "my-plugin").
	 * @return string Constant name (e.g. "MY_PLUGIN").
	 */
	private static function slug_to_constant( string $slug ): string {
		return strtoupper( str_replace( '-', '_', $slug ) );
	}

	/**
	 * Convert a plugin slug to a human-readable title-cased name.
	 *
	 * @param string $slug Plugin slug (e.g. "my-plugin").
	 * @return string Plugin name (e.g. "My Plugin").
	 */
	private static function slug_to_name( string $slug ): string {
		return ucwords( str_replace( '-', ' ', $slug ) );
	}

	/**
	 * Detect the plugin's main PHP file.
	 *
	 * Checks for {slug}.php in the project root first. If not found, prompts
	 * the user interactively via STDIN.
	 *
	 * @param string $project_root Absolute path to the project root.
	 * @param string $slug         Plugin slug.
	 * @return string Filename (not full path) of the plugin main file.
	 */
	private static function detect_main_file( string $project_root, string $slug ): string {
		$expected = $slug . '.php';
		if ( file_exists( $project_root . '/' . $expected ) ) {
			return $expected;
		}

		// Interactive fallback.
		self::log( sprintf( 'Plugin main file "%s" not found in project root.', $expected ) );
		self::log( 'Enter the plugin main filename (e.g. my-plugin.php): ' );

		$input = fgets( STDIN );
		if ( false !== $input ) {
			$input = trim( $input );
			if ( '' !== $input ) {
				return $input;
			}
		}

		// Final fallback — use the expected name even if it does not exist yet.
		return $expected;
	}

	/**
	 * Read database credentials from a .env file in the project root.
	 *
	 * Recognises DB_USER, DB_PASSWORD, and DB_NAME. Lines beginning with #
	 * are treated as comments. Falls back to the default value "wordpress"
	 * for any credential not found.
	 *
	 * @param string $project_root Absolute path to the project root.
	 * @return array{user: string, password: string, name: string}
	 */
	private static function read_db_credentials( string $project_root ): array {
		$defaults = [
			'user'     => 'wordpress',
			'password' => 'wordpress',
			'name'     => 'wordpress',
		];

		$env_file = $project_root . '/.env';
		if ( ! file_exists( $env_file ) ) {
			return $defaults;
		}

		$lines = file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( false === $lines ) {
			return $defaults;
		}

		$env = [];
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			if ( false === strpos( $line, '=' ) ) {
				continue;
			}
			[ $key, $value ] = explode( '=', $line, 2 );
			$env[ trim( $key ) ] = trim( $value, " \t\"'" );
		}

		return [
			'user'     => $env['DB_USER']     ?? $defaults['user'],
			'password' => $env['DB_PASSWORD'] ?? $defaults['password'],
			'name'     => $env['DB_NAME']     ?? $defaults['name'],
		];
	}

	/**
	 * Add convenience PHPUnit scripts to the consuming project's composer.json,
	 * skipping any keys that already exist.
	 *
	 * @param string $project_root Absolute path to the project root.
	 * @return void
	 */
	private static function inject_composer_scripts( string $project_root ): void {
		$composer_file = $project_root . '/composer.json';
		if ( ! file_exists( $composer_file ) ) {
			return;
		}

		$json = json_decode( file_get_contents( $composer_file ), true );
		if ( ! is_array( $json ) ) {
			return;
		}

		if ( ! isset( $json['scripts'] ) || ! is_array( $json['scripts'] ) ) {
			$json['scripts'] = [];
		}

		$added = [];
		foreach ( self::$composer_scripts as $script_name => $command ) {
			if ( isset( $json['scripts'][ $script_name ] ) ) {
				self::log( sprintf( '  SKIP script (exists): %s', $script_name ) );
				continue;
			}
			$json['scripts'][ $script_name ] = $command;
			$added[]                          = $script_name;
		}

		if ( empty( $added ) ) {
			return;
		}

		$encoded = json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( false !== $encoded ) {
			file_put_contents( $composer_file, $encoded . "\n" );
			self::log( sprintf( '  Scripts added: %s', implode( ', ', $added ) ) );
		}
	}

	/**
	 * Write a message to STDOUT.
	 *
	 * @param string $message Message to print.
	 * @return void
	 */
	private static function log( string $message ): void {
		echo $message . PHP_EOL;
	}
}

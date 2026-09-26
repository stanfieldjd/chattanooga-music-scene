<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class CUA_Visual_Browser_Runtime_Manager {
	const CATEGORY = 'admin-mcp';
	const PREFIX = 'admin-mcp/';
	const RUNTIME_VERSION = '154.0.8037.57';
	const RUNTIME_DIR = 'admin-mcp-browser-runtime';
	const MAX_ARCHIVE_BYTES = 157286400;
	const ASSETS = array(
		'linux64' => array(
			'url' => 'https://storage.googleapis.com/chrome-for-testing-public/154.0.8037.57/linux64/chrome-headless-shell-linux64.zip',
			'sha256' => '5a6979d0ab7cf952ea575d35164e7bdce4872b2ced8f8a215c8f8e8eda00ee09',
			'folder' => 'chrome-headless-shell-linux64',
		),
		'linux-arm64' => array(
			'url' => 'https://storage.googleapis.com/chrome-for-testing-public/154.0.8037.57/linux-arm64/chrome-headless-shell-linux-arm64.zip',
			'sha256' => '2213770a541c7ea17c900c631bdb6a3cda97ecf5194a34a575f32e54a8f5ab70',
			'folder' => 'chrome-headless-shell-linux-arm64',
		),
	);

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) { return; }
		wp_register_ability(
			self::PREFIX . 'get-browser-runtime',
			array(
				'label' => __( 'Inspect browser runtime', 'admin-mcp' ),
				'description' => __( 'Returns managed browser runtime status and host compatibility without exposing server filesystem paths.', 'admin-mcp' ),
				'category' => self::CATEGORY,
				'input_schema' => array( 'type' => 'object', 'properties' => array(), 'additionalProperties' => false ),
				'output_schema' => array( 'type' => 'object' ),
				'execute_callback' => array( __CLASS__, 'get_runtime' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta' => self::meta( true, false, true ),
			)
		);
		wp_register_ability(
			self::PREFIX . 'install-browser-runtime',
			array(
				'label' => __( 'Install verified browser runtime', 'admin-mcp' ),
				'description' => __( 'Installs a pinned official Chrome for Testing headless-shell runtime, verifies its SHA-256 and archive structure, validates it through the existing sandboxed CDP path, and rolls back on failure.', 'admin-mcp' ),
				'category' => self::CATEGORY,
				'input_schema' => array(
					'type' => 'object',
					'properties' => array( 'confirm_install' => array( 'type' => 'boolean' ) ),
					'required' => array( 'confirm_install' ),
					'additionalProperties' => false,
				),
				'output_schema' => array( 'type' => 'object' ),
				'execute_callback' => array( __CLASS__, 'install_runtime' ),
				'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
				'meta' => self::meta( false, false, true ),
			)
		);
	}

	public static function get_runtime( $input = array() ) {
		$platform = self::platform();
		$binary = is_wp_error( $platform ) ? '' : self::installed_binary();
		$exec_ready = function_exists( 'exec' ) && ! self::function_disabled( 'exec' );
		$version_output = '';
		if ( $binary && $exec_ready ) {
			$out = array(); $status = 0;
			@exec( escapeshellarg( $binary ) . ' --version 2>&1', $out, $status );
			if ( 0 === $status ) { $version_output = trim( implode( "\n", $out ) ); }
		}
		return array(
			'runtimeVersion' => self::RUNTIME_VERSION,
			'platform' => is_wp_error( $platform ) ? 'unsupported' : $platform,
			'architecture' => strtolower( trim( (string) php_uname( 'm' ) ) ),
			'execAvailable' => $exec_ready,
			'managedInstalled' => '' !== $binary,
			'managedExecutableValidated' => '' !== $version_output,
			'versionOutput' => $version_output,
			'source' => 'Google Chrome for Testing chrome-headless-shell',
			'pinnedSha256' => is_wp_error( $platform ) ? '' : (string) self::ASSETS[ $platform ]['sha256'],
		);
	}

	public static function install_runtime( $input ) {
		$input = is_array( $input ) ? $input : array();
		if ( true !== ( $input['confirm_install'] ?? false ) ) {
			return new WP_Error( 'cmsa_visual_runtime_confirmation_required', 'confirm_install must be true to install the managed browser runtime.' );
		}
		if ( ! function_exists( 'exec' ) || self::function_disabled( 'exec' ) ) {
			return new WP_Error( 'cmsa_visual_exec_unavailable', 'The host does not permit the process execution required for headless browser inspection.' );
		}
		$platform = self::platform();
		if ( is_wp_error( $platform ) ) { return $platform; }
		$asset = self::ASSETS[ $platform ];
		$existing = self::installed_binary();
		if ( '' !== $existing ) {
			$session = CUA_Visual_Browser_Runtime::start( 800, 600, $existing );
			if ( is_wp_error( $session ) ) {
				return new WP_Error( 'cmsa_visual_runtime_validation_failed', $session->get_error_message(), array( 'runtimeErrorCode' => $session->get_error_code() ) );
			}
			CUA_Visual_Browser_Runtime::close( $session );
			return array_merge( self::get_runtime(), array( 'installed' => true, 'reused' => true, 'validated' => true ) );
		}

		$root = self::runtime_root( true );
		if ( is_wp_error( $root ) ) { return $root; }
		self::write_access_guards( $root );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$temp = download_url( (string) $asset['url'], 300 );
		if ( is_wp_error( $temp ) ) { return new WP_Error( 'cmsa_visual_runtime_download_failed', $temp->get_error_message() ); }
		try {
			$size = @filesize( $temp );
			if ( false === $size || $size < 1 || $size > self::MAX_ARCHIVE_BYTES ) {
				return new WP_Error( 'cmsa_visual_runtime_archive_size_invalid', 'The downloaded browser runtime archive size is outside the permitted range.' );
			}
			$actual = hash_file( 'sha256', $temp );
			if ( ! is_string( $actual ) || ! hash_equals( (string) $asset['sha256'], strtolower( $actual ) ) ) {
				return new WP_Error( 'cmsa_visual_runtime_digest_mismatch', 'The downloaded browser runtime did not match the pinned SHA-256 digest.' );
			}
			if ( ! class_exists( 'ZipArchive' ) ) {
				return new WP_Error( 'cmsa_visual_runtime_zip_unavailable', 'The PHP ZipArchive extension is required to install the managed browser runtime.' );
			}
			$staging = trailingslashit( $root ) . '.staging-' . bin2hex( random_bytes( 8 ) );
			if ( ! wp_mkdir_p( $staging ) ) {
				return new WP_Error( 'cmsa_visual_runtime_staging_failed', 'The managed browser runtime staging directory could not be created.' );
			}
			$zip = new ZipArchive();
			if ( true !== $zip->open( $temp ) ) {
				self::delete_tree( $staging );
				return new WP_Error( 'cmsa_visual_runtime_archive_invalid', 'The browser runtime ZIP could not be opened.' );
			}
			$prefix = (string) $asset['folder'] . '/';
			$valid = $zip->numFiles > 0 && $zip->numFiles < 1000;
			for ( $i = 0; $valid && $i < $zip->numFiles; $i++ ) {
				$name = (string) $zip->getNameIndex( $i );
				if ( '' === $name || 0 !== strpos( $name, $prefix ) || str_contains( $name, "\0" ) || str_contains( $name, '../' ) || str_starts_with( $name, '/' ) || str_contains( $name, '\\' ) ) { $valid = false; }
			}
			if ( ! $valid ) {
				$zip->close(); self::delete_tree( $staging );
				return new WP_Error( 'cmsa_visual_runtime_archive_structure_invalid', 'The browser runtime ZIP failed archive structure validation.' );
			}
			if ( ! $zip->extractTo( $staging ) ) {
				$zip->close(); self::delete_tree( $staging );
				return new WP_Error( 'cmsa_visual_runtime_extract_failed', 'The browser runtime ZIP could not be extracted.' );
			}
			$zip->close();
			$source = trailingslashit( $staging ) . (string) $asset['folder'];
			$source_binary = trailingslashit( $source ) . 'chrome-headless-shell';
			if ( ! is_file( $source_binary ) || ! @chmod( $source_binary, 0755 ) || ! is_executable( $source_binary ) ) {
				self::delete_tree( $staging );
				return new WP_Error( 'cmsa_visual_runtime_executable_invalid', 'The extracted browser runtime executable could not be validated.' );
			}
			$target_parent = trailingslashit( $root ) . self::RUNTIME_VERSION;
			$target = trailingslashit( $target_parent ) . $platform;
			if ( ! is_dir( $target_parent ) && ! wp_mkdir_p( $target_parent ) ) {
				self::delete_tree( $staging );
				return new WP_Error( 'cmsa_visual_runtime_target_failed', 'The managed browser runtime target directory could not be created.' );
			}
			if ( is_dir( $target ) ) { self::delete_tree( $target ); }
			if ( ! @rename( $source, $target ) ) {
				self::delete_tree( $staging );
				return new WP_Error( 'cmsa_visual_runtime_promote_failed', 'The verified browser runtime could not be promoted from staging.' );
			}
			self::delete_tree( $staging );
			$binary = trailingslashit( $target ) . 'chrome-headless-shell';
			$manifest = array( 'version' => self::RUNTIME_VERSION, 'platform' => $platform, 'sha256' => (string) $asset['sha256'], 'installedAtGmt' => gmdate( 'c' ) );
			if ( false === file_put_contents( trailingslashit( $target ) . 'admin-mcp-runtime.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), LOCK_EX ) ) {
				self::delete_tree( $target );
				return new WP_Error( 'cmsa_visual_runtime_manifest_failed', 'The managed browser runtime manifest could not be written.' );
			}
			@chmod( $binary, 0755 );
			$session = CUA_Visual_Browser_Runtime::start( 800, 600, $binary );
			if ( is_wp_error( $session ) ) {
				self::delete_tree( $target );
				return new WP_Error( 'cmsa_visual_runtime_validation_failed', $session->get_error_message(), array( 'runtimeErrorCode' => $session->get_error_code() ) );
			}
			CUA_Visual_Browser_Runtime::close( $session );
			return array_merge( self::get_runtime(), array( 'installed' => true, 'reused' => false, 'validated' => true ) );
		} finally {
			if ( is_string( $temp ) && is_file( $temp ) ) { @unlink( $temp ); }
		}
	}

	public static function installed_binary() {
		$platform = self::platform();
		if ( is_wp_error( $platform ) ) { return ''; }
		$asset = self::ASSETS[ $platform ];
		$root = self::runtime_root( false );
		$target = trailingslashit( $root ) . self::RUNTIME_VERSION . '/' . $platform;
		$manifest_file = trailingslashit( $target ) . 'admin-mcp-runtime.json';
		$binary = trailingslashit( $target ) . 'chrome-headless-shell';
		if ( ! is_file( $manifest_file ) || ! is_file( $binary ) || ! is_executable( $binary ) ) { return ''; }
		$manifest = json_decode( (string) @file_get_contents( $manifest_file ), true );
		if ( ! is_array( $manifest ) || self::RUNTIME_VERSION !== (string) ( $manifest['version'] ?? '' ) || $platform !== (string) ( $manifest['platform'] ?? '' ) || ! hash_equals( (string) $asset['sha256'], (string) ( $manifest['sha256'] ?? '' ) ) ) { return ''; }
		$real = realpath( $binary );
		return false === $real ? '' : $real;
	}

	private static function platform() {
		if ( 'Linux' !== PHP_OS_FAMILY ) { return new WP_Error( 'cmsa_visual_runtime_platform_unsupported', 'The managed browser runtime currently supports Linux hosts only.' ); }
		$arch = strtolower( trim( (string) php_uname( 'm' ) ) );
		if ( in_array( $arch, array( 'x86_64', 'amd64' ), true ) ) { return 'linux64'; }
		if ( in_array( $arch, array( 'aarch64', 'arm64' ), true ) ) { return 'linux-arm64'; }
		return new WP_Error( 'cmsa_visual_runtime_arch_unsupported', 'The server CPU architecture is not supported by the pinned managed browser runtime.' );
	}

	private static function runtime_root( $create ) {
		$root = trailingslashit( WP_CONTENT_DIR ) . self::RUNTIME_DIR;
		if ( $create && ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) { return new WP_Error( 'cmsa_visual_runtime_storage_unavailable', 'WordPress content storage is not writable for the managed browser runtime.' ); }
		return $root;
	}

	private static function write_access_guards( $root ) {
		if ( ! is_dir( $root ) ) { return; }
		@file_put_contents( trailingslashit( $root ) . 'index.php', "<?php\nhttp_response_code( 404 );\nexit;\n", LOCK_EX );
		@file_put_contents( trailingslashit( $root ) . '.htaccess', "Require all denied\nDeny from all\n", LOCK_EX );
	}

	private static function delete_tree( $path ) {
		$root = self::runtime_root( false );
		$root_real = realpath( $root ); $path_real = realpath( $path );
		if ( false === $root_real || false === $path_real || $path_real === $root_real || 0 !== strpos( $path_real, $root_real . DIRECTORY_SEPARATOR ) ) { return; }
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path_real, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $item ) { $item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() ); }
		@rmdir( $path_real );
	}

	private static function function_disabled( $name ) { return in_array( $name, array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true ); }
	private static function meta( $readonly, $destructive, $open_world ) { return array( 'public' => true, 'show_in_rest' => false, 'mcp' => array( 'public' => true ), 'annotations' => array( 'readonly' => (bool) $readonly, 'destructive' => (bool) $destructive, 'idempotent' => true, 'open_world' => (bool) $open_world ) ); }
}

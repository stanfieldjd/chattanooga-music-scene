<?php

declare(strict_types=1);

use Chattanooga\RobustMcp\WordPress\ExternalWordPressBootstrap;
use Chattanooga\RobustMcp\WordPress\RuntimeInventorySourceInterface;
use Chattanooga\RobustMcp\WordPress\RuntimeManifestBuilder;

require __DIR__ . '/vendor/autoload.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$baseInventory = [
    'runtime' => [
        'wordpress_version' => '7.1',
        'php_version' => PHP_VERSION,
        'multisite' => false,
        'wp_cli' => false,
    ],
    'site' => [
        'site_url' => 'https://fixture.invalid/',
        'home_url' => 'https://fixture.invalid/',
        'name' => 'Fixture',
        'locale' => 'en_US',
        'charset' => 'UTF-8',
        'timezone' => 'America/New_York',
    ],
    'principal' => [
        'authenticated' => false,
        'user_id' => 0,
        'roles' => [],
        'capabilities' => [],
        'administrative' => false,
    ],
    'plugins' => [
        'vendor/example.php' => [
            'name' => 'Vendor Example',
            'version' => '9.4.1',
            'active' => true,
            'network_active' => false,
        ],
    ],
    'themes' => [],
    'post_types' => [
        'vendor_record' => [
            'label' => 'Vendor Records',
            'public' => false,
            'show_in_rest' => true,
            'supports' => ['title' => true, 'custom-fields' => true],
        ],
    ],
    'taxonomies' => [
        'vendor_genre' => [
            'label' => 'Vendor Genres',
            'object_types' => ['vendor_record'],
            'show_in_rest' => true,
        ],
    ],
    'settings' => [
        'vendor_setting' => [
            'type' => 'object',
            'show_in_rest' => ['schema' => ['type' => 'object']],
            'has_default' => true,
        ],
    ],
    'rest_routes' => [
        '/vendor/v1/items' => [
            'methods' => ['GET', 'POST'],
            'endpoint_count' => 2,
        ],
    ],
    'abilities' => [
        'vendor/read-record' => [
            'label' => 'Read Record',
            'category' => 'content',
            'input_schema' => ['type' => 'object'],
            'output_schema' => ['type' => 'object'],
            'meta' => ['annotations' => ['readonly' => true]],
        ],
    ],
    'roles' => [
        'administrator' => [
            'name' => 'Administrator',
            'capabilities' => ['edit_posts', 'manage_options'],
        ],
    ],
    'features' => [
        'abilities_api' => true,
        'rest_api' => true,
        'multisite' => false,
        'rest_namespaces' => ['vendor/v1'],
    ],
];

$source = static function (array $inventory): RuntimeInventorySourceInterface {
    return new class($inventory) implements RuntimeInventorySourceInterface {
        public function __construct(private readonly array $inventory)
        {
        }

        public function collect(): array
        {
            return $this->inventory;
        }
    };
};

$builder = new RuntimeManifestBuilder();
$inside = $builder->build($source($baseInventory), 'inside-wordpress');

$reordered = $baseInventory;
$reordered['plugins'] = array_reverse($reordered['plugins'], true);
$reordered['post_types'] = array_reverse($reordered['post_types'], true);
$reordered['site'] = array_reverse($reordered['site'], true);
$reordered['runtime']['wp_cli'] = true;
$reordered['principal']['authenticated'] = true;
$reordered['principal']['user_id'] = 77;
$reordered['principal']['roles'] = ['administrator'];
$reordered['principal']['capabilities'] = ['manage_options'];
$reordered['principal']['administrative'] = true;
$outside = $builder->build($source($reordered), 'outside-wordpress');

if ($inside['fingerprint'] !== $outside['fingerprint']) {
    $fail('Residence/context changes altered the structural WordPress fingerprint.');
}
if ('inside-wordpress' !== $inside['residence'] || 'outside-wordpress' !== $outside['residence']) {
    $fail('Workspace residence was not preserved separately from inventory.');
}
if (!isset($inside['inventory']['post_types']['vendor_record'])) {
    $fail('Generic plugin-defined post type was not preserved in blanket inventory.');
}
if (!isset($inside['inventory']['abilities']['vendor/read-record'])) {
    $fail('Generic registered ability was not preserved in blanket inventory.');
}
if (isset($inside['inventory']['runtime']) || isset($inside['inventory']['principal'])) {
    $fail('Execution context leaked into the structural inventory.');
}

try {
    $builder->build($source(array_diff_key($baseInventory, ['settings' => true])), 'inside-wordpress');
    $fail('Missing required inventory section was accepted.');
} catch (UnexpectedValueException) {
}

try {
    $builder->build($source($baseInventory), 'somewhere-else');
    $fail('Unknown workspace residence was accepted.');
} catch (InvalidArgumentException) {
}

$objectInventory = $baseInventory;
$objectInventory['settings']['vendor_setting']['unsafe'] = new stdClass();
try {
    $builder->build($source($objectInventory), 'inside-wordpress');
    $fail('Object value was accepted into the JSON-only runtime manifest.');
} catch (UnexpectedValueException) {
}

$tmp = sys_get_temp_dir() . '/robust-mcp-wp-bootstrap-' . bin2hex(random_bytes(8));
$outsideTmp = sys_get_temp_dir() . '/robust-mcp-wp-outside-' . bin2hex(random_bytes(8));
mkdir($tmp, 0700, true);
mkdir($outsideTmp, 0700, true);
file_put_contents($tmp . '/wp-load.php', "<?php\n");
$bootstrap = new ExternalWordPressBootstrap($tmp);
if (realpath($tmp) !== $bootstrap->root() || realpath($tmp . '/wp-load.php') !== $bootstrap->wpLoadPath()) {
    $fail('External bootstrap did not retain the exact configured WordPress root.');
}

try {
    new ExternalWordPressBootstrap($tmp . '/missing');
    $fail('Missing external WordPress root was accepted.');
} catch (InvalidArgumentException) {
}

$symlinkRoot = $tmp . '/symlink-root';
mkdir($symlinkRoot, 0700, true);
file_put_contents($outsideTmp . '/wp-load.php', "<?php\n");
if (function_exists('symlink') && @symlink($outsideTmp . '/wp-load.php', $symlinkRoot . '/wp-load.php')) {
    try {
        new ExternalWordPressBootstrap($symlinkRoot);
        $fail('wp-load.php symlink escaping the configured root was accepted.');
    } catch (InvalidArgumentException) {
    }
    @unlink($symlinkRoot . '/wp-load.php');
}

@unlink($tmp . '/wp-load.php');
@rmdir($symlinkRoot);
@rmdir($tmp);
@unlink($outsideTmp . '/wp-load.php');
@rmdir($outsideTmp);

echo 'robust-wordpress-runtime-contract: PASS residence-neutral-fingerprint json-only exact-root generic-structures abilities-rest-settings' . PHP_EOL;

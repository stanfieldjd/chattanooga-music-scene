<?php

declare(strict_types=1);

use Chattanooga\RobustMcp\SchemaGuard;
use Chattanooga\RobustMcp\SchemaGuardException;

require __DIR__ . '/vendor/autoload.php';

$guard = new SchemaGuard();
$rejected = 0;

$expectReject = static function (string $name, callable $test) use (&$rejected): void {
    try {
        $test();
    } catch (SchemaGuardException) {
        ++$rejected;
        return;
    }
    fwrite(STDERR, "Expected rejection did not occur: {$name}\n");
    exit(1);
};

$valid = [
    '$schema' => SchemaGuard::DRAFT_2020_12,
    'type' => 'object',
    '$defs' => [
        'name' => ['type' => 'string', 'minLength' => 1],
    ],
    'properties' => [
        'kind' => ['enum' => ['person', 'service']],
        'name' => ['$ref' => '#/$defs/name'],
        'token' => ['type' => 'string'],
    ],
    'required' => ['kind', 'name'],
    'allOf' => [
        [
            'if' => [
                'properties' => ['kind' => ['const' => 'service']],
                'required' => ['kind'],
            ],
            'then' => ['required' => ['token']],
        ],
    ],
    'additionalProperties' => false,
];

$guard->assertSafeSchema($valid, 'valid-complex-schema');
$guard->assertSafeSchema([], 'empty-output-schema');
$guard->assertValidData(['kind' => 'person', 'name' => 'Ada'], $valid, 'valid-data', true);

$expectReject('conditional-required', static fn () => $guard->assertValidData(
    ['kind' => 'service', 'name' => 'daemon'],
    $valid,
    'conditional-data',
    true,
));

$expectReject('external-ref', static fn () => $guard->assertSafeSchema([
    '$schema' => SchemaGuard::DRAFT_2020_12,
    'type' => 'object',
    'properties' => ['x' => ['$ref' => 'https://attacker.invalid/schema.json']],
], 'external-ref'));

$expectReject('relative-external-ref', static fn () => $guard->assertSafeSchema([
    'type' => 'object',
    'properties' => ['x' => ['$ref' => 'other.json#/$defs/x']],
], 'relative-external-ref'));

$expectReject('opis-extension', static fn () => $guard->assertSafeSchema([
    'type' => 'object',
    '$data' => ['unsafe' => true],
], 'opis-extension'));

$expectReject('wrong-dialect', static fn () => $guard->assertSafeSchema([
    '$schema' => 'http://json-schema.org/draft-07/schema#',
    'type' => 'object',
], 'wrong-dialect'));

$expectReject('malformed-keyword', static fn () => $guard->assertSafeSchema([
    '$schema' => SchemaGuard::DRAFT_2020_12,
    'type' => 'object',
    'required' => 'not-an-array',
], 'malformed-keyword'));

$deep = ['type' => 'string'];
for ($i = 0; $i < SchemaGuard::MAX_SCHEMA_DEPTH + 3; ++$i) {
    $deep = ['allOf' => [$deep]];
}
$expectReject('schema-depth', static fn () => $guard->assertSafeSchema($deep, 'schema-depth'));

$composed = ['type' => 'string'];
for ($i = 0; $i < 7; ++$i) {
    $composed = [
        'oneOf' => [
            $composed,
            ['type' => 'integer'],
            ['type' => 'boolean'],
            ['type' => 'null'],
            ['type' => 'number'],
        ],
    ];
}
$expectReject('composition-budget', static fn () => $guard->assertSafeSchema($composed, 'composition-budget'));

$largeData = array_fill(0, SchemaGuard::MAX_DATA_NODES + 1, 1);
$expectReject('data-node-budget', static fn () => $guard->assertValidData(
    $largeData,
    ['type' => 'array', 'items' => ['type' => 'integer']],
    'large-data',
));

printf(
    "robust-mcp-schema-guard: PASS rejected=%d draft=2020-12 max_schema_bytes=%d max_schema_depth=%d max_schema_nodes=%d max_data_depth=%d max_data_nodes=%d\n",
    $rejected,
    SchemaGuard::MAX_SCHEMA_BYTES,
    SchemaGuard::MAX_SCHEMA_DEPTH,
    SchemaGuard::MAX_SCHEMA_NODES,
    SchemaGuard::MAX_DATA_DEPTH,
    SchemaGuard::MAX_DATA_NODES,
);

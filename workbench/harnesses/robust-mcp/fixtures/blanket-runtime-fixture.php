<?php
/**
 * Plugin Name: Robust MCP Blanket Runtime Fixture
 * Version: 1.0.0
 */

declare(strict_types=1);

add_action('init', static function (): void {
    register_post_type('blanket_record', [
        'label' => 'Blanket Records',
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'custom-fields'],
    ]);

    register_taxonomy('blanket_genre', ['blanket_record'], [
        'label' => 'Blanket Genres',
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
    ]);

    register_setting('general', 'blanket_runtime_option', [
        'type' => 'object',
        'label' => 'Blanket Runtime Option',
        'description' => 'Fixture setting proving registered setting discovery without exposing its value.',
        'default' => ['enabled' => false],
        'show_in_rest' => [
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'enabled' => ['type' => 'boolean'],
                ],
                'additionalProperties' => false,
            ],
        ],
    ]);
});

add_action('rest_api_init', static function (): void {
    register_rest_route('blanket-fixture/v1', '/ping', [
        'methods' => 'GET',
        'callback' => static fn () => rest_ensure_response(['ok' => true]),
        'permission_callback' => static fn (): bool => current_user_can('manage_options'),
    ]);
});

add_action('wp_abilities_api_categories_init', static function (): void {
    wp_register_ability_category('blanket-runtime', [
        'label' => 'Blanket Runtime',
        'description' => 'Fixture category for blanket runtime discovery.',
    ]);
});

add_action('wp_abilities_api_init', static function (): void {
    wp_register_ability('blanket-fixture/read-runtime', [
        'label' => 'Read Fixture Runtime',
        'description' => 'Read-only fixture ability proving generic ability discovery.',
        'category' => 'blanket-runtime',
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'ok' => ['type' => 'boolean'],
            ],
            'required' => ['ok'],
            'additionalProperties' => false,
        ],
        'execute_callback' => static fn (): array => ['ok' => true],
        'permission_callback' => static fn (): bool => current_user_can('manage_options'),
        'meta' => [
            'public' => true,
            'annotations' => [
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true,
            ],
        ],
    ]);
});

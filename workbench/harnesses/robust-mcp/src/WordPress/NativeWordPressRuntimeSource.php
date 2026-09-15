<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp\WordPress;

final class NativeWordPressRuntimeSource implements RuntimeInventorySourceInterface
{
    public function collect(): array
    {
        $this->assertWordPressLoaded();

        return [
            'runtime' => $this->runtime(),
            'site' => $this->site(),
            'principal' => $this->principal(),
            'plugins' => $this->plugins(),
            'themes' => $this->themes(),
            'post_types' => $this->postTypes(),
            'taxonomies' => $this->taxonomies(),
            'settings' => $this->settings(),
            'rest_routes' => $this->restRoutes(),
            'abilities' => $this->abilities(),
            'roles' => $this->roles(),
            'features' => $this->features(),
        ];
    }

    private function assertWordPressLoaded(): void
    {
        $required = ['get_option', 'get_bloginfo', 'get_post_types', 'get_taxonomies', 'wp_roles'];
        foreach ($required as $function) {
            if (!function_exists($function)) {
                throw new \RuntimeException(sprintf('WordPress runtime is not fully booted; missing %s().', $function));
            }
        }
        if (!defined('ABSPATH')) {
            throw new \RuntimeException('WordPress runtime is not fully booted; ABSPATH is undefined.');
        }
    }

    /** @return array<string,mixed> */
    private function runtime(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'wordpress_version' => (string) \get_bloginfo('version'),
            'multisite' => function_exists('is_multisite') ? (bool) \is_multisite() : false,
            'wp_cli' => defined('WP_CLI') && true === constant('WP_CLI'),
        ];
    }

    /** @return array<string,mixed> */
    private function site(): array
    {
        return [
            'name' => (string) \get_bloginfo('name'),
            'home_url' => function_exists('home_url') ? (string) \home_url('/') : '',
            'site_url' => function_exists('site_url') ? (string) \site_url('/') : '',
            'locale' => function_exists('get_locale') ? (string) \get_locale() : '',
            'charset' => (string) \get_bloginfo('charset'),
            'timezone' => function_exists('wp_timezone_string') ? (string) \wp_timezone_string() : '',
        ];
    }

    /** @return array<string,mixed> */
    private function principal(): array
    {
        if (!function_exists('wp_get_current_user')) {
            return [
                'authenticated' => false,
                'user_id' => 0,
                'roles' => [],
                'capabilities' => [],
                'administrative' => false,
            ];
        }

        $user = \wp_get_current_user();
        $capabilities = [];
        foreach ((array) ($user->allcaps ?? []) as $capability => $granted) {
            if ($granted) {
                $capabilities[] = (string) $capability;
            }
        }
        sort($capabilities, SORT_STRING);

        $roles = array_values(array_map('strval', (array) ($user->roles ?? [])));
        sort($roles, SORT_STRING);

        return [
            'authenticated' => (int) ($user->ID ?? 0) > 0,
            'user_id' => (int) ($user->ID ?? 0),
            'roles' => $roles,
            'capabilities' => $capabilities,
            'administrative' => function_exists('current_user_can') && \current_user_can('manage_options'),
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function plugins(): array
    {
        if (!function_exists('get_plugins')) {
            $pluginInclude = rtrim((string) constant('ABSPATH'), '/\\') . '/wp-admin/includes/plugin.php';
            if (!is_readable($pluginInclude)) {
                throw new \RuntimeException('WordPress plugin inventory include is unavailable.');
            }
            require_once $pluginInclude;
        }

        $active = array_fill_keys(array_map('strval', (array) \get_option('active_plugins', [])), true);
        $networkActive = [];
        if (function_exists('is_multisite') && \is_multisite() && function_exists('get_site_option')) {
            $networkActive = (array) \get_site_option('active_sitewide_plugins', []);
        }

        $result = [];
        foreach ((array) \get_plugins() as $file => $data) {
            $file = (string) $file;
            $data = is_array($data) ? $data : [];
            $result[$file] = [
                'name' => (string) ($data['Name'] ?? ''),
                'version' => (string) ($data['Version'] ?? ''),
                'text_domain' => (string) ($data['TextDomain'] ?? ''),
                'requires_wordpress' => (string) ($data['RequiresWP'] ?? ''),
                'requires_php' => (string) ($data['RequiresPHP'] ?? ''),
                'network_only' => !empty($data['Network']),
                'active' => isset($active[$file]) || isset($networkActive[$file]),
                'network_active' => isset($networkActive[$file]),
            ];
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @return array<string,array<string,mixed>> */
    private function themes(): array
    {
        if (!function_exists('wp_get_themes')) {
            return [];
        }

        $activeStylesheet = function_exists('get_stylesheet') ? (string) \get_stylesheet() : '';
        $result = [];
        foreach ((array) \wp_get_themes() as $stylesheet => $theme) {
            if (!is_object($theme) || !method_exists($theme, 'get')) {
                continue;
            }
            $stylesheet = (string) $stylesheet;
            $result[$stylesheet] = [
                'name' => (string) $theme->get('Name'),
                'version' => (string) $theme->get('Version'),
                'template' => (string) $theme->get('Template'),
                'text_domain' => (string) $theme->get('TextDomain'),
                'requires_wordpress' => (string) $theme->get('RequiresWP'),
                'requires_php' => (string) $theme->get('RequiresPHP'),
                'active' => $stylesheet === $activeStylesheet,
            ];
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @return array<string,array<string,mixed>> */
    private function postTypes(): array
    {
        $result = [];
        foreach ((array) \get_post_types([], 'objects') as $name => $type) {
            if (!is_object($type)) {
                continue;
            }
            $name = (string) $name;
            $taxonomies = function_exists('get_object_taxonomies') ? array_map('strval', (array) \get_object_taxonomies($name, 'names')) : [];
            sort($taxonomies, SORT_STRING);

            $supports = [];
            if (function_exists('get_all_post_type_supports')) {
                foreach ((array) \get_all_post_type_supports($name) as $support => $args) {
                    $supports[(string) $support] = $this->safeJson($args);
                }
                ksort($supports, SORT_STRING);
            }

            $result[$name] = [
                'label' => (string) ($type->label ?? $name),
                'description' => (string) ($type->description ?? ''),
                'public' => (bool) ($type->public ?? false),
                'publicly_queryable' => (bool) ($type->publicly_queryable ?? false),
                'show_ui' => (bool) ($type->show_ui ?? false),
                'show_in_rest' => (bool) ($type->show_in_rest ?? false),
                'rest_base' => is_string($type->rest_base ?? null) ? $type->rest_base : null,
                'hierarchical' => (bool) ($type->hierarchical ?? false),
                'has_archive' => $this->safeJson($type->has_archive ?? false),
                'taxonomies' => $taxonomies,
                'supports' => $supports,
                'capabilities' => $this->capabilityObject($type->cap ?? null),
            ];
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @return array<string,array<string,mixed>> */
    private function taxonomies(): array
    {
        $result = [];
        foreach ((array) \get_taxonomies([], 'objects') as $name => $taxonomy) {
            if (!is_object($taxonomy)) {
                continue;
            }
            $name = (string) $name;
            $objectTypes = array_values(array_map('strval', (array) ($taxonomy->object_type ?? [])));
            sort($objectTypes, SORT_STRING);

            $result[$name] = [
                'label' => (string) ($taxonomy->label ?? $name),
                'description' => (string) ($taxonomy->description ?? ''),
                'object_types' => $objectTypes,
                'public' => (bool) ($taxonomy->public ?? false),
                'publicly_queryable' => (bool) ($taxonomy->publicly_queryable ?? false),
                'show_ui' => (bool) ($taxonomy->show_ui ?? false),
                'show_in_rest' => (bool) ($taxonomy->show_in_rest ?? false),
                'rest_base' => is_string($taxonomy->rest_base ?? null) ? $taxonomy->rest_base : null,
                'hierarchical' => (bool) ($taxonomy->hierarchical ?? false),
                'capabilities' => $this->capabilityObject($taxonomy->cap ?? null),
            ];
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @return array<string,array<string,mixed>> */
    private function settings(): array
    {
        if (!function_exists('get_registered_settings')) {
            return [];
        }

        $result = [];
        foreach ((array) \get_registered_settings() as $name => $setting) {
            if (!is_array($setting)) {
                continue;
            }
            $result[(string) $name] = [
                'type' => (string) ($setting['type'] ?? ''),
                'label' => (string) ($setting['label'] ?? ''),
                'description' => (string) ($setting['description'] ?? ''),
                'show_in_rest' => $this->safeJson($setting['show_in_rest'] ?? false),
                'has_default' => array_key_exists('default', $setting),
            ];
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @return array<string,array<string,mixed>> */
    private function restRoutes(): array
    {
        if (!function_exists('rest_get_server')) {
            return [];
        }

        $server = \rest_get_server();
        if (!is_object($server) || !method_exists($server, 'get_routes')) {
            return [];
        }

        $result = [];
        foreach ((array) $server->get_routes() as $route => $endpoints) {
            $methods = [];
            $endpointCount = 0;
            foreach ((array) $endpoints as $endpoint) {
                if (!is_array($endpoint)) {
                    continue;
                }
                ++$endpointCount;
                foreach ($this->extractRestMethods($endpoint) as $method) {
                    $methods[$method] = true;
                }
            }
            $methodNames = array_keys($methods);
            sort($methodNames, SORT_STRING);
            $result[(string) $route] = [
                'methods' => $methodNames,
                'endpoint_count' => $endpointCount,
            ];
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @return array<string,array<string,mixed>> */
    private function abilities(): array
    {
        if (!function_exists('wp_get_abilities')) {
            return [];
        }

        $result = [];
        foreach ((array) \wp_get_abilities() as $key => $ability) {
            if (!is_object($ability) || !method_exists($ability, 'get_name')) {
                continue;
            }
            $name = (string) $ability->get_name();
            if ('' === $name) {
                $name = (string) $key;
            }
            $result[$name] = [
                'label' => method_exists($ability, 'get_label') ? (string) $ability->get_label() : '',
                'description' => method_exists($ability, 'get_description') ? (string) $ability->get_description() : '',
                'category' => method_exists($ability, 'get_category') ? (string) $ability->get_category() : '',
                'input_schema' => method_exists($ability, 'get_input_schema') ? $this->safeJson($ability->get_input_schema()) : [],
                'output_schema' => method_exists($ability, 'get_output_schema') ? $this->safeJson($ability->get_output_schema()) : [],
                'meta' => method_exists($ability, 'get_meta') ? $this->safeJson($ability->get_meta()) : [],
            ];
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @return array<string,array<string,mixed>> */
    private function roles(): array
    {
        $rolesObject = \wp_roles();
        $roles = is_object($rolesObject) ? (array) ($rolesObject->roles ?? []) : [];
        $result = [];
        foreach ($roles as $slug => $role) {
            if (!is_array($role)) {
                continue;
            }
            $capabilities = [];
            foreach ((array) ($role['capabilities'] ?? []) as $capability => $granted) {
                if ($granted) {
                    $capabilities[] = (string) $capability;
                }
            }
            sort($capabilities, SORT_STRING);
            $result[(string) $slug] = [
                'name' => (string) ($role['name'] ?? $slug),
                'capabilities' => $capabilities,
            ];
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @return array<string,mixed> */
    private function features(): array
    {
        $namespaces = [];
        if (function_exists('rest_get_server')) {
            $server = \rest_get_server();
            if (is_object($server) && method_exists($server, 'get_namespaces')) {
                $namespaces = array_values(array_map('strval', (array) $server->get_namespaces()));
                sort($namespaces, SORT_STRING);
            }
        }

        return [
            'abilities_api' => function_exists('wp_get_abilities'),
            'rest_api' => function_exists('rest_get_server'),
            'multisite' => function_exists('is_multisite') && \is_multisite(),
            'rest_namespaces' => $namespaces,
        ];
    }

    /** @return array<string,string> */
    private function capabilityObject(mixed $capabilities): array
    {
        if (!is_object($capabilities) && !is_array($capabilities)) {
            return [];
        }

        $result = [];
        foreach ((array) $capabilities as $operation => $capability) {
            if (is_string($capability) || is_int($capability) || is_float($capability) || is_bool($capability)) {
                $result[(string) $operation] = (string) $capability;
            }
        }
        ksort($result, SORT_STRING);

        return $result;
    }

    /** @return list<string> */
    private function extractRestMethods(array $endpoint): array
    {
        $value = $endpoint['methods'] ?? null;
        $methods = [];

        if (is_string($value)) {
            foreach (preg_split('/[\s,|]+/', $value) ?: [] as $method) {
                if ('' !== $method) {
                    $methods[] = strtoupper($method);
                }
            }
        } elseif (is_array($value)) {
            foreach ($value as $key => $enabled) {
                if (is_string($key) && $enabled) {
                    $methods[] = strtoupper($key);
                } elseif (is_int($key) && is_string($enabled)) {
                    $methods[] = strtoupper($enabled);
                }
            }
        }

        $methods = array_values(array_unique($methods));
        sort($methods, SORT_STRING);

        return $methods;
    }

    private function safeJson(mixed $value): mixed
    {
        if (null === $value || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                if (is_object($item) || is_resource($item)) {
                    continue;
                }
                $result[$key] = $this->safeJson($item);
            }
            return $result;
        }

        return null;
    }
}

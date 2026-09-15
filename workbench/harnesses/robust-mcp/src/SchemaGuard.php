<?php

declare(strict_types=1);

namespace Chattanooga\RobustMcp;

use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Exceptions\SchemaException;

final class SchemaGuard
{
    public const DRAFT_2020_12 = 'https://json-schema.org/draft/2020-12/schema';
    public const MAX_SCHEMA_BYTES = 131072;
    public const MAX_SCHEMA_DEPTH = 32;
    public const MAX_SCHEMA_NODES = 4096;
    public const MAX_COMPOSITION_SCORE = 4096;
    public const MAX_DATA_DEPTH = 64;
    public const MAX_DATA_NODES = 20000;
    public const MAX_VALIDATION_ERRORS = 8;

    /** @var list<string> */
    private const OPIS_EXTENSION_KEYWORDS = [
        '$data',
        '$error',
        '$filters',
        '$globals',
        '$map',
        '$pragma',
        '$slots',
    ];

    private CompliantValidator $validator;
    private ErrorFormatter $formatter;

    public function __construct()
    {
        $this->validator = new CompliantValidator();
        $this->validator->setMaxErrors(self::MAX_VALIDATION_ERRORS);
        $this->validator->setStopAtFirstError(false);
        $this->formatter = new ErrorFormatter();
    }

    /**
     * Refuse unsafe or invalid JSON Schema before it reaches the MCP registry.
     *
     * @param array<string,mixed>|object $schema
     */
    public function assertSafeSchema(array|object $schema, string $label): void
    {
        $schema = $this->normalizeRootSchema($schema);
        $encoded = json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($encoded) > self::MAX_SCHEMA_BYTES) {
            throw new SchemaGuardException(sprintf('%s exceeds the %d-byte schema limit.', $label, self::MAX_SCHEMA_BYTES));
        }

        $nodes = 0;
        $this->scanSchema($schema, 0, '$', $nodes, 1, $label);

        $schemaObject = json_decode($encoded, false, 512, JSON_THROW_ON_ERROR);
        if ($schemaObject instanceof \stdClass && !property_exists($schemaObject, '$schema')) {
            $schemaObject->{'$schema'} = self::DRAFT_2020_12;
        }

        // Force Opis to parse and resolve the reachable schema. Whether this
        // sentinel validates is irrelevant; parser/resolver failures are not.
        try {
            $this->validator->validate(new \stdClass(), $schemaObject);
        } catch (SchemaException $error) {
            throw new SchemaGuardException(sprintf('%s is not a valid JSON Schema 2020-12 document: %s', $label, $error->getMessage()), 0, $error);
        } catch (\Throwable $error) {
            throw new SchemaGuardException(sprintf('%s could not be safely compiled.', $label), 0, $error);
        }
    }

    /**
     * @param array<string,mixed>|object $schema
     */
    public function assertValidData(mixed $data, array|object $schema, string $label, bool $rootObject = false): void
    {
        $nodes = 0;
        $this->assertDataBounds($data, 0, $label, $nodes);

        $schema = $this->normalizeRootSchema($schema);
        $encodedSchema = json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $schemaObject = json_decode($encodedSchema, false, 512, JSON_THROW_ON_ERROR);
        if ($schemaObject instanceof \stdClass && !property_exists($schemaObject, '$schema')) {
            $schemaObject->{'$schema'} = self::DRAFT_2020_12;
        }

        $jsonValue = $this->toJsonValue($data, $rootObject);

        try {
            $result = $this->validator->validate($jsonValue, $schemaObject);
        } catch (SchemaException $error) {
            throw new SchemaGuardException(sprintf('%s schema failed during validation.', $label), 0, $error);
        } catch (\Throwable $error) {
            throw new SchemaGuardException(sprintf('%s validation failed internally.', $label), 0, $error);
        }

        if ($result->isValid()) {
            return;
        }

        $error = $result->error();
        $details = null === $error ? [] : $this->formatter->formatFlat($error);
        $messages = [];
        foreach ($details as $pointer => $message) {
            if (count($messages) >= self::MAX_VALIDATION_ERRORS) {
                break;
            }
            $messages[] = sprintf(
                '%s: %s',
                (string) $pointer,
                is_array($message) ? implode('; ', array_map('strval', $message)) : (string) $message,
            );
        }

        $summary = [] === $messages ? 'schema constraint failed' : implode(' | ', $messages);
        throw new SchemaViolationException(sprintf('%s does not conform to its declared JSON Schema: %s', $label, $summary));
    }

    /**
     * @param array<string,mixed>|object $node
     */
    private function scanSchema(
        array|object $node,
        int $depth,
        string $path,
        int &$nodes,
        int $compositionScore,
        string $label,
    ): void {
        if ($depth > self::MAX_SCHEMA_DEPTH) {
            throw new SchemaGuardException(sprintf('%s exceeds the maximum schema depth of %d at %s.', $label, self::MAX_SCHEMA_DEPTH, $path));
        }

        ++$nodes;
        if ($nodes > self::MAX_SCHEMA_NODES) {
            throw new SchemaGuardException(sprintf('%s exceeds the maximum schema node count of %d.', $label, self::MAX_SCHEMA_NODES));
        }

        $entries = is_object($node) ? get_object_vars($node) : $node;
        foreach ($entries as $key => $value) {
            $key = (string) $key;
            $childPath = $path . '/' . str_replace(['~', '/'], ['~0', '~1'], $key);

            if ('$schema' === $key) {
                if (!is_string($value) || self::DRAFT_2020_12 !== rtrim($value, '#')) {
                    throw new SchemaGuardException(sprintf('%s must use JSON Schema draft 2020-12 at %s.', $label, $childPath));
                }
            }

            if ('$ref' === $key || '$dynamicRef' === $key) {
                if (!is_string($value) || '' === $value || '#' !== $value[0]) {
                    throw new SchemaGuardException(sprintf('%s contains a non-same-document %s at %s; external references are forbidden.', $label, $key, $childPath));
                }
            }

            if (in_array($key, self::OPIS_EXTENSION_KEYWORDS, true)) {
                throw new SchemaGuardException(sprintf('%s uses non-standard Opis keyword %s at %s.', $label, $key, $childPath));
            }

            $childCompositionScore = $compositionScore;
            if (in_array($key, ['allOf', 'anyOf', 'oneOf'], true) && is_array($value)) {
                $branches = max(1, count($value));
                if ($compositionScore > intdiv(self::MAX_COMPOSITION_SCORE, $branches)) {
                    throw new SchemaGuardException(sprintf('%s has excessive schema composition complexity at %s.', $label, $childPath));
                }
                $childCompositionScore *= $branches;
            }

            if (is_array($value) || $value instanceof \stdClass) {
                $this->scanSchema($value, $depth + 1, $childPath, $nodes, $childCompositionScore, $label);
            }
        }
    }

    private function assertDataBounds(mixed $value, int $depth, string $label, int &$nodes): void
    {
        if ($depth > self::MAX_DATA_DEPTH) {
            throw new SchemaViolationException(sprintf('%s exceeds the maximum JSON depth of %d.', $label, self::MAX_DATA_DEPTH));
        }

        ++$nodes;
        if ($nodes > self::MAX_DATA_NODES) {
            throw new SchemaViolationException(sprintf('%s exceeds the maximum JSON node count of %d.', $label, self::MAX_DATA_NODES));
        }

        if (is_array($value)) {
            foreach ($value as $child) {
                $this->assertDataBounds($child, $depth + 1, $label, $nodes);
            }
            return;
        }

        if ($value instanceof \stdClass) {
            foreach (get_object_vars($value) as $child) {
                $this->assertDataBounds($child, $depth + 1, $label, $nodes);
            }
            return;
        }

        if (is_object($value)) {
            try {
                $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $error) {
                throw new SchemaViolationException(sprintf('%s contains a non-JSON-serializable value.', $label), 0, $error);
            }
            $this->assertDataBounds($decoded, $depth + 1, $label, $nodes);
        }
    }

    /** @param array<string,mixed>|object $schema */
    private function normalizeRootSchema(array|object $schema): array|object
    {
        return is_array($schema) && [] === $schema ? new \stdClass() : $schema;
    }

    private function toJsonValue(mixed $data, bool $rootObject): mixed
    {
        if ($rootObject && [] === $data) {
            return new \stdClass();
        }

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            throw new SchemaViolationException('Value is not losslessly representable as JSON.', 0, $error);
        }
    }
}

class SchemaGuardException extends \RuntimeException
{
}

final class SchemaViolationException extends SchemaGuardException
{
}

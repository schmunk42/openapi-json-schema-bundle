<?php
// file generated with AI assistance: Claude Code - 2025-11-22 00:37:00

declare(strict_types=1);

namespace Schmunk42\OpenApiJsonSchema\Service;

use Schmunk42\OpenApiJsonSchema\Interface\SchemaProviderInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Registry for schema providers that generates unified schemas
 *
 * This service collects schemas from multiple SchemaProviderInterface implementations
 * and combines them into a unified anyOf schema. Useful for creating polymorphic
 * schemas where input must match one of several possible schemas.
 *
 * Features:
 * - Manual registration of schema providers
 * - Automatic caching of unified schema
 * - Configurable cache TTL
 * - Validation of schema files
 */
class SchemaRegistry
{
    private const CACHE_KEY = 'schmunk42_openapi_json_schema_unified';

    /** @var array<string, SchemaProviderInterface> */
    private array $providers = [];

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly int $cacheTtl = 3600
    ) {
    }

    /**
     * Register a schema provider
     *
     * @param SchemaProviderInterface $provider
     * @return void
     */
    public function register(SchemaProviderInterface $provider): void
    {
        $name = $provider->getName();

        if (isset($this->providers[$name])) {
            $this->logger->warning('Schema provider already registered, overwriting', [
                'provider' => $name
            ]);
        }

        $this->providers[$name] = $provider;
        $this->logger->debug('Registered schema provider', [
            'provider' => $name,
            'path' => $provider->getSchemaPath()
        ]);

        // Invalidate cache when providers change
        $this->invalidateCache();
    }

    /**
     * Register multiple schema providers (for tagged iterator injection)
     *
     * @param iterable<SchemaProviderInterface> $providers
     * @return void
     */
    public function registerProviders(iterable $providers): void
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Get a specific schema provider by name
     *
     * @param string $name
     * @return SchemaProviderInterface|null
     */
    public function getProvider(string $name): ?SchemaProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Get all registered schema providers
     *
     * @return array<string, SchemaProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Generate unified schema with anyOf pattern for all registered providers
     *
     * @return array JSON Schema with anyOf combining all provider schemas
     */
    public function getUnifiedSchema(): array
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $schema = $this->generateUnifiedSchema();

        $cacheItem->set($schema);
        $cacheItem->expiresAfter($this->cacheTtl);
        $this->cache->save($cacheItem);

        return $schema;
    }

    /**
     * Invalidate the schema cache
     *
     * Call this when providers are added/removed or schemas change
     *
     * @return void
     */
    public function invalidateCache(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY);
    }

    /**
     * Generate the unified schema structure
     *
     * @return array
     * @throws \RuntimeException If schema file not found or invalid JSON
     */
    private function generateUnifiedSchema(): array
    {
        if (empty($this->providers)) {
            $this->logger->warning('No schema providers registered, returning empty anyOf schema');
            return [
                '$schema' => 'https://json-schema.org/draft-07/schema#',
                'description' => 'No schemas available',
                'anyOf' => []
            ];
        }

        $anyOfSchemas = [];

        foreach ($this->providers as $provider) {
            $schemaPath = $provider->getSchemaPath();

            if (!file_exists($schemaPath)) {
                throw new \RuntimeException(
                    sprintf('Schema file not found for provider "%s": %s', $provider->getName(), $schemaPath)
                );
            }

            $schemaContent = file_get_contents($schemaPath);
            if ($schemaContent === false) {
                throw new \RuntimeException(
                    sprintf('Failed to read schema file for provider "%s": %s', $provider->getName(), $schemaPath)
                );
            }

            $providerSchema = json_decode($schemaContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException(
                    sprintf(
                        'Invalid JSON in schema file for provider "%s": %s',
                        $provider->getName(),
                        json_last_error_msg()
                    )
                );
            }

            // Add the provider schema to anyOf array
            $anyOfSchemas[] = $providerSchema;

            // Log individual schema for debugging
            $this->logger->debug('Loaded provider schema', [
                'provider' => $provider->getName(),
                'schemaPath' => $schemaPath,
                'hasProperties' => isset($providerSchema['properties'])
            ]);
        }

        $unifiedSchema = [
            '$schema' => 'https://json-schema.org/draft-07/schema#',
            'description' => sprintf(
                'Unified schema from %d provider(s). Must match one of the available schemas.',
                count($anyOfSchemas)
            ),
            'anyOf' => $anyOfSchemas,
        ];

        // Log the complete unified schema
        $this->logger->debug('Generated unified schema', [
            'providerCount' => count($anyOfSchemas),
            'providers' => array_keys($this->providers)
        ]);

        return $unifiedSchema;
    }
}

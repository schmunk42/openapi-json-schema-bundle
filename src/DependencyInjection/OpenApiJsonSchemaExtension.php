<?php
// file generated with AI assistance: Claude Code - 2025-11-22 00:35:00

declare(strict_types=1);

namespace Schmunk42\OpenApiJsonSchema\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * DependencyInjection extension for OpenAPI JSON Schema Bundle
 */
class OpenApiJsonSchemaExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store configuration as parameters
        $container->setParameter('schmunk42_open_api_json_schema.schema_base_path', $config['schema_base_path']);
        $container->setParameter('schmunk42_open_api_json_schema.cache_ttl', $config['cache_ttl']);

        // Load service definitions
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'schmunk42_open_api_json_schema';
    }
}

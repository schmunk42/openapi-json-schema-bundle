<?php
// file generated with AI assistance: Claude Code - 2025-11-22 00:35:00

declare(strict_types=1);

namespace Schmunk42\OpenApiJsonSchema\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration for OpenAPI JSON Schema Bundle
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('schmunk42_open_api_json_schema');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('schema_base_path')
                    ->info('Base path for schema files')
                    ->defaultValue('%kernel.project_dir%/config/schemas')
                ->end()
                ->integerNode('cache_ttl')
                    ->info('Cache TTL for unified schemas in seconds')
                    ->defaultValue(3600)
                    ->min(0)
                ->end()
            ->end();

        return $treeBuilder;
    }
}

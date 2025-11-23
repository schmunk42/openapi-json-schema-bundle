<?php
// file generated with AI assistance: Claude Code - 2025-11-22 00:35:00

declare(strict_types=1);

namespace Schmunk42\OpenApiJsonSchema;

use Schmunk42\OpenApiJsonSchema\DependencyInjection\OpenApiJsonSchemaExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for JSON Schema integration with API Platform OpenAPI documentation
 *
 * Features:
 * - Automatic JSON Schema injection into OpenAPI specs via #[JsonSchema] attribute
 * - File-based or dynamic schema generation
 * - Schema registry for multi-source schema composition
 * - Support for anyOf schemas from multiple providers
 */
class OpenApiJsonSchemaBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new OpenApiJsonSchemaExtension();
        }
        return $this->extension;
    }
}

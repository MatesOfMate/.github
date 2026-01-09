# MatesOfMate 🤝

Community-driven extensions for [Symfony AI Mate](https://github.com/symfony/ai-mate) — the MCP server that gives AI assistants superpowers for PHP development.

## What is this?

Symfony AI Mate creates a local MCP (Model Context Protocol) server that enhances AI assistants with knowledge about your PHP application. **MatesOfMate** is the community home for extensions that add framework-specific, CMS-specific, or domain-specific tools.

## Available Extensions

| Extension                          | Description                           | Status |
|------------------------------------|---------------------------------------|--------|
| [phpstan-extension](https://packagist.org/packages/matesofmate/phpstan-extension) | PHPStan static analysis tools with token-optimized output | ✅     |
| [phpunit-extension](https://packagist.org/packages/matesofmate/phpunit-extension) | PHPUnit testing tools and test introspection | ✅     |

*Want to add your extension? [Create an issue to request repository](https://github.com/matesofmate/.github/issues)!*

## Creating Your Own Extension

Check out our [extension-template](https://github.com/matesofmate/extension-template) to get started quickly.

Extensions can provide:
- **Tools** — callable functions for the AI (e.g., `sulu-content-types`, `doctrine-schema`)
- **Resources** — static context about your framework/CMS
- **Prompts** — pre-built prompt templates for common tasks

## Quick Start

```bash
# Install an extension
composer require --dev matesofmate/example-extension

# Discover new tools
vendor/bin/mate discover

# Verify it's loaded
vendor/bin/mate debug:extensions
vendor/bin/mate debug:capabilities
```

## Join the Community

- ⭐ [awesome-mate](https://github.com/matesofmate/awesome-mate) — curated resources for AI Mate
- 🐛 [Issues](https://github.com/matesofmate/.github/issues) — report bugs, request features
- 🤝 [Contributing](https://github.com/matesofmate/.github/blob/main/CONTRIBUTING.md) — help us grow

## Related Projects

- [symfony/ai-mate](https://github.com/symfony/ai-mate) — the core MCP server
- [symfony/ai](https://github.com/symfony/ai) — full Symfony AI component suite

---

*"Because every Mate needs Mates"*
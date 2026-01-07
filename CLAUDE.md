# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Purpose

This is the **`.github` repository** for the MatesOfMate organization - a special GitHub repository that contains organization-wide configuration, documentation, and templates.

When placed at `github.com/matesofmate/.github`, this repository automatically provides:
- Default community health files (CONTRIBUTING.md, CODE_OF_CONDUCT.md)
- Shared issue templates and PR templates for all organization repositories
- Organization profile content (profile/README.md)
- Default CODEOWNERS file

## Repository Structure

```
.github/
├── CODEOWNERS                    # Default code ownership for organization repos
├── ISSUE_TEMPLATE/
│   ├── 1-bug_report.md          # Bug report template
│   ├── 2-feature_request.md     # Feature request template
│   ├── 3-new_extension.md       # New extension submission template
│   └── config.yml               # Issue template configuration
└── PULL_REQUEST_TEMPLATE.md     # Default PR template

CONTRIBUTING.md                   # Organization-wide contributing guidelines
README.md                         # Organization overview and documentation
LICENSE                          # MIT license
profile/
└── README.md                    # Organization profile page content
```

## MatesOfMate Ecosystem Overview

**MatesOfMate** is a community-driven ecosystem for Symfony AI Mate extensions:

- **Core Concept**: Extensions provide MCP (Model Context Protocol) tools and resources to AI assistants
- **Naming Convention**: `matesofmate/{framework}-extension` packages
- **Base Namespace**: `MatesOfMate\{Framework}\`
- **Tool Naming**: `{framework}-{action}` format (e.g., `sulu-content-types`)

## Key Documentation Files

### README.md (Organization Overview)
- Explains what MatesOfMate is
- Lists available extensions
- Provides quick start guide
- Links to extension-template and awesome-mate

### CONTRIBUTING.md (Contribution Guidelines)
Contains guidelines for:
1. **Submitting new extensions** - Using extension-template, naming conventions, required documentation
2. **Extension structure requirements** - File layout, composer.json configuration, namespace patterns
3. **Tool best practices** - Single responsibility, clear descriptions, structured output
4. **Resource best practices** - URI schemes, static content patterns, helpful context
5. **Code quality standards** - PHP CS Fixer, PHPStan level 8, Rector
6. **PR process** - Fork, branch, test, lint, commit, PR checklist

### Issue Templates

**1-bug_report.md**: Standard bug report for extensions
**2-feature_request.md**: Feature request for new tools/capabilities
**3-new_extension.md**: Submission form for listing new extensions in awesome-mate collection

**Quality checklist items**:
- Follows contributing guidelines
- Comprehensive README
- Tests with passing CI
- Published on Packagist
- Semantic versioning
- MIT or compatible license

## Making Changes to Organization Files

### When to Update README.md
- Adding new extension to the "Available Extensions" table
- Updating ecosystem description or quick start guide
- Changing community links or related projects

### When to Update CONTRIBUTING.md
- Changing extension naming conventions
- Updating required files or structure
- Modifying code quality standards
- Adding new tool/resource best practices

### When to Update Issue Templates
- Adding new issue categories
- Modifying submission requirements
- Updating quality checklist items

### When to Update profile/README.md
- Changing organization profile display
- Updating organization description or goals
- Modifying featured repositories or highlights

## Important Standards

### Extension Naming
- **Package**: `matesofmate/{framework}-extension`
- **Namespace**: `MatesOfMate\{Framework}\` or `MatesOfMate\{Framework}Extension\`
- **Tool names**: `{framework}-{action}` (lowercase with hyphens)

### JSON Encoding Standard
All JSON encoding in examples must use:
```php
json_encode($data, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT)
```

### Code Quality Requirements
Extensions must support:
- **PHP 8.2+** minimum
- **PHPStan level 8** static analysis
- **PHP CS Fixer** with PSR-12 and Symfony standards
- **Rector** for automated refactoring
- **PHPUnit** for testing

### Required Composer Scripts
Extensions must provide:
- `composer test` - Run PHPUnit tests
- `composer lint` - Run all quality checks (validate, rector, php-cs-fixer, phpstan)
- `composer fix` - Auto-fix code style and apply refactorings

## Git Workflow

### Main Branch
- Default branch: `main`
- Protected branch requiring PR reviews
- All changes via pull requests

### Commit Message Convention

**Important**: Keep commit messages clean without AI attribution.

**Format**:
```
Short summary (50 chars or less)

- Conceptual change description
- Another concept or improvement
- More changes as needed
```

**✅ Good Examples**:
```
Strengthen extension quality requirements

- Raise minimum test coverage expectations
- Clarify documentation standards
- Add Packagist publication as requirement
```

```
Improve contributor onboarding experience

- Simplify initial setup instructions
- Add troubleshooting guidance
- Clarify code quality expectations
```

**❌ Bad Examples**:
```
Update files

Co-Authored-By: Claude Code <noreply@anthropic.com>
```

```
Fix documentation - coded by claude-code
```

**Rules**:
- ❌ NO AI attribution (no "Co-Authored-By: Claude", "coded by claude-code", etc.)
- ✅ Short, descriptive summary line
- ✅ Bullet list describing concepts/improvements, not file names
- ✅ Natural language explaining what changed
- ✅ Focus on the WHY and WHAT, not technical details

### Making Changes
```bash
# Clone repository
git clone git@github.com:matesofmate/.github.git
cd .github

# Create feature branch
git checkout -b update/description

# Make changes to documentation or templates
# Commit with proper format
git add .
git commit -m "Strengthen extension quality requirements

- Raise minimum test coverage expectations
- Clarify documentation standards"

git push origin update/description

# Create PR on GitHub
```

## Common Operations

### Adding a New Extension to README
1. Edit README.md "Available Extensions" table
2. Add row with extension name, description, and status
3. Keep alphabetical order by extension name
4. Update count if needed

### Updating Code Examples
When updating code examples in CONTRIBUTING.md:
- Use current namespace patterns (`MatesOfMate\ExampleExtension\`)
- Include proper JSON encoding flags
- Follow current coding standards (no strict types, no final classes)
- Match extension-template conventions

### Modifying Issue Templates
- Keep YAML frontmatter intact (name, about, title, labels)
- Maintain consistent checkbox format
- Update quality checklist to match current standards
- Test templates by creating test issues

## Cross-Repository Coordination

This repository coordinates with:
- **extension-template**: Ensure CONTRIBUTING.md matches template conventions
- **awesome-mate**: New extension submissions go through issue template
- All **extension repositories**: Inherit issue templates and PR templates

When updating standards here, also update:
- extension-template/README.md
- extension-template/CLAUDE.md
- Individual extension documentation as needed

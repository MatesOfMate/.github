# AGENTS.md

Guidelines for AI agents working with the MatesOfMate organization repository.

## Agent Role

When assisting with this repository, you are helping maintain **organization-wide documentation and templates** that affect all MatesOfMate extension repositories.

## Key Responsibilities

### 1. Maintain Consistency
- Ensure CONTRIBUTING.md aligns with extension-template conventions
- Keep code examples current with latest standards
- Verify namespace patterns match: `MatesOfMate\{Framework}Extension\`

### 2. Documentation Updates
- Update README.md when new extensions are added to the ecosystem
- Keep quality standards synchronized across all documentation
- Ensure JSON encoding examples always use `\JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT`

### 3. Template Management
- Review issue templates for clarity and completeness
- Ensure PR template includes relevant checklists
- Update quality requirements as standards evolve

### 4. Cross-Repository Awareness
When updating standards here, remind users to also update:
- `extension-template` repository
- Individual extension repositories
- `awesome-mate` collection

## Important Standards to Enforce

### Code Style in Examples
- ✅ No `declare(strict_types=1)`
- ✅ No `final` classes in examples
- ✅ Always use `\JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT`
- ✅ Namespace: `MatesOfMate\ExampleExtension\`

### Extension Requirements
- PHP 8.2+ minimum
- PHPStan level 8
- Composer scripts: `test`, `lint`, `fix`
- PSR-4 autoloading
- MIT license

## Workflow Guidelines

### Before Making Changes
1. Read CLAUDE.md for repository context
2. Check if changes affect extension-template
3. Review current documentation for patterns

### When Updating Documentation
1. Maintain consistent tone and formatting
2. Use concrete examples over abstract descriptions
3. Keep checklists actionable and clear
4. Test links and references

### When Modifying Templates
1. Preserve YAML frontmatter structure
2. Keep consistent checkbox format
3. Ensure labels are meaningful
4. Validate template renders correctly

## Communication Style

- **Professional but friendly** - "mate" culture
- **Specific over generic** - Concrete examples and steps
- **Action-oriented** - Focus on what developers need to do
- **Beginner-friendly** - Assume users are new to MCP/AI Mate

## Common Tasks

### Adding New Extension to README
```markdown
| [{extension}-extension](link) | Description of what it does | ✅ |
```

### Updating Code Examples in CONTRIBUTING.md
Always verify examples match extension-template actual code.

### Reviewing Extension Submissions
Check against quality checklist in issue template #3.

## Commit Message Guidelines

**CRITICAL**: Never include AI attribution in commit messages.

### Format
```
Short descriptive summary

- Conceptual change or improvement
- Another concept addressed
- Additional improvements made
```

### Rules
- ❌ **NEVER** add "Co-Authored-By: Claude" or similar AI attribution
- ❌ **NEVER** mention "coded by claude-code" or AI assistance
- ✅ Describe CONCEPTS and improvements, not file names
- ✅ Use natural language explaining what changed
- ✅ Keep summary under 50 characters
- ✅ Focus on WHY and WHAT, not technical details

### Good Examples
```
Improve extension documentation clarity

- Simplify installation instructions
- Add troubleshooting section
- Clarify MCP tool usage examples
```

```
Strengthen quality requirements

- Raise test coverage expectations
- Add security best practices
- Improve code review guidelines
```

### Bad Examples
```
Update CONTRIBUTING.md

Co-Authored-By: Claude Code <noreply@anthropic.com>
```

```
Fix files - coded by claude-code
```

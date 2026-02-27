# Developer Rules & Guidelines

> **IMPORTANT**: These rules must be followed when modifying the SAD Workflow Manager plugin.

## 1. Documentation First

- **Update Docs**: If you modify logic, add a class, or change how a feature works, you **MUST** update `docs/technical_overview.md`.
- **Explain Why**: In your commit messages or PRs, explain _why_ a change was made, not just _what_ changed.

## 2. Code Structure

- **Modularity**: Keep logic within the appropriate class in `includes/`.
  - Rule logic -> `SAD_Rule_Engine`
  - Integrations -> `SAD_Integration`
  - Admin UI -> `SAD_Workflow_Admin`
- **Don't Bloat Main File**: The main `sad-workflow-manager.php` should only handle initialization.

## 3. Naming Conventions

- **Classes**: `SAD_Name_Of_Class`
- **Functions**: `function_name_with_underscores`
- **Variables**: `$variable_name`
- **Prefix**: Use `sad_` prefix for global functions, options, or potential collisions.

## 4. Logging & Debugging

- **Use SAD_Logger**: Do not use `error_log` directly if `SAD_Logger` is available. It provides standardized formatting.
- **Clean Up**:Remove temporary logging before merging to production.

## 5. Workflow Taxonomy

- **Do not expose**: The `article_progress` taxonomy is designed to be internal. Do not set `public => true` unless strictly necessary for a frontend feature.

# SAD Workflow Manager - Technical Overview

## 1. Introduction

The **SAD Workflow Manager** handles automated tagging and workflow transitions for `scholarly_article` post types in Outliny. It serves two main purposes:

1.  **Rule-Based Automation**: Automatically adds/removes tags or changes post status based on conditions (e.g., specific meta fields being present).
2.  **Integration Sync**: Synchronizes status from other systems (Tickets, Invoices) to the article's progress tags.

## 2. File Structure & Core Classes

| File                                       | Class                   | Purpose                                                                                   |
| :----------------------------------------- | :---------------------- | :---------------------------------------------------------------------------------------- |
| `sad-workflow-manager.php`                 | N/A                     | Bootstrap file. Defines constants and initializes the plugin.                             |
| `includes/class-sad-workflow-manager.php`  | `SAD_Workflow_Manager`  | Main class. Orchestrates the loader and instantiates all other components.                |
| `includes/class-sad-workflow-loader.php`   | `SAD_Workflow_Loader`   | Registers and executes WordPress hooks (Actions & Filters).                               |
| `includes/class-sad-rule-engine.php`       | `SAD_Rule_Engine`       | **CORE LOGIC**. Evaluates rules against posts and applies tags/status changes.            |
| `includes/class-sad-rule-model.php`        | `SAD_Rule_Model`        | Handles database properties for rules (CRUD operations on `sad_workflow_rules` option).   |
| `includes/class-sad-integration.php`       | `SAD_Integration`       | **SYNC LOGIC**. Listens to external events (Ticket/Invoice saves) to update article tags. |
| `includes/class-sad-admin.php`             | `SAD_Workflow_Admin`    | Admin UI logic for the Rule Builder page and displaying tags in the post list.            |
| `includes/class-sad-activity-log.php`      | `SAD_Activity_Log`      | Displays the "Outliny Activity Log" meta box on article edit screens.                     |
| `includes/class-sad-workflow-taxonomy.php` | `SAD_Workflow_Taxonomy` | Registers the private `article_progress` taxonomy.                                        |

## 3. Core Logic Breakdown

### A. The Rule Engine (`SAD_Rule_Engine`)

The engine runs on `transition_post_status` and `save_post` hooks.

1.  **Trigger**: Checks if the post's status matches the rule's `trigger_status`.
2.  **Conditions**: Evaluates a list of conditions (Field + Operator + Value).
    - Supports Standard WP fields (`post_title`, `post_status`).
    - Supports Pods/Meta fields via `pods()` or `get_post_meta()`.
    - **Advanced**: Supports `rule_match:[RULE_ID]` to inherit logic from other rules (with recursion protection).
    - **Integration**: Supports `outliny_action_status:[BUTTON_ID]`.
3.  **Actions**:
    - **Add Tag**: Adds a term to `article_progress` taxonomy.
    - **Remove Tag**: Removes a term from `article_progress`.
    - **Change Status**: Updates the post status (e.g., move to 'Reviewing').

**Meta Storage**:
When a tag is added by a rule, the engine stores the _reason_ in the `_sad_tag_reasons` post meta.

```php
// Structure of _sad_tag_reasons meta
[
    {term_id} => [
        'reason' => 'Field X is not empty',
        'rule_id' => 'rule_123'
    ]
]
```

### B. Integration Sync (`SAD_Integration`)

This class listens for `save_post` on `stt_ticket` and `sad_invoice` types.

#### Ticket Integration

- **Trigger**: When a Ticket is saved or its status changes.
- **Logic**:
  - If Ticket is **Open/In-Progress**: Adds "Ticket Created" tag to the related Article.
  - If Ticket is **Closed**: Checks if _any other_ open tickets exist for that article. If none, removes the "Ticket Created" tag.
- **Tag Reason**: "Linked Ticket #{ID} is Open".

#### Outliny Actions Integration

- **Trigger**: `outliny_session_completed` hook.
- **Logic**:
  - When an Outliny Action (Button) finishes, it triggers the engine for that post.
  - **Condition**: `outliny_action_status:[BUTTON_ID]` checks the `outliny_action_logs` table for `success` or `error` status.
- **Purpose**: Automate workflow steps based on external API success/failure (e.g., "Add 'Published' tag if export succeeded").

#### Invoice Integration

- **Trigger**: When an Invoice is saved.
- **Logic**:
  - If Invoice status is `sent`: Adds "Invoice Sent" tag.
  - If Invoice status is `paid`: Adds "Invoice Paid" tag.

### C. Admin UI & Display (`SAD_Workflow_Admin`)

- **Rule Builder**: A React/JS-based UI in _Settings > Workflow Rules_ to create/edit rules.
- **Post List Columns**: Adds a column to the "All Articles" screen showing current Progress Tags.
  - _Self-Healing_: The display function (`add_progress_tags_to_title`) checks for missing tags (like "Ticket Created") and re-adds them if valid conditions exist, ensuring data integrity.

## 4. Debugging & Troubleshooting

### Problem: "My rule isn't working!"

1.  **Check Logs**: Enable `SAD_Logger` if available.
2.  **Check Triggers**: Does the post status match the rule's trigger?
3.  **Check Conditions**:
    - Are you checking a meta field? Ensure the field key is correct.
    - Is the value logic correct (e.g., "Is Empty" vs "Is Not Empty")?

### Problem: "Ticket Created tag won't go away!"

1.  **Check Open Tickets**: The logic (`SAD_Integration::handle_ticket_save`) only removes the tag if **zero** open tickets remain for the article.
2.  **Verify Status**: Ensure the tickets are actually in a "closed" or "resolved" status (not custom statuses that might be considered open).

### Key Functions for Debugging

- `SAD_Rule_Engine::run_rules` -> Main entry point for rule evaluation.
- `SAD_Rule_Engine::check_condition` -> Where individual field values are compared.
- `SAD_Integration::handle_ticket_save` -> Logic for ticket sync.
- `SAD_Workflow_Admin::add_progress_tags_to_title` -> Logic for displaying tags in Admin List (includes self-healing).

## 5. Developer Notes

- **Logging**: Use `SAD_Logger::log()` for debugging.
- **Taxonomy**: `article_progress` is non-hierarchical and private (not queryable on frontend).

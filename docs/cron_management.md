# Workflow Cron Management

This document provides instructions on how to manage the `sad_workflow_daily_check` system via server-level crontab for maximum reliability.

## 1. Server-Level Cron Configuration (Recommended)

To run the workflow tracking daily at 1:00 AM IST, add the following line to your server's crontab (`crontab -e`). This method is preferred if WP-CLI is not available:

```bash
0 1 * * * php /home/runcloud/webapps/GlobalJournalsOrg/public_html/wp-content/plugins/gj-workflow-manager-main/bin/workflow-cron.php > /dev/null 2>&1
```

## 2. Alternative: Using WP-CLI
If you have WP-CLI installed and configured, you can use the following instead:

```bash
0 1 * * * /usr/local/bin/wp cron event run sad_workflow_daily_check --path=/home/runcloud/webapps/GlobalJournalsOrg/public_html > /dev/null 2>&1
```
*(Note: Ensure the path to `wp` is correct for your system. Run `which wp` to verify. If `/usr/local/bin/wp` does not exist, use the PHP script method above.)*

## 3. Trigger Manually (via Command Line)
If you want to run the workflow check immediately:
```bash
php /home/runcloud/webapps/GlobalJournalsOrg/public_html/wp-content/plugins/gj-workflow-manager-main/bin/workflow-cron.php
```

Or using WP-CLI:
```bash
wp cron event run sad_workflow_daily_check --path=/home/runcloud/webapps/GlobalJournalsOrg/public_html
```

## 3. Why Server Cron?
WordPress internal cron (WP-Cron) only triggers when someone visits the site. For scholarly article deadlines, a server-level cron is preferred because:
- It runs exactly when scheduled, regardless of site traffic.
- It is more reliable for critical automation.
- **Performance Note**: The system currently only checks articles uploaded in **2026** to ensure the cron completes quickly and doesn't process historical dead-data.

## 4. View Specific Article Deadlines
To see the deadlines calculated for a specific article ID:
```bash
wp post meta list [ARTICLE_ID] --keys=_sad_step_% --like --path=/home/runcloud/webapps/GlobalJournalsOrg/public_html
```

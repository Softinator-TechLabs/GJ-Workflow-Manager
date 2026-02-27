# Workflow Cron Management

This document provides instructions on how to manage the `sad_workflow_daily_check` system via server-level crontab for maximum reliability.

## 1. Server-Level Cron Configuration
To run the workflow tracking daily at 1:00 AM IST, add the following line to your server's crontab (`crontab -e`):

```bash
0 1 * * * /usr/local/bin/wp cron event run sad_workflow_daily_check --path=/home/runcloud/webapps/GlobalJournalsOrg/public_html > /dev/null 2>&1
```
*(Note: Ensure the path to `wp` is correct for your system. Run `which wp` to verify.)*

## 2. Trigger Manually (via Command Line)
If you want to run the workflow check immediately:
```bash
wp cron event run sad_workflow_daily_check --path=/home/runcloud/webapps/GlobalJournalsOrg/public_html
```

## 3. Why Server Cron?
WordPress internal cron (WP-Cron) only triggers when someone visits the site. For scholarly article deadlines, a server-level cron is preferred because:
- It runs exactly when scheduled, regardless of site traffic.
- It is more reliable for critical automation.

## 4. View Specific Article Deadlines
To see the deadlines calculated for a specific article ID:
```bash
wp post meta list [ARTICLE_ID] --keys=_sad_step_% --like --path=/home/runcloud/webapps/GlobalJournalsOrg/public_html
```

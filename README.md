# Hybrid Promotional Campaign Laboratory

Systems Administration and Maintenance lab. A hybrid promotional email pipeline:

- **Offline (local MySQL):** seed 10,000 fictional customers with Faker, then prepare a campaign by selecting 50 random opted-in customers into a transactional outbox (`marketing_outbox`, status `PENDING`).
- **Online (AWS SQS + Mailtrap):** publish outbox rows to an SQS queue, then a worker long-polls the queue, checks consent and duplicates, sends each email through Mailtrap SMTP, records the outcome in `sent_marketing_emails`, and deletes the message only after success. Messages that keep failing move to a dead-letter queue.

| Path | Purpose |
|---|---|
| `schema.sql` | Table definitions (customers, marketing_outbox, sent_marketing_emails) |
| `src/` | `Lab\` classes (PSR-4): Database, Settings, ActivityLogger, LocalDatabaseTools, CustomerSeeder, CampaignPreparer, QueuePublisher, EmailSender, EmailHistory, MarketingProcessor, QueueWorker |
| `*.php` (root) | Entry scripts; they only build objects and call methods |
| `verify-offline.sql` | Read-only verification queries for the offline stage |
| `iam-policy.json` | Least-privilege SQS policy template (placeholders) |
| `env.example.ps1` | Environment variable template (placeholders) |

## Requirements

- PHP 8.2+ with the `pdo_mysql` extension
- Composer
- AWS CLI (with a configured named profile)
- MySQL 8.0.16+ (CHECK constraints are enforced from 8.0.16)

## Setup

1. Install dependencies:
   ```powershell
   composer install
   ```
2. Create the database:
   ```sql
   CREATE DATABASE marketing_lab CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   ```
3. Set the environment variables. Copy `env.example.ps1` to `env.local.ps1` (git-ignored), replace the placeholders, and load it in each PowerShell window:
   ```powershell
   Copy-Item env.example.ps1 env.local.ps1
   . .\env.local.ps1
   ```

   | Variable | Example (placeholder) |
   |---|---|
   | `DB_HOST` / `DB_PORT` / `DB_NAME` | `127.0.0.1` / `3306` / `marketing_lab` |
   | `DB_USER` / `DB_PASSWORD` | `YOUR_DB_USER` / `YOUR_DB_PASSWORD` |
   | `AWS_PROFILE` / `AWS_REGION` | `YOUR_AWS_PROFILE` / `YOUR_AWS_REGION` |
   | `SQS_QUEUE_URL` | `https://sqs.YOUR_AWS_REGION.amazonaws.com/ACCOUNT_ID/MarketingEmailQueue-s12345` |
   | `MAILTRAP_HOST` / `MAILTRAP_PORT` | `sandbox.smtp.mailtrap.io` / `587` |
   | `MAILTRAP_USERNAME` / `MAILTRAP_PASSWORD` | `YOUR_MAILTRAP_USERNAME` / `YOUR_MAILTRAP_PASSWORD` |
   | `MAIL_FROM_ADDRESS` | `marketing@example.com` |
   | `MAILTRAP_DELAY_SECONDS` | `11` (default; Mailtrap free plan allows about 1 email per 10 s) |

## Run order

```powershell
php init-db.php                               # create tables from schema.sql
php seed-customers.php                        # 10,000 customers (8,000 opted in), safe to re-run
php prepare-campaign.php CAMP-001 PROMO15     # 50 random opted-in recipients -> PENDING outbox
php enqueue.php                               # publish PENDING rows to SQS -> QUEUED
php worker.php                                # process the queue (Ctrl+C to stop)
php worker.php --idle-exit=3                  # ...or exit after 3 empty polls
php inspect.php                               # counts and recent outbox rows (no names/emails)
```

Test helpers:

```powershell
php resubmit.php CAMP-001 CUST-00042                       # re-send an existing payload (duplicate test)
php send-diagnostic.php CAMP-RECOVERY CUST-08001 PROMO15   # corrected payload with a new requestId
```

Structured events are appended to `logs/events.jsonl` (UTC, camelCase identifiers, no personal data).

## What is never committed

The database, customer data, exports, logs and credentials stay out of version control (see `.gitignore`): `vendor/`, `logs/`, `.env`, `env.local.ps1`, `*.sql.gz`, `*.dump`, `exports/`, `*.csv`. Only `schema.sql` (structure, no data) and placeholder templates are committed. All secrets come from environment variables.

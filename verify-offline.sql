-- Offline verification queries (read-only, no data changes).
USE marketing_lab;

-- First 10 customers
SELECT customer_id, full_name, email, marketing_opt_in
  FROM customers
 ORDER BY customer_id
 LIMIT 10;

-- Total customers (expected 10000)
SELECT COUNT(*) AS total_customers FROM customers;

-- Opt-in split (expected 0 = 2000, 1 = 8000)
SELECT marketing_opt_in, COUNT(*) AS total
  FROM customers
 GROUP BY marketing_opt_in;

-- Outbox status counts
SELECT status, COUNT(*) AS total
  FROM marketing_outbox
 GROUP BY status;

-- CAMP-001 requests and distinct recipients (expected 50 / 50)
SELECT COUNT(*) AS requests, COUNT(DISTINCT customer_id) AS distinct_recipients
  FROM marketing_outbox
 WHERE campaign_id = 'CAMP-001';

-- CAMP-001 recipients who are not opted in (expected 0)
SELECT COUNT(*) AS ineligible_recipients
  FROM marketing_outbox o
  JOIN customers c ON c.customer_id = o.customer_id
 WHERE o.campaign_id = 'CAMP-001'
   AND c.marketing_opt_in <> 1;

-- Selection fingerprint (must stay the same after re-running prepare-campaign.php)
SELECT MD5(GROUP_CONCAT(customer_id ORDER BY customer_id)) AS selection_fingerprint, COUNT(*) AS total
  FROM marketing_outbox
 WHERE campaign_id = 'CAMP-001';

-- CAMP-001 history rows (expected 0 while offline)
SELECT COUNT(*) AS history_rows
  FROM sent_marketing_emails
 WHERE campaign_id = 'CAMP-001';

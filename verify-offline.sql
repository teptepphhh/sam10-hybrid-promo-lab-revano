-- Checks to run before going online. They only read data, nothing is changed
USE marketing_lab;

-- First 10 customers
SELECT customer_id, full_name, email, marketing_opt_in
  FROM customers
 ORDER BY customer_id
 LIMIT 10;

-- Total customers (should be 10000)
SELECT COUNT(*) AS total_customers FROM customers;

-- How many opted in (should be 0 = 2000, 1 = 8000)
SELECT marketing_opt_in, COUNT(*) AS total
  FROM customers
 GROUP BY marketing_opt_in;

-- How many outbox rows per status
SELECT status, COUNT(*) AS total
  FROM marketing_outbox
 GROUP BY status;

-- CAMP-001 should have 50 rows for 50 different customers
SELECT COUNT(*) AS requests, COUNT(DISTINCT customer_id) AS distinct_recipients
  FROM marketing_outbox
 WHERE campaign_id = 'CAMP-001';

-- CAMP-001 should not have anyone who did not opt in (should be 0)
SELECT COUNT(*) AS ineligible_recipients
  FROM marketing_outbox o
  JOIN customers c ON c.customer_id = o.customer_id
 WHERE o.campaign_id = 'CAMP-001'
   AND c.marketing_opt_in <> 1;

-- A hash of the picked customers. It should not change if we run prepare-campaign.php again
SELECT MD5(GROUP_CONCAT(customer_id ORDER BY customer_id)) AS selection_fingerprint, COUNT(*) AS total
  FROM marketing_outbox
 WHERE campaign_id = 'CAMP-001';

-- No emails are sent yet, so this should be 0
SELECT COUNT(*) AS history_rows
  FROM sent_marketing_emails
 WHERE campaign_id = 'CAMP-001';

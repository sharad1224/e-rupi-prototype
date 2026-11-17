-- Safe to re-run; demo dates intentionally set in the future for testing.
INSERT INTO consumers (ivrs_no,full_name,mobile) VALUES
 ('1234567890','Rahul Sharma','9000000001'),
 ('1234567891','Amit Verma','9000000002'),
 ('1234567892','Neha Patel','9000000003')
ON CONFLICT (ivrs_no) DO NOTHING;
INSERT INTO bills (consumer_id,bill_month,bill_amount,subsidy_amount,due_date)
SELECT c.id, DATE '2026-11-01', x.bill_amount, x.subsidy_amount, DATE '2026-11-30'
FROM (VALUES ('1234567890',1000.00,900.00),('1234567891',850.00,650.00),('1234567892',950.00,800.00)) AS x(ivrs,bill_amount,subsidy_amount)
JOIN consumers c ON c.ivrs_no=x.ivrs
ON CONFLICT (consumer_id,bill_month) DO NOTHING;

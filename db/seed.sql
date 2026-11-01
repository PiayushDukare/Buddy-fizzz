USE vofgmclr_buddyfizz;

INSERT IGNORE INTO users (name, email, phone, password, role, status) VALUES
('Buddy Fizz Admin', 'admin@buddyfizz.com', '+917774067890', '$2y$12$SrXCZ/wWAqE5wIimUzg8GuLZj/Lrh6P8N7Jk94RKvypgGftXXjJPG', 'admin', 'active'),
('Ravi Naik',   'ravi@buddyfizz-partner.com',   '+919820011111', '$2y$12$kiMoC/SMlZiXi/wVvIQ3Dum.UICAlx4fpGK6Fav6ksG/jAsYZxa8e', 'partner', 'active'),
('Suresh Kamat','suresh@buddyfizz-partner.com', '+919820022222', '$2y$12$kiMoC/SMlZiXi/wVvIQ3Dum.UICAlx4fpGK6Fav6ksG/jAsYZxa8e', 'partner', 'active'),
('Anand Prabhu','anand@buddyfizz-retail.com',  '+919820033333', '$2y$12$kiMoC/SMlZiXi/wVvIQ3Dum.UICAlx4fpGK6Fav6ksG/jAsYZxa8e', 'retailer', 'active'),
('Priya Shet',  'priya@buddyfizz-retail.com',  '+919820044444', '$2y$12$kiMoC/SMlZiXi/wVvIQ3Dum.UICAlx4fpGK6Fav6ksG/jAsYZxa8e', 'retailer', 'active'),
('Vijay Gaonkar','vijay@buddyfizz-retail.com', '+919820055555', '$2y$12$kiMoC/SMlZiXi/wVvIQ3Dum.UICAlx4fpGK6Fav6ksG/jAsYZxa8e', 'retailer', 'active'),
('Deepak Naik', 'deepak@buddyfizz-retail.com', '+919820066666', '$2y$12$kiMoC/SMlZiXi/wVvIQ3Dum.UICAlx4fpGK6Fav6ksG/jAsYZxa8e', 'retailer', 'active'),
('Meena Rao',   'meena@buddyfizz-retail.com',  '+919820077777', '$2y$12$kiMoC/SMlZiXi/wVvIQ3Dum.UICAlx4fpGK6Fav6ksG/jAsYZxa8e', 'retailer', 'active');

INSERT INTO stores (user_id, partner_id, name, owner_name, phone, email, gstin, type, address, village, taluka, district, state, pincode, lat, lng, status)
SELECT u.id, NULL, 'North Goa Distributors', 'Ravi Naik', '+919820011111', 'ravi@buddyfizz-partner.com', '30ABCDN1234A1Z5', 'distributor', 'Market Road', 'Mapusa', 'Bardez', 'North Goa', 'Goa', '403507', 15.5937000, 73.8142000, 'active'
FROM users u WHERE u.email='ravi@buddyfizz-partner.com' AND NOT EXISTS (SELECT 1 FROM stores s WHERE s.user_id=u.id);
INSERT INTO stores (user_id, partner_id, name, owner_name, phone, email, gstin, type, address, village, taluka, district, state, pincode, lat, lng, status)
SELECT u.id, NULL, 'South Goa Beverages', 'Suresh Kamat', '+919820022222', 'suresh@buddyfizz-partner.com', '30ABCDS5678B1Z9', 'distributor', 'Station Road', 'Margao', 'Salcete', 'South Goa', 'Goa', '403601', 15.2993000, 74.1240000, 'active'
FROM users u WHERE u.email='suresh@buddyfizz-partner.com' AND NOT EXISTS (SELECT 1 FROM stores s WHERE s.user_id=u.id);
INSERT INTO stores (user_id, partner_id, name, owner_name, phone, email, gstin, type, address, village, taluka, district, state, pincode, lat, lng, status)
SELECT u.id, (SELECT id FROM users WHERE email='ravi@buddyfizz-partner.com'), 'Anand Kirana Store', 'Anand Prabhu', '+919820033333', 'anand@buddyfizz-retail.com', '30ABCDA1111C1Z1', 'retail', 'Main Bazaar', 'Mapusa', 'Bardez', 'North Goa', 'Goa', '403507', 15.5910000, 73.8090000, 'active'
FROM users u WHERE u.email='anand@buddyfizz-retail.com' AND NOT EXISTS (SELECT 1 FROM stores s WHERE s.user_id=u.id);
INSERT INTO stores (user_id, partner_id, name, owner_name, phone, email, gstin, type, address, village, taluka, district, state, pincode, lat, lng, status)
SELECT u.id, (SELECT id FROM users WHERE email='ravi@buddyfizz-partner.com'), 'Priya Supermart', 'Priya Shet', '+919820044444', 'priya@buddyfizz-retail.com', NULL, 'supermarket', '18th June Rd', 'Panaji', 'Tiswadi', 'North Goa', 'Goa', '403001', 15.4909000, 73.8278000, 'active'
FROM users u WHERE u.email='priya@buddyfizz-retail.com' AND NOT EXISTS (SELECT 1 FROM stores s WHERE s.user_id=u.id);
INSERT INTO stores (user_id, partner_id, name, owner_name, phone, email, gstin, type, address, village, taluka, district, state, pincode, lat, lng, status)
SELECT u.id, (SELECT id FROM users WHERE email='suresh@buddyfizz-partner.com'), 'Vijay Cold Drinks', 'Vijay Gaonkar', '+919820055555', 'vijay@buddyfizz-retail.com', '30ABCDV2222D1Z2', 'retail', 'Fatorda', 'Margao', 'Salcete', 'South Goa', 'Goa', '403602', 15.2700000, 73.9580000, 'active'
FROM users u WHERE u.email='vijay@buddyfizz-retail.com' AND NOT EXISTS (SELECT 1 FROM stores s WHERE s.user_id=u.id);
INSERT INTO stores (user_id, partner_id, name, owner_name, phone, email, gstin, type, address, village, taluka, district, state, pincode, lat, lng, status)
SELECT u.id, (SELECT id FROM users WHERE email='suresh@buddyfizz-partner.com'), 'Deepak Beverages', 'Deepak Naik', '+919820066666', 'deepak@buddyfizz-retail.com', NULL, 'retail', 'Tisk', 'Ponda', 'Ponda', 'South Goa', 'Goa', '403401', 15.4027000, 74.0078000, 'active'
FROM users u WHERE u.email='deepak@buddyfizz-retail.com' AND NOT EXISTS (SELECT 1 FROM stores s WHERE s.user_id=u.id);
INSERT INTO stores (user_id, partner_id, name, owner_name, phone, email, gstin, type, address, village, taluka, district, state, pincode, lat, lng, status)
SELECT u.id, NULL, 'Meena Stores', 'Meena Rao', '+919820077777', 'meena@buddyfizz-retail.com', '30ABCDM3333E1Z3', 'retail', 'Chicalim', 'Vasco', 'Mormugao', 'South Goa', 'Goa', '403802', 15.3860000, 73.8157000, 'active'
FROM users u WHERE u.email='meena@buddyfizz-retail.com' AND NOT EXISTS (SELECT 1 FROM stores s WHERE s.user_id=u.id);

INSERT INTO partner_territories (partner_user_id, level, value)
SELECT u.id, x.level, x.value FROM users u
JOIN (SELECT 'ravi@buddyfizz-partner.com' AS email, 'district' AS level, 'North Goa' AS value
      UNION ALL SELECT 'ravi@buddyfizz-partner.com', 'taluka', 'Bardez'
      UNION ALL SELECT 'suresh@buddyfizz-partner.com', 'district', 'South Goa'
      UNION ALL SELECT 'suresh@buddyfizz-partner.com', 'taluka', 'Salcete') x ON x.email=u.email
WHERE NOT EXISTS (SELECT 1 FROM partner_territories t WHERE t.partner_user_id=u.id AND t.level=x.level AND t.value=x.value);

INSERT IGNORE INTO orders (invoice_no, buyer_store_id, buyer_user_id, supplier_type, supplier_id, status, subtotal, gst_rate, gst_amount, cess_rate, cess_amount, total, amount_paid, payment_status, payment_method, created_at) VALUES
('BF/2026-27/000001', (SELECT id FROM stores WHERE name='Anand Kirana Store'), (SELECT id FROM users WHERE email='anand@buddyfizz-retail.com'),  'partner', (SELECT id FROM users WHERE email='ravi@buddyfizz-partner.com'),    'delivered',  6000.00, 28.00, 1680.00, 12.00,  720.00,  8400.00, 8400.00, 'paid',    'manual', '2026-06-10 10:15:00'),
('BF/2026-27/000002', (SELECT id FROM stores WHERE name='Anand Kirana Store'), (SELECT id FROM users WHERE email='anand@buddyfizz-retail.com'),  'partner', (SELECT id FROM users WHERE email='ravi@buddyfizz-partner.com'),    'delivered',  3000.00, 28.00,  840.00, 12.00,  360.00,  4200.00, 2000.00, 'partial', 'manual', '2026-06-20 12:40:00'),
('BF/2026-27/000003', (SELECT id FROM stores WHERE name='Priya Supermart'),    (SELECT id FROM users WHERE email='priya@buddyfizz-retail.com'),  'partner', (SELECT id FROM users WHERE email='ravi@buddyfizz-partner.com'),    'dispatched',10000.00, 28.00, 2800.00, 12.00, 1200.00, 14000.00,    0.00, 'unpaid',  'manual', '2026-06-25 09:05:00'),
('BF/2026-27/000004', (SELECT id FROM stores WHERE name='Priya Supermart'),    (SELECT id FROM users WHERE email='priya@buddyfizz-retail.com'),  'partner', (SELECT id FROM users WHERE email='ravi@buddyfizz-partner.com'),    'delivered',  6000.00, 28.00, 1680.00, 12.00,  720.00,  8400.00, 8400.00, 'paid',    'manual', '2026-06-28 16:20:00'),
('BF/2026-27/000005', (SELECT id FROM stores WHERE name='Vijay Cold Drinks'),  (SELECT id FROM users WHERE email='vijay@buddyfizz-retail.com'),  'partner', (SELECT id FROM users WHERE email='suresh@buddyfizz-partner.com'),  'delivered',  5000.00, 28.00, 1400.00, 12.00,  600.00,  7000.00, 7000.00, 'paid',    'manual', '2026-07-01 11:00:00'),
('BF/2026-27/000006', (SELECT id FROM stores WHERE name='Vijay Cold Drinks'),  (SELECT id FROM users WHERE email='vijay@buddyfizz-retail.com'),  'partner', (SELECT id FROM users WHERE email='suresh@buddyfizz-partner.com'),  'processing', 2400.00, 28.00,  672.00, 12.00,  288.00,  3360.00,    0.00, 'unpaid',  'manual', '2026-07-02 14:30:00'),
('BF/2026-27/000007', (SELECT id FROM stores WHERE name='Deepak Beverages'),   (SELECT id FROM users WHERE email='deepak@buddyfizz-retail.com'), 'partner', (SELECT id FROM users WHERE email='suresh@buddyfizz-partner.com'),  'delivered',  8000.00, 28.00, 2240.00, 12.00,  960.00, 11200.00, 5000.00, 'partial', 'manual', '2026-07-03 10:10:00'),
('BF/2026-27/000008', (SELECT id FROM stores WHERE name='Meena Stores'),       (SELECT id FROM users WHERE email='meena@buddyfizz-retail.com'),  'company', NULL,                                                              'delivered',  4800.00, 28.00, 1344.00, 12.00,  576.00,  6720.00, 6720.00, 'paid',    'manual', '2026-07-04 13:25:00'),
('BF/2026-27/000009', (SELECT id FROM stores WHERE name='Meena Stores'),       (SELECT id FROM users WHERE email='meena@buddyfizz-retail.com'),  'company', NULL,                                                              'pending',    1800.00, 28.00,  504.00, 12.00,  216.00,  2520.00,    0.00, 'unpaid',  'manual', '2026-07-05 08:45:00');

INSERT INTO order_items (order_id, product_id, qty, unit_price, line_total)
SELECT o.id, p.id, x.qty, x.up, x.lt FROM
(SELECT 'BF/2026-27/000001' inv, 'BF-LEM-250' sku, 200 qty, 20.00 up, 4000.00 lt
 UNION ALL SELECT 'BF/2026-27/000001','BF-ORA-250',100,20.00,2000.00
 UNION ALL SELECT 'BF/2026-27/000002','BF-GRA-250',150,20.00,3000.00
 UNION ALL SELECT 'BF/2026-27/000003','BF-LEM-250',500,20.00,10000.00
 UNION ALL SELECT 'BF/2026-27/000004','BF-MAS-250',300,20.00,6000.00
 UNION ALL SELECT 'BF/2026-27/000005','BF-ORA-250',250,20.00,5000.00
 UNION ALL SELECT 'BF/2026-27/000006','BF-GIN-250',120,20.00,2400.00
 UNION ALL SELECT 'BF/2026-27/000007','BF-GRA-250',400,20.00,8000.00
 UNION ALL SELECT 'BF/2026-27/000008','BF-MAS-250',180,20.00,3600.00
 UNION ALL SELECT 'BF/2026-27/000008','BF-LEM-250',60,20.00,1200.00
 UNION ALL SELECT 'BF/2026-27/000009','BF-ORA-250',90,20.00,1800.00) x
JOIN orders o ON o.invoice_no=x.inv
JOIN products p ON p.sku=x.sku
WHERE NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id=o.id AND oi.product_id=p.id);

UPDATE settings SET svalue='9' WHERE skey='invoice_seq' AND CAST(svalue AS UNSIGNED) < 9;

INSERT IGNORE INTO company_stock (product_id, quantity) SELECT id, 100000 FROM products;

INSERT IGNORE INTO store_stock (store_id, product_id, quantity)
SELECT s.id, p.id, 2000 FROM stores s CROSS JOIN products p
WHERE s.name IN ('North Goa Distributors', 'South Goa Beverages');

INSERT IGNORE INTO store_stock (store_id, product_id, quantity)
SELECT s.id, p.id, x.qty FROM
(SELECT 'Anand Kirana Store' sn, 'BF-LEM-250' sku, 120 qty
 UNION ALL SELECT 'Anand Kirana Store','BF-ORA-250',60
 UNION ALL SELECT 'Priya Supermart','BF-LEM-250',400
 UNION ALL SELECT 'Priya Supermart','BF-MAS-250',150
 UNION ALL SELECT 'Vijay Cold Drinks','BF-ORA-250',200
 UNION ALL SELECT 'Deepak Beverages','BF-GRA-250',300
 UNION ALL SELECT 'Meena Stores','BF-MAS-250',100) x
JOIN stores s ON s.name=x.sn JOIN products p ON p.sku=x.sku;

INSERT IGNORE INTO subscribers (email) VALUES
('fan1@example.com'), ('fan2@example.com'), ('fan3@example.com');

INSERT IGNORE INTO coupons (code, description, type, value, min_order, max_discount, is_active) VALUES
('WELCOME10', '10% off your order (up to Rs 500)', 'percent', 10.00, 500.00, 500.00, 1),
('FLAT100', 'Flat Rs 100 off orders above Rs 2000', 'flat', 100.00, 2000.00, NULL, 1);

INSERT INTO announcements (title, body, audience, is_active)
SELECT x.title, x.body, x.audience, 1 FROM
(SELECT 'Monsoon Scheme Live!' title, 'Order 500+ bottles this month and get 5% extra stock free.' body, 'retailer' audience
 UNION ALL SELECT 'New Flavour Coming Soon', 'Buddy Fizz Jamun launches next quarter — stay tuned!', 'all') x
WHERE NOT EXISTS (SELECT 1 FROM announcements a WHERE a.title=x.title);

INSERT INTO tickets (store_id, user_id, subject, category, priority, status)
SELECT s.id, u.id, 'Short delivery on last order', 'orders', 'high', 'open'
FROM stores s JOIN users u ON u.email='vijay@buddyfizz-retail.com'
WHERE s.name='Vijay Cold Drinks' AND NOT EXISTS (SELECT 1 FROM tickets t WHERE t.subject='Short delivery on last order');

INSERT INTO ticket_messages (ticket_id, user_id, message)
SELECT t.id, u.id, 'I received 240 bottles but the invoice says 250. Please check.'
FROM tickets t JOIN users u ON u.email='vijay@buddyfizz-retail.com'
WHERE t.subject='Short delivery on last order'
AND NOT EXISTS (SELECT 1 FROM ticket_messages m WHERE m.ticket_id=t.id);

INSERT INTO notifications (user_id, type, title, body, link)
SELECT u.id, 'welcome', 'Welcome to Buddy Fizz!', x.body, x.link FROM users u
JOIN (SELECT 'ravi@buddyfizz-partner.com' email, 'Your partner account is active.' body, 'partner/' link
      UNION ALL SELECT 'anand@buddyfizz-retail.com', 'Your retailer account is active.', 'retailer/'
      UNION ALL SELECT 'meena@buddyfizz-retail.com', 'Your retailer account is active.', 'retailer/') x ON x.email=u.email
WHERE NOT EXISTS (SELECT 1 FROM notifications n WHERE n.user_id=u.id AND n.type='welcome');

INSERT INTO applications (type, business_name, contact_name, email, phone, password, store_type, address, village, taluka, district, state, pincode, lat, lng, message, ip)
SELECT 'retailer', 'Coastal Mini Mart', 'Rohan Pai', 'rohan@applicant.com', '+919820088888', '$2y$12$kiMoC/SMlZiXi/wVvIQ3Dum.UICAlx4fpGK6Fav6ksG/jAsYZxa8e', 'retail', 'Calangute Beach Rd', 'Calangute', 'Bardez', 'North Goa', 'Goa', '403516', 15.5439000, 73.7553000, 'Keen to stock Buddy Fizz at my beachside store.', '203.0.113.10'
WHERE NOT EXISTS (SELECT 1 FROM applications WHERE email='rohan@applicant.com');

-- On cPanel the database is created in the "MySQL Databases" UI (name: vofgmclr_buddyfizz),
-- and cPanel DB users are not allowed to run CREATE DATABASE, so that line is disabled here.
-- In phpMyAdmin, select vofgmclr_buddyfizz first, then import this file.
-- CREATE DATABASE IF NOT EXISTS vofgmclr_buddyfizz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vofgmclr_buddyfizz;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(20) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','partner','retailer') NOT NULL DEFAULT 'retailer',
  status ENUM('active','suspended','terminated') NOT NULL DEFAULT 'active',
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (role)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('retailer','partner') NOT NULL DEFAULT 'retailer',
  business_name VARCHAR(160) NOT NULL,
  contact_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  password VARCHAR(255) NOT NULL,
  store_type ENUM('retail','supermarket','distributor','online') NOT NULL DEFAULT 'retail',
  address VARCHAR(255) DEFAULT NULL,
  village VARCHAR(120) DEFAULT NULL,
  taluka VARCHAR(120) DEFAULT NULL,
  district VARCHAR(120) DEFAULT NULL,
  state VARCHAR(120) DEFAULT NULL,
  pincode VARCHAR(12) DEFAULT NULL,
  lat DECIMAL(10,7) DEFAULT NULL,
  lng DECIMAL(10,7) DEFAULT NULL,
  message TEXT DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  review_notes VARCHAR(255) DEFAULT NULL,
  reviewed_by INT DEFAULT NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (status),
  INDEX (type),
  INDEX (ip, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  application_id INT DEFAULT NULL,
  partner_id INT DEFAULT NULL,
  name VARCHAR(160) NOT NULL,
  owner_name VARCHAR(120) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  email VARCHAR(160) DEFAULT NULL,
  gstin VARCHAR(20) DEFAULT NULL,
  type ENUM('retail','supermarket','distributor','online') NOT NULL DEFAULT 'retail',
  address VARCHAR(255) DEFAULT NULL,
  village VARCHAR(120) DEFAULT NULL,
  taluka VARCHAR(120) DEFAULT NULL,
  district VARCHAR(120) DEFAULT NULL,
  state VARCHAR(120) DEFAULT NULL,
  pincode VARCHAR(12) DEFAULT NULL,
  lat DECIMAL(10,7) DEFAULT NULL,
  lng DECIMAL(10,7) DEFAULT NULL,
  wallet_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  credit_allowed TINYINT(1) NOT NULL DEFAULT 0,
  credit_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_stores_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_stores_partner FOREIGN KEY (partner_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX (status), INDEX (state), INDEX (district), INDEX (taluka), INDEX (pincode)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS partner_territories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  partner_user_id INT NOT NULL,
  level ENUM('state','district','taluka') NOT NULL DEFAULT 'district',
  value VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_terr_partner FOREIGN KEY (partner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (level, value)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wallet_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  store_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  balance_after DECIMAL(12,2) NOT NULL DEFAULT 0,
  type ENUM('topup','order','refund','adjustment') NOT NULL DEFAULT 'topup',
  order_id INT DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  INDEX (store_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wallet_topups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  store_id INT NOT NULL,
  razorpay_order_id VARCHAR(40) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_topup_rzp (razorpay_order_id),
  INDEX (store_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS due_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  store_id INT NOT NULL,
  razorpay_order_id VARCHAR(40) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('created','paid') NOT NULL DEFAULT 'created',
  razorpay_payment_id VARCHAR(40) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_due_rzp (razorpay_order_id),
  INDEX (store_id),
  CONSTRAINT fk_due_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  description VARCHAR(180) DEFAULT NULL,
  type ENUM('percent','flat') NOT NULL DEFAULT 'percent',
  value DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_order DECIMAL(12,2) NOT NULL DEFAULT 0,
  max_discount DECIMAL(12,2) DEFAULT NULL,
  usage_limit INT DEFAULT NULL,
  used_count INT NOT NULL DEFAULT 0,
  expires_at DATE DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS termination_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  store_id INT NOT NULL,
  retailer_user_id INT NOT NULL,
  requested_by INT NOT NULL,
  reason VARCHAR(500) DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  review_notes VARCHAR(500) DEFAULT NULL,
  reviewed_by INT DEFAULT NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (status),
  INDEX (requested_by),
  CONSTRAINT fk_tr_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_tr_retailer FOREIGN KEY (retailer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tr_partner FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS partner_settlements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  partner_user_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) DEFAULT NULL,
  settled_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_settle_partner FOREIGN KEY (partner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (partner_user_id), INDEX (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  flavour VARCHAR(80) DEFAULT NULL,
  sku VARCHAR(60) NOT NULL UNIQUE,
  size VARCHAR(40) DEFAULT NULL,
  cost DECIMAL(10,2) NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  partner_profit DECIMAL(10,2) NOT NULL DEFAULT 0,
  mrp DECIMAL(10,2) DEFAULT NULL,
  image VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(40) DEFAULT NULL,
  buyer_store_id INT NOT NULL,
  buyer_user_id INT DEFAULT NULL,
  supplier_type ENUM('company','partner') NOT NULL DEFAULT 'company',
  supplier_id INT DEFAULT NULL,
  status ENUM('pending','approved','rejected','processing','dispatched','delivered','cancelled') NOT NULL DEFAULT 'pending',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  gst_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  cess_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  cess_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  coupon_code VARCHAR(40) DEFAULT NULL,
  delivery_date DATE DEFAULT NULL,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  payment_method ENUM('manual','razorpay','wallet','credit') NOT NULL DEFAULT 'manual',
  razorpay_order_id VARCHAR(40) DEFAULT NULL,
  razorpay_payment_id VARCHAR(40) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_store FOREIGN KEY (buyer_store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_orders_supplier FOREIGN KEY (supplier_id) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_invoice (invoice_no),
  INDEX (status), INDEX (created_at), INDEX (supplier_type, supplier_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS store_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  store_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_store_product (store_id, product_id),
  CONSTRAINT fk_stock_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS company_stock (
  product_id INT NOT NULL PRIMARY KEY,
  quantity INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cstock_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  store_id INT DEFAULT NULL,
  product_id INT NOT NULL,
  change_qty INT NOT NULL,
  reason ENUM('order','manual','adjustment','return','production') NOT NULL DEFAULT 'manual',
  order_id INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mov_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  INDEX (store_id, product_id), INDEX (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  store_id INT DEFAULT NULL,
  user_id INT NOT NULL,
  subject VARCHAR(180) NOT NULL,
  category VARCHAR(60) DEFAULT 'general',
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  status ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
  assigned_to INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  attachment VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tmsg_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_tmsg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(60) DEFAULT 'info',
  title VARCHAR(180) NOT NULL,
  body VARCHAR(255) DEFAULT NULL,
  link VARCHAR(180) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subscribers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  message TEXT DEFAULT NULL,
  status ENUM('new','contacted','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  body TEXT DEFAULT NULL,
  audience ENUM('all','retailer','partner') NOT NULL DEFAULT 'all',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  starts_at DATE DEFAULT NULL,
  ends_at DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  skey VARCHAR(80) NOT NULL UNIQUE,
  svalue TEXT DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(60) DEFAULT NULL,
  entity_id INT DEFAULT NULL,
  meta JSON DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id), INDEX (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (email, created_at), INDEX (ip, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pending_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  razorpay_order_id VARCHAR(40) NOT NULL,
  buyer_store_id INT NOT NULL,
  supplier_type ENUM('company','partner') NOT NULL DEFAULT 'company',
  supplier_id INT DEFAULT NULL,
  cart JSON NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  coupon_code VARCHAR(40) DEFAULT NULL,
  delivery_date DATE DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pending_rzp (razorpay_order_id),
  INDEX (user_id), INDEX (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS email_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  context VARCHAR(60) DEFAULT NULL,
  status ENUM('sent','failed','skipped') NOT NULL DEFAULT 'sent',
  error VARCHAR(255) DEFAULT NULL,
  sent_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (created_at), INDEX (context), INDEX (status)
) ENGINE=InnoDB;


INSERT INTO products (name, flavour, sku, size, cost, price, partner_profit, mrp, image) VALUES
('Lemon Lime Soda', 'Lemon', 'BF-LEM-250', '250 ml', 11.00, 20.00, 3.00, 25.00, 'frames/img/lemon.webp'),
('Ginger Lemon Soda', 'Ginger Lemon', 'BF-GIN-250', '250 ml', 12.00, 20.00, 3.00, 25.00, 'frames/img/ginger.webp'),
('Orange Soda', 'Orange', 'BF-ORA-250', '250 ml', 10.50, 20.00, 3.00, 25.00, 'frames/img/orange.webp'),
('Masala Soda', 'Masala', 'BF-MAS-250', '250 ml', 11.50, 20.00, 3.00, 25.00, 'frames/img/masala.webp'),
('Grape Soda', 'Grape', 'BF-GRA-250', '250 ml', 10.00, 20.00, 3.00, 25.00, 'frames/img/grape.webp')
ON DUPLICATE KEY UPDATE sku = sku;


INSERT INTO settings (skey, svalue) VALUES
('company_name', 'Buddy Fizz'),
('company_address', 'Khursa Vaddo, P.O. Piedade, Divar, Mallar, North Goa, Goa - 403403'),
('company_phone', '+91 77740 67890'),
('company_email', 'buddyfizzcare@gmail.com'),
('company_gstin', ''),
('company_state', 'Goa'),
('gst_percent', '28'),
('cess_percent', '12'),
('invoice_prefix', 'BF'),
('invoice_seq', '0'),
('order_min_qty', '1'),
('low_stock_threshold', '500')
ON DUPLICATE KEY UPDATE skey = skey;


-- Admin accounts (bcrypt cost 12). Idempotent: re-running will not overwrite existing accounts.
INSERT INTO users (name, email, phone, password, role, status) VALUES
('Piyush Dukare', 'piyushdukare05@outlook.com', NULL, '$2y$12$5SMZVTQAWa8jwSHnuU7hweVND5YgbItAG5aOZwpjKd3XJckwhQ8k6', 'admin', 'active'),
('Shivam Pilernekar', 'shivampilernekar216@gmail.com', NULL, '$2y$12$Va6FNjPNOLgId2sst0mpBuj4FojfthUguKk/91iJMnt2ne8uvPi5e', 'admin', 'active')
ON DUPLICATE KEY UPDATE email = email;

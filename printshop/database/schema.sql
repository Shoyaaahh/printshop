PRAGMA foreign_keys = ON;

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('customer','admin')),
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','suspended')),
  created_at TEXT NOT NULL,
  last_login_at TEXT
);

CREATE TABLE customer_profiles (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  full_name TEXT NOT NULL,
  phone TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE admin_users (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  full_name TEXT NOT NULL,
  admin_role TEXT NOT NULL DEFAULT 'admin'
);

CREATE TABLE service_categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  slug TEXT NOT NULL UNIQUE,
  description TEXT,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE services (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  category_id INTEGER NOT NULL REFERENCES service_categories(id),
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  description TEXT NOT NULL DEFAULT '',
  image TEXT,
  pricing_mode TEXT NOT NULL CHECK (pricing_mode IN ('per_page','flat_page','per_unit','per_sqft','quote')),
  rate_group TEXT,                       -- 'print' or 'copy' for per_page mode (links to pricing_rules)
  base_price REAL NOT NULL DEFAULT 0 CHECK (base_price >= 0),
  price_unit TEXT NOT NULL DEFAULT 'unit',
  processing_days INTEGER NOT NULL DEFAULT 1 CHECK (processing_days >= 0),
  options TEXT NOT NULL DEFAULT '{}',    -- JSON: fields, sizes, papers, colors, sides, bindings
  needs_upload INTEGER NOT NULL DEFAULT 1,
  needs_review INTEGER NOT NULL DEFAULT 0,
  is_popular INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_services_category ON services(category_id);

CREATE TABLE pricing_rules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  rule_key TEXT NOT NULL UNIQUE,
  label TEXT NOT NULL,
  group_name TEXT NOT NULL,
  amount REAL NOT NULL CHECK (amount >= 0),
  updated_at TEXT NOT NULL
);

CREATE TABLE orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  reference TEXT NOT NULL UNIQUE,
  customer_id INTEGER NOT NULL REFERENCES users(id),
  status TEXT NOT NULL DEFAULT 'order_received' CHECK (status IN (
    'order_received','awaiting_review','awaiting_customer_confirmation','pending_payment',
    'payment_verified','processing','printing','quality_checking','ready_for_pickup','completed','cancelled')),
  payment_status TEXT NOT NULL DEFAULT 'unpaid' CHECK (payment_status IN
    ('unpaid','pending_verification','paid','rejected','refunded')),
  payment_method TEXT,
  estimated_total REAL NOT NULL CHECK (estimated_total >= 0),
  final_total REAL CHECK (final_total IS NULL OR final_total >= 0),
  price_adjusted INTEGER NOT NULL DEFAULT 0,
  customer_confirmed INTEGER NOT NULL DEFAULT 1,
  estimated_completion TEXT,
  special_instructions TEXT,
  internal_notes TEXT,
  cancelled_reason TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  completed_at TEXT
);
CREATE INDEX idx_orders_customer ON orders(customer_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created ON orders(created_at);

CREATE TABLE order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  service_id INTEGER REFERENCES services(id),
  service_name TEXT NOT NULL,
  paper_size TEXT, paper_type TEXT, color TEXT, sides TEXT, orientation TEXT, binding TEXT,
  pages INTEGER NOT NULL DEFAULT 1 CHECK (pages > 0),
  copies INTEGER NOT NULL DEFAULT 1 CHECK (copies > 0),
  width_ft REAL, height_ft REAL,
  unit_price REAL NOT NULL DEFAULT 0,
  line_total REAL NOT NULL DEFAULT 0,
  breakdown TEXT,                        -- JSON price breakdown at time of order
  notes TEXT
);
CREATE INDEX idx_items_order ON order_items(order_id);
CREATE INDEX idx_items_service ON order_items(service_id);

CREATE TABLE uploaded_files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  user_id INTEGER NOT NULL REFERENCES users(id),
  original_name TEXT NOT NULL,
  stored_name TEXT NOT NULL UNIQUE,
  mime_type TEXT NOT NULL,
  size_bytes INTEGER NOT NULL,
  page_count INTEGER,
  uploaded_at TEXT NOT NULL
);
CREATE INDEX idx_files_order ON uploaded_files(order_id);

CREATE TABLE payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  method TEXT NOT NULL CHECK (method IN ('gcash','maya','bank','cash')),
  amount REAL NOT NULL CHECK (amount >= 0),
  reference_no TEXT,
  status TEXT NOT NULL DEFAULT 'pending_verification' CHECK (status IN
    ('pending_verification','paid','rejected','refunded')),
  receipt_no TEXT UNIQUE,
  rejection_reason TEXT,
  submitted_by INTEGER REFERENCES users(id),
  verified_by INTEGER REFERENCES users(id),
  submitted_at TEXT NOT NULL,
  verified_at TEXT
);
CREATE INDEX idx_payments_order ON payments(order_id);
CREATE INDEX idx_payments_status ON payments(status);

CREATE TABLE payment_receipts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  payment_id INTEGER NOT NULL REFERENCES payments(id) ON DELETE CASCADE,
  original_name TEXT NOT NULL,
  stored_name TEXT NOT NULL UNIQUE,
  mime_type TEXT NOT NULL,
  size_bytes INTEGER NOT NULL,
  uploaded_at TEXT NOT NULL
);

CREATE TABLE order_status_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  status TEXT NOT NULL,
  note TEXT,
  changed_by INTEGER REFERENCES users(id),
  created_at TEXT NOT NULL
);
CREATE INDEX idx_history_order ON order_status_history(order_id);

CREATE TABLE pickup_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL UNIQUE REFERENCES orders(id) ON DELETE CASCADE,
  picked_up_by TEXT NOT NULL,
  confirmed_by INTEGER NOT NULL REFERENCES users(id),
  picked_up_at TEXT NOT NULL,
  notes TEXT
);

CREATE TABLE notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  link TEXT,
  is_read INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_notif_user ON notifications(user_id, is_read);

CREATE TABLE business_settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT NOT NULL DEFAULT ''
);

CREATE TABLE audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  action TEXT NOT NULL,
  entity TEXT,
  entity_id TEXT,
  detail TEXT,
  ip_address TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_audit_created ON audit_logs(created_at);

-- Supporting tables (not in the original list, but needed by features above)
CREATE TABLE contact_messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL, email TEXT NOT NULL, subject TEXT NOT NULL, message TEXT NOT NULL,
  is_read INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);

CREATE TABLE email_queue (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  to_email TEXT NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','sent','failed')),
  error TEXT,
  created_at TEXT NOT NULL,
  sent_at TEXT
);

CREATE TABLE rate_limits (
  bucket TEXT PRIMARY KEY,
  hits INTEGER NOT NULL,
  window_start INTEGER NOT NULL
);


PRAGMA foreign_keys = ON;

-- 🧍 Kullanıcılar
CREATE TABLE IF NOT EXISTS "User" (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('user','admin','firma_admin')),
    balance INTEGER NOT NULL DEFAULT 5000,
    company_id INTEGER,                               -- ✅ Firma bağlantısı
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (company_id) REFERENCES "Bus_Company"(id) ON DELETE SET NULL
);

-- 🚌 Firmalar
CREATE TABLE IF NOT EXISTS "Bus_Company" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  logo_path TEXT,
  created_at TEXT DEFAULT (datetime('now'))
);

-- 🚍 Seferler
CREATE TABLE IF NOT EXISTS "Trips" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id INTEGER NOT NULL,
  destination_city TEXT NOT NULL,
  arrival_time TEXT NOT NULL,      -- ISO DATETIME
  departure_time TEXT NOT NULL,    -- ISO DATETIME
  departure_city TEXT NOT NULL,
  price INTEGER NOT NULL,          -- kuruş
  capacity INTEGER NOT NULL,
  created_date TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (company_id) REFERENCES "Bus_Company"(id) ON DELETE CASCADE
);

-- 🎟️ Biletler
CREATE TABLE IF NOT EXISTS "Tickets" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  trip_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','canceled','expired')),
  total_price INTEGER NOT NULL,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (trip_id) REFERENCES "Trips"(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES "User"(id) ON DELETE CASCADE
);

-- 💺 Koltuklar
CREATE TABLE IF NOT EXISTS "Booked_Seats" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_id INTEGER NOT NULL,
  seat_number INTEGER NOT NULL,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (ticket_id) REFERENCES "Tickets"(id) ON DELETE CASCADE
);

-- 🧾 Kuponlar
CREATE TABLE IF NOT EXISTS "Coupons" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  discount REAL NOT NULL,
  usage_limit INTEGER NOT NULL,
  expire_date TEXT NOT NULL,      -- ISO DATETIME
  created_at TEXT DEFAULT (datetime('now'))
);

-- 🎁 Kullanıcı-Kupon
CREATE TABLE IF NOT EXISTS "User_Coupons" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  coupon_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (coupon_id) REFERENCES "Coupons"(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES "User"(id) ON DELETE CASCADE,
  UNIQUE (coupon_id, user_id)
);

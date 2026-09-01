PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT
);

CREATE TABLE IF NOT EXISTS categories (
    slug TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category TEXT NOT NULL,
    name TEXT NOT NULL,
    image TEXT DEFAULT '',
    is_popular INTEGER DEFAULT 0,
    featured INTEGER DEFAULT 0,
    description TEXT DEFAULT '',
    price TEXT DEFAULT '',
    is_sale INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS service_categories (
    slug TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    short_desc TEXT DEFAULT '',
    keywords TEXT DEFAULT '',
    sort_order INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT DEFAULT '',
    icon TEXT DEFAULT '',
    sort_order INTEGER DEFAULT 0,
    subtitle TEXT DEFAULT '',
    image TEXT DEFAULT '',
    slug TEXT DEFAULT '',
    category_slug TEXT DEFAULT '',
    content TEXT DEFAULT '',
    keywords TEXT DEFAULT '',
    is_active INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS advantages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    icon TEXT DEFAULT '',
    title TEXT NOT NULL,
    subtitle TEXT DEFAULT '',
    image TEXT DEFAULT '',
    sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    number TEXT NOT NULL,
    label TEXT DEFAULT '',
    sort_order INTEGER DEFAULT 0
);

INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES
    ('hero_h1', 'Fasteners and metalware wholesale'),
    ('hero_sub', 'Demo catalog for local development'),
    ('about_h1', 'About the company'),
    ('about_sub', 'Portfolio-safe sample content'),
    ('services_h1', 'Services'),
    ('services_sub', 'Manufacturing and metalworking'),
    ('contacts_h1', 'Contacts'),
    ('contacts_sub', 'Use demo contact details locally'),
    ('phone1', '+7 (000) 000-00-00'),
    ('email1', 'demo@example.com'),
    ('address', 'Demo address'),
    ('hours1', 'Mon–Fri, 10:00–18:00'),
    ('seo_site_name', 'Lenspecopt'),
    ('seo_description', 'Portfolio-safe local demo of the Lenspecopt website'),
    ('seo_city', 'Saint Petersburg');

INSERT OR IGNORE INTO categories (slug, name, sort_order) VALUES
    ('sample-fasteners', 'Sample fasteners', 10);

INSERT OR IGNORE INTO products
    (id, category, name, image, is_popular, featured, description, price, is_sale)
VALUES
    (1, 'sample-fasteners', 'Sample product', 'images/placeholder.jpg', 1, 1, 'Demo catalog item', 'On request', 0);

INSERT OR IGNORE INTO service_categories
    (slug, name, short_desc, keywords, sort_order, is_active)
VALUES
    ('metalworking', 'Metalworking', 'Demo service category', 'metalworking', 10, 1);

INSERT OR IGNORE INTO services
    (id, title, description, icon, sort_order, subtitle, image, slug, category_slug, content, keywords, is_active)
VALUES
    (1, 'Custom manufacturing', 'Demo service for local development', '⚙️', 10, 'Made to specification', 'images/placeholder.jpg', 'custom-manufacturing', 'metalworking', 'Portfolio-safe sample content', 'manufacturing', 1);

INSERT OR IGNORE INTO advantages (id, icon, title, subtitle, image, sort_order) VALUES
    (1, '✓', 'Reliable supply', 'Sample advantage', '', 10);

INSERT OR IGNORE INTO stats (id, number, label, sort_order) VALUES
    (1, '2010', 'In business since', 10);


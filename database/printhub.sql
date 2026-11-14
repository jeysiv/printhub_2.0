-- =====================================================================
--  PRINTHUB DATABASE  (MySQL / MariaDB)
--  How to use: phpMyAdmin > Import > choose this file.
--  Tables are created PARENT FIRST, CHILD LAST (a child table that has a
--  FOREIGN KEY can only be created after the table it points to).
-- =====================================================================

DROP DATABASE IF EXISTS printhub;
CREATE DATABASE printhub CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE printhub;

-- ---------------------------------------------------------------------
-- 1. USERS  (customers and the admin)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id    INT          NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL,
    password   VARCHAR(255) NOT NULL,                  -- stores a HASH, never the real password
    role       ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email)                   -- no two accounts with the same email
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. CATEGORIES  (split out of services so category names are stored ONCE -> 3NF)
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    category_id   INT         NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(50) NOT NULL,
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_category_name (category_name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. SERVICES  (what the shop sells)       categories 1 --- M services
-- ---------------------------------------------------------------------
CREATE TABLE services (
    service_id   INT           NOT NULL AUTO_INCREMENT,
    category_id  INT           NOT NULL,
    service_name VARCHAR(100)  NOT NULL,
    description  TEXT          DEFAULT NULL,
    price        DECIMAL(10,2) NOT NULL,
    unit         VARCHAR(20)   NOT NULL DEFAULT 'per piece',   -- e.g. 'per page'
    status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (service_id),
    CONSTRAINT fk_services_category FOREIGN KEY (category_id)
        REFERENCES categories (category_id),
    CONSTRAINT chk_services_price CHECK (price >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. PROMOTIONS  (discounts shown on the front page, usable on orders)
-- ---------------------------------------------------------------------
CREATE TABLE promotions (
    promotion_id INT          NOT NULL AUTO_INCREMENT,
    title        VARCHAR(100) NOT NULL,
    description  TEXT         DEFAULT NULL,
    discount     DECIMAL(5,2) NOT NULL DEFAULT 0.00,     -- percent, e.g. 10.00 = 10%
    start_date   DATE         NOT NULL,
    end_date     DATE         NOT NULL,
    status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (promotion_id),
    CONSTRAINT chk_promo_discount CHECK (discount BETWEEN 0 AND 100)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. ORDERS  (ONE order = one customer's request)
--    users 1 --- M orders        promotions 1 --- M orders (optional)
--    NOTE: there is no total_price column. A total can be calculated from the
--    order items, so storing it would be redundant (a 3NF problem).
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    order_id     INT  NOT NULL AUTO_INCREMENT,
    user_id      INT  NOT NULL,
    promotion_id INT  DEFAULT NULL,                      -- NULL = no promo used
    promotion_discount DECIMAL(5,2) NOT NULL DEFAULT 0.00, -- discount snapshot at order time
    notes        TEXT DEFAULT NULL,
    status       ENUM('pending','confirmed','printing','ready','completed','cancelled')
                 NOT NULL DEFAULT 'pending',
    order_date   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (order_id),
    CONSTRAINT fk_orders_user  FOREIGN KEY (user_id)      REFERENCES users (user_id),
    CONSTRAINT fk_orders_promo FOREIGN KEY (promotion_id) REFERENCES promotions (promotion_id)
        ON DELETE SET NULL                                 -- deleting a promo keeps the order
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. ORDER_ITEMS  (JUNCTION TABLE)
--    One order can contain MANY services, and one service can appear in MANY orders.
--    That is a many-to-many (M:N) relationship, so it is resolved with this table:
--        orders 1 --- M order_items M --- 1 services
-- ---------------------------------------------------------------------
CREATE TABLE order_items (
    order_item_id INT           NOT NULL AUTO_INCREMENT,
    order_id      INT           NOT NULL,
    service_id    INT           NOT NULL,
    file_name     VARCHAR(255)  DEFAULT NULL,            -- original name shown to people
    file_path     VARCHAR(255)  DEFAULT NULL,            -- where the file is saved on the server
    paper_size    VARCHAR(60)   NOT NULL DEFAULT 'A4',
    copies        INT           NOT NULL DEFAULT 1,
    unit_price    DECIMAL(10,2) NOT NULL,                -- price AT THE TIME of ordering (see README)
    PRIMARY KEY (order_item_id),
    CONSTRAINT fk_items_order   FOREIGN KEY (order_id)   REFERENCES orders (order_id)
        ON DELETE CASCADE,                               -- deleting an order deletes its items
    CONSTRAINT fk_items_service FOREIGN KEY (service_id) REFERENCES services (service_id),
    CONSTRAINT chk_items_copies CHECK (copies >= 1)
) ENGINE=InnoDB;

-- =====================================================================
--  SAMPLE DATA
--  Login passwords:  admin@printhub.com = admin123
--                    every customer      = customer123
-- =====================================================================
INSERT INTO users (name, email, password, role) VALUES
('PrintHub Admin', 'admin@printhub.com', '$2y$12$zHmj/bEPoGPNdF028FtqROnKft7bhEbTWG0YhMxpJwqcVEDyaH.d6', 'admin'),
('Ghianne Erfe',   'ghianne@test.com',   '$2y$12$rye6MdoztfeJobrNjwKQfOTY.sgsRfocL3smeUmD2Iiruuo1facT6', 'customer'),
('Elma Santos',    'elma@test.com',      '$2y$12$rye6MdoztfeJobrNjwKQfOTY.sgsRfocL3smeUmD2Iiruuo1facT6', 'customer'),
('Ianne Drx',      'ianne@test.com',     '$2y$12$rye6MdoztfeJobrNjwKQfOTY.sgsRfocL3smeUmD2Iiruuo1facT6', 'customer'),
('Test User',      'user@test.com',      '$2y$12$rye6MdoztfeJobrNjwKQfOTY.sgsRfocL3smeUmD2Iiruuo1facT6', 'customer');

INSERT INTO categories (category_name) VALUES
('Document Printing'),   -- 1
('Photo Printing'),      -- 2
('Large Format'),        -- 3
('Business Printing'),   -- 4
('Marketing'),           -- 5
('Custom Printing'),     -- 6
('School Printing');     -- 7

INSERT INTO services (category_id, service_name, description, price, unit) VALUES
(1, 'Black & White Printing',       'Affordable black and white printing for documents, assignments, and school requirements.', 3.00,   'per page'),
(1, 'Colored Printing',             'High-quality colored printing for projects, reports, and presentations.',                 8.00,   'per page'),
(2, 'Photo Printing',               'Print your favorite photos in different sizes and finishes.',                             15.00,  'per photo'),
(3, 'Tarpaulin Printing',           'Durable and vibrant tarpaulin printing for birthdays, events, and businesses.',           200.00, 'per piece'),
(4, 'Business Cards',               'Professional business cards for individuals and businesses.',                             100.00, 'per set'),
(5, 'Flyer Printing',               'Eye-catching flyers for events, promotions, and businesses.',                             5.00,   'per piece'),
(6, 'Sticker Printing',             'Custom stickers for personal, school, and business use.',                                 20.00,  'per sheet'),
(7, 'Thesis and Research Printing', 'Complete printing services for thesis, research papers, and academic documents.',          3.00,   'per page'),
(7, 'ID Card Printing',             'Professional ID card printing for students.',                                             50.00,  'per card');

INSERT INTO promotions (title, description, discount, start_date, end_date, status) VALUES
('Student Printing Special', 'Students get 10% off their order.',                                10.00, '2026-09-01', '2026-12-31', 'active'),
('Business Starter Package', 'Get 15% off when ordering business cards and flyers together.',    15.00, '2026-09-01', '2026-11-30', 'active'),
('Event Printing Sale',      'Save 20% on event printing such as tarpaulins and flyers.',         20.00, '2026-10-01', '2026-12-31', 'active');

INSERT INTO orders (user_id, promotion_id, promotion_discount, notes, status, order_date) VALUES
(2, NULL, 0,  'Please print double sided',              'pending',   '2026-09-25 09:05:06'),
(5, NULL, 0,  '',                                       'confirmed', '2026-10-06 12:50:43'),
(3, 2,    15, 'Business launch next week, rush please', 'printing',  '2026-11-05 10:20:00'),
(4, 1,    10, 'For our class report',                   'completed', '2026-11-08 14:00:00');

INSERT INTO order_items (order_id, service_id, file_name, file_path, paper_size, copies, unit_price) VALUES
(1, 2, 'Certificate.pdf',   NULL, 'A4', 1,  8.00),
(2, 4, 'udd logo.png',      NULL, 'A4', 1,  200.00),
(3, 5, 'business-card.pdf', NULL, 'A4', 2,  100.00),
(3, 6, 'launch-flyer.pdf',  NULL, 'A4', 50, 5.00),
(4, 8, 'class-report.pdf',  NULL, 'A4', 3,  3.00);

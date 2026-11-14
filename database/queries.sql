-- =====================================================================
--  PRINTHUB: DEMONSTRATION QUERIES  (run ONE at a time in phpMyAdmin > SQL)
--  They cover what the Database rubric asks for:
--  ALTER, INSERT, SELECT (JOIN / WHERE / GROUP BY), UPDATE, DELETE
-- =====================================================================
USE printhub;

-- 1. ALTER (DDL): add a phone column to users
ALTER TABLE users ADD phone VARCHAR(20) NULL;

-- 2. INSERT (DML): add a new service (category 5 = Marketing)
INSERT INTO services (category_id, service_name, description, price, unit)
VALUES (5, 'Poster Printing', 'Large posters for events and promotions.', 45.00, 'per piece');

-- 3. SELECT with JOIN: services together with their category name
SELECT s.service_id, s.service_name, c.category_name, s.price, s.unit
FROM services s
JOIN categories c ON c.category_id = s.category_id
ORDER BY c.category_name, s.service_name;

-- 4. SELECT with a 4-table JOIN: every order line with the customer and the service
SELECT o.order_id, u.name AS customer, s.service_name,
       oi.copies, oi.unit_price, (oi.copies * oi.unit_price) AS line_total
FROM orders o
JOIN users u        ON u.user_id    = o.user_id
JOIN order_items oi ON oi.order_id  = o.order_id
JOIN services s     ON s.service_id = oi.service_id
ORDER BY o.order_id;

-- 5. SELECT with GROUP BY: total of each order AFTER the promotion discount
SELECT o.order_id, u.name AS customer,
       SUM(oi.copies * oi.unit_price)                                   AS subtotal,
       o.promotion_discount                                             AS discount_percent,
       SUM(oi.copies * oi.unit_price) * (1 - o.promotion_discount/100)  AS total
FROM orders o
JOIN users u           ON u.user_id   = o.user_id
JOIN order_items oi    ON oi.order_id = o.order_id
LEFT JOIN promotions p ON p.promotion_id = o.promotion_id
GROUP BY o.order_id, u.name, o.promotion_discount;

-- 6. SELECT with WHERE: orders that are still open
SELECT order_id, user_id, status, order_date
FROM orders
WHERE status IN ('pending','confirmed','printing')
ORDER BY order_date DESC;

-- 7. SELECT with GROUP BY: best-selling services
SELECT s.service_name, SUM(oi.copies) AS total_copies
FROM order_items oi
JOIN services s ON s.service_id = oi.service_id
GROUP BY s.service_id, s.service_name
ORDER BY total_copies DESC;

-- 8. UPDATE (DML): the admin moves order 1 forward
UPDATE orders SET status = 'confirmed' WHERE order_id = 1;

-- 9. UPDATE (DML): raise the price of Flyer Printing
UPDATE services SET price = 6.00 WHERE service_name = 'Flyer Printing';
-- Old orders keep their own unit_price, which is why order_items stores it.

-- 10. DELETE (DML): remove the service added in step 2 (it was never ordered)
DELETE FROM services WHERE service_name = 'Poster Printing';

-- 11. DELETE with CASCADE: deleting an order also deletes its items
DELETE FROM orders WHERE order_id = 4;
SELECT * FROM order_items;   -- the item that belonged to order 4 is gone too

-- Run once on an existing PrintHub database before deploying the new Order code.
-- Existing orders inherit the promotion's current discount as the best available
-- approximation; their original historical discount cannot be recovered if it changed.
USE printhub;

ALTER TABLE orders
    ADD COLUMN promotion_discount DECIMAL(5,2) NOT NULL DEFAULT 0.00
    AFTER promotion_id;

UPDATE orders o
JOIN promotions p ON p.promotion_id = o.promotion_id
SET o.promotion_discount = p.discount;

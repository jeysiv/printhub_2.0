-- Run once on an existing PrintHub database to store custom dimension text.
USE printhub;

ALTER TABLE order_items
    MODIFY paper_size VARCHAR(60) NOT NULL DEFAULT 'A4';

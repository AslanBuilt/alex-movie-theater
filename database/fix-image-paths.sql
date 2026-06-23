-- Fix concession image_path values: add missing 'assets/' prefix
-- Run this once in phpMyAdmin on the live server.
-- Safe to run multiple times (WHERE clause prevents double-prefixing).

UPDATE `concessions`
SET `image_path` = CONCAT('assets/', `image_path`)
WHERE `image_path` LIKE 'images/%';

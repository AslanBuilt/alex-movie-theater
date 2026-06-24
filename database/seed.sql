-- ============================================================================
-- Alex Movie Theatre — Seed Data
-- ----------------------------------------------------------------------------
-- Run AFTER schema.sql.
--
-- !!! IMPORTANT !!!
-- The default admin user below has username "admin" and password "changeme123"
-- (bcrypt hash included). You MUST log in and change this password on first
-- login. Do not deploy this seed to production without rotating the password.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- movies — Now Showing
-- ----------------------------------------------------------------------------
INSERT INTO `movies`
    (`id`, `title`, `rating`, `screen`, `poster_path`, `description`, `status`, `online_only`, `sort_order`)
VALUES
    (1, 'Star Wars: The Mandalorian & Grogu', 'PG-13', 'large', 'images/starwars.webp',
     'The Mandalorian and Grogu return for a new big-screen adventure.',
     'now_showing', 0, 10),
    (2, 'The Sheep Detectives', 'PG', 'small', 'images/sheep.webp',
     'A family-friendly animated mystery — small screen only, tickets must be purchased online.',
     'now_showing', 1, 20);

-- ----------------------------------------------------------------------------
-- showtimes — Star Wars (movie_id=1)
-- showtime_date intentionally NULL because labels span date ranges
-- (Fri–Sun, May 23–25) which a single DATE column can't represent.
-- ----------------------------------------------------------------------------
INSERT INTO `showtimes`
    (`movie_id`, `label`, `times`, `showtime_date`, `sort_order`)
VALUES
    (1, 'Thursday, May 22',     '4:00 PM • 7:15 PM',            NULL, 10),
    (1, 'Fri–Sun, May 23–25',   '1:00 • 4:00 • 7:15 PM',        NULL, 20),
    (1, 'Memorial Day, May 26', '1:00 PM • 4:00 PM',            NULL, 30);

-- ----------------------------------------------------------------------------
-- showtimes — Sheep Detectives (movie_id=2)
-- ----------------------------------------------------------------------------
INSERT INTO `showtimes`
    (`movie_id`, `label`, `times`, `showtime_date`, `sort_order`)
VALUES
    (2, 'Thursday, May 22',     '4:30 PM • 7:30 PM',            NULL, 10),
    (2, 'Fri–Sun, May 23–25',   '1:30 • 4:30 • 7:30 PM',        NULL, 20),
    (2, 'Memorial Day, May 26', '1:30 PM • 4:30 PM',            NULL, 30);

-- ----------------------------------------------------------------------------
-- movies — Coming Soon
-- ----------------------------------------------------------------------------
INSERT INTO `movies`
    (`id`, `title`, `rating`, `screen`, `poster_path`, `description`, `status`, `online_only`, `sort_order`)
VALUES
    (3, 'The Mummy',    '', 'either', '', NULL, 'coming_soon', 0, 10),
    (4, 'Toy Story 5',  '', 'either', '', NULL, 'coming_soon', 0, 20);

-- ----------------------------------------------------------------------------
-- events
-- ----------------------------------------------------------------------------
INSERT INTO `events`
    (`title`, `description`, `event_date`, `badge`, `image_path`, `status`, `sort_order`)
VALUES
    ('Escape From The "Lockdown Theatre"',
     'An immersive escape room experience set inside the Alex Theatre itself. Details coming soon — follow our social media for the announcement.',
     NULL, 'Coming Soon', NULL, 'tba', 10);

-- ----------------------------------------------------------------------------
-- senior_showings
-- ----------------------------------------------------------------------------
INSERT INTO `senior_showings`
    (`movie_title`, `showing_date`, `showing_time`, `notes`, `status`)
VALUES
    ('TBA — Check Back Soon', NULL, NULL,
     'Date & film to be announced. Hosted by Senior Essential Connections.',
     'tba');

-- ----------------------------------------------------------------------------
-- concessions
-- ----------------------------------------------------------------------------
INSERT INTO `concessions` (`category`, `name`, `description`, `price`, `image_path`, `is_available`, `sort_order`) VALUES
-- Combos
('Combos', 'Two Person Combo',
 'Large Popcorn + Two Large Drinks. Best value for two.',
 15.50, 'assets/images/concessions/combo-two.webp', 1, 10),
('Combos', 'One Person Combo',
 'Medium Popcorn + Large Drink.',
 9.50, 'assets/images/concessions/combo-one.webp', 1, 20),
('Combos', 'Kids Combo',
 'Popcorn + Kids Drink + Small Gummy.',
 4.00, 'assets/images/concessions/combo-kids.webp', 1, 30),
-- Popcorn
('Popcorn', 'Large Popcorn (170oz)',
 'Fresh-popped buttered popcorn — our biggest size.',
 7.50, 'assets/images/concessions/popcorn-large.webp', 1, 10),
('Popcorn', 'Medium Popcorn (130oz)',
 'Fresh-popped buttered popcorn — medium size.',
 5.50, 'assets/images/concessions/popcorn-medium.webp', 1, 20),
('Popcorn', 'Small Popcorn (85oz)',
 'Fresh-popped buttered popcorn — small size.',
 3.50, 'assets/images/concessions/popcorn-small.webp', 1, 30),
-- Drinks
('Drinks', 'Large Fountain (32oz)',
 'Pepsi, Mtn Dew, Dr Pepper, Diet Mtn Dew, Tropicana, Crush, Sierra Mist.',
 4.00, 'assets/images/concessions/drink-fountain.webp', 1, 10),
('Drinks', 'Medium Fountain (20oz)',
 'Pepsi, Mtn Dew, Dr Pepper, Diet Mtn Dew, Tropicana, Crush, Sierra Mist.',
 3.00, 'assets/images/concessions/drink-fountain.webp', 1, 20),
('Drinks', 'Bottle Drinks',
 'Water, Diet Pepsi, or Sweet Tea.',
 2.00, 'assets/images/concessions/drink-bottle.webp', 1, 30),
-- Candy
('Candy', 'Box Candy',
 'Reese''s Pieces, Skittles, M&M''s, Mike & Ike, Sour Patch, Whoppers, Junior Mints, Cookie Dough Bites, Milk Duds, Buncha Crunch.',
 2.50, 'assets/images/concessions/candy-box.webp', 1, 10),
('Candy', 'Wrapper Candy',
 'Single-wrapper candy bars and treats.',
 1.50, 'assets/images/concessions/candy-box.webp', 1, 20),
('Candy', 'Cotton Candy',
 'Classic spun cotton candy — pink & blue.',
 3.00, 'assets/images/concessions/candy-cotton.webp', 1, 30);

-- ----------------------------------------------------------------------------
-- concession_options
-- ----------------------------------------------------------------------------
-- Large Fountain (id=7) and Medium Fountain (id=8) share the same flavors
INSERT INTO `concession_options` (`concession_id`, `option_label`, `sort_order`) VALUES
(7, 'Pepsi', 0), (7, 'Mtn Dew', 1), (7, 'Dr Pepper', 2), (7, 'Diet Mtn Dew', 3),
(7, 'Tropicana', 4), (7, 'Crush', 5), (7, 'Sierra Mist', 6),
(8, 'Pepsi', 0), (8, 'Mtn Dew', 1), (8, 'Dr Pepper', 2), (8, 'Diet Mtn Dew', 3),
(8, 'Tropicana', 4), (8, 'Crush', 5), (8, 'Sierra Mist', 6);

-- Bottle Drinks (id=9)
INSERT INTO `concession_options` (`concession_id`, `option_label`, `sort_order`) VALUES
(9, 'Water', 0), (9, 'Diet Pepsi', 1), (9, 'Sweet Tea', 2);

-- Box Candy (id=10)
INSERT INTO `concession_options` (`concession_id`, `option_label`, `sort_order`) VALUES
(10, 'Reese''s Pieces', 0), (10, 'Skittles', 1), (10, 'M&M''s', 2),
(10, 'Mike & Ike', 3), (10, 'Sour Patch', 4), (10, 'Whoppers', 5),
(10, 'Junior Mints', 6), (10, 'Cookie Dough Bites', 7), (10, 'Milk Duds', 8),
(10, 'Buncha Crunch', 9);

-- ----------------------------------------------------------------------------
-- concessions — starting stock quantities
-- ----------------------------------------------------------------------------
UPDATE `concessions` SET `stock_quantity` = 20, `reorder_point` = 5  WHERE `id` = 1;  -- Two Person Combo
UPDATE `concessions` SET `stock_quantity` = 30, `reorder_point` = 5  WHERE `id` = 2;  -- One Person Combo
UPDATE `concessions` SET `stock_quantity` = 20, `reorder_point` = 5  WHERE `id` = 3;  -- Kids Combo
UPDATE `concessions` SET `stock_quantity` = 50, `reorder_point` = 10 WHERE `id` = 4;  -- Large Popcorn
UPDATE `concessions` SET `stock_quantity` = 50, `reorder_point` = 10 WHERE `id` = 5;  -- Medium Popcorn
UPDATE `concessions` SET `stock_quantity` = 50, `reorder_point` = 10 WHERE `id` = 6;  -- Small Popcorn
UPDATE `concessions` SET `stock_quantity` = 100, `reorder_point` = 20 WHERE `id` = 7; -- Large Fountain
UPDATE `concessions` SET `stock_quantity` = 100, `reorder_point` = 20 WHERE `id` = 8; -- Medium Fountain
UPDATE `concessions` SET `stock_quantity` = 48, `reorder_point` = 12  WHERE `id` = 9; -- Bottle Drinks
UPDATE `concessions` SET `stock_quantity` = 60, `reorder_point` = 15  WHERE `id` = 10; -- Box Candy
UPDATE `concessions` SET `stock_quantity` = 30, `reorder_point` = 10  WHERE `id` = 11; -- Wrapper Candy
UPDATE `concessions` SET `stock_quantity` = 20, `reorder_point` = 5   WHERE `id` = 12; -- Cotton Candy

-- ----------------------------------------------------------------------------
-- admin_users
-- ----------------------------------------------------------------------------
-- Default credentials: admin / changeme123
-- CHANGE THIS PASSWORD ON FIRST LOGIN.
INSERT INTO `admin_users`
    (`username`, `password_hash`, `email`, `role`, `is_active`)
VALUES
    ('admin',
     '$2y$12$JCVY0IFPSzBgctTNI6l7dui6GxWi1IXmPw1bx9zDVZp..5MEBOtxS', /* changeme123 */
     NULL,
     'admin',
     1);

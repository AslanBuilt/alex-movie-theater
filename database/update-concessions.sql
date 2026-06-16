-- ============================================================================
-- Alex Theatre — Concessions menu update
-- Run this in phpMyAdmin (or via mysql CLI) to replace the concession menu
-- with the correct items, prices, and image paths.
-- ============================================================================

SET NAMES utf8mb4;

TRUNCATE TABLE `concession_orders`; -- clear orders that reference old items
TRUNCATE TABLE `concessions`;

INSERT INTO `concessions` (`category`, `name`, `description`, `price`, `image_path`, `is_available`, `sort_order`) VALUES
-- Combos
('Combos', 'Two Person Combo',
 'Large Popcorn + Two Large Drinks. Best value for two.',
 15.50, 'images/concessions/combo-two.png', 1, 10),
('Combos', 'One Person Combo',
 'Medium Popcorn + Large Drink.',
 9.50, 'images/concessions/combo-one.png', 1, 20),
('Combos', 'Kids Combo',
 'Popcorn + Kids Drink + Small Gummy.',
 4.00, 'images/concessions/combo-kids.png', 1, 30),
-- Popcorn
('Popcorn', 'Large Popcorn (170oz)',
 'Fresh-popped buttered popcorn — our biggest size.',
 7.50, 'images/concessions/popcorn-large.png', 1, 10),
('Popcorn', 'Medium Popcorn (130oz)',
 'Fresh-popped buttered popcorn — medium size.',
 5.50, 'images/concessions/popcorn-medium.png', 1, 20),
('Popcorn', 'Small Popcorn (85oz)',
 'Fresh-popped buttered popcorn — small size.',
 3.50, 'images/concessions/popcorn-small.png', 1, 30),
-- Drinks
('Drinks', 'Large Fountain (32oz)',
 'Pepsi, Mtn Dew, Dr Pepper, Diet Mtn Dew, Tropicana, Crush, Sierra Mist.',
 4.00, 'images/concessions/drink-fountain.png', 1, 10),
('Drinks', 'Medium Fountain (20oz)',
 'Pepsi, Mtn Dew, Dr Pepper, Diet Mtn Dew, Tropicana, Crush, Sierra Mist.',
 3.00, 'images/concessions/drink-fountain.png', 1, 20),
('Drinks', 'Bottle Drinks',
 'Water, Diet Pepsi, or Sweet Tea.',
 2.00, 'images/concessions/drink-bottle.png', 1, 30),
-- Candy
('Candy', 'Box Candy',
 'Reese''s Pieces, Skittles, M&M''s, Mike & Ike, Sour Patch, Whoppers, Junior Mints, Cookie Dough Bites, Milk Duds, Buncha Crunch.',
 2.50, 'images/concessions/candy-box.png', 1, 10),
('Candy', 'Wrapper Candy',
 'Single-wrapper candy bars and treats.',
 1.50, 'images/concessions/candy-box.png', 1, 20),
('Candy', 'Cotton Candy',
 'Classic spun cotton candy — pink & blue.',
 3.00, 'images/concessions/candy-cotton.png', 1, 30);

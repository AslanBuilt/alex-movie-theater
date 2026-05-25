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
    (1, 'Star Wars: The Mandalorian & Grogu', 'PG-13', 'large', 'images/starwars.jpg',
     'The Mandalorian and Grogu return for a new big-screen adventure.',
     'now_showing', 0, 10),
    (2, 'The Sheep Detectives', 'PG', 'small', 'images/sheep.jpg',
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
-- admin_users
-- ----------------------------------------------------------------------------
-- Default credentials: admin / changeme123
-- CHANGE THIS PASSWORD ON FIRST LOGIN.
INSERT INTO `admin_users`
    (`username`, `password_hash`, `email`, `role`, `is_active`)
VALUES
    ('admin',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
     NULL,
     'admin',
     1);

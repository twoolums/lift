-- Swim Lift Log: database schema + seed data
-- Import this once (phpMyAdmin > Import) into an EMPTY MySQL/MariaDB database.
-- Requires MySQL 5.7+ or MariaDB 10.3+.

SET NAMES utf8mb4;

CREATE TABLE coaches (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coach_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE swimmers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(60) NOT NULL,
  last_name VARCHAR(60) NOT NULL DEFAULT '',
  pin_hash VARCHAR(255) NULL,
  failed_pins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  last_login DATETIME NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE device_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  swimmer_id INT UNSIGNED NOT NULL,
  selector CHAR(18) NOT NULL,
  validator_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_selector (selector),
  CONSTRAINT fk_dt_swimmer FOREIGN KEY (swimmer_id) REFERENCES swimmers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A weight-room tablet/computer that a coach has authorized for quick entry
CREATE TABLE kiosk_devices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  selector CHAR(18) NOT NULL,
  validator_hash CHAR(64) NOT NULL,
  label VARCHAR(60) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kiosk_selector (selector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- input_type: wr = value (weight/height/etc) + reps, reps = reps only, sec = seconds only
CREATE TABLE exercises (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  input_type ENUM('wr','reps','sec') NOT NULL DEFAULT 'wr',
  value_label VARCHAR(20) NOT NULL DEFAULT 'lb',
  step DECIMAL(4,1) NOT NULL DEFAULT 5.0,
  cue VARCHAR(400) NOT NULL DEFAULT '',
  link_url VARCHAR(500) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- day_type: A, B, C or P (meet-day primer). phase: 1-3, or 0 for P.
CREATE TABLE program_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  day_type CHAR(1) NOT NULL,
  phase TINYINT UNSIGNED NOT NULL,
  position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  label VARCHAR(8) NOT NULL DEFAULT '',
  exercise_id INT UNSIGNED NOT NULL,
  sets TINYINT UNSIGNED NOT NULL DEFAULT 3,
  reps_text VARCHAR(40) NOT NULL DEFAULT '',
  rest_text VARCHAR(20) NOT NULL DEFAULT '',
  starred TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_day_phase (day_type, phase, position),
  CONSTRAINT fk_pi_exercise FOREIGN KEY (exercise_id) REFERENCES exercises (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per scheduled lift session (the dates from your season schedule)
CREATE TABLE workouts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  workout_date DATE NOT NULL,
  title VARCHAR(100) NOT NULL,
  day_type CHAR(1) NOT NULL,
  phase TINYINT UNSIGNED NOT NULL DEFAULT 1,
  length_min SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  notes VARCHAR(500) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_workout_date (workout_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- val = weight / height / seconds (depending on exercise type), reps = reps
CREATE TABLE set_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  swimmer_id INT UNSIGNED NOT NULL,
  workout_id INT UNSIGNED NOT NULL,
  exercise_id INT UNSIGNED NOT NULL,
  set_no TINYINT UNSIGNED NOT NULL,
  val DECIMAL(6,1) NULL,
  reps SMALLINT UNSIGNED NULL,
  logged_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_set (swimmer_id, workout_id, exercise_id, set_no),
  KEY idx_swimmer_exercise (swimmer_id, exercise_id),
  CONSTRAINT fk_sl_swimmer FOREIGN KEY (swimmer_id) REFERENCES swimmers (id) ON DELETE CASCADE,
  CONSTRAINT fk_sl_workout FOREIGN KEY (workout_id) REFERENCES workouts (id) ON DELETE CASCADE,
  CONSTRAINT fk_sl_exercise FOREIGN KEY (exercise_id) REFERENCES exercises (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Seed data: exercises ----------
INSERT INTO exercises (id, name, input_type, value_label, step, cue, link_url) VALUES
(1, 'Countermovement Jump', 'reps', '', 1, 'Max intent, stick every landing, reset between reps.', 'https://library.theprehabguys.com/vimeo-video/counter-movement-jump-in-place'),
(2, 'Box Jump', 'wr', 'box in', 2, 'Enter the box height in inches. Pick a box you can land on softly. Stick the landing, step down.', 'https://www.nasm.org/resource-center/exercise-library/box-jumps'),
(3, 'DB Jump Squat', 'wr', 'lb', 5, 'Weight = each dumbbell. Light load, jump for max height, land soft and stick.', 'https://barbend.com/dumbbell-jump-squat/'),
(4, 'Back Squat', 'wr', 'lb', 5, '3-sec controlled lower, drive up fast. Leave reps in the tank. Rack with safeties.', 'https://www.acefitness.org/resources/everyone/exercise-library/11/back-squat/'),
(5, 'Barbell RDL', 'wr', 'lb', 5, 'Hips back, flat back, bar slides down the legs. Stop at a hamstring stretch, never a rounded back.', 'https://www.nasm.org/resource-center/exercise-library/romanian-deadlift-barbell'),
(6, 'Copenhagen Plank', 'sec', 'sec', 5, 'Enter hold time in seconds per side. Start with the knee-assisted version.', 'https://catalystathletics.com/exercise/565/Copenhagen-Plank'),
(7, 'DB Reverse Lunge', 'wr', 'lb', 5, 'Weight = each dumbbell. Step back softly, front shin vertical, drive through the front heel.', 'https://www.masterclass.com/articles/dumbbell-reverse-lunge'),
(8, 'Front Plank', 'sec', 'sec', 5, 'Ribs down, glutes squeezed, no sagging hips.', 'https://cathe.com/plank-progressions-how-to-advance-your-planks/'),
(9, 'RKC Plank', 'sec', 'sec', 5, 'Squeeze everything: pull elbows toward toes, glutes tight, ribs down.', 'https://generationiron.com/rkc-plank-variation-gains/'),
(10, 'Body Saw', 'reps', '', 1, 'Small rocking motion from the shoulders, body stays in one straight line.', 'https://coachweb.com/core-exercises/6344/upgrade-your-plank-with-the-body-saw'),
(11, 'Med Ball Chest Pass', 'wr', 'ball lb', 2, 'Max speed off the wall, catch and reset. Use a heavier ball each phase.', 'https://www.strengthlog.com/medicine-ball-chest-pass/'),
(12, 'Bench Press', 'wr', 'lb', 5, 'Shoulder blades pinched, feet planted, controlled lower, fast press. Always use a spotter.', 'https://www.nasm.org/resource-center/exercise-library/barbell-bench-press'),
(13, 'Pull-Up', 'wr', 'added lb', 5, 'Enter ADDED weight (0 = bodyweight). Full hang to chin over bar. If you use a band, log 0.', 'https://www.nasm.org/resource-center/exercise-library/pull-up'),
(14, 'Landmine Press', 'wr', 'lb', 5, 'Half-kneeling in Phases 1-2: squeeze the glute on the kneeling side, press up and slightly forward. Phase 3: standing with leg drive.', 'https://central.gymshark.com/article/how-to-landmine-press'),
(15, 'Chest-Supported DB Row', 'wr', 'lb', 5, 'Weight = each dumbbell. Chest on the bench, pull elbows to hips, 1-sec squeeze.', 'https://barbend.com/chest-supported-row/'),
(16, 'Straight-Arm Pulldown', 'wr', 'lb', 5, 'Arms nearly straight, pull bar or band to thighs, ribs down.', 'https://www.masterclass.com/articles/straight-arm-pulldowns-guide'),
(17, 'DB Overhead Press', 'wr', 'lb', 5, 'Weight = each dumbbell. Stand tall, glutes tight, press straight up past the ears.', 'https://fitbod.me/exercises/standing-dumbbell-shoulder-press'),
(18, 'DB Y-Raise / Straight Fly', 'wr', 'lb', 5, 'Weight = each dumbbell. Alternate sets: Y-raise, then straight fly out to the sides. Light weight.', 'https://www.puregym.com/exercises/arms-and-shoulders/rear-delt-exercises/y-raises/'),
(19, 'Med Ball Rotational Throw', 'wr', 'ball lb', 2, 'Load the back hip. Hips turn first, then trunk, then arms: legs to hands in one sequence.', 'https://theprehabguys.com/vimeo-video/medball-shot-put-throw/'),
(20, 'Trap Bar Deadlift', 'wr', 'lb', 5, 'Push the floor away, fast and flat. Pull the slack out of the bar before it leaves the floor.', 'https://www.masterclass.com/articles/trap-bar-deadlift-guide'),
(21, 'Landmine Rotational Press', 'wr', 'lb', 5, 'Drive from the ground: feet pivot, hips turn, then press. Fast but controlled.', 'https://rb100.fitness/exercise/landmine-rotational-press/'),
(22, 'DB Squat-to-Press', 'wr', 'lb', 5, 'Weight = each dumbbell. One fluid motion: squat, drive up, use the leg drive to press overhead.', 'https://fitbod.me/exercises/dumbbell-thruster'),
(23, 'Renegade Row', 'wr', 'lb', 5, 'Weight = each dumbbell. Feet wide, hips square, no twisting. Drop to the knees if the hips rotate.', 'https://www.puregym.com/exercises/back/rows/renegade-row/'),
(24, 'Landmine Kneeling Rotation', 'wr', 'lb', 5, 'Rotate from the upper back, not the low back. Tall-kneel or half-kneel.', 'https://musclewiki.com/exercise/landmine-kneeling-twist'),
(25, 'Med Ball Slam', 'wr', 'ball lb', 2, 'Reach tall, slam with the whole body, reset each rep.', 'https://sweat.com/exercises/medicine-ball-slams'),
(26, 'Pogo Hops', 'reps', '', 1, 'Quick, light ground contacts, stiff ankles.', 'https://hortonbarbell.com/forward-pogo-hops-how-to-benefits-common-mistakes/');

-- ---------- Seed data: program (day type A/B/C, phase 1-3; P = primer, phase 0) ----------
INSERT INTO program_items (day_type, phase, position, label, exercise_id, sets, reps_text, rest_text, starred) VALUES
('A', 1, 1, '1', 1, 3, '5', '90s', 0),
('A', 2, 1, '1', 2, 4, '3', '90s', 0),
('A', 3, 1, '1', 3, 4, '3', '90s', 0),
('A', 1, 2, '2', 4, 3, '8', '2-3 min', 0),
('A', 2, 2, '2', 4, 4, '6', '2-3 min', 0),
('A', 3, 2, '2', 4, 4, '4', '2-3 min', 0),
('A', 1, 3, '3', 5, 3, '8', '2 min', 0),
('A', 2, 3, '3', 5, 3, '6', '2 min', 0),
('A', 3, 3, '3', 5, 3, '5', '2 min', 0),
('A', 1, 4, '4', 6, 3, '15s/side', '45s', 0),
('A', 2, 4, '4', 6, 3, '20s/side', '45s', 0),
('A', 3, 4, '4', 6, 3, '25s/side', '45s', 0),
('A', 1, 5, '5a', 7, 3, '8/side', '60s', 1),
('A', 2, 5, '5a', 7, 3, '8/side', '60s', 1),
('A', 3, 5, '5a', 7, 3, '6/side', '60s', 1),
('A', 1, 6, '5b', 8, 3, '20s', '45s', 1),
('A', 2, 6, '5b', 9, 3, '30s', '45s', 1),
('A', 3, 6, '5b', 10, 3, '8', '45s', 1),
('B', 1, 1, '1', 11, 3, '6', '60s', 0),
('B', 2, 1, '1', 11, 4, '5', '60s', 0),
('B', 3, 1, '1', 11, 4, '4', '60s', 0),
('B', 1, 2, '2a', 12, 3, '8', '2 min', 0),
('B', 2, 2, '2a', 12, 4, '6', '2 min', 0),
('B', 3, 2, '2a', 12, 4, '4', '2 min', 0),
('B', 1, 3, '2b', 13, 3, '6-8', 'with 2a', 0),
('B', 2, 3, '2b', 13, 4, '5-6', 'with 2a', 0),
('B', 3, 3, '2b', 13, 4, '4', 'with 2a', 0),
('B', 1, 4, '3', 14, 3, '8/side', '75s', 0),
('B', 2, 4, '3', 14, 3, '6/side', '75s', 0),
('B', 3, 4, '3', 14, 4, '4/side', '75s', 0),
('B', 1, 5, '4a', 15, 3, '10', '60s', 1),
('B', 2, 5, '4a', 15, 3, '8', '60s', 1),
('B', 3, 5, '4a', 15, 3, '8', '60s', 1),
('B', 1, 6, '4b', 16, 3, '12', '60s', 1),
('B', 2, 6, '4b', 16, 3, '12', '60s', 1),
('B', 3, 6, '4b', 16, 3, '10', '60s', 1),
('B', 2, 7, '5a', 17, 3, '8', '90s', 1),
('B', 3, 7, '5a', 17, 3, '6', '90s', 1),
('B', 1, 8, '5b', 18, 2, '12', '45s', 1),
('B', 2, 8, '5b', 18, 3, '12', '45s', 1),
('B', 3, 8, '5b', 18, 3, '10', '45s', 1),
('C', 1, 1, '1', 19, 3, '5/side', '75s', 0),
('C', 2, 1, '1', 19, 4, '4/side', '75s', 0),
('C', 3, 1, '1', 19, 4, '4/side', '75s', 0),
('C', 1, 2, '2', 20, 3, '6', '2-3 min', 0),
('C', 2, 2, '2', 20, 4, '5', '2-3 min', 0),
('C', 3, 2, '2', 20, 4, '3', '2-3 min', 0),
('C', 1, 3, '3', 21, 3, '6/side', '75s', 0),
('C', 2, 3, '3', 21, 3, '6/side', '75s', 0),
('C', 3, 3, '3', 21, 4, '4/side', '75s', 0),
('C', 1, 4, '4', 22, 3, '8', '90s', 0),
('C', 2, 4, '4', 22, 3, '6', '90s', 0),
('C', 3, 4, '4', 22, 3, '5', '90s', 0),
('C', 1, 5, '5a', 23, 3, '5/side', '60s', 1),
('C', 2, 5, '5a', 23, 3, '6/side', '60s', 1),
('C', 3, 5, '5a', 23, 3, '6/side', '60s', 1),
('C', 1, 6, '5b', 24, 3, '8/side', '45s', 1),
('C', 2, 6, '5b', 24, 3, '8/side', '45s', 1),
('C', 3, 6, '5b', 24, 3, '8/side', '45s', 1),
('C', 1, 7, '6', 25, 3, '8', '60s', 1),
('C', 2, 7, '6', 25, 3, '8', '60s', 1),
('C', 3, 7, '6', 25, 3, '6', '60s', 1),
('P', 0, 1, '1', 26, 2, '10', '60s', 0),
('P', 0, 2, '2', 1, 2, '3', '60s', 0),
('P', 0, 3, '3', 11, 2, '5', '60s', 0),
('P', 0, 4, '4', 14, 2, '5/side', '60s', 0),
('P', 0, 5, '5', 13, 2, '5', '60s', 0),
('P', 0, 6, '6', 16, 2, '10', '60s', 0),
('P', 0, 7, '7', 6, 2, '15s/side', '60s', 0);

-- ---------- Seed data: your 2026-27 session dates ----------
INSERT INTO workouts (workout_date, title, day_type, phase, length_min, notes) VALUES
('2026-11-17', 'Day A: Lower Strength & Jumps', 'A', 1, 60, 'First session. Coach technique, keep 3-4 reps in reserve.'),
('2026-11-19', 'Day B: Upper Push/Pull', 'B', 1, 60, ''),
('2026-11-21', 'Day C: Kinetic Chain', 'C', 1, 45, '45-minute day: starred exercises are skipped.'),
('2026-11-24', 'Day A: Lower Strength & Jumps', 'A', 1, 60, 'Intrasquad meet at 3:15PM: drop 1 set from squat and RDL.'),
('2026-11-27', 'Turkey Day Special', 'C', 1, 120, 'Run Day C in full, then the circuit: 4 rounds of 6 stations (40s work / 20s rest): Med Ball Slam, Chest Pass, DB Reverse Lunge, Renegade Row (light), Landmine Kneeling Rotation, Plank Shoulder Tap. Finish with a fun team challenge.'),
('2026-11-28', 'Day B: Upper Push/Pull', 'B', 1, 45, 'Day after the long session: keep loads moderate.'),
('2026-12-01', 'Day C: Kinetic Chain', 'C', 2, 60, ''),
('2026-12-03', 'Meet-Day Primer', 'P', 0, 60, 'Away meet at Valley tonight, 5:30PM. Keep it light and sharp: no heavy lifting or max jumps.'),
('2026-12-08', 'Day A: Lower Strength & Jumps', 'A', 2, 60, 'Heaviest lower session before the Dec 12 meet.'),
('2026-12-10', 'Day B: Upper Push/Pull', 'B', 2, 60, 'Tiger Invite is Saturday: drop 1 set from every lift.'),
('2026-12-26', 'Day A: Lower (Re-Entry)', 'A', 2, 45, 'First lift in 16 days. Use about 90% of your Dec 8 weights.'),
('2026-12-29', 'Day B: Upper Push/Pull', 'B', 3, 45, ''),
('2026-12-31', 'Day C: Kinetic Chain', 'C', 3, 45, 'Ken Gaeth Invite is Saturday: drop 1 set from throws and jumps.'),
('2027-01-05', 'Day A: Lower Strength & Jumps', 'A', 3, 60, 'Last full lift of the season. Low volume, maximum bar and jump speed.'),
('2027-01-07', 'Meet-Day Primer', 'P', 0, 60, 'Home meet vs Waukee tonight, 5:30PM. Keep it light and sharp.');

# Swim Lift Log (v2)

A touch-friendly web app for swimmers to log their lifts (weight, reps, seconds) and for coaches to see who
has logged what, with progress charts per swimmer and for the whole team. PHP + MySQL, no frameworks.

Requirements: PHP 7.4 or newer (8.x is fine) with the `pdo_mysql` extension, and MySQL 5.7+ / MariaDB 10.3+.

## New install (about 10 minutes)

1. In your hosting control panel, create an empty MySQL database and a database user with full rights to it.
2. In phpMyAdmin, select that database, choose **Import**, and upload `schema.sql`.
   This creates the tables and loads your 2026-27 sessions, exercises and the 8-week program.
3. Edit `config.php`: database name/user/password, team name, and change `SETUP_KEY` to your own phrase.
4. Upload this whole folder to your website, for example into `lifts`, so it lives at
   `https://yourwebsite.com/lifts/`. Use HTTPS.
5. Visit `https://yourwebsite.com/lifts/admin/setup.php`, enter your setup key, and create your coach login.
6. **Delete `admin/setup.php`, `schema.sql` and `migrate_v2.sql` from the server.**
7. Log in at `/lifts/admin/login.php`, open **Team**, paste your swimmers' names (one per line), and print
   the PIN cards. Hand each swimmer their card.

## Upgrading from the first version

1. Back up your database (phpMyAdmin > Export).
2. Run `migrate_v2.sql` once (phpMyAdmin > SQL tab). It switches swimmers to 4-digit PINs and adds the kiosk table.
3. Upload the new files over the old ones (keep your edited `config.php`).
4. Log in as coach, open **Team**, and click **Create PINs for swimmers who need one**. Print the cards.
   Your logged lifts are untouched.
5. Delete `migrate_v2.sql` from the server.

## How swimmers log in

They open the site, **pick their name** from the list, and type their **4-digit PIN**. With "Keep me logged in"
checked they only do this once per phone. After 5 wrong PINs for a name, that name is locked for 10 minutes
(a coach can unlock it right away from the swimmer's page). The login list shows first name + last initial.

## Weight-room kiosk

Coach menu > **Kiosk**. On the tablet/computer that stays in the weight room, log in as coach, open that page,
and tap **Set up THIS device as a kiosk**, then **Open the kiosk on this device**.
A swimmer picks their name from the drop-down, enters their sets, and taps **Done: next athlete**.
After 3 minutes with no touching, it returns to the name picker. You can pick which session the kiosk shows
(it defaults to today's). Revoke any device from the same page. Only authorized devices can open the kiosk or
see the full roster.

## Coaches

Coach menu > **Coaches**: add a second coach, change your own password, remove a coach.
Every coach can do everything. If someone forgets a password, remove that coach and add them again.

## Charts (coach side)

- **Progress** (team): attendance by session, then pick any exercise for the team-average trend, an optional
  line for every swimmer, and a table of first / latest / change / best for each athlete.
  With "Show each swimmer" on, every swimmer gets their own color and a clickable key sits under the chart.
  Hover a line on a computer (or tap a line or a name on a phone) to see a tooltip with the swimmer's name, date
  and value, and to fade everyone else out. Click the same name again, or empty space, to show everyone.
- **Swimmer page**: attendance bars plus a trend chart for every lift logged in 2 or more sessions.

## Other coach tools

Sessions (dates, titles, lengths), Program (which exercises each session type shows), Exercises (names, +/- step,
cues, links), Export (CSV for Excel or Sheets). Coaches can also open any session for any swimmer to fix entries.

## Privacy and security notes

- The app stores a first name, last name, and lift numbers. No email, phone, or birthdate.
- PINs and passwords are stored hashed (`password_hash`). PINs are shown once when created, so print or screenshot them.
- A 4-digit PIN is a small secret: it is protected by per-name lockout and per-network throttling, and the data
  is low sensitivity, but treat it like a locker combination, not a bank password.
- The kiosk device cookie lets anyone using that device enter lifts for any swimmer, so keep it in the weight room
  and revoke it at season's end.
- All database access uses prepared statements, and forms use CSRF tokens.
- Because these are students' records, check your school or district policy on hosting student data, and
  keep the site on HTTPS.
- If your host is nginx instead of Apache, the `.htaccess` files are ignored. Move `config.php` above the web root
  or block `*.sql`, `config.php` and `inc/` in the server config.

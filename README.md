# Smart College Club Management System

A PHP, MySQL, HTML, CSS, and JavaScript college club management project with separate Student and Admin modules.

## Modules

### Student Module

- Student registration
- Separate student login: `student_login.php`
- Student dashboard: `dashboard.php`
- Profile create/edit and password update
- Club join requests and club leaving
- Joined/pending clubs
- Event registration for approved club memberships
- Personal participation and attendance history
- Personal payment history
- Student-only data access through session role checks

### Admin Module

- Separate admin login: `admin_login.php`
- Admin dashboard: `admin/admin_dashboard.php`
- Student management: `admin/manage_users.php`
- Club management: `admin/manage_clubs.php`
- Event and attendance management: `admin/manage_events.php`
- Payment management: `admin/manage_payments.php`
- Staff/coordinator management: `admin/manage_staff.php`
- Reports and analytics: `admin/reports.php`
- Admin-only data access through session role checks

## Admin Dashboard Includes

- Total students
- Total clubs
- Total events
- Active participants
- Total payments
- Club-wise approved and pending member counts
- Recent student registrations
- Upcoming event capacity
- Payment summaries

## Database

Import the full schema and seed data:

```bash
mysql -u root -p < club_management_system/sql/database.sql
```

The database includes:

- `LOGIN` with `username`, secure `password_hash`, `role`, `member_id`, and `staff_id`
- `MEMBER`
- `MEMBER_DETAILS`
- `CLUB`
- `MEMBER_CLUB` with `membership_status`, approval staff, approval date, and removal date
- `EVENT`
- `PARTICIPATION`
- `ATTENDANCE`
- `PAYMENT`
- `STAFF`
- `STAFF_ASSIGNMENT`

DBMS features used:

- Primary keys and foreign keys
- Role-based login table
- Student-club many-to-many relationship
- Payment tracking
- Event participation tracking
- Attendance tracking
- Staff/coordinator assignments
- Stored procedure: `GetEventsByClub`
- Triggers for event attendee counts and attendance row sync

## Setup

1. Install PHP 8+ and MySQL 8+ or MariaDB.
2. Import `sql/database.sql`.
3. Update database credentials in `db.php`, or set:

```bash
DB_HOST=localhost
DB_NAME=club_management_system
DB_USER=root
DB_PASS=your_password
```

4. Start the PHP server from the parent folder:

```bash
php -S 127.0.0.1:8000 -t club_management_system
```

5. Open:

```text
http://127.0.0.1:8000
```

## Demo Login

All seeded demo users use:

```text
password
```

Admin:

```text
admin@college.edu
```

Students:

```text
aarav.sharma@college.edu
neha.iyer@college.edu
rohan.kumar@college.edu
```

Students can also log in with their USN, for example:

```text
1XX22CS001
```

## Suggested Demo Flow

1. Register or log in as a student.
2. Request to join a club from the student club page.
3. Log in as admin and approve the membership.
4. Return as the student and register for an event from an approved club.
5. Mark attendance from Admin > Events.
6. Review payments and reports from the admin module.

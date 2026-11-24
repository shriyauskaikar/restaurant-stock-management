# Running this project on YOUR laptop

Hi! This project needs **XAMPP** (Apache + PHP) and **MySQL**. The code comes
from GitHub, but the **database is created on your own laptop** — it is not
downloaded from GitHub. That is normal: every student gets their own empty
database, then loads the sample data if they want it.

---

## The short answer to "what about the DB?"

| Question | Answer |
|---|---|
| Is the database in the GitHub repo? | **No.** Only the `.sql` scripts are. |
| How do I get the database? | Run `sql/schema.sql` in **MySQL Workbench** (or phpMyAdmin). |
| Do I need MySQL Workbench? | No, but it is the easiest way. phpMyAdmin works too. |
| Do I need the same password as Shriya? | **No.** You choose your own MySQL password and admin password. |

---

## Step 1 — Install XAMPP

1. Download XAMPP for Windows from https://www.apachefriends.org/
2. Install it, e.g. into `C:\xampp`.
3. Copy this GitHub project into:

```
C:\xampp\htdocs\restaurant-stock-management\
```

The folder name must be exactly `restaurant-stock-management`, because the
browser address is built from it.

---

## Step 2 — Start Apache and MySQL

Open the **XAMPP Control Panel** and click **Start** next to **Apache** and
**MySQL**.

> This project was written against MySQL Server 8.0 on port **3307** because
> XAMPP's own MariaDB already uses 3306. If you only have XAMPP's MariaDB,
> that is fine — see the "Using XAMPP's MariaDB instead" section below.

---

## Step 3 — Create the database in MySQL Workbench

1. Open **MySQL Workbench**.
2. Click your local connection (usually called `Local instance MySQL80`).
   - To make a new one: **+** → Connection Name `Local instance MySQL80`,
     Host `127.0.0.1`, Port `3307`, Username `root`. Enter **your** MySQL
     password (the one you set when installing MySQL), then **Test Connection**.
3. Press **Ctrl+T** (File → New Query Tab).
4. Click **Open** and choose:

```
sql/schema.sql
```

5. Press the **lightning bolt (Execute)** button.
6. You should see `Database created successfully` and 4 tables:
   `users`, `suppliers`, `ingredients`, `purchases`.

**Optional:** open `sql/sample_data.sql` and execute it too, to fill the
dashboard with example suppliers, ingredients and purchases.

### Using XAMPP's MariaDB instead (port 3306)

If you don't have standalone MySQL Server:

1. Use **phpMyAdmin** at http://localhost/phpmyadmin instead of Workbench,
   or make a Workbench connection to `127.0.0.1:3306`, user `root`, blank password.
2. In `config/db.php`, change `$db_port` from `3307` to `3306`.
3. The SQL files work unchanged.

---

## Step 4 — Tell PHP your MySQL password

Open **`config/db.php`** and set these to match your machine:

```php
$db_host = '127.0.0.1';
$db_name = 'restaurant_stock_management';
$db_user = 'root';
$db_pass = 'YOUR_MYSQL_PASSWORD';   // <- your password, or '' for XAMPP default
$db_port = '3307';                 // <- 3306 if using XAMPP's MariaDB
```

> **Safer option:** create `config/local.php` (it is gitignored) with your
> real values and leave `db.php` with placeholders:

```php
<?php
putenv('RS_DB_PASS=your-mysql-password');
putenv('RS_ADMIN_PASS=your-admin-password');
```

---

## Step 5 — Create your admin account

The first user cannot be created by SQL because the password must be hashed
by PHP. In a command prompt run:

```bat
cd C:\xampp\htdocs\restaurant-stock-management
C:\xampp\php\php.exe setup\create_admin.php
```

It prints the username/email/password it created. You can edit the
**SETTINGS** block at the top of `setup/create_admin.php` first to choose your
own username, email and password. The script stores only a bcrypt hash.

Alternatively, just open the site and use the **Sign up** link on the login
page to create your own account — the new user is logged in immediately.

---

## Step 6 — Open the website

Visit:

```
http://localhost/restaurant-stock-management/
```

You will see the login page. Sign in, and you get your dashboard. Each user
has their **own** suppliers, ingredients, purchases and stock — nothing is
shared between accounts.

---

## Common problems

| Problem | Fix |
|---|---|
| "Database connection failed" | Check `config/db.php` — password and port are the usual causes. |
| "Unknown database 'restaurant_stock_management'" | You skipped Step 3. Run `sql/schema.sql`. |
| "Access denied for user 'root'" | Wrong MySQL password in `config/db.php`. |
| Page has no CSS | The folder must be directly inside `htdocs`. |
| Login says incorrect password | Run `setup/create_admin.php` again to reset it, or sign up a new account. |

---

Good luck with your viva! Open `README.md` for a full explanation of every
file, the ER diagram, and how to explain the transaction and password hashing.

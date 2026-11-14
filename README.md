# PrintHub: Beginner's Guide

A small online ordering website for a printing shop, built with **PHP (OOP)** and **MySQL/MariaDB**.
This guide tells you how to run it, how it is organized, and how to explain it in your defense.

---

## 1. How to run it (XAMPP)

1. Copy the whole `printhub` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin` > **Import** > choose `database/printhub.sql` > **Go**.
   (This script starts with `DROP DATABASE IF EXISTS printhub`, so it replaces your old `printhub` database.)
   If you already have data you need to keep, do not re-import that file; run `database/migration_promotion_discount_snapshot.sql` and `database/migration_custom_paper_size.sql` once each instead.
4. Open `http://localhost/printhub/`.

| Account | Email | Password |
|---|---|---|
| Admin | admin@printhub.com | admin123 |
| Customer | ghianne@test.com (or elma@, ianne@, user@) | customer123 |

**Edit your shop details** (name, address, phone, email, hours) in `includes/config.php`. They show on the front page.
If your folder is not called `printhub`, change `BASE_URL` there too.

---

## 2. What was removed and why

| Removed | Reason |
|---|---|
| `messages` table and contact form | Not needed to sell prints. Your contact details are shown on the front page instead. |
| `reviews` table and moderation | Extra pages and extra workflow, little value for a first version. |
| `services.image` column | Always `NULL`. It was never used. |
| `orders.print_type` | Duplicated the service itself ("Colored Printing" already means colored). |
| `orders.finishing` | A free-text column that was not priced. Customers can write it in the notes. |
| `orders.total_price` | It can be calculated from the items, so storing it risks wrong data (see section 5). |

**Added** (to make the design correct): `categories`, `order_items` (junction table), and `orders.promotion_id`.

---

## 3. Folder map

```
printhub/
|-- index.php          Front page (marketing)
|-- services.php       Price list
|-- register.php       Create account
|-- login.php          Log in
|-- logout.php         Log out
|-- order.php          Place an order / add an item to a pending order
|-- my_orders.php      Customer's orders (view, cancel)
|-- admin/
|   |-- index.php        Orders dashboard (change status, delete cancelled)
|   |-- services.php     Manage services (add, edit, hide, delete)
|   `-- promotions.php   Manage promotions
|-- src/               ALL THE CLASSES (namespace PrintHub)
|-- includes/
|   |-- config.php       Settings
|   |-- bootstrap.php    Starts everything (autoloader, session, error handler)
|   |-- helpers.php      Small helper functions (e(), url(), flash(), csrf...)
|   `-- header.php, footer.php
|-- database/          printhub.sql, queries.sql
|-- assets/css/        style.css
`-- uploads/           customers' files (code cannot run here)
```

**Rule to remember:** pages (`*.php` in the main folder) only handle the *request and the HTML*.
Anything that touches the database lives in a **class** inside `src/`.

---

## 4. OOP: where each concept is used

| Concept | Plain meaning | Where in this project |
|---|---|---|
| **Class / Object** | A blueprint / a thing made from it | `Service`, `Order`... A page writes `new Service()` to get an object. |
| **Encapsulation** | Hide the inside, expose only safe methods | `Database` keeps the connection `private static` and has a `private` constructor. `Account` keeps `id`, `name`, `email` private and exposes `getId()`, `getName()`. |
| **Abstraction** | A blueprint that cannot be used directly | `abstract class BaseModel` and `abstract class Account`. You cannot write `new BaseModel()`. Children must supply `table()`, `primaryKey()`, `getHomePage()`, `getRoleLabel()`. |
| **Inheritance** | A child class reuses the parent's code | `Service`, `Order`, `Promotion`, `Category`, `User` all `extends BaseModel` and get `all()`, `find()`, `delete()` for free. `Customer` and `Admin` `extend Account`. `ValidationException extends Exception`. |
| **Polymorphism** | Same method name, different behaviour | `$user->getHomePage()` returns `my_orders.php` for a `Customer` and `admin/index.php` for an `Admin`. The login page does not need an `if`. Also **method overriding**: `Order::delete()` and `Service::delete()` change `BaseModel::delete()` and call `parent::delete()`. |
| **Namespaces + autoload** | Organized class files | Every class starts with `namespace PrintHub;`. `includes/bootstrap.php` loads `src/ClassName.php` automatically. |
| **Exceptions** | Handle errors cleanly | `ValidationException` (user mistakes), `try / catch` in pages, a transaction `rollBack()` in `Order`, and a global handler in `bootstrap.php`. |

### Class diagram (text)

```
BaseModel (abstract)  ---- all(), find(), delete()
   |-- User         findByEmail(), register()
   |-- Category     findOrCreate()
   |-- Service      allActive(), featured(), save(), toggleStatus(), delete()*
   |-- Promotion    currentlyActive(), save(), toggleStatus()
   `-- Order        summaries, items(), createWithItem(), addItem(), cancel(), delete()*

Account (abstract)  ---- getId(), getName(), isAdmin(), abstract getHomePage()/getRoleLabel()
   |-- Customer
   `-- Admin        (overrides isAdmin())

Auth (static)  login(), logout(), user(), requireLogin(), requireAdmin()
Validator      required(), email(), integerBetween(), inList()...
FileUploader   save(), delete()
Database       connect()
ValidationException extends Exception        (* = overrides the parent method)
```

### Follow one request: "Place order" (`order.php`)

1. `bootstrap.php` loads settings, the autoloader and the session.
2. `Auth::requireLogin()` checks you are logged in (visitors are sent to `login.php`).
3. On POST: `csrf_check()` confirms the form came from our site.
4. `Validator` checks service, paper size, copies, notes. Errors are collected, not thrown.
5. `FileUploader::save()` checks size, extension and real file type, then saves with a random name.
6. `Order::createWithItem()` starts a **transaction**: INSERT into `orders`, INSERT into `order_items`, then `commit()`. If anything fails: `rollBack()`, so you never get half an order.
7. A success message is stored with `flash()`, and the page redirects to `my_orders.php`.

### Security checklist (for rubric item "Forms, Data Handling, Validation, Security")

- **Prepared statements** (PDO placeholders like `:email`) in every query that uses user data: stops SQL injection.
- **`e()`** (`htmlspecialchars`) on all output: stops XSS.
- **`password_hash()` / `password_verify()`**: passwords are never stored in plain text.
- **Server-side validation** in `Validator` (the HTML `required` attributes are only a convenience).
- **CSRF token** in every POST form.
- **Sessions**: `session_regenerate_id()` after login; `requireAdmin()` guards admin pages.
- **File upload rules**: 5 MB limit, allowed types only, random file name, `uploads/.htaccess` blocks code execution.
- **Private order files**: direct HTTP access to `uploads/` is denied. `download.php` checks that the signed-in user owns the order (or is an admin) before streaming the file.
- **Exceptions**: users see friendly messages; technical details only appear when `DEBUG` is `true`.

---

## 5. Database design (for the Database subject)

### Tables and relationships

```
users      1 ----< orders          (one user, many orders)
promotions 1 ----< orders          (optional: an order may use one promotion)
categories 1 ----< services        (one category, many services)

orders     1 ----< order_items >---- 1 services
                   ^ JUNCTION TABLE: resolves the many-to-many between orders and services
```

| Relationship | Cardinality | Mapped by |
|---|---|---|
| A user places many orders | 1:M | `orders.user_id` (FK) |
| A category has many services | 1:M | `services.category_id` (FK) |
| A promotion can be used on many orders (optional) | 1:M | `orders.promotion_id` (FK, nullable) |
| **An order has many services, and a service appears in many orders** | **M:N** | **junction table `order_items`** with FKs `order_id` and `service_id` |

`order_items` also holds data that belongs to the *pair* (order, service): `copies`, `paper_size`, `file_name`, `file_path`, `unit_price`.
It uses its own `order_item_id` as primary key (not a composite key) because the same service may appear twice in one order, for example two different files printed in black and white.

### Normalization

- **1NF**: every column holds one value and there are no repeating groups. Before, one order could hold only one service; now repeated services live in rows of `order_items`.
- **2NF**: every non-key column depends on the *whole* key. `order_items` columns depend on the item, not on just the order or just the service.
- **3NF**: no column depends on another non-key column.
  - `category_name` was repeated in every service. It moved to `categories`, so renaming a category is a single update (**update anomaly** prevented).
  - `total_price` was removed from `orders`: it depended on other columns (items, discount). It is calculated with `SUM(copies * unit_price)` instead.

**Why `unit_price` is stored in `order_items`:** this is a deliberate *snapshot*. If you raise a price later, old orders must keep what the customer was actually charged. (`queries.sql` #9 demonstrates this.)

**Promotion discounts are snapshotted too:** `orders.promotion_discount` records the percentage applied when the order was placed. Totals use that saved percentage, so editing the current promotion does not change prior order totals. For an existing database, run `database/migration_promotion_discount_snapshot.sql` once. The migration copies each promotion's current discount to its old orders; if a promotion was edited in the past, the earlier percentage cannot be reconstructed automatically.

| Anomaly | How it is prevented |
|---|---|
| Insertion | You can add a new category or service before anyone orders it. |
| Update | A category name or service price is stored in one row only. |
| Deletion | Deleting an order removes only its items (`ON DELETE CASCADE`). Deleting a promotion keeps the orders (`ON DELETE SET NULL`). A service that was ordered cannot be deleted (foreign key blocks it), so you set it `inactive`. |

### SQL demonstration

`database/queries.sql` has ready-to-run examples: ALTER, INSERT, SELECT with JOIN / WHERE / GROUP BY, UPDATE, DELETE (including CASCADE). Run them one at a time in phpMyAdmin and take a screenshot of each result for your documentation.

### How the database supports the OOP system

Each model class maps to a table (`Service` > `services`, `Order` > `orders` + `order_items`...). Classes use PDO prepared statements to read and write, and the same data drives the front page (services, promotions), the customer's orders, and the admin dashboard.

---

## 6. Things you may be asked (and short answers)

- **Why `abstract class BaseModel`?** So `all()`, `find()` and `delete()` are written once and reused by every model; it should never be used alone.
- **Why a junction table?** Order and service are many-to-many; a relational table cannot store a list in one cell, so `order_items` stores one row per pair.
- **Why a transaction?** Creating an order writes two tables. A transaction makes it all-or-nothing.
- **Why not store the total?** It can be derived. Stored derived data can disagree with the real data.
- **Where is validation?** `Validator` (form rules) and the model classes (business rules, such as "only pending orders can be cancelled").
- **Why `private` and `protected`?** Encapsulation: only the class itself (private) or its children (protected) can touch the data.

---

## 7. Ideas for later

Online payment, order pickup date, email notifications, customer reviews, search and filter on the admin orders page.

## 8. Troubleshooting

- **Blank page or "Something went wrong"**: with `DEBUG = true` the message says what failed. Most common: MySQL not started, or `printhub.sql` not imported.
- **Page looks unstyled / links go to 404**: `BASE_URL` in `config.php` must match your folder name.
- **File upload fails**: make sure the `uploads` folder exists next to `index.php`.
- **Cannot log in with old accounts**: the old password hashes are not included. Use the accounts in section 1.

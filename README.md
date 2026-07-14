# Anti-Counterfeit Enterprise Platform

A PHP and MySQL product authenticity, supply-chain, and business workflow system for manufacturers, retailers, customers, auditors, and administrators.

## Features

- Role-based dashboards and menus
- Product registration and QR verification
- Inventory and supply-chain tracking
- Customer ownership and warranty workflows
- Complaint, recall, audit, fraud, and reporting modules
- Enterprise messaging and approval workflows
- Activity logs, notifications, login history, and profile management

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache or Nginx
- Modern browser with JavaScript enabled

## Installation

1. Copy the project into your web server folder.

   For XAMPP:
   ```bash
   C:/xampp/htdocs/Anti-counterfeit-System-main
   ```

2. Create and import the database.

   ```bash
   mysql -u root -p < database/database.sql
   mysql -u root -p anti_counterfeit < database/enterprise_schema.sql
   mysql -u root -p anti_counterfeit < database/phase2_schema.sql
   ```

3. Configure database credentials in `config/database.php`.

   ```php
   private $host = "localhost";
   private $db_name = "anti_counterfeit";
   private $username = "root";
   private $password = "";
   ```

4. Make sure `uploads/` is writable by PHP.

5. Open the application.

   ```text
   http://localhost/Anti-counterfeit-System-main/
   ```

## Default Login

```text
Username: admin
Password: admin123
```

## Folder Structure

```text
Anti-counterfeit-System-main/
|-- account/              User profile and account management
|-- admin/                Companies, approvals, and notifications
|-- api/                  REST API endpoint
|-- assets/
|   |-- css/              Stylesheets
|   |-- images/           Project images
|   `-- js/               JavaScript
|-- auth/                 Login, logout, and registration
|-- communication/        Enterprise messages
|-- config/               Database connection
|-- customers/            Ownership, complaints, warranty, timeline
|-- dashboard/            Role-based dashboards
|-- database/             SQL schema and migrations
|-- includes/             Shared helpers, layout, enterprise logic
|-- operations/           Inventory and supply-chain operations
|-- pages/                Static informational pages
|-- products/             Products, batches, recalls, QR registration
|-- reports/              Analytics, audit, search, report exports
|-- uploads/              Uploaded profile, invoice, and complaint files
|-- verification/         Product and QR verification
|-- index.php             Public landing page
`-- README.md
```

## Key Entry Points

- Login: `auth/login.php`
- Dashboard: `dashboard/dashboard.php`
- Product registration: `products/register_product.php`
- Product verification: `verification/verify.php`
- Inventory: `operations/inventory.php`
- Complaints: `customers/complaint.php`
- Reports: `reports/business_reports.php`

## Notes

- Shared navigation uses route helpers in `includes/helpers.php`, so pages work from feature folders.
- Runtime table bootstrapping lives in `includes/enterprise.php`.
- Uploaded files are stored under the root `uploads/` directory.

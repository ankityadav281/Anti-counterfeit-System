# Anti-Counterfeit Enterprise Platform

A comprehensive PHP and MySQL product authenticity, secure supply-chain tracking, and workflow management system. The platform allows manufacturers to generate cryptographically signed QR codes for products, track their movements across warehouses, distributors, and retailers, and enable customers to securely verify product legitimacy, assert ownership, and process warranty claims.

---

## 🎭 Role Architecture & User Matrix

The system enforces a rigid role-based access matrix. Each role accesses customized dashboards and restricted modules:

| Role Name | Description | Key Modules Allowed |
| :--- | :--- | :--- |
| **Super Admin** | Full system command, workflow audit, database health | All modules, system health, companies management, API tokens |
| **Manufacturer** | Registers brands, creates batches, generates secure QRs | Product registration, batch management, recalls, supply chain logs |
| **Warehouse Manager** | Manages localized storage, logs inbound/outbound items | Inventory, inventory movements, notifications, messages |
| **Distributor** | Logistics management, tracks cargo transitions | Inventory, recalls, notifications, messaging |
| **Retailer** | Handlers sales channels, claims customer warranty transfers | Inventory updates, ownership registration, warranty approvals |
| **Auditor** | Independent observer inspecting compliance and fraud risk | Analytics, audit logs, global search, business reports |
| **Customer** | End-consumers validating items, claiming warranties | QR verification, warranty claims, complaints registry |

---

## 🛠️ Installation & Environment Configuration

### 1. Place the Directory
Copy the project folder into your web server's document root. Under standard XAMPP installations:
```bash
C:/xampp/htdocs/Anti-counterfeit-System
```

### 2. Configure Environment Variables
Establish your private variables. Duplicate `.env.example` as `.env` at the project root:
```bash
cp .env.example .env
```
Open `.env` and fill out the configuration:
```env
APP_NAME="Anti-Counterfeit Enterprise Platform"
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=Asia/Kolkata
APP_SECRET_KEY=change-this-to-a-long-random-secret

DB_HOST=localhost
DB_NAME=anti_counterfeit
DB_USER=root
DB_PASS=""
DB_CHARSET=utf8mb4

# Google OAuth Config
GOOGLE_CLIENT_ID=""
```

### 3. Database Initialization
Import the schemas sequentially using your terminal or tool (e.g., phpMyAdmin):
```bash
# 1. Base database & core schema initialization
mysql -u root -p < database/database.sql

# 2. Enterprise tracking, audit logs & inventory schema updates
mysql -u root -p anti_counterfeit < database/enterprise_schema.sql

# 3. Phase 2 company management, messages & warranty schema updates
mysql -u root -p anti_counterfeit < database/phase2_schema.sql
```

> [!NOTE]
> **Runtime Bootstrapping:** The core system employs a bootstrap function `enterprise_bootstrap($db)` in [enterprise.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/includes/enterprise.php) which verifies table structures and seeds default parameters dynamically at runtime if not initialized.

---

## 🔑 Default Login Credentials

Configure a default user for testing the administrative control panel:
*   **Username / Email:** `admin` (or `admin@example.com`)
*   **Password:** `admin123`

---

## 🛡️ Advanced Security & Cryptography Mechanisms

The system relies on cryptographic assurance to prevent product data tampering and counterfeit injection.

### 1. Cryptographic QR Verification
QR codes are not simple text payloads. They are generated via:
*   **Secure Hash:** A SHA-256 hash containing product code, manufacturer ID, and timestamp salt concatenated with the private `APP_SECRET_KEY`.
*   **Digital Signature:** A dynamic HMAC-SHA-256 digital signature of the secure hash using the `APP_SECRET_KEY`.
*   **Tamper Check:** When scanned, the verification module recomputes the HMAC-SHA-256 signature to guarantee the QR code is authentic and was generated directly by the system.

### 2. Audit Logs Hash-Chaining
To ensure log integrity against direct database edits, audit entries are chained together:
*   Every audit entry re-calculates a SHA-256 signature combining: `entity_type`, `entity_id`, `product_id`, status progression, `user_id`, and the **previous record's hash**.
*   A break in the chain indicates historical database tampering, flagged immediately on the Auditor dashboard.

### 3. Algorithmic Fraud Telemetry
QR scans undergo real-time heuristic validation in `assess_fraud()`:
*   **Velocity Check:** Scan frequency $> 100$ in 24 hours ($+35$ risk score).
*   **Geospatial Hop:** Scan locations switching cities within 30 minutes ($+40$ risk score).
*   **IP Flood:** Repeated scans from the same IP address in under a minute ($+25$ risk score).
*   High-risk scores ($>70$) trigger instant alerts in the `fraud_logs` module.

### 4. CSRF Protection
POST requests undergo form signature validation via helper functions `csrf_token()` and `csrf_field()` defined in [helpers.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/includes/helpers.php).

---

## 🌐 Unified Google Authentication Flow

The login screen supports direct integration with Google Authentication (OAuth 2.0).

```
[Google Sign-In Triggered]
         │
         ├──► Is GOOGLE_CLIENT_ID configured? ────────┐
         │                                            │
         ▼ (Yes)                                      ▼ (No)
[GSI Client Flow Initialization]             [Sandbox Simulator Modal launched]
  ├── Fetch user access token                  ├── Select profile role (Manufacturer, Customer, etc.)
  └── Send to google-login.php                 └── Send simulated payloads to google-login.php
         │                                            │
         └───────────────────────┬────────────────────┘
                                 ▼
                     [auth/google-login.php]
                       ├── Verify Access Token / Mock identity
                       ├── If new email: Auto-register user & profile
                       └── Establish session and redirect
```

### real OAuth Integration
Provide a valid credentials client ID in `.env` under `GOOGLE_CLIENT_ID`. This initializes the Google Sign-in flow in [header.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/includes/header.php) using the Google Client library (`https://accounts.google.com/gsi/client`).

### Local Auth Sandbox Simulator
If `GOOGLE_CLIENT_ID` is left empty:
*   Clicking the **Continue with Google** button will prompt the **Google Auth Sandbox Simulator** modal.
*   Developers can select predefined role-based profiles (Manufacturer, Customer, Retailer, Auditor) or enter a custom test profile.
*   The system creates mock session objects, allowing local simulation and testing of complex multi-role workflows.

---

## 📂 Directory Structure

```text
Anti-counterfeit-System/
|-- account/              # User profile, 2FA settings, and password updates
|-- admin/                # System workflows, manual approvals, and system health
|-- api/                  # REST API logic and token issuing
|-- assets/               # CSS styling, images, and Javascript handlers
|-- auth/                 # Credential logins, registration, and Google OAuth
|-- communication/        # Inter-enterprise message exchanges
|-- config/               # Database and environment initialization
|-- customers/            # Warranty registries, customer complaints, ownership records
|-- dashboard/            # Specialized role-based landing pages
|-- database/             # Raw SQL schemas and structural logs
|-- includes/             # Global helper routines and business rules
|-- operations/           # Location and stock movement controllers
|-- pages/                # Static informational templates
|-- products/             # QR code generation, batches, and recalls
|-- reports/              # Analytics, fraud logs, audit records, and reporting exports
|-- storage/              # Private application files, server logs
|-- uploads/              # Storage directory for documents and invoice receipts
|-- verification/         # Verification check interfaces
|-- .env                  # Environment-specific configuration
|-- index.php             # Core landing page
`-- README.md             # Project documentation (this file)
```

---

## 🔗 Developer Anchors & Entry Points

*   **Public Gateway & QR Verification Portal:** [index.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/index.php)
*   **Authentication & Session Management:** [auth/login.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/auth/login.php)
*   **Google OAuth Handler:** [auth/google-login.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/auth/google-login.php)
*   **Role Redirect Center:** [dashboard/dashboard.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/dashboard/dashboard.php)
*   **Core Route & Auth Helpers:** [includes/helpers.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/includes/helpers.php)
*   **Enterprise Cryptographic Logic:** [includes/enterprise.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/includes/enterprise.php)
*   **QR Scanner / Verification Target:** [verification/verify.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/verification/verify.php)
*   **Inventory Node Management:** [operations/inventory.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/operations/inventory.php)
*   **Business Intelligence / Logs:** [reports/business_reports.php](file:///c:/xampp/htdocs/Anti-counterfeit-System/reports/business_reports.php)

# Anti-Counterfeit System

A comprehensive web-based system for detecting and preventing counterfeit products using QR codes and unique product identifiers.

## Features

- Product verification through QR codes
- Unique product code generation
- Manufacturer registration and management
- Product tracking and verification history
- Mobile-responsive design
- Secure authentication system
- Real-time verification status

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser with JavaScript enabled

## Installation

1. Clone the repository to your web server directory:
   ```bash
   git clone https://github.com/yourusername/anti-counterfeit.git
   ```

2. Create a MySQL database and import the schema:
   ```bash
   mysql -u root -p < database.sql
   ```

3. Configure the database connection:
   - Open `config/database.php`
   - Update the database credentials:
     ```php
     private $host = "localhost";
     private $db_name = "anti_counterfeit";
     private $username = "your_username";
     private $password = "your_password";
     ```

4. Set up your web server:
   - Point your web server's document root to the project directory
   - Ensure PHP has write permissions for the uploads directory
   - Enable mod_rewrite if using Apache

5. Access the application:
   - Open your web browser
   - Navigate to `http://localhost/anti-counterfeit`
   - Default admin credentials:
     - Username: admin
     - Password: admin123

## Security Features

- Password hashing using bcrypt
- SQL injection prevention using prepared statements
- XSS protection
- CSRF protection
- Input validation and sanitization
- Secure session management

## Directory Structure

```
anti-counterfeit/
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── main.js
│   └── images/
├── config/
│   └── database.php
├── includes/
│   ├── auth.php
│   └── functions.php
├── index.php
├── verify.php
├── database.sql
└── README.md
```

## Usage

1. **Product Verification**
   - Scan QR code or enter product code
   - View verification results
   - Check product details and authenticity

2. **Manufacturer Portal**
   - Register new products
   - Generate unique product codes
   - Track product verifications
   - Manage product information

3. **Admin Dashboard**
   - Manage manufacturers
   - View verification statistics
   - Monitor system activity
   - Generate reports

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please email support@example.com or create an issue in the repository. 
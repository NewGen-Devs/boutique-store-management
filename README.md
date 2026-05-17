# Boutique Store Management System

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-777BB4.svg)

A comprehensive web-based management system for boutique stores, designed to manage inventory, sales transactions, multiple branches, and user roles efficiently.

##  Features

### Core Functionality
- **Modern Dynamic UI**: Professional design system featuring glassmorphism, refined typography, and smooth micro-animations.
- **Inventory Management**: Track items, stock levels, and pricing across multiple branches
- **Sales Processing**: Record sales transactions with real-time inventory updates
- **Multi-Branch Support**: Manage multiple boutique locations from a single dashboard
- **Role-Based Access Control**: Three user roles with different permission levels
- **Advanced Reporting**: Daily, weekly, monthly, and custom-range sales reports
- **Stock Transfers**: Move inventory between branches with approval workflow
- **Audit Logging**: Track all system actions for compliance and security

### User Roles
1. **Manager**: Full system control with strategic oversight
2. **Store Keeper**: Inventory operations and stock management
3. **Seller**: Sales transactions and customer interactions

##  System Requirements

### Software Prerequisites
- **PHP**: 8.0 or higher
- **MySQL**: 8.0 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Composer**: For dependency management (optional)

##  Quick Start

### 1. Clone Repository
```bash
git clone https://github.com/your-org/boutique-store-management.git
cd boutique-store-management
```

### 2. Automated Setup & Start
Run the provided development server script. This script will automatically run `composer install`, set up your `.env` file, create and seed the database, and start the development server at `http://localhost:8000`.

```bash
chmod +x serve.sh
./serve.sh
```

### 3. Manual Server Configuration (Optional)
If you prefer not to use the automated `serve.sh` script, you can manually set up your `.env`, import `database/store.sql`, and configure Apache or Nginx. See [DEVELOPER_README.md](docs/DEVELOPER_README.md) for detailed manual server configuration.

##  Project Structure

```
boutique-store-management/
├── app/
│   ├── controllers/          # Application controllers
│   ├── models/               # Data models
│   ├── views/                # View templates
│   └── core/                 # Core framework classes
├── config/                   # Configuration files
├── database/                 # Database schema and migrations
├── public/                   # Web root (index.php entry point)
│   └── assets/               # CSS, JS, images
├── routes/                   # Route definitions
├── storage/                  # Logs, cache, uploads
├── tests/                    # Test suites
├── docs/                     # Documentation
└── .env.example              # Environment configuration template
```

See [PROJECT_STRUCTURE.md](docs/PROJECT_STRUCTURE.md) for detailed breakdown

##  Architecture

This project uses a **custom MVC (Model-View-Controller) architecture** built with core PHP:

- **No external frameworks**: Everything is built from scratch for maximum control
- **Custom Router**: URL routing and dispatch system
- **Database Abstraction Layer**: MySQLi-based database operations
- **Role-Based Access Control**: Permission system for three user types
- **RESTful API**: Optional JSON API endpoints

See [ARCHITECTURE.md](docs/ARCHITECTURE.md) for detailed technical documentation

##  Documentation

- [Architecture & Design](docs/ARCHITECTURE.md) - System design and patterns
- [Developer README](docs/DEVELOPER_README.md) - Setup and development guide
- [Project Structure](docs/PROJECT_STRUCTURE.md) - Directory structure explained
- [Git Workflow](docs/GIT_WORKFLOW.md) - Version control guidelines
- [Development Roadmap](docs/ROADMAP.md) - Feature roadmap and phases

##  Security Features

- Password hashing with bcrypt (cost factor 12)
- SQL injection prevention with prepared statements
- CSRF protection
- Session-based authentication
- Rate limiting on login attempts
- User account lockout mechanism
- Comprehensive audit logging

##  API Endpoints 

```
GET    /api/v1/inventory          - List all items
GET    /api/v1/inventory/{id}     - Get specific item
POST   /api/v1/inventory          - Create new item
PUT    /api/v1/inventory/{id}     - Update item
GET    /api/v1/sales              - List sales
POST   /api/v1/sales              - Create sale
GET    /api/v1/branches           - List branches
```


##  Git Workflow

This project follows **GitFlow** branching model:
- `main` - Production-ready code
- `develop` - Integration branch
- `feature/*` - Feature branches
- `release/*` - Release branches
- `hotfix/*` - Production fixes

See [GIT_WORKFLOW.md](docs/GIT_WORKFLOW.md) for detailed guidelines

##  Testing

```bash
# Run test suite
php vendor/bin/phpunit tests/

# Run specific test
php vendor/bin/phpunit tests/Models/UserTest.php
```

##  Dependencies

- PHP 8.0+
- MySQLi (PHP extension)
- Sessions (PHP extension)

Optional:
- Composer (for package management)
- PHPUnit (for testing)

## Contributing

1. Create feature branch: `git checkout -b feature/your-feature`
2. Commit changes: `git commit -am 'Add your feature'`
3. Push to branch: `git push origin feature/your-feature`
4. Submit pull request







# by7am API

A RESTful API for the **by7am** educational platform that manages school administration, student learning, and teacher communication. Built with CodeIgniter 4, it provides secure endpoints for students, teachers, and administrators to interact with an academic management system.

## About by7am

by7am is an educational platform designed to streamline communication and collaboration between students, teachers, and school administrators. This API serves as the backbone for:

- **Student Management**: Dashboards, learning feeds, activity boards
- **Teacher Management**: Class allocations, updates, student directory, engagement tracking
- **School Administration**: Profile management, enrollment workflows, system monitoring
- **Secure Authentication**: JWT-based stateless authentication for mobile and web clients

## Key Features

- **🔐 JWT Authentication**: Secure token-based authentication with stateless validation
- **📊 Student Dashboard**: Multi-school profile management with academic tracking
- **👨‍🏫 Teacher Management**: Subject allocations, class assignments, update creation
- **📱 Activity Feeds**: Real-time updates with engagement metrics
- **🛡️ Security-First**: BOLA/IDOR protection, role-based access control, token validation
- **🗄️ Stored Procedures**: Optimized database queries using MySQL stored procedures
- **✅ Fully Tested**: Comprehensive PHPUnit test suite with code coverage
- **🚀 Scalable**: RESTful API design with versioning (v1) for future extensibility

## Tech Stack

- **Framework**: CodeIgniter 4.7
- **Language**: PHP 8.2+
- **Authentication**: Firebase PHP-JWT 7.0
- **Query Language**: MySQL with Stored Procedures
- **Testing**: PHPUnit 10.5+
- **Development Utilities**: Faker, VFSStream

## Quick Start

### Prerequisites

- **PHP** 8.2 or higher
- **Composer** (dependency manager)
- **Git**

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/BigNotify-Global/api.by7am.com.git
   cd api.by7am.com
   ```

2. **Install dependencies**:
   ```bash
   composer install
   ```

3. **Configure environment**:
   ```bash
   cp env .env
   ```
   Edit `.env` and configure your environment variables (see [Configuration](#configuration) section).

4. **Start the development server**:
   ```bash
   php spark serve
   ```

   The API will be available at `http://localhost:8080`

5. **Run migrations** (requires database setup):
   ```bash
   php spark migrate
   ```

## API Endpoints

### Base URL
```
http://localhost:8080/v1
```

### Authentication
All endpoints require JWT authentication via the `Authorization` header.

### Admin Endpoints

| Method | Endpoint      | Description | Auth Required |
| ------ | ------------- | ----------- | ------------- |
| POST   | `/admin/demo` | Admin demo  | ✅ Yes         |

### Profile Endpoints

| Method | Endpoint                 | Description                              | Auth Required |
| ------ | ------------------------ | ---------------------------------------- | ------------- |
| POST   | `/profile/addAccount`    | Sync Firebase account and mint JWT token | ❌ No          |
| POST   | `/profile/createProfile` | Create new student/teacher profile       | ✅ Yes         |

### Student Endpoints

| Method | Endpoint             | Description                        | Query/Body Parameters               | Auth Required |
| ------ | -------------------- | ---------------------------------- | ----------------------------------- | ------------- |
| GET    | `/student/dashboard` | Student dashboard with all schools | —                                   | ✅ Yes         |
| GET    | `/student/board`     | Class teacher and subject boards   | `profileId` (required)              | ✅ Yes         |
| GET    | `/student/feed`      | Subject-specific activity feed     | `profileId`, `sectionId` (required) | ✅ Yes         |

### Teacher Endpoints

| Method | Endpoint                       | Description                         | Query/Body Parameters                     | Auth Required |
| ------ | ------------------------------ | ----------------------------------- | ----------------------------------------- | ------------- |
| GET    | `/teacher/dashboard`           | Teacher dashboard with allocations  | —                                         | ✅ Yes         |
| GET    | `/teacher/feed`                | Section/subject specific updates    | `profileId`, `sectionId`, `subjectId`     | ✅ Yes         |
| POST   | `/teacher/createUpdate`        | Create academic update              | `sectionId`, `subjectId`, `text`, etc.    | ✅ Yes         |
| GET    | `/teacher/classes/students`    | Student directory for a section     | `sectionId` (required)                    | ✅ Yes         |
| GET    | `/teacher/classes/approvals`   | Pending student enrollment requests | `sectionId` (required)                    | ✅ Yes         |
| POST   | `/teacher/classes/enrollments` | Approve/reject student enrollment   | `studentProfileId`, `sectionId`, `status` | ✅ Yes         |

## Authentication

### JWT Token Flow

1. **Account Sync**: Send Firebase credentials to `/profile/addAccount`
2. **Token Generation**: Server creates JWT with `accountId` and other claims
3. **Token Usage**: Include token in Authorization header for all authenticated requests:
   ```http
   Authorization: Bearer <token>
   ```

### JWT Claims

```json
{
  "iss": "api.by7am.com",
  "aud": "app.by7am.com",
  "iat": 1234567890,
  "exp": 1234654290,
  "sub": "firebase_uid",
  "accountId": 123
}
```

### Security Features

- **Stateless Authentication**: No session storage required; JWT is self-contained
- **Token Validation**: JwtAuthFilter validates signature and expiration on every request
- **BOLA Protection**: Endpoints verify JWT accountId owns requested profiles
- **IDOR Prevention**: Profile ownership checks prevent unauthorized access

## Project Structure

```
api.by7am.com/
├── app/
│   ├── Controllers/
│   │   ├── BaseController.php      # Base controller for all routes
│   │   ├── Home.php                # Welcome route
│   │   └── Api/
│   │       ├── Admin.php           # Admin endpoints
│   │       ├── Profile.php         # Profile management & JWT minting
│   │       ├── Student.php         # Student dashboards and feeds
│   │       └── Teacher.php         # Teacher dashboards, updates, approvals
│   │
│   ├── Config/
│   │   ├── App.php                 # Application configuration
│   │   ├── Database.php            # Database connection settings
│   │   ├── Routes.php              # API routes definition
│   │   ├── Filters.php             # Filter configuration (JWT)
│   │   └── ...                     # Other CodeIgniter configs
│   │
│   ├── Filters/
│   │   └── JwtAuthFilter.php       # JWT validation and token extraction
│   │
│   ├── Database/
│   │   ├── Migrations/
│   │   │   └── 2026-03-06-060306_CreateStoredProcedures.php
│   │   └── Seeds/                  # Database seeders
│   │
│   ├── Models/                     # Eloquent-style models (if used)
│   ├── Views/                      # HTML views/templates
│   └── Common.php                  # Shared helper functions
│
├── public/
│   ├── index.php                   # Application entry point
│   └── robots.txt                  # SEO configuration
│
├── tests/
│   ├── unit/                       # Unit tests
│   ├── database/                   # Database tests
│   ├── session/                    # Session tests
│   └── _support/                   # Test fixtures and helpers
│
├── vendor/                         # Composer dependencies
├── writable/                       # Logs, cache, session storage
├── composer.json                   # Project dependencies
├── phpunit.xml.dist               # PHPUnit configuration
├── env                            # Environment template
└── README.md                       # This file
```

## Configuration

### Environment Variables (.env)

Key variables to configure:

```env
# Application
CI_ENVIRONMENT = development
app.baseURL = http://localhost:8080

# JWT Secret Key
JWT_SECRET = your-secret-key-here

# CORS Settings
CORS_allowedOrigins = http://localhost:3000,http://localhost:8000
```

### Configuration Files

- **[app/Config/App.php](app/Config/App.php)**: Base URL, encryption, debug settings
- **[app/Config/Routes.php](app/Config/Routes.php)**: API route definitions
- **[app/Config/Filters.php](app/Config/Filters.php)**: JWT authentication filter binding
- **[app/Filters/JwtAuthFilter.php](app/Filters/JwtAuthFilter.php)**: Token validation logic

## Testing

### Run All Tests

```bash
composer test
```

Or with PHPUnit directly:

```bash
php vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Unit tests only
php vendor/bin/phpunit tests/unit

# Database tests
php vendor/bin/phpunit tests/database

# Specific test file
php vendor/bin/phpunit tests/unit/Api/StudentTest.php
```

### Generate Code Coverage Report

```bash
php vendor/bin/phpunit --coverage-html build/logs/html
```

Open `build/logs/html/index.html` to view coverage.

## Development

### Project Workflow

1. **Create a new controller** in `app/Controllers/Api/`
2. **Define routes** in `app/Config/Routes.php`
3. **Apply JWT filter** to protected routes
4. **Write stored procedures** if needed for complex queries
5. **Add tests** in `tests/unit/Api/` or `tests/database/`

### Code Style

- Follow PSR-12 coding standards
- Use type hints for all parameters and return types
- Add PHPDoc comments to public methods
- Use descriptive variable and method names

### Database Queries

This API uses **MySQL stored procedures** for optimized queries:

```php
$db = \Config\Database::connect();
$results = $db->query(
    "CALL sp_GetStudentDashboard(?)",
    [$accountId]
)->getResultArray();
```

Stored procedures are defined in the migration file: `app/Database/Migrations/2026-03-06-060306_CreateStoredProcedures.php`

### Error Handling

Responses follow standard HTTP status codes:

- **200 OK**: Request succeeded
- **400 Bad Request**: Invalid input
- **401 Unauthorized**: Missing or invalid JWT token
- **403 Forbidden**: Authenticated but not authorized for resource
- **404 Not Found**: Resource doesn't exist
- **500 Internal Server Error**: Server error

Error response format:
```json
{
  "status": 400,
  "error": "validation_error",
  "messages": {
    "field": "error message"
  }
}
```

## Security Considerations

- ✅ **JWT Validation**: All requests validated with signature verification
- ✅ **BOLA Protection**: Profile ownership verified before returning data
- ✅ **IDOR Prevention**: Account ID extracted from JWT, not user input
- ✅ **Rate Limiting**: (Can be implemented via middleware)
- ✅ **CORS Configuration**: Whitelist approved origins
- ✅ **HTTPS Enforcement**: (Recommended for production)

## Troubleshooting

### API returns 401 Unauthorized

- Verify JWT token is included in `Authorization: Bearer <token>` header
- Check token hasn't expired
- Confirm JWT_SECRET in `.env` matches server configuration

### 404 errors on endpoints

- Verify route is defined in `app/Config/Routes.php`
- Check correct HTTP method (GET, POST, etc.)
- Ensure controller and method names are correct

### Stored procedure errors

- Verify database migrations have been run: `php spark migrate`
- Check stored procedures exist: `SHOW PROCEDURES;` in MySQL
- Review error logs in `writable/logs/`

## Performance Tips

- Use stored procedures for complex queries
- Enable query caching in production
- Monitor code coverage and test execution time
- Use indexes on frequently queried columns
- Enable HTTP caching headers for GET endpoints

## Resources

- **[CodeIgniter 4 Documentation](https://codeigniter.com/user_guide/)** - Framework reference
- **[Firebase PHP-JWT](https://github.com/firebase/php-jwt)** - JWT library documentation
- **[PHPUnit Documentation](https://phpunit.de/documentation.html)** - Testing framework
- **[Testing Guide](tests/README.md)** - Project-specific testing documentation

## Contributing

We welcome contributions! To contribute:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Commit changes: `git commit -m 'Add your feature'`
4. Push branch: `git push origin feature/your-feature`
5. Open a Pull Request

Please ensure:
- All tests pass
- Code follows PSR-12 standards
- New endpoints include tests
- Code coverage doesn't decrease

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support

For questions, bug reports, or feature requests:

- 📧 Create an issue on [GitHub](https://github.com/BigNotify-Global/api.by7am.com)
- 💬 Join the [CodeIgniter Forum](https://forum.codeigniter.com/)
- 📖 Check [Testing Guide](tests/README.md) for testing help

## Authors

- **BigNotify Global** - Project initiator and maintainer

---

**Version**: 1.0.0 | **Last Updated**: March 2026
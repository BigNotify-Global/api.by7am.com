# by7am API

A robust RESTful API for the by7am educational platform built with CodeIgniter 4. This API provides endpoints for students and teachers to access dashboards, feeds, class information, and approval workflows within an academic management system.

## Features

- **JWT Authentication**: Secure token-based authentication for all endpoints
- **Student Endpoints**: Access to dashboards, activity boards, and personalized feeds
- **Teacher Endpoints**: Dashboard, feed management, student directory, and approval workflows
- **RESTful Design**: Clean, organized API structure with versioning (v1)
- **Database-Driven**: MySQL database integration with migration support
- **Testing**: PHPUnit test suite for reliability and maintainability
- **Scalable Architecture**: Built with CodeIgniter 4 framework for performance and scalability

## Tech Stack

- **Framework**: CodeIgniter 4.7
- **Language**: PHP 8.2+
- **Database**: MySQL
- **Authentication**: Firebase PHP-JWT
- **Testing**: PHPUnit

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL 8.0 or higher
- Git

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/BigNotify-Global/api.by7am.com.git
   cd api.by7am.com
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Configure environment variables:
   ```bash
   cp env .env
   ```
   Edit `.env` and set your database credentials and other configuration values.

4. Generate application key (if needed):
   ```bash
   php spark key:generate
   ```

5. Run database migrations:
   ```bash
   php spark migrate
   ```

6. Start the development server:
   ```bash
   php spark serve
   ```

The API will be available at `http://localhost:8080`

## API Endpoints

### Base URL
```
http://localhost:8080/v1
```

### Student Endpoints

| Method | Endpoint             | Description           | Payload                                 |
| ------ | -------------------- | --------------------- | --------------------------------------- |
| POST   | `/student/dashboard` | Get student dashboard | `{ "id": "142" }`                       |
| POST   | `/student/board`     | Get student board     | `{ "profileId": 123 }`                  |
| POST   | `/student/feed`      | Get student feed      | `{ "profileId": 123, "sectionId": 45 }` |

### Teacher Endpoints

| Method | Endpoint                     | Description           | Payload                               |
| ------ | ---------------------------- | --------------------- | ------------------------------------- |
| POST   | `/teacher/dashboard`         | Get teacher dashboard | `{ "profileId": 123 }`                |
| POST   | `/teacher/feed`              | Get teacher feed      | `{ "sectionId": 25, "subjectId": 5 }` |
| POST   | `/teacher/classes/students`  | Get student directory | `{ "sectionId": 25 }`                 |
| POST   | `/teacher/classes/approvals` | Get pending approvals | `{ "sectionId": 25 }`                 |

## Authentication

All API endpoints require JWT authentication. Include the token in the request header:

```
Authorization: Bearer <your_jwt_token>
```

The API validates the token and returns a 401 Unauthorized response if the token is missing or invalid.

## Project Structure

```
app/
├── Controllers/          # API controllers
│   ├── Api/
│   │   ├── Admin.php
│   │   ├── Student.php
│   │   └── Teacher.php
│   └── BaseController.php
├── Models/              # Database models
├── Filters/             # Authentication filters (JWT)
├── Database/            # Migrations and seeds
│   ├── Migrations/
│   └── Seeds/
├── Config/              # Application configuration
└── Views/               # View templates

public/                  # Web root
tests/                   # Test suite
vendor/                  # Composer dependencies
```

## Database Setup

Database migrations are located in `app/Database/Migrations/`. Run migrations with:

```bash
php spark migrate
```

To rollback migrations:

```bash
php spark migrate:rollback
```

## Testing

Run the test suite using:

```bash
composer test
```

Or directly with PHPUnit:

```bash
php vendor/bin/phpunit
```

Tests are located in the `tests/` directory.

## Configuration

Key configuration files:

- `app/Config/App.php` - Application settings
- `app/Config/Database.php` - Database configuration
- `app/Config/Routes.php` - API routes definition
- `.env` - Environment variables (create from `env` file)

## Development

### Adding New Endpoints

1. Create a controller in `app/Controllers/Api/`
2. Define routes in `app/Config/Routes.php`
3. Implement JWT authentication in the controller or apply the JwtAuthFilter
4. Create corresponding models and migrations
5. Write tests in `tests/`

### Database Migrations

Create a new migration:

```bash
php spark make:migration CreateTableName
```

## Troubleshooting

- **Database connection error**: Check your `.env` file and ensure MySQL is running
- **JWT validation fails**: Verify your token format and ensure it's valid
- **404 errors**: Check route definitions in `app/Config/Routes.php`

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For issues and questions, please create an issue on the [GitHub repository](https://github.com/BigNotify-Global/api.by7am.com).

## Author

BigNotify Global
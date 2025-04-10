## marker-service

This application provides REST API endpoints for converting PDF and other documents to paginated and chunked Markdown.

The service takes a URL to a document as input and returns a document ID which can be used as a parameter for polling the conversion status and retrieving the converted document when it has been processed.

### Development

Start the service:

```
docker-compose up -d
```

Run database migrations:

```
docker compose exec app php artisan migrate
```

### Rest API documentation

API documentation is automatically generated and published at `/docs/api`.

#### Other common commands

Reset the database (delete tables and run migrations again)

```
docker compose exec app php artisan migrate:refresh
```

### Configuration

#### Data persistence
Document conversion results can be pruned after a configurable amount of time.

## Tech stack

- PHP (php-fpm)
- Laravel framework
- MariaDB
- Nginx
- [Marker](https://github.com/VikParuchuri/marker)

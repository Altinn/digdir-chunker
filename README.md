# digdir-chunker

*Work in progress. Not yet working «out of the box».*

This application provides REST API endpoints for converting PDF and other documents to paginated and chunked Markdown.

The service takes a URL to a document as input and returns a document ID which can be used as a parameter for polling the conversion status and retrieving the converted and chunked document when it has been processed.

## Development

Start the application and required services:

```
docker-compose up -d
```

Run database migrations:

```
docker compose exec app php artisan migrate
```

The application should now be up and running.


## Common commands

Restart the queue (required after code changes that affect jobs)

```
docker compose exec app php artisan queue:restart
```

Reset the database (delete tables and run migrations again)

```
docker compose exec app php artisan migrate:refresh
```

List all available commands:

```
docker compose exec app php artisan list
```

## Rest API documentation

API documentation is automatically generated and published at `/docs/api`.


## Configuration

### Data persistence

@todo Document conversion results can be pruned after a configurable amount of time.

## Maintenance

Dependencies should be regularly updated by running:
```
composer update
yarn upgrade
```

and committing changes in `composer.json` and `yarn.json`.

## Tech stack

- PHP (php-fpm)
- Laravel framework
- MariaDB
- Nginx
- [Marker](https://github.com/VikParuchuri/marker)

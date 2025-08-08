# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel-based document chunking service that provides JSON REST API and MCP tools for converting PDF and other documents to paginated and chunked Markdown. It uses the Marker library for PDF conversion and offers semantic chunking capabilities with configurable parameters.

## Development Commands

### Docker Setup
```bash
# Start services
docker-compose up -d

# Install dependencies
docker compose exec app composer install

# Generate application key
docker compose exec app php artisan key:generate

# Run migrations
docker compose exec app php artisan migrate

# Reset database with seed data
docker compose exec app php artisan migrate:refresh --seed
```

### Development Workflow
```bash
# Start development environment
docker compose up -d

# Restart queue after code changes
docker compose exec app php artisan queue:restart

# List all available artisan commands
docker compose exec app php artisan list
```

### Testing
```bash
# Run tests with Pest
docker compose exec app php artisan test
# or
docker compose exec app vendor/bin/pest

# Run specific test
docker compose exec app php artisan test --filter=ExampleTest
```

### Code Quality
```bash
# Run Laravel Pint for code formatting
docker compose exec app vendor/bin/pint

# Update dependencies
composer update
```

## Architecture

### Core Components

**Models & Data Flow:**
- `File`: Represents uploaded documents with metadata and conversion status
- `Task`: Tracks processing jobs with configurable backends (marker) and chunking methods (semantic)
- `Chunk`: Individual document segments with configurable size/overlap
- `ChunkDerivative`: Generated variations of chunks (summaries, etc.)
- `Embedding`: Vector embeddings for chunks using Ollama

**Job Pipeline:**
1. `ConvertFileToMarkdown`: Converts documents using Marker backend
2. `ChunkFile`: Splits documents using semantic chunking
3. `GenerateChunkDerivatives`: Creates chunk variations (configurable via env)
4. `GenerateEmbeddings`: Generates embeddings (configurable via env)

**API Structure:**
- REST API endpoints in `app/Http/Controllers/`
- API documentation auto-generated at `/docs/api` using Scramble
- MCP server available at `/mcp` (HTTP transport) or via stdio

### Configuration

Key environment variables:
- `TASKS_DEFAULT_CONVERSION_BACKEND`: Document conversion method (currently 'marker')
- `TASKS_DEFAULT_CHUNKING_METHOD`: Chunking strategy (currently 'semantic')
- `TASKS_DEFAULT_CHUNK_SIZE`: Target chunk length (default: 1024)
- `TASKS_DEFAULT_CHUNK_OVERLAP`: Overlap between chunks (default: 256)
- `OLLAMA_URL`, `OLLAMA_EMBEDDINGS_MODEL`, `OLLAMA_QUERY_MODEL`: AI model configuration

### Services & Utilities

- `ChunkerService`: Core chunking logic
- `DocumentService`: MCP integration for document operations
- Queue system uses database driver for job processing
- Scout/Meilisearch integration for search capabilities

## Database

Uses MariaDB with migrations in `database/migrations/`. Key tables follow the models above. The service supports both SQLite (for development) and MariaDB (for production).

## MCP Integration

Supports both HTTP (`/mcp`) and stdio transports for MCP server functionality. The MCP service provides document processing capabilities for AI assistants.
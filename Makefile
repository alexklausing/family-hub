# Family Hub Management
# Adapted from Camp-Curated Doctrine

SAIL=./vendor/bin/sail
APP_SERVICE=laravel.test

# Use standard Docker Compose without forcing a specific context that might break
SAIL_CMD=docker compose exec $(APP_SERVICE)

# --- Main Commands ---

# Build and start the environment (Fresh Install)
buildFresh: down
	@echo "🚀 Building Docker images..."
	$(SAIL) build --no-cache
	@echo "🔥 Starting Containers..."
	$(SAIL) up -d
	@echo "Waiting for environment to initialize..."
	@sleep 10
	@echo "📦 Installing Local Dependencies (Composer & Node)..."
	$(SAIL_CMD) composer install
	$(SAIL_CMD) npm install
	@echo "🌱 Migrating..."
	$(SAIL_CMD) php artisan migrate:fresh
	@echo "✅ Setup Complete! Run 'make vite' to start the frontend."

# Start containers
up:
	$(SAIL) up -d

# Stop and remove containers
down:
	$(SAIL) stop

# --- Development Helpers ---

# Run Vite (direct via local npm)
vite:
	@echo "🧹 Cleaning up port 5173..."
	-@lsof -i tcp:5173 -sTCP:LISTEN -t | xargs kill -9 || true
	@echo "🚀 Starting Vite locally..."
	npm run dev

# Access PHP container shell
ssh:
	$(SAIL_CMD) bash

# --- Testing & QA ---

# Run all tests (PHP & JS)
test:
	@echo "🧪 Running Pest (PHP)..."
	$(SAIL_CMD) php ./vendor/bin/pest
	@echo "🧪 Running Vitest (JS)..."
	npm run test

# Run Static Analysis (PHPStan)
stan:
	@echo "🧐 Running PHPStan..."
	$(SAIL_CMD) php ./vendor/bin/phpstan analyse

# Run Linting (Pint)
lint:
	@echo "🧹 Running Pint (PHP Lint)..."
	$(SAIL_CMD) php ./vendor/bin/pint --test

# Run Formatting (Pint & Prettier)
format:
	@echo "🎨 Formatting PHP (Pint)..."
	$(SAIL_CMD) php ./vendor/bin/pint
	@echo "🎨 Formatting JS/Vue (Prettier)..."
	npx prettier --write .

# Run Codebase Health Check (Fallow)
fallow:
	@echo "🌾 Running Fallow..."
	npx fallow

# Run full Quality Check
check: test stan lint fallow
	@echo "✅ Quality Check Passed!"

# --- Utilities ---

# Run Artisan commands
# Usage: make art c="migrate"
art:
	$(SAIL_CMD) php artisan $(c)

# Clear all caches
refresh:
	$(SAIL_CMD) php artisan optimize:clear
	$(SAIL_CMD) php artisan config:clear
	$(SAIL_CMD) php artisan view:clear
	$(SAIL_CMD) php artisan route:clear
	$(SAIL_CMD) php artisan cache:clear

# Hard reset (Caches + Database)
reset:
	$(SAIL_CMD) bash -c "php artisan optimize:clear && php artisan migrate:fresh"
	> storage/logs/laravel.log
	@echo "✅ Reset complete."

# Access Tinker
tinker:
	$(SAIL_CMD) php artisan tinker

# Tail logs
log:
	$(SAIL_CMD) tail -f storage/logs/laravel.log

.PHONY: buildFresh up down vite ssh test art refresh reset tinker log

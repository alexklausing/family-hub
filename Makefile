# Family Hub Management

SAIL=./vendor/bin/sail
APP_SERVICE=laravel.test
SAIL_CMD=$(SAIL)

# --- Main Commands ---

# Build and start the environment (Fresh Install)
buildFresh: down
	@echo "🚀 Building Docker images..."
	$(SAIL_CMD) build --no-cache
	@echo "🔥 Starting Containers..."
	$(SAIL_CMD) up -d
	@echo "Waiting for environment to initialize..."
	@sleep 10
	@echo "📦 Installing Local Dependencies (Composer & Node)..."
	$(SAIL_CMD) composer install
	npm install
	@echo "🌱 Migrating..."
	$(SAIL_CMD) artisan migrate
	@echo "🔗 Linking storage..."
	$(SAIL_CMD) artisan storage:link
	@echo "✅ Setup Complete! Run 'make vite' to start the frontend."

# Start containers
up:
	$(SAIL_CMD) up -d

# Stop and remove containers
down:
	$(SAIL) stop

# --- Development Helpers ---

# Run Migrations
migrate:
	$(SAIL_CMD) artisan migrate

# Run Vite (direct via local npm)
vite:
	@echo "🧹 Cleaning up port 5173..."
	-@lsof -i tcp:5173 -sTCP:LISTEN -t | xargs kill -9 || true
	@echo "🚀 Starting Vite locally..."
	npm run dev

# Access PHP container shell
ssh:
	$(SAIL_CMD) shell

# --- Testing & QA ---

# Run all tests (PHP & JS)
test:
	@echo "🧪 Running Pest (PHP)..."
	$(SAIL_CMD) pest
	@echo "🧪 Running Vitest (JS)..."
	npm run vitest

# Run Static Analysis (PHPStan)
stan:
	@echo "🧐 Running PHPStan..."
	$(SAIL_CMD) phpstan analyse

# Run Linting (Pint)
lint:
	@echo "🧹 Running Pint (PHP Lint)..."
	$(SAIL_CMD) pint --test

# Run Formatting (Pint & Prettier)
format:
	@echo "🎨 Formatting PHP (Pint)..."
	$(SAIL_CMD) pint
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
	$(SAIL_CMD) artisan $(c)

# Clear all caches
refresh:
	$(SAIL_CMD) artisan optimize:clear
	$(SAIL_CMD) artisan config:clear
	$(SAIL_CMD) artisan view:clear
	$(SAIL_CMD) artisan route:clear
	$(SAIL_CMD) artisan cache:clear

# Hard reset (Caches + Database)
reset:
	$(SAIL_CMD) artisan optimize:clear
	$(SAIL_CMD) artisan migrate:fresh
	> storage/logs/laravel.log
	@echo "✅ Reset complete."

# Seed the database
seed:
	$(SAIL_CMD) artisan db:seed
	@echo "✅ Seeding complete."

# Access Tinker
tinker:
	$(SAIL_CMD) artisan tinker

# Tail logs
log:
	$(SAIL_CMD) tail -f storage/logs/laravel.log

# Diagnose Docker/Environment issues
diagnose:
	@echo "🐳 Checking Docker status..."
	@docker info > /dev/null 2>&1 || (echo "❌ Docker daemon is not responding. Please restart Docker Desktop." && exit 1)
	@echo "✅ Docker daemon is responsive."
	@echo "🔍 Checking Docker Context..."
	@docker context ls
	@echo "📂 Checking Docker Socket..."
	@ls -la /var/run/docker.sock ~/.docker/run/docker.sock 2>/dev/null || echo "⚠️  Standard Docker sockets not found in typical locations."
	@echo "🏗️  Checking Sail status..."
	@if [ -f "./vendor/bin/sail" ]; then echo "✅ Sail is installed."; else echo "❌ Sail is missing! Run 'composer install' locally."; fi

.PHONY: buildFresh up down vite ssh test art refresh reset tinker log migrate diagnose seed


cli:
	agy
	

# Pull latest changes and sync the environment
deploy:
	@echo "⬇️ Pulling latest code from GitHub..."
	git pull origin main
	@echo "📦 Syncing PHP dependencies..."
	$(SAIL_CMD) composer install --no-interaction --prefer-dist --optimize-autoloader
	@echo "📦 Syncing Node dependencies..."
	npm install
	@echo "🌱 Running database migrations..."
	$(SAIL_CMD) artisan migrate --force
	@echo "🧹 Flushing application caches..."
	$(SAIL_CMD) artisan optimize:clear
	@echo "🔗 Linking storage..."
	$(SAIL_CMD) artisan storage:link
	@echo "✅ Deployment complete! Hit F5 on the kiosk to refresh."
SAIL := ./vendor/bin/sail

.PHONY: up down restart logs shell fresh migrate test build dev rebuild-static apache apache-down

up:              ## Start app, MySQL, Mailpit, queue and scheduler
	$(SAIL) up -d

down:            ## Stop all containers
	$(SAIL) down

restart: down up

logs:            ## Tail container logs
	$(SAIL) logs -f

shell:           ## Open a shell in the app container
	$(SAIL) shell

migrate:
	$(SAIL) artisan migrate

fresh:           ## Drop all tables, migrate and seed
	$(SAIL) artisan migrate:fresh --seed

test:            ## Run the test suite
	$(SAIL) artisan test

build:           ## Production asset build
	$(SAIL) npm run build

dev:             ## Vite dev server with hot reload
	$(SAIL) npm run dev

rebuild-static:  ## Rebuild the static HTML cache now (public/static)
	$(SAIL) artisan static:build --now

apache:          ## Start the cPanel-like Apache container on :8080
	docker compose --profile apache up -d apache

apache-down:
	docker compose --profile apache stop apache

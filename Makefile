SHELL := /bin/bash

.PHONY: up down build setup test tests logs clean pytest teststack lint phpcs phpcbf phpstan php-compat validate security check-all

# ============================================================================
# Linting and Static Analysis
# ============================================================================

lint: phpcs phpstan
	@echo "All linting checks passed!"

# Run ALL checks (like CI does)
check-all: lint php-compat validate security check-txt
	@echo ""
	@echo "🎉 ALL CHECKS PASSED!"

phpcs:
	@echo "Running PHP CodeSniffer..."
	@if [ -x "$$HOME/.composer/vendor/bin/phpcs" ]; then \
		$$HOME/.composer/vendor/bin/phpcs; \
	elif command -v phpcs &> /dev/null; then \
		phpcs; \
	else \
		echo "phpcs not installed. Install with:"; \
		echo "  composer global require wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer"; \
	fi

phpcbf:
	@echo "Auto-fixing PHP CodeSniffer issues..."
	@if [ -x "$$HOME/.composer/vendor/bin/phpcbf" ]; then \
		$$HOME/.composer/vendor/bin/phpcbf || true; \
	elif command -v phpcbf &> /dev/null; then \
		phpcbf || true; \
	else \
		echo "phpcbf not installed. Install with:"; \
		echo "  composer global require wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer"; \
	fi

phpstan:
	@echo "Running PHPStan..."
	@if command -v phpstan &> /dev/null; then \
		phpstan analyse --no-progress --memory-limit=512M; \
	else \
		echo "phpstan not installed. Install with: composer global require phpstan/phpstan"; \
	fi

check-txt:
	@echo "Checking for escaped quotes in text files..."
	@if grep -r '\\\"' *.txt 2>/dev/null; then \
		echo "ERROR: Found escaped quotes in text files!"; exit 1; \
	else \
		echo "✅ No escaped quotes found in text files."; \
	fi

php-compat:
	@echo "Checking PHP compatibility..."
	@declared=$$(grep -i "^Requires PHP:" readme.txt | sed 's/.*: *//'); \
	echo "Declared minimum PHP: $$declared"; \
	if [ -x "$$HOME/.composer/vendor/bin/phpcs" ]; then \
		$$HOME/.composer/vendor/bin/phpcs --standard=PHPCompatibility \
			--runtime-set testVersion $$declared \
			--extensions=php \
			--ignore=tests/,vendor/ \
			. && echo "✅ Code is compatible with PHP $$declared+"; \
	else \
		echo "PHPCompatibility not installed. Install with:"; \
		echo "  composer global require phpcompatibility/php-compatibility"; \
	fi

validate:
	@echo "Validating plugin headers..."
	@readme_stable=$$(grep -i "^Stable tag:" readme.txt | sed 's/.*: *//'); \
	readme_wp=$$(grep -i "^Requires at least:" readme.txt | sed 's/.*: *//'); \
	readme_php=$$(grep -i "^Requires PHP:" readme.txt | sed 's/.*: *//'); \
	plugin_version=$$(grep -i "^ \* Version:" abbreviations.php | sed 's/.*: *//'); \
	plugin_wp=$$(grep -i "^ \* Requires at least:" abbreviations.php | sed 's/.*: *//'); \
	plugin_php=$$(grep -i "^ \* Requires PHP:" abbreviations.php | sed 's/.*: *//'); \
	errors=0; \
	echo "📦 Stable tag: $$readme_stable vs $$plugin_version"; \
	echo "🐘 PHP version: $$readme_php vs $$plugin_php"; \
	echo "📰 WP version: $$readme_wp vs $$plugin_wp"; \
	if [ "$$readme_stable" != "$$plugin_version" ]; then \
		echo "❌ Version mismatch!"; errors=1; \
	fi; \
	if [ "$$readme_php" != "$$plugin_php" ]; then \
		echo "❌ PHP version mismatch!"; errors=1; \
	fi; \
	if [ "$$readme_wp" != "$$plugin_wp" ]; then \
		echo "❌ WordPress version mismatch!"; errors=1; \
	fi; \
	if [ $$errors -eq 0 ]; then \
		echo "✅ All headers match!"; \
	else \
		exit 1; \
	fi

security:
	@echo "Running security checks..."
	@errors=0; \
	if grep -rn '\beval\s*(' *.php 2>/dev/null; then \
		echo "❌ Found eval() usage!"; errors=1; \
	fi; \
	if grep -rn '\bcreate_function\s*(' *.php 2>/dev/null; then \
		echo "❌ Found create_function()!"; errors=1; \
	fi; \
	if [ $$errors -eq 0 ]; then \
		echo "✅ No critical security issues found"; \
	else \
		exit 1; \
	fi

# ============================================================================
# Docker / Testing
# ============================================================================

build:
	cd tests && docker compose build --pull --no-cache

up:
	cd tests && docker compose up -d db wordpress

down:
	cd tests && docker compose down -v

setup:
	bash tests/setup.sh

test:
	# Legacy shell test kept for compatibility; prefer `make tests`
	@echo "Use 'make tests' (pytest) instead."

tests:
	# Ensure stack is up and WP is initialized before tests
	$(MAKE) up && \
	cd tests && bash setup.sh && \
	docker compose run --rm tester -v

pytest:
	cd tests && docker compose run --rm tester -q

teststack:
	# Use test-local docker-compose.yml under tests
	cd tests && docker compose up -d && \
	bash setup.sh && \
	docker compose run --rm tester -q && \
	docker compose down -v

.PHONY: ci-local
ci-local:
	# Emulate CI workflow locally
	cd tests && docker compose down -v || true
	cd tests && docker compose up -d db wordpress
	# Wait for wordpress health
	bash -lc 'cd tests; for i in {1..120}; do WP=$$(docker inspect -f {{.State.Health.Status}} $$(docker compose ps -q wordpress) || true); echo wordpress=$$WP; if [ "$$WP" = healthy ]; then break; fi; sleep 2; done'
	cd tests && bash setup.sh
	cd tests && docker compose up --exit-code-from tester --abort-on-container-exit tester | cat

logs:
	cd tests && docker compose logs -f | cat

clean: down
	# Remove any leftover test-related volumes if they exist.
	cd tests && volumes=$$(docker volume ls -q | grep -E '(wp_data|db_data)' || true); \
		if [ -n "$$volumes" ]; then docker volume rm $$volumes; fi

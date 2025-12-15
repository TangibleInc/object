.PHONY: install test test-all setup-integrations clean help

# Default WordPress develop location
WORDPRESS_DEVELOP_DIR ?= $(HOME)/workspace/wordpress-develop
DATABASE_MODULE_DIR ?= $(shell dirname $(CURDIR))/database-module

help:
	@echo "Available targets:"
	@echo "  make install             - Install composer dependencies"
	@echo "  make test                - Run tests (skips integration tests if deps missing)"
	@echo "  make test-all            - Run all tests (requires setup-integrations first)"
	@echo "  make setup-integrations  - Clone optional integration dependencies"
	@echo "  make setup-wp            - Clone WordPress develop for testing"
	@echo "  make clean               - Remove cloned dependencies"

install:
	composer install

test:
	WORDPRESS_DEVELOP_DIR=$(WORDPRESS_DEVELOP_DIR) ./vendor/bin/phpunit

test-all: setup-integrations
	WORDPRESS_DEVELOP_DIR=$(WORDPRESS_DEVELOP_DIR) \
	DATABASE_MODULE_DIR=$(DATABASE_MODULE_DIR) \
	./vendor/bin/phpunit

setup-integrations: setup-database-module

setup-database-module:
	@if [ ! -d "$(DATABASE_MODULE_DIR)" ]; then \
		echo "Cloning database-module to $(DATABASE_MODULE_DIR)..."; \
		git clone https://github.com/tangibleinc/database-module.git $(DATABASE_MODULE_DIR); \
	else \
		echo "database-module already exists at $(DATABASE_MODULE_DIR)"; \
	fi

setup-wp:
	@if [ ! -d "$(WORDPRESS_DEVELOP_DIR)" ]; then \
		echo "Cloning wordpress-develop to $(WORDPRESS_DEVELOP_DIR)..."; \
		git clone --depth=1 https://github.com/WordPress/wordpress-develop.git $(WORDPRESS_DEVELOP_DIR); \
		cp $(WORDPRESS_DEVELOP_DIR)/wp-tests-config-sample.php $(WORDPRESS_DEVELOP_DIR)/wp-tests-config.php; \
		echo "NOTE: Edit $(WORDPRESS_DEVELOP_DIR)/wp-tests-config.php with your database credentials"; \
	else \
		echo "wordpress-develop already exists at $(WORDPRESS_DEVELOP_DIR)"; \
	fi

clean:
	@echo "This will remove cloned integration dependencies. Are you sure? [y/N]" && read ans && [ $${ans:-N} = y ]
	rm -rf $(DATABASE_MODULE_DIR)

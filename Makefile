IMAGE ?= sspks:dev
PHPUNIT_URL = https://phar.phpunit.de/phpunit-11.phar

.PHONY: build run test fixtures

build:
	docker build \
		--build-arg BRANCH="$$(git rev-parse --abbrev-ref HEAD)" \
		--build-arg COMMIT="$$(git rev-parse HEAD)" \
		-t $(IMAGE) .

# Serves the test fixtures on http://localhost:9999/
run: build
	mkdir -p .cache
	docker run --rm -p 9999:8080 --user "$$(id -u):$$(id -g)" \
		-v "$(CURDIR)/tests/fixtures/spk:/packages:ro" \
		-v "$(CURDIR)/.cache:/cache" \
		-e SSPKS_SITE_NAME="SSpkS dev" \
		$(IMAGE)

test:
	docker run --rm -v "$(CURDIR):/app" -w /app php:8.4-cli \
		sh -c "curl -fsSLo /tmp/phpunit $(PHPUNIT_URL) && php /tmp/phpunit"

fixtures:
	python3 tests/fixtures/make-fixtures.py

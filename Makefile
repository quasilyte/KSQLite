.PHONY: ci-test
ci-test:
	mkdir -p ffilibs && ln -sf $(shell php -f locate_lib.php -- -q) ./ffilibs/libsqlite3
	composer install
	php -d opcache.enable_cli=true -d opcache.preload=preload.php ./vendor/bin/phpunit tests
	composer require --dev quasilyte/ktest-script:dev-master
	./vendor/bin/ktest bench-php --preload preload.php ./benchmarks
	@echo "everything is OK"

.PHONY: test
test:
	# First run PHP tests as they're faster to execute.
	php -d opcache.enable_cli=true -d opcache.preload=preload.php ./vendor/bin/phpunit tests
	# Benchmarks can be considered to be our secondary tests.
	# Run them in PHP-only mode.
	./vendor/bin/ktest bench-php --preload preload.php ./benchmarks
	# Run examples in the output compare mode.
	./vendor/bin/ktest compare --preload preload.php ./examples/quick_start.php
	./vendor/bin/ktest compare --preload preload.php ./examples/prepared_statements.php
	./vendor/bin/ktest compare --preload preload.php ./examples/transactions.php
	# Now run KPHP tests.
	./vendor/bin/ktest phpunit tests
	@echo "everything is OK"

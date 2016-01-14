VERSION := 1.0.0
PLUGINSLUG := passive-indexation-check
MAINFILE := index.php
SRCPATH := $(shell pwd)/src

lint:
	bin/phpcs --config-set show_warnings 0
	bin/phpcs --config-set default_standard PSR2
	bin/phpcs src/
	bin/phpcs tests/

test:
	bin/phpunit

release:
	cp -ar src $(PLUGINSLUG)
	zip -r $(PLUGINSLUG).zip $(PLUGINSLUG)
	rm -rf $(PLUGINSLUG)
	mv $(PLUGINSLUG).zip build/

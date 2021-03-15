# 2021-03-13 HSteeb
# makefile for osis-tools


# === RULES ==========================

# have non-dangerous first target...
.PHONY: info
info:
	@echo ""
	@echo "Unit Tests"
	@echo "  - phpunit    : runs PHP unit tests"
	@echo "To run an individual test case:"
	@echo "  - vendor/bin/phpunit --filter testcase"
	@echo ""
	@echo "Build"
	@echo "  - build      : creates standalone .php files"
	@echo ""
	@echo "Integration test"
	@echo "  - itest      : create files"
	@echo "  - idiff      : compare results"
	@echo "  - isave      : record results"
	@echo ""
	@echo "Run sample"
	@echo "  - sample     : runs osis2html.php on sample/Gn.xml"
	@echo ""
	@echo "General"
	@echo "  - info       : this text"



# === Unit Test ====================
.PHONY: phpunit
phpunit:
	@vendor/bin/phpunit

# === Build ===================
build:
	@cat src/osis2htmlSrc.php src/Converter.php src/Replacer.php | grep -v namespace | grep -v 'use HSteeb' | grep -v 'vendor/autoload.php' > osis2html.php

# === itest ===================
ITEST=test/itest
.PHONY: itest idiff isave
itest: build
	mkdir -p /tmp/osis2htmltest && php osis2html.php itest/src/Gn.xml /tmp/osis2htmltest/Gn.html sample/config-en.json && diff -r itest/ref /tmp/osis2htmltest
idiff:
	diff -r itest/ref /tmp/osis2htmltest
isave:
	cp -a /tmp/osis2htmltest/* itest/ref/

# === Sample ===================
sample: build
	@php ./osis2html.php sample/Gn.xml /tmp/Gn.html sample/config-en.json
	@cp sample/styles.css /tmp
	@echo Now please inspect /tmp/Gn.html...

# === /RULES =========================

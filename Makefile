# Simple Makefile for package management
# No complex dist directory - uses .gitattributes for clean archives

.PHONY: test-archive clean help

help:
	@echo "Available commands:"
	@echo "  test-archive  - Create a test archive to see what users will get"
	@echo "  clean        - Clean up test files"
	@echo ""
	@echo "The .gitattributes file handles excluding dev files from Composer installs"

test-archive:
	@echo "🧳 Creating test archive (simulates Composer download)..."
	@git archive --format=zip --output=test-package.zip HEAD
	@echo "📋 Extracting to see contents..."
	@mkdir -p test-extract && cd test-extract && unzip -q ../test-package.zip
	@echo "📊 Package contents (what users get):"
	@find test-extract -type f | sort
	@echo ""
	@echo "Package size: $$(du -h test-package.zip | cut -f1)"
	@echo "Total files: $$(find test-extract -type f | wc -l)"

clean:
	@echo "🧹 Cleaning test files..."
	@rm -rf test-package.zip test-extract dist/

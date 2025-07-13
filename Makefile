DIST_DIR=dist
SRC_DIR=src
FILES_TO_COPY=composer.json LICENSE README.md

.PHONY: all clean build package

all: build

clean:
	@echo "🧹 Cleaning dist folder..."
	rm -rf $(DIST_DIR)

build: clean
	@echo "📦 Building dist folder..."
	mkdir -p $(DIST_DIR)/$(SRC_DIR)

	@echo "📁 Copying source code..."
	cp -r $(SRC_DIR)/* $(DIST_DIR)/$(SRC_DIR)/

	@echo "📄 Copying other necessary files..."
	for file in $(FILES_TO_COPY); do cp $$file $(DIST_DIR)/; done

package: build
	@echo "📦 Creating composer archive..."
	composer archive --dir=$(DIST_DIR) --format=zip
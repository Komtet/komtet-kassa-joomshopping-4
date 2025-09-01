SHELL:=/bin/bash
VERSION=$(shell grep -o '^[0-9]\+\.[0-9]\+\.[0-9]\+' CHANGELOG.rst | head -n1)
FILENAME=komtetkassa.zip

# Colors
Color_Off=\033[0m
Red=\033[1;31m

help:
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST) | sort

version:  ## Версия проекта
	@echo -e "${Red}Version:${Color_Off} $(VERSION)";

build:  ## Собрать контейнер
	@sudo chmod -R 777 php/ &&\
	docker-compose build --no-cache

start:  ## Запустить контейнер
	@docker-compose up -d

stop:  ## Остановить контейнер
	@docker-compose down

update:  ## Обновить плагин
	@rsync -av --delete \
		--exclude='tests/' \
 		--exclude='examples/' \
		--exclude='docker_env/' \
		komtetkassa/ php/plugins/system/komtetkassa/

release:  ## Архивировать для загрузки
	@mkdir -p dist
	@rm -f dist/$(FILENAME) || true
	@zip -r dist/$(FILENAME) komtetkassa

.PHONY: version  release
.DEFAULT_GOAL := version
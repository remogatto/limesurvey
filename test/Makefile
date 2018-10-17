PHONY: all

test:
	docker-compose -f "compose/docker-compose.yml" down -v
	docker-compose -f "compose/docker-compose.yml" up -d
	go test

all: test

.PHONY: test

init_test:
	sudo docker exec -i mysql mysql -ppassword < test/sql/limesurvey_test.sql
test:
	docker-compose -f test/docker/docker-compose.yml up -d
	go test
	docker-compose -f test/docker/docker-compose.yml stop limesurvey_test

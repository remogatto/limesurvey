.PHONY: test

init_test:
	sudo docker exec -i remogatto_mysql_test mysql -ppassword < test/sql/limesurvey_test.sql
test:
	docker-compose -f test/docker/docker-compose.yml up -d
	go test
	docker-compose -f test/docker/docker-compose.yml stop remogatto_limesurvey_test

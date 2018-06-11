.PHONY: test up init_db cp

up:
	docker-compose -f test/docker/docker-compose.yml up -d

init_db:
	sudo docker exec -i remogatto_mysql_test mysql -ppassword < test/sql/limesurvey_test.sql

cp:
	docker cp test/docker/files/* remogatto_limesurvey_test:/var/www/html/upload/surveys/195163/files/

test:
	go test

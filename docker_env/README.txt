komtet_kassa_joomla
===============================

Чтобы развернуть окружение и потестировать плагин в docker контейнере:
1. создать в дирректории docker_env папку php, скопировать в нее текущий код CMS Shop-Script
2. создать дирректорию для хранения данных БД /srv/docker_volumes/mysql_data/komtet_kassa_joomla3
3. выполнить docker-compose up -d
4. перейти в браузере на localhost:8100 и выполнить установку CMS, параметры подключения к БД указать host: mysql, user: root, password: my_secret_pw_shh, db: test_db
5. установить и настроить плагин через инсталлер
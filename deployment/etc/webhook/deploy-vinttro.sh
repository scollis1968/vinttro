services:
  db:
    image: mariadb:10.6
    container_name: suitecrm_db
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: suitecrm
      MYSQL_USER: suitecrm_user
      MYSQL_PASSWORD: suitecrm_password
    ports:
      - "3307:3306"
    volumes:
      - db_data:/var/lib/mysql

  web:
    image: chialab/php-dev:8.1-apache
    container_name: suitecrm_web
    restart: always
    ports:
      - "8080:80"
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html/public
    depends_on:
      - db
    volumes:
      - suitecrm_runtime:/var/www/html

volumes:
  db_data:
  suitecrm_runtime:
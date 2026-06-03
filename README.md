# Laboratorium 12: Docker Compose – Stack LEMP

## 1. Wykonane polecenia i wyniki

### Uruchomienie środowiska
`docker compose up -d`

<img width="1110" height="221" alt="image" src="https://github.com/user-attachments/assets/c1e1cff9-b61a-4f85-9901-748512d21724" />


### Weryfikacja kontenerów
`docker compose ps`

<img width="2003" height="170" alt="image" src="https://github.com/user-attachments/assets/6c9777b1-b76e-48ba-95e1-4fbf304ac962" />

---

## 2. Dowody poprawnego działania aplikacji

### Działanie serwera PHP (localhost:4001)
<img width="1183" height="873" alt="4001" src="https://github.com/user-attachments/assets/beb2e6e5-d210-481b-8579-6bd80b9e8b17" />

### Działanie phpMyAdmin (localhost:6001)
<img width="1413" height="873" alt="6001" src="https://github.com/user-attachments/assets/b55476f8-a710-4de5-9e05-8e8396a28a4e" />

---

## 3. Kody źródłowe

### docker-compose.yaml
```yaml
services:
  # Serwer WWW Nginx (E)
  nginx:
    image: nginx:1.25.4-alpine
    container_name: lemp_nginx
    restart: always
    ports:
      - "4001:80"
    volumes:
      - ./src:/var/www/html:ro
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    networks:
      - frontend
      - backend
    depends_on:
      - php

  # Serwer PHP-FPM (P)
  php:
    image: php:8.2-fpm
    container_name: lemp_php
    restart: always
    volumes:
      - ./src:/var/www/html:ro
    networks:
      - backend
    command: sh -c "docker-php-ext-install mysqli && docker-php-ext-enable mysqli && php-fpm"

  # Serwer bazy danych MySQL (M)
  mysql:
    image: mysql:8.0.36
    container_name: lemp_mysql
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: test_db
    networks:
      - backend

  # Interfejs phpMyAdmin
  phpmyadmin:
    image: phpmyadmin/phpmyadmin:5.2.1
    container_name: lemp_phpmyadmin
    restart: always
    ports:
      - "6001:80"
    environment:
      PMA_HOST: mysql
      PMA_PORT: 3306
    networks:
      - frontend
      - backend
    depends_on:
      - mysql

networks:
  frontend:
    driver: bridge
  backend:
    driver: bridge
```

### nginx/default.conf
```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html;
    index index.php index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    # Przekazywanie skryptów PHP do kontenera PHP-FPM
    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        # 'php' to nazwa serwisu z docker-compose.yaml, a 9000 to domyślny port FPM
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }
}
```

### src/index.php
```php
<?php
echo "<h1>Stack LEMP dziala!</h1>";

$conn = new mysqli('mysql', 'root', 'rootpassword', 'test_db');

if ($conn->connect_error) {
    echo "Blad polaczenia z MySQL: " . $conn->connect_error;
} else {
    echo "Polaczenie z MySQL dziala poprawnie!";
}
?>
```

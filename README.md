# PAwCho - Laboratorium 5

## 1. Treść utworzonego pliku Dockerfile

```dockerfile
# ==========================================
# STAGE 1: Budowanie aplikacji
# ==========================================

# Użycie pustego obrazu scratch
FROM scratch AS builder

ADD alpine-minirootfs-3.21.3-x86_64.tar /

WORKDIR /app

# Instalacja kompilatora Go
RUN apk add --no-cache go

# Skopiowanie kodu i kompilacja
COPY main.go .
RUN go build -o myapp main.go


# ==========================================
# STAGE 2: Serwer NGINX (Reverse Proxy)
# ==========================================

FROM nginx:alpine

# Odbieranie wersji z polecenia docker build
ARG VERSION=v1.0.0
ENV APP_VERSION=${VERSION}

# Instalacja curl dla Healthcheck
RUN apk add --no-cache curl

WORKDIR /app

# Skopiowanie skompilowanej aplikacji z Etapu 1
COPY --from=builder /app/myapp /app/myapp

# Skopiowanie konfiguracji NGINX
COPY default.conf /etc/nginx/conf.d/default.conf

# Skopiowanie skryptu startowego i nadanie uprawnień
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

# Automatyczna kontrola działania
HEALTHCHECK --interval=10s --timeout=3s \
  CMD curl -f http://localhost/ || exit 1

# Uruchomienie aplikacji i serwera NGINX
CMD ["/start.sh"]
```

## 2. Polecenie do budowy obrazu i wynik działania

**Polecenie:**
```bash
docker build --build-arg VERSION=v1.0.0 -t lab5-app .
```

**Wynik działania:**
<img width="1376" height="842" alt="image" src="https://github.com/user-attachments/assets/f44b3e56-2f36-4835-ad77-72465f7a94eb" />


## 3. Polecenie uruchamiające serwer

```bash
docker run -d -p 8080:80 --name my-lab5-app lab5-app
```

## 4. Weryfikacja działania kontenera i aplikacji

**Działanie aplikacji:**

<img width="498" height="323" alt="Screenshot_20260401_103643" src="https://github.com/user-attachments/assets/60a083f1-f14a-4a66-b42d-c976c5ff2172" />

**Potwierdzenie poprawnego funkcjonowania (Healthcheck):**

<img width="2088" height="58" alt="image" src="https://github.com/user-attachments/assets/676c7ac9-d3a0-4fa0-b9b5-3f49d3ccb0ff" />


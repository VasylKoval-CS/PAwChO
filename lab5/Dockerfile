# ==========================================
# STAGE 1: Budowanie aplikacji
# ==========================================

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

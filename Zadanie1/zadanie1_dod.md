# PAwCho - Zadanie 1 (Część Nieobowiązkowa)

**Wybrany wariant:** Poziom 2 (max. +50%)

---

## 1. Przygotowanie Buildera
Zgodnie z wymogami, utworzono i uruchomiono nowy builder oparty na sterowniku `docker-container`, który obsługuje budowanie wieloarchitektoniczne.

**Polecenia:**
```bash
docker buildx create --name pawcho-builder --driver docker-container --use
docker buildx inspect --bootstrap
```

<img width="2878" height="1317" alt="Docker_Buildx" src="https://github.com/user-attachments/assets/90a77c87-d18c-4bca-9125-4395b404f3d3" />

## 2. Budowa obrazu Multi-Arch i optymalizacja Cache
Zbudowano obraz przeznaczony na platformy sprzętowe: `linux/amd64` oraz `linux/arm64`. 
W procesie wykorzystano optymalizację cache (eksporter `registry` oraz backend `inline`).

**Polecenie:**
```bash
docker buildx build --platform linux/amd64,linux/arm64 \
  -t vasylkoval/zadanie1-pogoda:latest \
  --cache-from type=registry,ref=vasylkoval/zadanie1-pogoda:latest \
  --cache-to type=inline \
  --push .
```

<img width="1611" height="1047" alt="Build" src="https://github.com/user-attachments/assets/788e1470-14db-4514-9507-cdc4129587c7" />

## 3. Weryfikacja Manifestu OCI na DockerHub
Narzędziem `imagetools` potwierdzono poprawną obecność deklaracji dla architektur `linux/amd64` oraz `linux/arm64`. Widoczne na zrzucie platformy `unknown/unknown` to standard bezpieczeństwa OCI Attestations/Provenance, dodawany przez BuildKit.

**Polecenie:**
```bash
docker buildx imagetools inspect vasylkoval/zadanie1-pogoda:latest
```

<img width="2879" height="791" alt="inspect_platforms" src="https://github.com/user-attachments/assets/5fffc350-e74f-486b-ab40-b197f7fdc177" />

## 4. Analiza bezpieczeństwa i wykrycie podatności (CVE)
Przeprowadzono analizę bezpieczeństwa obrazu za pomocą narzędzia Docker Scout. 
Ponieważ użyto obrazu bazowego `scratch`, kontener nie posiadał żadnych podatności na poziomie systemu operacyjnego. Wykryte zagrożenia w pakiecie `golang/stdlib` wynikały ze statycznego zlinkowania starszej wersji kompilatora Go (v1.22.12) wewnątrz pliku binarnego.

**Polecenie:**
```bash
docker scout cves vasylkoval/zadanie1-pogoda:latest
```

<img width="1535" height="584" alt="Scout_Old" src="https://github.com/user-attachments/assets/b9f13f92-84c3-470c-b745-3e1bb1b98962" />

## 5. Mitygacja zagrożeń - Aktualizacja Dockerfile
Aby całkowicie wyeliminować podatności, zaktualizowano wersję obrazu buildera na najnowszą zalecaną (`1.25`). 

**Zmodyfikowana linijka w pliku Dockerfile:**
* **Było:** `FROM golang:1.22-alpine AS builder`
* **Zmieniono na:** `FROM golang:1.25-alpine AS builder`

**Pełny zaktualizowany plik Dockerfile:**
```dockerfile
# ==========================================
# ETAP 1: Budowanie aplikacji (Builder)
# ==========================================

# Updated from golang:1.22-alpine
FROM golang:1.25-alpine AS builder

# Ustawienie katalogu roboczego
WORKDIR /app

# Instalacja certyfikatów CA (wymagane do zapytań HTTPS z obrazu scratch)
RUN apk --no-cache add ca-certificates

# Kopiowanie kodu źródłowego
COPY main.go .

# Kompilacja statyczna:
# -ldflags="-w -s" - usuwa informacje debugowania, zmniejszając rozmiar pliku binarnego
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-w -s" -a -installsuffix cgo -o webapp main.go

# ==========================================
# ETAP 2: Obraz docelowy (Release)
# ==========================================
# Użycie pustego obrazu
FROM scratch

# Metadane OCI
LABEL org.opencontainers.image.authors="Vasyl Koval"
LABEL org.opencontainers.image.title="Zadanie 1 - Pogoda"
LABEL org.opencontainers.image.description="Aplikacja pogodowa w Go na obrazie scratch"

# Kopiowanie certyfikatów SSL z etapu buildera
COPY --from=builder /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/

# Kopiowanie skompilowanego pliku binarnego
COPY --from=builder /app/webapp /webapp

# Informacja o porcie TCP
EXPOSE 8080

# HEALTHCHECK z użyciem flagi '-health' zdefiniowanej w kodzie
# Nie używamy curl, ponieważ obraz scratch nie posiada powłoki shell
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD ["/webapp", "-health"]

# Uruchomienie aplikacji
CMD ["/webapp"]

```

## 6. Ponowna budowa i końcowa weryfikacja bezpieczeństwa
Po modyfikacji jednej linijki w `Dockerfile`, projekt został zbudowany ponownie z wykorzystaniem tego samego środowiska `buildx` i poleceń optymalizacji cache. 
Końcowy skan zaktualizowanego obrazu potwierdził **całkowite wyeliminowanie podatności (0 CRITICAL, 0 HIGH)**.

<img width="1535" height="735" alt="Scout_Updated" src="https://github.com/user-attachments/assets/2dcc6c49-92f8-4760-b091-b796acc24da5" />

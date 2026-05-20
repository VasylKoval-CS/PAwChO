# PAwCho - Zadanie 1 (Część Obowiązkowa)

## 1. Kod oprogramowania i plik Dockerfile

W celu maksymalnej optymalizacji rozmiaru obrazu docelowego, aplikacja została napisana w języku Go, co umożliwia kompilację do w pełni statycznego pliku binarnego. Jako obraz bazowy w drugim etapie wykorzystano warstwę `scratch`. Mechanizm Healthcheck został zaimplementowany bezpośrednio wewnątrz kodu aplikacji, eliminując potrzebę instalacji powłoki systemowej czy narzędzi sieciowych takich jak `curl` lub `wget`.

**Kod `main.go`:**
```go
	package main

	import (
		"encoding/json"
		"fmt"
		"html/template"
		"log"
		"net/http"
		"os"
		"time"
	)

	// Mapa współrzędnych dla Open-Meteo API
	var cities = map[string]struct{ Lat, Lon string }{
		"Warszawa": {"52.2297", "21.0122"},
		"Lublin":   {"51.2500", "22.5667"},
		"Kyiv":     {"50.4501", "30.5234"},
	}

	// Struktura odpowiedzi JSON z API
	type WeatherResponse struct {
		CurrentWeather struct {
			Temperature float64 `json:"temperature"`
			Windspeed   float64 `json:"windspeed"`
		} `json:"current_weather"`
	}

	func main() {
		// 1. HEALTHCHECK
		// Uruchomienie z flagą '-health' testuje lokalny endpoint /health
		if len(os.Args) > 1 && os.Args[1] == "-health" {
			resp, err := http.Get("http://127.0.0.1:8080/health")
			if err != nil || resp.StatusCode != 200 {
				os.Exit(1) // Status: unhealthy
			}
			os.Exit(0) // Status: healthy
		}

		// 2. SERVER MODE
		port := "8080"
		author := "Vasyl Koval"

		// Logowanie przy starcie
		log.Printf("=== Aplikacja uruchomiona ===")
		log.Printf("Data uruchomienia: %s", time.Now().Format(time.RFC1123))
		log.Printf("Autor: %s", author)
		log.Printf("Nasłuchiwanie na porcie TCP: %s", port)

		// Główny handler aplikacji
		http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
			city := r.URL.Query().Get("city")
			var weatherData string

			// Pobieranie danych pogodowych, jeśli wybrano miasto
			if coords, ok := cities[city]; ok {
				url := fmt.Sprintf("https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s&current_weather=true", coords.Lat, coords.Lon)
				resp, err := http.Get(url)
				if err == nil {
					defer resp.Body.Close()
					var wr WeatherResponse
					if json.NewDecoder(resp.Body).Decode(&wr) == nil {
						weatherData = fmt.Sprintf("Temperatura: %.1f°C, Wiatr: %.1f km/h", wr.CurrentWeather.Temperature, wr.CurrentWeather.Windspeed)
					}
				}
			}

			// Prosty interfejs UI
			html := `
			<!DOCTYPE html>
			<html>
			<head><title>Pogoda - {{.Author}}</title><meta charset="utf-8"></head>
			<body style="font-family: sans-serif; padding: 20px;">
				<h1>Wybierz lokalizację</h1>
				<form method="GET">
					<select name="city">
						<option value="Warszawa">Warszawa (Polska)</option>
						<option value="Lublin">Lublin (Polska)</option>
						<option value="Kyiv">Kyiv (Ukraina)</option>
					</select>
					<button type="submit">Sprawdź pogodę</button>
				</form>
				{{if .City}}
					<div style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-radius: 5px; display: inline-block;">
						<h2>{{.City}}</h2>
						<p><strong>{{.Weather}}</strong></p>
					</div>
				{{end}}
			</body>
			</html>
			`
			tmpl, _ := template.New("webpage").Parse(html)
			tmpl.Execute(w, struct{ City, Weather, Author string }{city, weatherData, author})
		})

		// Endpoint używany przez mechanizm healthcheck
		http.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(http.StatusOK)
			w.Write([]byte("OK"))
		})

		log.Fatal(http.ListenAndServe(":"+port, nil))
	}
```

**Plik `Dockerfile`:**
```dockerfile
# ==========================================
# ETAP 1: Budowanie aplikacji (Builder)
# ==========================================
FROM golang:1.22-alpine AS builder

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

## 2. Polecenie do budowy obrazu i wynik działania

**Polecenie:**
```bash
docker build -t zadanie1-pogoda .
```

**Wynik działania:**
<img width="2843" height="763" alt="Docker_Build1" src="https://github.com/user-attachments/assets/a7e7273c-8a5b-48bc-81cc-6e1c0c79c43d" />


## 3. Polecenie uruchamiające serwer

**Polecenie:**
```bash
docker run -d -p 8080:8080 --name app_pogoda zadanie1-pogoda
```

**Wynik działania:**
<img width="1279" height="46" alt="Docker_run" src="https://github.com/user-attachments/assets/78eeb7e3-0dde-4436-90ab-b4f37ec54d00" />


## 4. Weryfikacja działania kontenera i aplikacji

**Logi startowe aplikacji oraz weryfikacja mechanizmu Healthcheck:**

**Polecenie:**
```bash
docker logs app_pogoda
docker ps --filter name=app_pogoda
```
**Wynik działania:**
<img width="1281" height="122" alt="Docker_logs" src="https://github.com/user-attachments/assets/f8f4cb4f-5a78-4663-8133-c57a6ed135a2" />


**Wynik działania:**
<img width="1803" height="83" alt="healthcheck" src="https://github.com/user-attachments/assets/9a200c14-f0fd-486d-98b2-2b2c8f10ef5e" />


**Weryfikacja rozmiaru obrazu oraz historii warstw:**

**Polecenia:**
```bash
docker images zadanie1-pogoda
docker history zadanie1-pogoda
```

**Wynik działania:**
<img width="1448" height="419" alt="layers_weight" src="https://github.com/user-attachments/assets/15ac2e25-fbfa-4cc7-9cf0-ca77fb583f57" />


**Działanie aplikacji w oknie przeglądarki:**

<img width="1434" height="532" alt="app_test" src="https://github.com/user-attachments/assets/099024d8-743e-4ba3-9212-901cd605cfe7" />

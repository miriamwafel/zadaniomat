# Fiszki - System Powtórek (Vercel Edition)

System zarządzania fiszkami z inteligentnym harmonogramem powtórek oparty na zasadzie spaced repetition.

## Tech Stack

- **Frontend**: Next.js 15 + React + TypeScript
- **Styling**: Custom CSS (zachowany oryginalny design "Langer Branding")
- **Backend**: Next.js API Routes
- **Database**: Neon Postgres (serverless)
- **Hosting**: Vercel

## Funkcjonalności

- 📚 System powtórek z 6 interwałami (0, 1, 5, 15, 40, 120 dni)
- 🌍 Wsparcie dla wielu języków (Hiszpański, Angielski)
- 📊 Wizualizacja postępu nauki
- 📅 Kalendarz z obciążeniem dni
- 🎯 Inteligentne rozkładanie dat powtórek (unikanie przeciążonych dni)

## Setup lokalny

1. Zainstaluj zależności:
```bash
npm install
```

2. Skopiuj przykładowy plik ENV:
```bash
cp .env.local.example .env.local
```

3. Dodaj connection string do Neon Postgres w `.env.local`:
```
POSTGRES_URL="postgresql://user:password@host/database"
```

4. Uruchom migrację bazy danych:
```bash
# Połącz się z bazą i wykonaj schema.sql
psql $POSTGRES_URL < schema.sql
```

5. Uruchom dev server:
```bash
npm run dev
```

Otwórz [http://localhost:3000](http://localhost:3000) w przeglądarce.

## Deploy na Vercel

### Krok 1: Utwórz projekt na Vercel

1. Zaloguj się na [vercel.com](https://vercel.com)
2. Kliknij "Add New Project"
3. Importuj to repozytorium z GitHuba

### Krok 2: Dodaj Neon Postgres

1. W dashboardzie Vercel przejdź do zakładki **Storage**
2. Kliknij **Create Database**
3. Wybierz **Neon** (Serverless Postgres)
4. Kliknij **Continue**
5. Wybierz region (najlepiej blisko użytkowników, np. Frankfurt dla Polski)
6. Kliknij **Create**

Vercel automatycznie doda zmienne środowiskowe:
- `POSTGRES_URL`
- `POSTGRES_PRISMA_URL`
- `POSTGRES_URL_NON_POOLING`

### Krok 3: Uruchom migrację bazy

Po deploymencie, musisz uruchomić schemat bazy:

**Opcja A: Przez Neon Dashboard**
1. Przejdź do [console.neon.tech](https://console.neon.tech)
2. Znajdź swoją bazę danych
3. Kliknij **SQL Editor**
4. Skopiuj zawartość pliku `schema.sql` i wykonaj

**Opcja B: Przez psql lokalnie**
```bash
# Pobierz connection string z Vercel Dashboard > Storage > [twoja baza] > .env.local
psql "postgresql://..." < schema.sql
```

### Krok 4: Deploy!

Vercel automatycznie zdeployuje aplikację. Po każdym pushu do brancha będzie automatyczny redeploy.

## API Endpoints

- `GET /api/fiszki/stats` - Statystyki per język
- `GET /api/fiszki/today?jezyk={jezyk}` - Zadania na dziś
- `GET /api/fiszki/date/{date}?jezyk={jezyk}` - Zadania na konkretny dzień
- `GET /api/fiszki/all?jezyk={jezyk}` - Wszystkie fiszki
- `GET /api/fiszki/load/{year}/{month}?jezyk={jezyk}` - Obciążenie miesiąca
- `POST /api/fiszki` - Utwórz nową fiszkę
- `GET /api/fiszki/{id}` - Pobierz fiszkę
- `PUT /api/fiszki/{id}` - Zaktualizuj fiszkę
- `DELETE /api/fiszki/{id}` - Usuń fiszkę

## Dodawanie fiszek

Możesz dodawać fiszki przez API:

```bash
curl -X POST https://twoja-domena.vercel.app/api/fiszki \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Czasowniki nieregularne - cz. 1",
    "jezyk": "hiszpanski",
    "ilosc_slowek": 25,
    "data_utworzenia": "2025-01-15"
  }'
```

System automatycznie wygeneruje inteligentny harmonogram powtórek.

## Struktura bazy danych

```sql
CREATE TABLE fiszki (
  id SERIAL PRIMARY KEY,
  name VARCHAR(500) NOT NULL,
  jezyk VARCHAR(20) NOT NULL DEFAULT 'hiszpanski',
  ilosc_slowek INTEGER DEFAULT 0,
  data_utworzenia DATE NOT NULL,
  powtorka_1 DATE,
  powtorka_2 DATE,
  powtorka_3 DATE,
  powtorka_4 DATE,
  powtorka_5 DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## License

MIT

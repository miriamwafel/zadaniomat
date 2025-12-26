-- Fiszki Database Schema for Neon Postgres

CREATE TABLE IF NOT EXISTS fiszki (
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

-- Index dla szybkiego wyszukiwania po języku
CREATE INDEX IF NOT EXISTS idx_fiszki_jezyk ON fiszki(jezyk);

-- Index dla dat powtórek
CREATE INDEX IF NOT EXISTS idx_fiszki_data_utworzenia ON fiszki(data_utworzenia);
CREATE INDEX IF NOT EXISTS idx_fiszki_powtorka_1 ON fiszki(powtorka_1);
CREATE INDEX IF NOT EXISTS idx_fiszki_powtorka_2 ON fiszki(powtorka_2);
CREATE INDEX IF NOT EXISTS idx_fiszki_powtorka_3 ON fiszki(powtorka_3);
CREATE INDEX IF NOT EXISTS idx_fiszki_powtorka_4 ON fiszki(powtorka_4);
CREATE INDEX IF NOT EXISTS idx_fiszki_powtorka_5 ON fiszki(powtorka_5);

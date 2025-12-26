import { neon } from '@neondatabase/serverless';

if (!process.env.POSTGRES_URL) {
  throw new Error('POSTGRES_URL environment variable is not set');
}

export const sql = neon(process.env.POSTGRES_URL);

export interface Fiszka {
  id: number;
  name: string;
  jezyk: 'hiszpanski' | 'angielski';
  ilosc_slowek: number;
  data_utworzenia: string;
  powtorka_1: string | null;
  powtorka_2: string | null;
  powtorka_3: string | null;
  powtorka_4: string | null;
  powtorka_5: string | null;
  created_at: Date;
  updated_at: Date;
}

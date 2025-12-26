import { NextResponse } from 'next/server';
import { sql } from '@/lib/db';

export async function GET() {
  try {
    const fiszki = await sql`SELECT * FROM fiszki`;

    const stats: Record<string, { total: number; today: number; slowek: number }> = {
      hiszpanski: { total: 0, today: 0, slowek: 0 },
      angielski: { total: 0, today: 0, slowek: 0 },
    };

    const today = new Date().toISOString().split('T')[0];
    const metaKeys = [
      'data_utworzenia',
      'powtorka_1',
      'powtorka_2',
      'powtorka_3',
      'powtorka_4',
      'powtorka_5',
    ];

    for (const fiszka of fiszki) {
      const jezyk = fiszka.jezyk || 'hiszpanski';

      if (stats[jezyk]) {
        stats[jezyk].total++;
        stats[jezyk].slowek += fiszka.ilosc_slowek || 0;

        for (const key of metaKeys) {
          if (fiszka[key] === today) {
            stats[jezyk].today++;
            break;
          }
        }
      }
    }

    return NextResponse.json(stats);
  } catch (error) {
    console.error('Error fetching stats:', error);
    return NextResponse.json({ error: 'Failed to fetch stats' }, { status: 500 });
  }
}

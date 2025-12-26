import { NextRequest, NextResponse } from 'next/server';
import { sql } from '@/lib/db';

export async function GET(request: NextRequest) {
  try {
    const searchParams = request.nextUrl.searchParams;
    const jezykFilter = searchParams.get('jezyk') || '';

    let fiszki;
    if (jezykFilter) {
      fiszki = await sql`
        SELECT * FROM fiszki
        WHERE jezyk = ${jezykFilter}
        ORDER BY data_utworzenia DESC
      `;
    } else {
      fiszki = await sql`
        SELECT * FROM fiszki
        ORDER BY data_utworzenia DESC
      `;
    }

    const result = fiszki.map((fiszka) => ({
      id: fiszka.id,
      name: fiszka.name,
      jezyk: fiszka.jezyk,
      ilosc_slowek: fiszka.ilosc_slowek || 0,
      data_utworzenia: fiszka.data_utworzenia,
      powtorka_1: fiszka.powtorka_1,
      powtorka_2: fiszka.powtorka_2,
      powtorka_3: fiszka.powtorka_3,
      powtorka_4: fiszka.powtorka_4,
      powtorka_5: fiszka.powtorka_5,
    }));

    return NextResponse.json({
      count: result.length,
      fiszki: result,
      jezyk_filter: jezykFilter,
    });
  } catch (error) {
    console.error('Error fetching all fiszki:', error);
    return NextResponse.json({ error: 'Failed to fetch fiszki' }, { status: 500 });
  }
}

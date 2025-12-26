import { NextRequest, NextResponse } from 'next/server';
import { sql } from '@/lib/db';

export async function GET(request: NextRequest) {
  try {
    const today = new Date().toISOString().split('T')[0];
    const searchParams = request.nextUrl.searchParams;
    const jezykFilter = searchParams.get('jezyk') || '';

    let fiszki;
    if (jezykFilter) {
      fiszki = await sql`SELECT * FROM fiszki WHERE jezyk = ${jezykFilter}`;
    } else {
      fiszki = await sql`SELECT * FROM fiszki`;
    }

    const tasks: any[] = [];

    const dateFields = [
      { field: 'data_utworzenia', type: 'nauka', priority: 0 },
      { field: 'powtorka_1', type: 'powtorka_1', priority: 1 },
      { field: 'powtorka_2', type: 'powtorka_2', priority: 2 },
      { field: 'powtorka_3', type: 'powtorka_3', priority: 3 },
      { field: 'powtorka_4', type: 'powtorka_4', priority: 4 },
      { field: 'powtorka_5', type: 'powtorka_5', priority: 5 },
    ];

    for (const fiszka of fiszki) {
      for (const dateField of dateFields) {
        if (fiszka[dateField.field] === today) {
          tasks.push({
            id: fiszka.id,
            name: fiszka.name,
            type: dateField.type,
            priority: dateField.priority,
            jezyk: fiszka.jezyk,
            ilosc_slowek: fiszka.ilosc_slowek || 0,
          });
        }
      }
    }

    tasks.sort((a, b) => a.priority - b.priority);

    return NextResponse.json({
      date: today,
      count: tasks.length,
      tasks,
      jezyk_filter: jezykFilter,
    });
  } catch (error) {
    console.error('Error fetching today tasks:', error);
    return NextResponse.json({ error: 'Failed to fetch tasks' }, { status: 500 });
  }
}

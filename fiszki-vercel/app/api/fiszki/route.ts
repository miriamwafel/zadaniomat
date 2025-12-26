import { NextRequest, NextResponse } from 'next/server';
import { sql } from '@/lib/db';
import { generateSmartSchedule } from '@/lib/helpers';

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const {
      name,
      jezyk = 'hiszpanski',
      ilosc_slowek = 0,
      data_utworzenia,
    } = body;

    if (!name) {
      return NextResponse.json({ error: 'Name is required' }, { status: 400 });
    }

    const startDate = data_utworzenia || new Date().toISOString().split('T')[0];

    // Generuj inteligentny harmonogram
    const schedule = await generateSmartSchedule(startDate, jezyk, 0);

    const result = await sql`
      INSERT INTO fiszki (
        name, jezyk, ilosc_slowek, data_utworzenia,
        powtorka_1, powtorka_2, powtorka_3, powtorka_4, powtorka_5
      )
      VALUES (
        ${name}, ${jezyk}, ${ilosc_slowek}, ${startDate},
        ${schedule.powtorka_1.date},
        ${schedule.powtorka_2.date},
        ${schedule.powtorka_3.date},
        ${schedule.powtorka_4.date},
        ${schedule.powtorka_5.date}
      )
      RETURNING *
    `;

    return NextResponse.json(result[0], { status: 201 });
  } catch (error) {
    console.error('Error creating fiszka:', error);
    return NextResponse.json({ error: 'Failed to create fiszka' }, { status: 500 });
  }
}

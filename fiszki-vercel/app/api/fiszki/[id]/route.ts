import { NextRequest, NextResponse } from 'next/server';
import { sql } from '@/lib/db';

export async function PUT(
  request: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const id = parseInt(params.id);
    const body = await request.json();

    const {
      name,
      jezyk,
      ilosc_slowek,
      data_utworzenia,
      powtorka_1,
      powtorka_2,
      powtorka_3,
      powtorka_4,
      powtorka_5,
    } = body;

    const result = await sql`
      UPDATE fiszki
      SET
        name = COALESCE(${name}, name),
        jezyk = COALESCE(${jezyk}, jezyk),
        ilosc_slowek = COALESCE(${ilosc_slowek}, ilosc_slowek),
        data_utworzenia = COALESCE(${data_utworzenia}, data_utworzenia),
        powtorka_1 = COALESCE(${powtorka_1}, powtorka_1),
        powtorka_2 = COALESCE(${powtorka_2}, powtorka_2),
        powtorka_3 = COALESCE(${powtorka_3}, powtorka_3),
        powtorka_4 = COALESCE(${powtorka_4}, powtorka_4),
        powtorka_5 = COALESCE(${powtorka_5}, powtorka_5),
        updated_at = CURRENT_TIMESTAMP
      WHERE id = ${id}
      RETURNING *
    `;

    if (result.length === 0) {
      return NextResponse.json({ error: 'Fiszka not found' }, { status: 404 });
    }

    return NextResponse.json(result[0]);
  } catch (error) {
    console.error('Error updating fiszka:', error);
    return NextResponse.json({ error: 'Failed to update fiszka' }, { status: 500 });
  }
}

export async function DELETE(
  request: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const id = parseInt(params.id);

    const result = await sql`
      DELETE FROM fiszki WHERE id = ${id}
      RETURNING *
    `;

    if (result.length === 0) {
      return NextResponse.json({ error: 'Fiszka not found' }, { status: 404 });
    }

    return NextResponse.json({ success: true, deleted: result[0] });
  } catch (error) {
    console.error('Error deleting fiszka:', error);
    return NextResponse.json({ error: 'Failed to delete fiszka' }, { status: 500 });
  }
}

export async function GET(
  request: NextRequest,
  { params }: { params: { id: string } }
) {
  try {
    const id = parseInt(params.id);

    const result = await sql`
      SELECT * FROM fiszki WHERE id = ${id}
    `;

    if (result.length === 0) {
      return NextResponse.json({ error: 'Fiszka not found' }, { status: 404 });
    }

    return NextResponse.json(result[0]);
  } catch (error) {
    console.error('Error fetching fiszka:', error);
    return NextResponse.json({ error: 'Failed to fetch fiszka' }, { status: 500 });
  }
}

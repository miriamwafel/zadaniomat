import { NextRequest, NextResponse } from 'next/server';
import { countTasksForDate } from '@/lib/helpers';

export async function GET(
  request: NextRequest,
  { params }: { params: { year: string; month: string } }
) {
  try {
    const { year, month } = params;
    const searchParams = request.nextUrl.searchParams;
    const jezykFilter = searchParams.get('jezyk') || '';

    const daysInMonth = new Date(parseInt(year), parseInt(month), 0).getDate();
    const load: Record<string, number> = {};

    for (let day = 1; day <= daysInMonth; day++) {
      const date = `${year}-${month.padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      load[date] = await countTasksForDate(date, jezykFilter);
    }

    return NextResponse.json({
      year,
      month,
      load,
      jezyk_filter: jezykFilter,
    });
  } catch (error) {
    console.error('Error loading month:', error);
    return NextResponse.json({ error: 'Failed to load month' }, { status: 500 });
  }
}

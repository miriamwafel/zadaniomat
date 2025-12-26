import { sql } from './db';

/**
 * Zlicza zadania na dany dzień dla konkretnego języka
 */
export async function countTasksForDate(
  date: string,
  jezyk: string = '',
  excludeId: number = 0
): Promise<number> {
  const metaKeys = [
    'data_utworzenia',
    'powtorka_1',
    'powtorka_2',
    'powtorka_3',
    'powtorka_4',
    'powtorka_5',
  ];

  let query = `
    SELECT COUNT(*) as count FROM fiszki
    WHERE id != $1
  `;

  const conditions = metaKeys.map((key) => `${key} = $2`).join(' OR ');
  query += ` AND (${conditions})`;

  if (jezyk) {
    query += ` AND jezyk = $3`;
    const result = await sql(query, [excludeId, date, jezyk]);
    return parseInt(result[0].count as string);
  } else {
    const result = await sql(query, [excludeId, date]);
    return parseInt(result[0].count as string);
  }
}

/**
 * Znajduje optymalny dzień dla danego języka
 */
export async function findOptimalDate(
  targetDate: string,
  jezyk: string = '',
  excludeId: number = 0,
  range: number = 3
): Promise<{
  date: string;
  count: number;
  original_date: string;
  original_count: number;
  changed: boolean;
  diff_days: number;
}> {
  let bestDate = targetDate;
  let bestCount = await countTasksForDate(targetDate, jezyk, excludeId);

  for (let i = 1; i <= range; i++) {
    // Sprawdź wcześniejszy dzień
    const earlier = addDays(targetDate, -i);
    const earlierCount = await countTasksForDate(earlier, jezyk, excludeId);
    if (earlierCount < bestCount) {
      bestCount = earlierCount;
      bestDate = earlier;
    }

    // Sprawdź późniejszy dzień
    const later = addDays(targetDate, i);
    const laterCount = await countTasksForDate(later, jezyk, excludeId);
    if (laterCount < bestCount) {
      bestCount = laterCount;
      bestDate = later;
    }
  }

  const diffDays = daysBetween(targetDate, bestDate);

  return {
    date: bestDate,
    count: bestCount,
    original_date: targetDate,
    original_count: await countTasksForDate(targetDate, jezyk, excludeId),
    changed: bestDate !== targetDate,
    diff_days: diffDays,
  };
}

/**
 * Generuje inteligentny harmonogram dla danego języka
 */
export async function generateSmartSchedule(
  startDate: string,
  jezyk: string = '',
  excludeId: number = 0
) {
  const intervals = {
    powtorka_1: 1,
    powtorka_2: 5,
    powtorka_3: 15,
    powtorka_4: 40,
    powtorka_5: 120,
  };

  const schedule: Record<string, any> = {};

  for (const [key, days] of Object.entries(intervals)) {
    const defaultDate = addDays(startDate, days);
    const optimal = await findOptimalDate(defaultDate, jezyk, excludeId);
    schedule[key] = {
      ...optimal,
      interval: days,
    };
  }

  return schedule;
}

/**
 * Dodaje dni do daty
 */
function addDays(dateStr: string, days: number): string {
  const date = new Date(dateStr);
  date.setDate(date.getDate() + days);
  return date.toISOString().split('T')[0];
}

/**
 * Oblicza różnicę w dniach między datami
 */
function daysBetween(date1: string, date2: string): number {
  const d1 = new Date(date1);
  const d2 = new Date(date2);
  const diffTime = d2.getTime() - d1.getTime();
  return Math.round(diffTime / (1000 * 60 * 60 * 24));
}

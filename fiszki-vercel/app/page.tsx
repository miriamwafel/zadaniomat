'use client';

import { useEffect, useState } from 'react';
import './globals.css';

const LIMIT = 3;
const GOALS = {
  hiszpanski: { words: 2500, level: 'B2' },
  angielski: { words: 2000, level: 'C1' },
};

type Jezyk = 'hiszpanski' | 'angielski';

interface Task {
  id: number;
  name: string;
  type: string;
  priority: number;
  jezyk: Jezyk;
  ilosc_slowek: number;
}

interface Fiszka {
  id: number;
  name: string;
  jezyk: Jezyk;
  ilosc_slowek: number;
  data_utworzenia: string;
  powtorka_1: string | null;
  powtorka_2: string | null;
  powtorka_3: string | null;
  powtorka_4: string | null;
  powtorka_5: string | null;
}

interface Stats {
  hiszpanski: { total: number; today: number; slowek: number };
  angielski: { total: number; today: number; slowek: number };
}

export default function FiszkiPage() {
  const [currentJezyk, setCurrentJezyk] = useState<Jezyk>('hiszpanski');
  const [currentDate, setCurrentDate] = useState(new Date());
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [tasks, setTasks] = useState<Task[]>([]);
  const [fiszki, setFiszki] = useState<Fiszka[]>([]);
  const [monthLoad, setMonthLoad] = useState<Record<string, number>>({});
  const [stats, setStats] = useState<Stats | null>(null);
  const [loading, setLoading] = useState(true);

  const toDateString = (date: Date) => date.toISOString().split('T')[0];
  const formatDatePL = (dateStr: string | null) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('pl-PL', { day: '2-digit', month: '2-digit' });
  };
  const formatDateLong = (dateStr: string) => {
    const d = new Date(dateStr);
    return d.toLocaleDateString('pl-PL', { day: 'numeric', month: 'long', year: 'numeric' });
  };
  const isToday = (dateStr: string) => dateStr === toDateString(new Date());
  const isPast = (dateStr: string) => dateStr < toDateString(new Date());
  const getTypeClass = (type: string) => {
    if (type === 'nauka') return 'nauka';
    if (type === 'powtorka_1') return 'p1';
    return type.replace('powtorka_', 'p');
  };

  const loadStats = async () => {
    try {
      const response = await fetch('/api/fiszki/stats');
      const data = await response.json();
      setStats(data);
    } catch (err) {
      console.error('Error loading stats:', err);
    }
  };

  const loadTasks = async () => {
    const dateStr = toDateString(currentDate);
    try {
      const response = await fetch(`/api/fiszki/date/${dateStr}?jezyk=${currentJezyk}`);
      const data = await response.json();
      setTasks(data.tasks || []);
    } catch (err) {
      console.error('Error loading tasks:', err);
      setTasks([]);
    }
  };

  const loadCalendar = async () => {
    const year = currentMonth.getFullYear();
    const month = currentMonth.getMonth() + 1;
    try {
      const response = await fetch(`/api/fiszki/load/${year}/${String(month).padStart(2, '0')}?jezyk=${currentJezyk}`);
      const data = await response.json();
      setMonthLoad(data.load || {});
    } catch (err) {
      console.error('Error loading calendar:', err);
      setMonthLoad({});
    }
  };

  const loadTable = async () => {
    try {
      const response = await fetch(`/api/fiszki/all?jezyk=${currentJezyk}`);
      const data = await response.json();
      setFiszki(data.fiszki || []);
    } catch (err) {
      console.error('Error loading table:', err);
      setFiszki([]);
    }
  };

  useEffect(() => {
    setLoading(true);
    Promise.all([loadStats(), loadTasks(), loadCalendar(), loadTable()]).finally(() => {
      setLoading(false);
    });
  }, [currentJezyk, currentDate, currentMonth]);

  const updateProgressRing = (jezyk: Jezyk, currentWords: number) => {
    const goal = GOALS[jezyk].words;
    const percent = Math.min(100, Math.round((currentWords / goal) * 100));
    const circumference = 2 * Math.PI * 52;
    const offset = circumference - (percent / 100) * circumference;
    return { percent, offset, currentWords, goal };
  };

  const renderCalendar = () => {
    const year = currentMonth.getFullYear();
    const month = currentMonth.getMonth() + 1;
    const monthNames = ['Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec',
                        'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'];
    const dayNames = ['PN', 'WT', 'ŚR', 'CZ', 'PT', 'SB', 'ND'];

    const firstDay = new Date(year, month - 1, 1);
    let startDay = firstDay.getDay() - 1;
    if (startDay < 0) startDay = 6;

    const daysInMonth = new Date(year, month, 0).getDate();
    const today = toDateString(new Date());

    const days = [];
    for (let i = 0; i < startDay; i++) {
      days.push(<div key={`empty-${i}`} style={{ background: 'var(--deep-blue-card)' }}></div>);
    }

    for (let day = 1; day <= daysInMonth; day++) {
      const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      const count = monthLoad[date] || 0;
      const loadClass = count >= 4 ? 'load-4' : `load-${count}`;
      const isCurrentDay = date === today;

      days.push(
        <div
          key={date}
          className={`calendar-day ${loadClass} ${isCurrentDay ? 'today' : ''}`}
          onClick={() => {
            setCurrentDate(new Date(date));
          }}
        >
          <div className="day-num">{day}</div>
          <div className="day-count">{count > 0 ? count : '-'}</div>
        </div>
      );
    }

    return (
      <>
        <div className="calendar-grid">
          {dayNames.map(d => (
            <div key={d} className="calendar-header">{d}</div>
          ))}
          {days}
        </div>
      </>
    );
  };

  const nauka = tasks.filter(t => t.type === 'nauka');
  const p1 = tasks.filter(t => t.type === 'powtorka_1');
  const pozostale = tasks.filter(t => ['powtorka_2', 'powtorka_3', 'powtorka_4', 'powtorka_5'].includes(t.type));
  const pokazane = pozostale.slice(0, LIMIT);
  const ukryte = pozostale.length - pokazane.length;

  const hiszpProgress = stats ? updateProgressRing('hiszpanski', stats.hiszpanski.slowek) : null;
  const angProgress = stats ? updateProgressRing('angielski', stats.angielski.slowek) : null;

  return (
    <div className="fiszki-wrapper">
      <div id="fiszki-app">
        <div className="brand-header">
          <h1>Progresownik Marcina <span>System</span></h1>
        </div>

        {/* Progress Banner */}
        <div className="progress-banner">
          <div className="progress-item">
            <div className="progress-ring-container">
              <svg className="progress-ring" viewBox="0 0 120 120">
                <circle className="progress-ring-bg" cx="60" cy="60" r="52"/>
                <circle
                  className="progress-ring-fill hiszpanski"
                  cx="60" cy="60" r="52"
                  strokeDasharray="326.73"
                  strokeDashoffset={hiszpProgress?.offset || 326.73}
                />
              </svg>
              <div className="progress-center">
                <div className="progress-percent">{hiszpProgress?.percent || 0}%</div>
                <div className="progress-label">B2</div>
              </div>
            </div>
            <div className="progress-info">
              <div className="progress-lang hiszpanski">Hiszpański</div>
              <div className="progress-goal">Cel: 2500 słówek</div>
              <div className="progress-words">{hiszpProgress?.currentWords || 0} / 2500</div>
            </div>
          </div>

          <div className="progress-item">
            <div className="progress-ring-container">
              <svg className="progress-ring" viewBox="0 0 120 120">
                <circle className="progress-ring-bg" cx="60" cy="60" r="52"/>
                <circle
                  className="progress-ring-fill angielski"
                  cx="60" cy="60" r="52"
                  strokeDasharray="326.73"
                  strokeDashoffset={angProgress?.offset || 326.73}
                />
              </svg>
              <div className="progress-center">
                <div className="progress-percent">{angProgress?.percent || 0}%</div>
                <div className="progress-label">C1</div>
              </div>
            </div>
            <div className="progress-info">
              <div className="progress-lang angielski">Angielski</div>
              <div className="progress-goal">Cel: 2000 słówek</div>
              <div className="progress-words">{angProgress?.currentWords || 0} / 2000</div>
            </div>
          </div>
        </div>

        {/* Language Switcher */}
        <div className="jezyk-switcher">
          <button
            className={`jezyk-btn hiszpanski ${currentJezyk === 'hiszpanski' ? 'active' : ''}`}
            onClick={() => setCurrentJezyk('hiszpanski')}
          >
            ES • Hiszpański
            <span className="count">{stats?.hiszpanski.today || '-'}</span>
            <span className="total-words">{stats?.hiszpanski.slowek || 0} słówek</span>
          </button>
          <button
            className={`jezyk-btn angielski ${currentJezyk === 'angielski' ? 'active' : ''}`}
            onClick={() => setCurrentJezyk('angielski')}
          >
            EN • Angielski
            <span className="count">{stats?.angielski.today || '-'}</span>
            <span className="total-words">{stats?.angielski.slowek || 0} słówek</span>
          </button>
        </div>

        {/* Tasks Card */}
        <div className="card">
          <div className="card-header">
            <h3><span style={{color: 'var(--flame)'}}>///</span> Zadania na{' '}
              {isToday(toDateString(currentDate)) ? 'dziś' : formatDateLong(toDateString(currentDate))}
            </h3>
            <div className="date-nav">
              <button onClick={() => setCurrentDate(new Date(currentDate.getTime() - 86400000))}>PREV</button>
              <input
                type="date"
                value={toDateString(currentDate)}
                onChange={(e) => setCurrentDate(new Date(e.target.value))}
              />
              <button onClick={() => setCurrentDate(new Date(currentDate.getTime() + 86400000))}>NEXT</button>
              <button
                onClick={() => setCurrentDate(new Date())}
                style={{borderColor: 'var(--flame)', color: 'var(--flame)'}}
              >
                DZIŚ
              </button>
            </div>
          </div>

          {loading ? (
            <div style={{textAlign: 'center', padding: '20px', color: 'var(--ash-grey)'}}>
              /// SYSTEM LOADING...
            </div>
          ) : tasks.length === 0 ? (
            <div className="empty-state">
              SYSTEM STATUS: CLEAR <br/>Brak zadań na ten dzień.
            </div>
          ) : (
            <>
              {nauka.length > 0 && (
                <div className="task-section">
                  <div className="task-section-header nauka">NAUKA [NEW]</div>
                  <ul className="task-list">
                    {nauka.map(t => (
                      <li key={t.id} className="task-item nauka">
                        <span className="name">
                          {t.name}
                          {t.ilosc_slowek > 0 && <span className="word-count">{t.ilosc_slowek} sł.</span>}
                        </span>
                        <span className="type">D0</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {p1.length > 0 && (
                <div className="task-section">
                  <div className="task-section-header p1">PRIORYTET [P1]</div>
                  <ul className="task-list">
                    {p1.map(t => (
                      <li key={t.id} className="task-item p1">
                        <span className="name">
                          {t.name}
                          {t.ilosc_slowek > 0 && <span className="word-count">{t.ilosc_slowek} sł.</span>}
                        </span>
                        <span className="type">P1</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {pozostale.length > 0 && (
                <div className="task-section">
                  <div className="task-section-header powtorka">STANDARD FLOW</div>
                  <ul className="task-list">
                    {pokazane.map(t => (
                      <li key={t.id} className={`task-item ${getTypeClass(t.type)}`}>
                        <span className="name">
                          {t.name}
                          {t.ilosc_slowek > 0 && <span className="word-count">{t.ilosc_slowek} sł.</span>}
                        </span>
                        <span className="type">{t.type.replace('powtorka_', 'P')}</span>
                      </li>
                    ))}
                  </ul>
                  {ukryte > 0 && (
                    <div className="hidden-count">WARN: +{ukryte} UKRYTYCH (LIMIT {LIMIT})</div>
                  )}
                </div>
              )}

              <div className="stats-row">
                TOTAL: {tasks.length} | NEW: {nauka.length} | P1: {p1.length} | OLD: {pozostale.length}
              </div>
            </>
          )}
        </div>

        {/* Calendar Card */}
        <div className="card">
          <div className="card-header">
            <h3><span style={{color: 'var(--cobalt)'}}>///</span> Obciążenie</h3>
            <div className="calendar-nav">
              <button onClick={() => setCurrentMonth(new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1))}>
                PREV
              </button>
              <span className="calendar-month">
                {['Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec',
                  'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'][currentMonth.getMonth()]} {currentMonth.getFullYear()}
              </span>
              <button onClick={() => setCurrentMonth(new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1))}>
                NEXT
              </button>
            </div>
          </div>
          {renderCalendar()}
          <div className="calendar-legend">
            <span><div className="legend-color" style={{background: 'var(--ash-grey)'}}></div> 1</span>
            <span><div className="legend-color" style={{background: 'var(--cobalt)'}}></div> 2</span>
            <span><div className="legend-color" style={{background: '#FF9F1C'}}></div> 3</span>
            <span><div className="legend-color" style={{background: 'var(--flame)'}}></div> 4+</span>
          </div>
        </div>

        {/* Table Card */}
        <div className="card">
          <h3>
            <span style={{color: 'var(--ash-grey)'}}>///</span> Baza wiedzy{' '}
            <span style={{fontSize: '0.8rem', color: 'var(--ash-grey)'}}>
              {currentJezyk === 'hiszpanski' ? 'ES' : 'EN'}
            </span>
          </h3>
          <div className="table-container">
            {fiszki.length === 0 ? (
              <p style={{color: 'var(--ash-grey)', textAlign: 'center', padding: '20px'}}>
                DATABASE EMPTY.
              </p>
            ) : (
              <table>
                <thead>
                  <tr>
                    <th>NAZWA</th>
                    <th>SŁ.</th>
                    <th>DATA</th>
                    <th>P1</th>
                    <th>P2</th>
                    <th>P3</th>
                    <th>P4</th>
                    <th>P5</th>
                  </tr>
                </thead>
                <tbody>
                  {fiszki.map(f => {
                    const cellClass = (date: string | null) => {
                      if (!date) return '';
                      const today = toDateString(new Date());
                      if (date === today) return 'today';
                      if (isPast(date)) return 'done';
                      return '';
                    };
                    const check = (date: string | null) => date && isPast(date) ? ' ✓' : '';

                    return (
                      <tr key={f.id}>
                        <td>{f.name}</td>
                        <td style={{textAlign: 'center', color: 'var(--flame)'}}>
                          {f.ilosc_slowek || '—'}
                        </td>
                        <td className={cellClass(f.data_utworzenia)}>
                          {formatDatePL(f.data_utworzenia)}{check(f.data_utworzenia)}
                        </td>
                        <td className={cellClass(f.powtorka_1)}>
                          {formatDatePL(f.powtorka_1)}{check(f.powtorka_1)}
                        </td>
                        <td className={cellClass(f.powtorka_2)}>
                          {formatDatePL(f.powtorka_2)}{check(f.powtorka_2)}
                        </td>
                        <td className={cellClass(f.powtorka_3)}>
                          {formatDatePL(f.powtorka_3)}{check(f.powtorka_3)}
                        </td>
                        <td className={cellClass(f.powtorka_4)}>
                          {formatDatePL(f.powtorka_4)}{check(f.powtorka_4)}
                        </td>
                        <td className={cellClass(f.powtorka_5)}>
                          {formatDatePL(f.powtorka_5)}{check(f.powtorka_5)}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            )}
            <div className="stats-row">
              RECORDS: {fiszki.length} | SŁÓWEK: {fiszki.reduce((sum, f) => sum + (f.ilosc_slowek || 0), 0)}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

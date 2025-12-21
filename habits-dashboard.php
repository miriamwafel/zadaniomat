<?php
/**
 * Plugin Name: Habits Dashboard
 * Description: Zewnętrzny dashboard wizualizacji nawyków w stylu LANGER
 * Version: 1.0
 */

// Rejestracja custom endpointu dla dashboardu
add_action('init', function() {
    add_rewrite_rule('^habits-dashboard/?$', 'index.php?habits_dashboard=1', 'top');
});

add_filter('query_vars', function($vars) {
    $vars[] = 'habits_dashboard';
    return $vars;
});

add_action('template_redirect', function() {
    if (get_query_var('habits_dashboard')) {
        habits_render_dashboard();
        exit;
    }
});

function habits_render_dashboard() {
    $ajax_url = admin_url('admin-ajax.php');
    ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Habits Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ==================== KOLORY LANGER ==================== */
        :root {
            --deep-blue: #0f172a;
            --flame: #ff4d1c;
            --cobalt: #0026ff;
            --ash-grey: #c4c4c4;
            --light-grey: #f5f5f5;
            --white: #ffffff;
            --green: #22c55e;
            --red: #ef4444;
            --yellow: #eab308;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--deep-blue);
            min-height: 100vh;
            color: var(--white);
        }

        #dashboard {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* ==================== NAGLOWEK ==================== */
        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header .year-badge {
            display: inline-block;
            background: var(--flame);
            color: var(--white);
            padding: 12px 30px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.9em;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 15px;
        }

        .header .date-range {
            color: var(--ash-grey);
            font-size: 0.95em;
            letter-spacing: 1px;
        }

        .header .current-date {
            font-size: 2.5em;
            font-weight: 700;
            margin-top: 15px;
            color: var(--white);
        }

        .header .day-number {
            color: var(--flame);
            font-size: 1.2em;
            font-weight: 600;
            margin-top: 8px;
        }

        /* ==================== ZAKLADKI ==================== */
        .tabs-container {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 5px;
            background: rgba(255,255,255,0.05);
            padding: 5px;
            border-radius: 8px;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }

        .tab-btn {
            padding: 14px 35px;
            font-size: 0.95em;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: var(--ash-grey);
            font-family: inherit;
            letter-spacing: 0.5px;
        }

        .tab-btn:hover {
            color: var(--white);
        }

        .tab-btn.active {
            background: var(--flame);
            color: var(--white);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* ==================== SELECTOR DNIA ==================== */
        .day-selector-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .day-selector-container label {
            display: block;
            font-weight: 500;
            margin-bottom: 10px;
            color: var(--ash-grey);
            font-size: 0.9em;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .day-selector {
            padding: 14px 25px;
            font-size: 16px;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            max-width: 280px;
            background: rgba(255,255,255,0.05);
            color: var(--white);
            font-family: inherit;
            font-weight: 500;
            transition: border-color 0.3s;
        }

        .day-selector:focus {
            outline: none;
            border-color: var(--flame);
        }

        .day-selector option {
            background: var(--deep-blue);
            color: var(--white);
        }

        /* ==================== KARTY ==================== */
        .card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .card h3 {
            color: var(--white);
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 1em;
            letter-spacing: 1px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h3::before {
            content: '';
            width: 4px;
            height: 20px;
            background: var(--flame);
            border-radius: 2px;
        }

        /* ==================== PROGRESS BAR ==================== */
        .progress-section {
            margin-bottom: 30px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            align-items: center;
        }

        .progress-label {
            font-weight: 500;
            color: var(--ash-grey);
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .progress-value {
            font-weight: 700;
            color: var(--flame);
            font-size: 1.1em;
        }

        .progress-bar-bg {
            background: rgba(255,255,255,0.1);
            border-radius: 6px;
            height: 12px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 6px;
            background: linear-gradient(90deg, var(--flame) 0%, #ff6b3d 100%);
            transition: width 0.5s ease;
        }

        .progress-bar-fill.calories {
            background: linear-gradient(90deg, var(--cobalt) 0%, #3d5eff 100%);
        }

        /* ==================== NAWYKI GRID ==================== */
        #habits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .habit-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 25px 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .habit-card:hover {
            transform: translateY(-3px);
            border-color: rgba(255,255,255,0.15);
        }

        .habit-card .icon {
            font-size: 2.2em;
            margin-bottom: 12px;
        }

        .habit-card .name {
            font-size: 0.85em;
            color: var(--ash-grey);
            margin-bottom: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .habit-card .status {
            font-size: 1.8em;
            font-weight: 700;
        }

        .habit-card.done {
            background: var(--flame);
            border-color: var(--flame);
        }

        .habit-card.done .name {
            color: rgba(255,255,255,0.9);
        }

        .habit-card.done .status {
            color: var(--white);
        }

        .habit-card.not-done .status {
            color: rgba(255,255,255,0.2);
        }

        .habit-card.steps {
            background: var(--cobalt);
            border-color: var(--cobalt);
        }

        .habit-card.steps .name {
            color: rgba(255,255,255,0.9);
        }

        .habit-card.steps .status {
            font-size: 1.3em;
            color: var(--white);
        }

        .habit-card.sport {
            background: linear-gradient(135deg, var(--flame) 0%, #ff6b3d 100%);
            border-color: var(--flame);
        }

        .habit-card.sport .name {
            color: rgba(255,255,255,0.9);
        }

        .habit-card.sport .status {
            font-size: 0.95em;
            color: var(--white);
        }

        /* ==================== STREAKI GRID ==================== */
        .streaks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .streak-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px 15px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .streak-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--flame);
        }

        .streak-card .icon {
            font-size: 1.8em;
            margin-bottom: 8px;
        }

        .streak-card .value {
            font-size: 2em;
            font-weight: 700;
            color: var(--flame);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .streak-card .value .fire {
            font-size: 0.8em;
        }

        .streak-card .label {
            font-size: 0.75em;
            color: var(--ash-grey);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }

        /* ==================== STATYSTYKI KALORYCZNE ==================== */
        .calorie-balance-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .calorie-balance-card h3 {
            color: var(--white);
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 1em;
            letter-spacing: 1px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .calorie-balance-card h3::before {
            content: '';
            width: 4px;
            height: 20px;
            background: var(--flame);
            border-radius: 2px;
        }

        .calorie-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
        }

        .calorie-stat-box {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }

        .calorie-stat-box .value {
            font-size: 2em;
            font-weight: 700;
        }

        .calorie-stat-box .label {
            font-size: 0.8em;
            color: var(--ash-grey);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }

        .calorie-stat-box.deficit .value {
            color: var(--green);
        }

        .calorie-stat-box.surplus .value {
            color: var(--red);
        }

        .calorie-stat-box.under .value {
            color: var(--green);
        }

        .calorie-stat-box.over .value {
            color: var(--yellow);
        }

        .total-balance {
            margin-top: 20px;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .total-balance.deficit {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .total-balance.surplus {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .total-balance .big-value {
            font-size: 2.5em;
            font-weight: 700;
        }

        .total-balance.deficit .big-value {
            color: var(--green);
        }

        .total-balance.surplus .big-value {
            color: var(--red);
        }

        .total-balance .big-label {
            font-size: 0.9em;
            color: var(--ash-grey);
            margin-top: 5px;
        }

        /* ==================== STATYSTYKI ==================== */
        #habits-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .stat-card .value {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--flame);
        }

        .stat-card .label {
            font-size: 0.8em;
            color: var(--ash-grey);
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card.cobalt .value {
            color: var(--cobalt);
        }

        /* ==================== DIETA - WYKRES MAKRO ==================== */
        #macro-chart-area {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            max-width: 260px;
            height: 260px;
            margin: 0 auto 25px;
        }

        #macro-center-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            pointer-events: none;
        }

        #macro-center-text .total-kcal {
            font-size: 2em;
            font-weight: 700;
            color: var(--white);
        }

        #macro-center-text .label {
            font-size: 0.75em;
            color: var(--ash-grey);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        #daily-macro-summary {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .macro-item {
            text-align: center;
        }

        .macro-item .value {
            font-size: 1.5em;
            font-weight: 700;
            display: block;
            margin-bottom: 4px;
        }

        .macro-item .label {
            font-size: 0.8em;
            color: var(--ash-grey);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .macro-item.protein .value { color: #4CAF50; }
        .macro-item.fat .value { color: #FFC107; }
        .macro-item.carbs .value { color: var(--cobalt); }

        /* ==================== TABELE ==================== */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }

        .data-table th {
            background: var(--flame);
            color: var(--white);
            padding: 14px 12px;
            text-align: center;
            font-weight: 600;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 14px 12px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: var(--ash-grey);
            font-size: 0.95em;
        }

        .data-table tbody tr:hover {
            background: rgba(255,255,255,0.03);
        }

        .check-done {
            color: var(--flame);
            font-size: 1.3em;
        }

        .check-not-done {
            color: rgba(255,255,255,0.15);
            font-size: 1.3em;
        }

        /* ==================== WYKRESY ==================== */
        .chart-container {
            position: relative;
            height: 250px;
        }

        /* ==================== LOADING ==================== */
        .loading-message {
            text-align: center;
            padding: 60px;
            font-size: 1.1em;
            color: var(--ash-grey);
        }

        .loading-message .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top-color: var(--flame);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ==================== KALORIE W NAWYKACH ==================== */
        .calories-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .calories-box {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
        }

        .calories-box .value {
            font-size: 2.2em;
            font-weight: 700;
            color: var(--flame);
        }

        .calories-box .label {
            font-size: 0.85em;
            color: var(--ash-grey);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 5px;
        }

        .calories-box.target .value {
            color: var(--cobalt);
        }

        /* ==================== RESPONSYWNOSC ==================== */
        @media (max-width: 600px) {
            .header .current-date {
                font-size: 1.8em;
            }

            .tab-btn {
                padding: 12px 20px;
                font-size: 0.9em;
            }

            .habit-card {
                padding: 20px 15px;
            }

            .habit-card .icon {
                font-size: 1.8em;
            }

            .calories-summary {
                grid-template-columns: 1fr;
            }

            .streaks-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .calorie-stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <div id="dashboard">
        <!-- NAGLOWEK -->
        <header class="header">
            <div class="year-badge">HABIT TRACKER</div>
            <div class="date-range" id="date-range">Ladowanie...</div>
            <div class="current-date" id="current-date-display">--</div>
            <div class="day-number" id="stats-display">--</div>
        </header>

        <!-- Loading -->
        <div id="loading-message" class="loading-message">
            <div class="spinner"></div>
            Ladowanie danych...
        </div>

        <div id="main-content" style="display: none;">
            <!-- Zakladki -->
            <div class="tabs-container">
                <button class="tab-btn active" data-tab="habits">NAWYKI</button>
                <button class="tab-btn" data-tab="diet">DIETA</button>
            </div>

            <!-- ==================== ZAKLADKA NAWYKI ==================== -->
            <div id="habits-tab" class="tab-content active">

                <div class="day-selector-container">
                    <label for="habits-day-selector">Wybierz dzien</label>
                    <select id="habits-day-selector" class="day-selector"></select>
                </div>

                <!-- Karty nawykow -->
                <div id="habits-grid"></div>

                <!-- Streaki -->
                <div class="card">
                    <h3>Aktualne Streaki</h3>
                    <div id="all-streaks-grid" class="streaks-grid"></div>
                </div>

                <!-- Statystyki sportowe -->
                <div class="card">
                    <h3>Aktywnosc fizyczna</h3>
                    <div id="sport-stats"></div>
                </div>

                <!-- Wykres krokow -->
                <div class="card">
                    <h3>Kroki - ostatnie 14 dni</h3>
                    <div class="chart-container">
                        <canvas id="stepsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- ==================== ZAKLADKA DIETA ==================== -->
            <div id="diet-tab" class="tab-content">

                <div class="day-selector-container">
                    <label for="diet-day-selector">Wybierz dzien</label>
                    <select id="diet-day-selector" class="day-selector"></select>
                </div>

                <!-- Bilans kaloryczny -->
                <div class="calorie-balance-card">
                    <h3>Bilans kaloryczny</h3>
                    <div class="calorie-stats-grid" id="calorie-stats-grid"></div>
                    <div id="total-balance-container"></div>
                </div>

                <!-- Wykres makro -->
                <div class="card">
                    <h3>Makroskladniki</h3>
                    <div id="macro-chart-area">
                        <canvas id="macroChart"></canvas>
                        <div id="macro-center-text">
                            <div class="total-kcal">0</div>
                            <div class="label">Kalorie</div>
                        </div>
                    </div>
                    <div id="daily-macro-summary"></div>
                </div>

                <!-- Wykres kalorii -->
                <div class="card">
                    <h3>Kalorie - ostatnie 14 dni</h3>
                    <div class="chart-container">
                        <canvas id="caloriesChart"></canvas>
                    </div>
                </div>

                <!-- Tabela makro -->
                <div class="card">
                    <h3>Historia makroskladnikow</h3>
                    <div class="table-container" id="diet-table-wrapper"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        console.log("Dashboard: Start ladowania...");

        const AJAX_URL = "<?php echo esc_js($ajax_url); ?>";
        const CALORIE_GOAL = 2500;

        // Dane
        let dashboardData = null;

        // Wykresy
        let stepsChartInstance = null;
        let macroChartInstance = null;
        let caloriesChartInstance = null;

        // Elementy DOM
        const loadingMessage = document.getElementById("loading-message");
        const mainContent = document.getElementById("main-content");
        const habitsTab = document.getElementById("habits-tab");
        const dietTab = document.getElementById("diet-tab");
        const tabBtns = document.querySelectorAll(".tab-btn");

        // Obsluga zakladek
        tabBtns.forEach(btn => {
            btn.addEventListener("click", () => {
                tabBtns.forEach(b => b.classList.remove("active"));
                btn.classList.add("active");

                const tabName = btn.dataset.tab;
                habitsTab.classList.toggle("active", tabName === "habits");
                dietTab.classList.toggle("active", tabName === "diet");
                habitsTab.style.display = tabName === "habits" ? "block" : "none";
                dietTab.style.display = tabName === "diet" ? "block" : "none";
            });
        });

        // Pobierz dane
        const today = new Date();
        const dateStart = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
        const dateEnd = today.toISOString().split('T')[0];

        fetch(`${AJAX_URL}?action=habits_dashboard_data&date_start=${dateStart}&date_end=${dateEnd}`)
            .then(r => r.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.data || 'Blad pobierania danych');
                }

                dashboardData = result.data;
                console.log("Dashboard: Dane pobrane", dashboardData);

                loadingMessage.style.display = "none";
                mainContent.style.display = "block";

                updateHeader();
                initializeHabitsTab();
                initializeDietTab();
            })
            .catch(error => {
                console.error("Dashboard: Blad:", error);
                loadingMessage.innerHTML = `<p style='color: var(--flame);'>Nie udalo sie zaladowac danych.<br>Blad: ${error.message}</p>`;
            });

        function updateHeader() {
            const today = new Date();
            const dniTygodnia = ['Niedziela', 'Poniedzialek', 'Wtorek', 'Sroda', 'Czwartek', 'Piatek', 'Sobota'];
            const miesiace = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca',
                              'lipca', 'sierpnia', 'wrzesnia', 'pazdziernika', 'listopada', 'grudnia'];

            const dzienTygodnia = dniTygodnia[today.getDay()];
            const dzien = today.getDate();
            const miesiac = miesiace[today.getMonth()];
            const rok = today.getFullYear();

            document.getElementById('current-date-display').textContent = `${dzienTygodnia}, ${dzien} ${miesiac} ${rok}`;

            if (dashboardData.period) {
                document.getElementById('date-range').textContent = `Okres: ${dashboardData.period.nazwa}`;
            }

            if (dashboardData.stats) {
                const level = Math.floor((dashboardData.stats.total_xp || 0) / 1000) + 1;
                document.getElementById('stats-display').textContent = `Poziom ${level} | ${dashboardData.stats.total_xp || 0} XP | Streak: ${dashboardData.stats.current_streak || 0}`;
            }
        }

        function initializeHabitsTab() {
            // Utworz selector dni
            const selector = document.getElementById('habits-day-selector');
            const uniqueDates = getUniqueDatesFromData();

            uniqueDates.forEach((date, index) => {
                const option = document.createElement('option');
                option.value = date;
                const d = new Date(date);
                option.textContent = d.toLocaleDateString('pl-PL', { day: 'numeric', month: 'short', year: 'numeric' });
                selector.appendChild(option);
            });

            selector.addEventListener('change', () => updateHabitsView(selector.value));

            if (uniqueDates.length > 0) {
                updateHabitsView(uniqueDates[0]);
            }

            updateStreaks();
            updateSportStats();
            createStepsChart();
        }

        function getUniqueDatesFromData() {
            const dates = new Set();

            if (dashboardData.entries) {
                dashboardData.entries.forEach(e => dates.add(e.dzien));
            }
            if (dashboardData.sport) {
                dashboardData.sport.forEach(s => dates.add(s.dzien));
            }
            if (dashboardData.diet) {
                dashboardData.diet.forEach(d => dates.add(d.dzien));
            }

            return Array.from(dates).sort((a, b) => new Date(b) - new Date(a));
        }

        function updateHabitsView(dateISO) {
            const grid = document.getElementById('habits-grid');

            // Pobierz wpisy dla tego dnia
            const dayEntries = dashboardData.entries.filter(e => e.dzien === dateISO);
            const daySport = dashboardData.sport.filter(s => s.dzien === dateISO);

            let html = '';

            // Nawyki
            dashboardData.habits.forEach(habit => {
                const entry = dayEntries.find(e => e.habit_id == habit.id);
                const minutes = entry ? parseInt(entry.minuty) : 0;
                const isDone = minutes >= parseInt(habit.cel_minut_dziennie);

                html += `
                    <div class="habit-card ${isDone ? 'done' : 'not-done'}">
                        <div class="icon">${habit.ikona || '📋'}</div>
                        <div class="name">${habit.nazwa}</div>
                        <div class="status">${minutes > 0 ? minutes + ' min' : (isDone ? '✓' : '○')}</div>
                    </div>
                `;
            });

            // Sport
            const activities = daySport.filter(s => s.typ !== 'kroki');
            if (activities.length > 0) {
                const sportNames = activities.map(a => a.typ).join(', ');
                html += `
                    <div class="habit-card sport">
                        <div class="icon">🏋️</div>
                        <div class="name">Sport</div>
                        <div class="status">${sportNames}</div>
                    </div>
                `;
            }

            // Kroki
            const stepsEntry = daySport.find(s => s.typ === 'kroki');
            const steps = stepsEntry ? parseInt(stepsEntry.kroki) : 0;

            html += `
                <div class="habit-card ${steps > 0 ? 'steps' : 'not-done'}">
                    <div class="icon">👟</div>
                    <div class="name">Kroki</div>
                    <div class="status">${steps.toLocaleString('pl-PL')}</div>
                </div>
            `;

            grid.innerHTML = html;
        }

        function updateStreaks() {
            const container = document.getElementById('all-streaks-grid');

            if (!dashboardData.stats) {
                container.innerHTML = '<p style="color: var(--ash-grey);">Brak danych</p>';
                return;
            }

            const currentStreak = dashboardData.stats.current_streak || 0;
            const bestStreak = dashboardData.stats.best_streak || 0;
            const totalXp = dashboardData.stats.total_xp || 0;
            const level = Math.floor(totalXp / 1000) + 1;

            container.innerHTML = `
                <div class="streak-card">
                    <div class="icon">🔥</div>
                    <div class="value">${currentStreak} <span class="fire">🔥</span></div>
                    <div class="label">Aktualny streak</div>
                </div>
                <div class="streak-card">
                    <div class="icon">🏆</div>
                    <div class="value">${bestStreak}</div>
                    <div class="label">Najlepszy streak</div>
                </div>
                <div class="streak-card">
                    <div class="icon">⭐</div>
                    <div class="value">${totalXp}</div>
                    <div class="label">Calkowite XP</div>
                </div>
                <div class="streak-card">
                    <div class="icon">📈</div>
                    <div class="value">${level}</div>
                    <div class="label">Poziom</div>
                </div>
            `;
        }

        function updateSportStats() {
            const container = document.getElementById('sport-stats');

            if (!dashboardData.sport || dashboardData.sport.length === 0) {
                container.innerHTML = '<p style="color: var(--ash-grey); text-align: center;">Brak danych sportowych</p>';
                return;
            }

            // Policz statystyki
            let totalSteps = 0;
            let daysWithSteps = 0;
            const activityCount = {};

            const stepsByDay = {};

            dashboardData.sport.forEach(s => {
                if (s.typ === 'kroki' && s.kroki > 0) {
                    stepsByDay[s.dzien] = (stepsByDay[s.dzien] || 0) + parseInt(s.kroki);
                } else if (s.typ !== 'kroki') {
                    activityCount[s.typ] = (activityCount[s.typ] || 0) + 1;
                }
            });

            Object.values(stepsByDay).forEach(steps => {
                totalSteps += steps;
                daysWithSteps++;
            });

            const avgSteps = daysWithSteps > 0 ? Math.round(totalSteps / daysWithSteps) : 0;

            let html = `
                <div class="streaks-grid">
                    <div class="streak-card">
                        <div class="icon">👟</div>
                        <div class="value">${totalSteps.toLocaleString('pl-PL')}</div>
                        <div class="label">Krokow lacznie</div>
                    </div>
                    <div class="streak-card">
                        <div class="icon">📊</div>
                        <div class="value">${avgSteps.toLocaleString('pl-PL')}</div>
                        <div class="label">Srednia/dzien</div>
                    </div>
            `;

            // Aktywnosci per typ
            const typeIcons = {
                'silownia': '🏋️',
                'bieganie': '🏃',
                'rower': '🚴',
                'plywanie': '🏊',
                'padel': '🎾',
                'spacer': '🚶',
                'inne': '⚡'
            };

            Object.entries(activityCount).forEach(([typ, count]) => {
                const icon = typeIcons[typ] || '⚡';
                html += `
                    <div class="streak-card">
                        <div class="icon">${icon}</div>
                        <div class="value">${count}x</div>
                        <div class="label">${typ}</div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        function createStepsChart() {
            // Przygotuj dane
            const stepsByDay = {};

            if (dashboardData.sport) {
                dashboardData.sport.forEach(s => {
                    if (s.typ === 'kroki' && s.kroki > 0) {
                        stepsByDay[s.dzien] = parseInt(s.kroki);
                    }
                });
            }

            // Ostatnie 14 dni
            const today = new Date();
            const labels = [];
            const data = [];

            for (let i = 13; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(today.getDate() - i);
                const dateISO = date.toISOString().split('T')[0];

                labels.push(`${date.getDate()}/${date.getMonth() + 1}`);
                data.push(stepsByDay[dateISO] || 0);
            }

            const ctx = document.getElementById('stepsChart').getContext('2d');

            if (stepsChartInstance) stepsChartInstance.destroy();

            stepsChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Kroki',
                        data: data,
                        backgroundColor: '#0026ff',
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255,255,255,0.05)' },
                            ticks: {
                                color: '#c4c4c4',
                                callback: (v) => v.toLocaleString('pl-PL')
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#c4c4c4' }
                        }
                    }
                }
            });
        }

        // ==================== DIETA ====================
        function initializeDietTab() {
            const selector = document.getElementById('diet-day-selector');

            if (!dashboardData.diet || dashboardData.diet.length === 0) {
                document.getElementById('calorie-stats-grid').innerHTML = '<p style="color: var(--ash-grey);">Brak danych diety</p>';
                return;
            }

            // Utworz selector
            dashboardData.diet.forEach((d, index) => {
                const option = document.createElement('option');
                option.value = d.dzien;
                const date = new Date(d.dzien);
                option.textContent = date.toLocaleDateString('pl-PL', { day: 'numeric', month: 'short', year: 'numeric' });
                selector.appendChild(option);
            });

            selector.addEventListener('change', () => updateDietView(selector.value));

            if (dashboardData.diet.length > 0) {
                updateDietView(dashboardData.diet[0].dzien);
            }

            updateCalorieBalance();
            createCaloriesChart();
            createDietTable();
        }

        function updateDietView(dateISO) {
            const dayData = dashboardData.diet.find(d => d.dzien === dateISO);

            if (!dayData) return;

            const kcal = parseFloat(dayData.kalorie) || 0;
            const p = parseFloat(dayData.bialko) || 0;
            const f = parseFloat(dayData.tluszcze) || 0;
            const c = parseFloat(dayData.weglowodany) || 0;

            // Wykres makro
            const macroData = [p * 4, f * 9, c * 4];

            document.querySelector("#macro-center-text .total-kcal").textContent = Math.round(kcal).toLocaleString('pl-PL');

            if (macroChartInstance) {
                macroChartInstance.data.datasets[0].data = macroData;
                macroChartInstance.update();
            } else {
                const ctx = document.getElementById('macroChart').getContext('2d');

                macroChartInstance = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Bialko', 'Tluszcze', 'Weglowodany'],
                        datasets: [{
                            data: macroData,
                            backgroundColor: ['#4CAF50', '#FFC107', '#0026ff'],
                            hoverOffset: 8,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#c4c4c4',
                                    padding: 15,
                                    usePointStyle: true
                                }
                            }
                        }
                    }
                });
            }

            document.getElementById("daily-macro-summary").innerHTML = `
                <div class="macro-item protein">
                    <span class="value">${p.toFixed(1)}g</span>
                    <span class="label">Bialko</span>
                </div>
                <div class="macro-item fat">
                    <span class="value">${f.toFixed(1)}g</span>
                    <span class="label">Tluszcze</span>
                </div>
                <div class="macro-item carbs">
                    <span class="value">${c.toFixed(1)}g</span>
                    <span class="label">Weglowodany</span>
                </div>
            `;
        }

        function updateCalorieBalance() {
            if (!dashboardData.diet || dashboardData.diet.length === 0) return;

            let daysUnder = 0;
            let daysOver = 0;
            let totalDeficit = 0;
            let totalSurplus = 0;

            dashboardData.diet.forEach(d => {
                const kcal = parseFloat(d.kalorie) || 0;
                const diff = kcal - CALORIE_GOAL;

                if (diff < 0) {
                    daysUnder++;
                    totalDeficit += Math.abs(diff);
                } else if (diff > 0) {
                    daysOver++;
                    totalSurplus += diff;
                }
            });

            const netBalance = totalSurplus - totalDeficit;
            const isDeficit = netBalance < 0;

            document.getElementById("calorie-stats-grid").innerHTML = `
                <div class="calorie-stat-box under">
                    <div class="value">${daysUnder}</div>
                    <div class="label">Dni ponizej ${CALORIE_GOAL}</div>
                </div>
                <div class="calorie-stat-box over">
                    <div class="value">${daysOver}</div>
                    <div class="label">Dni powyzej ${CALORIE_GOAL}</div>
                </div>
                <div class="calorie-stat-box deficit">
                    <div class="value">${Math.round(totalDeficit).toLocaleString('pl-PL')}</div>
                    <div class="label">Suma deficytu (kcal)</div>
                </div>
                <div class="calorie-stat-box surplus">
                    <div class="value">${Math.round(totalSurplus).toLocaleString('pl-PL')}</div>
                    <div class="label">Suma nadwyzki (kcal)</div>
                </div>
            `;

            document.getElementById("total-balance-container").innerHTML = `
                <div class="total-balance ${isDeficit ? 'deficit' : 'surplus'}">
                    <div class="big-value">${isDeficit ? '-' : '+'}${Math.abs(Math.round(netBalance)).toLocaleString('pl-PL')} kcal</div>
                    <div class="big-label">${isDeficit ? 'Laczny deficyt kaloryczny' : 'Laczna nadwyzka kaloryczna'}</div>
                </div>
            `;
        }

        function createCaloriesChart() {
            if (!dashboardData.diet || dashboardData.diet.length === 0) return;

            const sortedDiet = [...dashboardData.diet].sort((a, b) => new Date(a.dzien) - new Date(b.dzien)).slice(-14);

            const labels = sortedDiet.map(d => {
                const date = new Date(d.dzien);
                return `${date.getDate()}/${date.getMonth() + 1}`;
            });

            const data = sortedDiet.map(d => parseFloat(d.kalorie) || 0);

            const ctx = document.getElementById('caloriesChart').getContext('2d');

            if (caloriesChartInstance) caloriesChartInstance.destroy();

            caloriesChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Kalorie',
                        data: data,
                        borderColor: '#ff4d1c',
                        backgroundColor: 'rgba(255, 77, 28, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#ff4d1c',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: { color: 'rgba(255,255,255,0.05)' },
                            ticks: {
                                color: '#c4c4c4',
                                callback: (v) => v.toLocaleString('pl-PL')
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#c4c4c4' }
                        }
                    }
                }
            });
        }

        function createDietTable() {
            if (!dashboardData.diet || dashboardData.diet.length === 0) {
                document.getElementById('diet-table-wrapper').innerHTML = '<p style="color: var(--ash-grey); text-align: center;">Brak danych</p>';
                return;
            }

            const sortedDiet = [...dashboardData.diet].sort((a, b) => new Date(a.dzien) - new Date(b.dzien));

            let html = '<table class="data-table"><thead><tr>';
            html += '<th>Data</th><th>Kalorie</th><th>Bialko</th><th>Tluszcze</th><th>Weglowodany</th><th>Bilans</th>';
            html += '</tr></thead><tbody>';

            sortedDiet.forEach(d => {
                const date = new Date(d.dzien);
                const dateStr = `${date.getDate()}/${date.getMonth() + 1}`;
                const kcal = parseFloat(d.kalorie) || 0;
                const diff = kcal - CALORIE_GOAL;
                const balanceColor = diff < 0 ? '#22c55e' : '#ef4444';
                const balanceText = diff < 0 ? Math.round(diff) : `+${Math.round(diff)}`;

                html += `<tr>
                    <td style="color: #fff; font-weight: 500;">${dateStr}</td>
                    <td>${Math.round(kcal).toLocaleString('pl-PL')} kcal</td>
                    <td>${parseFloat(d.bialko).toFixed(1)}g</td>
                    <td>${parseFloat(d.tluszcze).toFixed(1)}g</td>
                    <td>${parseFloat(d.weglowodany).toFixed(1)}g</td>
                    <td style="color: ${balanceColor}; font-weight: 600;">${balanceText}</td>
                </tr>`;
            });

            html += '</tbody></table>';
            document.getElementById('diet-table-wrapper').innerHTML = html;
        }
    });
    </script>
</body>
</html>
    <?php
}

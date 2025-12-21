/**
 * Plugin Name: Habit Tracker
 * Description: System śledzenia nawyków życiowych z wykresami i challenge'ami
 * Version: 1.0
 * Author: Ty
 */

date_default_timezone_set('Europe/Warsaw');

// =============================================
// TWORZENIE TABEL
// =============================================
function habits_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Tabela lat (np. 2024, 2025)
    $table_years = $wpdb->prefix . 'habits_years';
    $sql1 = "CREATE TABLE IF NOT EXISTS $table_years (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nazwa VARCHAR(100) NOT NULL,
        data_start DATE NOT NULL,
        data_koniec DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Tabela okresów (np. Q1, Q2, Styczeń, itp.)
    $table_periods = $wpdb->prefix . 'habits_periods';
    $sql2 = "CREATE TABLE IF NOT EXISTS $table_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        year_id INT NOT NULL,
        nazwa VARCHAR(100) NOT NULL,
        data_start DATE NOT NULL,
        data_koniec DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Tabela definicji nawyków (czytanie, hiszpański, angielski, itp.)
    $table_habits = $wpdb->prefix . 'habits_definitions';
    $sql3 = "CREATE TABLE IF NOT EXISTS $table_habits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_id INT NOT NULL,
        nazwa VARCHAR(255) NOT NULL,
        cel_minut_dziennie INT DEFAULT 30,
        kolor VARCHAR(20) DEFAULT '#4A90D9',
        ikona VARCHAR(50) DEFAULT '📚',
        aktywny TINYINT(1) DEFAULT 1,
        pozycja INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Tabela wpisów dziennych (logowanie czasu)
    $table_entries = $wpdb->prefix . 'habits_entries';
    $sql4 = "CREATE TABLE IF NOT EXISTS $table_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        habit_id INT NOT NULL,
        dzien DATE NOT NULL,
        minuty INT DEFAULT 0,
        notatka TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY habit_day (habit_id, dzien)
    ) $charset_collate;";

    // Tabela definicji challenge'ów
    $table_challenges = $wpdb->prefix . 'habits_challenges';
    $sql5 = "CREATE TABLE IF NOT EXISTS $table_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_id INT NOT NULL,
        nazwa VARCHAR(255) NOT NULL,
        opis TEXT,
        typ VARCHAR(50) DEFAULT 'weekly',
        cel_dni INT DEFAULT 4,
        dni_w_tygodniu INT DEFAULT 7,
        ikona VARCHAR(50) DEFAULT '🎯',
        kolor VARCHAR(20) DEFAULT '#E74C3C',
        aktywny TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Tabela checkowania challenge'ów
    $table_challenge_checks = $wpdb->prefix . 'habits_challenge_checks';
    $sql6 = "CREATE TABLE IF NOT EXISTS $table_challenge_checks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        challenge_id INT NOT NULL,
        dzien DATE NOT NULL,
        wykonane TINYINT(1) DEFAULT 0,
        notatka TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY challenge_day (challenge_id, dzien)
    ) $charset_collate;";

    // Statystyki gamifikacji
    $table_stats = $wpdb->prefix . 'habits_stats';
    $sql7 = "CREATE TABLE IF NOT EXISTS $table_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        total_xp INT DEFAULT 0,
        current_streak INT DEFAULT 0,
        best_streak INT DEFAULT 0,
        last_active_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Tabela aktywności sportowych
    $table_sport = $wpdb->prefix . 'habits_sport';
    $sql8 = "CREATE TABLE IF NOT EXISTS $table_sport (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dzien DATE NOT NULL,
        typ VARCHAR(50) NOT NULL,
        czas_minuty INT DEFAULT 0,
        kroki INT DEFAULT 0,
        partie_ciala VARCHAR(255) DEFAULT NULL,
        custom_name VARCHAR(100) DEFAULT NULL,
        converted_to_activity TINYINT(1) DEFAULT 0,
        notatka TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
    dbDelta($sql5);
    dbDelta($sql6);
    dbDelta($sql7);
    dbDelta($sql8);
}

add_action('admin_init', function() {
    global $wpdb;
    $table_years = $wpdb->prefix . 'habits_years';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_years'") != $table_years) {
        habits_create_tables();
    }

    // Sprawdź czy tabela sport istnieje (nowa tabela)
    $table_sport = $wpdb->prefix . 'habits_sport';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_sport'") != $table_sport) {
        habits_create_tables();
    }

    // Migracja: dodaj nowe kolumny do tabeli sport jeśli nie istnieją
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_sport");
    $column_names = array_map(function($c) { return $c->Field; }, $columns);

    if (!in_array('custom_name', $column_names)) {
        $wpdb->query("ALTER TABLE $table_sport ADD COLUMN custom_name VARCHAR(100) DEFAULT NULL");
    }
    if (!in_array('converted_to_activity', $column_names)) {
        $wpdb->query("ALTER TABLE $table_sport ADD COLUMN converted_to_activity TINYINT(1) DEFAULT 0");
    }

    // Migracja: dodaj kolumnę typ do challenges jeśli nie istnieje (weekly/general)
    $table_challenges = $wpdb->prefix . 'habits_challenges';
    $ch_columns = $wpdb->get_results("SHOW COLUMNS FROM $table_challenges");
    $ch_column_names = array_map(function($c) { return $c->Field; }, $ch_columns);

    if (!in_array('completed', $ch_column_names)) {
        $wpdb->query("ALTER TABLE $table_challenges ADD COLUMN completed TINYINT(1) DEFAULT 0");
    }
});

// =============================================
// HELPER FUNCTIONS
// =============================================
function habits_get_current_period($date = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_periods';
    $date = $date ?: date('Y-m-d');

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE %s BETWEEN data_start AND data_koniec LIMIT 1",
        $date
    ));
}

function habits_get_current_year($date = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_years';
    $date = $date ?: date('Y-m-d');

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE %s BETWEEN data_start AND data_koniec LIMIT 1",
        $date
    ));
}

function habits_get_week_dates($date = null) {
    $date = $date ?: date('Y-m-d');
    $timestamp = strtotime($date);
    $day_of_week = date('N', $timestamp);

    $monday = date('Y-m-d', strtotime("-" . ($day_of_week - 1) . " days", $timestamp));
    $sunday = date('Y-m-d', strtotime("+" . (7 - $day_of_week) . " days", $timestamp));

    $dates = [];
    for ($i = 0; $i < 7; $i++) {
        $dates[] = date('Y-m-d', strtotime("+$i days", strtotime($monday)));
    }

    return $dates;
}

// =============================================
// AJAX HANDLERS
// =============================================

// Pobierz wszystkie lata
add_action('wp_ajax_habits_get_years', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_years';
    $years = $wpdb->get_results("SELECT * FROM $table ORDER BY data_start DESC");
    wp_send_json_success($years);
});

// Dodaj rok
add_action('wp_ajax_habits_add_year', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_years';

    $wpdb->insert($table, [
        'nazwa' => sanitize_text_field($_POST['nazwa']),
        'data_start' => sanitize_text_field($_POST['data_start']),
        'data_koniec' => sanitize_text_field($_POST['data_koniec'])
    ]);

    wp_send_json_success(['id' => $wpdb->insert_id]);
});

// Usuń rok
add_action('wp_ajax_habits_delete_year', function() {
    global $wpdb;
    $year_id = intval($_POST['id']);

    // Usuń wszystkie powiązane dane
    $periods = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}habits_periods WHERE year_id = %d", $year_id
    ));

    foreach ($periods as $period_id) {
        habits_delete_period_data($period_id);
    }

    $wpdb->delete($wpdb->prefix . 'habits_periods', ['year_id' => $year_id]);
    $wpdb->delete($wpdb->prefix . 'habits_years', ['id' => $year_id]);

    wp_send_json_success();
});

function habits_delete_period_data($period_id) {
    global $wpdb;

    // Pobierz habits z tego okresu
    $habits = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}habits_definitions WHERE period_id = %d", $period_id
    ));

    foreach ($habits as $habit_id) {
        $wpdb->delete($wpdb->prefix . 'habits_entries', ['habit_id' => $habit_id]);
    }

    // Pobierz challenges z tego okresu
    $challenges = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}habits_challenges WHERE period_id = %d", $period_id
    ));

    foreach ($challenges as $challenge_id) {
        $wpdb->delete($wpdb->prefix . 'habits_challenge_checks', ['challenge_id' => $challenge_id]);
    }

    $wpdb->delete($wpdb->prefix . 'habits_definitions', ['period_id' => $period_id]);
    $wpdb->delete($wpdb->prefix . 'habits_challenges', ['period_id' => $period_id]);
}

// Pobierz okresy dla roku
add_action('wp_ajax_habits_get_periods', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_periods';
    $year_id = intval($_POST['year_id']);

    $periods = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE year_id = %d ORDER BY data_start ASC",
        $year_id
    ));

    wp_send_json_success($periods);
});

// Dodaj okres
add_action('wp_ajax_habits_add_period', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_periods';

    $wpdb->insert($table, [
        'year_id' => intval($_POST['year_id']),
        'nazwa' => sanitize_text_field($_POST['nazwa']),
        'data_start' => sanitize_text_field($_POST['data_start']),
        'data_koniec' => sanitize_text_field($_POST['data_koniec'])
    ]);

    wp_send_json_success(['id' => $wpdb->insert_id]);
});

// Usuń okres
add_action('wp_ajax_habits_delete_period', function() {
    global $wpdb;
    $period_id = intval($_POST['id']);

    habits_delete_period_data($period_id);
    $wpdb->delete($wpdb->prefix . 'habits_periods', ['id' => $period_id]);

    wp_send_json_success();
});

// Kopiuj nawyki i challenge z jednego okresu do drugiego
add_action('wp_ajax_habits_copy_period', function() {
    global $wpdb;

    $from_period_id = intval($_POST['from_period_id']);
    $to_period_id = intval($_POST['to_period_id']);

    // Kopiuj nawyki
    $habits = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}habits_definitions WHERE period_id = %d",
        $from_period_id
    ));

    foreach ($habits as $habit) {
        $wpdb->insert($wpdb->prefix . 'habits_definitions', [
            'period_id' => $to_period_id,
            'nazwa' => $habit->nazwa,
            'cel_minut_dziennie' => $habit->cel_minut_dziennie,
            'kolor' => $habit->kolor,
            'ikona' => $habit->ikona,
            'aktywny' => $habit->aktywny,
            'pozycja' => $habit->pozycja
        ]);
    }

    // Kopiuj challenge
    $challenges = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}habits_challenges WHERE period_id = %d",
        $from_period_id
    ));

    foreach ($challenges as $challenge) {
        $wpdb->insert($wpdb->prefix . 'habits_challenges', [
            'period_id' => $to_period_id,
            'nazwa' => $challenge->nazwa,
            'opis' => $challenge->opis,
            'typ' => $challenge->typ,
            'cel_dni' => $challenge->cel_dni,
            'dni_w_tygodniu' => $challenge->dni_w_tygodniu,
            'ikona' => $challenge->ikona,
            'kolor' => $challenge->kolor,
            'aktywny' => $challenge->aktywny
        ]);
    }

    wp_send_json_success();
});

// Pobierz nawyki dla okresu
add_action('wp_ajax_habits_get_habits', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_definitions';
    $period_id = intval($_POST['period_id']);

    $habits = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE period_id = %d ORDER BY pozycja ASC, id ASC",
        $period_id
    ));

    wp_send_json_success($habits);
});

// Dodaj nawyk
add_action('wp_ajax_habits_add_habit', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_definitions';

    $wpdb->insert($table, [
        'period_id' => intval($_POST['period_id']),
        'nazwa' => sanitize_text_field($_POST['nazwa']),
        'cel_minut_dziennie' => intval($_POST['cel_minut_dziennie'] ?? 30),
        'kolor' => sanitize_hex_color($_POST['kolor'] ?? '#4A90D9'),
        'ikona' => sanitize_text_field($_POST['ikona'] ?? '📚')
    ]);

    wp_send_json_success(['id' => $wpdb->insert_id]);
});

// Edytuj nawyk
add_action('wp_ajax_habits_update_habit', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_definitions';

    $wpdb->update($table, [
        'nazwa' => sanitize_text_field($_POST['nazwa']),
        'cel_minut_dziennie' => intval($_POST['cel_minut_dziennie']),
        'kolor' => sanitize_hex_color($_POST['kolor']),
        'ikona' => sanitize_text_field($_POST['ikona']),
        'aktywny' => intval($_POST['aktywny'] ?? 1)
    ], ['id' => intval($_POST['id'])]);

    wp_send_json_success();
});

// Usuń nawyk
add_action('wp_ajax_habits_delete_habit', function() {
    global $wpdb;
    $habit_id = intval($_POST['id']);

    $wpdb->delete($wpdb->prefix . 'habits_entries', ['habit_id' => $habit_id]);
    $wpdb->delete($wpdb->prefix . 'habits_definitions', ['id' => $habit_id]);

    wp_send_json_success();
});

// Zapisz wpis (minuty na dany dzień)
add_action('wp_ajax_habits_save_entry', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_entries';

    $habit_id = intval($_POST['habit_id']);
    $dzien = sanitize_text_field($_POST['dzien']);
    $minuty = intval($_POST['minuty']);
    $notatka = sanitize_textarea_field($_POST['notatka'] ?? '');

    // Upsert
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE habit_id = %d AND dzien = %s",
        $habit_id, $dzien
    ));

    if ($existing) {
        $wpdb->update($table, [
            'minuty' => $minuty,
            'notatka' => $notatka
        ], ['id' => $existing]);
    } else {
        $wpdb->insert($table, [
            'habit_id' => $habit_id,
            'dzien' => $dzien,
            'minuty' => $minuty,
            'notatka' => $notatka
        ]);
    }

    // Aktualizuj XP i streak
    habits_update_stats();

    wp_send_json_success();
});

// Pobierz wpisy dla zakresu dat
add_action('wp_ajax_habits_get_entries', function() {
    global $wpdb;

    $period_id = intval($_POST['period_id']);
    $date_start = sanitize_text_field($_POST['date_start']);
    $date_end = sanitize_text_field($_POST['date_end']);

    $entries = $wpdb->get_results($wpdb->prepare("
        SELECT e.*, h.nazwa as habit_nazwa, h.kolor, h.ikona, h.cel_minut_dziennie
        FROM {$wpdb->prefix}habits_entries e
        JOIN {$wpdb->prefix}habits_definitions h ON e.habit_id = h.id
        WHERE h.period_id = %d AND e.dzien BETWEEN %s AND %s
        ORDER BY e.dzien ASC
    ", $period_id, $date_start, $date_end));

    wp_send_json_success($entries);
});

// Pobierz challenge dla okresu
add_action('wp_ajax_habits_get_challenges', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_challenges';
    $period_id = intval($_POST['period_id']);

    $challenges = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE period_id = %d ORDER BY id ASC",
        $period_id
    ));

    wp_send_json_success($challenges);
});

// Dodaj challenge
add_action('wp_ajax_habits_add_challenge', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_challenges';

    $wpdb->insert($table, [
        'period_id' => intval($_POST['period_id']),
        'nazwa' => sanitize_text_field($_POST['nazwa']),
        'opis' => sanitize_textarea_field($_POST['opis'] ?? ''),
        'typ' => sanitize_text_field($_POST['typ'] ?? 'weekly'),
        'cel_dni' => intval($_POST['cel_dni'] ?? 4),
        'dni_w_tygodniu' => intval($_POST['dni_w_tygodniu'] ?? 7),
        'ikona' => sanitize_text_field($_POST['ikona'] ?? '🎯'),
        'kolor' => sanitize_hex_color($_POST['kolor'] ?? '#E74C3C')
    ]);

    wp_send_json_success(['id' => $wpdb->insert_id]);
});

// Edytuj challenge
add_action('wp_ajax_habits_update_challenge', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_challenges';

    $wpdb->update($table, [
        'nazwa' => sanitize_text_field($_POST['nazwa']),
        'opis' => sanitize_textarea_field($_POST['opis'] ?? ''),
        'typ' => sanitize_text_field($_POST['typ']),
        'cel_dni' => intval($_POST['cel_dni']),
        'dni_w_tygodniu' => intval($_POST['dni_w_tygodniu']),
        'ikona' => sanitize_text_field($_POST['ikona']),
        'kolor' => sanitize_hex_color($_POST['kolor']),
        'aktywny' => intval($_POST['aktywny'] ?? 1)
    ], ['id' => intval($_POST['id'])]);

    wp_send_json_success();
});

// Usuń challenge
add_action('wp_ajax_habits_delete_challenge', function() {
    global $wpdb;
    $challenge_id = intval($_POST['id']);

    $wpdb->delete($wpdb->prefix . 'habits_challenge_checks', ['challenge_id' => $challenge_id]);
    $wpdb->delete($wpdb->prefix . 'habits_challenges', ['id' => $challenge_id]);

    wp_send_json_success();
});

// Checkuj/odcheckuj challenge na dany dzień
add_action('wp_ajax_habits_toggle_challenge', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_challenge_checks';

    $challenge_id = intval($_POST['challenge_id']);
    $dzien = sanitize_text_field($_POST['dzien']);

    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE challenge_id = %d AND dzien = %s",
        $challenge_id, $dzien
    ));

    if ($existing) {
        $new_value = $existing->wykonane ? 0 : 1;
        $wpdb->update($table, ['wykonane' => $new_value], ['id' => $existing->id]);
    } else {
        $wpdb->insert($table, [
            'challenge_id' => $challenge_id,
            'dzien' => $dzien,
            'wykonane' => 1
        ]);
    }

    wp_send_json_success();
});

// Pobierz checks dla challenge
add_action('wp_ajax_habits_get_challenge_checks', function() {
    global $wpdb;

    $challenge_id = intval($_POST['challenge_id']);
    $date_start = sanitize_text_field($_POST['date_start']);
    $date_end = sanitize_text_field($_POST['date_end']);

    $checks = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}habits_challenge_checks
        WHERE challenge_id = %d AND dzien BETWEEN %s AND %s
        ORDER BY dzien ASC
    ", $challenge_id, $date_start, $date_end));

    wp_send_json_success($checks);
});

// Toggle general challenge (jednorazowy)
add_action('wp_ajax_habits_toggle_general_challenge', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_challenges';

    $id = intval($_POST['id']);
    $completed = intval($_POST['completed']);

    $wpdb->update($table, ['completed' => $completed], ['id' => $id]);
    wp_send_json_success();
});

// Pobierz statystyki dla wykresu
add_action('wp_ajax_habits_get_chart_data', function() {
    global $wpdb;

    $period_id = intval($_POST['period_id']);
    $chart_type = sanitize_text_field($_POST['chart_type'] ?? 'weekly');

    // Pobierz okres
    $period = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}habits_periods WHERE id = %d",
        $period_id
    ));

    if (!$period) {
        wp_send_json_error('Period not found');
        return;
    }

    // Pobierz nawyki z tego okresu
    $habits = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}habits_definitions WHERE period_id = %d AND aktywny = 1",
        $period_id
    ));

    $data = [];

    if ($chart_type === 'weekly') {
        // Ostatnie 4 tygodnie
        for ($w = 3; $w >= 0; $w--) {
            $week_start = date('Y-m-d', strtotime("-$w weeks monday"));
            $week_end = date('Y-m-d', strtotime("-$w weeks sunday"));

            $week_data = ['week' => "Tydz. " . date('W', strtotime($week_start))];

            foreach ($habits as $habit) {
                $total = $wpdb->get_var($wpdb->prepare("
                    SELECT COALESCE(SUM(minuty), 0) FROM {$wpdb->prefix}habits_entries
                    WHERE habit_id = %d AND dzien BETWEEN %s AND %s
                ", $habit->id, $week_start, $week_end));

                $week_data[$habit->nazwa] = intval($total);
            }

            $data[] = $week_data;
        }
    } else {
        // Dzień po dniu w okresie
        $current = $period->data_start;
        while ($current <= $period->data_koniec && $current <= date('Y-m-d')) {
            $day_data = ['date' => $current, 'label' => date('d.m', strtotime($current))];

            foreach ($habits as $habit) {
                $minuty = $wpdb->get_var($wpdb->prepare("
                    SELECT COALESCE(minuty, 0) FROM {$wpdb->prefix}habits_entries
                    WHERE habit_id = %d AND dzien = %s
                ", $habit->id, $current));

                $day_data[$habit->nazwa] = intval($minuty);
            }

            $data[] = $day_data;
            $current = date('Y-m-d', strtotime('+1 day', strtotime($current)));
        }
    }

    wp_send_json_success([
        'data' => $data,
        'habits' => $habits
    ]);
});

// Pobierz podsumowanie statystyk
add_action('wp_ajax_habits_get_summary', function() {
    global $wpdb;

    $period_id = intval($_POST['period_id']);
    $year_id = isset($_POST['year_id']) ? intval($_POST['year_id']) : 0;
    $scope = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : 'period';

    // Determine periods based on scope
    $periods = [];

    if ($scope === 'period') {
        $period = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}habits_periods WHERE id = %d",
            $period_id
        ));
        if ($period) {
            $periods = [$period];
        }
    } elseif ($scope === 'year' && $year_id > 0) {
        $periods = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}habits_periods WHERE year_id = %d ORDER BY data_start ASC",
            $year_id
        ));
    } elseif ($scope === 'all') {
        $periods = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}habits_periods ORDER BY data_start ASC"
        );
    }

    if (empty($periods)) {
        wp_send_json_error('No periods found');
        return;
    }

    // Get all period IDs for query
    $period_ids = array_map(function($p) { return $p->id; }, $periods);
    $period_ids_placeholder = implode(',', array_map('intval', $period_ids));

    // Calculate overall date range
    $overall_start = min(array_map(function($p) { return $p->data_start; }, $periods));
    $overall_end = min(
        max(array_map(function($p) { return $p->data_koniec; }, $periods)),
        date('Y-m-d')
    );

    // Pobierz nawyki z wszystkich okresów
    $habits = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}habits_definitions WHERE period_id IN ($period_ids_placeholder) AND aktywny = 1"
    );

    // Group habits by name to aggregate across periods
    $habits_by_name = [];
    foreach ($habits as $habit) {
        $name = $habit->nazwa;
        if (!isset($habits_by_name[$name])) {
            $habits_by_name[$name] = [
                'habit' => $habit,
                'habit_ids' => [],
                'goal' => $habit->cel_minut_dziennie
            ];
        }
        $habits_by_name[$name]['habit_ids'][] = $habit->id;
    }

    $summary = [];

    foreach ($habits_by_name as $name => $data) {
        $habit_ids_str = implode(',', array_map('intval', $data['habit_ids']));
        $habit = $data['habit'];

        // Suma minut
        $total_minutes = $wpdb->get_var("
            SELECT COALESCE(SUM(minuty), 0) FROM {$wpdb->prefix}habits_entries
            WHERE habit_id IN ($habit_ids_str) AND dzien BETWEEN '$overall_start' AND '$overall_end'
        ");

        // Dni aktywne
        $active_days = $wpdb->get_var("
            SELECT COUNT(DISTINCT dzien) FROM {$wpdb->prefix}habits_entries
            WHERE habit_id IN ($habit_ids_str) AND dzien BETWEEN '$overall_start' AND '$overall_end' AND minuty > 0
        ");

        // Dni spełniające cel
        $goal_days = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT dzien) FROM {$wpdb->prefix}habits_entries
            WHERE habit_id IN ($habit_ids_str) AND dzien BETWEEN '$overall_start' AND '$overall_end' AND minuty >= %d
        ", $data['goal']));

        // Oblicz dni w zakresie (do dziś)
        $range_start = new DateTime($overall_start);
        $range_end = new DateTime($overall_end);
        $total_days = $range_start->diff($range_end)->days + 1;

        // Średnia dzienna
        $avg_daily = $total_days > 0 ? round($total_minutes / $total_days, 1) : 0;

        // Aktualny streak (use first habit id)
        $streak = habits_calculate_streak($data['habit_ids'][0]);

        $summary[] = [
            'habit' => $habit,
            'total_minutes' => intval($total_minutes),
            'total_hours' => round($total_minutes / 60, 1),
            'active_days' => intval($active_days),
            'goal_days' => intval($goal_days),
            'total_days' => $total_days,
            'avg_daily' => $avg_daily,
            'goal_percentage' => $total_days > 0 ? round(($goal_days / $total_days) * 100) : 0,
            'streak' => $streak
        ];
    }

    // Challenge summary
    $challenges = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}habits_challenges WHERE period_id IN ($period_ids_placeholder) AND aktywny = 1"
    );

    // Group challenges by name
    $challenges_by_name = [];
    foreach ($challenges as $challenge) {
        $name = $challenge->nazwa;
        if (!isset($challenges_by_name[$name])) {
            $challenges_by_name[$name] = [
                'challenge' => $challenge,
                'challenge_ids' => [],
                'goal' => $challenge->cel_dni
            ];
        }
        $challenges_by_name[$name]['challenge_ids'][] = $challenge->id;
    }

    $challenge_summary = [];

    foreach ($challenges_by_name as $name => $data) {
        $challenge_ids_str = implode(',', array_map('intval', $data['challenge_ids']));
        $challenge = $data['challenge'];

        $completed_days = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}habits_challenge_checks
            WHERE challenge_id IN ($challenge_ids_str) AND dzien BETWEEN '$overall_start' AND '$overall_end' AND wykonane = 1
        ");

        // Oblicz tygodnie
        $weeks_in_range = ceil((strtotime($overall_end) - strtotime($overall_start)) / (7 * 24 * 60 * 60));
        $expected = $weeks_in_range * $data['goal'];

        $challenge_summary[] = [
            'challenge' => $challenge,
            'completed_days' => intval($completed_days),
            'expected_days' => $expected,
            'percentage' => $expected > 0 ? round(($completed_days / $expected) * 100) : 0
        ];
    }

    wp_send_json_success([
        'habits' => $summary,
        'challenges' => $challenge_summary,
        'scope' => $scope,
        'date_range' => [
            'start' => $overall_start,
            'end' => $overall_end
        ]
    ]);
});

function habits_calculate_streak($habit_id) {
    global $wpdb;

    $streak = 0;
    $date = date('Y-m-d');

    // Sprawdź czy dziś jest wpis
    $today = $wpdb->get_var($wpdb->prepare("
        SELECT minuty FROM {$wpdb->prefix}habits_entries
        WHERE habit_id = %d AND dzien = %s
    ", $habit_id, $date));

    // Jeśli dziś nie ma wpisu, zacznij od wczoraj
    if (!$today || $today == 0) {
        $date = date('Y-m-d', strtotime('-1 day'));
    }

    // Licz streak wstecz
    while (true) {
        $minuty = $wpdb->get_var($wpdb->prepare("
            SELECT minuty FROM {$wpdb->prefix}habits_entries
            WHERE habit_id = %d AND dzien = %s
        ", $habit_id, $date));

        if ($minuty && $minuty > 0) {
            $streak++;
            $date = date('Y-m-d', strtotime('-1 day', strtotime($date)));
        } else {
            break;
        }

        // Limit żeby nie zapętlić
        if ($streak > 365) break;
    }

    return $streak;
}

function habits_update_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_stats';

    // Pobierz lub stwórz stats
    $stats = $wpdb->get_row("SELECT * FROM $table LIMIT 1");

    if (!$stats) {
        $wpdb->insert($table, ['total_xp' => 0]);
        $stats = $wpdb->get_row("SELECT * FROM $table LIMIT 1");
    }

    // Oblicz XP - 1 XP za każdą minutę
    $total_xp = $wpdb->get_var("SELECT COALESCE(SUM(minuty), 0) FROM {$wpdb->prefix}habits_entries");

    // Oblicz globalny streak
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // Sprawdź czy jest jakikolwiek wpis dziś
    $today_entries = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->prefix}habits_entries WHERE dzien = %s AND minuty > 0
    ", $today));

    $check_date = $today_entries > 0 ? $today : $yesterday;
    $streak = 0;

    while (true) {
        $day_entries = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}habits_entries WHERE dzien = %s AND minuty > 0
        ", $check_date));

        if ($day_entries > 0) {
            $streak++;
            $check_date = date('Y-m-d', strtotime('-1 day', strtotime($check_date)));
        } else {
            break;
        }

        if ($streak > 365) break;
    }

    $best_streak = max($stats->best_streak ?? 0, $streak);

    $wpdb->update($table, [
        'total_xp' => intval($total_xp),
        'current_streak' => $streak,
        'best_streak' => $best_streak,
        'last_active_date' => $today
    ], ['id' => $stats->id]);
}

// Pobierz globalne statystyki
add_action('wp_ajax_habits_get_global_stats', function() {
    global $wpdb;

    habits_update_stats();

    $stats = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}habits_stats LIMIT 1");

    // Oblicz poziom (co 1000 XP)
    $level = floor(($stats->total_xp ?? 0) / 1000) + 1;
    $xp_in_level = ($stats->total_xp ?? 0) % 1000;

    wp_send_json_success([
        'total_xp' => intval($stats->total_xp ?? 0),
        'level' => $level,
        'xp_in_level' => $xp_in_level,
        'xp_to_next' => 1000,
        'current_streak' => intval($stats->current_streak ?? 0),
        'best_streak' => intval($stats->best_streak ?? 0)
    ]);
});

// =============================================
// SPORT AJAX HANDLERS
// =============================================

// Zapisz aktywność sportową
add_action('wp_ajax_habits_save_sport', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_sport';

    $dzien = sanitize_text_field($_POST['dzien']);
    $typ = sanitize_text_field($_POST['typ']);
    $czas = intval($_POST['czas_minuty'] ?? 0);
    $kroki = intval($_POST['kroki'] ?? 0);
    $partie = sanitize_text_field($_POST['partie_ciala'] ?? '');
    $notatka = sanitize_textarea_field($_POST['notatka'] ?? '');
    $custom_name = sanitize_text_field($_POST['custom_name'] ?? '');

    // Dla kroków - upsert po dniu
    if ($typ === 'kroki') {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE dzien = %s AND typ = 'kroki'",
            $dzien
        ));

        if ($existing) {
            $wpdb->update($table, [
                'kroki' => $kroki
            ], ['id' => $existing]);
        } else {
            $wpdb->insert($table, [
                'dzien' => $dzien,
                'typ' => 'kroki',
                'czas_minuty' => 0,
                'kroki' => $kroki,
                'partie_ciala' => '',
                'notatka' => $notatka
            ]);
        }
    } else {
        // Dla aktywności - zawsze dodawaj nowy wpis (można mieć kilka aktywności dziennie)
        $wpdb->insert($table, [
            'dzien' => $dzien,
            'typ' => $typ,
            'czas_minuty' => $czas,
            'kroki' => $kroki,
            'partie_ciala' => $partie,
            'custom_name' => $custom_name ?: null,
            'notatka' => $notatka
        ]);
    }

    wp_send_json_success();
});

// Pobierz aktywności sportowe dla zakresu dat
add_action('wp_ajax_habits_get_sport', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_sport';

    $date_start = sanitize_text_field($_POST['date_start']);
    $date_end = sanitize_text_field($_POST['date_end']);
    $typ = isset($_POST['typ']) ? sanitize_text_field($_POST['typ']) : 'all';

    if ($typ && $typ !== 'all') {
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE dzien BETWEEN %s AND %s AND typ = %s ORDER BY dzien DESC",
            $date_start, $date_end, $typ
        ));
    } else {
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE dzien BETWEEN %s AND %s ORDER BY dzien DESC, typ ASC",
            $date_start, $date_end
        ));
    }

    wp_send_json_success($entries);
});

// Usuń aktywność sportową
add_action('wp_ajax_habits_delete_sport', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_sport';

    $id = intval($_POST['id']);
    $wpdb->delete($table, ['id' => $id]);

    wp_send_json_success();
});

// Pobierz podsumowanie sportowe
add_action('wp_ajax_habits_get_sport_summary', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_sport';

    $date_start = sanitize_text_field($_POST['date_start']);
    $date_end = sanitize_text_field($_POST['date_end']);

    // Suma kroków
    $total_steps = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(kroki), 0) FROM $table WHERE dzien BETWEEN %s AND %s",
        $date_start, $date_end
    ));

    // Średnia kroków
    $days_with_steps = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT dzien) FROM $table WHERE dzien BETWEEN %s AND %s AND kroki > 0",
        $date_start, $date_end
    ));
    $avg_steps = $days_with_steps > 0 ? round($total_steps / $days_with_steps) : 0;

    // Ilość treningów per typ
    $by_type = $wpdb->get_results($wpdb->prepare(
        "SELECT typ, COUNT(*) as count, SUM(czas_minuty) as total_minutes
         FROM $table WHERE dzien BETWEEN %s AND %s GROUP BY typ",
        $date_start, $date_end
    ));

    // Partie ciała (dla siłowni)
    $muscle_counts = [];
    $gym_entries = $wpdb->get_col($wpdb->prepare(
        "SELECT partie_ciala FROM $table WHERE dzien BETWEEN %s AND %s AND typ = 'silownia' AND partie_ciala != ''",
        $date_start, $date_end
    ));
    foreach ($gym_entries as $parties) {
        foreach (explode(',', $parties) as $part) {
            $part = trim($part);
            if ($part) {
                $muscle_counts[$part] = ($muscle_counts[$part] ?? 0) + 1;
            }
        }
    }

    wp_send_json_success([
        'total_steps' => intval($total_steps),
        'avg_steps' => intval($avg_steps),
        'days_with_steps' => intval($days_with_steps),
        'by_type' => $by_type,
        'muscle_counts' => $muscle_counts
    ]);
});

// Zapisz próg kroków
add_action('wp_ajax_habits_save_steps_threshold', function() {
    $threshold = intval($_POST['threshold']);
    update_option('habits_steps_threshold', $threshold);
    wp_send_json_success();
});

// Pobierz kroki (ostatnie 14 dni)
add_action('wp_ajax_habits_get_steps', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_sport';

    $days = intval($_POST['days'] ?? 14);
    $start_date = date('Y-m-d', strtotime("-{$days} days"));

    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE typ = 'kroki' AND dzien >= %s ORDER BY dzien DESC",
        $start_date
    ));

    $threshold = get_option('habits_steps_threshold', 10000);

    wp_send_json_success([
        'entries' => $entries,
        'threshold' => intval($threshold)
    ]);
});

// Dodaj kroki jako aktywność
add_action('wp_ajax_habits_steps_to_activity', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_sport';

    $dzien = sanitize_text_field($_POST['dzien']);
    $kroki = intval($_POST['kroki']);

    // Sprawdź czy już jest aktywność "spacer" dla tego dnia
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE dzien = %s AND typ = 'spacer'",
        $dzien
    ));

    if (!$existing) {
        // Dodaj jako spacer (szacuj 1 min na 100 kroków = ~6km/h)
        $minutes = round($kroki / 100);
        $wpdb->insert($table, [
            'dzien' => $dzien,
            'typ' => 'spacer',
            'czas_minuty' => $minutes,
            'kroki' => $kroki,
            'notatka' => 'Dodane z kroków'
        ]);
    }

    wp_send_json_success();
});

// Oznacz kroki jako skonwertowane do aktywności
add_action('wp_ajax_habits_mark_steps_converted', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'habits_sport';
    $id = intval($_POST['id']);

    $wpdb->update($table, ['converted_to_activity' => 1], ['id' => $id]);
    wp_send_json_success();
});

// =============================================
// MENU I STRONA
// =============================================
add_action('admin_menu', function() {
    add_menu_page(
        'Habit Tracker',
        'Habit Tracker',
        'manage_options',
        'habit-tracker',
        'habits_render_page',
        'dashicons-chart-line',
        30
    );
});

function habits_render_page() {
    $current_period = habits_get_current_period();
    $current_year = habits_get_current_year();
    ?>
    <style>
        :root {
            --habits-primary: #6366F1;
            --habits-secondary: #8B5CF6;
            --habits-success: #10B981;
            --habits-warning: #F59E0B;
            --habits-danger: #EF4444;
            --habits-dark: #1F2937;
            --habits-light: #F3F4F6;
            --habits-card-bg: #FFFFFF;
            --habits-border: #E5E7EB;
        }

        .habits-wrap {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .habits-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .habits-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--habits-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .habits-stats-bar {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .habits-stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: linear-gradient(135deg, var(--habits-primary), var(--habits-secondary));
            border-radius: 20px;
            color: white;
            font-weight: 600;
        }

        .habits-stat-item.streak {
            background: linear-gradient(135deg, var(--habits-warning), #F97316);
        }

        .habits-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--habits-border);
            padding-bottom: 0;
        }

        .habits-tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #6B7280;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .habits-tab:hover {
            color: var(--habits-primary);
        }

        .habits-tab.active {
            color: var(--habits-primary);
            border-bottom-color: var(--habits-primary);
        }

        .habits-tab-content {
            display: none;
        }

        .habits-tab-content.active {
            display: block;
        }

        .habits-card {
            background: var(--habits-card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--habits-border);
        }

        .habits-card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--habits-dark);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .habits-selectors {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .habits-select {
            padding: 10px 16px;
            border: 2px solid var(--habits-border);
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
            background: white;
            cursor: pointer;
        }

        .habits-select:focus {
            border-color: var(--habits-primary);
            outline: none;
        }

        .habits-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .habits-btn-primary {
            background: var(--habits-primary);
            color: white;
        }

        .habits-btn-primary:hover {
            background: #4F46E5;
        }

        .habits-btn-secondary {
            background: var(--habits-light);
            color: var(--habits-dark);
        }

        .habits-btn-secondary:hover {
            background: #E5E7EB;
        }

        .habits-btn-success {
            background: var(--habits-success);
            color: white;
        }

        .habits-btn-danger {
            background: var(--habits-danger);
            color: white;
        }

        .habits-btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Tabela nawyków */
        .habits-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .habits-table th,
        .habits-table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid var(--habits-border);
        }

        .habits-table th {
            background: var(--habits-light);
            font-weight: 600;
            color: var(--habits-dark);
        }

        .habits-table th:first-child,
        .habits-table td:first-child {
            text-align: left;
            min-width: 150px;
        }

        .habits-table td.day-cell {
            padding: 8px;
        }

        .habit-name {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .habit-icon {
            font-size: 20px;
        }

        .habit-label {
            font-weight: 500;
        }

        .habit-goal {
            font-size: 11px;
            color: #9CA3AF;
        }

        .day-header {
            font-size: 12px;
        }

        .day-header .day-name {
            font-weight: 600;
            color: var(--habits-dark);
        }

        .day-header .day-date {
            color: #9CA3AF;
        }

        .day-header.today {
            background: var(--habits-primary);
            color: white;
            border-radius: 8px;
            padding: 8px;
        }

        .day-header.today .day-date {
            color: rgba(255,255,255,0.8);
        }

        .day-header.out-of-period {
            opacity: 0.35;
        }

        .day-cell.out-of-period {
            background: #f5f5f5;
        }

        .habit-cell.disabled {
            pointer-events: none;
        }

        .habit-check.disabled {
            background: #e5e5e5;
            border-color: #d0d0d0;
            cursor: not-allowed;
        }

        .challenge-check.disabled {
            background: #e5e5e5;
            border-color: #d0d0d0;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Challenge checkbox */
        .challenge-check {
            width: 32px;
            height: 32px;
            border: 2px solid var(--habits-border);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            transition: all 0.2s;
            background: white;
        }

        .challenge-check:hover {
            border-color: var(--habits-primary);
        }

        .challenge-check.checked {
            background: var(--habits-success);
            border-color: var(--habits-success);
            color: white;
        }

        .challenge-check.checked::after {
            content: '✓';
            font-size: 18px;
            font-weight: bold;
        }

        /* Progress bars */
        .progress-bar {
            height: 8px;
            background: var(--habits-light);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }

        /* Summary scope buttons */
        .summary-scope-buttons {
            display: flex;
            gap: 10px;
        }

        /* Summary cards */
        .habits-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .summary-card {
            background: var(--habits-card-bg);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--habits-border);
        }

        .summary-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .summary-title {
            font-weight: 600;
            color: var(--habits-dark);
        }

        .summary-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .summary-stat {
            text-align: center;
            padding: 10px;
            background: var(--habits-light);
            border-radius: 8px;
        }

        .summary-stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--habits-primary);
        }

        .summary-stat-label {
            font-size: 11px;
            color: #6B7280;
            margin-top: 2px;
        }

        /* Charts */
        .chart-container {
            height: 300px;
            margin-top: 20px;
        }

        /* Sport */
        .muscle-groups {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }

        .muscle-checkbox {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: var(--habits-light);
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            user-select: none;
        }

        .muscle-checkbox:hover {
            background: #E0E7FF;
        }

        .muscle-checkbox input {
            display: none;
        }

        .muscle-checkbox:has(input:checked) {
            background: var(--habits-primary);
            color: white;
        }

        .sport-entry {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: var(--habits-light);
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .sport-entry-icon {
            font-size: 24px;
        }

        .sport-entry-info {
            flex: 1;
        }

        .sport-entry-type {
            font-weight: 600;
            color: var(--habits-dark);
        }

        .sport-entry-details {
            font-size: 12px;
            color: #6B7280;
        }

        .sport-entry-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--habits-primary);
        }

        .sport-entry-date {
            font-size: 11px;
            color: #9CA3AF;
        }

        .sport-stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .sport-stat-card {
            background: var(--habits-light);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }

        .sport-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--habits-primary);
        }

        .sport-stat-label {
            font-size: 12px;
            color: #6B7280;
            margin-top: 4px;
        }

        .muscle-tag {
            display: inline-block;
            padding: 3px 8px;
            background: var(--habits-secondary);
            color: white;
            border-radius: 12px;
            font-size: 11px;
            margin: 2px;
        }

        /* Forms */
        .habits-form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .habits-form-group {
            flex: 1;
            min-width: 200px;
        }

        .habits-form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--habits-dark);
            margin-bottom: 6px;
        }

        .habits-form-input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--habits-border);
            border-radius: 8px;
            font-size: 14px;
        }

        .habits-form-input:focus {
            border-color: var(--habits-primary);
            outline: none;
        }

        /* Modal */
        .habits-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            display: none;
        }

        .habits-modal-overlay.active {
            display: flex;
        }

        .habits-modal {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .habits-modal-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--habits-dark);
        }

        .habits-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        /* Period/Year management */
        .management-list {
            margin-top: 15px;
        }

        .management-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background: var(--habits-light);
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .management-item-info {
            display: flex;
            flex-direction: column;
        }

        .management-item-name {
            font-weight: 600;
            color: var(--habits-dark);
        }

        .management-item-dates {
            font-size: 12px;
            color: #6B7280;
        }

        .management-item-actions {
            display: flex;
            gap: 8px;
        }

        /* Week navigation */
        .week-nav {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .week-nav-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 2px solid var(--habits-border);
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .week-nav-btn:hover {
            border-color: var(--habits-primary);
            color: var(--habits-primary);
        }

        .week-nav-label {
            font-weight: 600;
            color: var(--habits-dark);
        }

        /* Habit check cell */
        .habit-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .habit-check {
            width: 36px;
            height: 36px;
            border: 2px solid var(--habits-border);
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            background: white;
            font-size: 18px;
        }

        .habit-check:hover {
            border-color: var(--habits-primary);
            transform: scale(1.05);
        }

        .habit-check.checked {
            background: var(--habits-success);
            border-color: var(--habits-success);
            color: white;
        }

        .habit-check.checked::after {
            content: '✓';
            font-weight: bold;
        }

        .habit-minutes {
            font-size: 11px;
            color: var(--habits-success);
            font-weight: 600;
            min-height: 16px;
        }

        .habit-minutes-input {
            width: 50px;
            padding: 4px;
            border: 2px solid var(--habits-primary);
            border-radius: 6px;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
        }

        .habit-minutes-input:focus {
            outline: none;
            border-color: var(--habits-secondary);
        }

        /* Responsive */
        @media (max-width: 900px) {
            .habits-wrap {
                padding: 0 10px;
            }

            .habits-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }

            .habits-tabs::-webkit-scrollbar {
                display: none;
            }

            .habits-tab {
                padding: 10px 14px;
                font-size: 12px;
                white-space: nowrap;
            }

            .habits-table {
                font-size: 11px;
            }

            .habits-table th,
            .habits-table td {
                padding: 6px 4px;
            }

            .habit-name {
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
            }

            .habit-icon {
                font-size: 16px;
            }

            .habit-label {
                font-size: 11px;
            }

            .habit-goal {
                font-size: 9px;
            }

            .habit-check {
                width: 32px;
                height: 32px;
                font-size: 14px;
            }

            .challenge-check {
                width: 28px;
                height: 28px;
                font-size: 14px;
            }

            .day-header {
                font-size: 10px;
            }

            .day-header.today {
                padding: 4px;
            }

            .week-nav {
                flex-wrap: wrap;
                gap: 8px;
            }

            .week-nav-label {
                font-size: 13px;
                order: -1;
                width: 100%;
                text-align: center;
            }

            .habits-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .habits-title {
                font-size: 22px;
            }

            .habits-stats-bar {
                width: 100%;
                justify-content: space-between;
            }

            .habits-stat-item {
                padding: 6px 12px;
                font-size: 12px;
            }

            .habits-selectors {
                flex-direction: column;
                gap: 10px;
            }

            .habits-select {
                width: 100%;
                min-width: auto;
            }

            .habits-card {
                padding: 15px;
            }

            .habits-summary-grid {
                grid-template-columns: 1fr;
            }

            .summary-stat-value {
                font-size: 20px;
            }

            .habits-modal {
                padding: 20px;
                width: 95%;
            }

            .habits-form-group {
                min-width: 100%;
            }

            .management-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .management-item-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .muscle-groups {
                gap: 6px;
            }

            .muscle-checkbox {
                padding: 6px 10px;
                font-size: 12px;
            }

            .sport-entry {
                flex-wrap: wrap;
                gap: 8px;
            }

            .sport-entry-icon {
                font-size: 20px;
            }

            .sport-stat-grid {
                grid-template-columns: 1fr 1fr;
            }

            .sport-stat-value {
                font-size: 20px;
            }
        }

        @media (max-width: 400px) {
            .habit-check {
                width: 28px;
                height: 28px;
            }

            .habits-table th:first-child,
            .habits-table td:first-child {
                min-width: 80px;
                max-width: 100px;
            }

            .habit-label {
                font-size: 10px;
                word-break: break-word;
            }
        }
    </style>

    <div class="habits-wrap">
        <div class="habits-header">
            <h1 class="habits-title">
                <span>📊</span> Habit Tracker
            </h1>
            <div class="habits-stats-bar">
                <div class="habits-stat-item">
                    <span>⭐</span>
                    <span id="globalLevel">Lvl 1</span>
                    <span id="globalXP">(0 XP)</span>
                </div>
                <div class="habits-stat-item streak">
                    <span>🔥</span>
                    <span id="globalStreak">0 dni</span>
                </div>
            </div>
        </div>

        <div class="habits-selectors">
            <select class="habits-select" id="yearSelect">
                <option value="">-- Wybierz rok --</option>
            </select>
            <select class="habits-select" id="periodSelect" disabled>
                <option value="">-- Wybierz okres --</option>
            </select>
            <button class="habits-btn habits-btn-secondary" onclick="HabitsApp.openManageModal()">
                ⚙️ Zarządzaj
            </button>
        </div>

        <div class="habits-tabs">
            <button class="habits-tab active" data-tab="tracking">📝 Logowanie</button>
            <button class="habits-tab" data-tab="sport">🏃 Sport</button>
            <button class="habits-tab" data-tab="challenges">🎯 Challenge</button>
            <button class="habits-tab" data-tab="summary">📊 Podsumowanie</button>
            <button class="habits-tab" data-tab="charts">📈 Wykresy</button>
            <button class="habits-tab" data-tab="settings">⚙️ Nawyki</button>
        </div>

        <!-- TAB: Logowanie -->
        <div class="habits-tab-content active" id="tab-tracking">
            <div class="habits-card">
                <div class="week-nav">
                    <button class="week-nav-btn" onclick="HabitsApp.prevWeek()">←</button>
                    <span class="week-nav-label" id="weekLabel">Tydzień</span>
                    <button class="week-nav-btn" onclick="HabitsApp.nextWeek()">→</button>
                    <button class="habits-btn habits-btn-sm habits-btn-secondary" onclick="HabitsApp.goToToday()">Dziś</button>
                </div>

                <div id="trackingTable">
                    <p style="color: #6B7280; text-align: center; padding: 40px;">
                        Wybierz rok i okres, aby rozpocząć logowanie nawyków.
                    </p>
                </div>
            </div>
        </div>

        <!-- TAB: Sport -->
        <div class="habits-tab-content" id="tab-sport">
            <!-- SEKCJA KROKI -->
            <div class="habits-card">
                <div class="habits-card-title">
                    👟 Kroki
                    <div style="margin-left: auto; display: flex; gap: 10px; align-items: center;">
                        <span style="font-size: 12px; color: #6B7280;">Próg aktywności:</span>
                        <input type="number" class="habits-form-input" id="stepsThreshold"
                               style="width: 100px; padding: 6px 10px; font-size: 12px;"
                               placeholder="np. 10000" value="<?php echo get_option('habits_steps_threshold', 10000); ?>"
                               onchange="HabitsApp.saveStepsThreshold()">
                    </div>
                </div>

                <div class="steps-table-container">
                    <table class="habits-table" id="stepsTable">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Data</th>
                                <th style="text-align: right;">Kroki</th>
                                <th style="width: 100px;"></th>
                            </tr>
                        </thead>
                        <tbody id="stepsTableBody">
                        </tbody>
                    </table>
                    <div style="margin-top: 15px; display: flex; gap: 10px; align-items: center;">
                        <input type="date" class="habits-form-input" id="newStepsDate"
                               value="<?php echo date('Y-m-d', strtotime('-1 day')); ?>" style="width: 150px;">
                        <input type="number" class="habits-form-input" id="newStepsValue"
                               placeholder="Liczba kroków" min="0" style="width: 150px;">
                        <button class="habits-btn habits-btn-primary" onclick="HabitsApp.addSteps()">+ Dodaj</button>
                    </div>
                </div>
            </div>

            <!-- SEKCJA AKTYWNOŚCI -->
            <div class="habits-card">
                <div class="habits-card-title">
                    🏃 Aktywności
                    <button class="habits-btn habits-btn-sm habits-btn-secondary" onclick="HabitsApp.openActivityTypesModal()" style="margin-left: auto;">
                        ⚙️ Typy
                    </button>
                </div>

                <div class="sport-form">
                    <div class="habits-form-row">
                        <div class="habits-form-group" style="flex: 0 0 140px;">
                            <label class="habits-form-label">Data</label>
                            <input type="date" class="habits-form-input" id="sportDate" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="habits-form-group" style="flex: 0 0 180px;">
                            <label class="habits-form-label">Typ aktywności</label>
                            <select class="habits-form-input" id="sportType" onchange="HabitsApp.onSportTypeChange()">
                                <option value="silownia">🏋️ Siłownia</option>
                                <option value="bieganie">🏃 Bieganie</option>
                                <option value="rower">🚴 Rower</option>
                                <option value="plywanie">🏊 Pływanie</option>
                                <option value="padel">🎾 Padel</option>
                                <option value="inne">⚡ Inne...</option>
                            </select>
                        </div>
                        <div class="habits-form-group" id="customActivityGroup" style="display: none; flex: 0 0 150px;">
                            <label class="habits-form-label">Nazwa</label>
                            <input type="text" class="habits-form-input" id="customActivityName" placeholder="Wpisz nazwę">
                        </div>
                        <div class="habits-form-group">
                            <label class="habits-form-label">Czas (min)</label>
                            <input type="number" class="habits-form-input" id="sportDuration" placeholder="np. 60" min="0">
                        </div>
                    </div>

                    <!-- Partie ciała dla siłowni -->
                    <div id="muscleGroupsSection" style="display: none;">
                        <label class="habits-form-label">Partie mięśniowe</label>
                        <div class="muscle-groups">
                            <label class="muscle-checkbox">
                                <input type="checkbox" value="klata"> 💪 Klata
                            </label>
                            <label class="muscle-checkbox">
                                <input type="checkbox" value="plecy"> 🔙 Plecy
                            </label>
                            <label class="muscle-checkbox">
                                <input type="checkbox" value="barki"> 🦾 Barki
                            </label>
                            <label class="muscle-checkbox">
                                <input type="checkbox" value="biceps"> 💪 Biceps
                            </label>
                            <label class="muscle-checkbox">
                                <input type="checkbox" value="triceps"> 💪 Triceps
                            </label>
                            <label class="muscle-checkbox">
                                <input type="checkbox" value="nogi"> 🦵 Nogi
                            </label>
                            <label class="muscle-checkbox">
                                <input type="checkbox" value="posladki"> 🍑 Pośladki
                            </label>
                            <label class="muscle-checkbox">
                                <input type="checkbox" value="brzuch"> 🎯 Brzuch
                            </label>
                        </div>
                    </div>

                    <div style="margin-top: 15px;">
                        <button class="habits-btn habits-btn-primary" onclick="HabitsApp.saveActivity()">
                            💾 Zapisz aktywność
                        </button>
                    </div>
                </div>
            </div>

            <!-- Historia aktywności -->
            <div class="habits-card">
                <div class="habits-card-title">
                    📊 Historia aktywności
                    <div style="margin-left: auto; display: flex; gap: 10px; align-items: center;">
                        <select class="habits-form-input" id="sportHistoryFilter" onchange="HabitsApp.loadSportHistory()" style="width: auto; padding: 6px 10px; font-size: 12px;">
                            <option value="7">Ostatnie 7 dni</option>
                            <option value="30">Ostatnie 30 dni</option>
                            <option value="90">Ostatnie 90 dni</option>
                            <option value="all">Cała historia</option>
                        </select>
                    </div>
                </div>
                <div id="sportHistory">
                    <p style="color: #6B7280; text-align: center;">Ładowanie...</p>
                </div>
            </div>

            <!-- Statystyki -->
            <div class="habits-card">
                <div class="habits-card-title">📈 Statystyki (bieżący miesiąc)</div>
                <div id="sportStats">
                    <p style="color: #6B7280; text-align: center;">Ładowanie...</p>
                </div>
            </div>
        </div>

        <!-- TAB: Challenge -->
        <div class="habits-tab-content" id="tab-challenges">
            <!-- Challenge tygodniowe -->
            <div class="habits-card">
                <div class="habits-card-title">
                    📅 Challenge tygodniowe
                    <button class="habits-btn habits-btn-sm habits-btn-primary" onclick="HabitsApp.openAddChallengeModal('weekly')" style="margin-left: auto;">
                        + Dodaj
                    </button>
                </div>

                <div class="week-nav">
                    <button class="week-nav-btn" onclick="HabitsApp.prevWeek()">←</button>
                    <span class="week-nav-label" id="weekLabelChallenge">Tydzień</span>
                    <button class="week-nav-btn" onclick="HabitsApp.nextWeek()">→</button>
                    <button class="habits-btn habits-btn-sm habits-btn-secondary" onclick="HabitsApp.goToToday()">Dziś</button>
                </div>

                <div id="weeklyChallengesTable">
                    <p style="color: #6B7280; text-align: center; padding: 20px;">
                        Wybierz okres, aby zobaczyć challenge.
                    </p>
                </div>
            </div>

            <!-- Challenge ogólne (jednorazowe) -->
            <div class="habits-card">
                <div class="habits-card-title">
                    🎯 Challenge ogólne
                    <button class="habits-btn habits-btn-sm habits-btn-primary" onclick="HabitsApp.openAddChallengeModal('general')" style="margin-left: auto;">
                        + Dodaj
                    </button>
                </div>

                <div id="generalChallengesTable">
                    <p style="color: #6B7280; text-align: center; padding: 20px;">
                        Wybierz okres, aby zobaczyć challenge.
                    </p>
                </div>
            </div>
        </div>

        <!-- TAB: Podsumowanie -->
        <div class="habits-tab-content" id="tab-summary">
            <div class="habits-card" style="margin-bottom: 20px;">
                <div class="habits-card-title">Zakres podsumowania</div>
                <div class="summary-scope-buttons">
                    <button class="habits-btn habits-btn-sm habits-btn-primary" id="btnScopePeriod" onclick="HabitsApp.setSummaryScope('period')">Okres</button>
                    <button class="habits-btn habits-btn-sm habits-btn-secondary" id="btnScopeYear" onclick="HabitsApp.setSummaryScope('year')">Rok</button>
                    <button class="habits-btn habits-btn-sm habits-btn-secondary" id="btnScopeAll" onclick="HabitsApp.setSummaryScope('all')">Wszystkie</button>
                </div>
            </div>
            <div id="summaryContent">
                <p style="color: #6B7280; text-align: center; padding: 40px;">
                    Wybierz okres, aby zobaczyć podsumowanie.
                </p>
            </div>
        </div>

        <!-- TAB: Wykresy -->
        <div class="habits-tab-content" id="tab-charts">
            <div class="habits-card">
                <div class="habits-card-title">📈 Postępy w czasie</div>
                <div style="margin-bottom: 15px;">
                    <button class="habits-btn habits-btn-sm habits-btn-secondary" onclick="HabitsApp.loadChart('weekly')" id="btnChartWeekly">Tygodniowo</button>
                    <button class="habits-btn habits-btn-sm habits-btn-secondary" onclick="HabitsApp.loadChart('daily')" id="btnChartDaily">Dziennie</button>
                </div>
                <div class="chart-container">
                    <canvas id="habitsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- TAB: Nawyki (settings) -->
        <div class="habits-tab-content" id="tab-settings">
            <div class="habits-card">
                <div class="habits-card-title">
                    📚 Zarządzaj nawykami
                    <button class="habits-btn habits-btn-sm habits-btn-primary" onclick="HabitsApp.openAddHabitModal()" style="margin-left: auto;">
                        + Dodaj nawyk
                    </button>
                </div>

                <div id="habitsManageList">
                    <p style="color: #6B7280; text-align: center; padding: 40px;">
                        Wybierz okres, aby zarządzać nawykami.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Zarządzanie latami/okresami -->
    <div class="habits-modal-overlay" id="manageModal">
        <div class="habits-modal" style="max-width: 700px;">
            <div class="habits-modal-title">⚙️ Zarządzaj latami i okresami</div>

            <div class="habits-card" style="margin-bottom: 20px;">
                <div class="habits-card-title">📅 Lata</div>
                <div class="habits-form-row">
                    <div class="habits-form-group">
                        <input type="text" class="habits-form-input" id="newYearName" placeholder="Nazwa (np. 2025)">
                    </div>
                    <div class="habits-form-group">
                        <input type="date" class="habits-form-input" id="newYearStart">
                    </div>
                    <div class="habits-form-group">
                        <input type="date" class="habits-form-input" id="newYearEnd">
                    </div>
                    <button class="habits-btn habits-btn-primary" onclick="HabitsApp.addYear()">Dodaj</button>
                </div>
                <div class="management-list" id="yearsList"></div>
            </div>

            <div class="habits-card">
                <div class="habits-card-title">📆 Okresy</div>
                <div class="habits-form-row">
                    <div class="habits-form-group">
                        <select class="habits-form-input" id="periodYearSelect">
                            <option value="">Wybierz rok</option>
                        </select>
                    </div>
                </div>
                <div class="habits-form-row">
                    <div class="habits-form-group">
                        <input type="text" class="habits-form-input" id="newPeriodName" placeholder="Nazwa (np. Q1, Styczeń)">
                    </div>
                    <div class="habits-form-group">
                        <input type="date" class="habits-form-input" id="newPeriodStart">
                    </div>
                    <div class="habits-form-group">
                        <input type="date" class="habits-form-input" id="newPeriodEnd">
                    </div>
                    <button class="habits-btn habits-btn-primary" onclick="HabitsApp.addPeriod()">Dodaj</button>
                </div>
                <div class="management-list" id="periodsList"></div>
            </div>

            <div class="habits-card">
                <div class="habits-card-title">📋 Kopiuj nawyki między okresami</div>
                <div class="habits-form-row">
                    <div class="habits-form-group">
                        <label class="habits-form-label">Z okresu:</label>
                        <select class="habits-form-input" id="copyFromPeriod"></select>
                    </div>
                    <div class="habits-form-group">
                        <label class="habits-form-label">Do okresu:</label>
                        <select class="habits-form-input" id="copyToPeriod"></select>
                    </div>
                    <button class="habits-btn habits-btn-success" onclick="HabitsApp.copyPeriod()" style="align-self: flex-end;">Kopiuj</button>
                </div>
            </div>

            <div class="habits-modal-actions">
                <button class="habits-btn habits-btn-secondary" onclick="HabitsApp.closeManageModal()">Zamknij</button>
            </div>
        </div>
    </div>

    <!-- Modal: Dodaj/Edytuj nawyk -->
    <div class="habits-modal-overlay" id="habitModal">
        <div class="habits-modal">
            <div class="habits-modal-title" id="habitModalTitle">Dodaj nawyk</div>
            <input type="hidden" id="editHabitId">

            <div class="habits-form-row">
                <div class="habits-form-group">
                    <label class="habits-form-label">Nazwa</label>
                    <input type="text" class="habits-form-input" id="habitName" placeholder="np. Czytanie">
                </div>
            </div>

            <div class="habits-form-row">
                <div class="habits-form-group">
                    <label class="habits-form-label">Cel (minut dziennie)</label>
                    <input type="number" class="habits-form-input" id="habitGoal" value="30" min="1">
                </div>
                <div class="habits-form-group">
                    <label class="habits-form-label">Ikona</label>
                    <input type="text" class="habits-form-input" id="habitIcon" value="📚" style="font-size: 20px;">
                </div>
            </div>

            <div class="habits-form-row">
                <div class="habits-form-group">
                    <label class="habits-form-label">Kolor</label>
                    <input type="color" class="habits-form-input" id="habitColor" value="#4A90D9" style="height: 44px;">
                </div>
            </div>

            <div class="habits-modal-actions">
                <button class="habits-btn habits-btn-secondary" onclick="HabitsApp.closeHabitModal()">Anuluj</button>
                <button class="habits-btn habits-btn-primary" onclick="HabitsApp.saveHabit()">Zapisz</button>
            </div>
        </div>
    </div>

    <!-- Modal: Dodaj/Edytuj challenge -->
    <div class="habits-modal-overlay" id="challengeModal">
        <div class="habits-modal">
            <div class="habits-modal-title" id="challengeModalTitle">Dodaj challenge</div>
            <input type="hidden" id="editChallengeId">
            <input type="hidden" id="challengeType" value="weekly">

            <div class="habits-form-row">
                <div class="habits-form-group">
                    <label class="habits-form-label">Nazwa</label>
                    <input type="text" class="habits-form-input" id="challengeName" placeholder="np. Gotowanie w domu">
                </div>
            </div>

            <div class="habits-form-row">
                <div class="habits-form-group">
                    <label class="habits-form-label">Opis</label>
                    <textarea class="habits-form-input" id="challengeDesc" rows="2" placeholder="np. Jedz tylko jedzenie zrobione w domu"></textarea>
                </div>
            </div>

            <div class="habits-form-row" id="challengeGoalRow">
                <div class="habits-form-group">
                    <label class="habits-form-label">Cel (dni w tygodniu)</label>
                    <input type="number" class="habits-form-input" id="challengeGoal" value="4" min="1" max="7">
                </div>
                <div class="habits-form-group">
                    <label class="habits-form-label">Ikona</label>
                    <input type="text" class="habits-form-input" id="challengeIcon" value="🎯" style="font-size: 20px;">
                </div>
            </div>

            <div class="habits-form-row" id="challengeIconRowGeneral" style="display: none;">
                <div class="habits-form-group">
                    <label class="habits-form-label">Ikona</label>
                    <input type="text" class="habits-form-input" id="challengeIconGeneral" value="🎯" style="font-size: 20px;">
                </div>
            </div>

            <div class="habits-form-row">
                <div class="habits-form-group">
                    <label class="habits-form-label">Kolor</label>
                    <input type="color" class="habits-form-input" id="challengeColor" value="#E74C3C" style="height: 44px;">
                </div>
            </div>

            <div class="habits-modal-actions">
                <button class="habits-btn habits-btn-secondary" onclick="HabitsApp.closeChallengeModal()">Anuluj</button>
                <button class="habits-btn habits-btn-primary" onclick="HabitsApp.saveChallenge()">Zapisz</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    const HabitsApp = {
        currentYearId: <?php echo $current_year ? $current_year->id : 'null'; ?>,
        currentPeriodId: <?php echo $current_period ? $current_period->id : 'null'; ?>,
        currentWeekStart: null,
        habits: [],
        challenges: [],
        chart: null,
        initialYearId: <?php echo $current_year ? $current_year->id : 'null'; ?>,
        initialPeriodId: <?php echo $current_period ? $current_period->id : 'null'; ?>,

        async init() {
            this.currentWeekStart = this.getMonday(new Date());
            this.loadGlobalStats();
            await this.loadYears();
            this.setupTabs();

            // Auto-select current year and period
            if (this.initialYearId) {
                document.getElementById('yearSelect').value = this.initialYearId;
                this.currentYearId = this.initialYearId;
                await this.loadPeriods(this.initialYearId);

                if (this.initialPeriodId) {
                    document.getElementById('periodSelect').value = this.initialPeriodId;
                    this.currentPeriodId = this.initialPeriodId;
                    this.loadHabits();
                    this.loadChallenges();
                    this.loadSummary();
                }
            }
        },

        getMonday(date) {
            const d = new Date(date);
            const day = d.getDay();
            const diff = d.getDate() - day + (day === 0 ? -6 : 1);
            return new Date(d.setDate(diff));
        },

        formatDate(date) {
            return date.toISOString().split('T')[0];
        },

        setupTabs() {
            document.querySelectorAll('.habits-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.habits-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.habits-tab-content').forEach(c => c.classList.remove('active'));

                    tab.classList.add('active');
                    document.getElementById('tab-' + tab.dataset.tab).classList.add('active');

                    // Refresh content based on tab
                    if (tab.dataset.tab === 'charts' && this.currentPeriodId) {
                        this.loadChart('weekly');
                    } else if (tab.dataset.tab === 'sport') {
                        this.loadSteps();
                        this.loadSportHistory();
                        this.loadSportStats();
                    } else if (tab.dataset.tab === 'summary' && this.currentPeriodId) {
                        this.loadSummary();
                    }
                });
            });
        },

        async ajax(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            for (const key in data) {
                formData.append(key, data[key]);
            }

            const response = await fetch(ajaxurl, {
                method: 'POST',
                body: formData
            });

            return response.json();
        },

        async loadGlobalStats() {
            const result = await this.ajax('habits_get_global_stats');
            if (result.success) {
                const stats = result.data;
                document.getElementById('globalLevel').textContent = 'Lvl ' + stats.level;
                document.getElementById('globalXP').textContent = '(' + stats.total_xp + ' XP)';
                document.getElementById('globalStreak').textContent = stats.current_streak + ' dni';
            }
        },

        async loadYears() {
            const result = await this.ajax('habits_get_years');
            if (result.success) {
                const select = document.getElementById('yearSelect');
                select.innerHTML = '<option value="">-- Wybierz rok --</option>';

                result.data.forEach(year => {
                    const opt = document.createElement('option');
                    opt.value = year.id;
                    opt.textContent = year.nazwa;
                    select.appendChild(opt);
                });

                select.onchange = () => {
                    this.currentYearId = select.value;
                    if (this.currentYearId) {
                        this.loadPeriods(this.currentYearId);
                    } else {
                        document.getElementById('periodSelect').innerHTML = '<option value="">-- Wybierz okres --</option>';
                        document.getElementById('periodSelect').disabled = true;
                    }
                };
            }
        },

        async loadPeriods(yearId) {
            const result = await this.ajax('habits_get_periods', { year_id: yearId });
            if (result.success) {
                const select = document.getElementById('periodSelect');
                select.innerHTML = '<option value="">-- Wybierz okres --</option>';
                select.disabled = false;

                // Store periods for later reference
                this.periods = {};
                result.data.forEach(period => {
                    this.periods[period.id] = period;
                    const opt = document.createElement('option');
                    opt.value = period.id;
                    opt.textContent = period.nazwa + ' (' + period.data_start + ' - ' + period.data_koniec + ')';
                    select.appendChild(opt);
                });

                select.onchange = () => {
                    this.currentPeriodId = select.value;
                    if (this.currentPeriodId) {
                        // Store current period dates
                        this.currentPeriodStart = this.periods[this.currentPeriodId].data_start;
                        this.currentPeriodEnd = this.periods[this.currentPeriodId].data_koniec;
                        this.loadHabits();
                        this.loadChallenges();
                        this.loadSummary();
                    }
                };
            }
        },

        async loadHabits() {
            const result = await this.ajax('habits_get_habits', { period_id: this.currentPeriodId });
            if (result.success) {
                this.habits = result.data;
                this.renderTrackingTable();
                this.renderHabitsManageList();
            }
        },

        async loadChallenges() {
            const result = await this.ajax('habits_get_challenges', { period_id: this.currentPeriodId });
            if (result.success) {
                this.challenges = result.data;
                this.renderChallengesTable();
            }
        },

        getWeekDates() {
            const dates = [];
            for (let i = 0; i < 7; i++) {
                const d = new Date(this.currentWeekStart);
                d.setDate(d.getDate() + i);
                dates.push(this.formatDate(d));
            }
            return dates;
        },

        updateWeekLabel() {
            const dates = this.getWeekDates();
            const start = new Date(dates[0]);
            const end = new Date(dates[6]);
            const label = start.toLocaleDateString('pl-PL', { day: 'numeric', month: 'short' }) +
                         ' - ' + end.toLocaleDateString('pl-PL', { day: 'numeric', month: 'short', year: 'numeric' });

            document.getElementById('weekLabel').textContent = label;
            document.getElementById('weekLabelChallenge').textContent = label;
        },

        prevWeek() {
            this.currentWeekStart.setDate(this.currentWeekStart.getDate() - 7);
            this.renderTrackingTable();
            this.renderChallengesTable();
        },

        nextWeek() {
            this.currentWeekStart.setDate(this.currentWeekStart.getDate() + 7);
            this.renderTrackingTable();
            this.renderChallengesTable();
        },

        goToToday() {
            this.currentWeekStart = this.getMonday(new Date());
            this.renderTrackingTable();
            this.renderChallengesTable();
        },

        async renderTrackingTable() {
            if (!this.currentPeriodId || this.habits.length === 0) {
                document.getElementById('trackingTable').innerHTML = `
                    <p style="color: #6B7280; text-align: center; padding: 40px;">
                        ${!this.currentPeriodId ? 'Wybierz okres, aby rozpocząć logowanie.' : 'Brak nawyków. Dodaj nawyk w zakładce "Nawyki".'}
                    </p>
                `;
                return;
            }

            this.updateWeekLabel();
            const dates = this.getWeekDates();
            const today = this.formatDate(new Date());

            // Pobierz wpisy
            const result = await this.ajax('habits_get_entries', {
                period_id: this.currentPeriodId,
                date_start: dates[0],
                date_end: dates[6]
            });

            const entries = {};
            if (result.success) {
                result.data.forEach(e => {
                    if (!entries[e.habit_id]) entries[e.habit_id] = {};
                    entries[e.habit_id][e.dzien] = e.minuty;
                });
            }

            const dayNames = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Nd'];

            // Check which days are within period range
            const periodStart = this.currentPeriodStart;
            const periodEnd = this.currentPeriodEnd;

            let html = `
                <table class="habits-table">
                    <thead>
                        <tr>
                            <th>Nawyk</th>
                            ${dates.map((d, i) => {
                                const isToday = d === today;
                                const isOutOfPeriod = d < periodStart || d > periodEnd;
                                const dateObj = new Date(d);
                                return `
                                    <th>
                                        <div class="day-header ${isToday ? 'today' : ''} ${isOutOfPeriod ? 'out-of-period' : ''}">
                                            <div class="day-name">${dayNames[i]}</div>
                                            <div class="day-date">${dateObj.getDate()}.${String(dateObj.getMonth() + 1).padStart(2, '0')}</div>
                                        </div>
                                    </th>
                                `;
                            }).join('')}
                            <th>Suma</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            this.habits.filter(h => h.aktywny == 1).forEach(habit => {
                let weekTotal = 0;

                html += `
                    <tr>
                        <td>
                            <div class="habit-name">
                                <span class="habit-icon">${habit.ikona}</span>
                                <div>
                                    <div class="habit-label">${habit.nazwa}</div>
                                    <div class="habit-goal">Cel: ${habit.cel_minut_dziennie} min/dzień</div>
                                </div>
                            </div>
                        </td>
                `;

                dates.forEach(d => {
                    const isOutOfPeriod = d < periodStart || d > periodEnd;
                    const value = entries[habit.id]?.[d] || 0;
                    if (!isOutOfPeriod) {
                        weekTotal += parseInt(value);
                    }
                    const hasValue = value > 0;

                    if (isOutOfPeriod) {
                        html += `
                            <td class="day-cell out-of-period">
                                <div class="habit-cell disabled">
                                    <div class="habit-check disabled"></div>
                                </div>
                            </td>
                        `;
                    } else {
                        html += `
                            <td class="day-cell">
                                <div class="habit-cell">
                                    <div class="habit-check ${hasValue ? 'checked' : ''}"
                                         data-habit-id="${habit.id}"
                                         data-date="${d}"
                                         data-goal="${habit.cel_minut_dziennie}"
                                         data-value="${value}"
                                         onclick="HabitsApp.toggleHabit(this)">
                                    </div>
                                    <div class="habit-minutes"
                                         data-habit-id="${habit.id}"
                                         data-date="${d}"
                                         onclick="HabitsApp.editMinutes(this, ${habit.id}, '${d}', ${value}, ${habit.cel_minut_dziennie})">
                                        ${hasValue ? value + 'm' : ''}
                                    </div>
                                </div>
                            </td>
                        `;
                    }
                });

                html += `
                        <td style="font-weight: 600; color: var(--habits-primary);">
                            ${weekTotal} min
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            document.getElementById('trackingTable').innerHTML = html;
        },

        async toggleHabit(el) {
            const habitId = el.dataset.habitId;
            const date = el.dataset.date;
            const goal = parseInt(el.dataset.goal);
            const currentValue = parseInt(el.dataset.value) || 0;

            // Toggle: jeśli 0 -> ustaw cel, jeśli > 0 -> ustaw 0
            const newValue = currentValue > 0 ? 0 : goal;

            // Update visual immediately (optimistic update)
            el.classList.toggle('checked', newValue > 0);
            el.dataset.value = newValue;

            const minutesEl = el.parentElement.querySelector('.habit-minutes');
            minutesEl.textContent = newValue > 0 ? newValue + 'm' : '';

            // Update row total without full re-render
            this.updateRowTotal(el, currentValue, newValue);

            // Save to server (no await to not block)
            this.ajax('habits_save_entry', {
                habit_id: habitId,
                dzien: date,
                minuty: newValue
            });

            // Lazy refresh stats
            this.loadGlobalStats();
        },

        updateRowTotal(el, oldValue, newValue) {
            const row = el.closest('tr');
            if (!row) return;
            const totalCell = row.querySelector('td:last-child');
            if (!totalCell) return;

            const currentTotal = parseInt(totalCell.textContent) || 0;
            const diff = newValue - oldValue;
            totalCell.textContent = (currentTotal + diff) + ' min';
        },

        editMinutes(el, habitId, date, currentValue, goal) {
            // Jeśli już jest input, nie rób nic
            if (el.querySelector('input')) return;

            const checkEl = el.parentElement.querySelector('.habit-check');
            const oldValue = currentValue;

            const input = document.createElement('input');
            input.type = 'number';
            input.className = 'habit-minutes-input';
            input.value = currentValue || goal;
            input.min = 0;

            el.textContent = '';
            el.appendChild(input);
            input.focus();
            input.select();

            const save = async () => {
                const newValue = parseInt(input.value) || 0;

                // Update visual immediately
                el.textContent = newValue > 0 ? newValue + 'm' : '';
                checkEl.classList.toggle('checked', newValue > 0);
                checkEl.dataset.value = newValue;

                // Update row total
                this.updateRowTotal(checkEl, oldValue, newValue);

                // Save to server
                this.ajax('habits_save_entry', {
                    habit_id: habitId,
                    dzien: date,
                    minuty: newValue
                });

                this.loadGlobalStats();
            };

            input.onblur = save;
            input.onkeydown = (e) => {
                if (e.key === 'Enter') {
                    input.blur();
                } else if (e.key === 'Escape') {
                    el.textContent = oldValue > 0 ? oldValue + 'm' : '';
                }
            };
        },

        async saveEntry(habitId, date, minutes) {
            await this.ajax('habits_save_entry', {
                habit_id: habitId,
                dzien: date,
                minuty: minutes
            });

            // Refresh stats
            this.loadGlobalStats();
            this.loadSummary();
        },

        async renderChallengesTable() {
            if (!this.currentPeriodId) {
                document.getElementById('weeklyChallengesTable').innerHTML = `
                    <p style="color: #6B7280; text-align: center; padding: 20px;">
                        Wybierz okres, aby zobaczyć challenge.
                    </p>
                `;
                document.getElementById('generalChallengesTable').innerHTML = `
                    <p style="color: #6B7280; text-align: center; padding: 20px;">
                        Wybierz okres, aby zobaczyć challenge.
                    </p>
                `;
                return;
            }

            // Podziel challenge na weekly i general
            const weeklyChallenges = this.challenges.filter(ch => ch.aktywny == 1 && ch.typ === 'weekly');
            const generalChallenges = this.challenges.filter(ch => ch.aktywny == 1 && ch.typ === 'general');

            // Render weekly challenges
            await this.renderWeeklyChallenges(weeklyChallenges);

            // Render general challenges
            this.renderGeneralChallenges(generalChallenges);
        },

        async renderWeeklyChallenges(challenges) {
            const container = document.getElementById('weeklyChallengesTable');

            if (challenges.length === 0) {
                container.innerHTML = `
                    <p style="color: #6B7280; text-align: center; padding: 20px;">
                        Brak challenge'ów tygodniowych.
                    </p>
                `;
                return;
            }

            this.updateWeekLabel();
            const dates = this.getWeekDates();
            const today = this.formatDate(new Date());
            const dayNames = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Nd'];

            // Pobierz checks dla wszystkich challenge
            const checksMap = {};
            for (const ch of challenges) {
                const result = await this.ajax('habits_get_challenge_checks', {
                    challenge_id: ch.id,
                    date_start: dates[0],
                    date_end: dates[6]
                });
                if (result.success) {
                    checksMap[ch.id] = {};
                    result.data.forEach(c => {
                        checksMap[ch.id][c.dzien] = c.wykonane == 1;
                    });
                }
            }

            // Check which days are within period range
            const periodStart = this.currentPeriodStart;
            const periodEnd = this.currentPeriodEnd;

            let html = `
                <table class="habits-table">
                    <thead>
                        <tr>
                            <th>Challenge</th>
                            ${dates.map((d, i) => {
                                const isToday = d === today;
                                const isOutOfPeriod = d < periodStart || d > periodEnd;
                                const dateObj = new Date(d);
                                return `
                                    <th>
                                        <div class="day-header ${isToday ? 'today' : ''} ${isOutOfPeriod ? 'out-of-period' : ''}">
                                            <div class="day-name">${dayNames[i]}</div>
                                            <div class="day-date">${dateObj.getDate()}.${String(dateObj.getMonth() + 1).padStart(2, '0')}</div>
                                        </div>
                                    </th>
                                `;
                            }).join('')}
                            <th>Wynik</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            challenges.forEach(challenge => {
                let weekCount = 0;

                html += `
                    <tr>
                        <td>
                            <div class="habit-name">
                                <span class="habit-icon">${challenge.ikona}</span>
                                <div>
                                    <div class="habit-label">${challenge.nazwa}</div>
                                    <div class="habit-goal">Cel: ${challenge.cel_dni} dni/tydzień</div>
                                </div>
                            </div>
                        </td>
                `;

                dates.forEach(d => {
                    const isOutOfPeriod = d < periodStart || d > periodEnd;
                    const checked = checksMap[challenge.id]?.[d] || false;
                    if (checked && !isOutOfPeriod) weekCount++;

                    if (isOutOfPeriod) {
                        html += `
                            <td class="day-cell out-of-period">
                                <div class="challenge-check disabled"></div>
                            </td>
                        `;
                    } else {
                        html += `
                            <td class="day-cell">
                                <div class="challenge-check ${checked ? 'checked' : ''}"
                                     data-challenge-id="${challenge.id}"
                                     data-date="${d}"
                                     onclick="HabitsApp.toggleChallenge(this)">
                                </div>
                            </td>
                        `;
                    }
                });

                const goalMet = weekCount >= challenge.cel_dni;

                html += `
                        <td style="font-weight: 600; color: ${goalMet ? 'var(--habits-success)' : 'var(--habits-dark)'};">
                            ${weekCount}/${challenge.cel_dni}
                            ${goalMet ? '✓' : ''}
                        </td>
                        <td>
                            <button class="habits-btn habits-btn-sm habits-btn-secondary" onclick="HabitsApp.editChallenge(${challenge.id})">✏️</button>
                            <button class="habits-btn habits-btn-sm habits-btn-danger" onclick="HabitsApp.deleteChallenge(${challenge.id})">🗑️</button>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        },

        renderGeneralChallenges(challenges) {
            const container = document.getElementById('generalChallengesTable');

            if (challenges.length === 0) {
                container.innerHTML = `
                    <p style="color: #6B7280; text-align: center; padding: 20px;">
                        Brak challenge'ów ogólnych.
                    </p>
                `;
                return;
            }

            let html = '<div class="general-challenges-list">';

            challenges.forEach(challenge => {
                const isCompleted = challenge.completed == 1;
                html += `
                    <div class="general-challenge-item ${isCompleted ? 'completed' : ''}" style="
                        display: flex;
                        align-items: center;
                        gap: 15px;
                        padding: 15px;
                        background: ${isCompleted ? '#F0FDF4' : '#F9FAFB'};
                        border-radius: 8px;
                        margin-bottom: 10px;
                        border-left: 4px solid ${isCompleted ? '#22C55E' : challenge.kolor};
                    ">
                        <div class="challenge-check ${isCompleted ? 'checked' : ''}"
                             style="cursor: pointer;"
                             onclick="HabitsApp.toggleGeneralChallenge(${challenge.id})">
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 20px;">${challenge.ikona}</span>
                                <span style="font-weight: 600; ${isCompleted ? 'text-decoration: line-through; color: #9CA3AF;' : ''}">${challenge.nazwa}</span>
                            </div>
                            ${challenge.opis ? `<div style="font-size: 12px; color: #6B7280; margin-top: 4px;">${challenge.opis}</div>` : ''}
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <button class="habits-btn habits-btn-sm habits-btn-secondary" onclick="HabitsApp.editChallenge(${challenge.id})">✏️</button>
                            <button class="habits-btn habits-btn-sm habits-btn-danger" onclick="HabitsApp.deleteChallenge(${challenge.id})">🗑️</button>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        },

        async toggleChallenge(el) {
            const challengeId = el.dataset.challengeId;
            const date = el.dataset.date;

            await this.ajax('habits_toggle_challenge', {
                challenge_id: challengeId,
                dzien: date
            });

            el.classList.toggle('checked');

            // Update week count
            this.renderChallengesTable();
        },

        summaryScope: 'period',

        setSummaryScope(scope) {
            this.summaryScope = scope;

            // Update button states
            document.getElementById('btnScopePeriod').classList.toggle('habits-btn-primary', scope === 'period');
            document.getElementById('btnScopePeriod').classList.toggle('habits-btn-secondary', scope !== 'period');
            document.getElementById('btnScopeYear').classList.toggle('habits-btn-primary', scope === 'year');
            document.getElementById('btnScopeYear').classList.toggle('habits-btn-secondary', scope !== 'year');
            document.getElementById('btnScopeAll').classList.toggle('habits-btn-primary', scope === 'all');
            document.getElementById('btnScopeAll').classList.toggle('habits-btn-secondary', scope !== 'all');

            this.loadSummary();
        },

        async loadSummary() {
            // Check if we have required data for selected scope
            if (this.summaryScope === 'period' && !this.currentPeriodId) {
                document.getElementById('summaryContent').innerHTML = `
                    <p style="color: #6B7280; text-align: center; padding: 40px;">
                        Wybierz okres, aby zobaczyć podsumowanie.
                    </p>
                `;
                return;
            }
            if (this.summaryScope === 'year' && !this.currentYearId) {
                document.getElementById('summaryContent').innerHTML = `
                    <p style="color: #6B7280; text-align: center; padding: 40px;">
                        Wybierz rok, aby zobaczyć podsumowanie.
                    </p>
                `;
                return;
            }

            const params = {
                period_id: this.currentPeriodId || 0,
                year_id: this.currentYearId || 0,
                scope: this.summaryScope
            };

            const result = await this.ajax('habits_get_summary', params);

            if (!result.success) return;

            const { habits, challenges, date_range } = result.data;

            // Show date range info
            const scopeLabels = { period: 'Okres', year: 'Rok', all: 'Wszystkie okresy' };
            let html = `
                <div style="margin-bottom: 20px; padding: 12px; background: #f0f9ff; border-radius: 8px; color: #0369a1;">
                    <strong>${scopeLabels[this.summaryScope]}</strong>: ${date_range.start} - ${date_range.end}
                </div>
            `;

            html += '<h3 style="margin-bottom: 20px; color: var(--habits-dark);">📊 Nawyki</h3>';

            if (habits.length === 0) {
                html += '<p style="color: #6B7280; text-align: center; padding: 20px;">Brak nawyków w wybranym zakresie.</p>';
            } else {
                html += '<div class="habits-summary-grid">';

                habits.forEach(item => {
                const h = item.habit;
                html += `
                    <div class="summary-card">
                        <div class="summary-card-header">
                            <div class="summary-icon" style="background: ${h.kolor}20; color: ${h.kolor};">
                                ${h.ikona}
                            </div>
                            <div class="summary-title">${h.nazwa}</div>
                        </div>
                        <div class="summary-stats">
                            <div class="summary-stat">
                                <div class="summary-stat-value">${item.total_hours}h</div>
                                <div class="summary-stat-label">Łącznie</div>
                            </div>
                            <div class="summary-stat">
                                <div class="summary-stat-value">${item.avg_daily}m</div>
                                <div class="summary-stat-label">Średnio/dzień</div>
                            </div>
                            <div class="summary-stat">
                                <div class="summary-stat-value">${item.goal_days}/${item.total_days}</div>
                                <div class="summary-stat-label">Dni z celem</div>
                            </div>
                            <div class="summary-stat">
                                <div class="summary-stat-value">🔥 ${item.streak}</div>
                                <div class="summary-stat-label">Streak</div>
                            </div>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-bar-fill" style="width: ${item.goal_percentage}%; background: ${h.kolor};"></div>
                        </div>
                        <div style="text-align: center; margin-top: 8px; font-size: 12px; color: #6B7280;">
                            ${item.goal_percentage}% dni z osiągniętym celem
                        </div>
                    </div>
                `;
                });

                html += '</div>';
            }

            if (challenges.length > 0) {
                html += '<h3 style="margin: 30px 0 20px; color: var(--habits-dark);">🎯 Challenge</h3>';
                html += '<div class="habits-summary-grid">';

                challenges.forEach(item => {
                    const ch = item.challenge;
                    html += `
                        <div class="summary-card">
                            <div class="summary-card-header">
                                <div class="summary-icon" style="background: ${ch.kolor}20; color: ${ch.kolor};">
                                    ${ch.ikona}
                                </div>
                                <div class="summary-title">${ch.nazwa}</div>
                            </div>
                            <div class="summary-stats">
                                <div class="summary-stat">
                                    <div class="summary-stat-value">${item.completed_days}</div>
                                    <div class="summary-stat-label">Dni zrobione</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="summary-stat-value">${item.percentage}%</div>
                                    <div class="summary-stat-label">Realizacja</div>
                                </div>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill" style="width: ${Math.min(100, item.percentage)}%; background: ${ch.kolor};"></div>
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
            }

            document.getElementById('summaryContent').innerHTML = html;
        },

        async loadChart(type) {
            if (!this.currentPeriodId) return;

            // Update button states
            document.getElementById('btnChartWeekly').classList.toggle('habits-btn-primary', type === 'weekly');
            document.getElementById('btnChartWeekly').classList.toggle('habits-btn-secondary', type !== 'weekly');
            document.getElementById('btnChartDaily').classList.toggle('habits-btn-primary', type === 'daily');
            document.getElementById('btnChartDaily').classList.toggle('habits-btn-secondary', type !== 'daily');

            const result = await this.ajax('habits_get_chart_data', {
                period_id: this.currentPeriodId,
                chart_type: type
            });

            if (!result.success) return;

            const { data, habits } = result.data;

            if (this.chart) {
                this.chart.destroy();
            }

            const ctx = document.getElementById('habitsChart').getContext('2d');

            const datasets = habits.map(h => ({
                label: h.nazwa,
                data: data.map(d => d[h.nazwa] || 0),
                backgroundColor: h.kolor + '40',
                borderColor: h.kolor,
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }));

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.label || d.week),
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Minuty'
                            }
                        }
                    }
                }
            });
        },

        renderHabitsManageList() {
            if (!this.currentPeriodId) return;

            if (this.habits.length === 0) {
                document.getElementById('habitsManageList').innerHTML = `
                    <p style="color: #6B7280; text-align: center; padding: 20px;">
                        Brak nawyków. Kliknij "Dodaj nawyk" aby utworzyć pierwszy.
                    </p>
                `;
                return;
            }

            let html = '<div class="management-list">';

            this.habits.forEach(habit => {
                html += `
                    <div class="management-item" style="border-left: 4px solid ${habit.kolor};">
                        <div class="management-item-info">
                            <div class="management-item-name">
                                ${habit.ikona} ${habit.nazwa}
                                ${habit.aktywny != 1 ? '<span style="color: #9CA3AF;">(nieaktywny)</span>' : ''}
                            </div>
                            <div class="management-item-dates">
                                Cel: ${habit.cel_minut_dziennie} min/dzień
                            </div>
                        </div>
                        <div class="management-item-actions">
                            <button class="habits-btn habits-btn-sm habits-btn-secondary" onclick="HabitsApp.editHabit(${habit.id})">✏️ Edytuj</button>
                            <button class="habits-btn habits-btn-sm habits-btn-danger" onclick="HabitsApp.deleteHabit(${habit.id})">🗑️ Usuń</button>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            document.getElementById('habitsManageList').innerHTML = html;
        },

        // Modal methods
        openManageModal() {
            document.getElementById('manageModal').classList.add('active');
            this.loadManageModalData();
        },

        closeManageModal() {
            document.getElementById('manageModal').classList.remove('active');
        },

        async loadManageModalData() {
            // Load years
            const yearsResult = await this.ajax('habits_get_years');
            if (yearsResult.success) {
                let html = '';
                yearsResult.data.forEach(year => {
                    html += `
                        <div class="management-item">
                            <div class="management-item-info">
                                <div class="management-item-name">${year.nazwa}</div>
                                <div class="management-item-dates">${year.data_start} - ${year.data_koniec}</div>
                            </div>
                            <div class="management-item-actions">
                                <button class="habits-btn habits-btn-sm habits-btn-danger" onclick="HabitsApp.deleteYear(${year.id})">🗑️</button>
                            </div>
                        </div>
                    `;
                });
                document.getElementById('yearsList').innerHTML = html;

                // Update period year select
                const select = document.getElementById('periodYearSelect');
                select.innerHTML = '<option value="">Wybierz rok</option>';
                yearsResult.data.forEach(year => {
                    select.innerHTML += `<option value="${year.id}">${year.nazwa}</option>`;
                });

                select.onchange = () => this.loadPeriodsForManage(select.value);

                // Update copy selects
                await this.loadAllPeriodsForCopy();
            }
        },

        async loadPeriodsForManage(yearId) {
            if (!yearId) {
                document.getElementById('periodsList').innerHTML = '';
                return;
            }

            const result = await this.ajax('habits_get_periods', { year_id: yearId });
            if (result.success) {
                let html = '';
                result.data.forEach(period => {
                    html += `
                        <div class="management-item">
                            <div class="management-item-info">
                                <div class="management-item-name">${period.nazwa}</div>
                                <div class="management-item-dates">${period.data_start} - ${period.data_koniec}</div>
                            </div>
                            <div class="management-item-actions">
                                <button class="habits-btn habits-btn-sm habits-btn-danger" onclick="HabitsApp.deletePeriod(${period.id})">🗑️</button>
                            </div>
                        </div>
                    `;
                });
                document.getElementById('periodsList').innerHTML = html;
            }
        },

        async loadAllPeriodsForCopy() {
            const yearsResult = await this.ajax('habits_get_years');
            if (!yearsResult.success) return;

            const allPeriods = [];
            for (const year of yearsResult.data) {
                const periodsResult = await this.ajax('habits_get_periods', { year_id: year.id });
                if (periodsResult.success) {
                    periodsResult.data.forEach(p => {
                        allPeriods.push({ ...p, yearName: year.nazwa });
                    });
                }
            }

            const fromSelect = document.getElementById('copyFromPeriod');
            const toSelect = document.getElementById('copyToPeriod');

            let options = '<option value="">Wybierz okres</option>';
            allPeriods.forEach(p => {
                options += `<option value="${p.id}">${p.yearName} - ${p.nazwa}</option>`;
            });

            fromSelect.innerHTML = options;
            toSelect.innerHTML = options;
        },

        async addYear() {
            const nazwa = document.getElementById('newYearName').value;
            const start = document.getElementById('newYearStart').value;
            const end = document.getElementById('newYearEnd').value;

            if (!nazwa || !start || !end) {
                alert('Wypełnij wszystkie pola');
                return;
            }

            await this.ajax('habits_add_year', {
                nazwa: nazwa,
                data_start: start,
                data_koniec: end
            });

            document.getElementById('newYearName').value = '';
            document.getElementById('newYearStart').value = '';
            document.getElementById('newYearEnd').value = '';

            this.loadManageModalData();
            this.loadYears();
        },

        async deleteYear(id) {
            if (!confirm('Czy na pewno chcesz usunąć ten rok i wszystkie powiązane dane?')) return;

            await this.ajax('habits_delete_year', { id: id });
            this.loadManageModalData();
            this.loadYears();
        },

        async addPeriod() {
            const yearId = document.getElementById('periodYearSelect').value;
            const nazwa = document.getElementById('newPeriodName').value;
            const start = document.getElementById('newPeriodStart').value;
            const end = document.getElementById('newPeriodEnd').value;

            if (!yearId || !nazwa || !start || !end) {
                alert('Wypełnij wszystkie pola');
                return;
            }

            await this.ajax('habits_add_period', {
                year_id: yearId,
                nazwa: nazwa,
                data_start: start,
                data_koniec: end
            });

            document.getElementById('newPeriodName').value = '';
            document.getElementById('newPeriodStart').value = '';
            document.getElementById('newPeriodEnd').value = '';

            this.loadPeriodsForManage(yearId);
            this.loadAllPeriodsForCopy();

            if (this.currentYearId) {
                this.loadPeriods(this.currentYearId);
            }
        },

        async deletePeriod(id) {
            if (!confirm('Czy na pewno chcesz usunąć ten okres i wszystkie powiązane dane?')) return;

            await this.ajax('habits_delete_period', { id: id });

            const yearId = document.getElementById('periodYearSelect').value;
            if (yearId) {
                this.loadPeriodsForManage(yearId);
            }
            this.loadAllPeriodsForCopy();

            if (this.currentYearId) {
                this.loadPeriods(this.currentYearId);
            }
        },

        async copyPeriod() {
            const fromId = document.getElementById('copyFromPeriod').value;
            const toId = document.getElementById('copyToPeriod').value;

            if (!fromId || !toId) {
                alert('Wybierz oba okresy');
                return;
            }

            if (fromId === toId) {
                alert('Wybierz różne okresy');
                return;
            }

            if (!confirm('Czy na pewno chcesz skopiować nawyki i challenge z wybranego okresu?')) return;

            await this.ajax('habits_copy_period', {
                from_period_id: fromId,
                to_period_id: toId
            });

            alert('Skopiowano!');

            if (this.currentPeriodId) {
                this.loadHabits();
                this.loadChallenges();
            }
        },

        // Habit modal
        openAddHabitModal() {
            if (!this.currentPeriodId) {
                alert('Najpierw wybierz okres');
                return;
            }

            document.getElementById('habitModalTitle').textContent = 'Dodaj nawyk';
            document.getElementById('editHabitId').value = '';
            document.getElementById('habitName').value = '';
            document.getElementById('habitGoal').value = '30';
            document.getElementById('habitIcon').value = '📚';
            document.getElementById('habitColor').value = '#4A90D9';

            document.getElementById('habitModal').classList.add('active');
        },

        editHabit(id) {
            const habit = this.habits.find(h => h.id == id);
            if (!habit) return;

            document.getElementById('habitModalTitle').textContent = 'Edytuj nawyk';
            document.getElementById('editHabitId').value = id;
            document.getElementById('habitName').value = habit.nazwa;
            document.getElementById('habitGoal').value = habit.cel_minut_dziennie;
            document.getElementById('habitIcon').value = habit.ikona;
            document.getElementById('habitColor').value = habit.kolor;

            document.getElementById('habitModal').classList.add('active');
        },

        closeHabitModal() {
            document.getElementById('habitModal').classList.remove('active');
        },

        async saveHabit() {
            const id = document.getElementById('editHabitId').value;
            const data = {
                nazwa: document.getElementById('habitName').value,
                cel_minut_dziennie: document.getElementById('habitGoal').value,
                ikona: document.getElementById('habitIcon').value,
                kolor: document.getElementById('habitColor').value
            };

            if (!data.nazwa) {
                alert('Podaj nazwę nawyku');
                return;
            }

            if (id) {
                data.id = id;
                data.aktywny = 1;
                await this.ajax('habits_update_habit', data);
            } else {
                data.period_id = this.currentPeriodId;
                await this.ajax('habits_add_habit', data);
            }

            this.closeHabitModal();
            this.loadHabits();
        },

        async deleteHabit(id) {
            if (!confirm('Czy na pewno chcesz usunąć ten nawyk i wszystkie jego wpisy?')) return;

            await this.ajax('habits_delete_habit', { id: id });
            this.loadHabits();
        },

        // Challenge modal
        openAddChallengeModal(type = 'weekly') {
            if (!this.currentPeriodId) {
                alert('Najpierw wybierz okres');
                return;
            }

            const isWeekly = type === 'weekly';
            document.getElementById('challengeModalTitle').textContent = isWeekly ? 'Dodaj challenge tygodniowy' : 'Dodaj challenge ogólny';
            document.getElementById('editChallengeId').value = '';
            document.getElementById('challengeType').value = type;
            document.getElementById('challengeName').value = '';
            document.getElementById('challengeDesc').value = '';
            document.getElementById('challengeGoal').value = '4';
            document.getElementById('challengeIcon').value = '🎯';
            document.getElementById('challengeIconGeneral').value = '🎯';
            document.getElementById('challengeColor').value = '#E74C3C';

            // Pokaż/ukryj odpowiednie pola
            document.getElementById('challengeGoalRow').style.display = isWeekly ? 'flex' : 'none';
            document.getElementById('challengeIconRowGeneral').style.display = isWeekly ? 'none' : 'flex';

            document.getElementById('challengeModal').classList.add('active');
        },

        editChallenge(id) {
            const challenge = this.challenges.find(c => c.id == id);
            if (!challenge) return;

            const isWeekly = challenge.typ === 'weekly';
            document.getElementById('challengeModalTitle').textContent = 'Edytuj challenge';
            document.getElementById('editChallengeId').value = id;
            document.getElementById('challengeType').value = challenge.typ;
            document.getElementById('challengeName').value = challenge.nazwa;
            document.getElementById('challengeDesc').value = challenge.opis || '';
            document.getElementById('challengeGoal').value = challenge.cel_dni;
            document.getElementById('challengeIcon').value = challenge.ikona;
            document.getElementById('challengeIconGeneral').value = challenge.ikona;
            document.getElementById('challengeColor').value = challenge.kolor;

            document.getElementById('challengeGoalRow').style.display = isWeekly ? 'flex' : 'none';
            document.getElementById('challengeIconRowGeneral').style.display = isWeekly ? 'none' : 'flex';

            document.getElementById('challengeModal').classList.add('active');
        },

        closeChallengeModal() {
            document.getElementById('challengeModal').classList.remove('active');
        },

        async saveChallenge() {
            const id = document.getElementById('editChallengeId').value;
            const type = document.getElementById('challengeType').value;
            const isWeekly = type === 'weekly';

            const data = {
                nazwa: document.getElementById('challengeName').value,
                opis: document.getElementById('challengeDesc').value,
                cel_dni: isWeekly ? document.getElementById('challengeGoal').value : 1,
                ikona: isWeekly ? document.getElementById('challengeIcon').value : document.getElementById('challengeIconGeneral').value,
                kolor: document.getElementById('challengeColor').value,
                typ: type,
                dni_w_tygodniu: isWeekly ? 7 : 0
            };

            if (!data.nazwa) {
                alert('Podaj nazwę challenge\'u');
                return;
            }

            if (id) {
                data.id = id;
                data.aktywny = 1;
                await this.ajax('habits_update_challenge', data);
            } else {
                data.period_id = this.currentPeriodId;
                await this.ajax('habits_add_challenge', data);
            }

            this.closeChallengeModal();
            this.loadChallenges();
        },

        async deleteChallenge(id) {
            if (!confirm('Czy na pewno chcesz usunąć ten challenge?')) return;

            await this.ajax('habits_delete_challenge', { id: id });
            this.loadChallenges();
        },

        async toggleGeneralChallenge(id) {
            const challenge = this.challenges.find(c => c.id == id);
            if (!challenge) return;

            const newCompleted = challenge.completed == 1 ? 0 : 1;
            await this.ajax('habits_toggle_general_challenge', { id: id, completed: newCompleted });
            this.loadChallenges();
        },

        // =============================================
        // SPORT FUNCTIONS
        // =============================================

        sportTypes: {
            silownia: { icon: '🏋️', name: 'Siłownia', unit: 'min' },
            bieganie: { icon: '🏃', name: 'Bieganie', unit: 'min' },
            rower: { icon: '🚴', name: 'Rower', unit: 'min' },
            plywanie: { icon: '🏊', name: 'Pływanie', unit: 'min' },
            padel: { icon: '🎾', name: 'Padel', unit: 'min' },
            spacer: { icon: '🚶', name: 'Spacer', unit: 'min' },
            inne: { icon: '⚡', name: 'Inne', unit: 'min' }
        },

        stepsThreshold: <?php echo get_option('habits_steps_threshold', 10000); ?>,

        // =============================================
        // STEPS FUNCTIONS
        // =============================================

        async loadSteps() {
            const result = await this.ajax('habits_get_steps', { days: 14 });
            if (!result.success) return;

            const tbody = document.getElementById('stepsTableBody');
            this.stepsThreshold = result.data.threshold;
            document.getElementById('stepsThreshold').value = this.stepsThreshold;

            if (result.data.entries.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: #6B7280; padding: 20px;">Brak wpisów kroków.</td></tr>';
                return;
            }

            let html = '';
            result.data.entries.forEach(entry => {
                const dateObj = new Date(entry.dzien);
                const dateStr = dateObj.toLocaleDateString('pl-PL', { weekday: 'short', day: 'numeric', month: 'short' });
                const steps = parseInt(entry.kroki);
                const aboveThreshold = steps >= this.stepsThreshold;

                html += `
                    <tr>
                        <td style="text-align: left;">${dateStr}</td>
                        <td style="text-align: right; font-weight: 600; color: ${aboveThreshold ? '#22C55E' : 'inherit'};">
                            ${steps.toLocaleString()}
                            ${aboveThreshold ? '<span style="margin-left: 5px;">✅</span>' : ''}
                        </td>
                        <td style="text-align: center;">
                            ${aboveThreshold && !entry.converted_to_activity ?
                                `<button class="habits-btn habits-btn-sm habits-btn-primary" onclick="HabitsApp.stepsToActivity(${entry.id}, '${entry.dzien}', ${steps})">→ Spacer</button>`
                                : ''}
                            <button class="habits-btn habits-btn-sm habits-btn-danger" onclick="HabitsApp.deleteSteps(${entry.id})">🗑️</button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        },

        async addSteps() {
            const date = document.getElementById('newStepsDate').value;
            const steps = parseInt(document.getElementById('newStepsValue').value) || 0;

            if (!date) {
                alert('Wybierz datę');
                return;
            }
            if (steps <= 0) {
                alert('Podaj liczbę kroków');
                return;
            }

            await this.ajax('habits_save_sport', {
                dzien: date,
                typ: 'kroki',
                kroki: steps,
                czas_minuty: 0,
                partie_ciala: ''
            });

            document.getElementById('newStepsValue').value = '';
            document.getElementById('newStepsDate').value = new Date().toISOString().split('T')[0];

            this.loadSteps();
            this.loadSportStats();
        },

        async deleteSteps(id) {
            if (!confirm('Usunąć ten wpis?')) return;
            await this.ajax('habits_delete_sport', { id: id });
            this.loadSteps();
            this.loadSportStats();
        },

        async saveStepsThreshold() {
            const threshold = parseInt(document.getElementById('stepsThreshold').value) || 10000;
            this.stepsThreshold = threshold;
            await this.ajax('habits_save_steps_threshold', { threshold: threshold });
            this.loadSteps();
        },

        async stepsToActivity(stepsId, date, steps) {
            // Dodaj spacer jako aktywność
            const minutes = Math.round(steps / 100); // ~100 kroków = 1 minuta spaceru

            await this.ajax('habits_save_sport', {
                dzien: date,
                typ: 'spacer',
                czas_minuty: minutes,
                kroki: 0,
                partie_ciala: ''
            });

            // Oznacz wpis kroków jako skonwertowany
            await this.ajax('habits_mark_steps_converted', { id: stepsId });

            this.loadSteps();
            this.loadSportHistory();
            this.loadSportStats();
        },

        // =============================================
        // ACTIVITY FUNCTIONS
        // =============================================

        onSportTypeChange() {
            const type = document.getElementById('sportType').value;
            const muscleSection = document.getElementById('muscleGroupsSection');
            const customGroup = document.getElementById('customActivityGroup');

            muscleSection.style.display = type === 'silownia' ? 'block' : 'none';
            customGroup.style.display = type === 'inne' ? 'flex' : 'none';
        },

        async saveActivity() {
            const type = document.getElementById('sportType').value;
            const date = document.getElementById('sportDate').value;
            const duration = parseInt(document.getElementById('sportDuration').value) || 0;

            if (!date) {
                alert('Wybierz datę');
                return;
            }

            if (duration === 0) {
                alert('Podaj czas trwania');
                return;
            }

            let data = {
                dzien: date,
                typ: type,
                czas_minuty: duration,
                kroki: 0,
                partie_ciala: ''
            };

            // Dla "inne" zapisz własną nazwę
            if (type === 'inne') {
                const customName = document.getElementById('customActivityName').value.trim();
                if (customName) {
                    data.custom_name = customName;
                }
            }

            // Dla siłowni zapisz partie ciała
            if (type === 'silownia') {
                const checkedMuscles = [];
                document.querySelectorAll('#muscleGroupsSection input:checked').forEach(cb => {
                    checkedMuscles.push(cb.value);
                });
                data.partie_ciala = checkedMuscles.join(',');
            }

            await this.ajax('habits_save_sport', data);

            // Clear form
            document.getElementById('sportDuration').value = '';
            document.getElementById('customActivityName').value = '';
            document.querySelectorAll('#muscleGroupsSection input').forEach(cb => cb.checked = false);

            // Refresh
            this.loadSportHistory();
            this.loadSportStats();
        },

        openActivityTypesModal() {
            alert('Zarządzanie typami aktywności będzie dostępne wkrótce!');
        },

        async loadSportHistory() {
            const filterDays = document.getElementById('sportHistoryFilter')?.value || '7';
            const filterType = document.getElementById('sportHistoryType')?.value || 'all';

            const today = new Date();
            let startDate;

            if (filterDays === 'all') {
                startDate = new Date('2020-01-01');
            } else {
                startDate = new Date(today);
                startDate.setDate(startDate.getDate() - parseInt(filterDays));
            }

            const result = await this.ajax('habits_get_sport', {
                date_start: this.formatDate(startDate),
                date_end: this.formatDate(today),
                typ: filterType
            });

            if (!result.success) return;

            const container = document.getElementById('sportHistory');

            if (result.data.length === 0) {
                container.innerHTML = '<p style="color: #6B7280; text-align: center;">Brak aktywności w wybranym okresie.</p>';
                return;
            }

            let html = '';
            // Filtruj kroki - teraz są w osobnej sekcji
            const activities = result.data.filter(e => e.typ !== 'kroki');

            if (activities.length === 0) {
                container.innerHTML = '<p style="color: #6B7280; text-align: center;">Brak aktywności w wybranym okresie.</p>';
                return;
            }

            activities.forEach(entry => {
                const typeInfo = this.sportTypes[entry.typ] || this.sportTypes.inne;
                const dateObj = new Date(entry.dzien);
                const dateStr = dateObj.toLocaleDateString('pl-PL', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

                const value = entry.czas_minuty;
                const details = 'minut';

                // Dla "inne" pokaż custom_name jeśli jest
                const typeName = (entry.typ === 'inne' && entry.custom_name) ? entry.custom_name : typeInfo.name;

                let musclesHtml = '';
                if (entry.partie_ciala) {
                    musclesHtml = '<div style="margin-top: 5px;">' +
                        entry.partie_ciala.split(',').map(m => `<span class="muscle-tag">${m}</span>`).join('') +
                        '</div>';
                }

                html += `
                    <div class="sport-entry">
                        <div class="sport-entry-icon">${typeInfo.icon}</div>
                        <div class="sport-entry-info">
                            <div class="sport-entry-type">${typeName}</div>
                            <div class="sport-entry-date">${dateStr}</div>
                            ${musclesHtml}
                        </div>
                        <div style="text-align: right;">
                            <div class="sport-entry-value">${value}</div>
                            <div class="sport-entry-details">${details}</div>
                        </div>
                        <button class="habits-btn habits-btn-sm habits-btn-danger" onclick="HabitsApp.deleteSport(${entry.id})" style="margin-left: 10px;">🗑️</button>
                    </div>
                `;
            });

            container.innerHTML = html;
        },

        async loadSportStats() {
            const today = new Date();
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);

            const result = await this.ajax('habits_get_sport_summary', {
                date_start: this.formatDate(monthStart),
                date_end: this.formatDate(today)
            });

            if (!result.success) return;

            const data = result.data;
            const container = document.getElementById('sportStats');

            let html = '<div class="sport-stat-grid">';

            html += `
                <div class="sport-stat-card">
                    <div class="sport-stat-value">👟 ${data.total_steps.toLocaleString()}</div>
                    <div class="sport-stat-label">Kroków łącznie</div>
                </div>
                <div class="sport-stat-card">
                    <div class="sport-stat-value">📊 ${data.avg_steps.toLocaleString()}</div>
                    <div class="sport-stat-label">Średnia/dzień</div>
                </div>
            `;

            // Treningi per typ
            if (data.by_type && data.by_type.length > 0) {
                data.by_type.forEach(t => {
                    if (t.typ !== 'kroki') {
                        const typeInfo = this.sportTypes[t.typ] || this.sportTypes.inne;
                        html += `
                            <div class="sport-stat-card">
                                <div class="sport-stat-value">${typeInfo.icon} ${t.count}x</div>
                                <div class="sport-stat-label">${typeInfo.name} (${t.total_minutes} min)</div>
                            </div>
                        `;
                    }
                });
            }

            html += '</div>';

            // Partie ciała
            if (data.muscle_counts && Object.keys(data.muscle_counts).length > 0) {
                html += '<div style="margin-top: 20px;"><strong>Partie mięśniowe:</strong><div style="margin-top: 8px;">';
                for (const [muscle, count] of Object.entries(data.muscle_counts)) {
                    html += `<span class="muscle-tag">${muscle} (${count}x)</span> `;
                }
                html += '</div></div>';
            }

            container.innerHTML = html;
        },

        async deleteSport(id) {
            if (!confirm('Usunąć tę aktywność?')) return;

            await this.ajax('habits_delete_sport', { id: id });
            this.loadSportHistory();
            this.loadSportStats();
        }
    };

    document.addEventListener('DOMContentLoaded', () => HabitsApp.init());
    </script>
    <?php
}

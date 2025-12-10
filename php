/**
 * Plugin Name: Zadaniomat OKR
 * Description: System zarządzania celami i zadaniami z rokami 90-dniowymi
 * Version: 4.0 AJAX
 * Author: Ty
 */

// =============================================
// KATEGORIE - DOMYŚLNE WARTOŚCI
// =============================================
define('ZADANIOMAT_DEFAULT_KATEGORIE', [
    'zapianowany' => 'Zapianowany',
    'klejpan' => 'Klejpan',
    'marka_langer' => 'Marka Langer',
    'marketing_construction' => 'Marketing Construction',
    'fjo' => 'FJO (Firma Jako Osobowość)',
    'obsluga_telefoniczna' => 'Obsługa telefoniczna'
]);

define('ZADANIOMAT_DEFAULT_KATEGORIE_ZADANIA', [
    'zapianowany' => 'Zapianowany',
    'klejpan' => 'Klejpan',
    'marka_langer' => 'Marka Langer',
    'marketing_construction' => 'Marketing Construction',
    'fjo' => 'FJO (Firma Jako Osobowość)',
    'obsluga_telefoniczna' => 'Obsługa telefoniczna',
    'sprawy_organizacyjne' => 'Sprawy Organizacyjne'
]);

// Funkcje do pobierania kategorii (z opcji lub domyślnych)
function zadaniomat_get_kategorie() {
    $saved = get_option('zadaniomat_kategorie');
    if ($saved && is_array($saved) && !empty($saved)) {
        return $saved;
    }
    return ZADANIOMAT_DEFAULT_KATEGORIE;
}

function zadaniomat_get_kategorie_zadania() {
    $saved = get_option('zadaniomat_kategorie_zadania');
    if ($saved && is_array($saved) && !empty($saved)) {
        return $saved;
    }
    return ZADANIOMAT_DEFAULT_KATEGORIE_ZADANIA;
}

// Stałe dla kompatybilności wstecznej (dynamicznie generowane)
define('ZADANIOMAT_KATEGORIE', zadaniomat_get_kategorie());
define('ZADANIOMAT_KATEGORIE_ZADANIA', zadaniomat_get_kategorie_zadania());

// =============================================
// TWORZENIE TABEL
// =============================================
function zadaniomat_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    $sql1 = "CREATE TABLE IF NOT EXISTS $table_roki (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nazwa VARCHAR(100) NOT NULL,
        data_start DATE NOT NULL,
        data_koniec DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    $table_cele_rok = $wpdb->prefix . 'zadaniomat_cele_rok';
    $sql2 = "CREATE TABLE IF NOT EXISTS $table_cele_rok (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rok_id INT NOT NULL,
        kategoria VARCHAR(50) NOT NULL,
        cel TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';
    $sql3 = "CREATE TABLE IF NOT EXISTS $table_okresy (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rok_id INT NOT NULL,
        nazwa VARCHAR(100) NOT NULL,
        data_start DATE NOT NULL,
        data_koniec DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
    $sql4 = "CREATE TABLE IF NOT EXISTS $table_cele_okres (
        id INT AUTO_INCREMENT PRIMARY KEY,
        okres_id INT NOT NULL,
        kategoria VARCHAR(50) NOT NULL,
        cel TEXT,
        status DECIMAL(3,1) DEFAULT NULL,
        osiagniety TINYINT(1) DEFAULT NULL,
        uwagi TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $sql5 = "CREATE TABLE IF NOT EXISTS $table_zadania (
        id INT AUTO_INCREMENT PRIMARY KEY,
        okres_id INT DEFAULT NULL,
        kategoria VARCHAR(50) NOT NULL,
        dzien DATE NOT NULL,
        zadanie VARCHAR(255) NOT NULL,
        cel_todo TEXT,
        planowany_czas INT DEFAULT 0,
        faktyczny_czas INT DEFAULT NULL,
        status DECIMAL(3,1) DEFAULT NULL,
        godzina_start TIME DEFAULT NULL,
        godzina_koniec TIME DEFAULT NULL,
        pozycja_harmonogram INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Tabela stałych zadań (cyklicznych)
    $table_stale = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $sql6 = "CREATE TABLE IF NOT EXISTS $table_stale (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nazwa VARCHAR(255) NOT NULL,
        kategoria VARCHAR(50) NOT NULL,
        planowany_czas INT DEFAULT 0,
        typ_powtarzania VARCHAR(50) NOT NULL DEFAULT 'codziennie',
        dni_tygodnia VARCHAR(50) DEFAULT NULL,
        dzien_miesiaca INT DEFAULT NULL,
        dni_przed_koncem_roku INT DEFAULT NULL,
        dni_przed_koncem_okresu INT DEFAULT NULL,
        minuty_po_starcie INT DEFAULT NULL,
        dodaj_do_listy TINYINT(1) DEFAULT 0,
        godzina_start TIME DEFAULT NULL,
        godzina_koniec TIME DEFAULT NULL,
        aktywne TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
    dbDelta($sql5);
    dbDelta($sql6);
}

add_action('admin_init', function() {
    global $wpdb;
    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_roki'") != $table_roki) {
        zadaniomat_create_tables();
    }
    
    // Migracja - dodaj nowe kolumny jeśli nie istnieją
    $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_cele_okres");
    
    if (!in_array('osiagniety', $columns)) {
        $wpdb->query("ALTER TABLE $table_cele_okres ADD COLUMN osiagniety TINYINT(1) DEFAULT NULL");
    }
    if (!in_array('uwagi', $columns)) {
        $wpdb->query("ALTER TABLE $table_cele_okres ADD COLUMN uwagi TEXT");
    }

    // Migracja - dodaj kolumny harmonogramu do zadań
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $zadania_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_zadania");

    if (!in_array('godzina_start', $zadania_columns)) {
        $wpdb->query("ALTER TABLE $table_zadania ADD COLUMN godzina_start TIME DEFAULT NULL");
    }
    if (!in_array('godzina_koniec', $zadania_columns)) {
        $wpdb->query("ALTER TABLE $table_zadania ADD COLUMN godzina_koniec TIME DEFAULT NULL");
    }
    if (!in_array('pozycja_harmonogram', $zadania_columns)) {
        $wpdb->query("ALTER TABLE $table_zadania ADD COLUMN pozycja_harmonogram INT DEFAULT NULL");
    }

    // Utwórz tabelę stałych zadań jeśli nie istnieje
    $table_stale = $wpdb->prefix . 'zadaniomat_stale_zadania';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_stale'") != $table_stale) {
        zadaniomat_create_tables();
    }

    // Migracja - dodaj kolumny do stałych zadań
    $stale_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_stale");
    if (!in_array('dni_przed_koncem_roku', $stale_columns)) {
        $wpdb->query("ALTER TABLE $table_stale ADD COLUMN dni_przed_koncem_roku INT DEFAULT NULL");
    }
    if (!in_array('dni_przed_koncem_okresu', $stale_columns)) {
        $wpdb->query("ALTER TABLE $table_stale ADD COLUMN dni_przed_koncem_okresu INT DEFAULT NULL");
    }
    if (!in_array('minuty_po_starcie', $stale_columns)) {
        $wpdb->query("ALTER TABLE $table_stale ADD COLUMN minuty_po_starcie INT DEFAULT NULL");
    }
    if (!in_array('dodaj_do_listy', $stale_columns)) {
        $wpdb->query("ALTER TABLE $table_stale ADD COLUMN dodaj_do_listy TINYINT(1) DEFAULT 0");
    }

    // Migracja - zmień typ_powtarzania na VARCHAR jeśli jest ENUM
    $column_info = $wpdb->get_row("SHOW COLUMNS FROM $table_stale WHERE Field = 'typ_powtarzania'");
    if ($column_info && strpos($column_info->Type, 'enum') !== false) {
        $wpdb->query("ALTER TABLE $table_stale MODIFY COLUMN typ_powtarzania VARCHAR(50) NOT NULL DEFAULT 'codziennie'");
    }
});

// =============================================
// HELPER FUNCTIONS
// =============================================
function zadaniomat_get_current_okres($date = null) {
    global $wpdb;
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';
    $date = $date ?: date('Y-m-d');
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_okresy WHERE %s BETWEEN data_start AND data_koniec LIMIT 1",
        $date
    ));
}

function zadaniomat_get_current_rok($date = null) {
    global $wpdb;
    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    $date = $date ?: date('Y-m-d');
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_roki WHERE %s BETWEEN data_start AND data_koniec LIMIT 1",
        $date
    ));
}

function zadaniomat_get_kategoria_label($key) {
    return ZADANIOMAT_KATEGORIE_ZADANIA[$key] ?? $key;
}

// =============================================
// MENU
// =============================================
add_action('admin_menu', function() {
    add_menu_page('Zadaniomat', 'Zadaniomat', 'manage_options', 'zadaniomat', 'zadaniomat_page_main', 'dashicons-list-view', 30);
    add_submenu_page('zadaniomat', 'Dashboard', '📋 Dashboard', 'manage_options', 'zadaniomat', 'zadaniomat_page_main');
    add_submenu_page('zadaniomat', 'Ustawienia', '⚙️ Ustawienia', 'manage_options', 'zadaniomat-settings', 'zadaniomat_page_settings');
});

// =============================================
// ALL AJAX HANDLERS
// =============================================

// Dodaj zadanie
add_action('wp_ajax_zadaniomat_add_task', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $task_date = sanitize_text_field($_POST['dzien']);
    $auto_okres = zadaniomat_get_current_okres($task_date);
    
    $wpdb->insert($table, [
        'okres_id' => $auto_okres ? $auto_okres->id : null,
        'kategoria' => sanitize_text_field($_POST['kategoria']),
        'dzien' => $task_date,
        'zadanie' => sanitize_text_field($_POST['zadanie']),
        'cel_todo' => sanitize_textarea_field($_POST['cel_todo']),
        'planowany_czas' => intval($_POST['planowany_czas'])
    ]);
    
    $task_id = $wpdb->insert_id;
    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $task_id));
    
    wp_send_json_success([
        'task' => $task,
        'kategoria_label' => zadaniomat_get_kategoria_label($task->kategoria)
    ]);
});

// Edytuj zadanie
add_action('wp_ajax_zadaniomat_edit_task', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $task_date = sanitize_text_field($_POST['dzien']);
    $auto_okres = zadaniomat_get_current_okres($task_date);
    $id = intval($_POST['id']);
    
    $wpdb->update($table, [
        'okres_id' => $auto_okres ? $auto_okres->id : null,
        'kategoria' => sanitize_text_field($_POST['kategoria']),
        'dzien' => $task_date,
        'zadanie' => sanitize_text_field($_POST['zadanie']),
        'cel_todo' => sanitize_textarea_field($_POST['cel_todo']),
        'planowany_czas' => intval($_POST['planowany_czas'])
    ], ['id' => $id]);
    
    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    
    wp_send_json_success([
        'task' => $task,
        'kategoria_label' => zadaniomat_get_kategoria_label($task->kategoria)
    ]);
});

// Usuń zadanie
add_action('wp_ajax_zadaniomat_delete_task', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $id = intval($_POST['id']);

    $wpdb->delete($table, ['id' => $id]);

    wp_send_json_success();
});

// Zbiorowe usuwanie zadań
add_action('wp_ajax_zadaniomat_bulk_delete', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];

    if (empty($ids)) {
        wp_send_json_error(['message' => 'Brak zadań do usunięcia']);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", $ids));

    wp_send_json_success(['deleted' => count($ids)]);
});

// Zbiorowe kopiowanie zadań
add_action('wp_ajax_zadaniomat_bulk_copy', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    $target_date = sanitize_text_field($_POST['target_date']);

    if (empty($ids) || empty($target_date)) {
        wp_send_json_error(['message' => 'Brak zadań lub daty docelowej']);
    }

    $auto_okres = zadaniomat_get_current_okres($target_date);

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $tasks = $wpdb->get_results($wpdb->prepare("SELECT kategoria, zadanie, cel_todo, planowany_czas FROM $table WHERE id IN ($placeholders)", $ids));

    foreach ($tasks as $task) {
        $wpdb->insert($table, [
            'okres_id' => $auto_okres ? $auto_okres->id : null,
            'kategoria' => $task->kategoria,
            'dzien' => $target_date,
            'zadanie' => $task->zadanie,
            'cel_todo' => $task->cel_todo,
            'planowany_czas' => $task->planowany_czas
        ]);
    }

    wp_send_json_success(['copied' => count($tasks)]);
});

// Szybka aktualizacja (status, faktyczny czas)
add_action('wp_ajax_zadaniomat_quick_update', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $id = intval($_POST['id']);
    $field = sanitize_text_field($_POST['field']);
    $value = $_POST['value'] === '' ? null : sanitize_text_field($_POST['value']);
    
    if (in_array($field, ['faktyczny_czas', 'status'])) {
        $wpdb->update($table, [$field => $value], ['id' => $id]);
    }
    
    wp_send_json_success();
});

// Przenieś zadanie
add_action('wp_ajax_zadaniomat_move_task', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $id = intval($_POST['id']);
    $new_date = sanitize_text_field($_POST['new_date']);
    $new_okres = zadaniomat_get_current_okres($new_date);
    
    $wpdb->update($table, [
        'dzien' => $new_date,
        'okres_id' => $new_okres ? $new_okres->id : null
    ], ['id' => $id]);
    
    wp_send_json_success();
});

// Pobierz zadania dla zakresu dat
add_action('wp_ajax_zadaniomat_get_tasks', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $start = sanitize_text_field($_POST['start']);
    $end = sanitize_text_field($_POST['end']);
    
    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE dzien BETWEEN %s AND %s ORDER BY dzien ASC, id ASC",
        $start, $end
    ));
    
    // Dodaj labele kategorii
    foreach ($tasks as &$task) {
        $task->kategoria_label = zadaniomat_get_kategoria_label($task->kategoria);
    }
    
    wp_send_json_success(['tasks' => $tasks]);
});

// Pobierz nieukończone zadania
add_action('wp_ajax_zadaniomat_get_overdue', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_zadania';
    
    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE dzien < %s AND (status IS NULL OR status < 0.8) ORDER BY dzien ASC",
        date('Y-m-d')
    ));
    
    foreach ($tasks as &$task) {
        $task->kategoria_label = zadaniomat_get_kategoria_label($task->kategoria);
    }
    
    wp_send_json_success(['tasks' => $tasks]);
});

// Pobierz dni z zadaniami (dla kalendarza)
add_action('wp_ajax_zadaniomat_get_calendar_dots', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $month = sanitize_text_field($_POST['month']);
    $month_start = $month . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));
    
    $days = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT dzien FROM $table WHERE dzien BETWEEN %s AND %s",
        $month_start, $month_end
    ));
    
    wp_send_json_success(['days' => $days]);
});

// Zapisz cel okresu
add_action('wp_ajax_zadaniomat_save_cel_okres', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $okres_id = intval($_POST['okres_id']);
    $kategoria = sanitize_text_field($_POST['kategoria']);
    $cel = sanitize_textarea_field($_POST['cel']);
    
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table WHERE okres_id = %d AND kategoria = %s", $okres_id, $kategoria
    ));
    
    if ($existing) {
        $wpdb->update($table, ['cel' => $cel], ['id' => $existing->id]);
        $cel_id = $existing->id;
    } else {
        $wpdb->insert($table, ['okres_id' => $okres_id, 'kategoria' => $kategoria, 'cel' => $cel]);
        $cel_id = $wpdb->insert_id;
    }
    
    wp_send_json_success(['cel_id' => $cel_id]);
});

// Aktualizuj status celu okresu
add_action('wp_ajax_zadaniomat_update_cel_okres_status', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $id = intval($_POST['id']);
    $status = $_POST['status'] === '' ? null : floatval($_POST['status']);
    
    $wpdb->update($table, ['status' => $status], ['id' => $id]);
    wp_send_json_success();
});

// Pobierz cele okresu (do modala)
add_action('wp_ajax_zadaniomat_get_okres_cele', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table_cele = $wpdb->prefix . 'zadaniomat_cele_okres';
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';
    $okres_id = intval($_POST['okres_id']);
    
    $okres = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_okresy WHERE id = %d", $okres_id));
    $cele = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_cele WHERE okres_id = %d", $okres_id));
    
    $cele_by_kat = [];
    foreach ($cele as $c) {
        $cele_by_kat[$c->kategoria] = $c;
    }
    
    wp_send_json_success([
        'okres' => $okres,
        'cele' => $cele_by_kat,
        'kategorie' => ZADANIOMAT_KATEGORIE
    ]);
});

// Zapisz podsumowanie celu okresu (osiągnięty + uwagi)
add_action('wp_ajax_zadaniomat_save_cel_podsumowanie', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $okres_id = intval($_POST['okres_id']);
    $kategoria = sanitize_text_field($_POST['kategoria']);
    $osiagniety = $_POST['osiagniety'] === '' ? null : intval($_POST['osiagniety']);
    $uwagi = sanitize_textarea_field($_POST['uwagi']);
    
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table WHERE okres_id = %d AND kategoria = %s", $okres_id, $kategoria
    ));
    
    if ($existing) {
        $wpdb->update($table, ['osiagniety' => $osiagniety, 'uwagi' => $uwagi], ['id' => $existing->id]);
    } else {
        $wpdb->insert($table, ['okres_id' => $okres_id, 'kategoria' => $kategoria, 'osiagniety' => $osiagniety, 'uwagi' => $uwagi]);
    }
    
    wp_send_json_success();
});

// Zapisz cel roku
add_action('wp_ajax_zadaniomat_save_cel_rok', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_cele_rok';
    $rok_id = intval($_POST['rok_id']);
    $kategoria = sanitize_text_field($_POST['kategoria']);
    $cel = sanitize_textarea_field($_POST['cel']);
    
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table WHERE rok_id = %d AND kategoria = %s", $rok_id, $kategoria
    ));
    
    if ($existing) {
        $wpdb->update($table, ['cel' => $cel], ['id' => $existing->id]);
    } else {
        $wpdb->insert($table, ['rok_id' => $rok_id, 'kategoria' => $kategoria, 'cel' => $cel]);
    }
    
    wp_send_json_success();
});

// Pobierz kategorie
add_action('wp_ajax_zadaniomat_get_kategorie', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    wp_send_json_success([
        'kategorie' => zadaniomat_get_kategorie(),
        'kategorie_zadania' => zadaniomat_get_kategorie_zadania()
    ]);
});

// Zapisz kategorie
add_action('wp_ajax_zadaniomat_save_kategorie', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $kategorie = [];
    $kategorie_zadania = [];

    if (isset($_POST['kategorie']) && is_array($_POST['kategorie'])) {
        foreach ($_POST['kategorie'] as $kat) {
            $key = sanitize_key($kat['key']);
            $label = sanitize_text_field($kat['label']);
            if ($key && $label) {
                $kategorie[$key] = $label;
            }
        }
    }

    if (isset($_POST['kategorie_zadania']) && is_array($_POST['kategorie_zadania'])) {
        foreach ($_POST['kategorie_zadania'] as $kat) {
            $key = sanitize_key($kat['key']);
            $label = sanitize_text_field($kat['label']);
            if ($key && $label) {
                $kategorie_zadania[$key] = $label;
            }
        }
    }

    if (!empty($kategorie)) {
        update_option('zadaniomat_kategorie', $kategorie);
    }
    if (!empty($kategorie_zadania)) {
        update_option('zadaniomat_kategorie_zadania', $kategorie_zadania);
    }

    wp_send_json_success();
});

// Resetuj kategorie do domyślnych
add_action('wp_ajax_zadaniomat_reset_kategorie', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    delete_option('zadaniomat_kategorie');
    delete_option('zadaniomat_kategorie_zadania');

    wp_send_json_success([
        'kategorie' => ZADANIOMAT_DEFAULT_KATEGORIE,
        'kategorie_zadania' => ZADANIOMAT_DEFAULT_KATEGORIE_ZADANIA
    ]);
});

// =============================================
// HARMONOGRAM DNIA - AJAX HANDLERS
// =============================================

// Pobierz stałe zadania
add_action('wp_ajax_zadaniomat_get_stale_zadania', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $stale = $wpdb->get_results("SELECT * FROM $table ORDER BY godzina_start ASC, id ASC");

    foreach ($stale as &$s) {
        $s->kategoria_label = zadaniomat_get_kategoria_label($s->kategoria);
    }

    wp_send_json_success(['stale_zadania' => $stale]);
});

// Dodaj stałe zadanie
add_action('wp_ajax_zadaniomat_add_stale_zadanie', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';

    $wpdb->insert($table, [
        'nazwa' => sanitize_text_field($_POST['nazwa']),
        'kategoria' => sanitize_text_field($_POST['kategoria']),
        'planowany_czas' => intval($_POST['planowany_czas']),
        'typ_powtarzania' => sanitize_text_field($_POST['typ_powtarzania']),
        'dni_tygodnia' => sanitize_text_field($_POST['dni_tygodnia'] ?? ''),
        'dzien_miesiaca' => !empty($_POST['dzien_miesiaca']) ? intval($_POST['dzien_miesiaca']) : null,
        'dni_przed_koncem_roku' => !empty($_POST['dni_przed_koncem_roku']) ? intval($_POST['dni_przed_koncem_roku']) : null,
        'dni_przed_koncem_okresu' => !empty($_POST['dni_przed_koncem_okresu']) ? intval($_POST['dni_przed_koncem_okresu']) : null,
        'minuty_po_starcie' => !empty($_POST['minuty_po_starcie']) ? intval($_POST['minuty_po_starcie']) : null,
        'dodaj_do_listy' => !empty($_POST['dodaj_do_listy']) ? 1 : 0,
        'godzina_start' => !empty($_POST['godzina_start']) ? sanitize_text_field($_POST['godzina_start']) : null,
        'godzina_koniec' => !empty($_POST['godzina_koniec']) ? sanitize_text_field($_POST['godzina_koniec']) : null,
        'aktywne' => 1
    ]);

    $id = $wpdb->insert_id;
    $zadanie = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    $zadanie->kategoria_label = zadaniomat_get_kategoria_label($zadanie->kategoria);

    wp_send_json_success(['zadanie' => $zadanie]);
});

// Edytuj stałe zadanie
add_action('wp_ajax_zadaniomat_edit_stale_zadanie', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $id = intval($_POST['id']);

    $wpdb->update($table, [
        'nazwa' => sanitize_text_field($_POST['nazwa']),
        'kategoria' => sanitize_text_field($_POST['kategoria']),
        'planowany_czas' => intval($_POST['planowany_czas']),
        'typ_powtarzania' => sanitize_text_field($_POST['typ_powtarzania']),
        'dni_tygodnia' => sanitize_text_field($_POST['dni_tygodnia'] ?? ''),
        'dzien_miesiaca' => !empty($_POST['dzien_miesiaca']) ? intval($_POST['dzien_miesiaca']) : null,
        'dni_przed_koncem_roku' => !empty($_POST['dni_przed_koncem_roku']) ? intval($_POST['dni_przed_koncem_roku']) : null,
        'dni_przed_koncem_okresu' => !empty($_POST['dni_przed_koncem_okresu']) ? intval($_POST['dni_przed_koncem_okresu']) : null,
        'minuty_po_starcie' => !empty($_POST['minuty_po_starcie']) ? intval($_POST['minuty_po_starcie']) : null,
        'dodaj_do_listy' => !empty($_POST['dodaj_do_listy']) ? 1 : 0,
        'godzina_start' => !empty($_POST['godzina_start']) ? sanitize_text_field($_POST['godzina_start']) : null,
        'godzina_koniec' => !empty($_POST['godzina_koniec']) ? sanitize_text_field($_POST['godzina_koniec']) : null
    ], ['id' => $id]);

    $zadanie = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    $zadanie->kategoria_label = zadaniomat_get_kategoria_label($zadanie->kategoria);

    wp_send_json_success(['zadanie' => $zadanie]);
});

// Usuń stałe zadanie
add_action('wp_ajax_zadaniomat_delete_stale_zadanie', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $id = intval($_POST['id']);

    $wpdb->delete($table, ['id' => $id]);

    wp_send_json_success();
});

// Toggle aktywność stałego zadania
add_action('wp_ajax_zadaniomat_toggle_stale_zadanie', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $id = intval($_POST['id']);
    $aktywne = intval($_POST['aktywne']);

    $wpdb->update($table, ['aktywne' => $aktywne], ['id' => $id]);

    wp_send_json_success();
});

// Pobierz stałe zadania dla danego dnia (sprawdza typ powtarzania)
add_action('wp_ajax_zadaniomat_get_stale_for_day', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $dzien = sanitize_text_field($_POST['dzien']);
    $date = new DateTime($dzien);
    $dayOfWeek = strtolower($date->format('D')); // mon, tue, wed...
    $dayOfWeekPl = ['mon' => 'pn', 'tue' => 'wt', 'wed' => 'sr', 'thu' => 'cz', 'fri' => 'pt', 'sat' => 'so', 'sun' => 'nd'];
    $dayPl = $dayOfWeekPl[$dayOfWeek];
    $dayOfMonth = intval($date->format('j'));

    $stale = $wpdb->get_results("SELECT * FROM $table WHERE aktywne = 1 ORDER BY godzina_start ASC");

    // Pobierz aktualny rok (90-dniowy okres) i okres dla tego dnia
    $current_rok = zadaniomat_get_current_rok($dzien);
    $current_okres = zadaniomat_get_current_okres($dzien);

    // Pobierz godzinę startu dnia (dla minuty_po_starcie)
    $start_dnia = get_option('zadaniomat_start_dnia_' . $dzien, '');

    $matching = [];
    foreach ($stale as $s) {
        $match = false;

        if ($s->typ_powtarzania === 'codziennie') {
            $match = true;
        } elseif ($s->typ_powtarzania === 'dni_tygodnia' && !empty($s->dni_tygodnia)) {
            $dni = explode(',', $s->dni_tygodnia);
            $match = in_array($dayPl, $dni);
        } elseif ($s->typ_powtarzania === 'dzien_miesiaca' && $s->dzien_miesiaca) {
            $match = ($dayOfMonth === intval($s->dzien_miesiaca));
        } elseif ($s->typ_powtarzania === 'dni_przed_koncem_roku' && $s->dni_przed_koncem_roku && $current_rok) {
            $rok_koniec = new DateTime($current_rok->data_koniec);
            $diff = $rok_koniec->diff($date);
            if ($diff->invert === 0 && $diff->days === intval($s->dni_przed_koncem_roku)) {
                $match = true;
            }
        } elseif ($s->typ_powtarzania === 'dni_przed_koncem_okresu' && $s->dni_przed_koncem_okresu && $current_okres) {
            $okres_koniec = new DateTime($current_okres->data_koniec);
            $diff = $okres_koniec->diff($date);
            if ($diff->invert === 0 && $diff->days === intval($s->dni_przed_koncem_okresu)) {
                $match = true;
            }
        }

        if ($match) {
            $s->kategoria_label = zadaniomat_get_kategoria_label($s->kategoria);

            // Oblicz godzinę startu jeśli ustawiono minuty_po_starcie
            if ($s->minuty_po_starcie && $start_dnia) {
                $start_time = DateTime::createFromFormat('H:i', $start_dnia);
                if ($start_time) {
                    $start_time->modify('+' . intval($s->minuty_po_starcie) . ' minutes');
                    $s->godzina_start = $start_time->format('H:i:s');
                }
            }

            $matching[] = $s;
        }
    }

    wp_send_json_success(['stale_zadania' => $matching]);
});

// Aktualizuj godziny zadania w harmonogramie
add_action('wp_ajax_zadaniomat_update_harmonogram', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $id = intval($_POST['id']);
    $godzina_start = !empty($_POST['godzina_start']) ? sanitize_text_field($_POST['godzina_start']) : null;
    $godzina_koniec = !empty($_POST['godzina_koniec']) ? sanitize_text_field($_POST['godzina_koniec']) : null;
    $pozycja = isset($_POST['pozycja']) ? intval($_POST['pozycja']) : null;

    $wpdb->update($table, [
        'godzina_start' => $godzina_start,
        'godzina_koniec' => $godzina_koniec,
        'pozycja_harmonogram' => $pozycja
    ], ['id' => $id]);

    wp_send_json_success();
});

// Pobierz zadania na dziś z harmonogramem
add_action('wp_ajax_zadaniomat_get_harmonogram', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $table_stale = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $dzien = sanitize_text_field($_POST['dzien']);

    // Pobierz zadania na ten dzień
    $zadania = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_zadania WHERE dzien = %s ORDER BY godzina_start ASC, pozycja_harmonogram ASC, id ASC",
        $dzien
    ));

    foreach ($zadania as &$z) {
        $z->kategoria_label = zadaniomat_get_kategoria_label($z->kategoria);
        $z->is_stale = false;
    }

    // Pobierz stałe zadania dla tego dnia
    $date = new DateTime($dzien);
    $dayOfWeek = strtolower($date->format('D'));
    $dayOfWeekPl = ['mon' => 'pn', 'tue' => 'wt', 'wed' => 'sr', 'thu' => 'cz', 'fri' => 'pt', 'sat' => 'so', 'sun' => 'nd'];
    $dayPl = $dayOfWeekPl[$dayOfWeek];
    $dayOfMonth = intval($date->format('j'));

    $stale = $wpdb->get_results("SELECT * FROM $table_stale WHERE aktywne = 1 ORDER BY godzina_start ASC");
    $stale_matching = [];

    // Pobierz aktualny rok (90-dniowy okres) i okres dla tego dnia
    $current_rok = zadaniomat_get_current_rok($dzien);
    $current_okres = zadaniomat_get_current_okres($dzien);

    // Pobierz godzinę startu dnia (dla minuty_po_starcie)
    $start_dnia = get_option('zadaniomat_start_dnia_' . $dzien, '');

    foreach ($stale as $s) {
        $match = false;

        if ($s->typ_powtarzania === 'codziennie') {
            $match = true;
        } elseif ($s->typ_powtarzania === 'dni_tygodnia' && !empty($s->dni_tygodnia)) {
            $dni = explode(',', $s->dni_tygodnia);
            $match = in_array($dayPl, $dni);
        } elseif ($s->typ_powtarzania === 'dzien_miesiaca' && $s->dzien_miesiaca) {
            $match = ($dayOfMonth === intval($s->dzien_miesiaca));
        } elseif ($s->typ_powtarzania === 'dni_przed_koncem_roku' && $s->dni_przed_koncem_roku && $current_rok) {
            // Oblicz ile dni przed końcem roku (90-dniowego okresu)
            $rok_koniec = new DateTime($current_rok->data_koniec);
            $diff = $rok_koniec->diff($date);
            if ($diff->invert === 0 && $diff->days === intval($s->dni_przed_koncem_roku)) {
                $match = true;
            }
        } elseif ($s->typ_powtarzania === 'dni_przed_koncem_okresu' && $s->dni_przed_koncem_okresu && $current_okres) {
            // Oblicz ile dni przed końcem okresu 2-tygodniowego
            $okres_koniec = new DateTime($current_okres->data_koniec);
            $diff = $okres_koniec->diff($date);
            if ($diff->invert === 0 && $diff->days === intval($s->dni_przed_koncem_okresu)) {
                $match = true;
            }
        }

        if ($match) {
            $s->kategoria_label = zadaniomat_get_kategoria_label($s->kategoria);
            $s->is_stale = true;
            $s->zadanie = $s->nazwa;

            // Oblicz godzinę startu jeśli ustawiono minuty_po_starcie
            if ($s->minuty_po_starcie && $start_dnia) {
                $start_time = DateTime::createFromFormat('H:i', $start_dnia);
                if ($start_time) {
                    $start_time->modify('+' . intval($s->minuty_po_starcie) . ' minutes');
                    $s->godzina_start = $start_time->format('H:i:s');
                }
            }

            $stale_matching[] = $s;
        }
    }

    wp_send_json_success([
        'zadania' => $zadania,
        'stale_zadania' => $stale_matching
    ]);
});

// Zapisz godzinę startu dnia
add_action('wp_ajax_zadaniomat_save_start_dnia', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $godzina = sanitize_text_field($_POST['godzina']);
    $dzien = sanitize_text_field($_POST['dzien']);

    update_option('zadaniomat_start_dnia_' . $dzien, $godzina);

    wp_send_json_success();
});

// Pobierz godzinę startu dnia
add_action('wp_ajax_zadaniomat_get_start_dnia', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $dzien = sanitize_text_field($_POST['dzien']);
    $godzina = get_option('zadaniomat_start_dnia_' . $dzien, '');

    wp_send_json_success(['godzina' => $godzina]);
});

// =============================================
// STYLE CSS
// =============================================
add_action('admin_head', function() {
    $page = isset($_GET['page']) ? $_GET['page'] : '';
    if (strpos($page, 'zadaniomat') !== false) {
        ?>
        <style>
            .zadaniomat-wrap { max-width: 1600px; margin: 20px auto; }
            .zadaniomat-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
            .zadaniomat-card h2 { margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 12px; font-size: 18px; }
            
            .main-layout { display: grid; grid-template-columns: 320px 1fr; gap: 20px; }
            @media (max-width: 1200px) { .main-layout { grid-template-columns: 1fr; } }
            
            /* Kalendarz */
            .calendar-wrap { background: #fff; border-radius: 12px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
            .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
            .calendar-header h3 { margin: 0; font-size: 16px; }
            .calendar-nav { display: flex; gap: 5px; }
            .calendar-nav button { background: #f0f0f0; border: none; padding: 5px 12px; border-radius: 6px; cursor: pointer; }
            .calendar-nav button:hover { background: #e0e0e0; }
            .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
            .calendar-day-header { text-align: center; font-size: 11px; font-weight: 600; color: #888; padding: 8px 0; }
            .calendar-day { 
                aspect-ratio: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; 
                border-radius: 8px; cursor: pointer; font-size: 14px; position: relative; transition: all 0.2s;
            }
            .calendar-day:hover { background: #f5f5f5; }
            .calendar-day.other-month { color: #ccc; }
            .calendar-day.today { background: #e3f2fd; font-weight: 600; }
            .calendar-day.selected { background: #667eea; color: #fff; }
            .calendar-day.has-tasks::after { content: ''; width: 6px; height: 6px; background: #28a745; border-radius: 50%; position: absolute; bottom: 4px; }
            .calendar-day.selected.has-tasks::after { background: #fff; }
            
            /* Okres banner */
            .okres-banner { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
            .okres-banner h2 { margin: 0 0 5px 0; color: #fff; border: none; padding: 0; font-size: 20px; }
            .okres-banner .dates { opacity: 0.9; font-size: 14px; }
            .no-okres-banner { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
            
            /* Cele grid */
            .cele-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
            .cel-card { background: #f8f9fa; padding: 12px; border-radius: 8px; border-left: 4px solid #007bff; }
            .cel-card.zapianowany { border-left-color: #28a745; }
            .cel-card.klejpan { border-left-color: #17a2b8; }
            .cel-card.marka_langer { border-left-color: #ffc107; }
            .cel-card.marketing_construction { border-left-color: #dc3545; }
            .cel-card.fjo { border-left-color: #6f42c1; }
            .cel-card.obsluga_telefoniczna { border-left-color: #e91e63; }
            .cel-card h4 { margin: 0 0 8px 0; font-size: 12px; color: #666; }
            .cel-card textarea { width: 100%; min-height: 50px; padding: 8px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 13px; resize: vertical; }
            .cel-card .status-row { margin-top: 8px; display: flex; align-items: center; gap: 8px; }
            .cel-card .status-row select { padding: 4px 8px; font-size: 12px; border-radius: 4px; border: 1px solid #ddd; }
            
            /* Formularz */
            .task-form { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
            .task-form h3 { margin: 0 0 15px 0; font-size: 16px; }
            .form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
            .form-group { flex: 1; min-width: 150px; }
            .form-group.wide { flex: 2; min-width: 300px; }
            .form-group label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
            .form-group input, .form-group select, .form-group textarea { 
                width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box;
            }
            .form-group textarea { min-height: 60px; resize: vertical; }
            .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
            
            /* Dzienne sekcje */
            .day-section { margin-bottom: 25px; }
            .day-header { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 12px 16px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #dee2e6; }
            .day-header.today-header { background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-bottom-color: #28a745; }
            .day-header h3 { margin: 0; font-size: 15px; }
            .day-header .day-stats { font-size: 13px; color: #666; }
            
            .day-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 0 0 10px 10px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            .day-table th, .day-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
            .day-table th { background: #fafafa; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #888; }
            .day-table tr:last-child td { border-bottom: none; }
            .day-table tr:hover { background: #fafafa; }
            
            .status-done { background-color: #d4edda !important; }
            .status-partial { background-color: #fff3cd !important; }
            .status-none { background-color: #f8d7da !important; }
            
            .inline-input { width: 60px; padding: 6px; border: 1px solid #ddd; border-radius: 6px; text-align: center; font-size: 13px; }
            .inline-select { padding: 6px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
            
            .btn-delete { color: #dc3545; background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 4px; }
            .btn-delete:hover { background: #f8d7da; }
            .btn-edit { color: #007bff; background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 4px; }
            .btn-edit:hover { background: #e3f2fd; }

            /* Bulk actions */
            .task-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #667eea; }
            .bulk-actions { display: none; align-items: center; gap: 10px; margin-left: 15px; }
            .bulk-actions.visible { display: flex; }
            .bulk-actions button { padding: 5px 12px; font-size: 12px; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px; }
            .btn-bulk-delete { background: #dc3545; color: #fff; }
            .btn-bulk-delete:hover { background: #c82333; }
            .btn-bulk-copy { background: #667eea; color: #fff; }
            .btn-bulk-copy:hover { background: #5a6fd6; }
            .bulk-copy-date { padding: 4px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; }
            .select-all-label { font-size: 12px; color: #666; display: flex; align-items: center; gap: 5px; cursor: pointer; }
            .day-header-left { display: flex; align-items: center; gap: 10px; }
            .selected-count { font-size: 12px; color: #667eea; font-weight: 600; }
            
            .kategoria-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
            .kategoria-badge.zapianowany { background: #d4edda; color: #155724; }
            .kategoria-badge.klejpan { background: #d1ecf1; color: #0c5460; }
            .kategoria-badge.marka_langer { background: #fff3cd; color: #856404; }
            .kategoria-badge.marketing_construction { background: #f8d7da; color: #721c24; }
            .kategoria-badge.fjo { background: #e2d9f3; color: #4a235a; }
            .kategoria-badge.sprawy_organizacyjne { background: #e2e3e5; color: #383d41; }
            .kategoria-badge.obsluga_telefoniczna { background: #fce4ec; color: #880e4f; }

            .saved-flash { animation: flash-green 0.5s ease; }
            @keyframes flash-green { 0% { background-color: #28a745; color: #fff; } 100% { background-color: transparent; } }
            
            .empty-day { text-align: center; padding: 30px; color: #999; font-size: 14px; background: #fff; border-radius: 0 0 10px 10px; }
            .empty-day-cell { text-align: center; padding: 20px; color: #999; font-size: 14px; }
            
            /* Empty slots for today */
            .empty-slot { background: #f8f9fa; }
            .empty-slot:hover { background: #f0f0f0; }
            .slot-input { 
                width: 100%; 
                padding: 8px 10px; 
                border: 1px dashed #ccc; 
                border-radius: 6px; 
                font-size: 13px; 
                background: #fff;
                transition: all 0.2s;
            }
            .slot-input:focus {
                border-style: solid;
                border-color: #667eea;
                outline: none;
                box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            }
            .slot-input::placeholder { color: #bbb; }
            .btn-add-slot {
                background: #28a745;
                color: #fff;
                border: none;
                padding: 6px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
            }
            .btn-add-slot:hover { background: #218838; }
            .btn-hide-slot {
                background: none;
                color: #6c757d;
                border: none;
                padding: 6px 8px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                margin-left: 4px;
            }
            .btn-hide-slot:hover { background: #f0f0f0; color: #333; }

            /* Copy/Paste buttons */
            .btn-copy {
                background: none;
                border: none;
                cursor: pointer;
                padding: 4px 6px;
                border-radius: 4px;
                font-size: 14px;
                color: #6c757d;
            }
            .btn-copy:hover { background: #e9ecef; color: #495057; }
            .btn-paste {
                background: #17a2b8;
                color: #fff;
                border: none;
                padding: 4px 10px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 12px;
                margin-right: 10px;
            }
            .btn-paste:hover { background: #138496; }
            
            .day-header-actions {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .action-buttons {
                white-space: nowrap;
            }
            .action-buttons button {
                margin-right: 2px;
            }
            
            /* Overdue alerts */
            .overdue-alert { background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%); border: 1px solid #fc8181; border-left: 4px solid #e53e3e; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; }
            .overdue-alert h3 { margin: 0 0 12px 0; color: #c53030; font-size: 16px; }
            .overdue-task { background: #fff; border-radius: 8px; padding: 12px 15px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .overdue-task:last-child { margin-bottom: 0; }
            .overdue-task-info { flex: 1; min-width: 200px; }
            .overdue-task-info .task-name { font-weight: 600; color: #333; }
            .overdue-task-info .task-meta { font-size: 12px; color: #888; margin-top: 3px; }
            .overdue-task-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
            .overdue-task-actions input[type="date"] { padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
            .overdue-task-actions button, .overdue-task-actions .btn { padding: 6px 12px; border-radius: 6px; font-size: 13px; cursor: pointer; border: none; }
            .btn-move { background: #4299e1; color: #fff; }
            .btn-move:hover { background: #3182ce; }
            .btn-complete { background: #48bb78; color: #fff; }
            .btn-complete:hover { background: #38a169; }
            .overdue-status-select { padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
            
            /* Toast notifications */
            .toast { position: fixed; bottom: 20px; right: 20px; background: #333; color: #fff; padding: 12px 20px; border-radius: 8px; z-index: 9999; animation: slideIn 0.3s ease; }
            .toast.success { background: #28a745; }
            .toast.error { background: #dc3545; }
            @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            
            /* Loading overlay */
            .loading { opacity: 0.5; pointer-events: none; }
            
            /* Edit modal */
            .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9998; display: flex; align-items: center; justify-content: center; }
            .modal { background: #fff; border-radius: 12px; padding: 25px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto; }
            .modal h3 { margin: 0 0 20px 0; }
            .modal-buttons { display: flex; gap: 10px; margin-top: 20px; }
            .modal-close { position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #999; }
            .modal-close:hover { color: #333; }
            
            /* Okres review modal */
            .okres-modal { position: relative; }
            .okres-modal-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; margin: -25px -25px 20px -25px; border-radius: 12px 12px 0 0; }
            .okres-modal-header h3 { color: #fff; margin: 0; }
            .okres-modal-header .dates { opacity: 0.9; font-size: 14px; margin-top: 5px; }
            .okres-modal-header.past { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
            
            .cel-review-card { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #007bff; }
            .cel-review-card.zapianowany { border-left-color: #28a745; }
            .cel-review-card.klejpan { border-left-color: #17a2b8; }
            .cel-review-card.marka_langer { border-left-color: #ffc107; }
            .cel-review-card.marketing_construction { border-left-color: #dc3545; }
            .cel-review-card.fjo { border-left-color: #6f42c1; }
            .cel-review-card.obsluga_telefoniczna { border-left-color: #e91e63; }
            .cel-review-card h4 { margin: 0 0 10px 0; font-size: 14px; color: #333; }
            .cel-review-card .cel-text { background: #fff; padding: 10px; border-radius: 6px; margin-bottom: 10px; font-size: 13px; color: #555; min-height: 40px; }
            .cel-review-card .cel-text.empty { color: #999; font-style: italic; }
            .cel-review-row { display: flex; gap: 15px; align-items: flex-start; flex-wrap: wrap; }
            .cel-review-row .field { flex: 1; min-width: 150px; }
            .cel-review-row label { display: block; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 4px; }
            .cel-review-row select, .cel-review-row textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
            .cel-review-row textarea { min-height: 60px; resize: vertical; }
            
            .osiagniety-yes { background: #d4edda !important; border-color: #28a745 !important; }
            .osiagniety-no { background: #f8d7da !important; border-color: #dc3545 !important; }
            .osiagniety-partial { background: #fff3cd !important; border-color: #ffc107 !important; }
            
            /* Settings */
            .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
            .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 15px; }
            .settings-table { width: 100%; border-collapse: collapse; }
            .settings-table th, .settings-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
            .settings-table th { background: #f8f9fa; }
            
            /* Info box */
            .day-info { margin-top: 15px; padding: 15px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
            .day-info h4 { margin: 0 0 8px 0; font-size: 14px; color: #666; }
            .day-info .date-big { font-size: 24px; font-weight: 600; color: #667eea; }
            .day-info .day-name { font-size: 14px; color: #888; margin-top: 4px; }
            .day-info .okres-name { font-size: 12px; color: #28a745; margin-top: 8px; }

            /* ========== HARMONOGRAM DNIA ========== */

            /* Modal startu dnia */
            .start-day-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.6);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .start-day-modal {
                background: #fff;
                border-radius: 16px;
                padding: 30px;
                max-width: 400px;
                width: 90%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                animation: slideUp 0.3s ease;
            }
            @keyframes slideUp {
                from { transform: translateY(20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            .start-day-modal h2 {
                margin: 0 0 10px 0;
                font-size: 24px;
                color: #333;
            }
            .start-day-modal p {
                color: #666;
                margin-bottom: 20px;
            }
            .start-day-modal .current-time {
                font-size: 48px;
                font-weight: 700;
                color: #667eea;
                margin: 20px 0;
            }
            .start-day-modal input[type="time"] {
                font-size: 24px;
                padding: 10px 20px;
                border: 2px solid #ddd;
                border-radius: 10px;
                text-align: center;
                width: 150px;
            }
            .start-day-modal input[type="time"]:focus {
                border-color: #667eea;
                outline: none;
            }
            .start-day-modal .btn-start-now {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                border: none;
                padding: 15px 40px;
                border-radius: 10px;
                font-size: 18px;
                font-weight: 600;
                cursor: pointer;
                margin: 20px 10px 10px;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .start-day-modal .btn-start-now:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 20px rgba(102,126,234,0.4);
            }
            .start-day-modal .btn-skip {
                background: transparent;
                border: none;
                color: #888;
                cursor: pointer;
                font-size: 14px;
                padding: 10px;
            }
            .start-day-modal .btn-skip:hover {
                color: #333;
            }

            /* Harmonogram godzinowy */
            .harmonogram-container {
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                padding: 20px;
                margin-bottom: 20px;
            }
            .harmonogram-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #f0f0f0;
            }
            .harmonogram-header h2 {
                margin: 0;
                font-size: 18px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .harmonogram-header .start-time-badge {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                padding: 5px 12px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 500;
            }
            .harmonogram-actions {
                display: flex;
                gap: 10px;
            }
            .btn-change-start {
                background: #f0f0f0;
                border: none;
                padding: 8px 15px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 13px;
            }
            .btn-change-start:hover {
                background: #e0e0e0;
            }
            .btn-toggle-view {
                background: #667eea;
                color: #fff;
                border: none;
                padding: 8px 15px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 13px;
            }
            .btn-toggle-view:hover {
                background: #5a6fd6;
            }

            /* Timeline */
            .harmonogram-timeline {
                position: relative;
                min-height: 600px;
            }
            .timeline-hour {
                display: flex;
                min-height: 60px;
                border-bottom: 1px solid #f0f0f0;
                position: relative;
            }
            .timeline-hour:last-child {
                border-bottom: none;
            }
            .timeline-hour-label {
                width: 60px;
                padding: 8px 10px;
                font-size: 13px;
                font-weight: 600;
                color: #888;
                flex-shrink: 0;
                border-right: 2px solid #f0f0f0;
            }
            .timeline-hour-content {
                flex: 1;
                min-height: 60px;
                position: relative;
                padding: 5px;
            }
            .timeline-hour-content.dragover {
                background: rgba(102,126,234,0.1);
            }
            .timeline-current-time {
                position: absolute;
                left: 60px;
                right: 0;
                height: 2px;
                background: #e53e3e;
                z-index: 10;
            }
            .timeline-current-time::before {
                content: '';
                position: absolute;
                left: -6px;
                top: -4px;
                width: 10px;
                height: 10px;
                background: #e53e3e;
                border-radius: 50%;
            }

            /* Zadania w harmonogramie */
            .harmonogram-task {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-left: 4px solid #667eea;
                border-radius: 8px;
                padding: 10px 12px;
                margin: 3px 0;
                cursor: grab;
                transition: all 0.2s;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .harmonogram-task:hover {
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
                transform: translateY(-1px);
            }
            .harmonogram-task.dragging {
                opacity: 0.5;
                cursor: grabbing;
            }
            .harmonogram-task.is-stale {
                background: #f8f9fa;
                border-style: dashed;
            }
            .harmonogram-task.zapianowany { border-left-color: #28a745; }
            .harmonogram-task.klejpan { border-left-color: #17a2b8; }
            .harmonogram-task.marka_langer { border-left-color: #ffc107; }
            .harmonogram-task.marketing_construction { border-left-color: #dc3545; }
            .harmonogram-task.fjo { border-left-color: #6f42c1; }
            .harmonogram-task.obsluga_telefoniczna { border-left-color: #e91e63; }
            .harmonogram-task.sprawy_organizacyjne { border-left-color: #607d8b; }

            .harmonogram-task-info {
                flex: 1;
            }
            .harmonogram-task-name {
                font-weight: 600;
                color: #333;
                font-size: 14px;
            }
            .harmonogram-task-meta {
                font-size: 12px;
                color: #888;
                margin-top: 3px;
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .harmonogram-task-time {
                display: flex;
                align-items: center;
                gap: 5px;
                font-size: 12px;
                color: #667eea;
                font-weight: 500;
            }
            .harmonogram-task-actions {
                display: flex;
                gap: 5px;
            }
            .harmonogram-task-actions button {
                background: none;
                border: none;
                cursor: pointer;
                padding: 5px;
                border-radius: 4px;
                font-size: 14px;
            }
            .harmonogram-task-actions button:hover {
                background: #f0f0f0;
            }

            /* Edytowalna godzina */
            .harmonogram-task-time-edit {
                display: flex;
                align-items: center;
                gap: 3px;
                margin-right: 10px;
            }
            .time-input-small {
                width: 85px;
                padding: 4px 6px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 12px;
                cursor: pointer;
            }
            .time-input-small:hover {
                border-color: #667eea;
            }
            .time-input-small:focus {
                border-color: #667eea;
                outline: none;
                box-shadow: 0 0 0 2px rgba(102,126,234,0.2);
            }
            .end-time {
                font-size: 12px;
                color: #888;
            }

            /* Nieprzypisane zadania */
            .unscheduled-tasks {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 15px;
                margin-bottom: 20px;
            }
            .unscheduled-tasks h3 {
                margin: 0 0 15px 0;
                font-size: 14px;
                color: #666;
            }
            .unscheduled-tasks-list {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            .unscheduled-task {
                background: #fff;
                border: 1px solid #ddd;
                border-left: 3px solid #667eea;
                border-radius: 6px;
                padding: 8px 12px;
                cursor: grab;
                font-size: 13px;
                transition: all 0.2s;
            }
            .unscheduled-task:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .unscheduled-task.dragging {
                opacity: 0.5;
            }

            /* Stałe zadania badge */
            .stale-badge {
                background: #e9ecef;
                color: #495057;
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 10px;
                font-weight: 600;
            }

            .stale-task-row {
                background: #f8f9fa;
                border-left: 3px solid #6c757d;
            }

            .stale-task-row td {
                color: #6c757d;
            }

            .btn-convert-stale {
                background: #17a2b8;
                color: white;
                border: none;
                padding: 4px 8px;
                border-radius: 4px;
                cursor: pointer;
            }

            .btn-convert-stale:hover {
                background: #138496;
            }

            /* Widok listy vs timeline toggle */
            .view-toggle {
                display: flex;
                gap: 5px;
                background: #f0f0f0;
                padding: 3px;
                border-radius: 8px;
            }
            .view-toggle button {
                background: transparent;
                border: none;
                padding: 6px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 13px;
            }
            .view-toggle button.active {
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            /* Stałe zadania w ustawieniach */
            .stale-zadania-table {
                width: 100%;
                border-collapse: collapse;
            }
            .stale-zadania-table th,
            .stale-zadania-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #f0f0f0;
            }
            .stale-zadania-table th {
                background: #f8f9fa;
                font-size: 12px;
                text-transform: uppercase;
                color: #666;
            }
            .stale-zadania-form {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 20px;
            }
            .stale-zadania-form .form-row {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
                margin-bottom: 15px;
            }
            .dni-tygodnia-checkboxes {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .dni-tygodnia-checkboxes label {
                display: flex;
                align-items: center;
                gap: 5px;
                padding: 5px 10px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 6px;
                cursor: pointer;
                font-size: 13px;
            }
            .dni-tygodnia-checkboxes input:checked + span {
                color: #667eea;
                font-weight: 600;
            }
            .toggle-switch {
                position: relative;
                width: 50px;
                height: 26px;
            }
            .toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: #ccc;
                border-radius: 26px;
                transition: 0.3s;
            }
            .toggle-slider:before {
                content: '';
                position: absolute;
                height: 20px;
                width: 20px;
                left: 3px;
                bottom: 3px;
                background: #fff;
                border-radius: 50%;
                transition: 0.3s;
            }
            .toggle-switch input:checked + .toggle-slider {
                background: #28a745;
            }
            .toggle-switch input:checked + .toggle-slider:before {
                transform: translateX(24px);
            }

            /* ========== TIMER / CZASOMIERZ ========== */
            .timer-btn {
                background: none;
                border: none;
                cursor: pointer;
                font-size: 16px;
                padding: 4px 8px;
                border-radius: 6px;
                transition: all 0.2s;
            }
            .timer-btn:hover {
                background: #f0f0f0;
            }
            .timer-btn.running {
                background: #e8f5e9;
                animation: pulse 1.5s infinite;
            }
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.6; }
            }
            .timer-display {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: 600;
                font-family: 'Courier New', monospace;
            }
            .timer-display.warning {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                animation: pulse 0.5s infinite;
            }
            .timer-display .timer-time {
                min-width: 60px;
                text-align: center;
            }
            .timer-display button {
                background: rgba(255,255,255,0.2);
                border: none;
                color: #fff;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .timer-display button:hover {
                background: rgba(255,255,255,0.3);
            }

            /* Timer w wierszu zadania */
            .task-timer-cell {
                white-space: nowrap;
            }
            .task-timer-cell .timer-cell-content {
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            .time-tracked {
                font-size: 12px;
                color: #28a745;
                font-weight: 600;
            }
            .time-edit-input {
                width: 50px;
                padding: 2px 4px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 12px;
                text-align: center;
            }

            /* Modal timera */
            .timer-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                z-index: 10001;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease;
            }
            .timer-modal {
                background: #fff;
                border-radius: 20px;
                padding: 40px;
                max-width: 450px;
                width: 90%;
                text-align: center;
                box-shadow: 0 25px 80px rgba(0,0,0,0.4);
                animation: slideUp 0.3s ease;
            }
            .timer-modal h2 {
                margin: 0 0 10px 0;
                font-size: 28px;
            }
            .timer-modal .task-name {
                color: #666;
                font-size: 16px;
                margin-bottom: 20px;
            }
            .timer-modal .timer-big {
                font-size: 64px;
                font-weight: 700;
                font-family: 'Courier New', monospace;
                color: #667eea;
                margin: 30px 0;
            }
            .timer-modal .timer-big.overtime {
                color: #e53e3e;
            }
            .timer-modal .time-info {
                font-size: 14px;
                color: #888;
                margin-bottom: 30px;
            }
            .timer-modal-actions {
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }
            .timer-modal-actions button {
                padding: 12px 25px;
                border-radius: 10px;
                border: none;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-timer-done {
                background: #28a745;
                color: #fff;
            }
            .btn-timer-done:hover {
                background: #218838;
                transform: translateY(-2px);
            }
            .btn-timer-extend {
                background: #667eea;
                color: #fff;
            }
            .btn-timer-extend:hover {
                background: #5a6fd6;
                transform: translateY(-2px);
            }
            .btn-timer-stop {
                background: #dc3545;
                color: #fff;
            }
            .btn-timer-stop:hover {
                background: #c82333;
            }
            .btn-timer-cancel {
                background: #f0f0f0;
                color: #333;
            }

            /* Extend options */
            .extend-options {
                display: flex;
                gap: 10px;
                justify-content: center;
                margin-top: 20px;
            }
            .extend-options button {
                padding: 8px 16px;
                border-radius: 8px;
                border: 2px solid #667eea;
                background: #fff;
                color: #667eea;
                font-weight: 600;
                cursor: pointer;
            }
            .extend-options button:hover {
                background: #667eea;
                color: #fff;
            }

            /* Floating timer */
            .floating-timer {
                position: fixed;
                bottom: 30px;
                right: 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                padding: 15px 25px;
                border-radius: 50px;
                box-shadow: 0 10px 40px rgba(102,126,234,0.4);
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 15px;
                cursor: pointer;
                animation: slideUp 0.3s ease;
            }
            .floating-timer:hover {
                transform: translateY(-3px);
                box-shadow: 0 15px 50px rgba(102,126,234,0.5);
            }
            .floating-timer .ft-time {
                font-size: 24px;
                font-weight: 700;
                font-family: 'Courier New', monospace;
            }
            .floating-timer .ft-task {
                font-size: 13px;
                max-width: 200px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .floating-timer .ft-actions {
                display: flex;
                gap: 8px;
            }
            .floating-timer .ft-actions button {
                background: rgba(255,255,255,0.2);
                border: none;
                color: #fff;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 14px;
            }
            .floating-timer .ft-actions button:hover {
                background: rgba(255,255,255,0.3);
            }
            .floating-timer.overtime {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            }
        </style>
        <?php
    }
});

// =============================================
// STRONA GŁÓWNA - DASHBOARD (AJAX VERSION)
// =============================================
function zadaniomat_page_main() {
    global $wpdb;
    $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
    $table_cele_rok = $wpdb->prefix . 'zadaniomat_cele_rok';
    
    $current_okres = zadaniomat_get_current_okres();
    $current_rok = zadaniomat_get_current_rok();
    
    // Cele dla aktualnego okresu
    $cele_okres = [];
    if ($current_okres) {
        $cele_raw = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_cele_okres WHERE okres_id = %d", $current_okres->id));
        foreach ($cele_raw as $c) {
            $cele_okres[$c->kategoria] = ['cel' => $c->cel, 'status' => $c->status, 'id' => $c->id];
        }
    }
    
    // Cele roku
    $cele_rok = [];
    if ($current_rok) {
        $cele_raw = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_cele_rok WHERE rok_id = %d", $current_rok->id));
        foreach ($cele_raw as $c) {
            $cele_rok[$c->kategoria] = $c->cel;
        }
    }
    
    $kategorie_json = json_encode(ZADANIOMAT_KATEGORIE_ZADANIA);
    $today = date('Y-m-d');
    $current_month = date('Y-m');
    
    ?>
    <div class="wrap zadaniomat-wrap">
        <h1 style="margin-bottom: 20px;">📋 Zadaniomat</h1>
        
        <!-- Overdue alerts container -->
        <div id="overdue-container"></div>
        
        <div class="main-layout">
            <!-- SIDEBAR -->
            <div class="sidebar">
                <div class="calendar-wrap">
                    <div class="calendar-header">
                        <h3 id="calendar-title"></h3>
                        <div class="calendar-nav">
                            <button onclick="changeMonth(-1)">←</button>
                            <button onclick="goToToday()">Dziś</button>
                            <button onclick="changeMonth(1)">→</button>
                        </div>
                    </div>
                    <div class="calendar-grid" id="calendar-grid"></div>
                </div>
                
                <div class="day-info">
                    <h4>📅 Wybrany dzień</h4>
                    <div class="date-big" id="selected-date-display"></div>
                    <div class="day-name" id="selected-day-name"></div>
                    <div class="okres-name" id="selected-okres-name"></div>
                </div>
            </div>
            
            <!-- CONTENT -->
            <div class="content">
                <?php if ($current_okres): ?>
                    <div class="okres-banner">
                        <h2>🎯 <?php echo esc_html($current_okres->nazwa); ?></h2>
                        <div class="dates"><?php echo date('d.m', strtotime($current_okres->data_start)); ?> - <?php echo date('d.m.Y', strtotime($current_okres->data_koniec)); ?></div>
                        <?php if ($current_rok): ?>
                            <div style="opacity: 0.8; font-size: 13px; margin-top: 5px;">📅 <?php echo esc_html($current_rok->nazwa); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="zadaniomat-card">
                        <h2>🎯 Cele na ten okres (2 tygodnie)</h2>
                        <div class="cele-grid">
                            <?php foreach (ZADANIOMAT_KATEGORIE as $key => $label):
                                $cel_data = $cele_okres[$key] ?? ['cel' => '', 'status' => null, 'id' => null];
                                $cel_rok = $cele_rok[$key] ?? '';
                            ?>
                                <div class="cel-card <?php echo $key; ?>">
                                    <h4><?php echo $label; ?></h4>
                                    <?php if ($cel_rok): ?>
                                        <div style="font-size: 11px; color: #888; margin-bottom: 6px; font-style: italic;">
                                            Cel roczny: <?php echo esc_html(mb_substr($cel_rok, 0, 50)); ?><?php echo mb_strlen($cel_rok) > 50 ? '...' : ''; ?>
                                        </div>
                                    <?php endif; ?>
                                    <textarea class="cel-okres-input"
                                              data-okres="<?php echo $current_okres->id; ?>"
                                              data-kategoria="<?php echo $key; ?>"
                                              data-cel-id="<?php echo $cel_data['id'] ?: ''; ?>"
                                              placeholder="Cel na 2 tygodnie..."><?php echo esc_textarea($cel_data['cel']); ?></textarea>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-okres-banner">
                        <strong>⚠️ Brak aktywnego okresu</strong><br>
                        Przejdź do <a href="<?php echo admin_url('admin.php?page=zadaniomat-settings'); ?>">Ustawień</a> i dodaj rok oraz okresy 2-tygodniowe.
                    </div>
                <?php endif; ?>

                <!-- Zadania na dziś -->
                <div class="zadaniomat-card" id="today-tasks-section">
                    <h2>📋 Zadania na dziś</h2>
                    <div id="today-tasks-container"></div>
                </div>

                <!-- Harmonogram dnia - pokazuje się tylko dla dzisiaj -->
                <div id="harmonogram-section" style="display: none;">
                    <div class="harmonogram-container">
                        <div class="harmonogram-header">
                            <h2>
                                📅 Harmonogram dnia
                                <span class="start-time-badge" id="start-time-badge">Start: --:--</span>
                            </h2>
                            <div class="harmonogram-actions">
                                <button class="btn-change-start" onclick="showStartDayModal()">⏰ Zmień start</button>
                                <div class="view-toggle">
                                    <button class="active" data-view="timeline" onclick="toggleHarmonogramView('timeline')">📊 Timeline</button>
                                    <button data-view="list" onclick="toggleHarmonogramView('list')">📋 Lista</button>
                                </div>
                            </div>
                        </div>

                        <!-- Nieprzypisane zadania (do przeciągnięcia) -->
                        <div class="unscheduled-tasks" id="unscheduled-tasks">
                            <h3>📦 Zadania do przypisania <span id="unscheduled-count"></span></h3>
                            <div class="unscheduled-tasks-list" id="unscheduled-list"></div>
                        </div>

                        <!-- Timeline godzinowy -->
                        <div class="harmonogram-timeline" id="harmonogram-timeline"></div>
                    </div>
                </div>

                <!-- Inne dni -->
                <div class="zadaniomat-card">
                    <h2>📋 Zadania - inne dni</h2>
                    <div id="tasks-container"></div>
                </div>

                <!-- Formularz zadania -->
                <div class="task-form">
                    <h3 id="form-title">➕ Dodaj zadanie</h3>
                    <form id="task-form">
                        <input type="hidden" id="edit-task-id" value="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>📅 Dzień</label>
                                <input type="date" id="task-date" required value="<?php echo $today; ?>">
                            </div>
                            <div class="form-group">
                                <label>📁 Kategoria</label>
                                <select id="task-kategoria" required>
                                    <?php foreach (ZADANIOMAT_KATEGORIE_ZADANIA as $key => $label): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>⏱️ Planowany czas (min)</label>
                                <input type="number" id="task-czas" min="0" placeholder="np. 30">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group wide">
                                <label>📝 Zadanie</label>
                                <input type="text" id="task-nazwa" required placeholder="Co masz do zrobienia?">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group wide">
                                <label>🎯 Określony cel TO DO</label>
                                <textarea id="task-cel" placeholder="Szczegółowy opis celu..."></textarea>
                            </div>
                        </div>
                        <button type="submit" class="button button-primary button-large" id="submit-btn">➕ Dodaj zadanie</button>
                        <button type="button" class="button button-large" id="cancel-edit-btn" style="display:none;" onclick="cancelEdit()">Anuluj</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal startu dnia -->
    <div id="start-day-modal-container"></div>

    <!-- Timer containers -->
    <div id="timer-modal-container"></div>
    <div id="floating-timer-container"></div>

    <!-- Toast container -->
    <div id="toast-container"></div>
    
    <script>
    (function($) {
        // ==================== CONFIG ====================
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var nonce = '<?php echo wp_create_nonce('zadaniomat_ajax'); ?>';
        var kategorie = <?php echo $kategorie_json; ?>;
        var today = '<?php echo $today; ?>';
        var dayNames = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];
        var monthNames = ['', 'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'];
        
        // ==================== STATE ====================
        var selectedDate = today;
        var currentMonth = '<?php echo $current_month; ?>';
        var daysWithTasks = [];
        var tasksCache = {};
        var copiedTask = null; // Skopiowane zadanie
        
        // ==================== INIT ====================
        $(document).ready(function() {
            renderCalendar();
            loadOverdueTasks();
            loadTasks();
            updateDateInfo();
            bindEvents();
            checkShowHarmonogram();
        });
        
        // ==================== CALENDAR ====================
        window.renderCalendar = function() {
            var monthStart = currentMonth + '-01';
            var monthDate = new Date(monthStart);
            var year = monthDate.getFullYear();
            var month = monthDate.getMonth();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var firstDayWeekday = new Date(year, month, 1).getDay();
            if (firstDayWeekday === 0) firstDayWeekday = 7; // Make Monday = 1
            
            $('#calendar-title').text(monthNames[month + 1] + ' ' + year);
            
            var html = '';
            ['Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'So', 'Nd'].forEach(function(d) {
                html += '<div class="calendar-day-header">' + d + '</div>';
            });
            
            // Empty cells
            for (var i = 1; i < firstDayWeekday; i++) {
                html += '<div class="calendar-day other-month"></div>';
            }
            
            // Days
            for (var day = 1; day <= daysInMonth; day++) {
                var dateStr = currentMonth + '-' + String(day).padStart(2, '0');
                var classes = ['calendar-day'];
                if (dateStr === today) classes.push('today');
                if (dateStr === selectedDate) classes.push('selected');
                if (daysWithTasks.indexOf(dateStr) !== -1) classes.push('has-tasks');
                
                html += '<div class="' + classes.join(' ') + '" data-date="' + dateStr + '">' + day + '</div>';
            }
            
            $('#calendar-grid').html(html);
            
            // Load calendar dots
            loadCalendarDots();
        };
        
        window.loadCalendarDots = function() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_calendar_dots',
                nonce: nonce,
                month: currentMonth
            }, function(response) {
                if (response.success) {
                    daysWithTasks = response.data.days;
                    $('.calendar-day').each(function() {
                        var date = $(this).data('date');
                        if (daysWithTasks.indexOf(date) !== -1) {
                            $(this).addClass('has-tasks');
                        }
                    });
                }
            });
        };
        
        window.changeMonth = function(delta) {
            var parts = currentMonth.split('-');
            var date = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1 + delta, 1);
            currentMonth = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
            renderCalendar();
        };
        
        window.goToToday = function() {
            selectedDate = today;
            currentMonth = today.substring(0, 7);
            renderCalendar();
            loadTasks();
            updateDateInfo();
            $('#task-date').val(today);
        };
        
        window.selectDate = function(date) {
            selectedDate = date;
            $('.calendar-day').removeClass('selected');
            $('.calendar-day[data-date="' + date + '"]').addClass('selected');
            loadTasks();
            updateDateInfo();
            $('#task-date').val(date);
        };
        
        window.updateDateInfo = function() {
            var d = new Date(selectedDate);
            $('#selected-date-display').text(d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear());
            $('#selected-day-name').text(dayNames[d.getDay()]);
            // Okres info could be loaded via AJAX if needed
        };
        
        // ==================== TASKS ====================
        var staleZadaniaForToday = [];

        window.loadTasks = function() {
            var start = addDays(selectedDate, -1);
            var end = addDays(selectedDate, 5);

            $('#tasks-container').addClass('loading');
            $('#today-tasks-container').addClass('loading');

            // Pobierz zadania i stałe zadania dla dziś równolegle
            var tasksRequest = $.post(ajaxurl, {
                action: 'zadaniomat_get_tasks',
                nonce: nonce,
                start: start,
                end: end
            });

            var staleRequest = $.post(ajaxurl, {
                action: 'zadaniomat_get_stale_for_day',
                nonce: nonce,
                dzien: today
            });

            $.when(tasksRequest, staleRequest).done(function(tasksResp, staleResp) {
                $('#tasks-container').removeClass('loading');
                $('#today-tasks-container').removeClass('loading');

                var tasks = tasksResp[0].success ? tasksResp[0].data.tasks : [];

                // Pobierz stałe zadania z opcją "dodaj do listy"
                staleZadaniaForToday = [];
                if (staleResp[0].success) {
                    staleZadaniaForToday = staleResp[0].data.stale_zadania.filter(function(s) {
                        return s.dodaj_do_listy == 1;
                    });
                }

                renderTasks(tasks, start, end);
            });
        };
        
        window.renderTasks = function(tasks, start, end) {
            // Group by day
            var byDay = {};
            tasks.forEach(function(t) {
                if (!byDay[t.dzien]) byDay[t.dzien] = [];
                byDay[t.dzien].push(t);
            });

            var todayHtml = '';
            var otherDaysHtml = '';
            var current = start;
            while (current <= end) {
                var dayTasks = byDay[current] || [];
                var isToday = (current === today);
                var isSelected = (current === selectedDate);
                var html = '';
                
                // Calculate stats
                var planned = 0, actual = 0;
                dayTasks.forEach(function(t) {
                    planned += parseInt(t.planowany_czas) || 0;
                    actual += parseInt(t.faktyczny_czas) || 0;
                });
                
                var d = new Date(current);
                var dayName = ['Nd', 'Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'So'][d.getDay()];
                
                html += '<div class="day-section" data-day="' + current + '">';
                html += '<div class="day-header ' + (isToday ? 'today-header' : '') + '">';
                html += '<div class="day-header-left">';
                html += '<h3>';
                if (isToday) html += '🔵 ';
                if (isSelected) html += '📍 ';
                html += dayName + ', ' + d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear();
                if (isToday) html += ' <span style="font-weight:normal;font-size:12px;">(dziś)</span>';
                html += '</h3>';
                // Bulk actions
                if (dayTasks.length > 0) {
                    html += '<div class="bulk-actions" data-day="' + current + '">';
                    html += '<span class="selected-count"></span>';
                    html += '<button class="btn-bulk-delete" onclick="bulkDeleteTasks(\'' + current + '\')">🗑️ Usuń zaznaczone</button>';
                    html += '<button class="btn-bulk-copy" onclick="bulkCopyTasks(\'' + current + '\')">📄 Kopiuj zaznaczone do:</button>';
                    html += '<input type="date" class="bulk-copy-date" value="' + addDays(current, 1) + '">';
                    html += '</div>';
                }
                html += '</div>';
                html += '<div class="day-header-actions">';
                if (copiedTask) {
                    html += '<button class="btn-paste" onclick="pasteTask(\'' + current + '\')" title="Wklej skopiowane zadanie">📋 Wklej</button>';
                }
                if (dayTasks.length > 0) {
                    html += '<span class="day-stats">' + dayTasks.length + ' zadań • Plan: ' + planned + ' min • Fakt: ' + actual + ' min</span>';
                }
                html += '</div></div>';

                html += '<table class="day-table"><thead><tr>';
                // Zawsze dodaj kolumnę checkbox dla dzisiaj (lub gdy są zadania)
                if (isToday || dayTasks.length > 0) {
                    html += '<th style="width:30px;">';
                    if (dayTasks.length > 0) {
                        html += '<input type="checkbox" class="select-all-checkbox" data-day="' + current + '" title="Zaznacz wszystkie">';
                    }
                    html += '</th>';
                }
                html += '<th style="width:130px;">Kategoria</th><th>Zadanie</th><th style="width:180px;">Cel TO DO</th>';
                html += '<th style="width:50px;">Plan</th><th style="width:70px;">Fakt</th><th style="width:70px;">Status</th><th style="width:90px;">Akcje</th>';
                html += '</tr></thead><tbody>';
                
                // Dla dzisiaj - pokaż sloty dla każdej kategorii
                if (isToday) {
                    var usedKategorie = {};
                    dayTasks.forEach(function(t) { usedKategorie[t.kategoria] = true; });
                    var hiddenCategories = getHiddenCategories(current);

                    // Najpierw istniejące zadania
                    dayTasks.forEach(function(t) {
                        html += renderTaskRow(t, current);
                    });

                    // Potem stałe zadania z opcją "dodaj do listy"
                    staleZadaniaForToday.forEach(function(stale) {
                        // Sprawdź czy nie ma już zadania w tej kategorii (nie duplikuj)
                        if (!usedKategorie[stale.kategoria]) {
                            html += renderStaleTaskRow(stale, current);
                            usedKategorie[stale.kategoria] = true;
                        }
                    });

                    // Potem puste sloty dla brakujących kategorii (bez ukrytych)
                    var hiddenCount = 0;
                    for (var kat in kategorie) {
                        if (!usedKategorie[kat]) {
                            if (hiddenCategories.includes(kat)) {
                                hiddenCount++;
                            } else {
                                html += renderEmptySlot(current, kat, kategorie[kat]);
                            }
                        }
                    }

                    // Pokaż link do przywrócenia ukrytych kategorii
                    if (hiddenCount > 0) {
                        html += '<tr class="hidden-categories-notice"><td colspan="8" style="text-align:center;padding:10px;background:#f9f9f9;font-size:12px;">';
                        html += '<a href="#" onclick="showAllCategories(\'' + current + '\'); return false;">👁️ Pokaż ' + hiddenCount + ' ukryte kategorie</a>';
                        html += '</td></tr>';
                    }
                } else {
                    // Dla innych dni - normalne wyświetlanie
                    if (dayTasks.length === 0) {
                        html += '<tr><td colspan="7" class="empty-day-cell">Brak zadań <a href="#" onclick="selectDate(\'' + current + '\'); return false;">+ Dodaj</a>';
                        if (copiedTask) {
                            html += ' lub <a href="#" onclick="pasteTask(\'' + current + '\'); return false;">📋 Wklej</a>';
                        }
                        html += '</td></tr>';
                    } else {
                        dayTasks.forEach(function(t) {
                            html += renderTaskRow(t, current);
                        });
                    }
                }
                
                html += '</tbody></table></div>';

                // Rozdziel dzisiejsze zadania od innych dni
                if (isToday) {
                    todayHtml += html;
                } else {
                    otherDaysHtml += html;
                }

                current = addDays(current, 1);
            }

            // Renderuj do osobnych kontenerów
            $('#today-tasks-container').html(todayHtml || '<p style="color:#888;padding:20px;text-align:center;">Wybierz dziś w kalendarzu aby zobaczyć zadania</p>');
            $('#tasks-container').html(otherDaysHtml || '<p style="color:#888;padding:20px;text-align:center;">Brak zadań na inne dni</p>');
        };
        
        window.renderTaskRow = function(t, day) {
            var statusClass = '';
            if (t.status !== null) {
                if (parseFloat(t.status) >= 1) statusClass = 'status-done';
                else if (parseFloat(t.status) > 0) statusClass = 'status-partial';
                else statusClass = 'status-none';
            }

            var planowany = parseInt(t.planowany_czas) || 0;
            var faktyczny = parseInt(t.faktyczny_czas) || 0;
            var isActiveTimer = activeTimer && activeTimer.taskId == t.id;

            var html = '<tr class="' + statusClass + '" data-task-id="' + t.id + '">';
            html += '<td><input type="checkbox" class="task-checkbox" data-task-id="' + t.id + '" data-day="' + day + '"></td>';
            html += '<td><span class="kategoria-badge ' + t.kategoria + '">' + t.kategoria_label + '</span></td>';
            html += '<td><strong>' + escapeHtml(t.zadanie) + '</strong></td>';
            html += '<td style="font-size:12px;color:#666;">' + escapeHtml(t.cel_todo || '') + '</td>';

            // Kolumna czasu z timerem
            html += '<td class="task-timer-cell">';
            html += '<div class="timer-cell-content">';
            html += '<span>' + planowany + '</span>';
            if (planowany > 0) {
                html += '<button class="timer-btn' + (isActiveTimer ? ' running' : '') + '" onclick="startTimer(' + t.id + ', \'' + escapeHtml(t.zadanie).replace(/'/g, "\\'") + '\', ' + planowany + ')" title="' + (isActiveTimer ? 'Timer działa' : 'Uruchom timer') + '">';
                html += isActiveTimer ? '⏸️' : '▶️';
                html += '</button>';
            }
            html += '</div></td>';

            // Faktyczny czas z możliwością edycji
            html += '<td class="task-timer-cell">';
            html += '<div class="timer-cell-content">';
            if (faktyczny > 0) {
                html += '<span class="time-tracked" onclick="editFaktycznyCzas(' + t.id + ', ' + faktyczny + ')" style="cursor:pointer;" title="Kliknij aby edytować">' + faktyczny + ' min</span>';
            } else {
                html += '<input type="number" class="inline-input quick-update" data-field="faktyczny_czas" data-id="' + t.id + '" value="" placeholder="-" min="0" style="width:50px;">';
            }
            // Przycisk do dodatkowej sesji timera
            if (planowany > 0 && !isActiveTimer) {
                html += '<button class="timer-btn" onclick="promptAdditionalTimer(' + t.id + ', \'' + escapeHtml(t.zadanie).replace(/'/g, "\\'") + '\', ' + faktyczny + ')" title="Dodaj czas">';
                html += '➕';
                html += '</button>';
            }
            html += '</div></td>';

            html += '<td><select class="inline-select quick-update" data-field="status" data-id="' + t.id + '">';
            html += '<option value=""' + (t.status === null ? ' selected' : '') + '>-</option>';
            ['0', '0.5', '0.9', '1'].forEach(function(s) {
                html += '<option value="' + s + '"' + (t.status == s ? ' selected' : '') + '>' + s + '</option>';
            });
            html += '</select></td>';
            html += '<td class="action-buttons">';
            html += '<button class="btn-copy" onclick="copyTask(' + t.id + ', this)" title="Kopiuj">📄</button>';
            html += '<button class="btn-edit" onclick="editTask(' + t.id + ', this)" title="Edytuj">✏️</button>';
            html += '<button class="btn-delete" onclick="deleteTask(' + t.id + ')" title="Usuń">🗑️</button>';
            html += '</td></tr>';
            return html;
        };

        // Renderuj wiersz stałego zadania (z opcją dodaj do listy)
        window.renderStaleTaskRow = function(stale, day) {
            var planowany = parseInt(stale.planowany_czas) || 0;

            var html = '<tr class="stale-task-row" data-stale-id="' + stale.id + '">';
            html += '<td></td>'; // Pusta komórka checkbox (stałe nie mają checkboxa)
            html += '<td><span class="kategoria-badge ' + stale.kategoria + '">' + stale.kategoria_label + '</span></td>';
            html += '<td><strong>' + escapeHtml(stale.nazwa) + '</strong> <span class="stale-badge">🔄 Stałe</span></td>';
            html += '<td style="font-size:12px;color:#666;">-</td>'; // Brak celu TODO dla stałych
            html += '<td>' + planowany + '</td>';
            html += '<td>-</td>'; // Brak faktycznego czasu
            html += '<td>-</td>'; // Brak statusu
            html += '<td class="action-buttons">';
            html += '<button class="btn-convert-stale" onclick="convertStaleToTask(' + stale.id + ', \'' + day + '\')" title="Przekształć w zadanie">📋</button>';
            html += '</td></tr>';
            return html;
        };

        // Przekształć stałe zadanie w zwykłe zadanie
        window.convertStaleToTask = function(staleId, day) {
            var stale = staleZadaniaForToday.find(function(s) { return s.id == staleId; });
            if (!stale) return;

            $.post(ajaxurl, {
                action: 'zadaniomat_add_task',
                nonce: nonce,
                dzien: day,
                kategoria: stale.kategoria,
                zadanie: stale.nazwa,
                cel_todo: '',
                planowany_czas: stale.planowany_czas || 0
            }, function(response) {
                if (response.success) {
                    showToast('Zadanie utworzone ze stałego!', 'success');
                    loadTasks();
                    loadCalendarDots();
                }
            });
        };

        // Prompt do dodatkowej sesji timera
        window.promptAdditionalTimer = function(taskId, taskName, currentMinutes) {
            var minutes = prompt('Na ile minut uruchomić timer?\n(Aktualny zapisany czas: ' + currentMinutes + ' min)', '15');
            if (minutes === null) return;

            var mins = parseInt(minutes);
            if (isNaN(mins) || mins <= 0) {
                alert('Podaj prawidłową liczbę minut');
                return;
            }

            // Pobierz aktualny faktyczny czas i ustaw jako elapsedBefore
            activeTimer = {
                taskId: taskId,
                taskName: taskName,
                plannedTime: mins * 60,
                startTime: Date.now(),
                elapsedBefore: currentMinutes * 60, // Poprzedni czas
                interval: null,
                notified: false
            };

            initTimerAudio();
            requestNotificationPermission();

            activeTimer.interval = setInterval(updateTimerDisplay, 1000);
            updateTimerDisplay();
            renderFloatingTimer();

            showToast('⏱️ Timer uruchomiony (+' + mins + ' min)', 'success');
        };
        
        // Pobierz ukryte kategorie dla danego dnia
        window.getHiddenCategories = function(day) {
            var key = 'zadaniomat_hidden_categories_' + day;
            var hidden = localStorage.getItem(key);
            return hidden ? JSON.parse(hidden) : [];
        };

        // Ukryj kategorię dla danego dnia
        window.hideEmptySlot = function(day, kategoria) {
            var key = 'zadaniomat_hidden_categories_' + day;
            var hidden = getHiddenCategories(day);
            if (!hidden.includes(kategoria)) {
                hidden.push(kategoria);
                localStorage.setItem(key, JSON.stringify(hidden));
            }
            loadTasks();
            showToast('Kategoria ukryta na dziś', 'success');
        };

        // Przywróć wszystkie ukryte kategorie dla dnia
        window.showAllCategories = function(day) {
            var key = 'zadaniomat_hidden_categories_' + day;
            localStorage.removeItem(key);
            loadTasks();
            showToast('Przywrócono wszystkie kategorie', 'success');
        };

        window.renderEmptySlot = function(day, kategoria, kategoriaLabel) {
            var html = '<tr class="empty-slot" data-day="' + day + '" data-kategoria="' + kategoria + '">';
            html += '<td></td>'; // Pusta komórka dla checkboxa
            html += '<td><span class="kategoria-badge ' + kategoria + '">' + kategoriaLabel + '</span></td>';
            html += '<td><input type="text" class="slot-input slot-zadanie" placeholder="Wpisz zadanie..." data-field="zadanie"></td>';
            html += '<td><input type="text" class="slot-input slot-cel" placeholder="Cel TO DO..." data-field="cel_todo"></td>';
            html += '<td><input type="number" class="slot-input slot-czas" placeholder="-" min="0" style="width:45px;" data-field="planowany_czas"></td>';
            html += '<td>-</td>';
            html += '<td>-</td>';
            html += '<td class="action-buttons">';
            html += '<button class="btn-add-slot" onclick="saveSlot(this)" title="Dodaj">➕</button>';
            html += '<button class="btn-hide-slot" onclick="hideEmptySlot(\'' + day + '\', \'' + kategoria + '\')" title="Ukryj na dziś">👁️‍🗨️</button>';
            html += '</td>';
            html += '</tr>';
            return html;
        };
        
        // Zapisz zadanie z empty slotu
        window.saveSlot = function(btn) {
            var $row = $(btn).closest('tr');
            var day = $row.data('day');
            var kategoria = $row.data('kategoria');
            var zadanie = $row.find('.slot-zadanie').val().trim();
            var cel = $row.find('.slot-cel').val().trim();
            var czas = $row.find('.slot-czas').val() || 0;
            
            if (!zadanie) {
                showToast('Wpisz nazwę zadania!', 'error');
                $row.find('.slot-zadanie').focus();
                return;
            }
            
            $.post(ajaxurl, {
                action: 'zadaniomat_add_task',
                nonce: nonce,
                dzien: day,
                kategoria: kategoria,
                zadanie: zadanie,
                cel_todo: cel,
                planowany_czas: czas
            }, function(response) {
                if (response.success) {
                    showToast('Zadanie dodane!', 'success');
                    loadTasks();
                    loadCalendarDots();
                }
            });
        };
        
        // Kopiuj zadanie
        window.copyTask = function(id, btn) {
            var $row = $(btn).closest('tr');
            var $daySection = $row.closest('.day-section');
            
            copiedTask = {
                kategoria: $row.find('.kategoria-badge').attr('class').replace('kategoria-badge ', '').trim(),
                zadanie: $row.find('strong').text(),
                cel_todo: $row.find('td:eq(2)').text(),
                planowany_czas: $row.find('td:eq(3)').text()
            };
            
            showToast('Zadanie skopiowane! Wybierz dzień i kliknij "Wklej"', 'success');
            
            // Odśwież żeby pokazać przyciski wklejania
            loadTasks();
        };
        
        // Wklej zadanie
        window.pasteTask = function(day) {
            if (!copiedTask) {
                showToast('Najpierw skopiuj jakieś zadanie!', 'error');
                return;
            }
            
            $.post(ajaxurl, {
                action: 'zadaniomat_add_task',
                nonce: nonce,
                dzien: day,
                kategoria: copiedTask.kategoria,
                zadanie: copiedTask.zadanie,
                cel_todo: copiedTask.cel_todo,
                planowany_czas: copiedTask.planowany_czas
            }, function(response) {
                if (response.success) {
                    showToast('Zadanie wklejone!', 'success');
                    loadTasks();
                    loadCalendarDots();
                }
            });
        };
        
        // ==================== OVERDUE ====================
        window.loadOverdueTasks = function() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_overdue',
                nonce: nonce
            }, function(response) {
                if (response.success && response.data.tasks.length > 0) {
                    renderOverdueTasks(response.data.tasks);
                } else {
                    $('#overdue-container').html('');
                }
            });
        };
        
        window.renderOverdueTasks = function(tasks) {
            var html = '<div class="overdue-alert">';
            html += '<h3>⚠️ Masz ' + tasks.length + ' nieukończonych zadań z przeszłości!</h3>';
            
            tasks.forEach(function(t) {
                var d = new Date(t.dzien);
                html += '<div class="overdue-task" data-task-id="' + t.id + '">';
                html += '<div class="overdue-task-info">';
                html += '<div class="task-name">' + escapeHtml(t.zadanie) + '</div>';
                html += '<div class="task-meta">📅 ' + d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear() + ' • ';
                html += '<span class="kategoria-badge ' + t.kategoria + '">' + t.kategoria_label + '</span>';
                if (t.status !== null) html += ' • Status: ' + t.status;
                html += '</div></div>';
                html += '<div class="overdue-task-actions">';
                html += '<span style="font-size:12px;color:#666;">Przenieś na:</span>';
                html += '<input type="date" class="move-date" value="' + today + '" min="' + today + '">';
                html += '<button class="btn-move" onclick="moveOverdueTask(' + t.id + ', this)">📅 Przenieś</button>';
                html += '<span style="font-size:12px;color:#666;margin-left:10px;">lub status:</span>';
                html += '<select class="overdue-status-select" onchange="updateOverdueStatus(' + t.id + ', this.value)">';
                html += '<option value="">-</option>';
                ['0', '0.5', '0.8', '0.9', '1'].forEach(function(s) {
                    html += '<option value="' + s + '"' + (t.status == s ? ' selected' : '') + '>' + s + (s == '1' ? ' ✓' : '') + '</option>';
                });
                html += '</select></div></div>';
            });
            
            html += '</div>';
            $('#overdue-container').html(html);
        };
        
        window.moveOverdueTask = function(id, btn) {
            var $container = $(btn).closest('.overdue-task');
            var newDate = $container.find('.move-date').val();
            
            if (!newDate) { showToast('Wybierz datę!', 'error'); return; }
            
            $.post(ajaxurl, {
                action: 'zadaniomat_move_task',
                nonce: nonce,
                id: id,
                new_date: newDate
            }, function(response) {
                if (response.success) {
                    $container.slideUp(300, function() {
                        $(this).remove();
                        if ($('.overdue-task').length === 0) $('.overdue-alert').slideUp();
                    });
                    showToast('Zadanie przeniesione!', 'success');
                    loadTasks();
                    loadCalendarDots();
                }
            });
        };
        
        window.updateOverdueStatus = function(id, status) {
            if (status === '') return;
            
            $.post(ajaxurl, {
                action: 'zadaniomat_quick_update',
                nonce: nonce,
                id: id,
                field: 'status',
                value: status
            }, function(response) {
                if (response.success) {
                    if (parseFloat(status) >= 0.8) {
                        var $container = $('[data-task-id="' + id + '"].overdue-task');
                        $container.slideUp(300, function() {
                            $(this).remove();
                            if ($('.overdue-task').length === 0) $('.overdue-alert').slideUp();
                        });
                    }
                    showToast('Status zaktualizowany!', 'success');
                    loadTasks();
                }
            });
        };
        
        // ==================== FORM HANDLING ====================
        window.bindEvents = function() {
            // Calendar click
            $(document).on('click', '.calendar-day:not(.other-month)', function() {
                var date = $(this).data('date');
                if (date) selectDate(date);
            });

            // Checkbox pojedynczego zadania
            $(document).on('change', '.task-checkbox', function() {
                var day = $(this).data('day');
                updateBulkActionsVisibility(day);
            });

            // Checkbox "zaznacz wszystkie"
            $(document).on('change', '.select-all-checkbox', function() {
                var day = $(this).data('day');
                var isChecked = $(this).prop('checked');
                var $daySection = $('.day-section[data-day="' + day + '"]');
                $daySection.find('.task-checkbox').prop('checked', isChecked);
                updateBulkActionsVisibility(day);
            });

            // Enter w slotach - zapisuje lub przechodzi do następnego pola
            $(document).on('keydown', '.slot-input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var $this = $(this);
                    var $row = $this.closest('tr');
                    var $next = $this.closest('td').next().find('.slot-input');
                    
                    if ($next.length) {
                        $next.focus();
                    } else {
                        // Ostatnie pole - zapisz
                        saveSlot($row.find('.btn-add-slot')[0]);
                    }
                }
            });
            
            // Quick update
            $(document).on('change', '.quick-update', function() {
                var $this = $(this);
                var $row = $this.closest('tr');
                
                $.post(ajaxurl, {
                    action: 'zadaniomat_quick_update',
                    nonce: nonce,
                    id: $this.data('id'),
                    field: $this.data('field'),
                    value: $this.val()
                }, function(response) {
                    if (response.success) {
                        $this.addClass('saved-flash');
                        setTimeout(function() { $this.removeClass('saved-flash'); }, 500);
                        
                        if ($this.data('field') === 'status') {
                            $row.removeClass('status-done status-partial status-none');
                            var val = parseFloat($this.val());
                            if (val >= 1) $row.addClass('status-done');
                            else if (val > 0) $row.addClass('status-partial');
                            else if (!isNaN(val)) $row.addClass('status-none');
                        }
                    }
                });
            });
            
            // Form submit
            $('#task-form').on('submit', function(e) {
                e.preventDefault();
                
                var editId = $('#edit-task-id').val();
                var action = editId ? 'zadaniomat_edit_task' : 'zadaniomat_add_task';
                
                var data = {
                    action: action,
                    nonce: nonce,
                    dzien: $('#task-date').val(),
                    kategoria: $('#task-kategoria').val(),
                    zadanie: $('#task-nazwa').val(),
                    cel_todo: $('#task-cel').val(),
                    planowany_czas: $('#task-czas').val() || 0
                };
                
                if (editId) data.id = editId;
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        showToast(editId ? 'Zadanie zaktualizowane!' : 'Zadanie dodane!', 'success');
                        resetForm();
                        loadTasks();
                        loadCalendarDots();
                        loadOverdueTasks();
                    }
                });
            });
            
            // Cele okresu
            $(document).on('change', '.cel-okres-input', function() {
                var $this = $(this);
                $.post(ajaxurl, {
                    action: 'zadaniomat_save_cel_okres',
                    nonce: nonce,
                    okres_id: $this.data('okres'),
                    kategoria: $this.data('kategoria'),
                    cel: $this.val()
                }, function(response) {
                    if (response.success) {
                        $this.addClass('saved-flash');
                        setTimeout(function() { $this.removeClass('saved-flash'); }, 500);
                        if (response.data.cel_id) {
                            $this.data('cel-id', response.data.cel_id);
                            var $statusRow = $this.siblings('.status-row');
                            $statusRow.show().find('select').data('id', response.data.cel_id);
                        }
                    }
                });
            });
            
            // Status celu okresu
            $(document).on('change', '.cel-okres-status', function() {
                var $this = $(this);
                $.post(ajaxurl, {
                    action: 'zadaniomat_update_cel_okres_status',
                    nonce: nonce,
                    id: $this.data('id'),
                    status: $this.val()
                }, function(response) {
                    if (response.success) {
                        $this.addClass('saved-flash');
                        setTimeout(function() { $this.removeClass('saved-flash'); }, 500);
                    }
                });
            });
        };
        
        // ==================== EDIT / DELETE ====================
        window.editTask = function(id, btn) {
            var $row = $(btn).closest('tr');
            var $daySection = $row.closest('.day-section');
            
            // Get task data from row
            var kategoria = $row.find('.kategoria-badge').attr('class').replace('kategoria-badge ', '').trim();
            var zadanie = $row.find('strong').text();
            var cel = $row.find('td:eq(2)').text();
            var plan = $row.find('td:eq(3)').text();
            var dzien = $daySection.data('day');
            
            $('#edit-task-id').val(id);
            $('#task-date').val(dzien);
            $('#task-kategoria').val(kategoria);
            $('#task-nazwa').val(zadanie);
            $('#task-cel').val(cel);
            $('#task-czas').val(plan);
            
            $('#form-title').text('✏️ Edytuj zadanie');
            $('#submit-btn').text('💾 Zapisz zmiany');
            $('#cancel-edit-btn').show();
            
            $('html, body').animate({ scrollTop: $('.task-form').offset().top - 50 }, 300);
        };
        
        window.cancelEdit = function() {
            resetForm();
        };
        
        window.resetForm = function() {
            $('#edit-task-id').val('');
            $('#task-nazwa').val('');
            $('#task-cel').val('');
            $('#task-czas').val('');
            $('#form-title').text('➕ Dodaj zadanie');
            $('#submit-btn').text('➕ Dodaj zadanie');
            $('#cancel-edit-btn').hide();
        };
        
        window.deleteTask = function(id) {
            if (!confirm('Na pewno usunąć to zadanie?')) return;

            var $row = $('tr[data-task-id="' + id + '"]');
            var $daySection = $row.closest('.day-section');

            $.post(ajaxurl, {
                action: 'zadaniomat_delete_task',
                nonce: nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    showToast('Zadanie usunięte!', 'success');
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        // Przelicz statystyki dnia po usunięciu wiersza
                        recalculateDayStats($daySection);
                    });
                    loadCalendarDots();
                }
            });
        };

        // Funkcja do przeliczania statystyk dnia
        window.recalculateDayStats = function($daySection) {
            var $rows = $daySection.find('tr[data-task-id]');
            var taskCount = $rows.length;
            var planned = 0, actual = 0;

            $rows.each(function() {
                var $row = $(this);
                // Planowany czas jest w 5. kolumnie (index 4) - po dodaniu kolumny checkbox
                var planText = $row.find('td:eq(4)').text();
                planned += parseInt(planText) || 0;
                // Faktyczny czas jest w input w 6. kolumnie
                var actualVal = $row.find('input[data-field="faktyczny_czas"]').val();
                actual += parseInt(actualVal) || 0;
            });

            var $stats = $daySection.find('.day-stats');
            if (taskCount > 0) {
                $stats.text(taskCount + ' zadań • Plan: ' + planned + ' min • Fakt: ' + actual + ' min');
            } else {
                $stats.text('');
            }
        };

        // ==================== BULK ACTIONS ====================
        // Aktualizuj widoczność przycisków akcji zbiorowych
        window.updateBulkActionsVisibility = function(day) {
            var $daySection = $('.day-section[data-day="' + day + '"]');
            var $checkboxes = $daySection.find('.task-checkbox:checked');
            var $bulkActions = $daySection.find('.bulk-actions');
            var $selectedCount = $bulkActions.find('.selected-count');

            if ($checkboxes.length > 0) {
                $bulkActions.addClass('visible');
                $selectedCount.text($checkboxes.length + ' zaznaczonych');
            } else {
                $bulkActions.removeClass('visible');
                $selectedCount.text('');
            }
        };

        // Zbiorowe usuwanie zadań
        window.bulkDeleteTasks = function(day) {
            var $daySection = $('.day-section[data-day="' + day + '"]');
            var $checkboxes = $daySection.find('.task-checkbox:checked');
            var ids = [];

            $checkboxes.each(function() {
                ids.push($(this).data('task-id'));
            });

            if (ids.length === 0) {
                showToast('Zaznacz najpierw zadania do usunięcia!', 'error');
                return;
            }

            if (!confirm('Na pewno usunąć ' + ids.length + ' zadań?')) return;

            $.post(ajaxurl, {
                action: 'zadaniomat_bulk_delete',
                nonce: nonce,
                ids: ids
            }, function(response) {
                if (response.success) {
                    showToast('Usunięto ' + response.data.deleted + ' zadań!', 'success');
                    loadTasks();
                    loadCalendarDots();
                }
            });
        };

        // Zbiorowe kopiowanie zadań
        window.bulkCopyTasks = function(day) {
            var $daySection = $('.day-section[data-day="' + day + '"]');
            var $checkboxes = $daySection.find('.task-checkbox:checked');
            var targetDate = $daySection.find('.bulk-copy-date').val();
            var ids = [];

            $checkboxes.each(function() {
                ids.push($(this).data('task-id'));
            });

            if (ids.length === 0) {
                showToast('Zaznacz najpierw zadania do skopiowania!', 'error');
                return;
            }

            if (!targetDate) {
                showToast('Wybierz datę docelową!', 'error');
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_bulk_copy',
                nonce: nonce,
                ids: ids,
                target_date: targetDate
            }, function(response) {
                if (response.success) {
                    showToast('Skopiowano ' + response.data.copied + ' zadań na ' + targetDate + '!', 'success');
                    loadTasks();
                    loadCalendarDots();
                }
            });
        };

        // ==================== HELPERS ====================
        window.addDays = function(dateStr, days) {
            var d = new Date(dateStr);
            d.setDate(d.getDate() + days);
            return d.toISOString().split('T')[0];
        };
        
        window.escapeHtml = function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        window.showToast = function(message, type) {
            var toast = $('<div class="toast ' + type + '">' + message + '</div>');
            $('#toast-container').append(toast);
            setTimeout(function() { toast.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        };

        // ==================== HARMONOGRAM DNIA ====================
        var startDnia = null;
        var harmonogramView = 'timeline';
        var harmonogramTasks = [];
        var harmonogramStale = [];
        var draggedTask = null;

        // Sprawdź czy pokazać harmonogram (tylko dla dzisiaj)
        window.checkShowHarmonogram = function() {
            if (selectedDate === today) {
                $('#harmonogram-section').show();
                checkStartDnia();
            } else {
                $('#harmonogram-section').hide();
            }
        };

        // Sprawdź czy użytkownik ustawił godzinę startu
        window.checkStartDnia = function() {
            // Sprawdź localStorage najpierw
            var savedStart = localStorage.getItem('zadaniomat_start_' + today);
            if (savedStart) {
                startDnia = savedStart;
                updateStartBadge();
                loadHarmonogram();
                return;
            }

            // Sprawdź w bazie
            $.post(ajaxurl, {
                action: 'zadaniomat_get_start_dnia',
                nonce: nonce,
                dzien: today
            }, function(response) {
                if (response.success && response.data.godzina) {
                    startDnia = response.data.godzina;
                    localStorage.setItem('zadaniomat_start_' + today, startDnia);
                    updateStartBadge();
                    loadHarmonogram();
                } else {
                    // Pokaż modal startu dnia
                    showStartDayModal();
                }
            });
        };

        // Modal startu dnia
        window.showStartDayModal = function() {
            var now = new Date();
            var currentTime = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');

            var html = '<div class="start-day-modal-overlay" onclick="closeStartDayModal(event)">';
            html += '<div class="start-day-modal" onclick="event.stopPropagation()">';
            html += '<h2>☀️ Dzień dobry!</h2>';
            html += '<p>O której godzinie zaczynasz dzisiaj pracę?</p>';
            html += '<div class="current-time">' + currentTime + '</div>';
            html += '<div style="margin: 20px 0;">';
            html += '<input type="time" id="start-time-input" value="' + currentTime + '">';
            html += '</div>';
            html += '<button class="btn-start-now" onclick="setStartDnia()">🚀 Zaczynam!</button>';
            html += '<br><button class="btn-skip" onclick="skipStartDnia()">Pomiń na dziś</button>';
            html += '</div></div>';

            $('#start-day-modal-container').html(html);
        };

        window.closeStartDayModal = function(event) {
            if (event && event.target !== event.currentTarget) return;
            $('#start-day-modal-container').html('');
        };

        window.setStartDnia = function() {
            startDnia = $('#start-time-input').val();

            // Zapisz w localStorage
            localStorage.setItem('zadaniomat_start_' + today, startDnia);

            // Zapisz w bazie
            $.post(ajaxurl, {
                action: 'zadaniomat_save_start_dnia',
                nonce: nonce,
                dzien: today,
                godzina: startDnia
            });

            closeStartDayModal();
            updateStartBadge();
            loadHarmonogram();
            showToast('Dzień rozpoczęty o ' + startDnia + '!', 'success');
        };

        window.skipStartDnia = function() {
            startDnia = null;
            localStorage.setItem('zadaniomat_start_' + today, 'skipped');
            closeStartDayModal();
            $('#harmonogram-section').hide();
        };

        window.updateStartBadge = function() {
            if (startDnia && startDnia !== 'skipped') {
                $('#start-time-badge').text('Start: ' + startDnia);
            }
        };

        // Załaduj harmonogram
        window.loadHarmonogram = function() {
            if (!startDnia || startDnia === 'skipped') return;

            $.post(ajaxurl, {
                action: 'zadaniomat_get_harmonogram',
                nonce: nonce,
                dzien: today
            }, function(response) {
                if (response.success) {
                    harmonogramTasks = response.data.zadania;
                    harmonogramStale = response.data.stale_zadania;
                    loadStaleModifications(); // Zastosuj dzisiejsze modyfikacje
                    renderHarmonogram();
                }
            });
        };

        // Renderuj harmonogram
        window.renderHarmonogram = function() {
            if (harmonogramView === 'timeline') {
                renderTimelineView();
            } else {
                renderListView();
            }
            renderUnscheduledTasks();
            updateCurrentTimeLine();
        };

        // Renderuj widok timeline
        window.renderTimelineView = function() {
            var startHour = parseInt(startDnia.split(':')[0]);
            var endHour = 20; // Domyślny koniec dnia

            var html = '';

            // Stwórz godziny
            for (var h = startHour; h <= endHour; h++) {
                var hourStr = String(h).padStart(2, '0') + ':00';
                var tasksInHour = getTasksForHour(h);

                html += '<div class="timeline-hour" data-hour="' + h + '">';
                html += '<div class="timeline-hour-label">' + hourStr + '</div>';
                html += '<div class="timeline-hour-content" ondragover="handleDragOver(event)" ondrop="handleDrop(event, ' + h + ')" ondragleave="handleDragLeave(event)">';

                // Renderuj zadania w tej godzinie
                tasksInHour.forEach(function(task) {
                    html += renderHarmonogramTask(task);
                });

                // Renderuj stałe zadania w tej godzinie
                harmonogramStale.forEach(function(stale) {
                    if (stale.godzina_start) {
                        var staleHour = parseInt(stale.godzina_start.split(':')[0]);
                        if (staleHour === h) {
                            html += renderHarmonogramTask(stale, true);
                        }
                    }
                });

                html += '</div></div>';
            }

            $('#harmonogram-timeline').html(html);
        };

        // Pobierz zadania dla godziny
        window.getTasksForHour = function(hour) {
            return harmonogramTasks.filter(function(task) {
                if (task.godzina_start) {
                    var taskHour = parseInt(task.godzina_start.split(':')[0]);
                    return taskHour === hour;
                }
                return false;
            });
        };

        // Renderuj pojedyncze zadanie w harmonogramie
        window.renderHarmonogramTask = function(task, isStale) {
            var staleClass = isStale ? ' is-stale' : '';
            var taskId = isStale ? 'stale-' + task.id : task.id;
            var draggable = 'draggable="true" ondragstart="handleDragStart(event, \'' + taskId + '\')"';

            var html = '<div class="harmonogram-task ' + task.kategoria + staleClass + '" data-id="' + taskId + '" data-is-stale="' + (isStale ? '1' : '0') + '" ' + draggable + '>';
            html += '<div class="harmonogram-task-info">';
            html += '<div class="harmonogram-task-name">' + escapeHtml(task.zadanie || task.nazwa) + '</div>';
            html += '<div class="harmonogram-task-meta">';
            html += '<span class="kategoria-badge ' + task.kategoria + '">' + task.kategoria_label + '</span>';
            if (task.planowany_czas) {
                html += '<span>⏱️ ' + task.planowany_czas + ' min</span>';
            }
            if (isStale) {
                html += '<span class="stale-badge">🔄 Stałe</span>';
            }
            html += '</div></div>';

            // Edytowalna godzina
            if (task.godzina_start) {
                var startTime = task.godzina_start.substring(0, 5);
                var endTime = task.godzina_koniec ? task.godzina_koniec.substring(0, 5) : '';

                html += '<div class="harmonogram-task-time-edit">';
                html += '<input type="time" class="time-input-small" value="' + startTime + '" onchange="updateTaskTime(\'' + taskId + '\', this.value, \'' + (isStale ? '1' : '0') + '\')" title="Zmień godzinę rozpoczęcia">';
                if (endTime) {
                    html += '<span style="margin: 0 3px;">-</span>';
                    html += '<span class="end-time">' + endTime + '</span>';
                }
                html += '</div>';
            }

            // Akcje dla wszystkich zadań (nie tylko zwykłych)
            html += '<div class="harmonogram-task-actions">';
            if (isStale) {
                html += '<button onclick="hideStaleForToday(\'' + task.id + '\')" title="Ukryj dzisiaj">👁️‍🗨️</button>';
            } else {
                html += '<button onclick="removeFromHarmonogram(' + task.id + ')" title="Usuń z harmonogramu">❌</button>';
            }
            html += '</div>';

            html += '</div>';
            return html;
        };

        // Aktualizuj godzinę zadania
        window.updateTaskTime = function(taskId, newTime, isStale) {
            if (isStale === '1') {
                // Dla stałych - zapisz w localStorage na dzisiaj
                var staleId = taskId.replace('stale-', '');
                var staleTask = harmonogramStale.find(function(s) { return s.id == staleId; });
                if (staleTask) {
                    staleTask.godzina_start = newTime + ':00';
                    // Przelicz godzinę końcową
                    if (staleTask.planowany_czas) {
                        var parts = newTime.split(':');
                        var endMinutes = parseInt(parts[0]) * 60 + parseInt(parts[1]) + parseInt(staleTask.planowany_czas);
                        var endHour = Math.floor(endMinutes / 60);
                        var endMin = endMinutes % 60;
                        staleTask.godzina_koniec = String(endHour).padStart(2, '0') + ':' + String(endMin).padStart(2, '0') + ':00';
                    }
                    saveStaleModifications();
                    renderHarmonogram();
                    showToast('Godzina stałego zadania zmieniona na dziś', 'success');
                }
            } else {
                // Dla zwykłych zadań - zapisz w bazie
                var task = harmonogramTasks.find(function(t) { return t.id == taskId; });
                if (task) {
                    var godzinaKoniec = null;
                    if (task.planowany_czas) {
                        var parts = newTime.split(':');
                        var endMinutes = parseInt(parts[0]) * 60 + parseInt(parts[1]) + parseInt(task.planowany_czas);
                        var endHour = Math.floor(endMinutes / 60);
                        var endMin = endMinutes % 60;
                        godzinaKoniec = String(endHour).padStart(2, '0') + ':' + String(endMin).padStart(2, '0');
                    }

                    $.post(ajaxurl, {
                        action: 'zadaniomat_update_harmonogram',
                        nonce: nonce,
                        id: taskId,
                        godzina_start: newTime,
                        godzina_koniec: godzinaKoniec
                    }, function(response) {
                        if (response.success) {
                            task.godzina_start = newTime + ':00';
                            task.godzina_koniec = godzinaKoniec ? godzinaKoniec + ':00' : null;
                            renderHarmonogram();
                            showToast('Godzina zmieniona', 'success');
                        }
                    });
                }
            }
        };

        // Ukryj stałe zadanie na dzisiaj
        window.hideStaleForToday = function(staleId) {
            var hidden = JSON.parse(localStorage.getItem('zadaniomat_hidden_stale_' + today) || '[]');
            if (hidden.indexOf(staleId) === -1) {
                hidden.push(staleId);
                localStorage.setItem('zadaniomat_hidden_stale_' + today, JSON.stringify(hidden));
            }
            harmonogramStale = harmonogramStale.filter(function(s) { return s.id != staleId; });
            renderHarmonogram();
            showToast('Stałe zadanie ukryte na dzisiaj', 'success');
        };

        // Zapisz modyfikacje stałych na dzisiaj
        window.saveStaleModifications = function() {
            var mods = {};
            harmonogramStale.forEach(function(s) {
                mods[s.id] = {
                    godzina_start: s.godzina_start,
                    godzina_koniec: s.godzina_koniec
                };
            });
            localStorage.setItem('zadaniomat_stale_mods_' + today, JSON.stringify(mods));
        };

        // Załaduj modyfikacje stałych na dzisiaj
        window.loadStaleModifications = function() {
            var hidden = JSON.parse(localStorage.getItem('zadaniomat_hidden_stale_' + today) || '[]');
            var mods = JSON.parse(localStorage.getItem('zadaniomat_stale_mods_' + today) || '{}');

            // Filtruj ukryte
            harmonogramStale = harmonogramStale.filter(function(s) {
                return hidden.indexOf(String(s.id)) === -1;
            });

            // Zastosuj modyfikacje godzin
            harmonogramStale.forEach(function(s) {
                if (mods[s.id]) {
                    s.godzina_start = mods[s.id].godzina_start;
                    s.godzina_koniec = mods[s.id].godzina_koniec;
                }
            });
        };

        // Renderuj nieprzypisane zadania
        window.renderUnscheduledTasks = function() {
            var unscheduled = harmonogramTasks.filter(function(task) {
                return !task.godzina_start;
            });

            if (unscheduled.length === 0) {
                $('#unscheduled-tasks').hide();
                return;
            }

            $('#unscheduled-tasks').show();
            $('#unscheduled-count').text('(' + unscheduled.length + ')');

            var html = '';
            unscheduled.forEach(function(task) {
                html += '<div class="unscheduled-task ' + task.kategoria + '" draggable="true" ondragstart="handleDragStart(event, ' + task.id + ')" data-id="' + task.id + '">';
                html += '<strong>' + escapeHtml(task.zadanie) + '</strong>';
                if (task.planowany_czas) {
                    html += ' <span style="color:#888;">(' + task.planowany_czas + ' min)</span>';
                }
                html += '</div>';
            });

            $('#unscheduled-list').html(html);
        };

        // Drag & Drop
        window.handleDragStart = function(event, taskId) {
            draggedTask = taskId;
            event.target.classList.add('dragging');
            event.dataTransfer.setData('text/plain', taskId);
        };

        window.handleDragOver = function(event) {
            event.preventDefault();
            event.currentTarget.classList.add('dragover');
        };

        window.handleDragLeave = function(event) {
            event.currentTarget.classList.remove('dragover');
        };

        window.handleDrop = function(event, hour) {
            event.preventDefault();
            event.currentTarget.classList.remove('dragover');

            if (!draggedTask) return;

            var godzina = String(hour).padStart(2, '0') + ':00';
            var isStale = String(draggedTask).indexOf('stale-') === 0;

            if (isStale) {
                // Stałe zadanie - zapisz tylko lokalnie na dzisiaj
                var staleId = String(draggedTask).replace('stale-', '');
                var staleTask = harmonogramStale.find(function(s) { return s.id == staleId; });

                if (staleTask) {
                    staleTask.godzina_start = godzina + ':00';
                    // Oblicz godzinę końcową
                    if (staleTask.planowany_czas) {
                        var endMinutes = hour * 60 + parseInt(staleTask.planowany_czas);
                        var endHour = Math.floor(endMinutes / 60);
                        var endMin = endMinutes % 60;
                        staleTask.godzina_koniec = String(endHour).padStart(2, '0') + ':' + String(endMin).padStart(2, '0') + ':00';
                    }
                    saveStaleModifications();
                    renderHarmonogram();
                    showToast('Stałe zadanie przesunięte na ' + godzina, 'success');
                }
            } else {
                // Zwykłe zadanie
                var task = harmonogramTasks.find(function(t) { return t.id == draggedTask; });

                if (task) {
                    // Oblicz godzinę końcową
                    var godzinaKoniec = null;
                    if (task.planowany_czas) {
                        var endMinutes = hour * 60 + parseInt(task.planowany_czas);
                        var endHour = Math.floor(endMinutes / 60);
                        var endMin = endMinutes % 60;
                        godzinaKoniec = String(endHour).padStart(2, '0') + ':' + String(endMin).padStart(2, '0');
                    }

                    // Aktualizuj w bazie
                    $.post(ajaxurl, {
                        action: 'zadaniomat_update_harmonogram',
                        nonce: nonce,
                        id: draggedTask,
                        godzina_start: godzina,
                        godzina_koniec: godzinaKoniec
                    }, function(response) {
                        if (response.success) {
                            // Aktualizuj lokalnie
                            task.godzina_start = godzina;
                            task.godzina_koniec = godzinaKoniec;
                            renderHarmonogram();
                            showToast('Zadanie przypisane do ' + godzina, 'success');
                        }
                    });
                }
            }

            draggedTask = null;
            $('.dragging').removeClass('dragging');
        };

        // Usuń z harmonogramu
        window.removeFromHarmonogram = function(taskId) {
            $.post(ajaxurl, {
                action: 'zadaniomat_update_harmonogram',
                nonce: nonce,
                id: taskId,
                godzina_start: '',
                godzina_koniec: ''
            }, function(response) {
                if (response.success) {
                    var task = harmonogramTasks.find(function(t) { return t.id == taskId; });
                    if (task) {
                        task.godzina_start = null;
                        task.godzina_koniec = null;
                    }
                    renderHarmonogram();
                    showToast('Zadanie usunięte z harmonogramu', 'success');
                }
            });
        };

        // Toggle widok
        window.toggleHarmonogramView = function(view) {
            harmonogramView = view;
            $('.view-toggle button').removeClass('active');
            $('.view-toggle button[data-view="' + view + '"]').addClass('active');
            renderHarmonogram();
        };

        // Renderuj widok listy
        window.renderListView = function() {
            var allTasks = [];

            // Dodaj zwykłe zadania
            harmonogramTasks.forEach(function(task) {
                if (task.godzina_start) {
                    allTasks.push({ task: task, isStale: false, time: task.godzina_start });
                }
            });

            // Dodaj stałe zadania
            harmonogramStale.forEach(function(stale) {
                if (stale.godzina_start) {
                    allTasks.push({ task: stale, isStale: true, time: stale.godzina_start });
                }
            });

            // Sortuj po czasie
            allTasks.sort(function(a, b) {
                return a.time.localeCompare(b.time);
            });

            var html = '<div style="padding: 10px;">';
            allTasks.forEach(function(item) {
                html += renderHarmonogramTask(item.task, item.isStale);
            });

            if (allTasks.length === 0) {
                html += '<p style="color: #888; text-align: center; padding: 40px;">Przeciągnij zadania na timeline, aby ułożyć harmonogram</p>';
            }

            html += '</div>';
            $('#harmonogram-timeline').html(html);
        };

        // Aktualizuj linię aktualnego czasu
        window.updateCurrentTimeLine = function() {
            if (harmonogramView !== 'timeline') return;

            var now = new Date();
            var currentHour = now.getHours();
            var currentMinute = now.getMinutes();

            // Usuń poprzednią linię
            $('.timeline-current-time').remove();

            // Znajdź godzinę
            var $hourDiv = $('.timeline-hour[data-hour="' + currentHour + '"]');
            if ($hourDiv.length) {
                var percentInHour = (currentMinute / 60) * 100;
                var line = $('<div class="timeline-current-time"></div>');
                line.css('top', percentInHour + '%');
                $hourDiv.find('.timeline-hour-content').append(line);
            }
        };

        // Aktualizuj linię co minutę
        setInterval(updateCurrentTimeLine, 60000);

        // Modyfikuj selectDate żeby sprawdzić harmonogram
        var originalSelectDate = window.selectDate;
        window.selectDate = function(date) {
            selectedDate = date;
            $('.calendar-day').removeClass('selected');
            $('.calendar-day[data-date="' + date + '"]').addClass('selected');
            loadTasks();
            updateDateInfo();
            $('#task-date').val(date);
            checkShowHarmonogram();
        };

        // ==================== TIMER / CZASOMIERZ ====================
        var activeTimer = null; // { taskId, taskName, plannedTime, startTime, elapsedBefore, interval, notified }
        var timerAudio = null;

        // Inicjalizacja dźwięku (generowany programowo)
        window.initTimerAudio = function() {
            if (timerAudio) return;
            var AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            timerAudio = {
                play: function() {
                    var ctx = new AudioContext();
                    var oscillator = ctx.createOscillator();
                    var gainNode = ctx.createGain();

                    oscillator.connect(gainNode);
                    gainNode.connect(ctx.destination);

                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    gainNode.gain.value = 0.3;

                    oscillator.start();

                    // Beep pattern
                    setTimeout(function() { gainNode.gain.value = 0; }, 200);
                    setTimeout(function() { gainNode.gain.value = 0.3; }, 400);
                    setTimeout(function() { gainNode.gain.value = 0; }, 600);
                    setTimeout(function() { gainNode.gain.value = 0.3; }, 800);
                    setTimeout(function() { oscillator.stop(); ctx.close(); }, 1000);
                }
            };
        };

        // Poproś o pozwolenie na powiadomienia
        window.requestNotificationPermission = function() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        };

        // Uruchom timer dla zadania
        window.startTimer = function(taskId, taskName, plannedMinutes) {
            // Jeśli już jest aktywny timer dla innego zadania
            if (activeTimer && activeTimer.taskId !== taskId) {
                if (!confirm('Masz już uruchomiony timer dla innego zadania. Czy chcesz go zatrzymać i uruchomić nowy?')) {
                    return;
                }
                stopTimer(false);
            }

            // Jeśli to kontynuacja tego samego zadania
            var elapsedBefore = 0;
            if (activeTimer && activeTimer.taskId === taskId) {
                elapsedBefore = activeTimer.elapsedBefore + getElapsedSeconds();
                clearInterval(activeTimer.interval);
            }

            initTimerAudio();
            requestNotificationPermission();

            activeTimer = {
                taskId: taskId,
                taskName: taskName,
                plannedTime: plannedMinutes * 60, // w sekundach
                startTime: Date.now(),
                elapsedBefore: elapsedBefore,
                interval: null,
                notified: false
            };

            activeTimer.interval = setInterval(updateTimerDisplay, 1000);
            updateTimerDisplay();
            renderFloatingTimer();

            showToast('⏱️ Timer uruchomiony: ' + taskName, 'success');
        };

        // Pobierz upływający czas w sekundach
        window.getElapsedSeconds = function() {
            if (!activeTimer) return 0;
            return Math.floor((Date.now() - activeTimer.startTime) / 1000);
        };

        // Pobierz całkowity czas (poprzedni + aktualny)
        window.getTotalElapsed = function() {
            if (!activeTimer) return 0;
            return activeTimer.elapsedBefore + getElapsedSeconds();
        };

        // Aktualizuj wyświetlanie timera
        window.updateTimerDisplay = function() {
            if (!activeTimer) return;

            var totalElapsed = getTotalElapsed();
            var remaining = activeTimer.plannedTime - totalElapsed;
            var isOvertime = remaining < 0;

            // Aktualizuj floating timer
            var timeStr = formatTime(Math.abs(remaining));
            if (isOvertime) timeStr = '+' + timeStr;

            $('.ft-time').text(timeStr);

            if (isOvertime) {
                $('#floating-timer-container .floating-timer').addClass('overtime');
            }

            // Sprawdź czy czas minął i pokaż powiadomienie
            if (remaining <= 0 && !activeTimer.notified) {
                activeTimer.notified = true;
                showTimerEndNotification();
            }
        };

        // Formatuj czas (sekundy -> MM:SS)
        window.formatTime = function(seconds) {
            var mins = Math.floor(seconds / 60);
            var secs = seconds % 60;
            return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        };

        // Formatuj czas w minutach
        window.formatMinutes = function(seconds) {
            return Math.round(seconds / 60);
        };

        // Renderuj floating timer
        window.renderFloatingTimer = function() {
            if (!activeTimer) {
                $('#floating-timer-container').html('');
                return;
            }

            var html = '<div class="floating-timer" onclick="showTimerModal()">';
            html += '<div class="ft-time">00:00</div>';
            html += '<div class="ft-task">' + escapeHtml(activeTimer.taskName) + '</div>';
            html += '<div class="ft-actions">';
            html += '<button onclick="event.stopPropagation(); stopTimer(true)" title="Zakończ">✓</button>';
            html += '<button onclick="event.stopPropagation(); cancelTimer()" title="Anuluj">✕</button>';
            html += '</div>';
            html += '</div>';

            $('#floating-timer-container').html(html);
        };

        // Pokaż powiadomienie o końcu czasu
        window.showTimerEndNotification = function() {
            // Dźwięk
            if (timerAudio) timerAudio.play();

            // Powiadomienie systemowe
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('⏰ Czas upłynął!', {
                    body: activeTimer.taskName + ' - czas planowany minął',
                    icon: '⏰',
                    requireInteraction: true
                });
            }

            // Pokaż modal
            showTimerModal();
        };

        // Pokaż modal timera
        window.showTimerModal = function() {
            if (!activeTimer) return;

            var totalElapsed = getTotalElapsed();
            var remaining = activeTimer.plannedTime - totalElapsed;
            var isOvertime = remaining < 0;
            var timeStr = formatTime(Math.abs(isOvertime ? remaining : totalElapsed));

            var html = '<div class="timer-modal-overlay" onclick="closeTimerModal(event)">';
            html += '<div class="timer-modal" onclick="event.stopPropagation()">';

            if (isOvertime) {
                html += '<h2>⏰ Czas upłynął!</h2>';
            } else {
                html += '<h2>⏱️ Timer aktywny</h2>';
            }

            html += '<div class="task-name">' + escapeHtml(activeTimer.taskName) + '</div>';
            html += '<div class="timer-big' + (isOvertime ? ' overtime' : '') + '" id="modal-timer-display">';
            html += (isOvertime ? '+' : '') + timeStr;
            html += '</div>';
            html += '<div class="time-info">';
            html += 'Planowany czas: ' + formatMinutes(activeTimer.plannedTime) + ' min | ';
            html += 'Przepracowano: ' + formatMinutes(totalElapsed) + ' min';
            html += '</div>';

            html += '<div class="timer-modal-actions">';
            html += '<button class="btn-timer-done" onclick="stopTimer(true)">✓ Zakończone</button>';
            if (isOvertime) {
                html += '<button class="btn-timer-extend" onclick="showExtendOptions()">+ Przedłuż</button>';
            }
            html += '<button class="btn-timer-stop" onclick="stopTimer(false)">⏹ Zatrzymaj</button>';
            html += '</div>';

            html += '<div class="extend-options" id="extend-options" style="display:none;">';
            html += '<button onclick="extendTimer(5)">+5 min</button>';
            html += '<button onclick="extendTimer(10)">+10 min</button>';
            html += '<button onclick="extendTimer(15)">+15 min</button>';
            html += '<button onclick="extendTimer(30)">+30 min</button>';
            html += '</div>';

            html += '</div></div>';

            $('#timer-modal-container').html(html);

            // Aktualizuj czas w modalu
            if (activeTimer) {
                var modalInterval = setInterval(function() {
                    if (!activeTimer) {
                        clearInterval(modalInterval);
                        return;
                    }
                    var elapsed = getTotalElapsed();
                    var rem = activeTimer.plannedTime - elapsed;
                    var over = rem < 0;
                    var ts = formatTime(Math.abs(over ? rem : elapsed));
                    $('#modal-timer-display').text((over ? '+' : '') + ts);
                    if (over) $('#modal-timer-display').addClass('overtime');
                }, 1000);
            }
        };

        window.closeTimerModal = function(event) {
            if (event && event.target !== event.currentTarget) return;
            $('#timer-modal-container').html('');
        };

        window.showExtendOptions = function() {
            $('#extend-options').show();
        };

        // Przedłuż timer
        window.extendTimer = function(minutes) {
            if (!activeTimer) return;

            activeTimer.plannedTime += minutes * 60;
            activeTimer.notified = false;

            closeTimerModal();
            $('#floating-timer-container .floating-timer').removeClass('overtime');
            showToast('Timer przedłużony o ' + minutes + ' minut', 'success');
        };

        // Zatrzymaj timer
        window.stopTimer = function(markAsComplete) {
            if (!activeTimer) return;

            clearInterval(activeTimer.interval);
            var totalMinutes = formatMinutes(getTotalElapsed());
            var taskId = activeTimer.taskId;

            // Zapisz rzeczywisty czas
            $.post(ajaxurl, {
                action: 'zadaniomat_quick_update',
                nonce: nonce,
                id: taskId,
                field: 'faktyczny_czas',
                value: totalMinutes
            }, function(response) {
                if (response.success) {
                    // Odśwież listę zadań
                    loadTasks();
                    if (selectedDate === today) loadHarmonogram();
                }
            });

            // Jeśli oznaczamy jako zakończone
            if (markAsComplete) {
                $.post(ajaxurl, {
                    action: 'zadaniomat_quick_update',
                    nonce: nonce,
                    id: taskId,
                    field: 'status',
                    value: '1'
                });
            }

            showToast('⏱️ Zapisano czas: ' + totalMinutes + ' min', 'success');

            activeTimer = null;
            $('#floating-timer-container').html('');
            closeTimerModal();
        };

        // Anuluj timer bez zapisywania
        window.cancelTimer = function() {
            if (!activeTimer) return;
            if (!confirm('Anulować timer bez zapisywania czasu?')) return;

            clearInterval(activeTimer.interval);
            activeTimer = null;
            $('#floating-timer-container').html('');
            closeTimerModal();
            showToast('Timer anulowany', 'warning');
        };

        // Uruchom kolejną sesję timera (dodatkowy czas)
        window.startAdditionalTimer = function(taskId, taskName, minutes) {
            // Pobierz aktualny faktyczny czas z bazy i dodaj do niego
            var currentFactical = 0;
            // Na razie uruchom normalnie - czas się zsumuje
            startTimer(taskId, taskName, minutes);
        };

        // Edytuj rzeczywisty czas ręcznie
        window.editFaktycznyCzas = function(taskId, currentValue) {
            var newValue = prompt('Podaj rzeczywisty czas w minutach:', currentValue || '0');
            if (newValue === null) return;

            var minutes = parseInt(newValue);
            if (isNaN(minutes) || minutes < 0) {
                alert('Podaj prawidłową liczbę minut');
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_quick_update',
                nonce: nonce,
                id: taskId,
                field: 'faktyczny_czas',
                value: minutes
            }, function(response) {
                if (response.success) {
                    loadTasks();
                    showToast('Czas zaktualizowany: ' + minutes + ' min', 'success');
                }
            });
        };

    })(jQuery);
    </script>
    <?php
}

// =============================================
// STRONA USTAWIEŃ
// =============================================
function zadaniomat_page_settings() {
    global $wpdb;
    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';
    $table_cele_rok = $wpdb->prefix . 'zadaniomat_cele_rok';
    
    // Dodawanie roku
    if (isset($_POST['dodaj_rok']) && wp_verify_nonce($_POST['nonce'], 'zadaniomat_action')) {
        $wpdb->insert($table_roki, [
            'nazwa' => sanitize_text_field($_POST['nazwa']),
            'data_start' => sanitize_text_field($_POST['data_start']),
            'data_koniec' => sanitize_text_field($_POST['data_koniec'])
        ]);
        echo '<div class="notice notice-success"><p>✅ Rok dodany!</p></div>';
    }
    
    // Dodawanie okresu
    if (isset($_POST['dodaj_okres']) && wp_verify_nonce($_POST['nonce'], 'zadaniomat_action')) {
        $wpdb->insert($table_okresy, [
            'rok_id' => intval($_POST['rok_id']),
            'nazwa' => sanitize_text_field($_POST['nazwa']),
            'data_start' => sanitize_text_field($_POST['data_start']),
            'data_koniec' => sanitize_text_field($_POST['data_koniec'])
        ]);
        echo '<div class="notice notice-success"><p>✅ Okres dodany!</p></div>';
    }
    
    // Usuwanie roku (kaskadowo usuwa okresy i cele)
    if (isset($_GET['delete_rok']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_rok')) {
        $rok_id = intval($_GET['delete_rok']);
        // Usuń cele roku
        $wpdb->delete($table_cele_rok, ['rok_id' => $rok_id]);
        // Usuń cele okresów tego roku
        $okresy_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_okresy WHERE rok_id = %d", $rok_id));
        if (!empty($okresy_ids)) {
            $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
            $wpdb->query("DELETE FROM $table_cele_okres WHERE okres_id IN (" . implode(',', array_map('intval', $okresy_ids)) . ")");
        }
        // Usuń okresy
        $wpdb->delete($table_okresy, ['rok_id' => $rok_id]);
        // Usuń rok
        $wpdb->delete($table_roki, ['id' => $rok_id]);
        echo '<div class="notice notice-success"><p>✅ Rok usunięty wraz z okresami!</p></div>';
    }
    if (isset($_GET['delete_okres']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_okres')) {
        $okres_id = intval($_GET['delete_okres']);
        // Usuń cele okresu
        $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
        $wpdb->delete($table_cele_okres, ['okres_id' => $okres_id]);
        // Usuń okres
        $wpdb->delete($table_okresy, ['id' => $okres_id]);
        echo '<div class="notice notice-success"><p>✅ Okres usunięty!</p></div>';
    }
    
    // Pobierz osierocone okresy (bez roku lub z nieistniejącym rokiem)
    $orphaned_okresy = $wpdb->get_results(
        "SELECT o.* FROM $table_okresy o LEFT JOIN $table_roki r ON o.rok_id = r.id WHERE r.id IS NULL"
    );
    
    $roki = $wpdb->get_results("SELECT * FROM $table_roki ORDER BY data_start DESC");
    $selected_rok = isset($_GET['rok_id']) ? intval($_GET['rok_id']) : ($roki ? $roki[0]->id : null);
    $current_rok = $selected_rok ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_roki WHERE id = %d", $selected_rok)) : null;
    
    $okresy = $selected_rok ? $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_okresy WHERE rok_id = %d ORDER BY data_start ASC", $selected_rok
    )) : [];
    
    $cele_rok = [];
    if ($selected_rok) {
        $cele_raw = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_cele_rok WHERE rok_id = %d", $selected_rok));
        foreach ($cele_raw as $c) {
            $cele_rok[$c->kategoria] = $c->cel;
        }
    }
    
    ?>
    <div class="wrap zadaniomat-wrap">
        <h1>⚙️ Ustawienia Zadaniomatu</h1>
        
        <?php if (!empty($orphaned_okresy)): ?>
        <div class="zadaniomat-card" style="background: #fff3cd; border-left: 4px solid #ffc107;">
            <h2>⚠️ Osierocone okresy (bez przypisanego roku)</h2>
            <p style="color: #856404;">Te okresy nie mają przypisanego roku. Możesz je usunąć.</p>
            <table class="settings-table">
                <thead><tr><th>Nazwa</th><th>Okres</th><th>Akcje</th></tr></thead>
                <tbody>
                    <?php foreach ($orphaned_okresy as $o): ?>
                        <tr>
                            <td><strong><?php echo esc_html($o->nazwa); ?></strong></td>
                            <td><?php echo date('d.m.Y', strtotime($o->data_start)); ?> - <?php echo date('d.m.Y', strtotime($o->data_koniec)); ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zadaniomat-settings&delete_okres=' . $o->id), 'delete_okres'); ?>" 
                                   class="button button-small" style="background: #dc3545; color: #fff; border-color: #dc3545;"
                                   onclick="return confirm('Usunąć ten osierocony okres?');">🗑️ Usuń</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="settings-grid">
            <div class="zadaniomat-card">
                <h2>📅 Roki 90-dniowe</h2>
                <form method="post" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('zadaniomat_action', 'nonce'); ?>
                    <div class="form-grid">
                        <div class="form-group"><label>Nazwa</label><input type="text" name="nazwa" placeholder="np. ROK 1" required></div>
                        <div class="form-group"><label>Start</label><input type="date" name="data_start" required></div>
                        <div class="form-group"><label>Koniec</label><input type="date" name="data_koniec" required></div>
                    </div>
                    <button type="submit" name="dodaj_rok" class="button button-primary">➕ Dodaj rok</button>
                </form>
                <table class="settings-table">
                    <thead><tr><th>Nazwa</th><th>Okres</th><th>Akcje</th></tr></thead>
                    <tbody>
                        <?php foreach ($roki as $r): 
                            $is_current = (date('Y-m-d') >= $r->data_start && date('Y-m-d') <= $r->data_koniec);
                        ?>
                            <tr class="<?php echo $is_current ? 'status-partial' : ''; ?>">
                                <td><strong><?php echo esc_html($r->nazwa); ?></strong></td>
                                <td><?php echo date('d.m.Y', strtotime($r->data_start)); ?> - <?php echo date('d.m.Y', strtotime($r->data_koniec)); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=zadaniomat-settings&rok_id=' . $r->id); ?>" class="button button-small">Wybierz</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zadaniomat-settings&delete_rok=' . $r->id), 'delete_rok'); ?>" class="btn-delete" onclick="return confirm('Usunąć?');">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="zadaniomat-card">
                <h2>📆 Okresy 2-tygodniowe <?php echo $current_rok ? '(' . esc_html($current_rok->nazwa) . ')' : ''; ?></h2>
                <?php if ($selected_rok): ?>
                <form method="post" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('zadaniomat_action', 'nonce'); ?>
                    <input type="hidden" name="rok_id" value="<?php echo $selected_rok; ?>">
                    <div class="form-grid">
                        <div class="form-group"><label>Nazwa</label><input type="text" name="nazwa" placeholder="np. Okres 1" required></div>
                        <div class="form-group"><label>Start</label><input type="date" name="data_start" required></div>
                        <div class="form-group"><label>Koniec</label><input type="date" name="data_koniec" required></div>
                    </div>
                    <button type="submit" name="dodaj_okres" class="button button-primary">➕ Dodaj okres</button>
                </form>
                <table class="settings-table">
                    <thead><tr><th>Nazwa</th><th>Okres</th><th>Status</th><th>Akcje</th></tr></thead>
                    <tbody>
                        <?php foreach ($okresy as $o): 
                            $is_current = (date('Y-m-d') >= $o->data_start && date('Y-m-d') <= $o->data_koniec);
                            $is_past = (date('Y-m-d') > $o->data_koniec);
                            $is_future = (date('Y-m-d') < $o->data_start);
                        ?>
                            <tr class="<?php echo $is_current ? 'status-partial' : ''; ?>">
                                <td><strong><?php echo esc_html($o->nazwa); ?></strong></td>
                                <td><?php echo date('d.m', strtotime($o->data_start)); ?> - <?php echo date('d.m.Y', strtotime($o->data_koniec)); ?></td>
                                <td>
                                    <?php if ($is_current): ?>
                                        <span style="color: #28a745;">🟢 Aktywny</span>
                                    <?php elseif ($is_past): ?>
                                        <span style="color: #6c757d;">✓ Zakończony</span>
                                    <?php else: ?>
                                        <span style="color: #17a2b8;">⏳ Przyszły</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="button button-small" onclick="openOkresModal(<?php echo $o->id; ?>)">📋 Cele</button>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zadaniomat-settings&rok_id=' . $selected_rok . '&delete_okres=' . $o->id), 'delete_okres'); ?>" class="btn-delete" onclick="return confirm('Usunąć?');">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="color: #666;">Najpierw dodaj i wybierz rok.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($current_rok): ?>
        <div class="zadaniomat-card">
            <h2>🎯 Cele strategiczne na <?php echo esc_html($current_rok->nazwa); ?></h2>
            <div class="cele-grid">
                <?php foreach (ZADANIOMAT_KATEGORIE as $key => $label): ?>
                    <div class="cel-card <?php echo $key; ?>">
                        <h4><?php echo $label; ?></h4>
                        <textarea class="cel-rok-input" data-rok="<?php echo $selected_rok; ?>" data-kategoria="<?php echo $key; ?>" placeholder="Cel strategiczny..."><?php echo esc_textarea($cele_rok[$key] ?? ''); ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="zadaniomat-card">
            <h2>🔄 Stałe zadania (cykliczne)</h2>
            <p style="color: #666; margin-bottom: 15px;">Definiuj zadania, które powtarzają się regularnie. Będą automatycznie pojawiać się w harmonogramie dnia.</p>

            <!-- Formularz dodawania/edycji stałego zadania -->
            <div class="stale-zadania-form">
                <h4 style="margin-top: 0;" id="stale-form-title">➕ Dodaj stałe zadanie</h4>
                <input type="hidden" id="stale-edit-id" value="">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Nazwa zadania</label>
                        <input type="text" id="stale-nazwa" placeholder="np. Sprawdzenie maili">
                    </div>
                    <div class="form-group">
                        <label>Kategoria</label>
                        <select id="stale-kategoria">
                            <?php foreach (ZADANIOMAT_KATEGORIE_ZADANIA as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Czas (min)</label>
                        <input type="number" id="stale-czas" min="0" placeholder="30" style="width: 80px;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Typ powtarzania</label>
                        <select id="stale-typ" onchange="toggleStaleOptions()">
                            <option value="codziennie">Codziennie</option>
                            <option value="dni_tygodnia">Wybrane dni tygodnia</option>
                            <option value="dzien_miesiaca">Dzień miesiąca</option>
                            <option value="dni_przed_koncem_roku">Dni przed końcem roku (90-dni)</option>
                            <option value="dni_przed_koncem_okresu">Dni przed końcem okresu (2 tyg)</option>
                        </select>
                    </div>
                    <div class="form-group" id="stale-dni-wrap" style="display: none;">
                        <label>Dni tygodnia</label>
                        <div class="dni-tygodnia-checkboxes">
                            <label><input type="checkbox" value="pn"><span>Pn</span></label>
                            <label><input type="checkbox" value="wt"><span>Wt</span></label>
                            <label><input type="checkbox" value="sr"><span>Śr</span></label>
                            <label><input type="checkbox" value="cz"><span>Cz</span></label>
                            <label><input type="checkbox" value="pt"><span>Pt</span></label>
                            <label><input type="checkbox" value="so"><span>So</span></label>
                            <label><input type="checkbox" value="nd"><span>Nd</span></label>
                        </div>
                    </div>
                    <div class="form-group" id="stale-dzien-wrap" style="display: none;">
                        <label>Dzień miesiąca</label>
                        <input type="number" id="stale-dzien-miesiaca" min="1" max="31" placeholder="1-31" style="width: 80px;">
                    </div>
                    <div class="form-group" id="stale-dni-przed-wrap" style="display: none;">
                        <label>Ile dni przed końcem roku</label>
                        <input type="number" id="stale-dni-przed-koncem" min="1" max="90" placeholder="np. 7" style="width: 80px;">
                    </div>
                    <div class="form-group" id="stale-dni-przed-okresu-wrap" style="display: none;">
                        <label>Ile dni przed końcem okresu</label>
                        <input type="number" id="stale-dni-przed-okresu" min="1" max="14" placeholder="np. 3" style="width: 80px;">
                    </div>
                    <div class="form-group">
                        <label>Godzina start</label>
                        <input type="time" id="stale-godzina-start">
                    </div>
                    <div class="form-group">
                        <label>Godzina koniec</label>
                        <input type="time" id="stale-godzina-koniec">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Minuty po starcie dnia</label>
                        <input type="number" id="stale-minuty-po-starcie" min="0" max="480" placeholder="np. 30" style="width: 80px;">
                        <span style="font-size:11px;color:#888;">(0 = domyślnie godzina start)</span>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="stale-dodaj-do-listy" style="width:auto;">
                            <span>Dodaj też do listy zadań</span>
                        </label>
                        <span style="font-size:11px;color:#888;">(nie tylko harmonogram)</span>
                    </div>
                </div>
                <button type="button" class="button button-primary" id="stale-submit-btn" onclick="saveStaleZadanie()">➕ Dodaj stałe zadanie</button>
                <button type="button" class="button" id="stale-cancel-btn" onclick="cancelStaleEdit()" style="display: none; margin-left: 10px;">Anuluj</button>
            </div>

            <!-- Lista stałych zadań -->
            <table class="stale-zadania-table" id="stale-zadania-table">
                <thead>
                    <tr>
                        <th>Aktywne</th>
                        <th>Nazwa</th>
                        <th>Kategoria</th>
                        <th>Powtarzanie</th>
                        <th>Godziny</th>
                        <th>Czas</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody id="stale-zadania-body">
                    <!-- Wypełniane przez JavaScript -->
                </tbody>
            </table>
        </div>

        <div class="zadaniomat-card">
            <h2>📁 Zarządzanie kategoriami</h2>
            <p style="color: #666; margin-bottom: 15px;">Edytuj kategorie celów i zadań. Kategorie celów to główne obszary strategiczne. Kategorie zadań to wszystkie dostępne kategorie przy dodawaniu zadań.</p>

            <div class="settings-grid">
                <div>
                    <h3 style="margin-top: 0;">Kategorie celów</h3>
                    <p style="font-size: 12px; color: #888;">Te kategorie są używane przy definiowaniu celów rocznych i 2-tygodniowych.</p>
                    <div id="kategorie-cele-list"></div>
                    <button type="button" class="button" onclick="addKategoriaCel()">➕ Dodaj kategorię</button>
                </div>
                <div>
                    <h3 style="margin-top: 0;">Kategorie zadań</h3>
                    <p style="font-size: 12px; color: #888;">Te kategorie są dostępne przy tworzeniu zadań (mogą zawierać dodatkowe).</p>
                    <div id="kategorie-zadania-list"></div>
                    <button type="button" class="button" onclick="addKategoriaZadanie()">➕ Dodaj kategorię</button>
                </div>
            </div>

            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                <button type="button" class="button button-primary" onclick="saveKategorie()">💾 Zapisz kategorie</button>
                <button type="button" class="button" onclick="resetKategorie()" style="margin-left: 10px;">🔄 Przywróć domyślne</button>
                <span id="kategorie-save-status" style="margin-left: 15px; color: #28a745;"></span>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var nonce = '<?php echo wp_create_nonce('zadaniomat_ajax'); ?>';
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        $('.cel-rok-input').on('change', function() {
            var $this = $(this);
            $.post(ajaxurl, {
                action: 'zadaniomat_save_cel_rok',
                nonce: nonce,
                rok_id: $this.data('rok'),
                kategoria: $this.data('kategoria'),
                cel: $this.val()
            }, function(response) {
                if (response.success) {
                    $this.css('background', '#d4edda');
                    setTimeout(function() { $this.css('background', ''); }, 500);
                }
            });
        });
        
        // Modal funkcje
        window.openOkresModal = function(okresId) {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_okres_cele',
                nonce: nonce,
                okres_id: okresId
            }, function(response) {
                if (response.success) {
                    renderOkresModal(response.data);
                }
            });
        };
        
        window.renderOkresModal = function(data) {
            var okres = data.okres;
            var cele = data.cele;
            var kategorie = data.kategorie;
            var today = new Date().toISOString().split('T')[0];
            var isPast = okres.data_koniec < today;
            
            var startDate = new Date(okres.data_start);
            var endDate = new Date(okres.data_koniec);
            var dateStr = startDate.getDate() + '.' + (startDate.getMonth()+1) + ' - ' + endDate.getDate() + '.' + (endDate.getMonth()+1) + '.' + endDate.getFullYear();
            
            var html = '<div class="modal-overlay" onclick="closeOkresModal(event)">';
            html += '<div class="modal okres-modal" onclick="event.stopPropagation()">';
            html += '<button class="modal-close" onclick="closeOkresModal()">&times;</button>';
            html += '<div class="okres-modal-header ' + (isPast ? 'past' : '') + '">';
            html += '<h3>' + (isPast ? '📊 Podsumowanie: ' : '🎯 Cele: ') + escapeHtml(okres.nazwa) + '</h3>';
            html += '<div class="dates">📅 ' + dateStr + '</div>';
            html += '</div>';
            
            for (var kat in kategorie) {
                var cel = cele[kat] || {};
                var celText = cel.cel || '';
                var osiagniety = cel.osiagniety;
                var uwagi = cel.uwagi || '';
                
                var cardClass = '';
                if (osiagniety === '1' || osiagniety === 1) cardClass = 'osiagniety-yes';
                else if (osiagniety === '0' || osiagniety === 0) cardClass = 'osiagniety-no';
                else if (osiagniety === '2' || osiagniety === 2) cardClass = 'osiagniety-partial';
                
                html += '<div class="cel-review-card ' + kat + ' ' + cardClass + '" data-kategoria="' + kat + '">';
                html += '<h4>' + kategorie[kat] + '</h4>';
                html += '<div class="cel-text ' + (celText ? '' : 'empty') + '">' + (celText ? escapeHtml(celText) : 'Brak celu') + '</div>';
                
                html += '<div class="cel-review-row">';
                html += '<div class="field">';
                html += '<label>Czy cel został osiągnięty?</label>';
                html += '<select class="osiagniety-select" data-okres="' + okres.id + '" data-kategoria="' + kat + '">';
                html += '<option value="">-- wybierz --</option>';
                html += '<option value="1"' + (osiagniety == 1 ? ' selected' : '') + '>✅ Tak</option>';
                html += '<option value="2"' + (osiagniety == 2 ? ' selected' : '') + '>🟡 Częściowo</option>';
                html += '<option value="0"' + (osiagniety == 0 ? ' selected' : '') + '>❌ Nie</option>';
                html += '</select>';
                html += '</div>';
                html += '<div class="field" style="flex: 2;">';
                html += '<label>Uwagi / wnioski</label>';
                html += '<textarea class="uwagi-input" data-okres="' + okres.id + '" data-kategoria="' + kat + '" placeholder="Co poszło dobrze? Co można poprawić?">' + escapeHtml(uwagi) + '</textarea>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }
            
            html += '<div class="modal-buttons">';
            html += '<button class="button button-primary" onclick="closeOkresModal()">Zamknij</button>';
            html += '</div>';
            html += '</div></div>';
            
            $('body').append(html);
            
            // Bind events
            $('.osiagniety-select').on('change', function() {
                var $this = $(this);
                var $card = $this.closest('.cel-review-card');
                var uwagi = $card.find('.uwagi-input').val();
                
                saveCelPodsumowanie($this.data('okres'), $this.data('kategoria'), $this.val(), uwagi, $card);
            });
            
            $('.uwagi-input').on('change', function() {
                var $this = $(this);
                var $card = $this.closest('.cel-review-card');
                var osiagniety = $card.find('.osiagniety-select').val();
                
                saveCelPodsumowanie($this.data('okres'), $this.data('kategoria'), osiagniety, $this.val(), $card);
            });
        };
        
        window.saveCelPodsumowanie = function(okresId, kategoria, osiagniety, uwagi, $card) {
            $.post(ajaxurl, {
                action: 'zadaniomat_save_cel_podsumowanie',
                nonce: nonce,
                okres_id: okresId,
                kategoria: kategoria,
                osiagniety: osiagniety,
                uwagi: uwagi
            }, function(response) {
                if (response.success) {
                    // Update card color
                    $card.removeClass('osiagniety-yes osiagniety-no osiagniety-partial');
                    if (osiagniety === '1') $card.addClass('osiagniety-yes');
                    else if (osiagniety === '0') $card.addClass('osiagniety-no');
                    else if (osiagniety === '2') $card.addClass('osiagniety-partial');
                    
                    // Flash effect
                    $card.css('box-shadow', '0 0 10px #28a745');
                    setTimeout(function() { $card.css('box-shadow', ''); }, 500);
                }
            });
        };
        
        window.closeOkresModal = function(event) {
            if (event && event.target !== event.currentTarget) return;
            $('.modal-overlay').remove();
        };
        
        window.escapeHtml = function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        // Close modal on ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') closeOkresModal();
        });

        // ==================== ZARZĄDZANIE KATEGORIAMI ====================
        var kategorieCele = <?php echo json_encode(zadaniomat_get_kategorie()); ?>;
        var kategorieZadania = <?php echo json_encode(zadaniomat_get_kategorie_zadania()); ?>;

        function renderKategorieList() {
            var htmlCele = '';
            for (var key in kategorieCele) {
                htmlCele += '<div class="kategoria-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">';
                htmlCele += '<input type="text" class="kat-cel-key" value="' + escapeHtml(key) + '" placeholder="klucz" style="width: 120px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
                htmlCele += '<input type="text" class="kat-cel-label" value="' + escapeHtml(kategorieCele[key]) + '" placeholder="Nazwa" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
                htmlCele += '<button type="button" class="button button-small" onclick="removeKategoriaCel(this)" style="color: #dc3545;">✕</button>';
                htmlCele += '</div>';
            }
            $('#kategorie-cele-list').html(htmlCele);

            var htmlZadania = '';
            for (var key in kategorieZadania) {
                htmlZadania += '<div class="kategoria-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">';
                htmlZadania += '<input type="text" class="kat-zad-key" value="' + escapeHtml(key) + '" placeholder="klucz" style="width: 120px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
                htmlZadania += '<input type="text" class="kat-zad-label" value="' + escapeHtml(kategorieZadania[key]) + '" placeholder="Nazwa" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
                htmlZadania += '<button type="button" class="button button-small" onclick="removeKategoriaZadanie(this)" style="color: #dc3545;">✕</button>';
                htmlZadania += '</div>';
            }
            $('#kategorie-zadania-list').html(htmlZadania);
        }

        window.addKategoriaCel = function() {
            var html = '<div class="kategoria-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">';
            html += '<input type="text" class="kat-cel-key" value="" placeholder="klucz" style="width: 120px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
            html += '<input type="text" class="kat-cel-label" value="" placeholder="Nazwa" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
            html += '<button type="button" class="button button-small" onclick="removeKategoriaCel(this)" style="color: #dc3545;">✕</button>';
            html += '</div>';
            $('#kategorie-cele-list').append(html);
        };

        window.addKategoriaZadanie = function() {
            var html = '<div class="kategoria-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">';
            html += '<input type="text" class="kat-zad-key" value="" placeholder="klucz" style="width: 120px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
            html += '<input type="text" class="kat-zad-label" value="" placeholder="Nazwa" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
            html += '<button type="button" class="button button-small" onclick="removeKategoriaZadanie(this)" style="color: #dc3545;">✕</button>';
            html += '</div>';
            $('#kategorie-zadania-list').append(html);
        };

        window.removeKategoriaCel = function(btn) {
            $(btn).closest('.kategoria-row').remove();
        };

        window.removeKategoriaZadanie = function(btn) {
            $(btn).closest('.kategoria-row').remove();
        };

        window.saveKategorie = function() {
            var kategorie = [];
            var kategorieZad = [];

            $('#kategorie-cele-list .kategoria-row').each(function() {
                var key = $(this).find('.kat-cel-key').val().trim();
                var label = $(this).find('.kat-cel-label').val().trim();
                if (key && label) {
                    kategorie.push({key: key, label: label});
                }
            });

            $('#kategorie-zadania-list .kategoria-row').each(function() {
                var key = $(this).find('.kat-zad-key').val().trim();
                var label = $(this).find('.kat-zad-label').val().trim();
                if (key && label) {
                    kategorieZad.push({key: key, label: label});
                }
            });

            $.post(ajaxurl, {
                action: 'zadaniomat_save_kategorie',
                nonce: nonce,
                kategorie: kategorie,
                kategorie_zadania: kategorieZad
            }, function(response) {
                if (response.success) {
                    $('#kategorie-save-status').text('✓ Zapisano! Odśwież stronę, aby zobaczyć zmiany.').show();
                    setTimeout(function() { $('#kategorie-save-status').fadeOut(); }, 5000);
                } else {
                    alert('Błąd podczas zapisywania kategorii.');
                }
            });
        };

        window.resetKategorie = function() {
            if (!confirm('Czy na pewno chcesz przywrócić domyślne kategorie? Twoje zmiany zostaną utracone.')) {
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_reset_kategorie',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    kategorieCele = response.data.kategorie;
                    kategorieZadania = response.data.kategorie_zadania;
                    renderKategorieList();
                    $('#kategorie-save-status').text('✓ Przywrócono domyślne kategorie!').show();
                    setTimeout(function() { $('#kategorie-save-status').fadeOut(); }, 3000);
                }
            });
        };

        // ==================== STAŁE ZADANIA ====================
        var staleZadania = [];

        window.toggleStaleOptions = function() {
            var typ = $('#stale-typ').val();
            $('#stale-dni-wrap').toggle(typ === 'dni_tygodnia');
            $('#stale-dzien-wrap').toggle(typ === 'dzien_miesiaca');
            $('#stale-dni-przed-wrap').toggle(typ === 'dni_przed_koncem_roku');
            $('#stale-dni-przed-okresu-wrap').toggle(typ === 'dni_przed_koncem_okresu');
        };

        window.loadStaleZadania = function() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_stale_zadania',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    staleZadania = response.data.stale_zadania;
                    renderStaleZadania();
                }
            });
        };

        window.renderStaleZadania = function() {
            var html = '';

            if (staleZadania.length === 0) {
                html = '<tr><td colspan="7" style="text-align: center; color: #888; padding: 30px;">Brak stałych zadań. Dodaj pierwsze zadanie powyżej.</td></tr>';
            } else {
                staleZadania.forEach(function(zadanie) {
                    var powtarzanie = '';
                    if (zadanie.typ_powtarzania === 'codziennie') {
                        powtarzanie = '📅 Codziennie';
                    } else if (zadanie.typ_powtarzania === 'dni_tygodnia') {
                        powtarzanie = '📆 ' + (zadanie.dni_tygodnia || '').toUpperCase().replace(/,/g, ', ');
                    } else if (zadanie.typ_powtarzania === 'dzien_miesiaca') {
                        powtarzanie = '🗓️ ' + zadanie.dzien_miesiaca + ' dnia miesiąca';
                    } else if (zadanie.typ_powtarzania === 'dni_przed_koncem_roku') {
                        powtarzanie = '🎯 ' + zadanie.dni_przed_koncem_roku + ' dni przed końcem roku';
                    } else if (zadanie.typ_powtarzania === 'dni_przed_koncem_okresu') {
                        powtarzanie = '📊 ' + zadanie.dni_przed_koncem_okresu + ' dni przed końcem okresu';
                    }

                    // Dodatkowe info
                    var extraInfo = [];
                    if (zadanie.minuty_po_starcie) {
                        extraInfo.push('⏰ +' + zadanie.minuty_po_starcie + ' min po starcie');
                    }
                    if (zadanie.dodaj_do_listy == 1) {
                        extraInfo.push('📋 Lista');
                    }

                    var godziny = '-';
                    if (zadanie.godzina_start) {
                        godziny = zadanie.godzina_start.substring(0, 5);
                        if (zadanie.godzina_koniec) {
                            godziny += ' - ' + zadanie.godzina_koniec.substring(0, 5);
                        }
                    }

                    html += '<tr data-id="' + zadanie.id + '">';
                    html += '<td>';
                    html += '<label class="toggle-switch">';
                    html += '<input type="checkbox" ' + (zadanie.aktywne == 1 ? 'checked' : '') + ' onchange="toggleStaleAktywne(' + zadanie.id + ', this.checked)">';
                    html += '<span class="toggle-slider"></span>';
                    html += '</label>';
                    html += '</td>';
                    html += '<td><strong>' + escapeHtml(zadanie.nazwa) + '</strong></td>';
                    html += '<td><span class="kategoria-badge ' + zadanie.kategoria + '">' + zadanie.kategoria_label + '</span></td>';
                    html += '<td>' + powtarzanie;
                    if (extraInfo.length > 0) {
                        html += '<br><span style="font-size:11px;color:#888;">' + extraInfo.join(' • ') + '</span>';
                    }
                    html += '</td>';
                    html += '<td>' + godziny + '</td>';
                    html += '<td>' + (zadanie.planowany_czas || '-') + ' min</td>';
                    html += '<td class="action-buttons">';
                    html += '<button class="btn-edit" onclick="editStaleZadanie(' + zadanie.id + ')" title="Edytuj">✏️</button>';
                    html += '<button class="btn-delete" onclick="deleteStaleZadanie(' + zadanie.id + ')" title="Usuń">🗑️</button>';
                    html += '</td>';
                    html += '</tr>';
                });
            }

            $('#stale-zadania-body').html(html);
        };

        // Edytuj stałe zadanie - wypełnij formularz
        window.editStaleZadanie = function(id) {
            var zadanie = staleZadania.find(function(z) { return z.id == id; });
            if (!zadanie) return;

            // Wypełnij formularz danymi
            $('#stale-edit-id').val(zadanie.id);
            $('#stale-nazwa').val(zadanie.nazwa);
            $('#stale-kategoria').val(zadanie.kategoria);
            $('#stale-czas').val(zadanie.planowany_czas || '');
            $('#stale-typ').val(zadanie.typ_powtarzania);
            $('#stale-godzina-start').val(zadanie.godzina_start ? zadanie.godzina_start.substring(0, 5) : '');
            $('#stale-godzina-koniec').val(zadanie.godzina_koniec ? zadanie.godzina_koniec.substring(0, 5) : '');
            $('#stale-dzien-miesiaca').val(zadanie.dzien_miesiaca || '');
            $('#stale-dni-przed-koncem').val(zadanie.dni_przed_koncem_roku || '');
            $('#stale-dni-przed-okresu').val(zadanie.dni_przed_koncem_okresu || '');
            $('#stale-minuty-po-starcie').val(zadanie.minuty_po_starcie || '');
            $('#stale-dodaj-do-listy').prop('checked', zadanie.dodaj_do_listy == 1);

            // Zaznacz dni tygodnia
            $('.dni-tygodnia-checkboxes input').prop('checked', false);
            if (zadanie.dni_tygodnia) {
                var dni = zadanie.dni_tygodnia.split(',');
                dni.forEach(function(dzien) {
                    $('.dni-tygodnia-checkboxes input[value="' + dzien + '"]').prop('checked', true);
                });
            }

            // Pokaż odpowiednie opcje
            toggleStaleOptions();

            // Zmień tytuł i przyciski
            $('#stale-form-title').text('✏️ Edytuj stałe zadanie');
            $('#stale-submit-btn').text('💾 Zapisz zmiany');
            $('#stale-cancel-btn').show();

            // Przewiń do formularza
            $('.stale-zadania-form')[0].scrollIntoView({ behavior: 'smooth' });
        };

        // Anuluj edycję
        window.cancelStaleEdit = function() {
            resetStaleForm();
        };

        // Reset formularza
        window.resetStaleForm = function() {
            $('#stale-edit-id').val('');
            $('#stale-nazwa').val('');
            $('#stale-czas').val('');
            $('#stale-godzina-start').val('');
            $('#stale-godzina-koniec').val('');
            $('#stale-typ').val('codziennie');
            $('#stale-kategoria').val($('#stale-kategoria option:first').val());
            toggleStaleOptions();
            $('.dni-tygodnia-checkboxes input').prop('checked', false);
            $('#stale-dzien-miesiaca').val('');
            $('#stale-dni-przed-koncem').val('');
            $('#stale-dni-przed-okresu').val('');
            $('#stale-minuty-po-starcie').val('');
            $('#stale-dodaj-do-listy').prop('checked', false);

            // Przywróć tytuł i przyciski
            $('#stale-form-title').text('➕ Dodaj stałe zadanie');
            $('#stale-submit-btn').text('➕ Dodaj stałe zadanie');
            $('#stale-cancel-btn').hide();
        };

        // Zapisz stałe zadanie (dodaj lub edytuj)
        window.saveStaleZadanie = function() {
            var nazwa = $('#stale-nazwa').val().trim();
            if (!nazwa) {
                alert('Wpisz nazwę zadania!');
                return;
            }

            var editId = $('#stale-edit-id').val();
            var typ = $('#stale-typ').val();
            var dniTygodnia = '';
            if (typ === 'dni_tygodnia') {
                var selected = [];
                $('.dni-tygodnia-checkboxes input:checked').each(function() {
                    selected.push($(this).val());
                });
                dniTygodnia = selected.join(',');
            }

            var data = {
                nonce: nonce,
                nazwa: nazwa,
                kategoria: $('#stale-kategoria').val(),
                planowany_czas: $('#stale-czas').val() || 0,
                typ_powtarzania: typ,
                dni_tygodnia: dniTygodnia,
                dzien_miesiaca: $('#stale-dzien-miesiaca').val(),
                dni_przed_koncem_roku: $('#stale-dni-przed-koncem').val(),
                dni_przed_koncem_okresu: $('#stale-dni-przed-okresu').val(),
                minuty_po_starcie: $('#stale-minuty-po-starcie').val(),
                dodaj_do_listy: $('#stale-dodaj-do-listy').is(':checked') ? 1 : 0,
                godzina_start: $('#stale-godzina-start').val(),
                godzina_koniec: $('#stale-godzina-koniec').val()
            };

            if (editId) {
                // Aktualizacja istniejącego
                data.action = 'zadaniomat_edit_stale_zadanie';
                data.id = editId;

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        // Zaktualizuj w tablicy
                        var index = staleZadania.findIndex(function(z) { return z.id == editId; });
                        if (index !== -1) {
                            staleZadania[index] = response.data.zadanie;
                        }
                        renderStaleZadania();
                        resetStaleForm();
                        showToast('Stałe zadanie zaktualizowane!', 'success');
                    }
                });
            } else {
                // Dodanie nowego
                data.action = 'zadaniomat_add_stale_zadanie';

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        staleZadania.push(response.data.zadanie);
                        renderStaleZadania();
                        resetStaleForm();
                        showToast('Stałe zadanie dodane!', 'success');
                    }
                });
            }
        };

        window.toggleStaleAktywne = function(id, aktywne) {
            $.post(ajaxurl, {
                action: 'zadaniomat_toggle_stale_zadanie',
                nonce: nonce,
                id: id,
                aktywne: aktywne ? 1 : 0
            }, function(response) {
                if (response.success) {
                    var zadanie = staleZadania.find(function(z) { return z.id == id; });
                    if (zadanie) {
                        zadanie.aktywne = aktywne ? 1 : 0;
                    }
                    showToast(aktywne ? 'Zadanie aktywowane' : 'Zadanie dezaktywowane', 'success');
                }
            });
        };

        window.deleteStaleZadanie = function(id) {
            if (!confirm('Na pewno usunąć to stałe zadanie?')) return;

            $.post(ajaxurl, {
                action: 'zadaniomat_delete_stale_zadanie',
                nonce: nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    staleZadania = staleZadania.filter(function(z) { return z.id != id; });
                    renderStaleZadania();
                    showToast('Stałe zadanie usunięte', 'success');
                }
            });
        };

        window.showToast = function(message, type) {
            var toast = $('<div style="position:fixed;bottom:20px;right:20px;background:' + (type === 'success' ? '#28a745' : '#dc3545') + ';color:#fff;padding:12px 20px;border-radius:8px;z-index:9999;">' + message + '</div>');
            $('body').append(toast);
            setTimeout(function() { toast.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        };

        // Inicjalizacja
        renderKategorieList();
        loadStaleZadania();
    });
    </script>
    <?php
}

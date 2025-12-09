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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
    dbDelta($sql5);
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
                
                <!-- Zadania -->
                <div class="zadaniomat-card">
                    <h2>📋 Zadania</h2>
                    <div id="tasks-container"></div>
                </div>
            </div>
        </div>
    </div>
    
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
        window.loadTasks = function() {
            var start = addDays(selectedDate, -1);
            var end = addDays(selectedDate, 5);
            
            $('#tasks-container').addClass('loading');
            
            $.post(ajaxurl, {
                action: 'zadaniomat_get_tasks',
                nonce: nonce,
                start: start,
                end: end
            }, function(response) {
                $('#tasks-container').removeClass('loading');
                if (response.success) {
                    renderTasks(response.data.tasks, start, end);
                }
            });
        };
        
        window.renderTasks = function(tasks, start, end) {
            // Group by day
            var byDay = {};
            tasks.forEach(function(t) {
                if (!byDay[t.dzien]) byDay[t.dzien] = [];
                byDay[t.dzien].push(t);
            });
            
            var html = '';
            var current = start;
            while (current <= end) {
                var dayTasks = byDay[current] || [];
                var isToday = (current === today);
                var isSelected = (current === selectedDate);
                
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
                if (dayTasks.length > 0) {
                    html += '<th style="width:30px;"><input type="checkbox" class="select-all-checkbox" data-day="' + current + '" title="Zaznacz wszystkie"></th>';
                }
                html += '<th style="width:130px;">Kategoria</th><th>Zadanie</th><th style="width:180px;">Cel TO DO</th>';
                html += '<th style="width:50px;">Plan</th><th style="width:70px;">Fakt</th><th style="width:70px;">Status</th><th style="width:90px;">Akcje</th>';
                html += '</tr></thead><tbody>';
                
                // Dla dzisiaj - pokaż sloty dla każdej kategorii
                if (isToday) {
                    var usedKategorie = {};
                    dayTasks.forEach(function(t) { usedKategorie[t.kategoria] = true; });
                    
                    // Najpierw istniejące zadania
                    dayTasks.forEach(function(t) {
                        html += renderTaskRow(t, current);
                    });
                    
                    // Potem puste sloty dla brakujących kategorii
                    for (var kat in kategorie) {
                        if (!usedKategorie[kat]) {
                            html += renderEmptySlot(current, kat, kategorie[kat]);
                        }
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
                current = addDays(current, 1);
            }
            
            $('#tasks-container').html(html);
        };
        
        window.renderTaskRow = function(t, day) {
            var statusClass = '';
            if (t.status !== null) {
                if (parseFloat(t.status) >= 1) statusClass = 'status-done';
                else if (parseFloat(t.status) > 0) statusClass = 'status-partial';
                else statusClass = 'status-none';
            }

            var html = '<tr class="' + statusClass + '" data-task-id="' + t.id + '">';
            html += '<td><input type="checkbox" class="task-checkbox" data-task-id="' + t.id + '" data-day="' + day + '"></td>';
            html += '<td><span class="kategoria-badge ' + t.kategoria + '">' + t.kategoria_label + '</span></td>';
            html += '<td><strong>' + escapeHtml(t.zadanie) + '</strong></td>';
            html += '<td style="font-size:12px;color:#666;">' + escapeHtml(t.cel_todo || '') + '</td>';
            html += '<td>' + (t.planowany_czas || 0) + '</td>';
            html += '<td><input type="number" class="inline-input quick-update" data-field="faktyczny_czas" data-id="' + t.id + '" value="' + (t.faktyczny_czas || '') + '" placeholder="-" min="0"></td>';
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
        
        window.renderEmptySlot = function(day, kategoria, kategoriaLabel) {
            var html = '<tr class="empty-slot" data-day="' + day + '" data-kategoria="' + kategoria + '">';
            html += '<td></td>'; // Pusta komórka dla checkboxa
            html += '<td><span class="kategoria-badge ' + kategoria + '">' + kategoriaLabel + '</span></td>';
            html += '<td><input type="text" class="slot-input slot-zadanie" placeholder="Wpisz zadanie..." data-field="zadanie"></td>';
            html += '<td><input type="text" class="slot-input slot-cel" placeholder="Cel TO DO..." data-field="cel_todo"></td>';
            html += '<td><input type="number" class="slot-input slot-czas" placeholder="-" min="0" style="width:45px;" data-field="planowany_czas"></td>';
            html += '<td>-</td>';
            html += '<td>-</td>';
            html += '<td><button class="btn-add-slot" onclick="saveSlot(this)" title="Dodaj">➕</button></td>';
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

        // Inicjalizacja
        renderKategorieList();
    });
    </script>
    <?php
}

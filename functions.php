<?php

/**
 * Определяет API-ключ OpenWeatherMap для получения погодных данных.
 * @link https://openweathermap.org/api
 */
define('OPENWEATHERMAP_API_KEY', 'a1f40109825954fcf8e4acdfbf3cf195');

/**
 * Подключает стили дочерней темы Storefront с поддержкой автокеша.
 *
 * @action wp_enqueue_scripts
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'storefront-child-style',
        get_stylesheet_uri(),
        [],
        filemtime(get_stylesheet_directory() . '/style.css')
    );
});

/**
 * Регистрирует пользовательский тип записи "Cities".
 * Используется для хранения информации о городах.
 *
 * @action init
 * @see register_post_type()
 */
add_action('init', function () {
    register_post_type('city', [
        'labels' => [
            'name' => __('Cities', 'storefront-child'),
            'singular_name' => __('City', 'storefront-child')
        ],
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-location-alt',
        'supports' => ['title'],
        'show_in_rest' => true, // Для поддержки Gutenberg и REST API
    ]);
});

/**
 * Регистрирует таксономию "Countries" для CPT "Cities".
 * Позволяет группировать города по странам.
 *
 * @action init
 * @see register_taxonomy()
 */
add_action('init', function () {
    register_taxonomy('country', 'city', [
        'label' => __('Countries', 'storefront-child'),
        'hierarchical' => true,
        'show_in_rest' => true, // Для поддержки Gutenberg и REST API
    ]);
});

/**
 * Добавляет метабокс для ввода координат города (широта и долгота) на странице редактирования "Cities".
 *
 * @action add_meta_boxes
 * @see add_meta_box()
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'city_coords',             // ID метабокса
        __('Координаты города', 'storefront-child'), // Заголовок
        'city_coords_metabox_callback', // Функция вывода HTML
        'city',                    // CPT
        'normal',
        'high'
    );
});

/**
 * Выводит HTML для метабокса координат города.
 *
 * @param WP_Post $post Объект текущей записи (города).
 */
function city_coords_metabox_callback($post)
{
    // Получаем значения широты и долготы из метаполей
    $lat = get_post_meta($post->ID, 'latitude', true);
    $lon = get_post_meta($post->ID, 'longitude', true);
    ?>
    <label>
        <?php _e('Широта (latitude):', 'storefront-child'); ?>
        <input type="text" name="latitude" value="<?php echo esc_attr($lat); ?>">
    </label><br>
    <label>
        <?php _e('Долгота (longitude):', 'storefront-child'); ?>
        <input type="text" name="longitude" value="<?php echo esc_attr($lon); ?>">
    </label>
    <?php
}

/**
 * Сохраняет значения широты и долготы при сохранении записи "City".
 *
 * @param int $post_id ID текущей записи.
 * @action save_post_city
 */
add_action('save_post_city', function ($post_id) {
    if (array_key_exists('latitude', $_POST)) {
        update_post_meta($post_id, 'latitude', sanitize_text_field($_POST['latitude']));
    }
    if (array_key_exists('longitude', $_POST)) {
        update_post_meta($post_id, 'longitude', sanitize_text_field($_POST['longitude']));
    }
});

/**
 * Подключает PHP-файл с определением виджета "City Temperature Widget".
 */
require_once get_stylesheet_directory() . '/widgets/class-city-temperature-widget.php';

/**
 * Регистрирует обработчик AJAX-запроса поиска городов по названию для таблицы на фронте.
 *
 * @action wp_ajax_search_cities
 * @action wp_ajax_nopriv_search_cities
 */
add_action('wp_ajax_search_cities', 'ajax_search_cities');
add_action('wp_ajax_nopriv_search_cities', 'ajax_search_cities');

/**
 * AJAX-обработчик поиска городов по названию.
 * Возвращает строки <tr> с городом, страной и температурой для подстановки в таблицу.
 *
 * @global wpdb $wpdb Глобальный объект базы данных WordPress.
 */
function ajax_search_cities()
{
    global $wpdb;
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $like = '%' . $wpdb->esc_like($search) . '%';

    // SQL-запрос: получает города с координатами и страной по части названия
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_title, pm1.meta_value as latitude, pm2.meta_value as longitude, t.name as country
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm1 ON (p.ID = pm1.post_id AND pm1.meta_key = 'latitude')
        LEFT JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = 'longitude')
        LEFT JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
        LEFT JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
        LEFT JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
        WHERE p.post_type = 'city' AND p.post_status = 'publish'
        AND p.post_title LIKE %s
    ", $like));

    $apiKey = OPENWEATHERMAP_API_KEY;

    foreach ($results as $row) {
        $temp = '';
        // Получаем температуру города с помощью API OpenWeatherMap, если заданы координаты
        if ($row->latitude && $row->longitude) {
            $url = "https://api.openweathermap.org/data/2.5/weather?lat={$row->latitude}&lon={$row->longitude}&units=metric&appid=$apiKey";
            $response = wp_remote_get($url);
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data['main']['temp'])) {
                    $temp = $data['main']['temp'] . '°C';
                }
            }
        }
        echo '<tr>';
        echo '<td>' . esc_html($row->country) . '</td>';
        echo '<td>' . esc_html($row->post_title) . '</td>';
        echo '<td>' . esc_html($temp) . '</td>';
        echo '</tr>';
    }
    wp_die();
}

/**
 * Кастомные хуки для расширения функционала до и после таблицы на кастомном шаблоне.
 *
 * @hook cities_table_before
 * @hook cities_table_after
 */
do_action('cities_table_before');
// (Таблица будет выведена в кастомном шаблоне page-cities-table.php)
do_action('cities_table_after');

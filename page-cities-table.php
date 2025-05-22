<?php
/**
 * Template Name: Cities Table
 *
 * Кастомный шаблон для вывода таблицы стран, городов и текущей температуры.
 * Данные городов берутся из пользовательского типа записи "Cities" и таксономии "Countries".
 * Температура подгружается через API OpenWeatherMap на сервере.
 * Есть ajax-поиск по городам без перезагрузки страницы.
 *
 * @package storefront-child
 */

get_header();

/**
 * Кастомный action hook для вывода произвольного содержимого перед таблицей.
 * Можно использовать для баннеров, пояснений, фильтров и т.д.
 *
 * @hook cities_table_before
 */
do_action('cities_table_before');
?>

<div>
    <input type="text" id="city-search" placeholder="<?php esc_attr_e('Поиск по городам...', 'storefront-child'); ?>">
    <table id="cities-table">
        <thead>
            <tr>
                <th><?php _e('Страна', 'storefront-child'); ?></th>
                <th><?php _e('Город', 'storefront-child'); ?></th>
                <th><?php _e('Температура', 'storefront-child'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            /**
             * Выводим список городов, стран и температур на странице.
             * Данные берутся напрямую из БД с помощью $wpdb (требование задания).
             *
             * @global wpdb $wpdb
             */
            global $wpdb;

            // Получаем все города с их координатами и страной
            $results = $wpdb->get_results("
                SELECT p.ID, p.post_title, pm1.meta_value as latitude, pm2.meta_value as longitude, t.name as country
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm1 ON (p.ID = pm1.post_id AND pm1.meta_key = 'latitude')
                LEFT JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = 'longitude')
                LEFT JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
                LEFT JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
                LEFT JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
                WHERE p.post_type = 'city' AND p.post_status = 'publish'
            ");

            // Берём API-ключ из константы (определён в functions.php)
            $apiKey = OPENWEATHERMAP_API_KEY;

            foreach ($results as $row) {
                $temp = '';
                // Если есть координаты — делаем запрос к OpenWeatherMap
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
                // Выводим строку таблицы
                echo '<tr>';
                echo '<td>' . esc_html($row->country) . '</td>';
                echo '<td>' . esc_html($row->post_title) . '</td>';
                echo '<td>' . esc_html($temp) . '</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<?php
/**
 * Кастомный action hook для вывода содержимого после таблицы.
 *
 * @hook cities_table_after
 */
do_action('cities_table_after');
?>

<!-- 
    Скрипт для ajax-поиска по городам.
    При вводе в поле поиска отправляется запрос в админку WordPress,
    результатом возвращаются строки <tr> для обновления таблицы.
-->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('city-search');
    const tableBody = document.querySelector('#cities-table tbody');
    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function() {
            const value = searchInput.value;
            const formData = new FormData();
            formData.append('action', 'search_cities');
            formData.append('search', value);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                tableBody.innerHTML = html;
            })
            .catch(error => {
                console.error('AJAX error:', error);
            });
        });
    }
});
</script>

<?php
get_footer();
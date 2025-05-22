<?php
/**
 * Виджет "City Temperature Widget".
 *
 * Позволяет выбрать город из CPT "Cities" и отображает его текущую температуру через OpenWeatherMap.
 *
 * @package storefront-child
 */

/**
 * Class City_Temperature_Widget
 *
 * Виджет WordPress для вывода температуры выбранного города.
 */
class City_Temperature_Widget extends WP_Widget
{
    /**
     * Конструктор. Регистрирует виджет в WordPress.
     */
    public function __construct()
    {
        parent::__construct(
            'city_temperature_widget', // ID виджета
            __('City Temperature Widget', 'storefront-child'), // Название в админке
            [
                'description' => __('Выберите город и отобразите его температуру через OpenWeatherMap.', 'storefront-child'),
            ]
        );
    }

    /**
     * Вывод формы настройки виджета в админке WordPress.
     *
     * @param array $instance Текущие значения настроек виджета.
     */
    public function form($instance)
    {
        // ID выбранного города, если установлен
        $selected_city = $instance['city_id'] ?? '';

        // Получаем все города (CPT "city")
        $cities = get_posts([
            'post_type'   => 'city',
            'numberposts' => -1,
        ]);
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('city_id'); ?>">
                <?php _e('Город:', 'storefront-child'); ?>
            </label>
            <select name="<?php echo $this->get_field_name('city_id'); ?>" id="<?php echo $this->get_field_id('city_id'); ?>">
                <?php foreach ($cities as $city): ?>
                    <option value="<?php echo $city->ID; ?>" <?php selected($selected_city, $city->ID); ?>>
                        <?php echo esc_html($city->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    /**
     * Выводит сам виджет на фронтенде сайта.
     *
     * @param array $args     Аргументы WordPress (до/после виджета, до/после заголовка).
     * @param array $instance Настройки виджета.
     */
    public function widget($args, $instance)
    {
        $city_id = $instance['city_id'] ?? 0;
        if (!$city_id) {
            // Город не выбран — ничего не выводим
            return;
        }

        $title = get_the_title($city_id);
        $lat   = get_post_meta($city_id, 'latitude', true);
        $lon   = get_post_meta($city_id, 'longitude', true);

        if ($lat && $lon) {
            // Получаем API-ключ из константы
            $apiKey = OPENWEATHERMAP_API_KEY;
            // Формируем URL для запроса к OpenWeatherMap
            $url = "https://api.openweathermap.org/data/2.5/weather?lat=$lat&lon=$lon&units=metric&appid=$apiKey";
            $response = wp_remote_get($url);
            $temp = '';

            // Обрабатываем ответ от API
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data['main']['temp'])) {
                    $temp = $data['main']['temp'] . '°C';
                }
            }

            // Выводим результат
            echo "<div class='city-temp-widget'><strong>" . esc_html($title) . "</strong>: " . esc_html($temp) . "</div>";
        }
    }

    /**
     * Сохраняет настройки виджета при обновлении в админке.
     *
     * @param array $new_instance Новые значения.
     * @param array $old_instance Старые значения.
     * @return array
     */
    public function update($new_instance, $old_instance)
    {
        // Валидация поля city_id
        $instance = [];
        $instance['city_id'] = isset($new_instance['city_id']) ? intval($new_instance['city_id']) : '';
        return $instance;
    }
}

/**
 * Регистрирует виджет City_Temperature_Widget в WordPress.
 *
 * @action widgets_init
 */
add_action('widgets_init', function () {
    register_widget('City_Temperature_Widget');
});

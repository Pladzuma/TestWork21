# TestWork21 (тестовое на WordPress)

## Установка
1. Установите WordPress.
2. Установите тему Storefront через админку.
3. Скопируйте папку storefront-child в /wp-content/themes/
4. Активируйте Storefront Child.
5. Получите API-ключ на https://openweathermap.org/api и пропишите его в коде.
6. В админке появится “Cities” и “Countries”.
7. Создайте страницу и выберите шаблон “Cities Table”.

## Структура:
- Custom Post Type: Cities
- Taxonomy: Countries
- Метаполя: latitude, longitude
- Виджет: City Temperature Widget
- Страница: Таблица городов/стран/температур, ajax-поиск
<?php
/**
 * ============================================================================
 * МОДУЛЬ: РАСЧЕТЫ ТОВАРОВ (КРИТИЧЕСКИЙ)
 * ============================================================================
 * 
 * Функции расчета площади, цен, множителей для товаров.
 * 
 * @package ParusWeb_Functions
 * @subpackage Core
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// ИЗВЛЕЧЕНИЕ ПЛОЩАДИ ИЗ НАЗВАНИЯ ТОВАРА
// ============================================================================
/**
 * Извлечь площадь упаковки из названия или атрибутов товара
 * 
 * @param string $title Название товара
 * @param int $product_id ID товара
 * @return float|null Площадь в м² или null
 */
function extract_area_with_qty($title, $product_id = null) {
// Сначала пытаемся получить из атрибутов WooCommerce
    if ($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            // Получаем атрибуты товара разными способами
            $shirina = '';
            $dlina = '';
            
            // Способ 1: через get_attribute (возвращает label/name термина)
            $shirina = $product->get_attribute('pa_shirina');
            if (!$shirina) {
                $shirina = $product->get_attribute('shirina');
            }
            
            $dlina = $product->get_attribute('pa_dlina');
            if (!$dlina) {
                $dlina = $product->get_attribute('dlina');
            }
            
            // Способ 2: если не нашли, пробуем через get_attributes и берем slug
            if (!$dlina) {
                $attributes = $product->get_attributes();
                if (isset($attributes['pa_dlina'])) {
                    $attr = $attributes['pa_dlina'];
                    if ($attr->is_taxonomy()) {
                        $terms = wp_get_post_terms($product_id, 'pa_dlina');
                        if (!empty($terms) && !is_wp_error($terms)) {
                            $dlina = $terms[0]->name; // Берем name, не slug
                        }
                    }
                } elseif (isset($attributes['dlina'])) {
                    $dlina = $attributes['dlina']->get_options()[0] ?? '';
                }
            }
            
            if (!$shirina) {
                $attributes = $product->get_attributes();
                if (isset($attributes['pa_shirina'])) {
                    $attr = $attributes['pa_shirina'];
                    if ($attr->is_taxonomy()) {
                        $terms = wp_get_post_terms($product_id, 'pa_shirina');
                        if (!empty($terms) && !is_wp_error($terms)) {
                            $shirina = $terms[0]->name;
                        }
                    }
                } elseif (isset($attributes['shirina'])) {
                    $shirina = $attributes['shirina']->get_options()[0] ?? '';
                }
            }
            
            // Если нашли оба атрибута
            if ($shirina && $dlina) {
                // Очищаем строки от лишних символов
                $shirina = trim($shirina);
                $dlina = trim($dlina);
                
                // Извлекаем числовые значения из строк
                preg_match('/(\d+(?:[.,]\d+)?)/', $shirina, $width_match);
                preg_match('/(\d+(?:[.,]\d+)?)/', $dlina, $length_match);
                
                if (!empty($width_match[1]) && !empty($length_match[1])) {
                    // Заменяем запятую на точку для корректного преобразования
                    $width_mm = floatval(str_replace(',', '.', $width_match[1]));
                    $length_value = floatval(str_replace(',', '.', $length_match[1]));
                    
                    // Определяем единицы измерения длины
                    $length_mm = $length_value;
                    
                    // Ищем указание на метры в разных форматах
                    // Поддержка: "3 м", "3м", "3 m", "3-m", "3_м"
                    if (preg_match('/м|m/ui', $dlina)) {
                        // Длина указана в метрах - конвертируем в мм
                        $length_mm = $length_value * 1000;
                    } elseif ($length_value < 50) {
                        // Если число меньше 50 и нет явного указания единиц, скорее всего метры
                        $length_mm = $length_value * 1000;
                    }
                    // Иначе считаем, что уже в мм (например 3000)
                    
                    // Вычисляем площадь одной штуки в м²
                    $area_m2 = ($width_mm / 1000) * ($length_mm / 1000);
                    
                    // Проверяем, если площадь слишком мала (меньше 0.01), возможно ошибка в единицах
                    if ($area_m2 < 0.01) {
                        // Попробуем другой вариант: возможно размеры уже в метрах
                        $area_m2 = $width_mm * $length_value;
                    }
                    
                    return round($area_m2, 3);
                }
            }
        }
    }
    
    // Если атрибуты не найдены, пытаемся извлечь из названия
    // Формат: "Название 1.44 м² кол-во 10 шт" или "10x144"
    if (preg_match('/(\d+(?:\.\d+)?)\s*(?:м²|m2|м2)/ui', $title, $matches)) {
        return floatval($matches[1]);
    }
    
    // Формат: "20x1.44" или "20 x 1.44"
    if (preg_match('/(\d+)\s*[xх×]\s*(\d+(?:\.\d+)?)/ui', $title, $matches)) {
        $qty = intval($matches[1]);
        $area_per_item = floatval($matches[2]);
        return $area_per_item; // Возвращаем площадь одной штуки
    }
    
// Формат: "Название 140×22×4000" (ширина×высота×длина в мм)
    if (preg_match('/(\d+(?:\.\d+)?)\s*[×\*хx]\s*(\d+(?:\.\d+)?)\s*[×\*хx]\s*(\d+(?:\.\d+)?)/ui', $title, $matches)) {
        $dim1 = floatval($matches[1]);
        $dim2 = floatval($matches[2]);
        $dim3 = floatval($matches[3]);
        
        // Логика: самый большой размер - длина, самый маленький - высота, средний - ширина
        $dims = [$dim1, $dim2, $dim3];
        sort($dims); // Сортируем по возрастанию
        
        $height_mm = $dims[0];  // Минимальный - высота (толщина)
        $width_mm = $dims[1];   // Средний - ширина
        $length_mm = $dims[2];  // Максимальный - длина
        
        // Если длина в метрах (< 50)
        if ($length_mm < 50) {
            $length_mm = $length_mm * 1000;
        }
        
        // Площадь = ширина × длина (высоту не учитываем)
        $area_m2 = ($width_mm / 1000) * ($length_mm / 1000);
        return round($area_m2, 3);
    }
    
    return null;
}

// ============================================================================
// МНОЖИТЕЛИ ЦЕН
// ============================================================================

/**
 * Получить множитель цены для товара или категории
 */
function get_price_multiplier($product_id) {
    // Проверяем множитель товара
    $product_multiplier = get_post_meta($product_id, '_price_multiplier', true);
    if (!empty($product_multiplier) && is_numeric($product_multiplier)) {
        return floatval($product_multiplier);
    }
    
    // Проверяем категории
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    if (!is_wp_error($product_categories) && !empty($product_categories)) {
        foreach ($product_categories as $cat_id) {
            $cat_multiplier = get_term_meta($cat_id, 'category_price_multiplier', true);
            if (!empty($cat_multiplier) && is_numeric($cat_multiplier)) {
                return floatval($cat_multiplier);
            }
        }
    }
    
    return 1.0;
}

// ============================================================================
// ИЗВЛЕЧЕНИЕ РАЗМЕРОВ ИЗ НАЗВАНИЯ
// ============================================================================

/**
 * Извлечь размеры из названия товара или атрибутов WC
 * 
 * @param string $title Название товара
 * @param int $product_id ID товара (опционально)
 * @return array|null Массив с ключами widths, length_min, length_max или null
 */
function extract_dimensions_from_title($title, $product_id = null) {
    $result = null;
    
    // Сначала пытаемся получить из атрибутов WooCommerce
    if ($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            // Получаем атрибуты товара
            $shirina = $product->get_attribute('pa_shirina') ?: $product->get_attribute('shirina');
            $dlina = $product->get_attribute('pa_dlina') ?: $product->get_attribute('dlina');
            
            if ($shirina && $dlina) {
                // Извлекаем числовые значения
                preg_match('/(\d+(?:\.\d+)?)/', $shirina, $width_match);
                preg_match('/(\d+(?:\.\d+)?)/', $dlina, $length_match);
                
                if (!empty($width_match[1]) && !empty($length_match[1])) {
                    $width = floatval($width_match[1]);
                    $length = floatval($length_match[1]);
                    
                    // Если длина в метрах (< 50), конвертируем в мм
                    if ($length < 50) {
                        $length = $length * 1000;
                    }
                    
                    return [
                        'widths' => [$width],
                        'length_min' => $length,
                        'length_max' => $length
                    ];
                }
            }
        }
    }
    
    // Если атрибуты не найдены, пытаемся извлечь из названия
    if (preg_match('/\d+\/(\d+)(?:\*|х|x)(\d+(?:\.\d+)?)/ui', $title, $matches)) {
        $width = intval($matches[1]);
        $length = floatval($matches[2]);
        
        if ($length < 50) {
            $length = $length * 1000;
        }
        
        $result = [
            'widths' => [$width],
            'length_min' => $length,
            'length_max' => $length
        ];
    }
    elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:\*|х|x)\s*(\d+(?:\.\d+)?)\s*(?:\*|х|x)\s*(\d+(?:\.\d+)?)/ui', $title, $matches)) {
        $width = floatval($matches[1]);
        $height = floatval($matches[2]);
        $length = floatval($matches[3]);
        
        if ($width > 1000) {
            $temp = $width;
            $width = $length;
            $length = $temp;
        }
        
        if ($length < 50) {
            $length = $length * 1000;
        }
        
        $result = [
            'widths' => [$width],
            'length_min' => $length,
            'length_max' => $length
        ];
    }
    elseif (preg_match('/(\d+)\s*(?:\*|х|x)\s*(\d+(?:\.\d+)?)/ui', $title, $matches)) {
        $width = intval($matches[1]);
        $length = floatval($matches[2]);
        
        if ($length < 50) {
            $length = $length * 1000;
        }
        
        $result = [
            'widths' => [$width],
            'length_min' => $length,
            'length_max' => $length
        ];
    }
    
    return $result;
}

// ============================================================================
// РАСЧЕТ МИНИМАЛЬНОЙ ЦЕНЫ ДЛЯ ОТОБРАЖЕНИЯ
// ============================================================================

/**
 * Рассчитать минимальную цену товара для превью
 */
function calculate_min_price($product_id, $base_price) {
    $type = parusweb_get_product_type($product_id);
    
    switch ($type) {
        case 'partition_slat':
            return calculate_min_price_partition_slat($product_id, $base_price);
            
        case 'running_meter':
            return calculate_min_price_running_meter($product_id, $base_price);
            
        case 'square_meter':
            return calculate_min_price_square_meter($product_id, $base_price);
            
        case 'multiplier':
            return calculate_min_price_multiplier($product_id, $base_price);
            
        default:
            return $base_price;
    }
}

/**
 * Минимальная цена для реечных перегородок
 */
function calculate_min_price_partition_slat($product_id, $base_price_per_m2) {
    $min_width = 30; // мм
    $min_length = 3; // м
    $multiplier = get_price_multiplier($product_id);
    
    $min_area = ($min_width / 1000) * $min_length;
    return $base_price_per_m2 * $min_area * $multiplier;
}

/**
 * Минимальная цена для погонных метров
 */
function calculate_min_price_running_meter($product_id, $base_price_per_m) {
    $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 1;
    $multiplier = get_price_multiplier($product_id);
    
    return $base_price_per_m * $min_length * $multiplier;
}

/**
 * Минимальная цена для квадратных метров
 */
function calculate_min_price_square_meter($product_id, $base_price_per_m2) {
    $is_falsebalk = product_in_category($product_id, 266);
    $multiplier = get_price_multiplier($product_id);
    
    if ($is_falsebalk) {
        $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true)) ?: 70;
        $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 1;
        $min_area = 2 * ($min_width / 1000) * $min_length;
    } else {
        $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true)) ?: 100;
        $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 0.01;
        $min_area = ($min_width / 1000) * $min_length;
    }
    
    return $base_price_per_m2 * $min_area * $multiplier;
}

/**
 * Минимальная цена для товаров с множителем
 */
function calculate_min_price_multiplier($product_id, $base_price_per_m2) {
    $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true));
    $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true));
    $multiplier = get_price_multiplier($product_id);
    
    // Для штакетника фиксированная ширина
    if (has_term(273, 'product_cat', $product_id)) {
        $min_width = 95;
    }
    
    if (!$min_width || $min_width <= 0) $min_width = 100;
    if (!$min_length || $min_length <= 0) $min_length = 0.01;
    
    $min_area = ($min_width / 1000) * $min_length;
    $min_price = $base_price_per_m2 * $min_area * $multiplier;
    
    // Добавляем цену формы для штакетника
    if (has_term(273, 'product_cat', $product_id)) {
        $flat_shape_price = floatval(get_post_meta($product_id, '_shape_price_flat', true)) ?: 0;
        $min_price += $flat_shape_price;
    }
    
    return $min_price;
}

// ============================================================================
// ФОРМАТИРОВАНИЕ ЦЕН
// ============================================================================

/**
 * Форматировать цену с валютой
 */
function parusweb_format_price($price) {
    return wc_price($price);
}

/**
 * Форматировать число
 */
function parusweb_format_number($number, $decimals = 2) {
    return number_format($number, $decimals, ',', ' ');
}

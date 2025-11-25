<?php
/**
 * ============================================================================
 * МОДУЛЬ: ФОРМАТИРОВАНИЕ ЦЕН
 * ============================================================================
 * 
 * Изменение отображения цен в зависимости от типа товара.
 * 
 * @package ParusWeb_Functions
 * @subpackage Display
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// ФИЛЬТР ОТОБРАЖЕНИЯ ЦЕН
// ============================================================================

add_filter('woocommerce_get_price_html', 'parusweb_format_product_price', 20, 2);

function parusweb_format_product_price($price, $product) {
    $product_id = $product->get_id();
    
    // Сначала проверяем категорию ДПК и МПК (197)
    if (!function_exists('has_term_or_parent_dpk')) {
        function has_term_or_parent_dpk($parent_term_id, $taxonomy, $product_id) {
            if (has_term($parent_term_id, $taxonomy, $product_id)) {
                return true;
            }
            $terms = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'ids'));
            if (is_wp_error($terms) || empty($terms)) {
                return false;
            }
            foreach ($terms as $term_id) {
                if (term_is_ancestor_of($parent_term_id, $term_id, $taxonomy)) {
                    return true;
                }
            }
            return false;
        }
    }
    
    $is_dpk_mpk = has_term_or_parent_dpk(197, 'product_cat', $product_id);
    if ($is_dpk_mpk) {
        return format_dpk_mpk_price($product, $product_id);
    }
    
    // Проверяем целевые категории
    if (!is_in_target_categories($product_id)) {
        return $price;
    }
    
    $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
    $type = parusweb_get_product_type($product_id);
    
    // Категории для скрытия базовой цены
    $hide_base_price_categories = range(265, 271);
    $should_hide_base_price = has_term($hide_base_price_categories, 'product_cat', $product_id);
    
    // Форматирование в зависимости от типа
    switch ($type) {
        case 'partition_slat':
            return format_partition_slat_price($product_id, $base_price_m2, $should_hide_base_price);
            
        case 'running_meter':
            return format_running_meter_price($product_id, $base_price_m2, $should_hide_base_price);
            
        case 'square_meter':
            return format_square_meter_price($product_id, $base_price_m2, $should_hide_base_price);
            
        case 'multiplier':
            return format_multiplier_price($product_id, $base_price_m2, $should_hide_base_price);
            
        case 'target':
            return format_target_price($product, $product_id, $base_price_m2);
            
        case 'liter':
            return format_liter_price($price);
            
        default:
            return $price;
    }
}

// ============================================================================
// ФОРМАТИРОВАНИЕ ПО ТИПАМ
// ============================================================================

function format_dpk_mpk_price($product, $product_id) {
    $price_per_m2 = floatval($product->get_regular_price() ?: $product->get_price());
    
    // Получаем площадь одной доски из названия
    $title = $product->get_name();
    $board_area = extract_area_with_qty($title, $product_id);
    
    if (!$board_area || $board_area <= 0) {
        // Если не удалось извлечь площадь, показываем просто цену за м²
        if (is_product()) {
            return '<span style="font-size:1.3em;"><strong>' . wc_price($price_per_m2) . '</strong> за м²</span>';
        } else {
            return '<span style="font-size:1.1em;"><strong>' . wc_price($price_per_m2) . '</strong> за м²</span>';
        }
    }
    
    // Рассчитываем цену за штуку
    $price_per_piece = $price_per_m2 * $board_area;
    
    if (is_product()) {
        // На странице товара: показываем цену за штуку крупно + цену за м² мелко
        return '<span style="font-size:1.3em;"><strong>' . wc_price($price_per_piece) . '</strong> за шт.</span><br>' .
               '<span style="font-size:0.9em; color:#666;">(' . wc_price($price_per_m2) . ' за м²)</span>';
    } else {
        // В архиве/категории: компактный вариант
        return '<span style="font-size:1.1em;"><strong>' . wc_price($price_per_piece) . '</strong> за шт.</span><br>' .
               '<span style="font-size:0.85em; color:#666;">(' . wc_price($price_per_m2) . ' за м²)</span>';
    }
}

function format_partition_slat_price($product_id, $base_price_per_m2, $hide_base) {
    $min_price = calculate_min_price_partition_slat($product_id, $base_price_per_m2);
    
    if (is_product()) {
        if ($hide_base) {
            return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт.</span>';
        }
        return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт.</span>';
    } else {
        if ($hide_base) {
            return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
        }
        return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
    }
}

function format_running_meter_price($product_id, $base_price_per_m, $hide_base) {
    $min_price = calculate_min_price_running_meter($product_id, $base_price_per_m);
    $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 1;
    
    if (is_product()) {
        if ($hide_base) {
            return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт.</span>';
        }
        return wc_price($base_price_per_m) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт.</span>';
    } else {
        if ($hide_base) {
            return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
        }
        return wc_price($base_price_per_m) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
    }
}

function format_square_meter_price($product_id, $base_price_per_m2, $hide_base) {
    $min_price = calculate_min_price_square_meter($product_id, $base_price_per_m2);
    $min_area = ($min_price / $base_price_per_m2) / get_price_multiplier($product_id);
    
    if (is_product()) {
        if ($hide_base) {
            return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . number_format($min_area, 2) . ' м²)</span>';
        }
        return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . number_format($min_area, 2) . ' м²)</span>';
    } else {
        if ($hide_base) {
            return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
        }
        return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
    }
}

function format_multiplier_price($product_id, $base_price_per_m2, $hide_base) {
    $min_price = calculate_min_price_multiplier($product_id, $base_price_per_m2);
    $multiplier = get_price_multiplier($product_id);
    $min_area = ($min_price / $base_price_per_m2) / $multiplier;
    
    if (is_product()) {
        if ($hide_base) {
            return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . number_format($min_area, 3) . ' м²)</span>';
        }
        return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . number_format($min_area, 3) . ' м²)</span>';
    } else {
        if ($hide_base) {
            return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
        }
        return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
    }
}

function format_target_price($product, $product_id, $base_price_m2) {
    $pack_area = extract_area_with_qty($product->get_name(), $product_id);
    $is_leaf = is_leaf_category($product_id);
    
    if (!$pack_area) {
        return wc_price($base_price_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span>';
    }
    
    $price_per_pack = $base_price_m2 * $pack_area;
    $unit_text = $is_leaf ? 'лист' : 'упаковку';
    
    if (is_product()) {
        return wc_price($base_price_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:1.3em;"><strong>' . wc_price($price_per_pack) . '</strong> за 1 ' . $unit_text . '</span>';
    } else {
        return wc_price($base_price_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
               '<span style="font-size:1.1em;"><strong>' . wc_price($price_per_pack) . '</strong> за ' . $unit_text . '</span>';
    }
}

function format_liter_price($price) {
    if (strpos($price, 'за литр') !== false) {
        return $price;
    }
    
    if (preg_match('/(.*)<\/span>(.*)$/i', $price, $matches)) {
        return $matches[1] . '/литр</span>' . $matches[2];
    }
    
    return $price . ' за литр';
}

// ============================================================================
// КОНЕЦ ФАЙЛА
// ============================================================================
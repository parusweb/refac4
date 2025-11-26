<?php
/**
 * ============================================================================
 * МОДУЛЬ: ОТОБРАЖЕНИЕ В КОРЗИНЕ
 * ============================================================================
 * 
 * @package ParusWeb_Functions
 * @subpackage Cart
 * @version 2.0.1
 */

if (!defined('ABSPATH')) exit;

add_filter('woocommerce_get_item_data', 'parusweb_display_calculator_data_in_cart', 10, 2);

function parusweb_display_calculator_data_in_cart($item_data, $cart_item) {
    
    if (!empty($cart_item['pm_selected_scheme_name'])) {
        $item_data[] = [
            'name' => 'Схема покраски',
            'value' => $cart_item['pm_selected_scheme_name']
        ];
    }
    
    if (!empty($cart_item['pm_selected_color'])) {
        $color_display = $cart_item['pm_selected_color'];
        
        if (!empty($cart_item['pm_selected_color_image'])) {
            $image_url = $cart_item['pm_selected_color_image'];
            $filename = !empty($cart_item['pm_selected_color_filename']) ? $cart_item['pm_selected_color_filename'] : '';
            
            $color_display = '<div style="display:flex; align-items:center; gap:10px;">';
            $color_display .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($filename) . '" style="width:40px; height:40px; object-fit:cover; border:2px solid #ddd; border-radius:4px;">';
            $color_display .= '<div>';
            $color_display .= '<div>' . esc_html($cart_item['pm_selected_color']) . '</div>';
            if ($filename) {
                $color_display .= '<div style="font-size:11px; color:#999;">Код: ' . esc_html($filename) . '</div>';
            }
            $color_display .= '</div>';
            $color_display .= '</div>';
        }
        
        $item_data[] = [
            'name' => 'Цвет',
            'value' => $color_display
        ];
    }
    
    if (isset($cart_item['custom_area_calc'])) {
        $area_calc = $cart_item['custom_area_calc'];
        $is_leaf_category = $area_calc['is_leaf'];
        $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
        
        $plural = ($area_calc['packs'] % 10 === 1 && $area_calc['packs'] % 100 !== 11) ? $unit_forms[0] :
                  (($area_calc['packs'] % 10 >= 2 && $area_calc['packs'] % 10 <= 4 && ($area_calc['packs'] % 100 < 10 || $area_calc['packs'] % 100 >= 20)) ? $unit_forms[1] : $unit_forms[2]);
        
        $base_price = isset($area_calc['base_price']) ? $area_calc['base_price'] : $area_calc['total_price'];
        $display_text = $area_calc['area'] . ' м² (' . $area_calc['packs'] . ' ' . $plural . ') — ' . number_format($base_price, 2, '.', ' ') . ' ₽';
        
        $item_data[] = [
            'name' => 'Выбранная площадь',
            'value' => $display_text
        ];
        
        if (isset($area_calc['painting_service']) && !empty($area_calc['painting_service'])) {
            $painting = $area_calc['painting_service'];
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽'
            ];
        }
    }
    
    if (isset($cart_item['custom_dimensions'])) {
        $dims = $cart_item['custom_dimensions'];
        $area = ($dims['width'] / 1000) * ($dims['length'] / 1000);
        
        $base_price = isset($dims['base_price']) ? $dims['base_price'] : $dims['price'];
        $display_text = $dims['width'] . ' мм × ' . $dims['length'] . ' мм (' . round($area, 3) . ' м²)<br>';
        
        // Добавляем фаску если есть (с превью изображения)
        if (isset($dims['faska_name']) && !empty($dims['faska_name'])) {
            $display_text .= '<div style="display: flex; align-items: center; gap: 10px; margin: 10px 0;">';
            
            if (isset($dims['faska_image']) && !empty($dims['faska_image'])) {
                $display_text .= '<img src="' . esc_url($dims['faska_image']) . '" alt="' . esc_attr($dims['faska_name']) . '" style="width: 60px; height: 60px; object-fit: contain; border: 2px solid #ddd; border-radius: 4px;">';
            }
            
            $display_text .= '<div><strong>Фаска:</strong> ' . esc_html($dims['faska_name']) . '</div>';
            $display_text .= '</div>';
        }
        
        $display_text .= 'Стоимость: ' . number_format($base_price, 2, '.', ' ') . ' ₽';
        
        $item_data[] = [
            'name' => 'Размеры',
            'value' => $display_text
        ];
        
        if (isset($dims['painting_service']) && !empty($dims['painting_service'])) {
            $painting = $dims['painting_service'];
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽'
            ];
        }
    }
    
    if (isset($cart_item['custom_multiplier_calc'])) {
        $mult_calc = $cart_item['custom_multiplier_calc'];
        
        $base_price = isset($mult_calc['base_price']) ? $mult_calc['base_price'] : $mult_calc['price'];
        $display_text = 'Ширина: ' . $mult_calc['width'] . ' мм<br>';
        $display_text .= 'Длина: ' . $mult_calc['length'] . ' м<br>';
        $display_text .= 'Площадь: ' . round($mult_calc['area_per_item'], 3) . ' м²<br>';
        $display_text .= 'Стоимость товара: ' . number_format($base_price, 2, '.', ' ') . ' ₽';
        
        $item_data[] = [
            'name' => 'Параметры',
            'value' => $display_text
        ];
        
        if (isset($mult_calc['painting_service']) && !empty($mult_calc['painting_service'])) {
            $painting = $mult_calc['painting_service'];
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽'
            ];
        }
    }
    
    if (isset($cart_item['custom_running_meter_calc'])) {
        $rm_calc = $cart_item['custom_running_meter_calc'];
        
        // ВРЕМЕННАЯ ОТЛАДКА - удалите после проверки
        if (WP_DEBUG) {
            error_log('=== RUNNING METER DEBUG ===');
            error_log('RM_CALC: ' . print_r($rm_calc, true));
            if (isset($rm_calc['painting_service'])) {
                error_log('PAINTING_SERVICE: ' . print_r($rm_calc['painting_service'], true));
            } else {
                error_log('PAINTING_SERVICE: НЕТ ДАННЫХ!');
            }
        }
        
        $display_text = '<div style="line-height: 1.6;">';
        
        if (isset($rm_calc['shape_label']) && !empty($rm_calc['shape_label'])) {
            $display_text .= '<strong>Форма сечения:</strong> ' . esc_html($rm_calc['shape_label']) . '<br>';
        }
        
        $display_text .= '<strong>Размеры:</strong><br>';
        
        if (isset($rm_calc['width']) && $rm_calc['width'] > 0) {
            $display_text .= '&nbsp;&nbsp;• Ширина: ' . esc_html($rm_calc['width']) . ' мм<br>';
        }
        
        if (isset($rm_calc['height']) && $rm_calc['height'] > 0) {
            $display_text .= '&nbsp;&nbsp;• Высота: ' . esc_html($rm_calc['height']) . ' мм<br>';
        }
        
        if (isset($rm_calc['height2']) && $rm_calc['height2'] > 0) {
            $display_text .= '&nbsp;&nbsp;• Высота 2: ' . esc_html($rm_calc['height2']) . ' мм<br>';
        }
        
        if (isset($rm_calc['length']) && $rm_calc['length'] > 0) {
            $display_text .= '&nbsp;&nbsp;• Длина: ' . esc_html($rm_calc['length']) . ' м<br>';
        }
        
        $base_price = isset($rm_calc['base_price']) ? $rm_calc['base_price'] : (isset($rm_calc['price']) ? $rm_calc['price'] : 0);
        $display_text .= '<strong>Стоимость материала:</strong> ' . number_format($base_price, 2, '.', ' ') . ' ₽<br>';
        
        // Добавляем стоимость покраски прямо в этот же блок
        if (isset($rm_calc['painting_service']) && !empty($rm_calc['painting_service'])) {
            $painting = $rm_calc['painting_service'];
            
            // Пробуем все возможные варианты получения стоимости
            $painting_cost = 0;
            if (isset($painting['total_cost']) && $painting['total_cost'] > 0) {
                $painting_cost = $painting['total_cost'];
            } elseif (isset($painting['total_price']) && $painting['total_price'] > 0) {
                $painting_cost = $painting['total_price'];
            } elseif (isset($painting['price']) && $painting['price'] > 0) {
                $painting_cost = $painting['price'];
            } elseif (isset($painting['cost']) && $painting['cost'] > 0) {
                $painting_cost = $painting['cost'];
            }
            
            // ВРЕМЕННАЯ ОТЛАДКА
            if (WP_DEBUG) {
                error_log('PAINTING_COST вычислено: ' . $painting_cost);
            }
            
            if ($painting_cost > 0) {
                $display_text .= '<strong>Стоимость покраски:</strong> ' . number_format($painting_cost, 2, '.', ' ') . ' ₽';
            }
        }
        
        $display_text .= '</div>';
        
        $item_data[] = [
            'name' => '',
            'value' => $display_text
        ];
    }
    
    if (isset($cart_item['custom_square_meter_calc'])) {
        $sq_calc = $cart_item['custom_square_meter_calc'];
        
        $base_price = isset($sq_calc['base_price']) ? $sq_calc['base_price'] : $sq_calc['price'];
        $display_text = 'Ширина: ' . $sq_calc['width'] . ' мм<br>';
        $display_text .= 'Длина: ' . $sq_calc['length'] . ' м<br>';
        $display_text .= 'Площадь: ' . round($sq_calc['area'], 3) . ' м²<br>';
        $display_text .= 'Стоимость товара: ' . number_format($base_price, 2, '.', ' ') . ' ₽';
        
        $item_data[] = [
            'name' => 'Параметры',
            'value' => $display_text
        ];
        
        if (isset($sq_calc['painting_service']) && !empty($sq_calc['painting_service'])) {
            $painting = $sq_calc['painting_service'];
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽'
            ];
        }
    }
    
    if (isset($cart_item['custom_partition_slat_calc'])) {
        $part_calc = $cart_item['custom_partition_slat_calc'];
        
        $base_price = isset($part_calc['base_price']) ? $part_calc['base_price'] : $part_calc['price'];
        $display_text = 'Ширина: ' . $part_calc['width'] . ' мм<br>';
        $display_text .= 'Длина: ' . $part_calc['length'] . ' м<br>';
        $display_text .= 'Толщина: ' . $part_calc['thickness'] . ' мм<br>';
        $display_text .= 'Объем: ' . round($part_calc['volume'], 4) . ' м³<br>';
        $display_text .= 'Стоимость товара: ' . number_format($base_price, 2, '.', ' ') . ' ₽';
        
        $item_data[] = [
            'name' => 'Параметры',
            'value' => $display_text
        ];
        
        if (isset($part_calc['painting_service']) && !empty($part_calc['painting_service'])) {
            $painting = $part_calc['painting_service'];
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽'
            ];
        }
    }
    
    if (isset($cart_item['card_pack_purchase'])) {
        $pack_data = $cart_item['card_pack_purchase'];
        
        $base_price = isset($pack_data['base_price']) ? $pack_data['base_price'] : $pack_data['total_price'];
        $display_text = 'Площадь: ' . $pack_data['area'] . ' м²<br>';
        $display_text .= 'Цена за м²: ' . number_format($pack_data['price_per_m2'], 2, '.', ' ') . ' ₽<br>';
        $display_text .= 'Стоимость: ' . number_format($base_price, 2, '.', ' ') . ' ₽';
        
        $item_data[] = [
            'name' => 'В корзине ' . $pack_data['unit_type'],
            'value' => $display_text
        ];
        
        if (isset($pack_data['painting_service']) && !empty($pack_data['painting_service'])) {
            $painting = $pack_data['painting_service'];
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽'
            ];
        }
    }
    
    if (isset($cart_item['standard_pack_purchase'])) {
        $pack_data = $cart_item['standard_pack_purchase'];
        
        $base_price = isset($pack_data['base_price']) ? $pack_data['base_price'] : $pack_data['total_price'];
        $display_text = 'Площадь: ' . $pack_data['area'] . ' м²<br>';
        $display_text .= 'Цена за м²: ' . number_format($pack_data['price_per_m2'], 2, '.', ' ') . ' ₽<br>';
        $display_text .= 'Стоимость: ' . number_format($base_price, 2, '.', ' ') . ' ₽';
        
        $item_data[] = [
            'name' => 'В корзине ' . $pack_data['unit_type'],
            'value' => $display_text
        ];
        
        if (isset($pack_data['painting_service']) && !empty($pack_data['painting_service'])) {
            $painting = $pack_data['painting_service'];
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽'
            ];
        }
    }
    
    return $item_data;
}

add_filter('woocommerce_cart_item_price', 'parusweb_format_cart_item_price', 10, 3);

function parusweb_format_cart_item_price($price, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $product_id = $product->get_id();
    
    if (!is_in_target_categories($product_id)) {
        return $price;
    }
    
    if (isset($cart_item['card_pack_purchase']) || 
        isset($cart_item['custom_area_calc']) || 
        isset($cart_item['custom_dimensions']) ||
        isset($cart_item['custom_multiplier_calc']) ||
        isset($cart_item['custom_running_meter_calc']) ||
        isset($cart_item['custom_square_meter_calc']) ||
        isset($cart_item['custom_partition_slat_calc'])) {
        
        $current_price = floatval($product->get_price());
        $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
        
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
        $unit_text = $is_leaf_category ? 'лист' : 'упаковку';
        
        $has_painting = false;
        if (isset($cart_item['custom_area_calc']) && !empty($cart_item['custom_area_calc']['painting_service'])) $has_painting = true;
        if (isset($cart_item['custom_dimensions']) && !empty($cart_item['custom_dimensions']['painting_service'])) $has_painting = true;
        if (isset($cart_item['custom_multiplier_calc']) && !empty($cart_item['custom_multiplier_calc']['painting_service'])) $has_painting = true;
        if (isset($cart_item['custom_running_meter_calc']) && !empty($cart_item['custom_running_meter_calc']['painting_service'])) $has_painting = true;
        if (isset($cart_item['custom_square_meter_calc']) && !empty($cart_item['custom_square_meter_calc']['painting_service'])) $has_painting = true;
        if (isset($cart_item['custom_partition_slat_calc']) && !empty($cart_item['custom_partition_slat_calc']['painting_service'])) $has_painting = true;
        if (isset($cart_item['card_pack_purchase']) && !empty($cart_item['card_pack_purchase']['painting_service'])) $has_painting = true;
        if (isset($cart_item['standard_pack_purchase']) && !empty($cart_item['standard_pack_purchase']['painting_service'])) $has_painting = true;
        
        $price_text = wc_price($current_price) . ' за ' . $unit_text;
        if ($has_painting) {
            $price_text .= ' (вместе с покраской)';
        }
        $price_text .= '<br><small style="color: #666;">' . wc_price($base_price_m2) . ' / м²</small>';
        
        return $price_text;
    }
    
    return $price;
}

add_filter('woocommerce_cart_item_subtotal', 'parusweb_format_cart_item_subtotal', 10, 3);

function parusweb_format_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $product_id = $product->get_id();
    
    if (!is_in_target_categories($product_id)) {
        return $subtotal;
    }
    
    if (isset($cart_item['card_pack_purchase']) || 
        isset($cart_item['custom_area_calc']) || 
        isset($cart_item['custom_dimensions']) ||
        isset($cart_item['custom_multiplier_calc']) ||
        isset($cart_item['custom_running_meter_calc']) ||
        isset($cart_item['custom_square_meter_calc']) ||
        isset($cart_item['custom_partition_slat_calc'])) {
        
        $quantity = $cart_item['quantity'];
        $total = floatval($product->get_price()) * $quantity;
        
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
        $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковку', 'упаковки', 'упаковок'];
        
        $plural = parusweb_get_russian_plural($quantity, $unit_forms);
        
        return '<strong>' . wc_price($total) . '</strong><br>' .
               '<small style="color: #666;">' . $quantity . ' ' . $plural . '</small>';
    }
    
    return $subtotal;
}

add_filter('woocommerce_widget_cart_item_quantity', 'parusweb_format_mini_cart_quantity', 10, 3);

function parusweb_format_mini_cart_quantity($quantity, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $product_id = $product->get_id();
    
    if (!is_in_target_categories($product_id)) {
        return $quantity;
    }
    
    if (isset($cart_item['card_pack_purchase']) || 
        isset($cart_item['custom_area_calc']) || 
        isset($cart_item['custom_dimensions']) ||
        isset($cart_item['custom_multiplier_calc']) ||
        isset($cart_item['custom_running_meter_calc']) ||
        isset($cart_item['custom_square_meter_calc']) ||
        isset($cart_item['custom_partition_slat_calc'])) {
        
        $qty = $cart_item['quantity'];
        $current_price = floatval($product->get_price());
        
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
        $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковку', 'упаковки', 'упаковок'];
        
        $plural = parusweb_get_russian_plural($qty, $unit_forms);
        
        return '<span class="quantity">' . $qty . ' ' . $plural . ' × ' . wc_price($current_price) . '</span>';
    }
    
    return $quantity;
}

add_filter('gettext', 'parusweb_change_subtotal_to_cost_in_mini_cart', 20, 3);

function parusweb_change_subtotal_to_cost_in_mini_cart($translated, $text, $domain) {
    if ($domain === 'woocommerce' && $text === 'Subtotal') {
        return 'Стоимость';
    }
    if ($domain === 'woocommerce' && $text === 'Подытог') {
        return 'Стоимость';
    }
    return $translated;
}

add_filter('woocommerce_get_item_data', 'parusweb_remove_price_from_service_name', 15, 2);

function parusweb_remove_price_from_service_name($item_data, $cart_item) {
    foreach ($item_data as &$data) {
        if (isset($data['value']) && is_string($data['value'])) {
            $data['value'] = preg_replace('/\s*—\s*[\d\s,.]+₽/', '', $data['value']);
            $data['value'] = preg_replace('/\s*\([\d\s,.]+м²\s*×\s*[\d\s,.]+₽\/м²\)/', '', $data['value']);
        }
    }
    return $item_data;
}

function parusweb_get_russian_plural($number, $forms) {
    $number = abs($number) % 100;
    $n1 = $number % 10;
    
    if ($number > 10 && $number < 20) {
        return $forms[2];
    }
    if ($n1 > 1 && $n1 < 5) {
        return $forms[1];
    }
    if ($n1 == 1) {
        return $forms[0];
    }
    return $forms[2];
}

add_action('wp_head', 'parusweb_cart_custom_css');

function parusweb_cart_custom_css() {
    ?>
    <style>
    /* Скрываем только dt с классом variation- и содержимым ":" */
    .woocommerce-mini-cart-item dl.variation dt.variation-:empty,
    .woocommerce-mini-cart-item dl.variation dt.variation- {
        display: none !important;
    }
    
    /* Проверяем содержимое через CSS */
    .woocommerce-mini-cart-item dl.variation dt.variation-:not(:has(*)):not(:has(img)) {
        display: none !important;
    }
    </style>
    <script>
    jQuery(document).ready(function($) {
        // Функция для скрытия dt с одним двоеточием
        function hideEmptyVariationDt() {
            $('.woocommerce-mini-cart-item dl.variation dt, .cart_item dl.variation dt').each(function() {
                var text = $(this).text().trim();
                if (text === ':' || text === '') {
                    $(this).hide();
                }
            });
        }
        
        // Функция для замены "Подытог" на "Стоимость" в мини-корзине
        function replaceSubtotalText() {
            $('.widget_shopping_cart .total strong, .woocommerce-mini-cart__total strong').each(function() {
                var text = $(this).text();
                if (text === 'Подытог:' || text === 'Subtotal:') {
                    $(this).text('Стоимость:');
                }
            });
        }
        
        // Запускаем при загрузке
        hideEmptyVariationDt();
        replaceSubtotalText();
        
        // Запускаем при обновлении корзины
        $(document.body).on('updated_cart_totals updated_wc_div wc_fragments_refreshed wc_fragments_loaded', function() {
            hideEmptyVariationDt();
            replaceSubtotalText();
        });
        
        // Запускаем при открытии мини-корзины
        $(document).on('mouseenter', '.widget_shopping_cart', function() {
            setTimeout(function() {
                hideEmptyVariationDt();
                replaceSubtotalText();
            }, 100);
        });
    });
    </script>
    <?php
}

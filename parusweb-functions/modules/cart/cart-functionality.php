<?php
/**
 * ============================================================================
 * МОДУЛЬ: ФУНКЦИОНАЛ КОРЗИНЫ
 * ============================================================================
 * 
 * @package ParusWeb_Functions
 * @subpackage Cart
 * @version 2.0.1
 */

if (!defined('ABSPATH')) exit;

add_filter('woocommerce_add_cart_item_data', 'parusweb_add_calculator_data_to_cart', 10, 3);

function parusweb_add_calculator_data_to_cart($cart_item_data, $product_id, $variation_id) {
    
    // ВРЕМЕННАЯ ОТЛАДКА - начало функции
    if (WP_DEBUG) {
        error_log('=== ADD TO CART START ===');
        error_log('Product ID: ' . $product_id);
        error_log('POST keys: ' . implode(', ', array_keys($_POST)));
    }
    
    if (!is_in_target_categories($product_id)) {
        return $cart_item_data;
    }
    
    $product = wc_get_product($product_id);
    if (!$product) return $cart_item_data;
    
    $title = $product->get_name();
    $pack_area = extract_area_with_qty($title, $product_id);
    $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
    
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
    
    // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: НЕ применяем покраску для крепежа и других некрасимых товаров
    $fastener_categories = [97, 98, 99, 100]; // ID категорий крепежа - ОБНОВИТЕ ПОД ВАШИ!
    $exclude_painting_categories = array_merge($fastener_categories, [/* добавьте другие категории */]);
    
    $is_paintable = !has_term($exclude_painting_categories, 'product_cat', $product_id);
    
    // Получаем покраску ТОЛЬКО для красимых товаров
    $painting_service = $is_paintable ? parusweb_get_painting_service_from_post() : null;
    
    // ВРЕМЕННАЯ ОТЛАДКА - результат получения покраски
    if (WP_DEBUG) {
        if (!$is_paintable) {
            error_log('PAINTING_SERVICE: ПРОПУЩЕНО (товар не красится, категория крепежа)');
        } elseif ($painting_service) {
            error_log('PAINTING_SERVICE получен: ' . print_r($painting_service, true));
        } else {
            error_log('PAINTING_SERVICE: NULL (покраска не выбрана или не передана)');
        }
    }
    
    $scheme_data = parusweb_get_scheme_data_from_post();
    if ($scheme_data) {
        $cart_item_data = array_merge($cart_item_data, $scheme_data);
    }
    
    if (!empty($_POST['custom_area_packs']) && !empty($_POST['custom_area_area_value'])) {
        $total_price = floatval($_POST['custom_area_total_price']);
        $cart_item_data['custom_area_calc'] = [
            'packs' => intval($_POST['custom_area_packs']),
            'area' => floatval($_POST['custom_area_area_value']),
            'total_price' => $total_price,
            'grand_total' => floatval($_POST['custom_area_grand_total'] ?? $total_price),
            'is_leaf' => $is_leaf_category,
            'painting_service' => $painting_service
        ];
        
        if ($painting_service) {
            $cart_item_data['custom_area_calc']['base_price'] = $total_price - $painting_service['total_cost'];
        }
        
        return $cart_item_data;
    }
    
    if (!empty($_POST['custom_width_val']) && !empty($_POST['custom_length_val'])) {
        $price = floatval($_POST['custom_dim_price']);
        $cart_item_data['custom_dimensions'] = [
            'width' => intval($_POST['custom_width_val']),
            'length'=> intval($_POST['custom_length_val']),
            'price'=> $price,
            'grand_total' => floatval($_POST['custom_dim_grand_total'] ?? $price),
            'is_leaf' => $is_leaf_category,
            'painting_service' => $painting_service
        ];
        
        // Добавляем фаску если выбрана (название и изображение)
        if (!empty($_POST['selected_faska_type'])) {
            $cart_item_data['custom_dimensions']['faska_name'] = sanitize_text_field($_POST['selected_faska_type']);
            
            // Ищем изображение фаски из данных калькулятора
            if (!empty($_POST['selected_faska_image'])) {
                $cart_item_data['custom_dimensions']['faska_image'] = esc_url_raw($_POST['selected_faska_image']);
            }
        }
        
        if ($painting_service) {
            $cart_item_data['custom_dimensions']['base_price'] = $price - $painting_service['total_cost'];
        }
        
        return $cart_item_data;
    }
    
    if (!empty($_POST['custom_mult_width']) && !empty($_POST['custom_mult_length'])) {
        $price = floatval($_POST['custom_mult_price']);
        $cart_item_data['custom_multiplier_calc'] = [
            'width' => floatval($_POST['custom_mult_width']),
            'length' => floatval($_POST['custom_mult_length']),
            'quantity' => intval($_POST['custom_mult_quantity'] ?? 1),
            'area_per_item' => floatval($_POST['custom_mult_area_per_item']),
            'total_area' => floatval($_POST['custom_mult_total_area']),
            'multiplier' => floatval($_POST['custom_mult_multiplier']),
            'price' => $price,
            'grand_total' => floatval($_POST['custom_mult_grand_total'] ?? $price),
            'painting_service' => $painting_service
        ];
        
        if ($painting_service) {
            $cart_item_data['custom_multiplier_calc']['base_price'] = $price - $painting_service['total_cost'];
        }
        
        return $cart_item_data;
    }
    
    if (!empty($_POST['custom_rm_length'])) {
        $price = floatval($_POST['custom_rm_price']);
        $rm_data = [
            'width' => floatval($_POST['custom_rm_width'] ?? 0),
            'length' => floatval($_POST['custom_rm_length']),
            'quantity' => intval($_POST['custom_rm_quantity'] ?? 1),
            'total_length' => floatval($_POST['custom_rm_total_length']),
            'painting_area' => floatval($_POST['custom_rm_painting_area'] ?? 0),
            'multiplier' => floatval($_POST['custom_rm_multiplier'] ?? 1),
            'price' => $price,
            'grand_total' => floatval($_POST['custom_rm_grand_total'] ?? $price),
            'painting_service' => $painting_service
        ];
        
        if (!empty($_POST['falsebalk_shape'])) {
            $shape = sanitize_text_field($_POST['falsebalk_shape']);
            $rm_data['shape'] = $shape;
            
            $shape_labels = [
                'g' => 'Г-образная',
                'p' => 'П-образная',
                'o' => 'О-образная'
            ];
            $rm_data['shape_label'] = isset($shape_labels[$shape]) ? $shape_labels[$shape] : ucfirst($shape);
        }
        
        if (!empty($_POST['custom_rm_height'])) {
            $rm_data['height'] = floatval($_POST['custom_rm_height']);
        }
        
        if (!empty($_POST['custom_rm_height1'])) {
            $rm_data['height'] = floatval($_POST['custom_rm_height1']);
        }
        
        if (!empty($_POST['custom_rm_height2'])) {
            $rm_data['height2'] = floatval($_POST['custom_rm_height2']);
        }
        
        if ($painting_service) {
            $rm_data['base_price'] = $price - $painting_service['total_cost'];
        }
        
        $cart_item_data['custom_running_meter_calc'] = $rm_data;
        return $cart_item_data;
    }
    
    if (!empty($_POST['custom_sqm_width']) && !empty($_POST['custom_sqm_length'])) {
        $price = floatval($_POST['custom_sqm_price']);
        $cart_item_data['custom_square_meter_calc'] = [
            'width' => floatval($_POST['custom_sqm_width']),
            'length' => floatval($_POST['custom_sqm_length']),
            'area' => floatval($_POST['custom_sqm_area']),
            'quantity' => intval($_POST['custom_sqm_quantity'] ?? 1),
            'price' => $price,
            'grand_total' => floatval($_POST['custom_sqm_grand_total'] ?? $price),
            'painting_service' => $painting_service
        ];
        
        if ($painting_service) {
            $cart_item_data['custom_square_meter_calc']['base_price'] = $price - $painting_service['total_cost'];
        }
        
        return $cart_item_data;
    }
    
    if (!empty($_POST['custom_part_width']) && !empty($_POST['custom_part_length']) && !empty($_POST['custom_part_thickness'])) {
        $price = floatval($_POST['custom_part_price']);
        $cart_item_data['custom_partition_slat_calc'] = [
            'width' => floatval($_POST['custom_part_width']),
            'length' => floatval($_POST['custom_part_length']),
            'thickness' => floatval($_POST['custom_part_thickness']),
            'volume' => floatval($_POST['custom_part_volume']),
            'quantity' => intval($_POST['custom_part_quantity'] ?? 1),
            'price' => $price,
            'grand_total' => floatval($_POST['custom_part_grand_total'] ?? $price),
            'painting_service' => $painting_service
        ];
        
        if ($painting_service) {
            $cart_item_data['custom_partition_slat_calc']['base_price'] = $price - $painting_service['total_cost'];
        }
        
        return $cart_item_data;
    }
    
    if (!empty($_POST['card_pack_purchase'])) {
        $total_price = $base_price_m2 * $pack_area;
        $cart_item_data['card_pack_purchase'] = [
            'area' => $pack_area,
            'price_per_m2' => $base_price_m2,
            'total_price' => $total_price,
            'is_leaf' => $is_leaf_category,
            'unit_type' => $is_leaf_category ? 'лист' : 'упаковку',
            'painting_service' => $painting_service
        ];
        
        if ($painting_service) {
            $cart_item_data['card_pack_purchase']['base_price'] = $total_price - $painting_service['total_cost'];
        }
        
        return $cart_item_data;
    }
    
    if ($pack_area > 0) {
        $total_price = $base_price_m2 * $pack_area;
        $cart_item_data['standard_pack_purchase'] = [
            'area' => $pack_area,
            'price_per_m2' => $base_price_m2,
            'total_price' => $total_price,
            'is_leaf' => $is_leaf_category,
            'unit_type' => $is_leaf_category ? 'лист' : 'упаковку',
            'painting_service' => $painting_service
        ];
        
        if ($painting_service) {
            $cart_item_data['standard_pack_purchase']['base_price'] = $total_price - $painting_service['total_cost'];
        }
    }
    
    return $cart_item_data;
}

add_filter('woocommerce_add_cart_item_data', 'parusweb_validate_falsebalk_data_in_cart', 15, 3);

function parusweb_validate_falsebalk_data_in_cart($cart_item_data, $product_id, $variation_id) {
    
    if (!has_term(266, 'product_cat', $product_id)) {
        return $cart_item_data;
    }
    
    if (isset($_POST['custom_rm_width']) || isset($_POST['custom_rm_length'])) {
        
        if (empty($_POST['falsebalk_shape'])) {
            wc_add_notice('Пожалуйста, выберите форму сечения фальшбалки', 'error');
            return $cart_item_data;
        }
        
        if (empty($_POST['custom_rm_width']) || floatval($_POST['custom_rm_width']) <= 0) {
            wc_add_notice('Пожалуйста, выберите ширину фальшбалки', 'error');
            return $cart_item_data;
        }
        
        if (empty($_POST['custom_rm_length']) || floatval($_POST['custom_rm_length']) <= 0) {
            wc_add_notice('Пожалуйста, выберите длину фальшбалки', 'error');
            return $cart_item_data;
        }
        
        $shape = sanitize_text_field($_POST['falsebalk_shape']);
        
        if (in_array($shape, ['g', 'o'])) {
            if ((empty($_POST['custom_rm_height']) && empty($_POST['custom_rm_height1'])) || 
                (floatval($_POST['custom_rm_height'] ?? 0) <= 0 && floatval($_POST['custom_rm_height1'] ?? 0) <= 0)) {
                wc_add_notice('Пожалуйста, выберите высоту фальшбалки', 'error');
                return $cart_item_data;
            }
        }
        
        if ($shape === 'p') {
            if (empty($_POST['custom_rm_height1']) || floatval($_POST['custom_rm_height1']) <= 0) {
                wc_add_notice('Пожалуйста, выберите высоту 1 фальшбалки', 'error');
                return $cart_item_data;
            }
            if (empty($_POST['custom_rm_height2']) || floatval($_POST['custom_rm_height2']) <= 0) {
                wc_add_notice('Пожалуйста, выберите высоту 2 фальшбалки', 'error');
                return $cart_item_data;
            }
        }
    }
    
    return $cart_item_data;
}

function parusweb_get_painting_service_from_post() {
    // ВРЕМЕННАЯ ОТЛАДКА
    if (WP_DEBUG) {
        error_log('=== PAINTING SERVICE FROM POST ===');
        error_log('POST данные покраски:');
        error_log('painting_service_id: ' . ($_POST['painting_service_id'] ?? 'НЕТ'));
        error_log('painting_service_key: ' . ($_POST['painting_service_key'] ?? 'НЕТ'));
        error_log('painting_service_name: ' . ($_POST['painting_service_name'] ?? 'НЕТ'));
        error_log('painting_service_cost: ' . ($_POST['painting_service_cost'] ?? 'НЕТ'));
        error_log('painting_service_total_cost: ' . ($_POST['painting_service_total_cost'] ?? 'НЕТ'));
        error_log('painting_service_price_per_m2: ' . ($_POST['painting_service_price_per_m2'] ?? 'НЕТ'));
        error_log('painting_service_area: ' . ($_POST['painting_service_area'] ?? 'НЕТ'));
    }
    
    // ИСПРАВЛЕНИЕ: JavaScript создает painting_service_key, а не painting_service_id
    if (empty($_POST['painting_service_key']) && empty($_POST['painting_service_id'])) {
        if (WP_DEBUG) {
            error_log('РЕЗУЛЬТАТ: ни painting_service_key, ни painting_service_id не найдены - возвращаем NULL');
        }
        return null;
    }
    
    // ИСПРАВЛЕНИЕ: используем painting_service_key если есть, иначе painting_service_id
    $service_id = !empty($_POST['painting_service_key']) ? $_POST['painting_service_key'] : $_POST['painting_service_id'];
    
    // ИСПРАВЛЕНИЕ: JavaScript создает painting_service_total_cost, а не painting_service_cost
    $total_cost = 0;
    if (!empty($_POST['painting_service_total_cost'])) {
        $total_cost = floatval($_POST['painting_service_total_cost']);
    } elseif (!empty($_POST['painting_service_cost'])) {
        $total_cost = floatval($_POST['painting_service_cost']);
    }
    
    $painting_service = [
        'id' => sanitize_text_field($service_id),
        'name' => sanitize_text_field($_POST['painting_service_name'] ?? ''),
        'price_per_m2' => floatval($_POST['painting_service_price_per_m2'] ?? 0),
        'area' => floatval($_POST['painting_service_area'] ?? 0),
        'total_cost' => $total_cost
    ];
    
    if (!empty($_POST['painting_service_color'])) {
        $painting_service['color'] = sanitize_text_field($_POST['painting_service_color']);
    }
    
    $painting_service['name_with_color'] = $painting_service['name'];
    if (!empty($painting_service['color'])) {
        $painting_service['name_with_color'] .= ' (' . $painting_service['color'] . ')';
    }
    
    // ВРЕМЕННАЯ ОТЛАДКА
    if (WP_DEBUG) {
        error_log('РЕЗУЛЬТАТ painting_service:');
        error_log(print_r($painting_service, true));
    }
    
    return $painting_service;
}

function parusweb_get_scheme_data_from_post() {
    $scheme_data = [];
    
    if (!empty($_POST['pm_selected_scheme_name'])) {
        $scheme_data['pm_selected_scheme_name'] = sanitize_text_field($_POST['pm_selected_scheme_name']);
    }
    
    if (!empty($_POST['pm_selected_scheme_slug'])) {
        $scheme_data['pm_selected_scheme_slug'] = sanitize_text_field($_POST['pm_selected_scheme_slug']);
    }
    
    if (!empty($_POST['pm_selected_color_image'])) {
        $scheme_data['pm_selected_color_image'] = esc_url_raw($_POST['pm_selected_color_image']);
    }
    
    if (!empty($_POST['pm_selected_color_filename'])) {
        $scheme_data['pm_selected_color'] = sanitize_text_field($_POST['pm_selected_color_filename']);
        $scheme_data['pm_selected_color_filename'] = sanitize_text_field($_POST['pm_selected_color_filename']);
    }
    
    return !empty($scheme_data) ? $scheme_data : null;
}

add_filter('woocommerce_add_to_cart_quantity', 'parusweb_adjust_cart_quantity', 10, 2);

function parusweb_adjust_cart_quantity($quantity, $product_id) {
    if (!is_in_target_categories($product_id)) {
        return $quantity;
    }
    
    if (isset($_POST['custom_area_packs']) && !empty($_POST['custom_area_packs']) && 
        isset($_POST['custom_area_area_value']) && !empty($_POST['custom_area_area_value'])) {
        return intval($_POST['custom_area_packs']);
    }
    
    if (isset($_POST['custom_width_val']) && !empty($_POST['custom_width_val']) && 
        isset($_POST['custom_length_val']) && !empty($_POST['custom_length_val'])) {
        return 1;
    }
    
    return $quantity;
}

add_action('woocommerce_add_to_cart', 'parusweb_correct_cart_quantity', 10, 6);

function parusweb_correct_cart_quantity($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    if (!is_in_target_categories($product_id)) {
        return;
    }
    
    if (isset($cart_item_data['custom_area_calc'])) {
        $packs = intval($cart_item_data['custom_area_calc']['packs']);
        if ($packs > 0 && $quantity !== $packs) {
            WC()->cart->set_quantity($cart_item_key, $packs);
        }
    }
}

add_action('woocommerce_before_calculate_totals', 'parusweb_recalculate_cart_prices');

function parusweb_recalculate_cart_prices($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        
        if (isset($cart_item['custom_area_calc'])) {
            $area_calc = $cart_item['custom_area_calc'];
            $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
            $area = floatval($area_calc['area']);
            $total_price = $base_price_m2 * $area;
            
            if (isset($area_calc['painting_service']) && !empty($area_calc['painting_service'])) {
                $painting_price = floatval($area_calc['painting_service']['total_cost']);
                $total_price += $painting_price;
            }
            
            $product->set_price($total_price);
        }
        
        if (isset($cart_item['custom_dimensions'])) {
            $dims = $cart_item['custom_dimensions'];
            $price = floatval($dims['price']);
            
            if (isset($dims['painting_service']) && !empty($dims['painting_service'])) {
                $painting_price = floatval($dims['painting_service']['total_cost']);
                $price += $painting_price;
            }
            
            $product->set_price($price);
        }
        
        if (isset($cart_item['custom_multiplier_calc'])) {
            $mult_calc = $cart_item['custom_multiplier_calc'];
            $price = floatval($mult_calc['grand_total'] ?? $mult_calc['price']);
            $product->set_price($price);
        }
        
        if (isset($cart_item['custom_running_meter_calc'])) {
            $rm_calc = $cart_item['custom_running_meter_calc'];
            $price = floatval($rm_calc['grand_total'] ?? $rm_calc['price']);
            $product->set_price($price);
        }
        
        if (isset($cart_item['custom_square_meter_calc'])) {
            $sq_calc = $cart_item['custom_square_meter_calc'];
            $price = floatval($sq_calc['grand_total'] ?? $sq_calc['price']);
            $product->set_price($price);
        }
        
        if (isset($cart_item['custom_partition_slat_calc'])) {
            $part_calc = $cart_item['custom_partition_slat_calc'];
            $price = floatval($part_calc['grand_total'] ?? $part_calc['price']);
            $product->set_price($price);
        }
        
        if (isset($cart_item['card_pack_purchase']) || isset($cart_item['standard_pack_purchase'])) {
            $pack_data = isset($cart_item['card_pack_purchase']) ? $cart_item['card_pack_purchase'] : $cart_item['standard_pack_purchase'];
            $total_price = floatval($pack_data['total_price']);
            
            if (isset($pack_data['painting_service']) && !empty($pack_data['painting_service'])) {
                $painting_price = floatval($pack_data['painting_service']['total_cost']);
                $total_price += $painting_price;
            }
            
            $product->set_price($total_price);
        }
    }
}

add_action('wp_footer', 'parusweb_card_purchase_script');

function parusweb_card_purchase_script() {
    if (!is_shop() && !is_product_category() && !is_product_tag()) {
        return;
    }
    
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addToCartButtons = document.querySelectorAll('.add_to_cart_button:not(.product_type_variable)');
        
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                const productId = this.getAttribute('data-product_id');
                
                if (!productId) return;
                
                const formData = new FormData();
                formData.append('card_pack_purchase', '1');
                formData.append('product_id', productId);
                formData.append('quantity', this.getAttribute('data-quantity') || 1);
                
                const href = this.getAttribute('href');
                if (href && href.includes('add-to-cart=')) {
                    e.preventDefault();
                    
                    fetch(wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart'), {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error adding to cart:', data);
                            return;
                        }
                        
                        jQuery(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash, button]);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                }
            });
        });
    });
    </script>
    <?php
}

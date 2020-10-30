<?php
/**
 * Plugin Name: WooCommerce Product Manager API
 * Plugin URI: https://github.com/uleytech/wc-product-manager-api
 * Description: Provides functionality for WooCommerce.
 * Version: 1.1.8
 * Author: Oleksandr Krokhin
 * Author URI: https://www.krohin.com
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Tested: 5.2
 * WC requires at least: 4.6.0
 * WC tested up to: 4.6.1
 * Text Domain: wc-product-manager-api
 * Domain Path: /languages/
 * License: MIT
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/include.php';
require_once __DIR__ . '/options.php';
require_once __DIR__ . '/update.php';
if (is_admin()) {
    new PmaUpdater(__FILE__, 'uleytech', "wc-product-manager-api");
}

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

function pma_settings_link($links)
{
    $url = esc_url(add_query_arg(
        'page',
        'wc-product-manager-api',
        get_admin_url() . 'options-general.php'
    ));
    $link[] = "<a href='$url'>" . __('Settings') . '</a>';


    return array_merge($link, $links);
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pma_settings_link');

//function init_product_manager_api()
//{
//    global $wpdb;
//    echo '<pre>' . print_r($wpdb->queries, true) . '</pre>>';
//}
//add_action('admin_init', 'init_product_manager_api');

function pma_import()
{
    if (isset($_POST['import'])) {
        return pma_import_action();
    } elseif (isset($_POST['delete_categories'])) {
        return pma_delete_category_action();
    } elseif (isset($_POST['delete_attributes'])) {
        return pma_delete_attribute_action();
    }
}

function pma_import_action()
{
    $options = get_option('wc_product_manager_api_options');

    $client = new Client();
    $token = [
        'token' => $options['api_key'],
    ];
    $parameters = http_build_query($token);
    try {
        $response = $client->get(PMA_API_URL . '?' . $parameters);
    } catch (GuzzleException $exception) {
        echo $exception->getMessage();
    }
    $rawProducts = json_decode($response->getBody(), true);
    $products = [];
    foreach ($rawProducts as $product) {
        $products[$product['group_id']][] = $product;
    }
    $imported = [];
    $updated = [];
    $deleted = [];
    $isIncludeGroups = false;
    foreach ($products as $group) {
        if (isset($options['include_groups']) && $options['include_groups'] !== '') {
            $isIncludeGroups = true;
            $includeGroups = explode(',', $options['include_groups']);
            if (!in_array($group[0]['group_id'], $includeGroups)) {
                continue;
            }
        }
        if (isset($options['exclude_groups']) && $options['exclude_groups'] !== '') {
            $excludeGroups = explode(',', $options['exclude_groups']);
            if (in_array($group[0]['group_id'], $excludeGroups)) {
                continue;
            }
        }

        $product = getProductBySku($group[0]['group_id']);
        if ($product) {
            $skus = [];
            // update
            foreach ($group as $item) {
                $skus[] = (string)$item['product_id'];
                $productVariation = getProductVariationBySku($item['product_id']);
                if ($productVariation) {
                    // update
                    updateProductVariation($productVariation, $item);
                } else {
                    // add
                    try {
                        addProductVariation($product, $item);
                    } catch (WC_Data_Exception $exception) {
                        return $exception->getMessage();
                    }
                }
            }
            $product->set_stock_status();
            $productId = $product->save();

            // delete
            $productVariations = $product->get_children();
            $skusOnShop = [];
            foreach ($productVariations as $productVariationId) {
                $productVariation = wc_get_product($productVariationId);
                $skusOnShop[] = (string)$productVariation->get_sku();
            }
            $skusNotInStock = array_diff($skusOnShop, $skus);
            foreach ($skusNotInStock as $skuNotInStock) {
                $productVariation = getProductVariationBySku($skuNotInStock);
                if ($productVariation) {
                    $productVariation->set_stock_status('outofstock');
                    $productVariation->save();
                }
            }
            $updated[] = $productId;
        } else {
            // add
            $product = new WC_Product_Variable();
            try {
                $product->set_name($group[0]['group_name']);
                $product->set_description($group[0]['product_seo_description'] ?? '');
                $product->set_short_description($group[0]['product_description'] ?? '');
                $product->set_sku($group[0]['group_id']);
                $product->set_category_ids(
                    getCategoryByName($group[0]['category_name'], $group[0]['category_seo_description'])
                );
                $product->set_reviews_allowed(false);
                $product->set_status('publish');
                $product->set_stock_status();
//            $product->set_gallery_image_ids([$imageId]);
            } catch (WC_Data_Exception $exception) {
                return $exception->getMessage();
            }

            $dosages = [];
            $packages = [];
            foreach ($group as $item) {
                $dosages[pma_sanitizer($item['product_dosage_type'])][] = trim($item['product_dosage']);
                $packages[pma_sanitizer($item['product_package_type'])][] = trim($item['product_package']);
            }
            $attributes = array_merge(
                addProductAttributes($dosages),
                addProductAttributes($packages)
            );
            $product->set_attributes($attributes);
            $productId = $product->save();
            $imageId = getIdFromPictureUrl($group[0]['image']);
            if ($imageId) {
                $product->set_image_id($imageId);
            }
            $product->save();

            foreach ($group as $item) {
                // add
                try {
                    addProductVariation($product, $item);
                } catch (WC_Data_Exception $exception) {
                    return $exception->getMessage();
                }
            }
            $imported[] = $productId;
        }
    }

    if (!$isIncludeGroups) {
        $productsNotInStock = array_diff(getProductIds(), $imported, $updated);
        foreach ($productsNotInStock as $productNotInStock) {
            $product = wc_get_product($productNotInStock);
            if ($product) {
                $product->set_stock_status('outofstock');
                $product->save();
                $productVariations = $product->get_children();
                foreach ($productVariations as $productVariationId) {
                    $productVariation = wc_get_product($productVariationId);
                    $productVariation->set_stock_status('outofstock');
                    $productVariation->save();
                }
                $deleted[] = $product->get_id();
            }
        }
    }

    return __('All products import successful', 'wc-product-manager-api') . ', '
        . count($imported) . ' ' . __('imported') . ', '
        . count($updated) . ' ' . __('updated') . ', '
        . count($deleted) . ' ' . __('out of stock') ;
}

function pma_delete_category_action()
{
    global $wpdb;
    $wpdb->query("
        DELETE a, c 
        FROM {$wpdb->base_prefix}terms AS a
        LEFT JOIN {$wpdb->base_prefix}term_taxonomy AS c ON a.term_id = c.term_id
        LEFT JOIN {$wpdb->base_prefix}term_relationships AS b ON b.term_taxonomy_id = c.term_taxonomy_id
        WHERE c.taxonomy = 'product_cat'
        AND a.slug not like 'uncategorized'
    ");

    return 'Affected: ' . $wpdb->rows_affected;
}

function pma_delete_attribute_action()
{
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->base_prefix}woocommerce_attribute_taxonomies");
    $wpdb->query("DELETE FROM {$wpdb->base_prefix}options where option_name = '_transient_wc_attribute_taxonomies' limit 1");
    $wpdb->query("
        DELETE a, c, b  
        FROM {$wpdb->base_prefix}terms AS a
        LEFT JOIN {$wpdb->base_prefix}term_taxonomy AS c ON a.term_id = c.term_id
        LEFT JOIN {$wpdb->base_prefix}termmeta AS b ON b.term_id = a.term_id
        WHERE c.taxonomy like 'pa_%'
    ");

    return 'Affected: ' . $wpdb->rows_affected;
}

/**
 * @param string $url
 * @return int|null
 */
function getIdFromPictureUrl(string $url): ?int
{
    global $wpdb;
    $fileName = basename($url, '.jpg');

    $sql = $wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta 
         WHERE meta_key = '_wp_attached_file' AND meta_value like '%s'",
        '%/' . $fileName
    );
    $postId = $wpdb->get_var($sql);

    if (!$postId) {
        $size = getimagesize($url);
        if (!$size) {
            return null;
        }
        $postId = media_sideload_image(
            $url,
            null,
            $fileName,
            'id'
        );
    }

    return (!($postId instanceof WP_Error)) ? $postId : null;
}

/**
 * @param string $data
 * @return string
 */
function pma_sanitizer(string $data): string
{
    return strtolower(str_replace(['(', ')', '%', ' '], '', $data));
}

/**
 * @param string $sku
 * @return WC_Product_Variable|null
 */
function getProductBySku(string $sku): ?WC_Product_Variable
{
    global $wpdb;
    $product_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1",
            $sku
        )
    );
    if ($product_id) {
        return new WC_Product_Variable($product_id);
    }
    return null;
}

/**
 * @return array|null
 */
function getProductIds(): ?array
{
    global $wpdb;
    $rawProducts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id FROM $wpdb->posts WHERE post_type='%s'",
            'product'
        ), 'ARRAY_A'
    );

    return
        array_map(
            function ($item) {
                return $item['id'];
            },
            $rawProducts
        );
}

/**
 * @param string $sku
 * @return WC_Product_Variation|null
 */
function getProductVariationBySku(string $sku): ?WC_Product_Variation
{
    global $wpdb;
    $product_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1",
            $sku
        )
    );
    if ($product_id) {
        return new WC_Product_Variation($product_id);
    }
    return null;
}

/**
 * @param string $name
 * @param string $description
 * @return array
 */
function getCategoryByName(string $name, string $description): array
{
    if (!term_exists($name, 'product_cat')) {
        return wp_insert_term(
            $name, // the term
            'product_cat', // the taxonomy
            [
                'description' => $description,
                'slug' => '',
            ]
        );
    } else {
        return (get_term_by('name', $name, 'product_cat'))->to_array();
    }
}

/**
 * @param WC_Product_Variable $product
 * @param array $data
 * @return int
 * @throws WC_Data_Exception
 */
function addProductVariation(WC_Product_Variable $product, array $data): int
{
    $variation = new WC_Product_Variation();
    try {
        $variation->set_regular_price($data['product_price']);
        $variation->set_sku($data['product_id']);
        $variation->set_parent_id($product->get_id());
        $variation->set_attributes([
            pma_sanitizer($data['product_dosage_type']) => trim($data['product_dosage']),
            pma_sanitizer($data['product_package_type']) => trim($data['product_package']),
        ]);
        $variation->set_status('publish');
        $variation->set_stock_status();
    } catch (WC_Data_Exception $exception) {
        throw $exception;
    }
    return $variation->save();
}

/**
 * @param WC_Product_Variation $productVariation
 * @param array $data
 * @return void
 */
function updateProductVariation(WC_Product_Variation $productVariation, array $data): void
{
    $productVariation->set_stock_status();
    if ($productVariation->get_regular_price() !== $data['product_price']) {
        $productVariation->set_regular_price($data['product_price']);
    }
    $attributes = [];
    $productDosage = $productVariation->get_attribute('product_dosage_type');
    if ($productDosage !== $data['product_dosage']) {
        $attributes[pma_sanitizer($data['product_dosage_type'])] = trim($data['product_dosage']);
    }
    $productPackage = $productVariation->get_attribute('product_package_type');
    if ($productPackage !== $data['product_package']) {
        $attributes[pma_sanitizer($data['product_package_type'])] = trim($data['product_package']);
    }
    if (count($attributes) > 0) {
        $productVariation->set_attributes($attributes);
    }
    $productVariation->save();
}


/**
 * @param array $data
 * @return array
 */
function addProductAttributes(array $data): array
{
    $attributes = [];
    foreach ($data as $type => $dosage) {
        $dosage = array_unique($dosage);
        $attribute = new WC_Product_Attribute();
        $attribute->set_id(0);
        $attribute->set_name($type);
        $attribute->set_options(array_values($dosage));
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attributes[] = $attribute;
    }
    return $attributes;
}
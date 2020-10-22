<?php
/**
 * Plugin Name: WooCommerce Product Manager API
 * Plugin URI: https://github.com/uleytech/wc-product-manager-api
 * Description: Provides functionality for WooCommerce.
 * Version: 1.0.7
 * Author: Oleksandr Krokhin
 * Author URI: https://www.krohin.com
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * License: MIT
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/options.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

function init_product_manager_api()
{
    //
}

add_action('admin_init', 'init_product_manager_api');

function pma_import()
{
    if (isset($_POST['import'])) {
        return pma_import_action();
    } elseif (isset($_POST['update'])) {
        return pma_update_action();
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
        $response = $client->get('https://restrict.ax.megadevs.xyz/api/rss/en?' . $parameters);
    } catch (GuzzleException $exception) {
        echo $exception->getMessage();
    }
    $rawProducts = json_decode($response->getBody(), true);
    $products = [];
    foreach ($rawProducts as $product) {
        $products[$product['group_id']][] = $product;
    }
    $imported = [];
    foreach ($products as $group) {
        if (!in_array($group[0]['group_id'], [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 133, 366])) {
            continue;
        }
        $imageId = media_sideload_image(
            $group[0]['image'],
            null,
            basename($group[0]['image'], '.jpg'),
            'id'
        );
        if (!term_exists($group[0]['category_name'], 'product_cat')) {
            $category = wp_insert_term(
                $group[0]['category_name'], // the term
                'product_cat', // the taxonomy
                [
                    'description' => $group[0]['category_seo_description'],
                    'slug' => '',
                ]
            );
        } else {
            $category = (get_term_by('name', $group[0]['category_name'], 'product_cat'))->to_array();
        }

        $product = new WC_Product_Variable();
        try {
            $product->set_name($group[0]['group_name']);
            $product->set_description($group[0]['product_seo_description']);
            $product->set_short_description($group[0]['product_description']);
            $product->set_sku($group[0]['group_id']);
            $product->set_category_ids($category);
            $product->set_image_id($imageId);
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
            $dosages[strtolower(str_replace(['(', ')', '%', ' '], '', $item['product_dosage_type']))][]
                = trim($item['product_dosage']);
            $packages[strtolower(str_replace(['(', ')', ' '], '', $item['product_package_type']))][]
                = trim($item['product_package']);
        }
        $attributes = [];
        foreach ($dosages as $type => $dosage) {
            $dosage = array_unique($dosage);
            $attribute = new WC_Product_Attribute();
            $attribute->set_id(0);
            $attribute->set_name($type);
            $attribute->set_options(array_values($dosage));
            $attribute->set_visible(1);
            $attribute->set_variation(1);
            $attributes[] = $attribute;
        }
        foreach ($packages as $type => $package) {
            $package = array_unique($package);
            $attribute = new WC_Product_Attribute();
            $attribute->set_id(0);
            $attribute->set_name($type);
            $attribute->set_options(array_values($package));
            $attribute->set_visible(1);
            $attribute->set_variation(1);
            $attributes[] = $attribute;
        }
        $product->set_attributes($attributes);
        $productId = $product->save();

        foreach ($group as $item) {
            $variation = new WC_Product_Variation();
            try {
                $variation->set_regular_price($item['product_price']);
                $variation->set_sku($item['product_id']);
                $variation->set_parent_id($productId);
                $variation->set_attributes([
                    strtolower(
                        str_replace(
                            ['(', ')', '%', ' '], '', $item['product_dosage_type']
                        )
                    ) => trim($item['product_dosage']),
                    strtolower(
                        str_replace(
                            ['(', ')', ' '], '', $item['product_package_type']
                        )
                    ) => trim($item['product_package']),
                ]);
                $variation->set_status('publish');
                $variation->set_stock_status();
                $variation->save();
            } catch (WC_Data_Exception $exception) {
                return $exception->getMessage();
            }
        }
        $imported[] = $productId;
    }

    return __('All products import successful', 'wc-product-manager-api') . ', ' . count($imported);
}

function pma_update_action()
{
    return 'Coming soon...';

    $options = get_option('wc_product_manager_api_options');

    $client = new Client();
    $token = [
        'token' => $options['api_key'],
    ];
    $parameters = http_build_query($token);
    try {
        $response = $client->get('https://restrict.ax.megadevs.xyz/api/rss/en?' . $parameters);
    } catch (GuzzleException $exception) {
        echo $exception->getMessage();
    }
    $rawProducts = json_decode($response->getBody(), true);
    $products = [];
    foreach ($rawProducts as $product) {
        $products[$product['group_id']][] = $product;
    }

    return '<pre>' . print_r(array_slice($products[2], 0, 100), true) . '</pre>';
}

function pma_delete_category_action()
{
    global $wpdb;
    $wpdb->query("
        DELETE a, c 
        FROM wp_terms AS a
        LEFT JOIN wp_term_taxonomy AS c ON a.term_id = c.term_id
        LEFT JOIN wp_term_relationships AS b ON b.term_taxonomy_id = c.term_taxonomy_id
        WHERE c.taxonomy = 'product_cat'
        AND a.slug not like 'uncategorized'
    ");

    return 'Affected: ' . $wpdb->rows_affected;
}

function pma_delete_attribute_action()
{
    global $wpdb;
    $wpdb->query("DELETE FROM wp_woocommerce_attribute_taxonomies");
    $wpdb->query("DELETE FROM wp_options where option_name = '_transient_wc_attribute_taxonomies' limit 1");
    $wpdb->query("
        DELETE a, c, b  
        FROM wp_terms AS a
        LEFT JOIN wp_term_taxonomy AS c ON a.term_id = c.term_id
        LEFT JOIN wp_termmeta AS b ON b.term_id = a.term_id
        WHERE c.taxonomy like 'pa_%'
    ");

    return 'Affected: ' . $wpdb->rows_affected;
}

<?php
/**
 * Plugin Name: WooCommerce Product Manager API
 * Plugin URI: https://github.com/uleytech/wc-product-manager-api
 * Description: Provides functionality for WooCommerce.
 * Version: 1.0.1
 * Author: Oleksandr Krokhin
 * Author URI: https://www.krohin.com
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * License: MIT
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/options.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

function init_product_manager_api()
{
    $client = new Client();
    $token = [
        'token' => '2e1f76f32f16ddecce3c524fa57c8c411a8ff706',
    ];
    $parameters = http_build_query($token);
return;
    try {
        $response = $client->get('https://restrict.ax.megadevs.xyz/api/rss/en?' . $parameters);
    } catch (GuzzleException $exception) {
        echo $exception->getMessage();
    }
    $products = json_decode($response->getBody(), true);
//    print_r(array_slice($products, 0, 10));
}

add_action('admin_init', 'init_product_manager_api');


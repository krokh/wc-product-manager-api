<?php

function pma_add_settings_page()
{
    add_options_page('Product Manager API', 'Product Manager API', 'manage_options', 'wc-product-manager-api', 'pma_render_plugin_settings_page');
}

add_action('admin_menu', 'pma_add_settings_page');

function pma_render_plugin_settings_page()
{
    ?>
    <h2>Product Manager API Settings</h2>
    <form action="options.php" method="post">
        <?php
        settings_fields('wc_product_manager_api_options');
        do_settings_sections('wc_product_manager_api'); ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>"/>
    </form>
    <?php
}

function pma_register_settings()
{
    register_setting('wc_product_manager_api_options', 'wc_product_manager_api_options', 'wc_product_manager_api_options_validate');
    add_settings_section('api_settings', 'API Settings', 'wc_product_manager_api_section_text', 'wc_product_manager_api');
    add_settings_field('wc_product_manager_api_setting_api_key', 'API Key', 'wc_product_manager_api_setting_api_key', 'wc_product_manager_api', 'api_settings');
}

add_action('admin_init', 'pma_register_settings');

function wc_product_manager_api_options_validate($input)
{
    $newinput['api_key'] = trim($input['api_key']);
    if (!preg_match('/^[a-z0-9]{40}$/i', $newinput['api_key'])) {
        $newinput['api_key'] = '';
    }
    return $newinput;
}

function wc_product_manager_api_section_text()
{
    echo '<p>Here you can set all the options for using the Product Manager API</p>';
}

function wc_product_manager_api_setting_api_key()
{
    $options = get_option('wc_product_manager_api_options');
    echo "<input id='wc_product_manager_api_setting_api_key' name='wc_product_manager_api_options[api_key]' type='text' value='" . esc_attr($options['api_key']) . "' />";
}

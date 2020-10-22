<?php

function pma_add_settings_page()
{
    add_options_page('Product Manager API', 'Product Manager API', 'manage_options', 'wc-product-manager-api', 'pma_render_plugin_settings_page');
}

add_action('admin_menu', 'pma_add_settings_page');

function pma_render_plugin_settings_page()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = pma_import();
        if ($response) {
            show_message('
                <div class="notice notice-success is-dismissible">
                    <p>' . $response . '</p>
                </div>'
            );
        } else {
            show_message('
                <div class="notice notice-error is-dismissible">
                    <p>Error while processing.</p>
                </div>'
            );
        }
    }
    echo '<h2>' . __('Product Manager API Settings', 'wc-product-manager-api') . '</h2>';
    echo '<form action="options.php" method="post">';
    settings_fields('wc_product_manager_api_options');
    do_settings_sections('wc_product_manager_api');
    submit_button(__('Save'), 'primary', 'submit', false);
    echo '</form>';
    echo '<h2>' . __('Import / Update Products', 'wc-product-manager-api') . '</h2>';
    echo '<form action="options-general.php?page=wc-product-manager-api" method="post">';
    submit_button(__('Import'), 'primary', 'import', false);
    echo '&nbsp;';
    submit_button(__('Update'), 'secondary', 'update', false);
    echo '<h2>' . __('Delete objects', 'wc-product-manager-api') . '</h2>';
    submit_button(__('Delete Categories'), 'secondary', 'delete_categories', false);
    echo '&nbsp;';
    submit_button(__('Delete Attributes'), 'secondary', 'delete_attributes', false);
    echo '</form>';
}

function pma_register_settings()
{
    register_setting('wc_product_manager_api_options', 'wc_product_manager_api_options', 'wc_product_manager_api_options_validate');
    add_settings_section('api_settings', 'API Settings', 'wc_product_manager_api_section_text', 'wc_product_manager_api');
    add_settings_field('wc_product_manager_api_setting_api_key', 'API Key', 'wc_product_manager_api_setting_api_key', 'wc_product_manager_api', 'api_settings');
    add_settings_field('wc_product_manager_api_setting_include_groups', 'Include SKU', 'wc_product_manager_api_setting_include_groups', 'wc_product_manager_api', 'api_settings');
    add_settings_field('wc_product_manager_api_setting_exclude_groups', 'Exclude SKU', 'wc_product_manager_api_setting_exclude_groups', 'wc_product_manager_api', 'api_settings');
}

add_action('admin_init', 'pma_register_settings');

function wc_product_manager_api_options_validate($input)
{
    $newinput['api_key'] = trim($input['api_key']);
    if (!preg_match('/^[a-z0-9]{40}$/i', $newinput['api_key'])) {
        $newinput['api_key'] = '';
    }
    $newinput['include_groups'] = trim($input['include_groups']);
    if (!preg_match('/^[0-9\,\ ]+$/i', $newinput['include_groups'])) {
        $newinput['include_groups'] = '';
    }
    $newinput['exclude_groups'] = trim($input['exclude_groups']);
    if (!preg_match('/^[0-9\,\ ]+$/i', $newinput['exclude_groups'])) {
        $newinput['exclude_groups'] = '';
    }
    return $newinput;
}

function wc_product_manager_api_section_text()
{
    echo '<p>' . __('Here you can set all the options for using the Product Manager API', 'wc-product-manager-api') . '</p>';
}

function wc_product_manager_api_setting_api_key()
{
    $options = get_option('wc_product_manager_api_options');
    echo "<input id='wc_product_manager_api_setting_api_key' name='wc_product_manager_api_options[api_key]' type='text' class='regular-text' value='" . esc_attr($options['api_key']) . "' />";
}

function wc_product_manager_api_setting_include_groups()
{
    $options = get_option('wc_product_manager_api_options');
    echo "<input id='wc_product_manager_api_setting_include_groups' name='wc_product_manager_api_options[include_groups]' type='text' class='regular-text' value='" . esc_attr($options['include_groups']) . "' />";
    echo "<p class='description'>Comma separated SKU (1,2,3)</p>";
}

function wc_product_manager_api_setting_exclude_groups()
{
    $options = get_option('wc_product_manager_api_options');
    echo "<input id='wc_product_manager_api_setting_exclude_groups' name='wc_product_manager_api_options[exclude_groups]' type='text' class='regular-text' value='" . esc_attr($options['exclude_groups']) . "' />";
    echo "<p class='description'>Comma separated SKU (1,2,3)</p>";
}
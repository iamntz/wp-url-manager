<?php

/*
Plugin Name: URL Manager (legacy)
Description: Manage URLS
Author: Ionuț Staicu
Version: 1.0.0
Author URI: http://ionutstaicu.com
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NTZ_URL_MANAGER_VERSION', '1.0.0');
define('NTZ_URL_MANAGER_DB_VERSION', '1.0.3');

define('NTZ_URL_MANAGER_BASEFILE', __FILE__);
define('NTZ_URL_MANAGER_URL', plugin_dir_url(__FILE__));
define('NTZ_URL_MANAGER_PATH', plugin_dir_path(__FILE__));

add_action('plugins_loaded', function () {
    load_plugin_textdomain('url-manager', false, dirname(plugin_basename(__FILE__)) . '/lang');
});

if (version_compare(get_option('ntz_url_manager_db_verions', -1), NTZ_URL_MANAGER_DB_VERSION) !== 1) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    global $wpdb;
    dbDelta("CREATE TABLE {$wpdb->prefix}url_manager (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      added_on datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
      url varchar(500) DEFAULT '' NOT NULL,
      title TEXT DEFAULT '' NOT NULL,
      hits bigint NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;");

    update_option('ntz_url_manager_db_verions', NTZ_URL_MANAGER_DB_VERSION);
}

add_action('admin_enqueue_scripts', function () {
    wp_register_script('ntz-url-manager', plugins_url('/url-manager.js', __FILE__),
        ['jquery', 'jquery-ui-autocomplete'], NTZ_URL_MANAGER_VERSION, true);

    wp_enqueue_style('ntz-url-manager-ui', 'https://code.jquery.com/ui/1.12.0/themes/smoothness/jquery-ui.css', [],
        NTZ_URL_MANAGER_VERSION, 'screen');
    wp_enqueue_style('ntz-url-manager', plugins_url('/url-manager.css', __FILE__), ['ntz-url-manager-ui'],
        NTZ_URL_MANAGER_VERSION, 'screen');

    wp_localize_script('ntz-url-manager', 'ntz_url_manager', [
        'nonce' => wp_create_nonce('ntz-url-manager'),
        'redirect_to' => add_query_arg('vezi=', '', home_url('/')),
    ]);

    wp_enqueue_script('ntz-url-manager');
});

add_action('wp_dashboard_setup', function () {
    wp_add_dashboard_widget('ntz_url_manager', 'URL Manager', 'ntz_url_manager_widget');
});

function ntz_url_manager_get_formatted_hits($item)
{
    $markup = sprintf('<span class="widefat" data-title="%s">%s</span>', esc_attr($item->title), esc_attr($item->url));
    $markup .= sprintf('<span class="js-ntz-delete-link dashicons dashicons-no-alt" data-id="%d" data-title="Remove the link"></span>',
        $item->id);
    $markup .= sprintf('<span class="copy-id dashicons dashicons-admin-links" data-title="%d Hits. Click to copy ID. CTRL+Click to copy full url" data-id="%d"></span>',
        $item->hits, $item->id);

    return sprintf('<li>%s</li>', $markup);
}

function ntz_url_manager_widget()
{
    global $wpdb;

    printf('<p class="ntz-link-manager-add"><input type="text" placeholder="Search/Add http://url anchor" class="widefat" /><button class="button-secondary">+</button></p>');

    $hits = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}url_manager ORDER BY hits DESC, id DESC LIMIT 10");

    $formattedHits = '';
    if (is_array($hits)) {
        $formattedHits .= implode("\n", array_map('ntz_url_manager_get_formatted_hits', $hits));
    }
    printf('<ul class="ntz-link-manager-list">%s</ul>', $formattedHits);
}

add_action('wp_ajax_ntz_url_manager_search', function () {
    global $wpdb;
    header('Content-Type: application/json');

    check_ajax_referer('ntz-url-manager', 'ntz_nonce');
    $url = '%' . sanitize_text_field(urldecode($_REQUEST['query'])) . '%';
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}url_manager WHERE url LIKE %s OR title LIKE %s  ORDER BY hits DESC, id DESC LIMIT 20",
        $url, $url));
    echo json_encode($results);
    wp_die();
});


add_action('wp_ajax_ntz_url_manager_delete', function () {
    global $wpdb;
    header('Content-Type: application/json');
    check_ajax_referer('ntz-url-manager', 'ntz_nonce');

    $id = absint($_REQUEST['id']);
    $results = $wpdb->get_results($wpdb->prepare("DELETE FROM {$wpdb->prefix}url_manager WHERE id=%d", $id));
    wp_die();
});


function ntz_get_page_title($url)
{
    $fetch = wp_remote_get($url, [
        'timeout' => 60,
        'redirection' => 10,
        'user-agent' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
    ]);

    if (!is_wp_error($fetch) && wp_remote_retrieve_response_code($fetch) < 299) {
        $body = $fetch['body'];
        $strippedBody = trim(preg_replace('/\s+/', ' ', $body));

        // the reason behind using explode instead of preg_match-ing the whole title tag is that i encountered
        // sites that used more than one <title></title> pairs.
        $explodedBodyTillTitle = explode('</title>', $strippedBody);
        preg_match("/<title>(.*)/", $explodedBodyTillTitle[0], $title);

        return trim(!empty($title[1]) ? $title[1] : $url);
    }

    return '';
}

add_action('wp_ajax_ntz_url_manager_add', function () {
    global $wpdb;

    check_ajax_referer('ntz-url-manager', 'ntz_nonce');
    $request = explode(' ', urldecode($_REQUEST['url']), 2);

    $url = $request[0];

    if (!empty($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}url_manager WHERE url = %s", $url)))) {
        wp_die();
    }

    $title = !empty($request[1]) ? $request[1] : ntz_get_page_title($url);

    $wpdb->insert("{$wpdb->prefix}url_manager", [
        'url' => $url,
        'title' => $title,
    ], ['%s']);

    echo ntz_url_manager_get_formatted_hits(ntz_url_manager_get_url_by_id($wpdb->insert_id));
    wp_die();
});

function ntz_url_manager_get_url_by_id($id)
{
    global $wpdb;
    return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}url_manager WHERE id = {$id} LIMIT 1");
}

add_action('parse_query', function () {
    if (empty($_GET['vezi'])) {
        return;
    }
    global $wpdb;
    $vezi = (int) $_GET['vezi'];

    $url = ntz_url_manager_get_url_by_id($vezi);

    if (!$url) {
        wp_redirect('/invalid-url-id');
        die();
    }

    $wpdb->update(
        "{$wpdb->prefix}url_manager",
        ['hits' => $url->hits + 1],
        ['id' => $url->id],
        ['%d'],
        ['%d']
    );

    wp_redirect($url->url);

    die();
});


add_action('parse_query', function () {
    if (empty($_GET['get_url'])) {
        return;
    }

    header('Content-Type: application/json');
    global $wpdb;

    $vezi = (int) $_GET['get_url'];

    $url = ntz_url_manager_get_url_by_id($vezi);

    $wpdb->update(
        "{$wpdb->prefix}url_manager",
        ['hits' => $url->hits + 1],
        ['id' => $url->id],
        ['%d'],
        ['%d']
    );

    echo json_encode($url);

    die();
});

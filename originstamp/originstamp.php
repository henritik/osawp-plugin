<?php

/*
 * Plugin Name: OriginStamp for Wordpress
 * Plugin URI: http://www.originstamp.org
 * description: Creates a tamper-proof timestamp of your content each time it is modified. The timestamp is created with the Bitcoin blockchain.
 * Version: 0.0.5
 * Author: Thomas Hepp, André Gernandt, Eugen Stroh
 * Author URI: https://github.com/thhepp/, https://github.com/ager, https://github.com/eustro
 * License: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

defined('ABSPATH') OR exit;

define('originstamp_for_wordpress', plugins_url(__FILE__));

// Define api key and email to save for future uses.
define("ORIGINSTAMP_SETTINGS", serialize(array(
    "api_key" => "",
    "email" => ""
)));

/**
 * register hooks, action and bind the functions
 */
register_activation_hook(__FILE__, 'originstamp_create_originstamp_table');
register_uninstall_hook(__FILE__, 'originstamp_on_uninstall');
add_action('save_post', 'originstamp_create_fingerprint');
add_action('admin_menu', 'originstamp_admin_menu');
add_action('wp_head', 'originstamp_request_fingerprints_for_api_key');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'originstamp_action_links');
add_action('init', 'originstamp_download_hash_data');
add_action('template_redirect', 'originstamp_download_hash_data');
add_action('admin_enqueue_scripts', 'originstamp_load_font_awesome_icons');

/**
 * function is loaded to use the fontawesome styles
 */
function originstamp_load_font_awesome_icons()
{
    // register style
    wp_register_style('originstamp_wp_admin_css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css', false, '1.0.0');
    // set
    wp_enqueue_style('originstamp_wp_admin_css');
}

/**
 * creates the originstamp table for the hash information
 */
function originstamp_create_originstamp_table()
{
    // Create data table for local storage at activation.
    if (!current_user_can('activate_plugins'))
        return;

    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    // adding the database name
    add_option("db_table_name", 'originstamp_hash_data');

    $table_name = $wpdb->prefix . get_option('db_table_name');

    $sql = "CREATE TABLE $table_name (
		sha256 varchar(64) UNIQUE NOT NULL,
		time datetime DEFAULT CURRENT_TIMESTAMP,
        post_title tinytext NOT NULL,
        post_content longtext NOT NULL,
		PRIMARY KEY (sha256)
	) $charset_collate;";

    if (is_admin())
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    try {
        $res = dbDelta($sql);
        if (!$res) {
            return;
        }
    } catch (Exception $e) {
        return;
    }
}

/**
 * is called if the plugin is removed from the wordpress plugin page
 */
function originstamp_on_uninstall()
{
    // Remove local data talbe with all content.
    if (!current_user_can('install_plugins'))
        return;
    if (__FILE__ != WP_UNINSTALL_PLUGIN)
        return;
    check_admin_referer('bulk-plugins');

    global $wpdb;

    $table_name = $wpdb->prefix . get_option('db_table_name');
    $sql = 'DROP TABLE IF EXISTS ' . $table_name;
    $wpdb->query($sql);

    delete_option('originstamp');
}

/**
 * registers the originstamp admin menu
 */
function originstamp_admin_menu()
{
    register_setting('originstamp', 'originstamp');

    add_settings_section('originstamp', __('Settings'), 'originstamp_settings_section', 'originstamp');
    add_settings_field('originstamp_description', __('description'), 'originstamp_render_description', 'originstamp', 'originstamp');
    add_settings_field('originstamp_originstamp_render_api_key', __('API Key'), 'originstamp_render_api_key', 'originstamp', 'originstamp');
    add_settings_field('originstamp_originstamp_sender_email', __('Sender Email'), 'originstamp_sender_email', 'originstamp', 'originstamp');
    add_options_page(__('OriginStamp'), __('OriginStamp'), 'manage_options', 'originstamp', 'originstamp_admin_page');
    add_settings_field('originstamp_db_status', __('DB status'), 'originstamp_get_db_status', 'originstamp', 'originstamp');
    add_settings_field('oroginstamp_hash_table', __('Hash table'), 'originstamp_request_fingerprints_for_originstamp_render_api_key', 'originstamp', 'originstamp');
    add_settings_field('originstamp_dev', __('Developers'), 'originstamp_dev_info', 'originstamp', 'originstamp');
}

/**
 * adds the acction links
 * @param $links
 * @return mixed
 */
function originstamp_action_links($links)
{
    array_unshift($links, '<a href="' . admin_url('options-general.php?page=originstamp') . '">Settings</a>');
    return $links;
}

/**
 * dummy functions / do nothing
 */
function originstamp_settings_section()
{
    // do nothing
    ;
}


function originstamp_get_settings_options()
{
    $options = (array)get_option('originstamp');
    return $options;
}

function originstamp_admin_page()
{
    ?>
    <div class="wrap">
        <h2><?php _e('OriginStamp'); ?></h2>
        <?php if (!empty($options['invalid_emails']) && $_GET['settings-updated']) : ?>
            <div class="error">
                <p><?php ?></p>
            </div>
        <?php endif; ?>
        <form action="options.php" method="post">
            <?php settings_fields('originstamp'); ?>
            <?php do_settings_sections('originstamp'); ?>
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>"/>
            </p>
        </form>
    </div>
    <?php
}

/**
 * downloading the original texts which are hashed
 */
function originstamp_download_hash_data()
{
    // Download a data set from plugin data table.
    if (isset($_GET['d'])) {
        $hash_string = $_GET['d'];
        $data = originstamp_get_local_hash($hash_string);
        if (!$data)
            exit('Hash string not found in database.');
        header("Content-type: application/x-msdownload", true, 200);
        header("Content-Disposition: attachment; filename=$hash_string.txt");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo $data;
        exit();
    }
}

/**
 * adds a new hash to the database
 * @param $hash_string
 * @param $post_title
 * @param $post_content
 */
function originstamp_add_hash($hash_string, $post_title, $post_content)
{
    global $wpdb;

    $table_name = $wpdb->prefix . get_option('db_table_name');
    $wpdb->insert($table_name,
        array('sha256' => $hash_string, 'post_title' => $post_title, 'post_content' => $post_content),
        array());
}

/**
 * reads the original text for a certain hash from the wordpress database
 * @param $hash_string
 * @return string
 */
function originstamp_get_local_hash($hash_string)
{
    global $wpdb;
    $table_name = $wpdb->prefix . get_option('db_table_name');
    $sql = "SELECT * FROM $table_name WHERE sha256 = \"$hash_string\"";
    $result = $wpdb->get_row($sql);
    $data = $result->post_title . $result->post_content;

    return $data;
}

/**
 * extracts the fulltext from a specified post id, concats the text and calculates the SHA256
 * @param $post_id
 */
function originstamp_create_fingerprint($post_id)
{
    // Create a SHA256 value from WP post or edit.
    if (wp_is_post_revision($post_id))
        return;
    $title = preg_replace(
        "/[\n\r ]+/",//Also more than one space
        " ",//space
        wp_strip_all_tags(get_the_title($post_id)));
    $content = preg_replace(
        "/[\n\r ]+/",//Also more than one space
        " ",//space
        wp_strip_all_tags(get_post_field('post_content', $post_id)));

    $data = $title . $content;
    $hash_string = hash('sha256', $data);
    $body['hash_string'] = $hash_string;

    originstamp_add_hash($hash_string, $title, $content);

    originstamp_submit_hash($body, $hash_string);
    originstamp_send_confirm_email($data, $hash_string);
}

/**
 * submits the hash to originstamp
 * @param $body
 * @param $hashString
 * @return mixed
 */
function originstamp_submit_hash($body, $hashString)
{
    // Send computed hash value to OriginStamp.
    $options = originstamp_get_settings_options();
    $body['email'] = $options['email'];

    $response = wp_remote_post('https://api.originstamp.org/api/' . $hashString, array(
        'method' => "POST",
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => "1.1",
        'blocking' => true,
        'headers' => array(
            'content-type' => "application/json",
            'Authorization' => $options['originstamp_render_api_key']
        ),
        'body' => json_encode($body),
        'cookies' => array()

    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        $message = "Sorry, we had some issues posting your data to OriginStamp:\n";
        wp_mail($options['email'], "Originstamp: Error.", $message . $error_message);

        return $error_message;
    }

    return $response;
}

function originstamp_send_confirm_email($data, $hash_string)
{
    // Send confirmation Email to user.
    // I no Email address provided, nothing will be sent.
    $instructions = "Please store this Email. You need to hash following value with a SHA256:\n\n";
    $header = "================ START TEXT =================\n";
    $footer = "\n================ END TEXT ===================";
    $options = originstamp_get_settings_options();
    if (!$options['email']) {
        return '';
    }
    $msg = $instructions . $header . $data . $footer;
    $headers = "Content-type: text/plain";
    $temp = fopen('php://temp', 'w+');
    fwrite($temp, $data);
    rewind($temp);
    fpassthru($temp);
    $response = wp_mail($options['email'], "OriginStamp " . $hash_string, $msg, $headers, array($temp));
    fclose($temp);

    return $response;
}

/**
 * requests the table of timestamps (pagination) for the api key
 * @param $offset
 * @param $records
 * @return array
 */
function originstamp_request_hash_table($offset, $records)
{
    // Get hash table for API key.
    /*POST fields for table request.
     email
     hash_string
     comment
     date_created
     originstamp_render_api_key
     offset
     records*/
    $options = originstamp_get_settings_options();
    $body['originstamp_render_api_key'] = $options['originstamp_render_api_key'];

    if ($body['originstamp_render_api_key'] == '')
        return array();

    // Start offset
    $body['offset'] = $offset;
    // Number of records
    $body['records'] = $records;

    $response = wp_remote_post('https://api.originstamp.org/api/table', array(
        'method' => "POST",
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => "1.1",
        'blocking' => true,
        'headers' => array(
            'content-type' => "application/json",
            'Authorization' => $options['originstamp_render_api_key']
        ),
        'body' => json_encode($body),
        'cookies' => array()

    ));

    return $response;
}

/*
* The function renders the developers information for the support
*/
function originstamp_dev_info()
{
    ?>
    Visit us on <a href="https://app.originstamp.org/home">https://app.originstamp.org/home</a><br><br>
    Or contact us:<br>
    <table id="originstamp_dev_info" style="display: inline-table;">
        <tr>
            <td>
                Thomas Hepp
            </td>
            <td>
                <a target="_top" href="mailto:thomas.hepp@uni-konstanz.de?Subject=OriginStamp-wp%20plugin">contact</a>
            </td>
        </tr>
        <tr>
            <td>
                André Gernandt
            </td>
            <td>
                <a target="_top" href="mailto:andre.gernandt@gmail.com?Subject=OriginStamp-wp%20plugin">contact</a>
            </td>
        </tr
        <tr>
            <td>
                Eugen Stroh
            </td>
            <td>
                <a target="_top" href="mailto:eugen.stroh@uni-konstanz.de?Subject=OriginStamp-wp%20plugin">contact</a>
            </td>
        </tr>
    </table>
    <?php
}

/*
* The function adds the FAQ about the plugin to the admin panel
*/
function originstamp_render_description()
{
    ?>
    <p> This plugin saves and stores every single stage of your posts. Anytime you hit the save button while creating or
        editing a post, we will save the stage of your work in local data table. The timestamp is secure within the
        Bitcoin network and verifiable to anyone who is in possession of a coyp of the data.</p><br>
    <p><b>What content is saved?</b>
    <p class="description">We save the post title, the post body as plain text where all layout tags. Line breaks are
        converted to spaces.</p>
    <p><b>What content is timestamped?</b>
    <p class="description">We save the post title, the post body as plain text. To hash the post we concatenate the post
        title and the post body with a space in between. The sha256 of the generated string is being sent to originStamp
        to be timestamped.</p>
    <p><b>How to verify a timestamp?</b>';
    <p class="description">In order to verify the timestamp you would have to download the data, copy the string that is
        stored in the text file and then use any sha256 calculator of your choice to hash the string. After that go to
        OriginStamp and search for the hash. There you will also find further instructions and features.</p>
    <p><b>Where do I get more Information?</b></p>
    <p>Please visit <a target="_blank" href="https://app.originstamp.org/faq">OriginStamp FAQ.</a></p>
    <p><b>You still got questions?</b></p>
    <p>Fee free to contact us, our emails are provide in the develpoer information on the bottom of this page.</p>
    <?php
}

/*
* The method checks the database status from the wordpress plugin and creates an alert if the database does not exists
*/
function originstamp_get_db_status()
{
    global $wpdb;
    $table_name = $wpdb->prefix . get_option('db_table_name');
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        echo '<p style="color: rgb(0, 150, 136)">Database table created: "' . $table_name . '".</p>';
    } else {
        echo '<p style="color: rgb(255, 152, 0)">ERROR: Data table does not exist!</p>';
    }
    echo '<p class="description">Here you can check status of the database that stores hashed post data.</p>';
}

function originstamp_render_api_key()
{
    // Read in API key.
    $options = originstamp_get_settings_options();
    ?>
    <input title="API key" type="text" name="originstamp[originstamp_render_api_key]" size="40"
           value="<?php echo $options['originstamp_render_api_key'] ?>"/>
    <p class="description"><?php _e('An API key is required to create timestamps. Receive your personal key here:') ?>
        <a href="https://originstamp.org/dev">
            <i class="fa fa-sign-in" aria-hidden="true"></i>
        </a></p>
    <?php
}

/**
 * sends a mail with the content of the post
 */
function originstamp_sender_email() {

// Optional:
$options = originstamp_get_settings_options();
?>
<input title="Email" type="text" name="originstamp[email]" size="40" value="<?php echo $options['email'] ?>"/>
<p class="description"><?php _e('Please provide an Email address so that we can send your data. You need to store your data to be able to verify it.') ?>
    <?php
    }

    function originstamp_parse_table($response_json_body)
    {
        echo '<table style="display: inline-table;">';
        echo '<tr><th>Date created</th><th>Hash string (SHA256)</th><th>Status</th><th>Data</th></tr>';
        foreach ($response_json_body->hashes as $hash) {
            // From milliseconds to seconds.
            $date_created = $hash->date_created / 1000;
            $submit_status = $hash->submit_status->multi_seed;
            $hash_string = $hash->hash_string;
            $db_res = originstamp_get_local_hash($hash_string);
            echo '<tr>';
            echo '<td>' . gmdate("Y-m-d H:i:s", $date_created) . '</td>';

            echo '<td>';
            echo '<a href="https://originstamp.org/s/'
                . $hash_string
                . '"'
                . ' target="_blank"'
                . '">'
                . $hash_string
                . '</a>';
            echo '</td>';
            echo '<td>';
            if ($submit_status == 3) {
                try {
                    echo '<i style="color: rgb(0, 150, 136)" class="fa fa-check-circle-o" aria-hidden="true"></i>';
                } catch (Exception $e) {
                    echo $submit_status;
                }
            } else {
                try {
                    echo '<i style="color: rgb(255, 152, 0)" class="fa fa-clock-o" aria-hidden="true"></i>';
                } catch (Exception $e) {
                    echo $submit_status;
                }
            }
            echo '</td>';
            echo '<td>';
            echo "<a href=\"?page=originstamp&d=$hash_string\" title=\"download\" target=\"_blank\">download</a>";
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    function originstamp_request_fingerprints_for_api_key()
    {
        // Maximum number of pages the API will return.
        $limit = 25;

        // Get first record, to determine, how many records there are overall.
        $get_page_info = originstamp_request_hash_table(0, 1);
        if (!$get_page_info)
            return;

        // Handle errors.
        if (is_wp_error($get_page_info)) {
            $error_message = $get_page_info->get_error_message();
            echo '<p style="color: rgb(255, 152, 0)">An error occurred while retrieving hash table:</p><br/>' . $error_message;
            return;
        }

        // Extract bory from response.
        $page_info_json_obj = json_decode($get_page_info['body']);

        // Total number of records in the database.
        $total = $page_info_json_obj->total_records;

        // Overall number of pages
        $num_of_pages = ceil($page_info_json_obj->total_records / $limit);

        // GET current page
        $page = min($num_of_pages, filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 1,
                'min_range' => 1,
            ),
        )));
        // Calculate the offset for the query
        $offset = ($page - 1) * $limit;

        // Some information to display to the user
        $start = $offset + 1;
        $end = min(($offset + $limit), $total);

        // The "back" link
        $prevlink = ($page > 1) ? '<a href="?page=originstamp&p=1" title="First page">&laquo;</a> <a href="?page=originstamp' . '&p=' . ($page - 1) . '" title="Previous page">&lsaquo;</a>' : '<span class="disabled">&laquo;</span> <span class="disabled">&lsaquo;</span>';

        // The "forward" link
        $nextlink = ($page < $num_of_pages) ? '<a href="?page=originstamp' . '&p=' . ($page + 1) . '" title="Next page">&rsaquo;</a> <a href="?page=originstamp' . '&p=' . $num_of_pages . '" title="Last page">&raquo;</a>' : '<span class="disabled">&rsaquo;</span> <span class="disabled">&raquo;</span>';

        // Display the paging information
        echo '<p class="description">A list of all your hashes submitted with API key above: <br></p>';
        echo '<div id="paging"><p>', $prevlink, ' Page ', $page, ' of ', $num_of_pages, ' pages, displaying ', $start, '-', $end, ' of ', $total, ' results ', $nextlink, ' </p></div>';

        // Get data from API.
        $response = originstamp_request_hash_table($offset, $limit);
        $response_json_body = json_decode($response['body']);

        // Parse response.
        originstamp_parse_table($response_json_body);

        return;
    }

    ?>

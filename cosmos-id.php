<?php
/**
 * @package Cosmos ID
 */
/*
Plugin Name: Cosmos ID
Plugin URI: https://cosmos-id.surge.sh
Description: Sign in using just your emailaddress
Version: 0.1
Author: Cosmos
Author URI: https://cosmos-id.surge.sh
Text Domain: cosmos-id
*/

add_action('init', function () {
    if (is_user_logged_in()) {
        return;
    }

    if (!isset($_GET['code'])) {
        return;
    }

    $code = $_GET['code'];

    $cosmos_id_client_id = get_option('cosmos_id_client_id');
    $cosmos_id_client_secret = get_option('cosmos_id_client_secret');

    //API URL
    $url = 'https://cosmos-id-api.andev.io/api/v0';

    //create a new cURL resource
    $ch = curl_init($url);

    //setup request to send json via POST
    $data = array(
        'query' => 'query session($id: ID!) {
  viewer {
    session(id: $id) {
      id
      user {
        id
        email
      }
    }
  }
}
',
        'variables' => [
            'id' => $code
        ]
    );
    $payload = json_encode($data);

    //attach encoded JSON string to the POST fields
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    //set the content type to application/json
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type:application/json',
        'Authorization:Bearer ' . base64_encode(implode(':', [$cosmos_id_client_id, $cosmos_id_client_secret]))
    ));

    //return response instead of outputting
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    //execute the POST request
    $result = curl_exec($ch);

    echo curl_getinfo($ch) . '<br/>';
    echo curl_errno($ch) . '<br/>';
    echo curl_error($ch) . '<br/>';

    //close cURL resource
    curl_close($ch);

    $data = json_decode($result);

    $email = $data->data->viewer->session->user->email;

    $user = get_user_by('email', $email);

    // Redirect URL
    if (!is_wp_error($user)) {
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        $redirect_to = user_admin_url();
        wp_safe_redirect($redirect_to);
        exit();
    }
});

// custom start

function h($tag, $props = [], ...$children)
{
    $attrs = [];

    foreach ($props as $key => $value) {
        if ($value == null) continue;
        $attrs[] = $key . '="' . $value . '"';
    }

    $attrs = implode(' ', $attrs);

    $children = array_map(function ($child) {
        if (is_array($child)) {
            return implode('', $child);
        }
        return $child;
    }, $children);

    $children = implode('', $children);

    return '<' . $tag . ' ' . $attrs . '>' . $children . '</' . $tag . '>';
}

add_action('login_form', function () {

    include 'sign-in-button.php';
});

add_action('admin_menu', function () {
    add_options_page('Cosmos ID Options', 'Cosmos ID', 'manage_options', 'cosmos-id-options', function () {

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        if (isset($_POST['cosmos_id_client_id'])) {
            $client_id = $_POST['cosmos_id_client_id'];
            update_option('cosmos_id_client_id', $client_id);
        }

        if (isset($_POST['cosmos_id_client_secret'])) {
            $client_secret = $_POST['cosmos_id_client_secret'];
            update_option('cosmos_id_client_secret', $client_secret);
        }

        $client_id = get_option('cosmos_id_client_id', '');
        $client_secret = get_option('cosmos_id_client_secret', '');

        include_once 'options-form.php';
    });
});
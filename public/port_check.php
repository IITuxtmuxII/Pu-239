<?php

require_once dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'bittorrent.php';
require_once INCL_DIR . 'user_functions.php';
require_once INCL_DIR . 'html_functions.php';
check_user_status();
global $site_config, $CURUSER, $lang;

$lang = array_merge(load_language('global'), load_language('userdetails'));
$stdfoot = [
    'js' => [
        get_file_name('checkport_js'),
    ],
];

if ($CURUSER >= UC_STAFF && !empty($_GET['id']) && is_valid_id($_GET['id'])) {
    $id = (int) $_GET['id'];
} else {
    $id = $CURUSER['id'];
}
$user = format_username($id);

$completed = "
    <h1 class='text-center'>$user Port Status</h1>";
$completed .= main_div("
    <div id='ipports' data-uid='{$id}'></div>
    <div class='columns is-multiline'>
        <input class='has-text-centered column is-4' type='text' id='userip' placeholder='Your Torrent Client IP [" . getip() . "]'>
        <input class='has-text-centered column is-4' type='text' id='userport' placeholder='Your Torrent Client Port'>
        <input class='has-text-centered column is-4' type='text' id='ipport' placeholder='Check Status' readonly>
    </div>
    <div class='has-text-centered'>
        <input id='portcheck' type='submit' value='Test Connectivity' class='button is-small margin20'>
    </div>");

echo stdhead('Check My Ports') . wrapper($completed) . stdfoot($stdfoot);

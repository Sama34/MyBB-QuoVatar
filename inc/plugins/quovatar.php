<?php

if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if(!defined("PLUGINLIBRARY"))
{
    define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

/* --- Plugin API: --- */

function quovatar_info()
{
    return array (
        'name' => 'Quovatar',
        'description' => 'Display avatars for quotes in posts.',
        'website' => 'https://github.com/frostschutz/',
        'author' => 'Andreas Klauer',
        'authorsite' => 'mailto:Andreas.Klauer@metamorpher.de',
        'version' => '0.1',
        'guid' => '',
        'compatibility' => '16*',
        );
}

function quovatar_activate()
{
    global $PL;

    if(!file_exists(PLUGINLIBRARY))
    {
        flash_message("PluginLibrary is missing.", "error");
        admin_redirect("index.php?module=config-plugins");
    }

    $PL or require_once PLUGINLIBRARY;

    if($PL->version < 11)
    {
        flash_message("PluginLibrary is too old.", "error");
        admin_redirect("index.php?module=config-plugins");
    }

    $PL->edit_core(
        'quavatar',
        'inc/class_parser.php',
        array(
            'search' => 'return "<blockquote><cite>',
            'before' => 'if(function_exists("quovatar_blockquote")) { $x = quovatar_blockquote($pid,$message,$username,$postdate,$posttime,$linkback); if($x) return $x; }',
            ),
        true
        );
}

/*
 * function quovatar_deactivate()
 * function quovatar_is_installed()
 * function quovatar_install()
 * function quovatar_uninstall()
 */

/* --- Hooks: --- */

//$plugins->add_hook('showthread_start', 'quovatar_showthread_start');
//$plugins->add_hook('showthread_end', 'quovatar_showthread_end');

/* --- Functions: --- */

function quovatar_blockquote($pid, $message, $username, $postdate, $posttime, $linkback)
{
    global $db, $lang;

    $query = $db->query("
            SELECT u.*
            FROM ".TABLE_PREFIX."posts p
            LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
            WHERE pid='{$pid}'
    ");

    $user = $db->fetch_array($query);

    if($user)
    {
        return "<blockquote><cite><img src=\"{$user['avatar']}\"><span>({$postdate} {$posttime})</span>".htmlspecialchars_uni($username)." $lang->wrote{$linkback}</cite>{$message}</blockquote>\n";
    }
}

/* --- End of file. --- */
?>

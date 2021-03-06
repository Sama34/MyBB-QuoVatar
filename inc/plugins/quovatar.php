<?php
/**
 * This file is part of QuoVatar plugin for MyBB.
 * Copyright (C) 2012 Andreas Klauer <Andreas.Klauer@metamorpher.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

if(!defined('IN_MYBB'))
{
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if(!defined("PLUGINLIBRARY"))
{
    define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

/* --- Global: --- */

global $quovatar_cache, $quovatar_lazy, $templatelist;

$quovatar_cache = array();
$quovatar_lazy = array();

switch(THIS_SCRIPT)
{
    case 'newreply.php':
    case 'newthread.php':
    case 'showthread.php':
        $templatelist .= ',quovatar';
}

/* --- Hooks: --- */

$plugins->add_hook('parse_message_start', 'quovatar_parse_message');
$plugins->add_hook('pre_output_page', 'quovatar_pre_output_page');

/* --- Plugin API: --- */

function quovatar_depend()
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
}

function quovatar_info()
{
    return array (
        'name' => 'QuoVatar',
        'description' => 'Display avatars for quotes in posts.',
        'website' => 'https://github.com/frostschutz/',
        'author' => 'Andreas Klauer',
        'authorsite' => 'mailto:Andreas.Klauer@metamorpher.de',
        'version' => '0.4',
        'guid' => '1dadb9af8f375f61ee9776e531a80599',
        'compatibility' => '18*',
        );
}

function quovatar_activate()
{
    global $PL;

    quovatar_depend();

    $PL->settings(
        'quovatar',
        'QuoVatar',
        'QuoVatar? QuoVatar!',
        array(
            'default' => array(
                'title' => 'Default Avatar',
                'optionscode' => 'text',
                'value' => 'images/avatars/clear_avatar.gif'
                )
            )
        );

    $PL->templates(
        'quovatar',
        'QuoVatar',
        array(
            '' => '<blockquote style="padding: 5px; padding-left: 60px;"><img src="{$avatar}" style="width: 50px; margin-left: -55px; float: left;" /><cite><span>({$date} {$time})</span>{$name} {$lang->wrote}{$gotopost}</cite>{$message}<div class="clear"></div></blockquote><br />',
            )
        );

    $result = $PL->edit_core(
        'quovatar',
        'inc/class_parser.php',
        array(
            'search' => 'eval("\$mycode_quote =',
            'before' => 'if(function_exists("quovatar_quote")) { return quovatar_quote($pid,$message,$username,$postdate,$posttime,$linkback);  }',
            ),
        true
        );

    if($result !== true)
    {
        flash_message('Could not apply changes to inc/class_parser.php. Is the file writable?', 'error');
        admin_redirect('index.php?module=config-plugins');
    }
}

/*
 * function quovatar_deactivate()
 * function quovatar_install()
 * function quovatar_uninstall()
 */

function quovatar_is_installed()
{
    global $db;

    $query = $db->simple_select('templategroups', 'title', "title='quovatar'");

    return $db->fetch_array($query);
}

function quovatar_uninstall()
{
    global $PL;

    quovatar_depend();

    $PL->settings_delete('quovatar');
    $PL->templates_delete('quovatar');
    $PL->edit_core('quovatar', 'inc/class_parser.php', array(), true);
    $PL->edit_core('quavatar', 'inc/class_parser.php', array(), true); // typo in 0.1
}

/* --- Functions: --- */

function quovatar_parse_message()
{
    global $quovatar_cache, $post, $settings;

    if((int)$post['pid'] && isset($post['avatar']))
    {
        if($post['avatar'])
        {
            $quovatar_cache[(int)$post['pid']] = htmlspecialchars_uni($post['avatar']);
        }
        else
        {
            $quovatar_cache[(int)$post['pid']] = htmlspecialchars_uni($settings['quovatar_default']);
        }
    }
}


function quovatar_pre_output_page(&$contents)
{
    global $mybb, $db, $lang, $quovatar_cache, $quovatar_lazy, $settings;

    if($quovatar_lazy)
    {
        $pidlist = implode(',', array_keys($quovatar_lazy));

        $query = $db->query("
            SELECT p.pid, u.avatar
            FROM ".TABLE_PREFIX."posts p
            LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
            WHERE pid IN ({$pidlist})
        ");

        $tr = array();

        foreach($quovatar_lazy as $key => $value)
        {
            $tr[$value] = $settings['quovatar_default'];
        }

        while($row = $db->fetch_array($query))
        {
            if($row['avatar'])
            {
                $tr[$quovatar_lazy[$row['pid']]] = htmlspecialchars_uni($row['avatar']);
            }
        }

        $contents = strtr($contents, $tr);
    }

    unset($quovatar_lazy);

    return $contents;
}


function quovatar_quote($pid, $message, $name, $date, $time, $gotopost)
{
    global $mybb, $db, $lang, $quovatar_cache, $quovatar_lazy, $templates, $settings;

    $pid = (int)$pid;

    if(isset($quovatar_cache[$pid]))
    {
        // do naught
    }

    else
    {
        // Lazy mode:
        $quovatar_lazy[$pid] = $quovatar_cache[$pid] = random_str()."_{$pid}";
    }

    $name = htmlspecialchars_uni($name);
    $avatar = $quovatar_cache[$pid];

    eval('$quovatar = "'.trim($templates->get('quovatar', 1, 0)).'";');

    return $quovatar;
}

/* --- End of file. --- */
?>

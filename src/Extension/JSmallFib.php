<?php
/**
 * @package Plugin JSmallfib for Joomla! 5.x/6.x
 * @version 5.0
 * @author ConseilGouz
 * @ from Enrico Sandoli
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved
 * @license GNU/GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 **/

/**
 * *************************************************************************
 *
 * This file is part of jsmallfib
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * A copy of the GNU General Public License is on <http://www.gnu.org/licenses/>.
 *
 * **************************************************************************
 *
 * This plugin has been written by Enrico Sandoli based on the original
 * enCode eXplorer v4 by Marek Rei. Because the code works within the Joomla!
 * environment, the original password protection has been replaced with a
 * new access rights system. The ability to delete files and folders (if empty)
 * has also been added to the original code, together with some extra security
 * checks to forbid access to areas outside the intended repositories.
 *
 * For info on usage, please refer to the plugin configuration page within
 * the administrator site in Joomla!
 *
 * **************************************************************************
 *
 * Module extended, corrected and modified in several ways by
 * Erik Liljencrantz, erik@eldata.se, http://www.eldata.se
 * marked below as /ErikLtz
 *
 * One special correction: the module used urldecode on $_GET-variables
 * which is a no-no. From Google:
 * A reminder: if you are considering using urldecode() on a $_GET
 * variable, DON'T!
 * Though delfile and delfolder is double urlencoded so these still have
 * the urldecode there.
 *
 * *************************************************************************
 */
namespace Lomart\Plugin\Content\JSmallFib\Extension;
// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\Archive\Archive;
use Joomla\Archive\Zip;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;

define('LOG_TYPE_TEXT', 0);
define('LOG_TYPE_JSON', 1);
define('LOG_TYPE_RDBM', 2);

// log status
define('LOG_STATUS_DISABLED', 0);
define('LOG_STATUS_LOG_NO_MAIL', 1);
define('LOG_STATUS_LOG_AND_MAIL', 2);

// log file
define('LOG_FILE_SINGLE', 0);
define('LOG_FILE_MULTIPLE', 1);

// log action types
define('LOG_ACTION_UPLOAD', 1);
define('LOG_ACTION_DOWNLOAD', 2);
define('LOG_ACTION_DELFOLDER', 3);
define('LOG_ACTION_DELFILE', 4);
define('LOG_ACTION_RESTOREFILE', 5);
define('LOG_ACTION_NEWFOLDER', 6);
define('LOG_ACTION_RENFOLDER', 7);
define('LOG_ACTION_RENFILE', 8);
define('LOG_ACTION_UNZIP', 9);

// log action results
define('LOG_ACTION_RESULT_OK', 0);
define('LOG_ACTION_UPLOAD_ERROR_1', 1);
define('LOG_ACTION_UPLOAD_ERROR_2', 2);
define('LOG_ACTION_UPLOAD_ERROR_3', 3);
define('LOG_ACTION_UPLOAD_ERROR_4', 4);
define('LOG_ACTION_UPLOAD_ERROR_5', 5);
define('LOG_ACTION_UPLOAD_ERROR_6', 6);
define('LOG_ACTION_UPLOAD_ERROR_7', 7);
define('LOG_ACTION_UPLOAD_ERROR_8', 8);

class JSmallFib extends CMSPlugin implements SubscriberInterface
{
    public $DEBUG_enabled;

    public $default_absolute_path;

    public $baselink;

    public $imgdirNavigation;

    public $imgdirExtensions;

    public $option;

    public $view;

    public $id;

    public $Itemid;

    public $display_currentdirectory;

    public $display_filesize;

    public $display_filedate;

    public $date_format;

    public $display_filetime;

    public $display_seconds;

    public $filesize_separator;

    public $filter_list_allow;

    public $filter_list_width;

    public $encode_to_utf8;

    public $icon_width;

    public $icon_padding;

    public $border_radius;

    public $use_box_shadow;

    public $shadow_width;

    public $shadow_blur;

    public $shadow_color;

    public $box_distance;

    public $table_width;

    public $min_row_height;

    public $highlighted_color;

    public $oddrows_color;

    public $evenrows_color;

    public $framebox_bgcolor;

    public $framebox_border;

    public $framebox_linetype;

    public $framebox_linecolor;

    public $errorbox_bgcolor;

    public $errorbox_border;

    public $errorbox_linetype;

    public $errorbox_linecolor;

    public $successbox_bgcolor;

    public $successbox_border;

    public $successbox_linetype;

    public $successbox_linecolor;

    public $uploadbox_bgcolor;

    public $uploadbox_border;

    public $uploadbox_linetype;

    public $uploadbox_linecolor;

    public $inputbox_bgcolor;

    public $inputbox_border;

    public $inputbox_linetype;

    public $inputbox_linecolor;

    public $header_bgcolor;

    public $line_bgcolor;

    public $line_height;

    public $cur_sort_by;

    public $cur_sort_as;

    public $default_sort_nat;

    public $thumbsize;

    public $unzip_allow;
    
    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 4.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' 	=> 'onContentPrepare',
        ];
    }


    public function __construct(&$subject, $config = [])
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();
    }

    public function onContentPrepare(ContentPrepareEvent $event) // ($context, &$article, &$params, $limitstart = 0)
    {
        $version_number = "5.0";

        $context = $event->getContext();
        $article = $event->getItem();
        $params = $event->getParams();
        
        // return if manually disabled in this article (needed for demo purposes)
        if (strstr($article->text, "jsmallfib_disabled_here")) {
            $article->text = preg_replace("/jsmallfib_disabled_here/", "", $article->text);
            return;
        }

        // check article text; if it is NOT in the form of a jsmallfib command than return
        $regex = '/{(jsmallfib)\s*(.*?)}/i';
        $command_match = array();
        $command_match_found = preg_match($regex, $article->text, $command_match);

        // return if command is not found
        if (! $command_match_found) {
            return;
        }

        // only allow article view (if section or category view, just output a reference to the repository)
        $view = Factory::getApplication()->getInput()->get('view', 0);

        if (strcmp(strtoupper($view), "ARTICLE") && // if not an article
        strcmp(strtoupper($view), "DETAILS") && // if not an element of EventList
        strcmp(strtoupper($view), "ITEM") && // if not a K2 page
        strcmp(strtoupper($view), "ITEMS")) { // if not a FlexiContent page
            // disable repository display
            $article->text = preg_replace("/{(jsmallfib)\s*(.*?)}/i", Text::_('only_article_view'), $article->text);
            return;
        }

        // check if the article was written or modified by a trusted author
        if ($this->article_written_by_untrusted_author($article)) {
            $article->text = preg_replace("/{(jsmallfib)\s*(.*?)}/i", Text::_('untrusted_author'), $article->text);
            return;
        }

        // GOT HERE SO GO AHEAD AND PROCESS THE COMMAND

        // set error reporting level
        // error_reporting(E_ALL ^ (E_NOTICE | E_WARNING));

        // this is needed to solve the blank page problem with a long list of files:
        // see solution on http://forum.joomla.org/viewtopic.php?p=1679517
        ini_set('pcre.backtrack_limit', - 1);
        ini_set('pcre.recursion_limit', - 1);

        // get default parameters
        $this->DEBUG_enabled = $this->params->def('DEBUG_enabled', "0");

        $this->encode_to_utf8 = $this->params->def('encode_to_utf8', "1");

        $this->display_currentdirectory = $this->params->def('display_currentdirectory', '1');

        $this->display_filesize = $this->params->def('display_filesize', '1');
        $this->display_filedate = $this->params->def('display_filedate', '1');
        $this->date_format = $this->params->def('date_format', 'dd_mm_yyyy_slashsep');
        $this->display_filetime = $this->params->def('display_filetime', '1');
        $this->display_seconds = $this->params->def('display_seconds', '1');
        $this->filesize_separator = $this->params->def('filesize_separator', '.');

        $this->filter_list_allow = $this->params->def('filter_list_allow', '1');
        $this->filter_list_width = $this->params->def('filter_list_width', '220');

        $this->imgdirNavigation = "plugins/content/jsmallfib/media/" . $this->params->def('js_iconset', 'smallerik') . "/navigationIcons/";
        $this->imgdirExtensions = "plugins/content/jsmallfib/media/" . $this->params->def('js_iconset', 'smallerik') . "/extensionsIcons/";

        $this->icon_width = $this->params->def('js_icon_width', 32);
        $this->icon_padding = $this->params->def('js_icon_padding', 12);

        // ensure original size and padding for original iconset (small icons, fixed size)
        if (! strcmp($this->params->def('js_iconset', 'smallerik'), 'original')) {
            $this->icon_width = 18;
            $this->icon_padding = 5;
        }

        $this->border_radius = $this->params->def('border_radius', 5);
        $this->use_box_shadow = $this->params->def('use_box_shadow', 1);
        $this->shadow_width = $this->params->def('shadow_width', 3);
        $this->shadow_blur = $this->params->def('shadow_blur', 5);
        $this->shadow_color = $this->params->def('shadow_color', 100);

        $this->box_distance = $this->params->def('box_distance', 10);

        $this->min_row_height = $this->params->def('min_row_height', 40);
        $this->highlighted_color = $this->params->def('highlighted_color', "FFD");
        $this->oddrows_color = $this->params->def('oddrows_color', "F9F9F9");
        $this->evenrows_color = $this->params->def('evenrows_color', "FFFFFF");

        $this->framebox_bgcolor = $this->params->def('framebox_bgcolor', "FFFFFF");
        $this->framebox_border = $this->params->def('framebox_border', "1");
        $this->framebox_linetype = $this->params->def('framebox_linetype', "solid");
        $this->framebox_linecolor = $this->params->def('framebox_linecolor', "CDD2D6");

        $this->errorbox_bgcolor = $this->params->def('errorbox_bgcolor', "FFE4E1");
        $this->errorbox_border = $this->params->def('errorbox_border', "1");
        $this->errorbox_linetype = $this->params->def('errorbox_linetype', "solid");
        $this->errorbox_linecolor = $this->params->def('errorbox_linecolor', "F8A097");

        $this->successbox_bgcolor = $this->params->def('successbox_bgcolor', "E7F6DC");
        $this->successbox_border = $this->params->def('successbox_border', "1");
        $this->successbox_linetype = $this->params->def('successbox_linetype', "solid");
        $this->successbox_linecolor = $this->params->def('successbox_linecolor', "66B42D");

        $this->uploadbox_bgcolor = $this->params->def('uploadbox_bgcolor', "FFFFFF");
        $this->uploadbox_border = $this->params->def('uploadbox_border', "1");
        $this->uploadbox_linetype = $this->params->def('uploadbox_linetype', "solid");
        $this->uploadbox_linecolor = $this->params->def('uploadbox_linecolor', "CDD2D6");

        $this->header_bgcolor = $this->params->def('header_bgcolor', "FFFFFF");

        $this->line_bgcolor = $this->params->def('line_bgcolor', "CDD2D6");
        $this->line_height = $this->params->def('line_height', "1");

        $this->inputbox_bgcolor = $this->params->def('inputbox_bgcolor', "FFFFFF");
        $this->inputbox_border = $this->params->def('inputbox_border', "1");
        $this->inputbox_linetype = $this->params->def('inputbox_linetype', "solid");
        $this->inputbox_linecolor = $this->params->def('inputbox_linecolor', "CDD2D6");

        $is_direct_link_to_files = $this->params->def('is_direct_link_to_files', 0); // 0 for link through download_file; 1 for direct link in same window; 2 for direct link in new window

        $default_file_chmod = $this->params->def('default_file_chmod', "0664");
        $default_file_chmod = '0' . ltrim($default_file_chmod, "0");
        $default_file_chmod = octdec($default_file_chmod); // convert octal mode to decimal

        $default_dir_chmod = $this->params->def('default_dir_chmod', "0775");
        $default_dir_chmod = '0' . ltrim($default_dir_chmod, "0");
        $default_dir_chmod = octdec($default_dir_chmod); // convert octal mode to decimal

        // GROUPBOUND backend parameters
        $groupbound_prefix_use = $this->params->def('groupbound_prefix_use', 1); // needed because setting a default value will not allow prefix to be an empty string
        $groupbound_prefix = $groupbound_prefix_use ? $this->params->def('groupbound_prefix', "Shared area for group ID") : "";
        $groupbound_suffix = $this->params->def('groupbound_suffix', "");
        $groupbound_parameter = $this->params->def('groupbound_parameter', 0); // 0 for ID, 1 for TITLE

        // remove magic quotes if needed
        if (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) {

            function js_stripslashes_deep($value)
            {
                $value = is_array($value) ? array_map('js_stripslashes_deep', $value) : stripslashes($value);
                return $value;
            }

            $_POST = array_map('js_stripslashes_deep', $_POST);
            $_GET = array_map('js_stripslashes_deep', $_GET);
            $_COOKIE = array_map('js_stripslashes_deep', $_COOKIE);
            $_REQUEST = array_map('js_stripslashes_deep', $_REQUEST);
        }

        // split the article text in two parts (before and after the FIRST occurrence of the command)
        $text_array = array();
        $text_array = preg_split($regex, $article->text, 2);

        // CHECK ACCESS RIGHTS

        // get access rights (they are in the format [<optional 'g' or 'G'>userid:permission], but would also work
        // without brackets and/or separated by commas or other chars (excluding ':')

        $access_rights_args = array();
        $access_rights_args_found = preg_match_all("/(g?\d+|reg|guest|thumbsize|sortby|sortas|sortnat|unzip|width):\d+/i", $command_match[0], $access_rights_args);

        // get current userid
        $user = Factory::getApplication()->getIdentity();
        $userid = $user->id;
        $username = $user->name;
        $user_username = $user->username; // used for userbound repositories

        if ($this->DEBUG_enabled) {
            echo "userid [" . $userid . "]<br />";
            echo "username [" . $username . "]<br />";
            echo "user_username [" . $user_username . "]<br />";
        }

        if (! $username) {
            $username = Text::_('unregistered_visitor');
        }
        $remote_address = $_SERVER['REMOTE_ADDR'];
        if (! $remote_address) {
            $remote_address = Text::_('unavailable');
        }

        // CHECK COMMAND OPTIONS FOR PERMISSIONS SET FOR THE CURRENT USER ID
        $access_rights = 0;
        $access_rights_userid_matched = 0;

        if ($userid && $access_rights_args_found) {
            foreach ($access_rights_args[0] as $access_rights_pair) {
                list($tmp_userid, $tmp_permission) = explode(":", $access_rights_pair);
                if ($this->DEBUG_enabled) {
                    echo "1. Found command option PAIR [$tmp_userid]:[$tmp_permission]<br />";
                }
                // TODO document 'guest' command option
                if ($tmp_userid == $userid) {
                    $access_rights = $tmp_permission;
                    $access_rights_userid_matched = 1;
                    break;
                }
            }
        }

        // ASSIGN DEFAULT REGISTERED USER RIGHTS
        if (! $access_rights_userid_matched && $userid) {
            $access_rights = $this->params->def('default_reguser_access_rights', 5);

            // check for override
            if ($access_rights_args_found) {
                foreach ($access_rights_args[0] as $access_rights_pair) {
                    list($tmp_userid, $tmp_permission) = explode(":", $access_rights_pair);
                    if ($this->DEBUG_enabled) {
                        echo "2. Found command option PAIR [$tmp_userid]:[$tmp_permission]<br />";
                    }

                    if (! strcasecmp($tmp_userid, "REG")) {
                        $access_rights = $tmp_permission;
                        break;
                    }
                }
            }
        }

        // FIND DEFAULT VISITOR RIGHTS (this constitutes the minimum rights)
        $default_visitor_access_rights = $this->params->def('default_visitor_access_rights', 1); // this will be the minimum access rights in all cases

        if ($access_rights_args_found) {
            foreach ($access_rights_args[0] as $access_rights_pair) {
                list($tmp_userid, $tmp_permission) = explode(":", $access_rights_pair);
                if ($this->DEBUG_enabled) {
                    echo "3. Found command option PAIR [$tmp_userid]:[$tmp_permission]<br />";
                }
                // TODO we need to document 'guest' command option
                if (! strcasecmp($tmp_userid, "GUEST") || ! strcmp($tmp_userid, "0")) {
                    $default_visitor_access_rights = $tmp_permission;
                    break;
                }
            }
        }

        // ensure registered users have at least visitors' rights
        $access_rights = max($access_rights, $default_visitor_access_rights);

        // FOR Joomla! 1.6 USERGROUPS

        // get the usergroup (there can be more than one) the user belongs to
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        //         $query = $db->getQuery(true);
        //         $query->select('g.id,g.title');
        //         $query->from($db->quoteName('#__usergroups', 'g'));
        //         $query->join('INNER', $db->quoteName('#__user_usergroup_map', 'm') . ' ON g.id = m.group_id');
        //         $query->where('m.user_id  = ' . $userid);

        $query = "SELECT #__usergroups.id AS usergroup_id, #__usergroups.title AS usergroup_title "
            ."FROM #__user_usergroup_map LEFT JOIN #__usergroups ON #__usergroups.id=#__user_usergroup_map.group_id "
                ."WHERE #__user_usergroup_map.user_id='".$userid."'";

        $db->setQuery($query);
        try {
            $row = $db->loadObjectList();
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage('SQL error '. $e->getCode() .' '. $e->getMessage());
            return false;
        }

        $usergroup_memberships = count($row);

        if ($usergroup_memberships) {
            $usergroup_id = array();
            $usergroup_title = array();
        }
        for ($i = 0; $i < count($row); $i++) {
            $usergroup_id[$i] = $row[$i]->usergroup_id;
            $usergroup_title[$i] = $row[$i]->usergroup_title;
        }

        // if a user belongs to more than one group, initially set the GROUPBOUND repository to point to the first group,
        // but provide links to switch between groups of membership (using cookies)
        if (isset($_COOKIE['selected_usergroup_index'])) {
            $selected_usergroup_index = $_COOKIE['selected_usergroup_index'];
        } else {
            $selected_usergroup_index = 0;
        }

        // set up links to other groupbound repositories (to be enabled if user belongs to more than one usergroup and GROUPBOUND keyword is used either in default path or in repository)
        $enable_usergroup_switch_links = 0;
        if ($usergroup_memberships > 1) {
            $usergroup_switch_links = "</div><div id='JS_FILES_DIV'>" . "<table>";
        }
        for ($i = 0; $i < $usergroup_memberships; $i++) {
            if ($i == $selected_usergroup_index) {
                continue;
            }
            $usergroup_switch_links .= "<tr class='groupSwitch'>" . "<td class='groupSwitchIcon'><img src=\"" . $this->imgdirNavigation . "switch.png\"></td>" . "<td>" . Text::sprintf('switch_to_userbound_repository_for_group', "TMP_BASELINK&selected_usergroup_index=" . $i, $usergroup_title[$i]) . "</td>" . "</tr>";
        }
        if ($usergroup_memberships > 1) {
            $usergroup_switch_links .= "</table>";
        }

        // FIND USERGROUP RIGHTS (if a specific user ID match has not been found)

        if ($userid && ! $access_rights_userid_matched && $access_rights_args_found) {
            $pick_usergroup_lowest_rights = $this->params->def('pick_usergroup_lowest_rights', 1); // TODO create this parameter in XML and translation files

            // check if any of the specific access rights apply to the current user
            $first_found = 0;
            foreach ($access_rights_args[0] as $access_rights_pair) {
                list($tmp_userid, $tmp_permission) = explode(":", $access_rights_pair);
                if ($this->DEBUG_enabled) {
                    echo "4. Found command option PAIR [$tmp_userid]:[$tmp_permission]<br />";
                }

                if ($tmp_userid[0] == 'g' || $tmp_userid[0] == 'G') {
                    $tmp_userid[0] = ' ';
                    $tmp_userid = ltrim($tmp_userid);

                    if (in_array($tmp_userid, $usergroup_id)) {
                        if (! $first_found) {
                            $first_found = 1;
                            $access_rights = $tmp_permission;
                        } else {
                            if ($pick_usergroup_lowest_rights) {
                                $access_rights = min($access_rights, $tmp_permission);
                            } else {
                                $access_rights = max($access_rights, $tmp_permission);
                            }
                        }
                    }
                }
            }
            // ensure visitors' rights is still minimum
            if ($first_found) {
                $access_rights = max($access_rights, $default_visitor_access_rights);
            }
        }

        // if access rights have not been defined, or they are lower than user 0's (this prevents a registered user having lower access than a visitor)
        // TODO

        // CHECK COMMAND OPTIONS FOR DEFAULT SORT OVERRIDE
        $default_sort_by = $this->params->def('default_sort_by', "name");
        $default_sort_as = $this->params->def('default_sort_as', "desc");
        $this->default_sort_nat = $this->params->def('default_sort_nat', 1);

        if ($access_rights_args_found) {
            foreach ($access_rights_args[0] as $access_rights_pair) {
                list($tmp_userid, $tmp_permission) = explode(":", $access_rights_pair);
                // echo "FOUND PAIR : [$tmp_userid], [$tmp_permission]<br />";

                // if overriding default sorting backend parameters
                if (! strcasecmp($tmp_userid, "SORTBY")) {
                    switch ($tmp_permission) {
                        case 1:
                            $default_sort_by = "name";
                            break;
                        case 2:
                            $default_sort_by = "size";
                            break;
                        case 3:
                            $default_sort_by = "changed";
                            break;
                    }
                }
                if (! strcasecmp($tmp_userid, "SORTAS")) {
                    switch ($tmp_permission) {
                        case 1:
                            $default_sort_as = "asc";
                            break;
                        case 2:
                            $default_sort_as = "desc";
                            break;
                    }
                }
                if (! strcasecmp($tmp_userid, "SORTNAT")) {
                    switch ($tmp_permission) {
                        case 0:
                            $this->default_sort_nat = 0;
                            break;
                        case 1:
                            $this->default_sort_nat = 1;
                            break;
                    }
                }
            }
        }

        // CHECK COMMAND OPTIONS FOR THUMBSIZE OVERRIDE
        $this->thumbsize = $this->params->def('thumbsize', 60);

        if ($access_rights_args_found) {
            foreach ($access_rights_args[0] as $access_rights_pair) {
                list($tmp_cmd, $tmp_value) = explode(":", $access_rights_pair);
                // echo "FOUND PAIR : [$tmp_userid], [$tmp_permission]<br />";

                // if overriding default sorting backend parameters
                if (! strcasecmp($tmp_cmd, "THUMBSIZE")) {
                    $this->thumbsize = $tmp_value;
                }
            }
        }

        // CHECK COMMAND OPTIONS FOR UNZIP ENABLING
        $this->unzip_allow = $this->params->def('unzip_allow', 1);

        if ($access_rights_args_found) {
            foreach ($access_rights_args[0] as $access_rights_pair) {
                list($tmp_cmd, $tmp_value) = explode(":", $access_rights_pair);

                if (! strcasecmp($tmp_cmd, "UNZIP")) {
                    $this->unzip_allow = $tmp_value;
                }
            }
        }

        // CHECK COMMAND OPTIONS FOR table_width
        $this->table_width = $this->params->def('table_width', 680);
        // echo '$this->table_width = '.$this->table_width;
        if ($access_rights_args_found) {
            foreach ($access_rights_args[0] as $access_rights_pair) {
                list($tmp_cmd, $tmp_value) = explode(":", $access_rights_pair);

                if (! strcasecmp($tmp_cmd, "WIDTH")) {
                    $this->table_width = $tmp_value;
                }
            }
        }

        // DEBUG access rights and other command options
        if ($this->DEBUG_enabled) {
            echo "<br />I am user [" . $username . "] with ID [" . $userid . "]<br />";
            if ($usergroup_memberships) {
                echo "I am member of the following " . $usergroup_memberships . " user groups:<br /><br />";
                for ($i = 0; $i < $usergroup_memberships; $i++) {
                    echo " - user group ID [" . $usergroup_id[$i] . "], title [" . $usergroup_title[$i] . "]<br />";
                }
            } else {
                echo "I am NOT a member of any user group<br />";
            }

            echo "<br />I have access rights [" . $access_rights . "]<br />";

            echo "default_sort_by  : [" . $default_sort_by . "]<br />";
            echo "default_sort_as  : [" . $default_sort_as . "]<br /><br />";
            echo "default_sort_nat : [" . $this->default_sort_nat . "]<br /><br />";
        }

        // GOT HERE SO GO AHEAD WITH DISPLAY

        $plg	= 'plugins/content/jsmallfib/';

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        $wa->addInlineScript($this->do_js());
        $wa->addInlineStyle($this->do_css());

        $error = null;
        $success = null;

        // ***********************************************************************************************************************
        // MANAGE REPOSITORY INFO
        // ***********************************************************************************************************************

        // get the default path parameter and check if this is meant to be expressed as an absolute path or a path relative to the Joomla! root folder
        //
        // however, before doing so, check if the default path is being overriden in this particular command - this is achieved by the command option
        // ABSPATH(current_default_absolute_path) or RELPATH(current_default_relative_path) - This is ONLY POSSIBLE IF the defalt path override parameter is enabled
        //
        // we can then check if a string is found within square brackets [repository], which we take as the repository folder for this command; this folder
        // is located within the default path; repository, which is created if it doesn't exist, may be a keyword, either USERBOUND or GROUPBOUND, to signal
        // jsmallfib to use a user- or group-dependent repository (a repository whose name contains a reference to a user or a group of users)

        // see if overriding default path (if enabled from the backend)
        $default_path_override_enabled = $this->params->def('default_path_override_enabled', 0);

        $default_path_override_match = array();
        $default_path_override_found = preg_match("/(abspath|relpath)\(.*?\)/i", $command_match[0], $default_path_override_match);
        if ($default_path_override_enabled && $default_path_override_found) {
            $default_path_override_command_option = $this->chosen_decoding($default_path_override_match[0]);
            $rel_abs_string = substr($default_path_override_command_option, 0, 7);
            $default_path_override_command_option = trim(substr($default_path_override_command_option, 8, strlen($default_path_override_command_option) - 7), "()");
            $default_path_override_command_option = rtrim($default_path_override_command_option, "/\\");

            if (! strcasecmp($rel_abs_string, "ABSPATH")) {
                $is_path_relative = 0;
                $this->default_absolute_path = $default_path_override_command_option;
            } elseif (! strcasecmp($rel_abs_string, "RELPATH")) {
                $is_path_relative = 1;
                $this->default_absolute_path = JPATH_ROOT . '/' . $default_path_override_command_option;
            }
        } else {
            // use backend values for default path and whether it's expressed as a relative or an absolute path
            $is_path_relative = $this->params->def('is_path_relative', 1);

            if ($is_path_relative) {
                $this->default_absolute_path = JPATH_ROOT . '/' . $this->chosen_decoding(trim($this->params->def('default_path', 'jsmallfib_top'), "/\\"));
            } else {
                $this->default_absolute_path = $this->chosen_decoding(rtrim($this->params->def('default_path', JPATH_ROOT . '/' . 'jsmallfib_top'), "/\\"));
            }
        }

        if ($this->DEBUG_enabled) {
            echo "<br />Default jsmallfib path (" . ($is_path_relative ? "RELATIVE" : "ABSOLUTE") . ") = [" . $this->default_absolute_path . "]<br /><br />";
        }

        // we can now check if a repository folder has been explicitly indicated in the command using a string in square brackets
        $repository_match = array();
        $repository_found = preg_match("/\[.*?\]/i", $command_match[0], $repository_match);
        if ($repository_found && ! strstr($repository_match[0], ":")) { // note: avoid mistaking permission pairs for a repository (they contain ':')
            $repository = $this->chosen_decoding(trim($repository_match[0], "[]"));
            $repository = rtrim($repository, "/\\");
        } else {
            $repository = "";
        }

        if ($this->DEBUG_enabled) {
            echo "repository_found [" . $repository_found . "]<br />";
            echo "repository [" . $repository . "]<br />";
        }

        // check if repository is USERBOUND or GROUPBOUND
        if (strtoupper($repository) == "USERBOUND") {
            if (! $userid) { // TODO specialise cases: maybe allow for guest case...
                $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::_('no_access_rights') . "</td>" . // TODO change this warning text to something more appropriate
                    "</tr></table></div></div>";

                $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
                return;
            }

            $userbound_prefix_use = $this->params->def('userbound_prefix_use', 1); // needed because setting a default value will not allow prefix to be an empty string
            $userbound_prefix = $userbound_prefix_use ? $this->params->def('userbound_prefix', "Personal area for user ID") : "";
            $userbound_suffix = $this->params->def('userbound_suffix', "");
            $userbound_parameter = $this->params->def('userbound_parameter', 0); // 0 for ID, 1 for NAME, 2 for USERNAME, 3 for ID USERNAME
            switch ($userbound_parameter) {
                case 0:
                    $userbound_parameter = $userid;
                    break;
                case 1:
                    $userbound_parameter = $username;
                    break;
                case 2:
                    $userbound_parameter = $user_username;
                    break;
                case 3:
                    $userbound_parameter = $userid . " " . $user_username; // peter geiger
            }

            // peter geiger: if user already got higher rights based as a USER or GROUP he is in so be it ...
            if ($this->params->def('default_personal_access_rights') > $access_rights) {
                $access_rights = $this->params->def('default_personal_access_rights', 5);
            }

            $repository = (strlen($userbound_prefix) ? $userbound_prefix . " " : "") . $userbound_parameter . (strlen($userbound_suffix) ? " " . $userbound_suffix : "");

            $userbound_repository_with_id = $repository; // needed in navigation bar
            $userbound_repository_with_name = Text::sprintf('personal_area_for_username', $username); // needed in navigation bar
        } elseif (strtoupper($repository) == "GROUPBOUND") {
            if (! $usergroup_memberships) { // user is not in a group. But maybe this could be a group in itself ... (TODO)
                $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::_('no_access_rights') . "</td>" . // TODO change this warning text to something more appropriate
                    "</tr></table></div></div>";

                $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
                return;
            }

            // peter geiger: if user already got higher rights based as a USER or GROUP he is in so be it ...
            if ($this->params->def('default_group_access_rights') > $access_rights) {
                $access_rights = $this->params->def('default_group_access_rights', 5);
            }

            // enable links to other groupbound repositories
            if ($usergroup_memberships > 1) {
                $enable_usergroup_switch_links = 1;
            }

            $repository = (strlen($groupbound_prefix) ? $groupbound_prefix . " " : "") . ($groupbound_parameter ? $usergroup_title[$selected_usergroup_index] : $usergroup_id[$selected_usergroup_index]) . (strlen($groupbound_suffix) ? " " . $groupbound_suffix : "");
            $groupbound_repository_with_id = $repository; // needed in navigation bar
            $groupbound_repository_with_name = Text::sprintf('shared_area_for_category_name', $usergroup_title[$selected_usergroup_index]); // needed in navigation bar
        }

        // check if default path embeds keyword GROUPBOUND and replace them appropriately
        // ES20110217 modified for Mike MCMullen
        if (stristr($this->default_absolute_path, "GROUPBOUND")) {
            $keyword_replacement = (strlen($groupbound_prefix) ? $groupbound_prefix . " " : "") . ($groupbound_parameter ? $usergroup_title[$selected_usergroup_index] : $usergroup_id[$selected_usergroup_index]) . (strlen($groupbound_suffix) ? " " . $groupbound_suffix : "");
            $this->default_absolute_path = str_replace("GROUPBOUND", $keyword_replacement, $this->default_absolute_path);

            // enable links to other groupbound repositories
            if ($usergroup_memberships > 1) {
                $enable_usergroup_switch_links = 1;
            }
        }

        if ($this->DEBUG_enabled) {
            echo "<br />Default jsmallfib path (" . ($is_path_relative ? "RELATIVE" : "ABSOLUTE") . ") = [" . $this->default_absolute_path . "]<br /><br />";
        }

        // now create the default path folder, only if it doesn't exist already
        if (! file_exists($this->default_absolute_path)) {
            if (! ($rc = @mkdir($this->default_absolute_path, $default_dir_chmod, true))) { // we need to use recursive option TRUE
                $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::sprintf('failed_creating_default_dir', $this->default_absolute_path) . "</td>" . "</tr></table></div></div>";

                $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
                return;
            }
        }

        // we finally set the starting dir to be the actual initial folder of the repository (in absolute terms)
        if ($repository) {
            $starting_dir = $this->default_absolute_path . '/' . $repository;
        } else {
            $starting_dir = $this->default_absolute_path;
        }

        // END OF MANAGE REPOSITORY INFO

        // kick out the user if no access is granted
        if (! $access_rights) {
            $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::_('no_access_rights') . "</td>" . "</tr></table></div></div>";

            $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
            return;
        }

        // if starting dir does not exist, attempt to create it
        if (! file_exists($starting_dir)) {
            if (! ($rc = @mkdir($starting_dir, $default_dir_chmod, true))) { // we need to use recursive option TRUE
                $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::sprintf('failed_creating_repository', $this->chosen_encoding($repository), $this->default_absolute_path) . "</td>" . "</tr></table></div></div>";

                $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
                return;
            }
        }

        // get optional description from within the command: must be in the form desc(this is a description)
        $description_args = array();
        $description_args_found = preg_match_all("/desc\(.*?\)/i", $command_match[0], $description_args);
        if ($description_args_found) {
            $description = substr_replace($description_args[0][0], "", 0, 5);
            $description = substr_replace($description, "", - 1, 1);
        } else {
            $description = "";
        }

        // build link base
        $option = Factory::getApplication()->getInput()->get('option', 0);
        $id = Factory::getApplication()->getInput()->get('id', 0);
        $Itemid = Factory::getApplication()->getInput()->get('Itemid', 0);

        if (! strcmp(strtoupper($view), "ITEM")) {
            // K2
            $this->baselink = Route::_(Uri::base() . 'index.php?option=' . $option . '&view=' . $view . '&layout=item&id=' . $id . '&Itemid=' . $Itemid . '&jsmallfib=1'); // jsmallfib=1 is used in external applications such as jconference
        } else {
            $this->baselink = Route::_(Uri::base() . 'index.php?option=' . $option . '&view=' . $view . '&id=' . $id . '&Itemid=' . $Itemid . '&jsmallfib=1'); // jsmallfib=1 is used in external applications such as jconference
        }

        // The array of folders that will be hidden from the list.
        $hidden_folders_parameter = $this->params->def('hidden_folders', 0);
        $hidden_folders = array();
        $hidden_folders = preg_split("/\s*,+\s*/", $hidden_folders_parameter . ", JS_ARCHIVE, JS_THUMBS");

        // Manage filenames and extensions that will be hidden from the list.
        $hidden_files_parameter = $this->params->def('hidden_files', 0);

        $hidden_extensions = array();
        // $hidden_extensions_found = preg_match_all("/\*{1}\.{1}\w+/", $hidden_files_parameter, $hidden_extensions); // this matches *.php but not *.th.jpg
        $hidden_extensions_found = preg_match_all("/\*{1}\.{1}[\w\.]+/", $hidden_files_parameter, $hidden_extensions); // this matches *.php but also *.th.jpg

        $hidden_prefixes = array();
        $hidden_prefixes_found = preg_match_all("/[^\s]+\*{1}/", $hidden_files_parameter, $hidden_prefixes);

        $hidden_files = array();
        $hidden_files_string = trim(preg_replace("/\*{1}\.{1}\w+/", "", $hidden_files_parameter));
        $hidden_files_string = trim(preg_replace("/[^s]+\*{1}/", "", $hidden_files_string));
        $hidden_files = preg_split("/\s*,+\s*/", $hidden_files_string);

        // ***********************************************************************************************************************
        // Managing input from user actions
        // ***********************************************************************************************************************

        // check if we need to set a new selected_usergroup_index cookie (used to switch between usergroups when viewing a GROUPBOUND
        // repository and the user belongs to more than one group) - we do it here as baselink is now defined
        if (isset($_GET['selected_usergroup_index'])) {
            setcookie('selected_usergroup_index', $_GET['selected_usergroup_index'], time() + 3600 * 24 * 365);
            header('Location: ' . $this->baselink);
        }

        // set variables for logs
        $log_type = $this->params->def('log_type', LOG_TYPE_JSON);
        $logfile_consolidation = $this->params->def('log_file_consolidation', LOG_FILE_MULTIPLE);
        $is_log_path_relative = $this->params->def('is_log_path_relative', 1);

        if ($is_log_path_relative) {
            $default_absolute_log_path = JPATH_ROOT . '/' . $this->chosen_decoding(trim($this->params->def('default_log_path', 'logs'), "/\\"));
        } else {
            $default_absolute_log_path = $this->chosen_decoding(rtrim($this->params->def('default_log_path', JPATH_ROOT . '/' . 'logs'), "/\\"));
        }

        // now create the default log path folder, if it doesn't exist
        if (! file_exists($default_absolute_log_path)) {
            if (! ($rc = @mkdir($default_absolute_log_path, $default_dir_chmod, true))) { // we need to use recursive option TRUE
                $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::sprintf('failed_creating_default_log_dir', $default_absolute_log_path) . "</td>" . "</tr></table></div></div>";

                $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
                return;
            }
        }

        // set the prefix and suffix of the log file(s)
        if ($log_type != LOG_TYPE_RDBM) {
            $logfile_prefix = $default_absolute_log_path . '/' . "jsmallfib_log_" . md5($starting_dir);
            $logfile_extension = $log_type == LOG_TYPE_TEXT ? ".txt" : ".json";
        } else {
            $logfile_prefix = "";
            $logfile_extension = "";
        }

        $log_uploads = $this->params->def('log_uploads', 0);
        $log_downloads = $this->params->def('log_downloads', 0);
        $log_removedfolders = $this->params->def('log_removedfolders', 0);
        $log_removedfiles = $this->params->def('log_removedfiles', 0);
        $log_restoredfiles = $this->params->def('log_restoredfiles', 0);
        $log_newfolders = $this->params->def('log_newfolders', 0);
        $log_newfoldernames = $this->params->def('log_newfoldernames', 0);
        $log_newfilenames = $this->params->def('log_newfilenames', 0);
        $log_unzippedfiles = $this->params->def('log_unzippedfiles', 0);

        $logs_enabled = $log_uploads || $log_downloads || $log_removedfolders || $log_removedfiles || $log_restoredfiles || $log_newfolders || $log_newfoldernames || $log_newfilenames || $log_unzippedfiles ? 1 : 0;

        $today = date("Y-m-d H:i:s");

        // Let's see what folder is being opened and react accordingly
        $dir = $starting_dir;
        $upper_dir = "";

        $a_file_was_removed = 0;
        $removed_folder = "";
        $removed_file = "";
        $restored_file = "";
        $unzipped_file = "";

        if (isset($_GET["dir"]) && strlen($_GET["dir"])) {
            // we had to utf-8 encode delfolders and delfiles for Firefox (special chars are not sent to javascript for delete confirmation)
            if ((isset($_GET["delfile"]) && strlen($_GET["delfile"])) || (isset($_GET["delfolder"]) && strlen($_GET["delfolder"])) || (isset($_GET["extfile"]) && strlen($_GET["extfile"])) || (isset($_GET["restorefile"]) && strlen($_GET["restorefile"]))) {
                $get_dir = html_entity_decode($this->chosen_decoding(urldecode($_GET["dir"]))); // NOTE: Here we need urldecode as delfile is double encoded /ErikLtz
            } else {
                // $get_dir = html_entity_decode($_GET["dir"]); // Removed urldecode on _GET (not delete) /ErikLtz
                $get_dir = $_GET["dir"]; // Removed urldecode on _GET (not delete) /ErikLtz

                // quick check on dir values
                if ($this->DEBUG_enabled) {
                    echo "<br />GET_DIR before 1st check:<br /><br />" . "[" . $_GET["dir"] . "] <b>GET var</b><br />" . "[" . $get_dir . "] <b>get_dir</b><br /><br />";
                }
            }

            // quick check on dir
            if ($this->DEBUG_enabled) {
                echo "<br />GET_DIR 1st check:<br /><br />" . "[" . $get_dir . "] <b>get_dir</b><br /><br />";
            }

            // unmask get_dir now that's been decoded
            $get_dir = $this->unmaskAbsPath($get_dir);

            // quick check on dir
            if ($this->DEBUG_enabled) {
                echo "<br />GET_DIR 2nd check:<br /><br />" . "[" . $get_dir . "] <b>get_dir</b><br /><br />";
            }

            // This format is forbidden (also check for trying to access folders outside the repository root)
            if (preg_match("/\.\.(.*)/", $get_dir) || (strlen($get_dir) == 1 && $get_dir[0] == '/') || (! stristr(str_replace("/", "\\", $get_dir), str_replace("/", "\\", $starting_dir)))) {
                $dir = $starting_dir;
                $upper_dir = "";
            } else {
                // if got here then the user is allowed to view the current folder (remove the upper link if this is the starting_dir)
                $dir = rtrim($get_dir, "/\\");

                // quick check on dir
                if ($this->DEBUG_enabled) {
                    echo "<br />GET_DIR and DIR check:<br /><br />" . "[" . $get_dir . "] <b>get_dir</b><br />" . "[" . $dir . "] <b>dir</b><br /><br />";
                }

                if (strcmp(str_replace("/", "\\", $starting_dir), str_replace("/", "\\", $get_dir))) {
                    $upper_dir = $this->upperDirSetForwardSlashes($this->maskAbsPath($dir));
                }

                // if asking to delete a folder
                if ($access_rights > 4 && isset($_GET["delfolder"]) && strlen($_GET["delfolder"])) {
                    // only works with empty folders
                    $tmpdir = html_entity_decode($dir . '/' . $this->chosen_decoding(urldecode($_GET["delfolder"]))); // NOTE: Here we need urldecode as delfolder is double encoded /ErikLtz
                    $rc = @rmdir($tmpdir);

                    // Check whether directory is gone
                    if (file_exists($tmpdir)) {

                        // Nah, still there, show new error message
                        $error .= Text::sprintf('delete_folder_failed', urldecode($_GET["delfolder"])); // NOTE: Here we need urldecode as delfolder is double encoded /ErikLtz
                    } else {

                        // for logging purposes
                        if ($log_removedfolders && $rc) {
                            $removed_folder = $this->chosen_decoding(urldecode($_GET["delfolder"])); // NOTE: Here we need urldecode as delfolder is double encoded /ErikLtz
                        }
                    }
                } // if asking to delete a file
                elseif ($access_rights > 3 && isset($_GET["delfile"]) && strlen($_GET["delfile"])) {
                    $rc = @unlink(html_entity_decode($dir . '/' . $this->chosen_decoding(urldecode($_GET["delfile"])))); // NOTE: Here we need urldecode as delfile is double encoded /ErikLtz

                    // try removing thumbnail and thumbs dir (will only work if a thumbnail for this file exists and if the thumbs dir is empty)
                    $rc_thumbs = @unlink(html_entity_decode($dir . '/' . "JS_THUMBS" . '/' . $this->chosen_decoding(urldecode($_GET["delfile"]))));
                    $rc_thumbs = @rmdir($dir . '/' . "JS_THUMBS");

                    // for logging purposes
                    if ($log_removedfiles && $rc) {
                        $removed_file = $this->chosen_decoding(urldecode($_GET["delfile"])); // NOTE: Here we need urldecode as delfile is double encoded /ErikLtz
                    }

                    // this is used later to check if the file deleted was the last one of an archive
                    if ($rc) {
                        $a_file_was_removed = 1;
                    }
                } // if asking to extract a file (Francisco Esteban)
                elseif ($this->unzip_allow && $access_rights > 3 && isset($_GET["extfile"]) && strlen($_GET["extfile"])) {
                    $zip = (new Archive())->getAdapter('zip');
                    $rc = $zip->extract(html_entity_decode($dir . '/' . $this->chosen_decoding(urldecode($_GET["extfile"]))), $dir); // NOTE: Here we need urldecode as extfile is double encoded /ErikLtz

                    // for logging purposes
                    $unzipped_file = "";
                    if ($log_unzippedfiles && $rc) {
                        $unzipped_file = $this->chosen_decoding(urldecode($_GET["extfile"])); // NOTE: Here we need urldecode as extfile is double encoded /ErikLtz
                    }
                } // if asking to restore an archived file
                elseif ($access_rights > 3 && isset($_GET["restorefile"]) && strlen($_GET["restorefile"])) {
                    if (! @copy(html_entity_decode($dir . '/' . $this->chosen_decoding(urldecode($_GET['restorefile']))), html_entity_decode($this->upperDirSetForwardSlashes($dir) . '/' . $this->chosen_decoding(urldecode($this->restoreArchiveFilename($_GET['restorefile'])))))) {
                        $error .= Text::sprintf('restorefile_failed', urldecode($_GET['restorefile']));
                        $restored_file = "";
                    } else {
                        @chmod(html_entity_decode($this->upperDirSetForwardSlashes($dir) . '/' . $this->chosen_decoding(urldecode($_GET['restorefile']))), $default_file_chmod);

                        $success .= Text::sprintf('restorefile_success', urldecode($_GET['restorefile']));

                        // for logging purposes
                        if ($log_restoredfiles) {
                            $restored_file = $this->chosen_decoding(urldecode($_GET["restorefile"])); // NOTE: Here we need urldecode as restore is double encoded
                        }
                    }
                }
            }
        }

        // set masked dirs to mask the absolute path (to be used in get strings)
        $masked_dir = $this->maskAbsPath($dir);
        $masked_starting_dir = $this->maskAbsPath($starting_dir);

        // once dir is defined (with absolute path), define the current position (the sub-path under the main default repository folder),
        // and the relative dir (dir relative to the web root)
        $base_web_root = rtrim(isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : JPATH_ROOT, "/\\");
        $current_position = substr($dir, strlen($this->default_absolute_path) + 1, strlen($dir) - strlen($this->default_absolute_path));
        $relative_dir = substr($dir, strlen($base_web_root), strlen($dir) - strlen($base_web_root));

        // if the repository is OUTSIDE the web root then use for files the absolute path
        // (you won't be able to display files left-clicking, but you'll be able to download them right-clicking on them)
        // note: user SERVER[DOCUMENT_ROOT] if available, otherwise use JPATH_ROOT (which adds the joomla folder, which might be below the actual webroot)
        // if(!stristr(str_replace("/", "\\", $dir), str_replace("/", "\\", $_SERVER["DOCUMENT_ROOT"])))
        if (! stristr(str_replace("/", "\\", $dir), str_replace("/", "\\", $base_web_root))) {
            $is_current_position_inside_webroot = 0;
            $relative_dir = $dir;
        } else {
            $is_current_position_inside_webroot = 1;
        }

        if ($this->DEBUG_enabled) {
            echo "<br />Current status of directories:<br /><br />" . "[" . $dir . "] <b>dir</b><br />" . "[" . $starting_dir . "] <b>starting_dir</b><br />" . "[" . JPATH_ROOT . "] <b>JPATH_ROOT</b><br />" . "[" . $base_web_root . "] <b>base_web_root</b><br />" . "[" . $relative_dir . "] <b>relative_dir</b><br />" . "[" . $current_position . "] <b>current_position</b><br />" . "<br />";

            echo "The current position is <b>" . ($is_current_position_inside_webroot ? "INSIDE" : "OUTSIDE") . "</b> the webroot (or the Joomla root if " . "the DOCUMENT_ROOT variable is not available)<br /><br />";
        }

        // set the navigation links to put on top of the repository
        $is_current_position_an_archive = ! strcmp(substr($current_position, - 10), "JS_ARCHIVE");

        if (! $current_position || ($is_current_position_an_archive && ! $this->upperDirSetForwardSlashes($current_position))) {
            $current_position_links = "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_starting_dir) . "'>" . Text::_('toplevel') . "</a>";
        } else {

            if (! $is_current_position_an_archive) {
                // Use current_position to build linked list of directories in $current_position_links [ErikLtz]
                // $arr = explode('/', $current_position); // substituted (same as in else statements) by code line below (and upperDir() set to upperDirSetForwardSlashes() to avoid problems in IE9 (see Paul Tease)
                $arr = explode("/", $this->makeForwardSlashes($current_position));
                $current_position_links = "";
                $tmpdir = $masked_dir;

                for ($i = count($arr) - 1; $i >= 0; $i--) {

                    $current_position_links = "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($tmpdir) . "'>" . $arr[$i] . "</a>" . ($i == count($arr) - 1 ? "" : "&nbsp;<img src=\"" . $this->imgdirNavigation . "arrow_right.png\" />&nbsp;") . $current_position_links;

                    $tmpdir = $this->upperDirSetForwardSlashes($tmpdir);
                }

                // if the repository is not reported in the command (using default top repository) then display link to top level (to default top repository)
                if (! $repository) {
                    $current_position_links = "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_starting_dir) . "'>" . Text::_('toplevel') . "</a>" . "&nbsp;<img src=\"" . $this->imgdirNavigation . "arrow_right.png\" />&nbsp;" . $current_position_links;
                }
            } else {
                // if inside an archive
                // $arr = explode('/', $current_position);
                $arr = explode("/", $this->makeForwardSlashes($current_position));
                $current_position_links = "";
                $tmpdir = $this->upperDirSetForwardSlashes($masked_dir);

                for ($i = count($arr) - 2; $i >= 0; $i--) {

                    $current_position_links = "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($tmpdir) . "'>" . $arr[$i] . "</a>" . ($i == count($arr) - 2 ? "" : "&nbsp;<img src=\"" . $this->imgdirNavigation . "arrow_right.png\" />&nbsp;") . $current_position_links;

                    $tmpdir = $this->upperDirSetForwardSlashes($tmpdir);
                }

                // if the repository is not reported in the command (using default top repository) then display link to top level (to default top repository)
                if (! $repository) {
                    $current_position_links = "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_starting_dir) . "'>" . Text::_('toplevel') . "</a>" . "&nbsp;<img src=\"" . $this->imgdirNavigation . "arrow_right.png\" />&nbsp;" . $current_position_links;
                }
            }
        }

        // once dir is finally completely established, set requested cookies

        // set display filter cookie
        if (isset($_POST['current_filter_list']) && strlen($_POST['current_filter_list'])) {
            setcookie('current_filter_list', $_POST['current_filter_list']);
            header('Location: ' . $this->baselink . "&dir=" . urlencode($masked_dir));
        } elseif (isset($_GET['current_filter_list']) && ! strlen($_GET['current_filter_list'])) {
            setcookie('current_filter_list', "");
            header('Location: ' . $this->baselink . "&dir=" . urlencode($masked_dir));
        }

        // set display upload actions cookie (NOTE: ...display_actions... refers to all actions, for example UPLOAD. In case of more action boxes, only one will be open at any one time)
        if (isset($_GET['set_display_actions_cookie']) && ! strcmp($_GET['set_display_actions_cookie'], "UPLOAD")) {
            setcookie("display_actions", "UPLOAD", time() + 3600 * 24 * 365);
            header('Location: ' . $this->baselink . "&dir=" . urlencode($masked_dir));
        } elseif (isset($_GET['set_display_actions_cookie']) && ! strcmp($_GET['set_display_actions_cookie'], "NO_ACTION")) {
            setcookie("display_actions", "", time() - 3600 * 24 * 365);
            header('Location: ' . $this->baselink . "&dir=" . urlencode($masked_dir));
        }

        // set upload type cookie: this only happens if the default type is SWFUPLOAD (from the backend)
        if (isset($_GET['set_upload_type_cookie']) && ! strcmp($_GET['set_upload_type_cookie'], "HTMLUPLOAD")) {
            setcookie("upload_type", "HTMLUPLOAD", time() + 3600 * 24 * 365);
            header('Location: ' . $this->baselink . "&dir=" . urlencode($masked_dir));
        }

        // now that the current dir is established, log removals/restores and zipping/unzipping registered above
        if ($log_removedfolders && $removed_folder) {
            $this->do_log_this_action($log_removedfolders, LOG_ACTION_DELFOLDER, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($removed_folder), $this->chosen_encoding($relative_dir));
            $removed_folder = "";
        }
        if ($log_removedfiles && $removed_file) {
            $this->do_log_this_action($log_removedfiles, LOG_ACTION_DELFILE, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($removed_file), $this->chosen_encoding($relative_dir));
            $removed_file = "";
        }
        if ($log_restoredfiles && $restored_file) {
            $this->do_log_this_action($log_restoredfiles, LOG_ACTION_RESTOREFILE, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($restored_file), $this->chosen_encoding($this->upperDirSetForwardSlashes($relative_dir)));
            $restored_file = "";
        }
        if ($log_unzippedfiles && $unzipped_file) {
            $this->do_log_this_action($log_unzippedfiles, LOG_ACTION_UNZIP, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($unzipped_file), $this->chosen_encoding($relative_dir));
            $unzipped_file = "";
        }

        // creating the new directory
        if ($access_rights > 2 && isset($_POST['userdir']) && strlen($_POST['userdir']) > 0) {
            $forbidden = array(
                ".",
                "/",
                "\\"
            );
            for ($i = 0; $i < count($forbidden); $i++) {
                $_POST['userdir'] = str_replace($forbidden[$i], "", $_POST['userdir']);
            }
            $tmpdir = html_entity_decode($dir . '/' . $this->chosen_decoding($_POST['userdir']));
            if (! @mkdir($tmpdir)) {
                // Check for existing file with same name and choose different error message [ErikLtz]
                if (file_exists($tmpdir)) {
                    $error .= Text::_('new_folder_failed_exists');
                } else {
                    $error .= Text::_('new_folder_failed');
                }
            } elseif (! @chmod($tmpdir, $default_dir_chmod)) {
                $error .= Text::_('chmod_dir_failed');
            } elseif ($log_newfolders) {
                $this->do_log_this_action($log_newfolders, LOG_ACTION_NEWFOLDER, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $_POST['userdir'], $this->chosen_encoding($relative_dir));
            }
        }

        // changing name to a folder
        if ($access_rights > 2 && isset($_POST['old_foldername']) && strlen($_POST['old_foldername']) > 0 && isset($_POST['new_foldername']) && strlen($_POST['new_foldername']) > 0) {
            $old_foldername = urldecode($_POST['old_foldername']);
            $new_foldername = $this->chosen_decoding($_POST['new_foldername']); // this is utf-8 encoded because it comes from the visible text field

            $forbidden = array(
                ".",
                "/",
                "\\"
            );
            for ($i = 0; $i < count($forbidden); $i++) {
                $old_foldername = str_replace($forbidden[$i], "", $old_foldername);
            }
            for ($i = 0; $i < count($forbidden); $i++) {
                $new_foldername = str_replace($forbidden[$i], "", $new_foldername);
            }
            if (! @rename(html_entity_decode($dir . "/" . $old_foldername), html_entity_decode($dir . "/" . $new_foldername))) {
                $error .= Text::sprintf('folder_rename_failed', $this->chosen_encoding($old_foldername), $this->chosen_encoding($new_foldername));
            } elseif ($log_newfoldernames) {
                $this->do_log_this_action($log_newfoldernames, LOG_ACTION_RENFOLDER, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($old_foldername), $this->chosen_encoding($relative_dir), $this->chosen_encoding($new_foldername));
            }
        }

        // changing name to a file
        if ($access_rights > 2 && isset($_POST['old_filename']) && strlen($_POST['old_filename']) > 0 && isset($_POST['new_filename']) && strlen($_POST['new_filename']) > 0) {
            $old_filename = urldecode($_POST['old_filename']);
            $new_filename = $this->chosen_decoding($_POST['new_filename']);

            $forbidden = array(
                "/",
                "\\"
            );
            for ($i = 0; $i < count($forbidden); $i++) {
                $old_filename = str_replace($forbidden[$i], "", $old_filename);
            }
            for ($i = 0; $i < count($forbidden); $i++) {
                $new_filename = str_replace($forbidden[$i], "", $new_filename);
            }
            if (! @rename(html_entity_decode($dir . "/" . $old_filename), html_entity_decode($dir . "/" . $new_filename))) {
                $error .= Text::sprintf('file_rename_failed', $this->chosen_encoding($old_filename), $this->chosen_encoding($new_filename));
            } elseif ($log_newfilenames) {
                // try removing thumbnail of oldname file (will only work if a thumbnail for this file exists)
                $rc_thumbs = @unlink(html_entity_decode($dir . '/' . "JS_THUMBS" . '/' . $old_filename));

                $this->do_log_this_action($log_newfilenames, LOG_ACTION_RENFILE, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($old_filename), $this->chosen_encoding($relative_dir), $this->chosen_encoding($new_filename));
            } else {
                // try removing thumbnail of oldname file (will only work if a thumbnail for this file exists)
                $rc_thumbs = @unlink(html_entity_decode($dir . '/' . "JS_THUMBS" . '/' . $old_filename));
            }
        }

        // MANAGING UPLOAD// **********************************

        $allow_file_archiving = $this->params->def('allow_file_archiving', 1);


        if ($access_rights > 2 && isset($_GET['resolve_conflicts_filename'])) {
            $file_content = @file($_GET['resolve_conflicts_filename']);

            if (! isset($file_content) || ! strlen(trim($file_content[0]))) { // notice that file() returns an array
                return;
            }

            // echo $this->chosen_encoding(str_replace(PHP_EOL, "<br />", $tmptext));

            // loop through the resolve_conflicts file and do logs (email) and/or set up a form to finalise uploads
            //
            // available options:
            //
            // ASK
            // UPLOADED
            // CANCELED
            // OVERRIDEN
            // ARCHIVED
            // ERROR
            //
            // and ERROR codes:
            //
            // 1: File exceeded maximum server upload size of ini_get('upload_max_filesize')
            // 2: File exceeded maximum file size
            // 3: File only partially uploaded
            // 4: No file uploaded
            // 5: Cannot override existing file (copy)
            // 6: Cannot create new archive directory
            // 7: Cannot copy existing file in archive directory
            // 8: Cannot override existing file (move)

            $swfupload_ask_form = "<form name='uploadAskForm' style='display:inline; margin: 0px; padding: 0px;' enctype='multipart/form-data' action='" . $this->baselink . "&dir=" . urlencode($masked_dir) . "' method='post'>" . "	<table>" . "	<input type='hidden' name='swfupload_ask_form_submitted' value='1'>" . "	<tr><td colspan='2'><b>" . Text::_('swfupload_ask_form_header') . "</b><br /><br /></td></tr>" . "	<tr>" . "	<td><b>" . Text::_('file_name') . "</b></td>" . "	<td class='right_aligned'><b>" . Text::_('swfupload_action_required') . "</b></td>" . "	</tr>";

            $swfupload_ask_index = 0;

            foreach ($file_content as $curline) {
                list($action, $filename, $other) = explode(";", $curline);

                switch ($action) {
                    case "ASK":

                        $other = trim($other);

                        if ($this->line_height) {
                            $swfupload_ask_form .= "<tr class='jsmalline'><td colspan='2'><img src=\"" . $this->imgdirNavigation . "null.gif\" /></td></tr>";
                        }

                        if ($allow_file_archiving) {
                            $ask_option_3 = "<option value='3'>" . Text::_('swfupload_ask_option_archive') . "</option>";
                        } else {
                            $ask_option_3 = "";
                        }

                        $swfupload_ask_select_tag = "<select name='action_required_" . $swfupload_ask_index . "'>" . "<option value='1'>" . Text::_('swfupload_ask_option_cancel') . "</option>" . "<option value='2'>" . Text::_('swfupload_ask_option_override') . "</option>" . $ask_option_3 . "</select>";

                        $swfupload_ask_form .= "<tr>" . "<td>" . $this->chosen_encoding($filename) . "<input type='hidden' name='filename_" . $swfupload_ask_index . "' value=\"" . $this->chosen_encoding($filename) . "\"></td>" . "<td class='right_aligned'>" . $swfupload_ask_select_tag . "<input type='hidden' name='tmpfile_" . $swfupload_ask_index . "' value='" . $other . "'></td>" . "</tr>";

                        $swfupload_ask_index++;

                        break;

                    case "UPLOADED":
                    case "OVERRIDEN":
                    case "ARCHIVED":

                        // log
                        if ($log_uploads) {
                            $this->do_log_this_action($log_uploads, LOG_ACTION_UPLOAD, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($filename), $this->chosen_encoding($relative_dir));
                        }
                        break;

                    case "CANCELED": // do nothing here
                        break;

                    case "ERROR":

                        // log
                        $other = trim($other);

                        if ($other == 1) {
                            if ($log_uploads) {
                                $this->do_log_this_action($log_uploads, LOG_ACTION_UPLOAD, LOG_ACTION_UPLOAD_ERROR_1, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($filename), $this->chosen_encoding($relative_dir), ini_get('upload_max_filesize'));
                            }
                            $error_text = Text::sprintf('swfupload_error_1', $today, $this->chosen_encoding($filename), $this->chosen_encoding($relative_dir), $username, $remote_address, ini_get('upload_max_filesize'));
                        } else {
                            if ($log_uploads) {
                                switch ($other) {
                                    case 2:
                                        $log_action_upload_error = LOG_ACTION_UPLOAD_ERROR_2;
                                        break;
                                    case 3:
                                        $log_action_upload_error = LOG_ACTION_UPLOAD_ERROR_3;
                                        break;
                                    case 4:
                                        $log_action_upload_error = LOG_ACTION_UPLOAD_ERROR_4;
                                        break;
                                    case 5:
                                        $log_action_upload_error = LOG_ACTION_UPLOAD_ERROR_5;
                                        break;
                                    case 6:
                                        $log_action_upload_error = LOG_ACTION_UPLOAD_ERROR_6;
                                        break;
                                    case 7:
                                        $log_action_upload_error = LOG_ACTION_UPLOAD_ERROR_7;
                                        break;
                                    case 8:
                                        $log_action_upload_error = LOG_ACTION_UPLOAD_ERROR_8;
                                        break;
                                }
                                $this->do_log_this_action($log_uploads, LOG_ACTION_UPLOAD, $log_action_upload_error, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($filename), $this->chosen_encoding($relative_dir));
                            }
                            $error_text = Text::sprintf('swfupload_error_' . $other, $today, $this->chosen_encoding($filename), $this->chosen_encoding($relative_dir), $username, $remote_address);
                        }

                        // set error for display
                        $error .= $error_text;

                        break;
                }
            }

            if ($this->line_height) {
                $swfupload_ask_form .= "<tr class='jsmalline'><td colspan='2'><img src=\"" . $this->imgdirNavigation . "null.gif\" /></td></tr>";
                $swfupload_ask_form .= "<tr><td colspan='2'><br /></td></tr>";
            }

            $swfupload_ask_form .= "<tr>" . "<td class='right_aligned'>" . "<input type='hidden' name='files_to_manage' value='" . $swfupload_ask_index . "'>" . "<input type='hidden' name='conflicts_filename' value='" . $_GET['resolve_conflicts_filename'] . "'>" . Text::_('swfupload_ask_complete_upload') . "&nbsp;" . "</td>" . "<td>" . "<input type='image' src=\"" . $this->imgdirNavigation . "addfile.png\" title=\"" . Text::_('swfupload_ask_complete_upload_hover') . "\" />" . "</td>" . "</tr></table>" . "</form>";

            if ($swfupload_ask_index) {
                $error .= $swfupload_ask_form;
            }
        }

        // moving the uploaded file (HTML upload)

        if ($access_rights > 2 && isset($_GET['keep_existing_file']) && isset($_GET['tmpfiletoupload'])) {
            // unlink WAITING file
            @unlink(html_entity_decode($this->chosen_decoding($_GET['tmpfiletoupload'] . "_WAITING")));
        } elseif ($access_rights > 2 && (isset($_GET['override_file']) || isset($_GET['archive_file']))) {
            $name = $this->baseName($this->chosen_decoding($_GET['filetoupload']));

            $upload_dir = urldecode($_GET['uploaddir']); // we urldecode this get variable as this is originally a post undecoded one (and masked, so we unmask it in the next line)

            $upload_dir = $this->unmaskAbsPath($upload_dir);

            // security check on upload_dir (suggestion by Mark Gentry)
            if (preg_match("/\.\.(.*)/", $upload_dir) || (strlen($upload_dir) == 1 && $upload_dir[0] == '/') || (! stristr(str_replace("/", "\\", $upload_dir), str_replace("/", "\\", $starting_dir)))) {
                $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::_('security_file_upload') . "</td>" . "</tr></table></div></div>";

                $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
                return;
            }

            if (isset($_GET['override_file']) && $_GET['override_file'] == 1) {
                $upload_file = $upload_dir . '/' . $name;

                // copy WAITING file onto existing one (will then unlink WAITING tmp file)
                if (! @copy(html_entity_decode($this->chosen_decoding($_GET['tmpfiletoupload'] . "_WAITING")), html_entity_decode($upload_file))) {
                    $error .= Text::_('failed_move');
                } else {
                    @chmod(html_entity_decode($upload_file), $default_file_chmod);

                    // log
                    if ($log_uploads) {
                        $this->do_log_this_action($log_uploads, LOG_ACTION_UPLOAD, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($this->baseName($upload_file)), $this->chosen_encoding($relative_dir));
                    }
                }
            } elseif ($allow_file_archiving && $_GET['archive_file'] == 1) {
                if (! is_dir($upload_dir . '/' . "JS_ARCHIVE") && ! ($rc = @mkdir($upload_dir . '/' . "JS_ARCHIVE"))) {
                    $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::sprintf('failed_creating_archive_dir', $upload_dir . '/' . "JS_ARCHIVE") . "</td>" . "</tr></table></div></div>";

                    $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
                    return;
                }

                $upload_file = $upload_dir . '/' . $name;

                if (strpos($name, '.') === false) {
                    $archive_file = $upload_dir . '/' . "JS_ARCHIVE" . '/' . $name . " (" . Text::_('archived') . " " . date("Y-m-d H.i.s") . ")";
                } else {
                    $archive_file = $this->fileWithoutExtension($upload_dir . '/' . "JS_ARCHIVE" . '/' . $name) . " (" . Text::_('archived') . " " . date("Y-m-d H.i.s") . ")." . $this->fileExtension($name);
                }

                // copy current file into archive folder
                if (! @copy(html_entity_decode($upload_file), html_entity_decode($archive_file))) {
                    $error .= Text::_('failed_move');
                } else {
                    // copy WAITING file onto existing one (will then unlink WAITING tmp file)
                    if (! @copy(html_entity_decode($this->chosen_decoding($_GET['tmpfiletoupload'] . "_WAITING")), html_entity_decode($upload_file))) {
                        $error .= Text::_('failed_move');
                    } else {
                        @chmod(html_entity_decode($upload_file), $default_file_chmod);

                        // log
                        if ($log_uploads) {
                            $this->do_log_this_action($log_uploads, LOG_ACTION_UPLOAD, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($this->baseName($upload_file)), $this->chosen_encoding($relative_dir));
                        }
                    }
                }
            }

            // unlink WAITING file
            @unlink(html_entity_decode($this->chosen_decoding($_GET['tmpfiletoupload'] . "_WAITING")));
        } elseif ($access_rights > 2 && isset($_FILES['userfile']['name']) && strlen($_FILES['userfile']['name']) > 0) {
            $name = $this->baseName($this->chosen_decoding($_FILES['userfile']['name']));

            $upload_dir = urldecode($_POST['upload_dir']);

            $upload_dir = $this->unmaskAbsPath($upload_dir);

            // security check on upload_dir (suggestion by Mark Gentry)
            if (preg_match("/\.\.(.*)/", $upload_dir) || (strlen($upload_dir) == 1 && $upload_dir[0] == '/') || (! stristr(str_replace("/", "\\", $upload_dir), str_replace("/", "\\", $starting_dir)))) {
                $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::_('security_file_upload') . "</td>" . "</tr></table></div></div>";

                $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
                return;
            }

            $upload_file = $upload_dir . '/' . $name;

            // DEBUG
            if ($this->DEBUG_enabled) {
                var_dump(is_uploaded_file(html_entity_decode($this->chosen_decoding($_FILES['userfile']['tmp_name']))));
                echo "<br />0. Tried to move file [" . html_entity_decode($_FILES['userfile']['tmp_name']) . "] (" . (file_exists(html_entity_decode($_FILES['userfile']['tmp_name'])) ? "EXISTS" : "DOES NOT EXIST") . ") to [" . $this->chosen_encoding(html_entity_decode($upload_file)) . "]<br />";
                echo "Permission of temporary file is [" . substr(sprintf('%o', @fileperms(html_entity_decode($this->chosen_decoding($_FILES['userfile']['tmp_name'])))), - 4) . "]<br />";
                echo "Permission of temporary folder [" . html_entity_decode($this->upperDirSetForwardSlashes($_FILES['userfile']['tmp_name'])) . "] is [" . substr(sprintf('%o', @fileperms(html_entity_decode($this->chosen_decoding($this->upperDir($_FILES['userfile']['tmp_name']))))), - 4) . "]<br />";
                echo "Permission of destination file is [" . substr(sprintf('%o', @fileperms(html_entity_decode($this->chosen_decoding($upload_file)))), - 4) . "]<br />";
                echo "Permission of destination folder [" . $this->chosen_encoding($upload_dir) . "] is [" . substr(sprintf('%o', fileperms(html_entity_decode($upload_dir))), - 4) . "]<br /><br />";
            }
            if (! is_uploaded_file(html_entity_decode($this->chosen_decoding($_FILES['userfile']['tmp_name'])))) {
                $error .= Text::_('failed_upload');
            } elseif (file_exists($upload_file)) { // Check to avoid overwriting existing file /ErikLtz
                if ($allow_file_archiving) {
                    $error .= Text::sprintf('failed_upload_exists_archive', $this->baselink . "&dir=" . urlencode($masked_dir) . "&keep_existing_file=1&tmpfiletoupload=" . $_FILES['userfile']['tmp_name'], $this->baselink . "&dir=" . urlencode($masked_dir) . "&override_file=1&uploaddir=" . $_POST['upload_dir'] . "&filetoupload=" . $_FILES['userfile']['name'] . "&tmpfiletoupload=" . $_FILES['userfile']['tmp_name'], $this->baselink . "&dir=" . urlencode($masked_dir) . "&archive_file=1&uploaddir=" . $_POST['upload_dir'] . "&filetoupload=" . $_FILES['userfile']['name'] . "&tmpfiletoupload=" . $_FILES['userfile']['tmp_name']);
                } else {
                    $error .= Text::sprintf('failed_upload_exists', $this->baselink . "&dir=" . urlencode($masked_dir) . "&keep_existing_file=1&tmpfiletoupload=" . $_FILES['userfile']['tmp_name'], $this->baselink . "&dir=" . urlencode($masked_dir) . "&override_file=1&uploaddir=" . $_POST['upload_dir'] . "&filetoupload=" . $_FILES['userfile']['name'] . "&tmpfiletoupload=" . $_FILES['userfile']['tmp_name']);
                }

                // copy tmp uploaded file to WAITING one and delete it
                move_uploaded_file(html_entity_decode($this->chosen_decoding($_FILES['userfile']['tmp_name'])), html_entity_decode($this->chosen_decoding($_FILES['userfile']['tmp_name'] . "_WAITING")));
                @unlink(html_entity_decode($this->chosen_decoding($_FILES['userfile']['tmp_name'])));
            } elseif (! move_uploaded_file(html_entity_decode($this->chosen_decoding($_FILES['userfile']['tmp_name'])), html_entity_decode($upload_file))) {
                // DEBUG
                if ($this->DEBUG_enabled) {
                    var_dump(is_uploaded_file(html_entity_decode($this->chosen_decoding($_FILES['userfile']['tmp_name']))));
                    echo "<br />1. Tried to move file [" . html_entity_decode($_FILES['userfile']['tmp_name']) . "] (" . (file_exists(html_entity_decode($_FILES['userfile']['tmp_name'])) ? "EXISTS" : "DOES NOT EXIST") . ") to [" . $this->chosen_encoding(html_entity_decode($upload_file)) . "]<br />";
                    echo "Permission of temporary file is [" . substr(sprintf('%o', @fileperms(html_entity_decode($this->chosen_decoding($_FILES['userfile']['tmp_name'])))), - 4) . "]<br />";
                    echo "Permission of temporary folder [" . html_entity_decode($this->upperDirSetForwardSlashes($_FILES['userfile']['tmp_name'])) . "] is [" . substr(sprintf('%o', @fileperms(html_entity_decode($this->chosen_decoding($this->upperDirSetForwardSlashes($_FILES['userfile']['tmp_name']))))), - 4) . "]<br />";
                    echo "Permission of destination file is [" . substr(sprintf('%o', @fileperms(html_entity_decode($this->chosen_decoding($upload_file)))), - 4) . "]<br />";
                    echo "Permission of destination folder [" . $this->chosen_encoding($upload_dir) . "] is [" . substr(sprintf('%o', fileperms(html_entity_decode($upload_dir))), - 4) . "]<br /><br />";
                }
                $error .= Text::_('failed_move');
            } else {
                @chmod(html_entity_decode($upload_file), $default_file_chmod);

                // log
                if ($log_uploads) {
                    $this->do_log_this_action($log_uploads, LOG_ACTION_UPLOAD, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($this->baseName($upload_file)), $this->chosen_encoding($relative_dir));
                }
            }
        }

        // managing file download
        if ($access_rights > 1 && isset($_GET['download_file']) && strlen($_GET['download_file'])) {
            // send requested file
            $download_file = html_entity_decode($_GET['download_file']); // Removed urldecode on _GET /ErikLtz
            $download_file = $this->unmaskAbsPath($download_file);

            // security check (problem raised by Ludovic De Luna on 20091013)
            if (strcmp(substr($download_file, 0, strlen($dir)), $dir) || preg_match("/\.\.(.*)/", $download_file)) {
                $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::_('security_file_download') . "</td>" . "</tr></table></div></div>";

                $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
                return;
            }

            if (file_exists($download_file)) {

                @ob_end_clean();
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header("Content-Disposition: attachment; filename=\"" . $this->baseName($download_file) . "\"");
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($download_file));
                @ob_flush();
                @flush();

                // standard PHP function readfile() has documented problems with large files; readfile_chunked() is reported on php.net
                $this->readfile_chunked($download_file);
                // readfile($download_file);

                // log
                if ($log_downloads) {
                    $this->do_log_this_action($log_downloads, LOG_ACTION_DOWNLOAD, LOG_ACTION_RESULT_OK, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $this->chosen_encoding($this->baseName($download_file)), $this->chosen_encoding($relative_dir));
                }
                die(); // stop execution of further script because we are only outputting the pdf
                // (see readfile() function comment by mark dated 17-Sep-2008 on php.net)
            } else {
                $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::sprintf('file_not_found', $this->chosen_encoding($this->baseName($download_file))) . "</td>" . "</tr></table></div></div>";

                $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
                return;
            }
        }

        // asking for the actions log
        if ($access_rights > 5 && isset($_GET['view_log']) && ($log_uploads || $log_downloads || $log_removedfolders || $log_removedfiles || $log_restoredfiles || $log_newfolders || $log_newfoldernames || $log_newfilenames || $log_unzippedfiles)) {
            $this->view_log($log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $article, $params, $description, $masked_dir, $log_uploads, $log_downloads, $log_removedfolders, $log_removedfiles, $log_restoredfiles, $log_newfolders, $log_newfoldernames, $log_newfilenames, $log_unzippedfiles);
            return;
        }

        // asking for help
        if (isset($_GET['help'])) {
            $this->do_help($article, $params, $description, $masked_dir);
            return;
        }

        // for file filtering
        $file_filter_pattern_required = 0;
        if (isset($_COOKIE['current_filter_list']) && strlen($_COOKIE['current_filter_list'])) {
            $file_filter_pattern_required = 1;
        }

        // Reading the data of files and directories
        if ($this->DEBUG_enabled) {
            echo "Opening directory [" . html_entity_decode(str_replace("\\", "/", $dir . "/")) . "]<br /><br />";
        }

        if ($open_dir = @opendir(html_entity_decode(str_replace("\\", "/", $dir . "/")))) {
            $dirs = array();
            $files = array();
            $i = 0;
            while ($it = @readdir($open_dir)) {
                if ($it != "." && $it != "..") {
                    if (is_dir($dir . '/' . $it)) {
                        if (! in_array($it, $hidden_folders)) {
                            $dirs[] = htmlspecialchars($it);
                        }
                    } // else if(!in_array($it, $hidden_files) && !in_array("*.".$this->fileExtension($it), $hidden_extensions[0]))
                    elseif (! in_array($it, $hidden_files)) {
                        $matched_prefix = 0;
                        for ($k = 0; $k < count($hidden_prefixes[0]); $k++) {
                            if (! strncasecmp($hidden_prefixes[0][$k], $it, strlen($hidden_prefixes[0][$k]) - 1)) {
                                $matched_prefix = 1;
                            }
                        }

                        $matched_extension = 0;
                        for ($k = 0; $k < count($hidden_extensions[0]); $k++) {
                            if (! strncasecmp(strrev($hidden_extensions[0][$k]), strrev($it), strlen($hidden_extensions[0][$k]) - 1)) {
                                $matched_extension = 1;
                            }
                        }

                        // file list filtering
                        $file_filter_pattern_matched = 0;
                        if ($file_filter_pattern_required) {
                            $pattern_array = explode(";", $this->chosen_decoding($_COOKIE['current_filter_list']));
                            for ($k = 0; $k < count($pattern_array); $k++) {
                                if (stristr($it, trim($pattern_array[$k]))) {
                                    $file_filter_pattern_matched = 1;
                                }
                            }
                        }

                        if (! $matched_prefix && ! $matched_extension && (! $file_filter_pattern_required || $file_filter_pattern_matched)) {
                            $files[$i]["name"] = htmlspecialchars($it);
                            $it = $dir . "/" . $it;
                            $files[$i]["extension"] = $this->fileExtension($it);
                            $files[$i]["size"] = $this->fileRealSize(html_entity_decode($it));
                            $files[$i]["changed"] = @filemtime(html_entity_decode($it));
                            $i++;
                        }
                    }
                }
            }
            @closedir($open_dir);
        } else {
            $text = "<div id='JS_MAIN_DIV'><div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . Text::sprintf('dir_not_found', $this->chosen_encoding($current_position), $this->default_absolute_path) . "</td>" . "</tr></table></div></div>";

            $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];
            return;
        }

        // if a file was just successfully removed, we are in an archive folder and there are no more files, then remove the folder and reload to the upper level
        if ($a_file_was_removed && $is_current_position_an_archive && ! $files) {
            $rc = @rmdir($dir);

            // check if the current directory (an archive) is gone
            if (file_exists($dir)) {

                $error .= Text::sprintf('delete_folder_failed', $dir);
            } else {
                header('Location: ' . $this->baselink . "&dir=" . urlencode($this->upperDirSetForwardSlashes($masked_dir)));
            }
        }

        // Sort files and folders. By default, they are sorted by name
        if ($files || $dirs) {
            if (isset($_GET["sort_by"]) && isset($_GET["sort_as"]) && $_GET["sort_by"] == "name" && $_GET["sort_as"] != "asc") {
                @usort($dirs, array(
                    $this,
                    "dirname_cmp_desc"
                ));
                @usort($files, array(
                    $this,
                    "filename_cmp_desc"
                ));

                $this->cur_sort_by = "name";
                $this->cur_sort_as = "desc";
            } elseif (isset($_GET["sort_by"]) && isset($_GET["sort_as"]) && $_GET["sort_by"] == "name" && $_GET["sort_as"] == "asc") {
                @usort($dirs, array(
                    $this,
                    "dirname_cmp_asc"
                ));
                @usort($files, array(
                    $this,
                    "filename_cmp_asc"
                ));

                $this->cur_sort_by = "name";
                $this->cur_sort_as = "asc";
            } elseif (isset($_GET["sort_by"]) && isset($_GET["sort_as"]) && $_GET["sort_by"] == "size" && $_GET["sort_as"] != "asc" && $files) {
                @usort($files, array(
                    $this,
                    "size_cmp_desc"
                ));

                $this->cur_sort_by = "size";
                $this->cur_sort_as = "desc";
            } elseif (isset($_GET["sort_by"]) && isset($_GET["sort_as"]) && $_GET["sort_by"] == "size" && $_GET["sort_as"] == "asc" && $files) {
                @usort($files, array(
                    $this,
                    "size_cmp_asc"
                ));

                $this->cur_sort_by = "size";
                $this->cur_sort_as = "asc";
            } elseif (isset($_GET["sort_by"]) && isset($_GET["sort_as"]) && $_GET["sort_by"] == "changed" && $_GET["sort_as"] != "asc" && $files) {
                @usort($files, array(
                    $this,
                    "changed_cmp_desc"
                ));

                $this->cur_sort_by = "changed";
                $this->cur_sort_as = "desc";
            } elseif (isset($_GET["sort_by"]) && isset($_GET["sort_as"]) && $_GET["sort_by"] == "changed" && $_GET["sort_as"] == "asc" && $files) {
                @usort($files, array(
                    $this,
                    "changed_cmp_asc"
                ));

                $this->cur_sort_by = "changed";
                $this->cur_sort_as = "asc";
            } else {
                // default sort by name
                if (! strcmp($default_sort_by, "name")) {
                    if (! strcmp($default_sort_as, "desc")) {
                        @usort($dirs, array(
                            $this,
                            "dirname_cmp_desc"
                        ));
                        @usort($files, array(
                            $this,
                            "filename_cmp_desc"
                        ));

                        $this->cur_sort_by = "name";
                        $this->cur_sort_as = "desc";
                    } else {
                        @usort($dirs, array(
                            $this,
                            "dirname_cmp_asc"
                        ));
                        @usort($files, array(
                            $this,
                            "filename_cmp_asc"
                        ));

                        $this->cur_sort_by = "name";
                        $this->cur_sort_as = "asc";
                    }
                }

                // default sort by size
                if (! strcmp($default_sort_by, "size")) {
                    if (! strcmp($default_sort_as, "desc")) {
                        @usort($dirs, array(
                            $this,
                            "dirname_cmp_asc"
                        ));
                        @usort($files, array(
                            $this,
                            "size_cmp_desc"
                        ));

                        $this->cur_sort_by = "size";
                        $this->cur_sort_as = "desc";
                    } else {
                        @usort($dirs, array(
                            $this,
                            "dirname_cmp_asc"
                        ));
                        @usort($files, array(
                            $this,
                            "size_cmp_asc"
                        ));

                        $this->cur_sort_by = "size";
                        $this->cur_sort_as = "asc";
                    }
                }

                // default sort by changed
                if (! strcmp($default_sort_by, "changed")) {
                    if (! strcmp($default_sort_as, "desc")) {
                        @usort($dirs, array(
                            $this,
                            "dirname_cmp_asc"
                        ));
                        @usort($files, array(
                            $this,
                            "changed_cmp_desc"
                        ));

                        $this->cur_sort_by = "changed";
                        $this->cur_sort_as = "desc";
                    } else {
                        @usort($dirs, array(
                            $this,
                            "dirname_cmp_asc"
                        ));
                        @usort($files, array(
                            $this,
                            "changed_cmp_asc"
                        ));

                        $this->cur_sort_by = "changed";
                        $this->cur_sort_as = "asc";
                    }
                }
            }
        }

        // ***********************************************************************************************************************
        // Start of HTML :: all html code created by jsmallfib is wrapped around a div with id JS_MAIN_DIV
        // the rest is contained in 4 div tags whose ids are JS_TOP_DIV, JS_FILES_DIV, JS_ACTIONS_DIV, JS_BOTTOM_DIV
        // ***********************************************************************************************************************

        $text = "<div id='JS_MAIN_DIV'>";

        if ($description) {
            $text .= "<b>$description</b>";
        }

        // this is for file filtering
        if ($this->filter_list_allow && (count($dirs) || count($files))) {
            $clear_current_filter_list_icon = isset($_COOKIE['current_filter_list']) && strlen($_COOKIE['current_filter_list']) ? "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&current_filter_list='><img src='" . $this->imgdirNavigation . "delete.png' title='" . Text::_('clear_current_filter_list') . "'></a>" : "";

            $filter_list_tr = $file_filter_pattern_required ? "<tr class='jsmalline'><td colspan='7'></td></tr>" : "";
            $filter_list_tr .= "<tr " . ($file_filter_pattern_required ? "class='row highlighted' style='border-style:solid'" : "") . ">" . "<form action='" . $this->baselink . "&dir=" . urlencode($masked_dir) . "' method='post'>" . "<td colspan='7'>" . "<table class='filterTable'>" . "<tr>" . "<td title=\"" . Text::_('set_filter_list') . "\">" . Text::_('set_filter_list_label') . "&nbsp;&nbsp;</td>" . "<td width='" . ($this->filter_list_width + 30 + (strlen($clear_current_filter_list_icon) ? 30 : 0)) . "' align='right'>" . "<input class='long_input_field' name='current_filter_list' type='text' value=\"" . (isset($_COOKIE['current_filter_list']) ? $_COOKIE['current_filter_list'] : "") . "\" />" . "</td>" . "<td class='filterIconTick'>" . "<input type='image' src=\"" . $this->imgdirNavigation . "tick.png\" title=\"" . Text::_('set_filter_list') . "\" />" . "</td>" . "<td class='filterIconDelete'>" . $clear_current_filter_list_icon . "</td>" . "</tr>" . "</table>" . "</td>" . "</form>" . "</tr>";
            $filter_list_tr .= $file_filter_pattern_required ? "<tr class='jsmalline'><td colspan='7'></td></tr>" . "<tr><td colspan='7'><img src=\"" . $this->imgdirNavigation . "null.gif\" height=20 /></td></tr>" : "";
        } elseif ($this->filter_list_allow && (! count($dirs) && ! count($files)) && $file_filter_pattern_required) {
            // needed when deleting all files inside a filter list (issue raised by Daniel Campos)
            $clear_current_filter_list_text = "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&current_filter_list='>" . Text::_('clear_current_filter_list') . "</a>";
            $filter_list_tr = "<tr class='jsmalline'><td colspan='19'></td></tr>" . "<tr height='30' valign='middle'><td colspan='19' align='center'>" . $clear_current_filter_list_text . "</td></tr>" . "<tr class='jsmalline'><td colspan='19'></td></tr>";
        } else {
            $filter_list_tr = "";
        }

        // Print the error (if there is something to print)
        if ($this->DEBUG_enabled) {
            $error = "This is a simulated error<br />Don't get too upset about it!";
            $success = "This is a simulated success<br />Don't get too excited about it!";
        }
        if ($error) {

            $text .= "<div id='JS_ERROR_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "warning.png\"></td><td>" . $error . "</td>" . "</tr></table></div>";
        }
        if ($success) {

            $text .= "<div id='JS_SUCCESS_DIV'><table><tr>" . "<td class='alertIcon'><img src=\"" . $this->imgdirNavigation . "success.png\"></td><td>" . $success . "</td>" . "</tr></table></div>";
        }

        // logs / help area
        $show_help_link = $this->params->def('show_help_link', "1");
        $logs_link = $access_rights > 5 && $logs_enabled ? "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&view_log=1'>" . Text::_('view_log') . "</a>" : "";
        if ($show_help_link) {
            $help_link = ($logs_link ? "&nbsp;|&nbsp;" : "") . "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&help=1'>" . Text::_('help') . "</a>";
        } else {
            $help_link = "";
        }

        $links_string = $logs_link . $help_link;

        if (isset($userbound_repository_with_id) && isset($userbound_repository_with_name)) {
            $current_position_links = (str_replace($userbound_repository_with_id, $userbound_repository_with_name, $current_position_links));
        } elseif (isset($groupbound_repository_with_id) && isset($groupbound_repository_with_name)) {
            $current_position_links = (str_replace($groupbound_repository_with_id, $groupbound_repository_with_name, $current_position_links));
        }

        if ($this->display_currentdirectory) {
            $browsing_text = ($is_current_position_an_archive ? Text::_('archive_folder_for') : Text::_('browsing'));
            $currentdirectory_td = "<td class='navigation'>" . $browsing_text . ": " . $this->chosen_encoding($current_position_links) . "</td>";
        } else {
            $currentdirectory_td = "";
        }

        // START (and END) OF div TAG with id JS_TOP_DIV

        $text .= "<div id='JS_TOP_DIV'>" . "<table>" . "<tr valign='center'>" . "	<td colspan='2'>&nbsp;</td>" . "</tr>" . "<tr valign='top'>" . $currentdirectory_td . "	<td class='topLinks'>" . $links_string . "</td>" . "</tr>" . "</table>" . ($enable_usergroup_switch_links ? str_replace("TMP_BASELINK", $this->baselink, $usergroup_switch_links) : "") . "</div>";

        if ($enable_usergroup_switch_links) {
            $text .= "<div id='jsmallspacer'><img src=\"" . $this->imgdirNavigation . "null.gif\" /></div>";
        }

        // START OF div TAG with id JS_FILES_DIV

        $text .= "<div id='JS_FILES_DIV'>";

        // start files/folders table with filter row and header row

        $rowColspan = 8; // this should really be 7, but in joomla3 this was misaligned on both Safari and Firefox: setting it 8 is fine (and has no drawbacks)

        $text .= "<table>" . $filter_list_tr . "<tr class='row header'>";

        // CELL 1 (files icon | header row)
        if ($upper_dir) {
            // note: upper_dir has the absolute path masked
            $text .= "<td class='fileIcon'>" . "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($upper_dir) . "'><img title=\"" . Text::_('go_to_previous_folder') . "\" src=\"" . $this->imgdirNavigation . "upperdir.png\" border='0' /></a>" . "</td>";
        } else {
            $text .= "<td class='emptyTd'></td>";
        }

        if (! count($dirs) && ! count($files)) {
            $text .= "<td colspan='$rowColspan'>" . Text::_('this_repository_is_empty') . "</td>";
        } else {
            // CELL 2 (filename | header row)
            $text .= "<td class='fileName'>" . $this->makeArrow((isset($_GET["sort_by"]) ? $_GET["sort_by"] : ""), (isset($_GET["sort_as"]) ? $_GET["sort_as"] : ""), "name", $masked_dir, Text::_('file_name')) . "</td>";

            // CELL 3 (filesize | header row)
            if ($this->display_filesize && count($files)) {
                $text .= "<td class='fileSize'>" . $this->makeArrow((isset($_GET["sort_by"]) ? $_GET["sort_by"] : ""), (isset($_GET["sort_as"]) ? $_GET["sort_as"] : ""), "size", $masked_dir, Text::_('file_size')) . "</td>";
            } else {
                $text .= "<td class='emptyTd'></td>";
            }

            // CELL 4 (filedate | header row)
            if ($this->display_filedate && count($files)) {
                $text .= "<td class='fileChanged'>" . $this->makeArrow((isset($_GET["sort_by"]) ? $_GET["sort_by"] : ""), (isset($_GET["sort_as"]) ? $_GET["sort_as"] : ""), "changed", $masked_dir, Text::_('last_changed')) . "</td>";
            } else {
                $text .= "<td class='emptyTd'></td>";
            }

            // actions CELLS are set empty
            $text .= "<td colspan='3' class='emptyTd' style='width:10%'></td>";
        }

        $text .= "</tr>";

        // Ready to display folders and files.
        $row = 1;

        // Folders first
        if ($dirs) {
            foreach ($dirs as $a_dir) {
                $row_style = ($row ? "odd" : "even");

                if ($this->line_height) {
                    $text .= "<tr class='jsmalline'><td colspan='$rowColspan'></td></tr>";
                }

                // different line if editing name or not
                if (isset($_GET['old_foldername']) && strlen($_GET['old_foldername']) && ! strcmp($_GET['old_foldername'], $a_dir)) { // Removed urldecode on _GET /ErikLtz
                    $text .= "<form action='" . $this->baselink . "&dir=" . urlencode($masked_dir) . "' method='post'>" . "<tr class='row $row_style'>" . "	<td class='fileIcon'>" . "	<img src=\"" . $this->imgdirNavigation . "folder.png\" width='" . $this->icon_width . "' style='width:". $this->icon_width."px;max-width:inherit' />" . "	</td>" . "	<td class='fileName'>" . "	<input name='new_foldername' type='text' value=\"" . $this->chosen_encoding($a_dir) . "\" />" . "	</td>" . "	<td colspan='3' class='emptyTd'></td>" . "	<td class='fileAction'>" . "	<input type='image' src=\"" . $this->imgdirNavigation . "tick.png\" title=\"" . Text::_('rename_folder_title') . "\" />" . "	</td>" . "	<td class='fileAction'><a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "'>" . Text::_('rename_folder_cancel') . "</a></td>" . "</tr>" . "	<input type='hidden' name='old_foldername' value=\"" . urlencode($a_dir) . "\" />" . "</form>";
                } else {
                    $text .= "<tr class='row $row_style' onmouseover='this.className=\"row highlighted\"' onmouseout='this.className=\"row $row_style\"'>" . "	<td class='fileIcon'>" . "	<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir . "/" . $a_dir) . "'>" . "<img src=\"" . $this->imgdirNavigation . "folder.png\" width='" . $this->icon_width . "' style='width:". $this->icon_width."px;max-width:inherit' /></a>" . "	</td>" . "	<td class='fileName'>" . "	<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir . "/" . $a_dir) . "'>" . $this->chosen_encoding($a_dir) . "</a>" .
                    // ." <a href='".$this->baselink."&dir=".str_replace("%2F", "/", urlencode($masked_dir))."/".str_replace("%2F", "/", urlencode($a_dir))."'>".$this->chosen_encoding($a_dir)."</a>"
                    // ." <a href='".$this->baselink."&dir=".urlencode($masked_dir).'/'.urlencode($a_dir)."'>".$this->chosen_encoding($a_dir)."</a>" // THIS WAS THE ORIGINAL LINE (changed for Paul Tease 20110909)
                    // ." <a href='".$this->baselink."&dir=".urlencode($masked_dir."/".$a_dir)."'>".$this->chosen_encoding($a_dir)."</a>"
                    "	</td>" . "	<td colspan='3' class='emptyTd'></td>";
                    if ($access_rights > 2) {
                        $text .= "<td class='fileAction'>" . "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&old_foldername=" . $this->urlEncodePreserveForwardSlashes($a_dir) . "'>" . "<img src=\"" . $this->imgdirNavigation . "rename.png\" border='0' title=\"" . Text::sprintf('folder_rename', $this->chosen_encoding($a_dir)) . "\" /></a>" . "</td>";
                    } else {
                        $text .= "<td class='emptyTd'></td>";
                    }
                    if ($access_rights > 4) {
                        // we need to utf-8 encode potential special characters to be passed to javascript, because Firefox does not handle this (it works in IE)
                        $text .= "<td class='fileAction'>" . "<a href=\"javascript:confirmDelfolder('" . addslashes($this->baselink) . "','" . urlencode(addslashes($this->chosen_encoding($masked_dir))) . "','" . urlencode(addslashes($this->chosen_encoding($a_dir))) . "','" . addslashes(Text::sprintf('about_to_remove_folder', $this->chosen_encoding($a_dir))) . "')\">" . "<img src=\"" . $this->imgdirNavigation . "delete.png\" border='0' title=\"" . Text::sprintf('remove_folder', $this->chosen_encoding($a_dir)) . "\" /></a>" . "</td>";
                    } else {
                        $text .= "<td class='emptyTd'></td>";
                    }
                    $text .= "</tr>";
                }
                $row = 1 - $row;
            }
        }

        // Now the files
        if ($files) {
            foreach ($files as $a_file) {
                $row_style = ($row ? "odd" : "even");

                if ($this->line_height) {
                    $text .= "<tr class='jsmalline'><td colspan='$rowColspan'></td></tr>";
                }

                // makeThumbnail will only make a new thumbnail if required, and will return one if the right thumbnail is available
                if ($is_current_position_inside_webroot && $this->makeThumbnail($a_file["name"], $a_file["extension"], $dir, $this->thumbsize, $this->thumbsize)) {
                    $file_icon_td_begin = "<td class='fileThumb'>";
                    $file_icon_image = "<img src=\"" . $this->makeForwardSlashes($this->chosen_encoding($relative_dir . "/" . "JS_THUMBS" . "/" . $a_file["name"])) . "\" border='0' />";
                    $file_icon_td_end = "</td>";
                } else {
                    $file_icon_td_begin = "<td class='fileIcon'>";
                    $file_icon_image = "<img src=\"" . $this->fileIcon($a_file["extension"]) . "\" style='width:". $this->icon_width."px!important;max-width:inherit' width='" . $this->icon_width . "' border='0' />";
                    $file_icon_td_end = "</td>";
                }

                // different line if editing name or not
                if (isset($_GET['old_filename']) && strlen($_GET['old_filename']) && ! strcmp($_GET['old_filename'], $a_file["name"])) { // Removed urldecode on _GET /ErikLtz
                    $text .= "<form action='" . $this->baselink . "&dir=" . urlencode($masked_dir) . "' method='post'>" . "<tr class='row $row_style'>" . $file_icon_td_begin . $file_icon_image . $file_icon_td_end . "	<td class='fileName'>" . "	<input name='new_filename' type='text' value=\"" . $this->chosen_encoding($a_file["name"]) . "\" />" . "	</td>" . ($this->display_filesize ? "<td class='fileSize'>" . $this->fileSizeF($a_file["size"]) . "</td>" : "<td class='emptyTd'></td>") . ($this->display_filedate ? "<td class='fileChanged'>" . $this->fileChanged($a_file["changed"]) . "</td>" : "<td class='emptyTd'></td>") . "	<td class='emptyTd'></td>" . "	<td class='fileAction'>" . "	<input type='image' src=\"" . $this->imgdirNavigation . "tick.png\" title=\"" . Text::_('rename_file_title') . "\" />" . "	</td>" . "	<td class='fileAction'><a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "'>" . Text::_('rename_file_cancel') . "</a></td>" . "</tr>" . "	<input type='hidden' name='old_filename' value=\"" . urlencode($a_file["name"]) . "\" />" . "</form>";
                } else {
                    if ($access_rights == 1) {
                        $file_link = $this->chosen_encoding($a_file["name"]);
                        $file_link_a_tag_begin = "";
                        $file_link_a_tag_end = "";
                    } else {
                        // now uses absolute path in download file (relative path returns false to file_exists() on certain unix configurations

                        // set the <a href...> tag for the file link (depends on the linking method, direct or through the open/download box)
                        if ($is_current_position_inside_webroot && $is_direct_link_to_files) {
                            $file_link_a_tag_begin = "<a href=\"" . $this->makeForwardSlashes($this->chosen_encoding($relative_dir . '/' . $a_file["name"])) . "\" " . ($is_direct_link_to_files > 1 ? "target='_blank'" : "") . ">";
                        } else {
                            $file_link_a_tag_begin = "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&download_file=" . $this->urlEncodePreserveForwardSlashes($masked_dir . "/" . $a_file["name"]) . "'>";
                        }
                        $file_link_a_tag_end = "</a>";

                        // display normal open/download link if either outside of an archive or inside, but with no right to restore a file
                        if (! $is_current_position_an_archive || $access_rights <= 3) {
                            // normal link
                            $file_link = $file_link_a_tag_begin . $this->chosen_encoding($a_file["name"]) . $file_link_a_tag_end;
                        } else {
                            // link in case of an archived file
                            $file_link = "<br />" . $this->chosen_encoding($a_file["name"]) . "<br />" . $file_link_a_tag_begin . Text::_('download_or_open_file') . $file_link_a_tag_end;

                            if ($access_rights > 3) {
                                $file_link .= "&nbsp;|&nbsp;<a href=\"javascript:confirmRestoreFile('" . addslashes($this->baselink) . "','" . urlencode(addslashes($this->chosen_encoding($masked_dir))) . "','" . urlencode(addslashes($this->chosen_encoding($a_file["name"]))) . "','" . addslashes(Text::sprintf('about_to_restore_archived_file', $this->chosen_encoding($a_file["name"]))) . "')\">" . Text::_('restore_archived_file') . "</a>" . "<br /><br />";
                            }
                        }
                    }

                    $text .= "<tr class='row $row_style' onmouseover='this.className=\"row highlighted\"' onmouseout='this.className=\"row $row_style\"'>" . $file_icon_td_begin . $file_link_a_tag_begin . $file_icon_image . $file_link_a_tag_end . $file_icon_td_end . "	<td class='fileName'>" . $file_link . "	</td>" . ($this->display_filesize ? "<td class='fileSize'>" . $this->fileSizeF($a_file["size"]) . "</td>" : "<td class='emptyTd'></td>") . ($this->display_filedate ? "<td class='fileChanged'>" . $this->fileChanged($a_file["changed"]) . "</td>" : "<td class='emptyTd'></td>");

                    if ($access_rights > 3 && ! $is_current_position_an_archive) {
                        // for zipped files
                        $supported_zip_extensions = array(
                            "BZ2",
                            "BZIP2",
                            "GZ",
                            "GZIP",
                            "TAR",
                            "TBZ2",
                            "TGZ",
                            "ZIP"
                        );

                        if ($this->unzip_allow && ! $is_current_position_an_archive && in_array(strtoupper($a_file["extension"]), $supported_zip_extensions)) {
                            $text .= "<td class='fileAction'>" . "<a href=\"javascript:confirmExtractfile('" . addslashes($this->baselink) . "','" . urlencode(addslashes($this->chosen_encoding($masked_dir))) . "','" . urlencode(addslashes($this->chosen_encoding($a_file["name"]))) . "','" . addslashes(Text::sprintf('about_to_extract_file', $this->chosen_encoding($a_file["name"]))) . "')\">" . "<img src=\"" . $this->imgdirNavigation . "unzip.png\" border='0' nowidth='23' title=\"" . Text::sprintf('extract_file', $this->chosen_encoding($a_file["name"])) . "\" /></a>" . "</td>";
                        } else {
                            $text .= "<td class='emptyTd'></td>";
                        }
                    } else {
                        $text .= "<td class='emptyTd'></td>";
                    }

                    if ($access_rights > 2 && ! $is_current_position_an_archive) {
                        $text .= "<td class='fileAction'>" . "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&old_filename=" . $this->urlEncodePreserveForwardSlashes($a_file["name"]) . "'>" . "<img src=\"" . $this->imgdirNavigation . "rename.png\" border='0' title=\"" . Text::sprintf('file_rename', $this->chosen_encoding($a_file["name"])) . "\" /></a>" . "</td>";
                    } else {
                        $text .= "<td class='emptyTd'></td>";
                    }
                    if ($access_rights > 3) {
                        // we need to utf-8 encode potential special characters to be passed to javascript, because Firefox does not handle this (it works in IE)
                        $text .= "<td class='fileAction'>" . "<a href=\"javascript:confirmDelfile('" . addslashes($this->baselink) . "','" . urlencode(addslashes($this->chosen_encoding($masked_dir))) . "','" . urlencode(addslashes($this->chosen_encoding($a_file["name"]))) . "','" . addslashes(Text::sprintf('about_to_remove_file', $this->chosen_encoding($a_file["name"]))) . "')\">" . "<img src=\"" . $this->imgdirNavigation . "delete.png\" border='0' title=\"" . Text::sprintf('remove_file', $this->chosen_encoding($a_file["name"])) . "\" /></a>" . "</td>";
                    } else {
                        $text .= "<td class='emptyTd'></td>";
                    }
                    $text .= "</tr>";
                }
                $row = 1 - $row;
            }
        }

        $text .= "</table>"; // closing files/folders table

        $text .= "</div>"; // closing JS_FILES_DIV

        // START (and END) OF div TAG with id JS_ARCHIVE_DIV

        if (is_dir($dir . '/' . "JS_ARCHIVE")) {
            // $text .= "<img src=\"".$this->imgdirNavigation."null.gif\" height=10 />";

            $archiveLinkATag = "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "/JS_ARCHIVE'>";

            $text .= "<div id='JS_ARCHIVE_DIV'>" . "<table>" . "<tr>" . "	<td class='actionIcon'>" . "	</td>" . "      <td>" . $archiveLinkATag . Text::_('view_archive_folder') . "</a>" . "      </td>" . "	<td class='actionIcon'>" . $archiveLinkATag . "<img src=\"" . $this->imgdirNavigation . "viewArchive.png\" border='0' /></a>" . "	</td>";

            $text .= "</tr>" . "</table>" . "</div>";
        }

        // ***********************************************************************************************************************
        // UPLOAD display_actions
        // ***********************************************************************************************************************

        $allow_upload_box_hiding = $this->params->def('allow_upload_box_hiding', 1);

        if ($access_rights > 2 && strcmp($this->baseName($dir), "JS_ARCHIVE") && (! $allow_upload_box_hiding || (isset($_COOKIE['display_actions']) && ! strcmp($_COOKIE['display_actions'], "UPLOAD")))) {
            // $split_upload_section = $this->params->def('split_upload_section', 0);

            // get default upload type and (if set) the relevant cookie setting, which overrides the default value
            // if the default value is SWFUPLOAD then we'll give the option to toggle upload type
            $upload_type = $this->params->def('upload_type', "SWFUPLOAD");

            if (! strcmp($upload_type, "SWFUPLOAD")) {
                if (isset($_COOKIE['upload_type'])) {
                    $toggle_upload_type_link = "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&set_upload_type_cookie=" . (! strcmp($_COOKIE['upload_type'], "SWFUPLOAD") ? "HTMLUPLOAD" : "SWFUPLOAD") . "' >" . (! strcmp($_COOKIE['upload_type'], "SWFUPLOAD") ? Text::_('set_upload_type_htmlupload') : Text::_('set_upload_type_swfupload')) . "</a>&nbsp;|&nbsp;";
                } else {
                    $toggle_upload_type_link = "<a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&set_upload_type_cookie=HTMLUPLOAD' >" . Text::_('set_upload_type_htmlupload') . "</a>&nbsp;|&nbsp;";
                }
            } else {
                $toggle_upload_type_link = "";
            }

            // set variable to define the upload form type to display
            if (! strcmp($upload_type, "HTMLUPLOAD") || (isset($_COOKIE['upload_type']) && ! strcmp($_COOKIE['upload_type'], "HTMLUPLOAD"))) {
                $display_html_upload_form = true;
            } else {
                $display_html_upload_form = false;
            }

            // start actions div

            $text .= "<div id='JS_ACTIONS_DIV'>" .
            "<table>" .

            // first action (new folder)

            "<tr>" . "	<td class='right_aligned'>" . "              <form style='display:inline; margin: 0px; padding: 0px;' enctype='multipart/form-data' action='" . $this->baselink . "&dir=" . urlencode($masked_dir) . "' method='post'>" . Text::_('create_new_folder') . ":&nbsp;&nbsp;" . "	</td>" . "	<td>" . "              <input class='long_input_field' name='userdir' type='text' />" . "	</td>" . "	<td class='actionIcon'>" . "              <input type='image' src=\"" . $this->imgdirNavigation . "addfolder.png\" title=\"" . Text::_('add_folder') . "\" />" . "              </form>" . "	</td>" . "</tr>" .

            // second action (upload)

            "<tr>" . "	<td class='right_aligned' colspan='" . ($display_html_upload_form ? 1 : 3) . "'>"; // the swf upload form only has one td cell

            $text .= "<form name='uploadForm' style='display:inline; margin: 0px; padding: 0px;' enctype='multipart/form-data' action='" . $this->baselink . "&dir=" . urlencode($masked_dir) . "' method='post'>";

            // display different upload form depending on chosen type
            if ($display_html_upload_form) {
                // DISPLAYING HTML UPLOAD

                $text .= $toggle_upload_type_link;
                $text .= Text::_('upload_file') . ":&nbsp;" . "	</td>" . "	<td>" . "	<input name=\"userfile\" type=\"file\" />" . "	</td>" . "	<td class='actionIcon'>" . "	<input type='image' src=\"" . $this->imgdirNavigation . "addfile.png\" title=\"" . Text::_('upload_file') . "\" />" . "	<input type='hidden' name='upload_dir' value=\"" . urlencode($masked_dir) . "\">";
            }

            $text .= "	" . // </td>"
                             // ." </tr>"
                             // ." </table>"
            "	</form>" . "	</td>" . "</tr>" . "</table>" . "</div>"; // end of div tag with id JS_ACTIONS_DIV
        }

        // small icon with link to site and title containing copyright and version number
        $hide_credits_icon = $this->params->def('hide_credits_icon', 0);

        if (! $hide_credits_icon) {
            $credits_icon = "<td class='right_aligned'><a href='#' target='_blank'>" . "<img src=\"" . $this->imgdirNavigation . "jsmallfib.png\" border='0' title=\"" . Text::sprintf('short_credits', $version_number,date('Y')) . "\" /></a>" . "</td>";
        } else {
            $credits_icon = "<td class='emptyTd'></td>";
        }

        // set display actions link(s): distinguish case of cookie set (the cookie is the same, so only one box is open at any one time) or not set
        if (isset($_COOKIE['display_actions'])) {
            // for the upload box (not allowed in archive folder)
            if ($allow_upload_box_hiding && strcmp($this->baseName($dir), "JS_ARCHIVE")) {
                if ($access_rights > 2 && ! strcmp($_COOKIE['display_actions'], "UPLOAD")) {
                    $upload_actions_icon = "<td class='actionIcon'><a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&set_display_actions_cookie=NO_ACTION' " . "title=\"" . Text::_('close_upload_actions_area') . "\"><img src=\"" . $this->imgdirNavigation . "minus.png\" border='0' /></a></td>";
                } elseif ($access_rights > 2) {
                    $upload_actions_icon = "<td class='actionIcon'><a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&set_display_actions_cookie=UPLOAD' " . "title=\"" . Text::_('open_upload_actions_area') . "\"><img src=\"" . $this->imgdirNavigation . "plus.png\" border='0' /></a></td>";
                } else {
                    $upload_actions_icon = "<td class='emptyTd'></td>";
                }
            } else {
                $upload_actions_icon = "<td class='emptyTd'></td>";
            }
        } else {
            // for the upload box (not allowed in archive folder)
            if ($allow_upload_box_hiding && strcmp($this->baseName($dir), "JS_ARCHIVE") && $access_rights > 2) {
                $upload_actions_icon = "<td class='actionIcon'><a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&set_display_actions_cookie=UPLOAD' " . "title=\"" . Text::_('open_upload_actions_area') . "\"><img src=\"" . $this->imgdirNavigation . "plus.png\" border='0' /></a></td>";
            } else {
                $upload_actions_icon = "<td class='emptyTd'></td>";
            }
        }

        // Bottom line

        $text .= "<div id='JS_BOTTOM_DIV'>" . "<table>" . "<tr>" . $upload_actions_icon . "	<td>&nbsp;</td>" . $credits_icon . "</tr>" . "</table>" . "</div>";

        $text .= "</div>"; // end of div of id jsmallfibCSS

        // ***********************************************************************************************************************
        // End of HTML - Now set article data
        // ***********************************************************************************************************************

        $article->text = $article->fulltext = $article->introtext = $text_array[0] . $text . $text_array[1];

        /*
         * 20110916 - following mail from Dylan Parker, I agree, why shouldn't these parameters be visible?
         * $params->set('show_author', '0');
         * $params->set('show_create_date', '0');
         * $params->set('show_modify_date', '0');
         */
    }

    // end of onPrepareContent method

    // ***********************************************************************************************************************
    // Log functions
    // ***********************************************************************************************************************
    public function do_log_this_action($log_status, $log_action, $log_action_result, $log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, $log_element, $log_location, $log_info = "")
    {
        $today = date("Y-m-d H:i:s");

        if ($log_status == LOG_STATUS_DISABLED) {
            return;
        }

        // get common veriables
        $user = Factory::getApplication()->getIdentity();
        $userid = $user->id;
        $username = $user->name;
        $user_username = $user->username; // used for userbound repositories

        if (! $username) {
            $username = Text::_('unregistered_visitor');
        }
        $remote_address = $_SERVER['REMOTE_ADDR'];
        if (! $remote_address) {
            $remote_address = Text::_('unavailable');
        }

        // discriminate based on log type (text, JSON or RDBM)
        if ($log_type == LOG_TYPE_TEXT) {
            // text type: we need to preserve the original format
            switch ($log_action) {
                case LOG_ACTION_UPLOAD:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_uploads" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    if ($log_action_result == LOG_ACTION_RESULT_OK) {
                        $log_text = Text::sprintf('upload_log_text', $today, $log_element, $log_location, $username, $remote_address);
                    } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_1) {
                        $log_text = Text::sprintf('swfupload_error_1', $today, $log_element, $log_location, $username, $remote_address, $log_info);
                    } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_2) {
                        $log_text = Text::sprintf('swfupload_error_2', $today, $log_element, $log_location, $username, $remote_address);
                    } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_3) {
                        $log_text = Text::sprintf('swfupload_error_3', $today, $log_element, $log_location, $username, $remote_address);
                    } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_4) {
                        $log_text = Text::sprintf('swfupload_error_4', $today, $log_element, $log_location, $username, $remote_address);
                    } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_5) {
                        $log_text = Text::sprintf('swfupload_error_5', $today, $log_element, $log_location, $username, $remote_address);
                    } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_6) {
                        $log_text = Text::sprintf('swfupload_error_6', $today, $log_element, $log_location, $username, $remote_address);
                    } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_7) {
                        $log_text = Text::sprintf('swfupload_error_7', $today, $log_element, $log_location, $username, $remote_address);
                    } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_8) {
                        $log_text = Text::sprintf('swfupload_error_8', $today, $log_element, $log_location, $username, $remote_address);
                    }
                    break;
                case LOG_ACTION_DOWNLOAD:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_downloads" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    $log_text = Text::sprintf('download_log_text', $today, $log_element, $log_location, $username, $remote_address);
                    break;
                case LOG_ACTION_DELFOLDER:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_removedfolders" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    $log_text = Text::sprintf('removedfolder_log_text', $today, $log_element, $log_location, $username, $remote_address);
                    break;
                case LOG_ACTION_DELFILE:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_removedfiles" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    $log_text = Text::sprintf('removedfile_log_text', $today, $log_element, $log_location, $username, $remote_address);
                    break;
                case LOG_ACTION_RESTOREFILE:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_restoredfiles" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    $log_text = Text::sprintf('restoredfile_log_text', $today, $log_element, $log_location, $username, $remote_address);
                    break;
                case LOG_ACTION_NEWFOLDER:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_newfolders" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    $log_text = Text::sprintf('newfolder_log_text', $today, $log_element, $log_location, $username, $remote_address);
                    break;
                case LOG_ACTION_RENFOLDER:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_newfoldernames" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    $log_text = Text::sprintf('newfoldername_log_text', $today, $log_element, $log_info, $log_location, $username, $remote_address);
                    break;
                case LOG_ACTION_RENFILE:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_newfilenames" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    $log_text = Text::sprintf('newfilename_log_text', $today, $log_element, $log_info, $log_location, $username, $remote_address);
                    break;
                case LOG_ACTION_UNZIP:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_unzippedfiles" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    $log_text = Text::sprintf('unzippedfile_log_text', $today, $log_element, $log_location, $username, $remote_address);
                    break;
            }
            if ($log_status == LOG_STATUS_LOG_AND_MAIL) {
                $this->email_log($log_type, $log_text);
            }
            file_put_contents($log_file, $log_text, FILE_APPEND);
        } elseif ($log_type == LOG_TYPE_RDBM) {
            // database type TODO
        } else {
            // JSON type
            $log_text_array = array();
            $log_text_array['timestamp'] = $today;
            $log_text_array['new_element'] = "";
            $log_text_array['info'] = "";

            switch ($log_action) {
                case LOG_ACTION_UPLOAD:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_uploads" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    if ($log_action_result > LOG_ACTION_RESULT_OK) {
                        if ($log_action_result == LOG_ACTION_UPLOAD_ERROR_1) {
                            $log_text_array['info'] = Text::sprintf('swfupload_error_1_info', $log_info);
                        } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_2) {
                            $log_text_array['info'] = Text::_('swfupload_error_2_info');
                        } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_3) {
                            $log_text_array['info'] = Text::_('swfupload_error_3_info');
                        } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_4) {
                            $log_text_array['info'] = Text::_('swfupload_error_4_info');
                        } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_5) {
                            $log_text_array['info'] = Text::_('swfupload_error_5_info');
                        } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_6) {
                            $log_text_array['info'] = Text::_('swfupload_error_6_info');
                        } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_7) {
                            $log_text_array['info'] = Text::_('swfupload_error_7_info');
                        } elseif ($log_action_result == LOG_ACTION_UPLOAD_ERROR_8) {
                            $log_text_array['info'] = Text::_('swfupload_error_8_info');
                        }
                    }
                    break;
                case LOG_ACTION_DOWNLOAD:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_downloads" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    break;
                case LOG_ACTION_DELFOLDER:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_removedfolders" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    break;
                case LOG_ACTION_DELFILE:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_removedfiles" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    break;
                case LOG_ACTION_RESTOREFILE:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_restoredfiles" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    break;
                case LOG_ACTION_NEWFOLDER:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_newfolders" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    break;
                case LOG_ACTION_RENFOLDER:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_newfoldernames" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    $log_text_array['new_element'] = $log_info;
                    break;
                case LOG_ACTION_RENFILE:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_newfilenames" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    $log_text_array['new_element'] = $log_info;
                    break;
                case LOG_ACTION_UNZIP:
                    $log_file = ($logfile_consolidation == LOG_FILE_MULTIPLE) ? $logfile_prefix . "_unzippedfiles" . $logfile_extension : $logfile_prefix . $logfile_extension;
                    break;
            }

            // other array elements
            $log_text_array['action_type'] = $log_action;
            $log_text_array['action_result'] = $log_action_result;
            $log_text_array['element'] = $log_element;
            $log_text_array['location'] = $log_location;
            $log_text_array['userid'] = $userid;
            $log_text_array['username'] = $username;
            $log_text_array['user_username'] = $user_username;
            $log_text_array['remote_address'] = $remote_address;

            $log_text = json_encode($log_text_array);

            if ($log_status == LOG_STATUS_LOG_AND_MAIL) {
                $this->email_log($log_type, $log_text);
            }
            file_put_contents($log_file, $log_text . "\n", FILE_APPEND);
        }
    }

    public function format_logtext_for_display($log_type, $logfile_consolidation, $logtext)
    {
        $color = $this->params->def('log_highlighted_color', "FF6600");

        if ($log_type == LOG_TYPE_TEXT) {
            $logtext = preg_replace("/\\n/", "<hr />", $logtext);
            $logtext = preg_replace("/\[/", "<font color='$color'>", $logtext);
            $logtext = preg_replace("/\]/", "</font>", $logtext);
        } elseif ($log_type == LOG_TYPE_JSON) {
            $logtext_lines = explode("\n", $logtext);
            $logtext = "";
            foreach ($logtext_lines as $logtext_line) {
                if (! strlen($logtext_line)) {
                    continue;
                }

                $log_text_array = json_decode($logtext_line, true);
                $logtext .= "<font color='$color'>" . Text::_('log_timestamp_label') . ":</font>&nbsp;" . $log_text_array['timestamp'] . "<br />";
                $logtext .= $logfile_consolidation == LOG_FILE_MULTIPLE ? "" : "<font color='$color'>" . Text::_('log_action_label') . ":</font>&nbsp;" . $this->get_log_action_type_string($log_text_array['action_type']) . "<br />";
                $logtext .= "<font color='$color'>" . Text::_('log_element_label') . ":</font>&nbsp;" . $log_text_array['element'] . "<br />";
                $logtext .= strlen($log_text_array['new_element']) ? "<font color='$color'>" . Text::_('log_new_element_label') . ":</font>&nbsp;" . $log_text_array['new_element'] . "<br />" : "";
                $logtext .= "<font color='$color'>" . Text::_('log_location_label') . ":</font>&nbsp;" . $log_text_array['location'] . "<br />";
                $logtext .= "<font color='$color'>" . Text::_('log_user_id_label') . ":</font>&nbsp;" . $log_text_array['userid'] . "<br />";
                $logtext .= "<font color='$color'>" . Text::_('log_user_name_label') . ":</font>&nbsp;" . $log_text_array['username'] . "<br />";
                $logtext .= "<font color='$color'>" . Text::_('log_login_name_label') . ":</font>&nbsp;" . $log_text_array['user_username'] . "<br />";
                $logtext .= "<font color='$color'>" . Text::_('log_remote_address_label') . ":</font>&nbsp;" . $log_text_array['remote_address'] . "<br />";
                $logtext .= strlen($log_text_array['info']) ? "<font color='$color'>" . Text::_('log_info_label') . ":</font>&nbsp;" . $log_text_array['info'] . "<br />" : "";
                $logtext .= "<hr />";
            }
        }
        return $logtext;
    }

    public function view_log($log_type, $logfile_prefix, $logfile_extension, $logfile_consolidation, &$article, &$params, $description, $masked_dir, $log_uploads, $log_downloads, $log_removedfolders, $log_removedfiles, $log_restoredfiles, $log_newfolders, $log_newfoldernames, $log_newfilenames, $log_unzippedfiles)
    {
        $text = "";

        if ($description) {
            $text = "<strong>$description</strong>";
        }

        $log_id = $this->baseName($logfile_prefix);
        $log_id = substr($log_id, 14);

        // title
        $text .= "<div id='JS_MAIN_DIV'>" . // this page is displayed instead of the main repository page
        "<div id='JS_FILES_DIV'>" . "<table>" . "<tr>" . "<td colspan='2'><strong>" . Text::_('log_title') . "</strong>&nbsp;[ID:&nbsp;" . $log_id . "]<br />&nbsp;</td>" . "</tr>";

        if ($logfile_consolidation == LOG_FILE_MULTIPLE) {
            // uploads
            if ($log_uploads) {

                $logfile = $logfile_prefix . "_uploads" . $logfile_extension;
                $logtext = @file_get_contents($logfile);

                if (! $logtext) {
                    $logtext = Text::_('no_log_found');
                    $icon = $this->imgdirNavigation . "log_not_found.png";
                } else {
                    $logtext = $this->format_logtext_for_display($log_type, $logfile_consolidation, $logtext);
                    $icon = $this->imgdirNavigation . "log_found.png";
                }
            } else {
                $logtext = Text::_('not_logging');
                $icon = $this->imgdirNavigation . "log_disabled.png";
            }
            $text .= "<tr>" . "<td colspan='2'><b>" . Text::_('log_uploads_title') . "</b></td>" . "</tr>" . "<tr>" . "<td class='jsmallicon_log'><img src=\"$icon\"></td><td>$logtext</td>" . "</tr>";

            // downloads
            if ($log_downloads) {

                $logfile = $logfile_prefix . "_downloads" . $logfile_extension;
                $logtext = @file_get_contents($logfile);

                if (! $logtext) {
                    $logtext = Text::_('no_log_found');
                    $icon = $this->imgdirNavigation . "log_not_found.png";
                } else {
                    $logtext = $this->format_logtext_for_display($log_type, $logfile_consolidation, $logtext);
                    $icon = $this->imgdirNavigation . "log_found.png";
                }
            } else {
                $logtext = Text::_('not_logging');
                $icon = $this->imgdirNavigation . "log_disabled.png";
            }
            $text .= "<tr>" . "<td colspan='2'><b>" . Text::_('log_downloads_title') . "</b></td>" . "</tr>" . "<tr>" . "<td class='jsmallicon_log'><img src=\"$icon\"></td><td>$logtext</td>" . "</tr>";

            // removed folders
            if ($log_removedfolders) {

                $logfile = $logfile_prefix . "_removedfolders" . $logfile_extension;
                $logtext = @file_get_contents($logfile);

                if (! $logtext) {
                    $logtext = Text::_('no_log_found');
                    $icon = $this->imgdirNavigation . "log_not_found.png";
                } else {
                    $logtext = $this->format_logtext_for_display($log_type, $logfile_consolidation, $logtext);
                    $icon = $this->imgdirNavigation . "log_found.png";
                }
            } else {
                $logtext = Text::_('not_logging');
                $icon = $this->imgdirNavigation . "log_disabled.png";
            }
            $text .= "<tr>" . "<td colspan='2'><b>" . Text::_('log_removedfolders_title') . "</b></td>" . "</tr>" . "<tr>" . "<td class='jsmallicon_log'><img src=\"$icon\"></td><td>$logtext</td>" . "</tr>";

            // removed files
            if ($log_removedfiles) {

                $logfile = $logfile_prefix . "_removedfiles" . $logfile_extension;
                $logtext = @file_get_contents($logfile);

                if (! $logtext) {
                    $logtext = Text::_('no_log_found');
                    $icon = $this->imgdirNavigation . "log_not_found.png";
                } else {
                    $logtext = $this->format_logtext_for_display($log_type, $logfile_consolidation, $logtext);
                    $icon = $this->imgdirNavigation . "log_found.png";
                }
            } else {
                $logtext = Text::_('not_logging');
                $icon = $this->imgdirNavigation . "log_disabled.png";
            }
            $text .= "<tr>" . "<td colspan='2'><b>" . Text::_('log_removedfiles_title') . "</b></td>" . "</tr>" . "<tr>" . "<td class='jsmallicon_log'><img src=\"$icon\"></td><td>$logtext</td>" . "</tr>";

            // restored files
            if ($log_restoredfiles) {

                $logfile = $logfile_prefix . "_restoredfiles" . $logfile_extension;
                $logtext = @file_get_contents($logfile);

                if (! $logtext) {
                    $logtext = Text::_('no_log_found');
                    $icon = $this->imgdirNavigation . "log_not_found.png";
                } else {
                    $logtext = $this->format_logtext_for_display($log_type, $logfile_consolidation, $logtext);
                    $icon = $this->imgdirNavigation . "log_found.png";
                }
            } else {
                $logtext = Text::_('not_logging');
                $icon = $this->imgdirNavigation . "log_disabled.png";
            }
            $text .= "<tr>" . "<td colspan='2'><b>" . Text::_('log_restoredfiles_title') . "</b></td>" . "</tr>" . "<tr>" . "<td class='jsmallicon_log'><img src=\"$icon\"></td><td>$logtext</td>" . "</tr>";

            // new folders
            if ($log_newfolders) {

                $logfile = $logfile_prefix . "_newfolders" . $logfile_extension;
                $logtext = @file_get_contents($logfile);

                if (! $logtext) {
                    $logtext = Text::_('no_log_found');
                    $icon = $this->imgdirNavigation . "log_not_found.png";
                } else {
                    $logtext = $this->format_logtext_for_display($log_type, $logfile_consolidation, $logtext);
                    $icon = $this->imgdirNavigation . "log_found.png";
                }
            } else {
                $logtext = Text::_('not_logging');
                $icon = $this->imgdirNavigation . "log_disabled.png";
            }
            $text .= "<tr>" . "<td colspan='2'><b>" . Text::_('log_newfolders_title') . "</b></td>" . "</tr>" . "<tr>" . "<td class='jsmallicon_log'><img src=\"$icon\"></td><td>$logtext</td>" . "</tr>";

            // renamed folders
            if ($log_newfoldernames) {

                $logfile = $logfile_prefix . "_newfoldernames" . $logfile_extension;
                $logtext = @file_get_contents($logfile);

                if (! $logtext) {
                    $logtext = Text::_('no_log_found');
                    $icon = $this->imgdirNavigation . "log_not_found.png";
                } else {
                    $logtext = $this->format_logtext_for_display($log_type, $logfile_consolidation, $logtext);
                    $icon = $this->imgdirNavigation . "log_found.png";
                }
            } else {
                $logtext = Text::_('not_logging');
                $icon = $this->imgdirNavigation . "log_disabled.png";
            }
            $text .= "<tr>" . "<td colspan='2'><b>" . Text::_('log_newfoldernames_title') . "</b></td>" . "</tr>" . "<tr>" . "<td class='jsmallicon_log'><img src=\"$icon\"></td><td>$logtext</td>" . "</tr>";

            // renamed files
            if ($log_newfilenames) {

                $logfile = $logfile_prefix . "_newfilenames" . $logfile_extension;
                $logtext = @file_get_contents($logfile);

                if (! $logtext) {
                    $logtext = Text::_('no_log_found');
                    $icon = $this->imgdirNavigation . "log_not_found.png";
                } else {
                    $logtext = $this->format_logtext_for_display($log_type, $logfile_consolidation, $logtext);
                    $icon = $this->imgdirNavigation . "log_found.png";
                }
            } else {
                $logtext = Text::_('not_logging');
                $icon = $this->imgdirNavigation . "log_disabled.png";
            }
            $text .= "<tr>" . "<td colspan='2'><b>" . Text::_('log_newfilenames_title') . "</b></td>" . "</tr>" . "<tr>" . "<td class='jsmallicon_log'><img src=\"$icon\"></td><td>$logtext</td>" . "</tr>";

            // unzipped files
            if ($log_unzippedfiles) {

                $logfile = $logfile_prefix . "_unzippedfiles" . $logfile_extension;
                $logtext = @file_get_contents($logfile);

                if (! $logtext) {
                    $logtext = Text::_('no_log_found');
                    $icon = $this->imgdirNavigation . "log_not_found.png";
                } else {
                    $logtext = $this->format_logtext_for_display($log_type, $logfile_consolidation, $logtext);
                    $icon = $this->imgdirNavigation . "log_found.png";
                }
            } else {
                $logtext = Text::_('not_logging');
                $icon = $this->imgdirNavigation . "log_disabled.png";
            }
            $text .= "<tr>" . "<td colspan='2'><b>" . Text::_('log_unzippedfiles_title') . "</b></td>" . "</tr>" . "<tr>" . "<td class='jsmallicon_log'><img src=\"$icon\"></td><td>$logtext</td>" . "</tr>";
        } else {
            $logfile = $logfile_prefix . $logfile_extension;
            $logtext = @file_get_contents($logfile);

            if (! $logtext) {
                $logtext = Text::_('no_log_found');
                $icon = $this->imgdirNavigation . "log_not_found.png";
            } else {
                $logtext = $this->format_logtext_for_display($log_type, $logfile_consolidation, $logtext);
                $icon = $this->imgdirNavigation . "log_found.png";
            }
            $text .= "<tr>" . "<td class='jsmallicon_log'><img src=\"$icon\"></td><td>$logtext</td>" . "</tr>";
        }

        // final link
        $text .= // "<tr>"
                    // ."<td colspan='2'><a href='".$this->baselink."&dir=".$this->urlEncodePreserveForwardSlashes($masked_dir)."'>".Text::_('go_back')."</a></td>"
                    // ."</tr>"
        "</table>" . "</div>" . "<p><br /><a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "'>" . Text::_('go_back') . "</a></p>" . "</div>";

        $article->text = $article->fulltext = $article->introtext = $text;

        $params->set('show_author', '0');
        $params->set('show_create_date', '0');
        $params->set('show_modify_date', '0');
        return;
    }

    public function get_log_action_type_string($action_type)
    {
        switch ($action_type) {
            case LOG_ACTION_UPLOAD:
                $log_action_string = Text::_('log_action_type_upload');
                break;
            case LOG_ACTION_DOWNLOAD:
                $log_action_string = Text::_('log_action_type_download');
                break;
            case LOG_ACTION_DELFOLDER:
                $log_action_string = Text::_('log_action_type_delfolder');
                break;
            case LOG_ACTION_DELFILE:
                $log_action_string = Text::_('log_action_type_delfile');
                break;
            case LOG_ACTION_RESTOREFILE:
                $log_action_string = Text::_('log_action_type_restorefile');
                break;
            case LOG_ACTION_NEWFOLDER:
                $log_action_string = Text::_('log_action_type_newfolder');
                break;
            case LOG_ACTION_RENFOLDER:
                $log_action_string = Text::_('log_action_type_renfolder');
                break;
            case LOG_ACTION_RENFILE:
                $log_action_string = Text::_('log_action_type_renfile');
                break;
            case LOG_ACTION_UNZIP:
                $log_action_string = Text::_('log_action_type_unzip');
                break;
        }
        return $log_action_string;
    }

    public function email_log($log_type, $log_text)
    {
        $log_email_from_string = $this->params->def('log_email_from_string', "Jsmallfib Log Alert");
        $log_email_from = $this->params->def('log_email_from', "");
        $log_email_to = $this->params->def('log_email_to', "");
        $log_email_subject = $this->params->def('log_email_subject', "Jsmallfib Log Alert");

        if ($log_email_from && $log_email_to) {
            // $mailer =& JFactory::getMailer();
            $mailer = Factory::getMailer();

            if ($log_type != LOG_TYPE_TEXT) {
                // if JSON or RDBM type, format text based on JSON format (different if HTML)
                $log_text_array = json_decode($log_text, true);

                if ($this->params->def('mail_in_html_mode', 1)) {
                    $mailer->isHTML(true);
                    $mailer->Encoding = 'base64';

                    $log_text = "<table border=0>";
                    $log_text .= "<tr><td><strong>" . Text::_('log_timestamp_label') . ":</strong></td><td>" . $log_text_array['timestamp'] . "</td></tr>";
                    $log_text .= "<tr><td><strong>" . Text::_('log_action_label') . ":</strong></td><td>" . $this->get_log_action_type_string($log_text_array['action_type']) . "</td></tr>";
                    $log_text .= "<tr><td><strong>" . Text::_('log_element_label') . ":</strong></td><td>" . $log_text_array['element'] . "</td></tr>";
                    $log_text .= strlen($log_text_array['new_element']) ? "<tr><td><strong>" . Text::_('log_new_element_label') . ":</strong></td><td>" . $log_text_array['new_element'] . "</td></tr>" : "";
                    $log_text .= "<tr><td><strong>" . Text::_('log_location_label') . ":</strong></td><td>" . $log_text_array['location'] . "</td></tr>";
                    $log_text .= "<tr><td><strong>" . Text::_('log_user_id_label') . ":</strong></td><td>" . $log_text_array['userid'] . "</td></tr>";
                    $log_text .= "<tr><td><strong>" . Text::_('log_user_name_label') . ":</strong></td><td>" . $log_text_array['username'] . "</td></tr>";
                    $log_text .= "<tr><td><strong>" . Text::_('log_login_name_label') . ":</strong></td><td>" . $log_text_array['user_username'] . "</td></tr>";
                    $log_text .= "<tr><td><strong>" . Text::_('log_remote_address_label') . ":</strong></td><td>" . $log_text_array['remote_address'] . "</td></tr>";
                    $log_text .= strlen($log_text_array['info']) ? "<tr><td><strong>" . Text::_('log_info_label') . ":</strong></td><td>" . $log_text_array['info'] . "</td></tr>" : "";
                    $log_text .= "</table>";
                } else {
                    $log_text = Text::_('log_timestamp_label') . ": " . $log_text_array['timestamp'] . "\n";
                    $log_text .= Text::_('log_action_label') . ": " . $this->get_log_action_type_string($log_text_array['action_type']) . "\n";
                    $log_text .= Text::_('log_element_label') . ": " . $log_text_array['element'] . "\n";
                    $log_text .= strlen($log_text_array['new_element']) ? Text::_('log_new_element_label') . ": " . $log_text_array['new_element'] . "\n" : "";
                    $log_text .= Text::_('log_location_label') . ": " . $log_text_array['location'] . "\n";
                    $log_text .= Text::_('log_user_id_label') . ": " . $log_text_array['userid'] . "\n";
                    $log_text .= Text::_('log_user_name_label') . ": " . $log_text_array['username'] . "\n";
                    $log_text .= Text::_('log_login_name_label') . ": " . $log_text_array['user_username'] . "\n";
                    $log_text .= Text::_('log_remote_address_label') . ": " . $log_text_array['remote_address'] . "\n";
                    $log_text .= strlen($log_text_array['info']) ? Text::_('log_info_label') . ": " . $log_text_array['info'] . "\n" : "";
                }
            }

            $mailer->setSender(array(
                $log_email_from,
                $log_email_from_string
            ));
            $mailer->addRecipient($log_email_to);
            $mailer->setSubject($log_email_subject);
            $mailer->setBody($log_text);

            if ($mailer->Send() !== true) {
                return 1;
            } // error
            else {
                return 0;
            } // OK
        }
    }

    // ***********************************************************************************************************************
    // Info display functions
    // ***********************************************************************************************************************
    public function do_help(&$article, &$params, $description, $masked_dir)
    {
        $text = "";

        if ($description) {
            $text = "<strong>$description</strong>";
        }

        // $helptitle = Text::_('help');
        // $helptext = Text::_('plg_jsmallfib_desc');
        // $helptext = preg_replace("/\.\.\/plugins/", "plugins", $helptext);
        $helptext = Text::_('info_page_text');

        // $text .= "<div id='JS_MAIN_DIV'>"
        // ."<table>"
        // ."<tr>"
        // ."<td colspan='2'><strong>$helptitle</strong></td>"
        // ."</tr>"
        // ."<tr>"
        // ."<td width='60px'><img src=\"".$this->imgdirNavigation."null.gif\"></td><td>$helptext</td>"
        // ."</tr>"
        // ."<tr>"
        // ."<td colspan='2'><a href='".$this->baselink."&dir=".$this->urlEncodePreserveForwardSlashes($masked_dir)."'>".Text::_('go_back')."</a></td>"
        // ."</tr>"
        // ."</table>"
        // ."</div>";
        $text .= "<div id='JS_MAIN_DIV'>" .
        // ."<p><strong>$helptitle</strong></p>"
        // ."<p><br /><strong>JSmallfib Info</strong></p>"
        "<div id='JS_FILES_DIV'>" . "<table>" . "<tr>" . "      <td>&nbsp;&nbsp;</td>" . "      <td>$helptext</td>" . "</tr>" . "</table>" . "</div>" . "<p><br /><a href='" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "'>" . Text::_('go_back') . "</a></p>" . "</div>";

        $article->text = $article->fulltext = $article->introtext = $text;

        $params->set('show_author', '0');
        $params->set('show_create_date', '0');
        $params->set('show_modify_date', '0');
        return;
    }

    // ***********************************************************************************************************************
    // Auxiliary functions
    // ***********************************************************************************************************************
    public function article_written_by_untrusted_author($article)
    {
        $trusted_authors_parameter = trim($this->params->def('trusted_authors', ""));
        if (! strlen($trusted_authors_parameter)) {
            return false; // if the parameter is not defined, assume all authors are trusted
        }

        $trusted_authors_raw_array = explode(",", $trusted_authors_parameter);

        // echo "RAW ARRAY:<br />";
        // print_r($trusted_authors_raw_array);
        // echo "<br />";

        $authors = $groups = 0;
        $trusted_authors = array();
        $trusted_groups = array();

        for ($i = 0; $i < count($trusted_authors_raw_array); $i++) {
            $cur_element = trim($trusted_authors_raw_array[$i]);
            if (is_numeric($cur_element)) {
                $trusted_authors[$authors] = $cur_element;
                $authors++;
            } else {
                $cur_element = preg_replace("/[gG]/", "", $cur_element);
                if (is_numeric($cur_element)) {
                    $trusted_groups[$groups] = $cur_element;
                    $groups++;
                }
            }
        }

        if (! $authors && ! $groups) {
            return false;
        }

        // echo "AUTHORS [".$authors."]:<br />";
        // print_r($trusted_authors);
        // echo "<br />";
        // echo "GROUPS [".$groups."]:<br />";
        // print_r($trusted_groups);
        // echo "<br />";

        // if groups are found, then query the database to get a list of users belonging to those groups
        if ($groups) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            //             $query = $db->getQuery(true);

            //             $query->select("user_id");
            //             $query->from("#__user_usergroup_map");
            //             $query->where("group_id = " . $trusted_groups[0]);

            //             for ($i = 1; $i < count($groups); $i ++) {
            //                 $query->where("group_id = " . $trusted_groups[$i]);
            //             }
            $query = "SELECT user_id FROM #__user_usergroup_map WHERE group_id = ".$trusted_groups[0];

            for ($i = 1; $i < count($groups); $i++) {
                $query .= " AND group_id = ".$trusted_groups[$i];
            }

            $db->setQuery($query);
            try {
                $row = $db->loadObjectList();
            } catch (Exception $e) {
                Factory::getApplication()->enqueueMessage('SQL error '. $e->getCode() .' '. $e->getMessage());
                return false;
            }


            // add user ids to the trusted authors array
            for ($i = 0; $i < count($row); $i++) {
                $trusted_authors[$authors] = $row[$i]->user_id;
                $authors++;
            }
        }

        // echo "ALL AUTHORS [".$authors."]:<br />";
        // print_r($trusted_authors);
        // echo "<br />";
        //
        // echo "ARTICLE CREATED BY USER ID ".$article->created_by."<br />";
        // echo "ARTICLE MODIFIED BY USER ID ".$article->modified_by."<br />";

        // do the actual check
        if ($article->modified_by != 0 && $article->modified_by != "") {
            if (in_array($article->modified_by, $trusted_authors)) {
                return false; // this means OK for display
            } else {
                return true;
            }
        } else {
            if (in_array($article->created_by, $trusted_authors)) {
                return false; // this means OK for display
            } else {
                return true;
            }
        }
    }

    // ***********************************************************************************************************************
    // Javascript and Cascading Style Sheets used locally, and other functions
    // ***********************************************************************************************************************
    public function do_js()
    {
        $js = "function confirmDelfolder(baselink, dir, delfolder, msgString) {" .
        "	var browser=navigator.appName;" . "	var b_version=navigator.appVersion;" . "	var version=parseFloat(b_version);" .
        "	if (confirm(msgString)) {" .
        "	if (browser=='Netscape')" . "	{" . "	window.location=baselink+'&dir='+escape(encodeURI(dir))+'&delfolder='+escape(encodeURI(delfolder));" . // Firefox
        "	}" . "	else if (browser=='Microsoft Internet Explorer')" . "	{" . "	window.location=baselink+'&dir='+escape(dir)+'&delfolder='+escape(delfolder);" . // IE
        "	}" . "	else" . "	{" . "	window.location=baselink+'&dir='+escape(dir)+'&delfolder='+escape(delfolder);" . // treat others like IE
        "	}" . "	return;" . "	}" . "	}" .
        "	function confirmDelfile(baselink, dir, delfile, msgString) {" .
        "	var browser=navigator.appName;" . "	var b_version=navigator.appVersion;" . "	var version=parseFloat(b_version);" .
        "	if (confirm(msgString)) {" .
        "	if (browser=='Netscape')" . "	{" . "	window.location=baselink+'&dir='+escape(encodeURI(dir))+'&delfile='+escape(encodeURI(delfile));" . // Firefox
        "	}" . "	else if (browser=='Microsoft Internet Explorer')" . "	{" . "	window.location=baselink+'&dir='+escape(dir)+'&delfile='+escape(delfile);" . // IE
        "	}" . "	else" . "	{" . "	window.location=baselink+'&dir='+escape(dir)+'&delfile='+escape(delfile);" . // treat others like IE
        "	}" . "	return;" . "	}" . "	}" .
        "	function confirmExtractfile(baselink, dir, extfile, msgString) {" .
        "	var browser=navigator.appName;" . "	var b_version=navigator.appVersion;" . "	var version=parseFloat(b_version);" .
        "	if (confirm(msgString)) {" .
        "	if (browser=='Netscape')" . "	{" . "	window.location=baselink+'&dir='+escape(encodeURI(dir))+'&extfile='+escape(encodeURI(extfile));" . // Firefox
        "	}" . "	else if (browser=='Microsoft Internet Explorer')" . "	{" . "	window.location=baselink+'&dir='+escape(dir)+'&extfile='+escape(extfile);" . // IE
        "	}" . "	else" . "	{" . "	window.location=baselink+'&dir='+escape(dir)+'&extfile='+escape(extfile);" . // treat others like IE
        "	}" . "	return;" . "	}" . "	}" .
        "	function confirmRestoreFile(baselink, dir, restorefile, msgString) {" .
        "	var browser=navigator.appName;" . "	var b_version=navigator.appVersion;" . "	var version=parseFloat(b_version);" .
        "	if (confirm(msgString)) {" .
        "	if (browser=='Netscape')" . "	{" . "	window.location=baselink+'&dir='+escape(encodeURI(dir))+'&restorefile='+escape(encodeURI(restorefile));" . // Firefox
        "	}" . "	else if (browser=='Microsoft Internet Explorer')" . "	{" . "	window.location=baselink+'&dir='+escape(dir)+'&restorefile='+escape(restorefile);" . // IE
        "	}" . "	else" . "	{" . "	window.location=baselink+'&dir='+escape(dir)+'&restorefile='+escape(restorefile);" . // treat others like IE
        "	}" . "	return;" . "	}" . "	}";

        return ($js);
    }

    public function do_css()
    {
        $css = "" .

        // main div (default values)

        "#JS_MAIN_DIV " . "{" .
        // ." background-color:rgba(0,0,0,0);" // we set this as transparent (default value)
        "      background-color:transparent;" . // we set this as transparent (default value)
        "	width:" . ($this->table_width) . "px;" . "      padding:0px;" . "	border:0px;" . "	margin: 0 auto 0 auto;" . // this will center the content inside the main div
                                                                                                                         // ." font-family: Verdana;"
                                                                                                                         // ." font-size: 11px;"
        "}" .

        // main div tables

        "#JS_MAIN_DIV table " . "{" .
        // ." background-color:rgba(0,0,0,0);" // we set this as transparent (default value)
        "      background-color:transparent;" . // we set this as transparent (default value)
        "	width:100%;" . "      padding:0px;" . "	border:0px !important;" . "     	border-spacing:0px;" . "	margin:0px;" . "      cellspacing:0px !important;" . "      cellpadding:0px !important;" . "}	" .

        // for all table rows

        "#JS_MAIN_DIV tr {" .
        // ." background-color:rgba(0,0,0,0);" // we set this as transparent (default value)
        "      background-color:transparent;" . // we set this as transparent (default value)
        "      background:url('" . $this->imgdirNavigation . "null.gif');" . "	height:" . ($this->min_row_height) . "px;" . "      padding:0px;" . "	border:0px;" . "      margin:0px;" . "}	" .
        "#JS_MAIN_DIV tr.jsmalline {" . "	background-color:#" . $this->line_bgcolor . ";" . "	height:" . ($this->line_height) . "px;" . "}	" .

        // for all table cells

        "#JS_MAIN_DIV td {" .
        // ." background-color:rgba(0,0,0,0);" // we set this as transparent (default value)
        "      background-color:transparent;" . // we set this as transparent (default value)
        "	padding:0px;" . "	border: 0px;" . "	margin: 0px;" . "	text-align:left;" . "      vertical-align:middle;" . "}	" .
        "#JS_MAIN_DIV td.right_aligned {" .
        // ." width: 100px;"
        "	text-align:right;" . "}	" .
        "#JS_MAIN_DIV td.emptyTd {" . "	width: 0px;" . "}	" .
        "#JS_MAIN_DIV td.actionIcon {" . "	width: 25px;" . "	text-align:center;" . "}	" .
        "#JS_MAIN_DIV td.jsmallicon_log {" . "      vertical-align:top;" . "	text-align:center;" . "	width:30px;" . "	padding:" . $this->icon_padding . "px;" . "}	" .

        // for all input fields

        "#JS_MAIN_DIV input {" . "	background-color:#FFFFFF;" . "	border:0px;" . "}	" .
        "#JS_MAIN_DIV input[type=text], #JS_MAIN_DIV input[type=file], #JS_MAIN_DIV select {" .
        // ." width:40%;"//.($this->table_width - 380)."px;"
        "	background-color:#" . $this->inputbox_bgcolor . ";" . "	border: " . $this->inputbox_border . "px; border-style: " . $this->inputbox_linetype . "; border-color: #" . $this->inputbox_linecolor . ";" . "}	" .
        "#JS_MAIN_DIV input[type=image] {" . "	background-color:transparent;" . "	border: 0px;" . "}	" .
        "#JS_MAIN_DIV .long_input_field {" . "	width:99%;" . "}" .
        "#JS_MAIN_DIV a { background-image:none; } " . // removes background (extra icons) when using some nasty templates (ref. Clay Hess)

        // for JS_TOP_DIV elements

        "#JS_TOP_DIV {" . "	width:" . ((int) $this->table_width - 10) . "px;" . "	padding:5px;" . "	border:0px;" . "	margin: 0px;" . "}	" .
        "#JS_TOP_DIV tr {" . "	height:10px;" . "}	" .
        "#JS_TOP_DIV td.navigation {" . "	text-align:left;" . "}	" .
        "#JS_TOP_DIV td.topLinks {" . "	text-align:right;" . "}	" .

        // for JS_FILES_DIV elements

        "#JS_FILES_DIV {" . "	width:" . ((int) $this->table_width - 10 - 2 * $this->framebox_border) . "px;" . "	background-color:#" . $this->framebox_bgcolor . ";" . "	text-align:left;" . "	margin: 0;" . "	padding:5px;" . "	border: " . $this->framebox_border . "px; border-style: " . $this->framebox_linetype . "; border-color: #" . $this->framebox_linecolor . ";";

        $css .= "      border-radius:" . $this->border_radius . "px;";

        if ($this->use_box_shadow) {
            /* for Firefox */
            $css .= "-moz-box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);"
                                /* for Safari and Chrome */
                                ."-webkit-box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);"
                                /* W3C specs */
                                ."box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);";
        }
        $css .= "}  " .
        "#JS_FILES_DIV tr.row {display:table;width:100%}" .
        "#JS_FILES_DIV tr.groupSwitch {" . "	height:25px;" . "}	" .
        "#JS_FILES_DIV tr.highlighted {" . "	background-color:#" . ($this->highlighted_color) . ";" . "}	" .
        "#JS_FILES_DIV tr.row.header {" . "	background-color:#" . ($this->header_bgcolor) . ";" . "}	" .
        "#JS_FILES_DIV tr.row.odd {" . "	background-color:#" . ($this->oddrows_color) . ";" . "}	" .
        "#JS_FILES_DIV tr.row.even {" . "	background-color:#" . ($this->evenrows_color) . ";" . "}	" .
        "#JS_FILES_DIV td.groupSwitchIcon {" . "	text-align:center;" . "	width:40px;" . // we don't put 0 here as Safari seems to take it as 'no fixed width'
        "	padding:5px;" . "}	" .
        "#JS_FILES_DIV td.fileIcon {" . "	text-align:center;" . "	width:" . $this->icon_width . "px;max-width:inherit;" .
        // ." width:1px;" // we don't put 0 here as Safari seems to take it as 'no fixed width' // ES20120502 replaced by line above (ref. Clay Hess)
        "	padding:" . $this->icon_padding . "px;" . "}	" .
        "#JS_FILES_DIV td.fileThumb {" . "	padding:" . $this->icon_padding . "px;width:32px;text-align: center;" .
        // ." width:".($this->thumbsize + 10)."px;"
        "}	" .
        "#JS_FILES_DIV td.fileName {" . "	width:30%;" . // auto J4 this is needed on IE when displaying only folders, to force names to take most of the space, squeezing other icons to their respective size!
        "}	" .
        "#JS_FILES_DIV td.fileSize {" .
        // ." width: 100px;"
        "	text-align:right;width:15%" . "}	" .
        "#JS_FILES_DIV td.fileChanged {" . "	width: 25%;" . "	text-align:center;" . "}	" .
        "#JS_FILES_DIV td.fileAction {" . "	width: 5%;" . "	text-align:center;" . "}	" .

        // filter table

        "#JS_FILES_DIV table.filterTable tr {" . "      align:right;" . "}	" .
        "#JS_FILES_DIV table.filterTable td {" . "	text-align:right;" . "}	" .
        "#JS_FILES_DIV td.filterIconTick {" . "	text-align:center;" . "	width:25px;" . "	padding:0px 0px 0px 5px;" . "}	" .
        "#JS_FILES_DIV td.filterIconDelete {" . "	text-align:center;" . "	width:25px;" . "	padding:0px 10px 0px 0px;" . "}	" .

        // for JS_ARCHIVE_DIV elements

        "#JS_ARCHIVE_DIV {" . "	width:" . ((int) $this->table_width - 10 - 2 * $this->framebox_border) . "px;" . "	background-color:#" . $this->framebox_bgcolor . ";" . "	text-align:left;" . "	margin: " . $this->box_distance . "px 0px 0px 0px;" . "	padding:5px;" . "	border: " . $this->framebox_border . "px; border-style: " . $this->framebox_linetype . "; border-color: #" . $this->framebox_linecolor . ";";

        $css .= "      border-radius:" . $this->border_radius . "px;";

        if ($this->use_box_shadow) {
            /* for Firefox */
            $css .= "-moz-box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);"
                                /* for Safari and Chrome */
                                ."-webkit-box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);"
                                /* W3C specs */
                                ."box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);";
        }
        $css .= "}  " .

        // for JS_ACTIONS_DIV

        "#JS_ACTIONS_DIV {" . "	width:" . ((int) $this->table_width - 10 - 2 * $this->uploadbox_border) . "px;" . "	background-color:#" . $this->uploadbox_bgcolor . ";" . "	text-align:left;" . "	margin: " . $this->box_distance . "px 0px 0px 0px;" . "	padding:5px;" . "	border: " . $this->uploadbox_border . "px; border-style: " . $this->uploadbox_linetype . "; border-color: #" . $this->uploadbox_linecolor . ";";

        $css .= "      border-radius:" . $this->border_radius . "px;";

        if ($this->use_box_shadow) {
            /* for Firefox */
            $css .= "-moz-box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);"
                                /* for Safari and Chrome */
                                ."-webkit-box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);"
                                /* W3C specs */
                                ."box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);";
        }
        $css .= "}  " .
        "#JS_ACTIONS_DIV td.actionIcon {" . "	text-align:center;" . "	width:25px;" . "	padding:0px 5px 0px 5px;" . "}	" .

        // for JS_BOTTOM_DIV

        "#JS_BOTTOM_DIV {" . "	width:" . $this->table_width . "px;" . "	margin:0px;" . "	padding:0px;" . "	border:0px;" . "}	" .

        // for JS_ERROR_DIV

        "#JS_ERROR_DIV {" . "	width:" . ((int) $this->table_width - 10 - 2 * $this->errorbox_border) . "px;" . "	background-color:#" . $this->errorbox_bgcolor . ";" . "	text-align:left;" . "	margin: 30px 0px 0px 0px;" . "	padding:5px;" . "	border: " . $this->errorbox_border . "px; border-style: " . $this->errorbox_linetype . "; border-color: #" . $this->errorbox_linecolor . ";";

        $css .= "      border-radius:" . $this->border_radius . "px;";

        if ($this->use_box_shadow) {
            /* for Firefox */
            $css .= "-moz-box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);"
                                /* for Safari and Chrome */
                                ."-webkit-box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);"
                                /* W3C specs */
                                ."box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);";
        }
        $css .= "}  " .
            "#JS_SUCCESS_DIV {" . "	width:" . ((int) $this->table_width - 10 - 2 * $this->successbox_border) . "px;" . "	background-color:#" . $this->successbox_bgcolor . ";" . "	text-align:left;" . "	margin: 30px 0px 0px 0px;" . "	padding:5px;" . "	border: " . $this->successbox_border . "px; border-style: " . $this->successbox_linetype . "; border-color: #" . $this->successbox_linecolor . ";";

        $css .= "      border-radius:" . $this->border_radius . "px;";

        if ($this->use_box_shadow) {
            /* for Firefox */
            $css .= "-moz-box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);"
                                /* for Safari and Chrome */
                                ."-webkit-box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);"
                                /* W3C specs */
                                ."box-shadow: " . $this->shadow_width . "px " . $this->shadow_width . "px " . $this->shadow_blur . "px rgba(" . $this->shadow_color . "," . $this->shadow_color . "," . $this->shadow_color . ",0.8);";
        }
        $css .= "}  " .
        "#JS_ERROR_DIV td.alertIcon, #JS_SUCCESS_DIV td.alertIcon {" . "      vertical-align:top;" . "	text-align:center;" . "	width:60px;" . "}	" .

        // TODO remove this css
        "#jsmallspacer {" . "	width:100%" . "	margin:0px;" . "	padding:5px;" . "	margin: 0px;" . "}	" .

        // for SWFUpload

        "#upload div.fieldset span.legend {" . "	position: relative;" . "	background-color:#" . $this->uploadbox_bgcolor . ";" . "	padding: 8px;" . "	top: -30px;" . "	font-family:Verdana;" . "	font-size:11px;" . "	#font: 700 14px Arial, Helvetica, sans-serif;" . "	color: #888888;" . "}	" .
        "#upload div.flash {" . "	width: 375px;" . "	margin: 10px 5px;" . "	border-color: #" . $this->inputbox_linecolor . ";";

        $css .= "	-moz-border-radius-topleft : " . $this->border_radius . "px;" . "	-webkit-border-top-left-radius : " . $this->border_radius . "px;" . "	-moz-border-radius-topright : " . $this->border_radius . "px;" . "	-webkit-border-top-right-radius : " . $this->border_radius . "px;" . "	-moz-border-radius-bottomleft : " . $this->border_radius . "px;" . "	-webkit-border-bottom-left-radius : " . $this->border_radius . "px;" . "	-moz-border-radius-bottomright : " . $this->border_radius . "px;" . "	-webkit-border-bottom-right-radius : " . $this->border_radius . "px;";

        $css .= "}	" .
        "#upload input[disabled] {" . "	border: " . $this->inputbox_border . "px; border-style: " . $this->inputbox_linetype . "; border-color: #" . $this->inputbox_linecolor . ";" . "}	";


        return $css;
    }

    //
    // Format the file size
    //
    public function fileSizeF($size)
    {
        $sizes = array(
            'B',
            'KB',
            'MB',
            'GB',
            'TB',
            'PB',
            'EB'
        );
        $y = $sizes[0];
        for ($i = 1; (($i < count($sizes)) && ($size >= 1024)); $i++) {
            $size = $size / 1024;
            $y = $sizes[$i];
        }

        // Erik: Adjusted number format
        $dec = max(0, (3 - strlen(round($size))));
        return number_format($size, $dec, $this->filesize_separator, " ") . " " . $y;
        // Old code:
        // return round($size, 2)." ".$y;
    }

    public function fileRealSize($file)
    {
        $sizeInBytes = @filesize($file);
        //
        // If filesize() fails (with larger files), try to get the size from unix command line.
        if ($sizeInBytes === false) {
            $sizeInBytes = @exec("ls -l '$file' | awk '{print $5}'");
        } else {
            return $sizeInBytes;
        }
    }

    //
    // Return file extension (the string after the last dot.
    // NOTE: THIS FUNCTION IS REPLICATED IN UPLOAD.PHP
    //
    public function fileExtension($file)
    {
        $a = explode(".", $file);
        $b = count($a);
        return $a[$b - 1];
    }

    // Return file without extension (the string before the last dot.
    // NOTE: THIS FUNCTION IS REPLICATED IN UPLOAD.PHP
    //
    public function fileWithoutExtension($file)
    {
        $a = explode(".", $file);
        $b = count($a);
        $c = $a[0];
        for ($i = 1; $i < $b - 1; $i++) {
            $c .= "." . $a[$i];
        }
        return $c;
    }

    //
    // Formatting the changing time
    //
    public function fileChanged($time)
    {
        if (! $this->display_filetime) {
            $timeformat = "";
        } elseif ($this->display_seconds) {
            $timeformat = " H:i:s";
        } else {
            $timeformat = " H:i";
        }

        switch ($this->date_format) {
            case 'dd_mm_yyyy_dashsep':
                return date("d-m-Y" . $timeformat, $time);
            case 'dd_mm_yyyy_pointsep':
                return date("d.m.Y" . $timeformat, $time);
            case 'dd_mm_yyyy_slashsep':
                return date("d/m/Y" . $timeformat, $time);
            case 'yyyy_mm_dd_dashsep':
                return date("Y-m-d" . $timeformat, $time);
            case 'yyyy_mm_dd_pointsep':
                return date("Y.m.d" . $timeformat, $time);
            case 'yyyy_mm_dd_slashsep':
                return date("Y/m/d" . $timeformat, $time);
            case 'mm_dd_yyyy_dashsep':
                return date("m-d-Y" . $timeformat, $time);
            case 'mm_dd_yyyy_pointsep':
                return date("m.d.Y" . $timeformat, $time);
            case 'mm_dd_yyyy_slashsep':
                return date("m/d/Y" . $timeformat, $time);
        }
    }

    //
    // Find the icon for the extension
    //
    public function fileIcon($l)
    {
        $l = strtolower($l);

        if (file_exists($this->imgdirExtensions . $l . ".png")) {
            return $this->imgdirExtensions . "$l.png";
        } else {
            return $this->imgdirExtensions . "unknown.png";
        }
    }

    //
    // Generates the sorting arrows
    //
    public function makeArrow($sort_by, $sort_as, $type, $masked_dir, $text)
    {
        // set icons
        $sort_icon = $this->cur_sort_by == $type ? ($this->cur_sort_as == "desc" ? "arrow_down.png" : "arrow_up.png") : "null.gif";

        // set links (with relevant icons)
        if (($sort_by == $type || $this->cur_sort_by == $type) && ($sort_as == "desc" || $this->cur_sort_as == "desc")) {
            return "<a href=\"" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&amp;sort_by=" . $type . "&amp;sort_as=asc\" title=\"" . Text::_('set_ascending_order') . "\"> $text <img style=\"border:0;\" src=\"" . $this->imgdirNavigation . $sort_icon . "\" /></a>";
        } else {
            return "<a href=\"" . $this->baselink . "&dir=" . $this->urlEncodePreserveForwardSlashes($masked_dir) . "&amp;sort_by=" . $type . "&amp;sort_as=desc\" title=\"" . Text::_('set_descending_order') . "\"> $text <img style=\"border:0;\" src=\"" . $this->imgdirNavigation . $sort_icon . "\" /></a>";
        }
    }

    //
    // Functions that help sort the files
    //
    public function dirname_cmp_asc($a, $b)
    {
        return $this->default_sort_nat ? strnatcasecmp($a, $b) : strcasecmp($a, $b);
    }

    public function filename_cmp_asc($a, $b)
    {
        return $this->default_sort_nat ? strnatcasecmp($a["name"], $b["name"]) : strcasecmp($a["name"], $b["name"]);
    }

    public function size_cmp_asc($a, $b)
    {
        return ($a["size"] - $b["size"]);
    }

    public function changed_cmp_asc($a, $b)
    {
        return ($a["changed"] - $b["changed"]);
    }

    public function dirname_cmp_desc($b, $a)
    {
        return $this->default_sort_nat ? strnatcasecmp($a, $b) : strcasecmp($a, $b);
    }

    public function filename_cmp_desc($b, $a)
    {
        return $this->default_sort_nat ? strnatcasecmp($a["name"], $b["name"]) : strcasecmp($a["name"], $b["name"]);
    }

    public function size_cmp_desc($b, $a)
    {
        return ($a["size"] - $b["size"]);
    }

    public function changed_cmp_desc($b, $a)
    {
        return ($a["changed"] - $b["changed"]);
    }

    //
    // Find the directory one level up
    //
    public function upperDir($dir)
    {
        // Simpler implementation of upperDir method /ErikLtz
        $arr = explode('/', $dir);
        unset($arr[count($arr) - 1]);
        return implode('/', $arr);

        /*
         * $chops = explode('/', $dir);
         * $num = count($chops);
         * $chops2 = array();
         * for($i = 0; $i < $num - 1; $i++)
         * {
         * $chops2[$i] = $chops[$i];
         * }
         * $dir2 = implode('/', $chops2);
         * return $dir2;
         */
    }

    public function upperDirSetForwardSlashes($dir)
    {
        // same as upperDir, but sets alla directory separators to forward slashes
        $arr = explode("/", $this->makeForwardSlashes($dir));
        unset($arr[count($arr) - 1]);
        return implode("/", $arr);
    }

    // Return last part in directory chain (built in basename depends on locale and having an utf8 locale may
    // return wrong characters when they really are iso8859-1)
    // [ErikLtz]
    public function baseName($dir)
    {
        // $arr = explode('/', $dir);
        $arr = explode("/", $this->makeForwardSlashes($dir));
        return $arr[count($arr) - 1];
    }

    // returns urlencoded string, but preserving forward slashes
    public function urlEncodePreserveForwardSlashes($url)
    {
        return str_replace("%2F", "/", urlencode($url));
    }

    // this function is reported in readfile() php.net page to bypass readfile() documented problems with large files
    public function readfile_chunked($filename, $retbytes = true)
    {
        $chunksize = 1 * (1024 * 1024); // how many bytes per chunk
        $buffer = '';
        $counter = 0;

        $handle = fopen($filename, 'rb');
        if ($handle === false) {
            return false;
        }

        while (! feof($handle)) {
            $buffer = fread($handle, $chunksize);
            echo $buffer;
            @ob_flush();
            @flush();

            if ($retbytes) {
                $counter += strlen($buffer);
            }
        }

        $status = fclose($handle);

        if ($retbytes && $status) {
            return $counter; // return num. bytes delivered like readfile() does.
        }

        return $status;
    }

    // UTF-8 encoding and decoding is set as a backend parameter
    public function chosen_encoding($in_string)
    {
        if ($this->encode_to_utf8) {
            return utf8_encode($in_string);
        } else {
            return $in_string;
        }
    }

    public function chosen_decoding($in_string)
    {
        if ($this->encode_to_utf8) {
            return utf8_decode($in_string);
        } else {
            return $in_string;
        }
    }

    public function restoreArchiveFilename($filename)
    {
        return preg_replace("/\s\(" . Text::_('archived') . "\s\d{4}\-\d{2}\-\d{2}\s\d{2}\.\d{2}\.\d{2}\)/", "", $filename);
    }

    public function makeForwardSlashes($url)
    {
        return str_replace("\\", "/", $url);
    }

    public function maskAbsPath($url)
    {
        return str_replace($this->default_absolute_path, "JSROOT", $url);
    }

    public function unmaskAbsPath($url)
    {
        return str_replace("JSROOT", $this->default_absolute_path, $url);
    }

    // this function is used to encrypt the absolute path when transferring it to upload.php as post
    // NOTE: THE CORRESPONDING DECRYPT FUNCTION IS IN UPLOAD.PHP
    //
    public function encrypt($string, $key)
    {
        $result = '';

        for ($i = 0; $i < strlen($string); $i++) {

            $char = substr($string, $i, 1);
            $keychar = substr($key, ($i % strlen($key)) - 1, 1);
            $char = chr(ord($char) + ord($keychar));

            $result .= $char;
        }

        return base64_encode($result);
    }

    // function based on CroppedThumbnail() by seifer at loveletslive dot com and olaso on class by satanas147 at gmail dot com (php.net on imagecopyresampled)
    public function makeThumbnail($imgfile, $imgfile_ext, $dir, $thumbnail_width, $thumbnail_height)
    {

        // check if at least GD version 1.8 is installed, otherwise do not create a thumbnail (return 0)
        if ($this->DEBUG_enabled) {
            // var_dump(gd_info()); // DEBUG option

            echo "DEFINED ? " . defined(GD_MAJOR_VERSION) . "<br />";

            echo "<br />MAJ_VER = [" . (@defined('GD_MAJOR_VERSION') ? GD_MAJOR_VERSION : 0) . "]<br />";
            echo "MIN_VER = [" . (@defined('GD_MINOR_VERSION') ? GD_MINOR_VERSION : 0) . "]<br /><br />";
        }
        if (! @defined('GD_MAJOR_VERSION') || GD_MAJOR_VERSION < 2 || (GD_MAJOR_VERSION == 1 && GD_MINOR_VERSION < 8)) {
            return 0;
        }

        // if size is 0 return 0 (it means do not make a thumbnail)
        if (! $this->thumbsize || ! strcmp($this->baseName($dir), "JS_THUMBS")) {
            // remove all existing thumbnails if the current thumbsize is zero
            $this->remove_thumbnail_files($dir);
            return 0;
        }

        // also return 0 if thumbs folder cannot be created
        if (! is_dir($dir . '/' . "JS_THUMBS") && ! ($rc = @mkdir($dir . '/' . "JS_THUMBS"))) {
            if ($this->DEBUG_enabled) {
                echo "<br />[" . $dir . '/' . "JS_THUMBS] cannot be created<br /><br />";
            }
            return 0;
        }

        // and also if extension is not available - update this part when new extensions are introdced
        $available_extensions = array(
            "JPG",
            "JPEG",
            "GIF",
            "PNG",
            "WEBP"
        );

        if (! in_array(strtoupper($imgfile_ext), $available_extensions)) {
            return 0;
        }

        // if thumbnail file exists, do some checks before returning 1 (it means use current thumbnail)
        if (file_exists($dir . '/' . "JS_THUMBS" . '/' . $imgfile)) {
            // check if thumbnail is newer than file and the image size is the same as the requested size (in this case return 1 as we don't need to make a new thumb)
            list($curthumbwidth, $curthumbheight) = getimagesize($dir . '/' . "JS_THUMBS" . '/' . $imgfile);
            if ((filemtime($dir . '/' . "JS_THUMBS" . '/' . $imgfile) >= filemtime($dir . '/' . $imgfile)) && $thumbnail_width == $curthumbwidth && $thumbnail_height == $curthumbheight) {
                return 1;
            }
        }

        // getting the image dimensions
        list($width_orig, $height_orig) = getimagesize($dir . '/' . $imgfile);

        // switch based on image type
        switch (strtoupper($imgfile_ext)) {
            case "JPEG":
            case "JPG":
                $image_resource = imagecreatefromjpeg($dir . '/' . $imgfile);
                break;

            case "GIF":
                $image_resource = imagecreatefromgif($dir . '/' . $imgfile);
                break;

            case "PNG":
                $image_resource = imagecreatefrompng($dir . '/' . $imgfile);
                break;
            case "WEBP":
                $image_resource = imagecreatefromwebp($dir . '/' . $imgfile);
                break;
        }
        $ratio_orig = $width_orig / $height_orig;

        if ($thumbnail_width / $thumbnail_height > $ratio_orig) {
            $new_height = $thumbnail_width / $ratio_orig;
            $new_width = $thumbnail_width;
        } else {
            $new_width = $thumbnail_height * $ratio_orig;
            $new_height = $thumbnail_height;
        }

        $x_mid = $new_width / 2; // horizontal middle
        $y_mid = $new_height / 2; // vertical middle

        $process = imagecreatetruecolor(round($new_width), round($new_height));

        imagecopyresampled($process, $image_resource, 0, 0, 0, 0, (int)$new_width, (int)$new_height, (int)$width_orig, (int)$height_orig);
        $thumb = imagecreatetruecolor($thumbnail_width, $thumbnail_height);
        imagecopyresampled($thumb, $process, 0, 0, (int)($x_mid - ($thumbnail_width / 2)), (int)($y_mid - ($thumbnail_height / 2)), (int)$thumbnail_width, (int)$thumbnail_height, (int)$thumbnail_width, (int)$thumbnail_height);

        imagejpeg($thumb, $dir . '/' . "JS_THUMBS" . '/' . $imgfile);

        return 1;
    }

    public function remove_thumbnail_files($dir)
    {
        if (is_dir($dir . '/' . "JS_THUMBS")) {
            if ($dh = opendir($dir . '/' . "JS_THUMBS")) {
                while (($file = readdir($dh)) !== false) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }
                    @unlink($dir . '/' . "JS_THUMBS" . '/' . $file);
                }
                closedir($dh);
            }
            @rmdir($dir . '/' . "JS_THUMBS");
        }
    }
} // end of plugin class extension

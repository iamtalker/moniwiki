<?php
// Copyright 2013-2015 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Since: 2013-07-04
// Modified: 2015-04-30
// Name: XE 1.7 User plugin
// Description: XE 1.7 user plugin
// URL: MoniWiki:XeUserPlugin
// Version: $Revision: 1.5 $
// License: GPL
//
// Param: xe_root_dir='/home/path_to_the_root_of_installed_xe/'; # default xe root path
// Usage: set $user_class = 'xe17'; in the config.php
//

class User_xe17 extends WikiUser {

    function xe_context_init($xe) {
        //
        // simplified XE context init method to speed up
        //

        // set context variables in $GLOBALS (to use in display handler)
        $xe->context = &$GLOBALS['__Context__'];
        $xe->context->_COOKIE = $_COOKIE;

        $xe->loadDBInfo();

        // set session handler
        if (Context::isInstalled() && $this->db_info->use_db_session == 'Y') {
            $oSessionModel = getModel('session');
            $oSessionController = getController('session');
            session_set_save_handler(
                    array(&$oSessionController, 'open'),
                    array(&$oSessionController, 'close'),
                    array(&$oSessionModel, 'read'),
                    array(&$oSessionController, 'write'),
                    array(&$oSessionController, 'destroy'),
                    array(&$oSessionController, 'gc')
           );
        }
    }

    function User_xe17($id = '') {
        global $DBInfo;

        $this->css = isset($_COOKIE['MONI_CSS']) ? $_COOKIE['MONI_CSS'] : '';
        $this->theme = isset($_COOKIE['MONI_THEME']) ? $_COOKIE['MONI_THEME'] : '';
        $this->bookmark = isset($_COOKIE['MONI_BOOKMARK']) ? $_COOKIE['MONI_BOOKMARK'] : '';
        $this->trail = isset($_COOKIE['MONI_TRAIL']) ? _stripslashes($_COOKIE['MONI_TRAIL']) : '';
        $this->tz_offset = isset($_COOKIE['MONI_TZ']) ?_stripslashes($_COOKIE['MONI_TZ']) : '';
        $this->nick = isset($_COOKIE['MONI_NICK']) ?_stripslashes($_COOKIE['MONI_NICK']) : '';
        if ($this->tz_offset == '') $this->tz_offset = date('Z');

        $cookie_id = '';
        // get the current Cookie vals
        if (isset($_COOKIE['MONI_ID'])) {
            $this->ticket = substr($_COOKIE['MONI_ID'], 0, 32);
            $cookie_id = urldecode(substr($_COOKIE['MONI_ID'], 33));
        }

        // is it a valid user ?
        $udb = new UserDB($DBInfo);

        $update = false;
        if (!empty($cookie_id)) {
            $tmp = $udb->getUser($cookie_id);

            // not found
            if ($tmp->id == 'Anonymous') {
                $update = true;
                $cookie_id = '';
            } else {
                // check ticket
                $ticket = getTicket($tmp->id, $_SERVER['REMOTE_ADDR']);
                if ($this->ticket != $ticket) {
                    // not a valid user
                    $this->ticket = '';
                    $this->setID('Anonymous');
                    $update = true;
                    $cookie_id = '';
                } else {
                    // OK good user
                    $this->setID($cookie_id);
                    $id = $cookie_id;
                }
            }
        } else {
            // empty cookie
            $update = true;
        }

        $sessid = session_name(); // PHPSESSID
        // set the session_id() using saved cookie
        if (isset($_COOKIE[$sessid])) {
            session_id($_COOKIE[$sessid]);
        }

        // do not use cookies for varnish cache server
        ini_set("session.use_cookies", 0);
        session_cache_limiter('');
        session_start();

        // set xe_root_dir config option
        $xe_root_dir = !empty($DBInfo->xe_root_dir) ?
                $DBInfo->xe_root_dir : dirname(__FILE__).'/../../../xe';
        // default xe_root_dir is 'xe' subdirectory of the parent dir of the moniwiki

        if ($update && !empty($_SESSION['is_logged'])) {
            define('__XE__', true);

            require_once($xe_root_dir."/config/config.inc.php");

            $context = &Context::getInstance();
            $this->xe_context_init($context); // simplified init context method
            // $context->init(); // slow slow

            $oMemberModel = &getModel('member');
            $oMemberController = &getController('member');

            $oMemberController->setSessionInfo();
            $member = new memberModel();
            $info = $member->getLoggedInfo();

            $id = $info->user_id;

            $this->setID($id);
            $udb = new UserDB($DBInfo);
            $tmp = $udb->getUser($id);

            // not a registered user ?
            if ($tmp->id == 'Anonymous' || $update || empty($tmp->info['nick'])) {
                $this->setID($id);
                $this->tz_offset = $tz_offset;
                $this->info['nick'] = $info->nick_name;
                $this->info['tz_offset'] = $tz_offset;
            }
        } else {
            // not logged in
            if (empty($_SESSION['is_logged'])) {
                if (!empty($cookie_id))
                    header($this->unsetCookie());
                $this->setID('Anonymous');
                $id = 'Anonymous';
            }
        }

        if ($update || !empty($id) and $id != 'Anonymous') {
            if ($cookie_id != $id)
                header($this->setCookie());

            if ($update || !$udb->_exists($id)) {
                // automatically save/register user
                $dummy = $udb->saveUser($this);
            }
        }
    }
}

// vim:et:sts=4:sw=4:

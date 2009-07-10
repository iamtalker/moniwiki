<?php
#
# nFORGE plugin by semtlnori
#
# based on needtologin security plugin.
#
# $Id$

class Security_nforge extends Security {
  var $DB;

  function Security_nforge($DB='') {
    $this->DB=$DB;
  }

  function help($formatter) {
    return $formatter->macro_repl('UserPreferences');
  }

# $options[page]: pagename
# $options[id]: user id

  function writable($options="") {
    return $this->DB->_isWritable($options['page']);
  }

  function may_edit($action,&$options) {
    $public_pages=array('WikiSandBox');
    if (!$options['page']) return 0; # XXX
    if (in_array($options['page'],$public_pages)) return 1;
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      $options['help']='help';
      return 0;
    }
    return 1;
  }

  function may_blog($action,&$options) {
    if (!$options['page']) return 0; # XXX
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      $options['help']='help';
      return 0;
    }
    return 1;
  }

  function may_uploadfile($action,&$options) {
    if (!$options['page']) return 0;
    if ($options['id']=='Anonymous') {
      $options['err']=sprintf(_("You are not allowed to '%s' on this page"),$action);
      $options['err'].="\n"._("Please Login or make your ID on this Wiki ;)");
      $options['help']='help';
      return 0;
    }
    return 1;
  }

  function is_allowed($action="read",&$options) {
    $method='may_'.$action;
    if (method_exists($this, $method)) {
      if (!$this->$method($action,$options)) {
        header('Location: /account/login.php?return_to='.$_SERVER['SCRIPT_URI']);
        exit;
      }
    }
    return 1;
  }

  function is_protected($action="read",&$options) {
    $perm =& $this->DB->group->getPermission( session_get_user() );
    // check if the user is docman's admin
    if (!$perm || $perm->isError() || !$perm->isDocEditor() || !$perm->isAdmin()) {
      return 1;
    } else {
      return 0;
    }

    # password protected POST actions
    $protected_actions=array("rcs","rename", "revert", "rcspurge","chmod","backup","restore","deletefile","deletepage");
    $notprotected_actions=array("userform");
    $action=strtolower($action);

    if (in_array($action,$protected_actions)) return 1;
    if (in_array($action,$notprotected_actions)) return 0;
    if ($options['id']=='Anonymous') return 1;

    return 0;
  }

}

?>
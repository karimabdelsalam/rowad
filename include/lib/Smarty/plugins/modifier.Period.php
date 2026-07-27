<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty plugin
 *
 * Type:     modifier<br>
 * Name:     ardate<br>
 * Date:     Feb 26, 2003
 * Purpose:  convert \r\n, \r or \n to <<br>>
 * Input:<br>
 *         - contents = contents to replace
 *         - preceed_test = if true, includes preceeding break tags
 *           in replacement
 * Example:  {$text|ardate}
 * @link http://smarty.php.net/manual/en/language.modifier.nl2br.php
 *          nl2br (Smarty online manual)
 * @version  1.0
 * @author   Monte Ohrt <monte at ohrt dot com>
 * @param string
 * @return string
 */
function smarty_modifier_Period($unixtime)
{
  if(!is_numeric($unixtime)) $unixtime = strtotime($unixtime);
  $diff = time() - $unixtime;
  $days = floor($diff / 84600);
  $hours = floor($diff / 3600);
  $mints = floor($diff / 60);

  if($mints <= 1) return gettext('منذ 1 دقيقة'); elseif($mints <= 2) return gettext('منذ 2 دقيقة');
  elseif($mints <= 59) return gettext('منذ ' . $mints . ' دقيقة');
  elseif($hours <= 1) return gettext('منذ 1 ساعة');
  elseif($hours <= 2) return gettext('منذ 1 ساعات');
  elseif($hours <= 23) return gettext('منذ ' . $hours . ' ساعة');
  elseif($days <= 1) return gettext('منذ امس');
  elseif($days <= 2) return gettext('منذ 2 ايام');
  elseif($days <= 30) return gettext('منذ ' . $days . ' ايام');
  else return date('d/m/Y', $unixtime);
}

/* vim: set expandtab: */

?>
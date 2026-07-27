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
function smarty_modifier_ardate($hashtime, $fulltime = false, $unixtime = true)
{
  if(!$unixtime){
    $hashtime = strtotime($hashtime);
  }
  if(SYS_CALENDAR == 2){
    if($fulltime){
      $time = explode('-', date('h-i-A', $hashtime));
      $A = ($time[2] == 'AM') ? gettext('صباحاً') : gettext('مساءاً');
      return uCal::g2u(date('Y-m-d', $hashtime), true) . ' ' . $time[0] . ':' . $time[1] . ' ' . $A;
    } else{
      return uCal::g2u(date('Y-m-d', $hashtime), true);
    }
  }
  $hashtime = date('d-m-Y-h-i-A', $hashtime);
  $time = explode('-', $hashtime);
  $d = $time[0];
  $m = $time[1];
  if($m == '01'){
    $m = gettext("يناير");
  } elseif($m == '02'){
    $m = gettext("فبراير");
  } elseif($m == '03'){
    $m = gettext("مارس");
  } elseif($m == '04'){
    $m = gettext("ابريل");
  } elseif($m == '05'){
    $m = gettext("مايو");
  } elseif($m == '06'){
    $m = gettext("يونيو");
  } elseif($m == '07'){
    $m = gettext("يوليو");
  } elseif($m == '08'){
    $m = gettext("اغسطس");
  } elseif($m == '09'){
    $m = gettext("سبتمبر");
  } elseif($m == '10'){
    $m = gettext("اكتوبر");
  } elseif($m == '11'){
    $m = gettext("نوفمبر");
  } elseif($m == '12'){
    $m = gettext("ديسمبر");
  }
  $y = $time[2];
  if($time[5] == 'AM'){
    $A = gettext('صباحا');
  } else{
    $A = gettext('مساءا');
  }
  if($fulltime) return "$d $m $y " . $time[3] . ":" . $time[4] . " " . $A;
  else return "$d $m $y";
}

/* vim: set expandtab: */

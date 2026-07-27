<?php

/*======================================================================*\
|| #################################################################### ||
|| # Smart Aqaari System 1.0                                          # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2014-2017 Smart IO Labs Inc. All Rights Reserved.     # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # --- Smart Push Notification System IS NOT FREE SOFTWARE ---      # ||
|| # https://smartiolabs.com/product/smart-push-notification-system   # ||
|| #################################################################### ||
\*======================================================================*/

class updatecore extends updatecore_model
{

  public function __construct()
  {
    parent::__construct();
    define('OUTPUT_NO_SIDEBAR', true);
    $this->Smarty->assign('pagetitle', gettext('تحديث النظام'));
  }

  public function update()
  {
    if(empty($this->config['purchase_code'])){
      $this->showmsg(gettext('من فضلك ادخل كود الشراء في صفحة الاعدادات لاستكمال عملية التحديث .'), 0);
    }
    set_time_limit(0);
    $update = $this->config['updatecore'];
    if($update['version'] > $this->config['version']){
      if(!function_exists('copy')){
        $this->silentMessage(gettext('فشل عملية التحديث لأن دالة copy() غير مدعومة من قبل سيرفرك.'));
      }
      if(!function_exists('fopen')){
        $this->silentMessage(gettext('فشل عملية التحديث لأن دالة fopen() غير مدعومة من قبل سيرفرك'));
      }
      if(!function_exists('unlink')){
        $this->silentMessage(gettext('فشل عملية التحديث لأن دالة unlink() غير مدعومة من قبل سيرفرك'));
      }
      if(!function_exists('rmdir')){
        $this->silentMessage(gettext('فشل عملية التحديث لأن دالة rmdir() غير مدعومة من قبل سيرفرك'));
      }
      if(!function_exists('curl_init')){
        $this->silentMessage(gettext('فشل عملية التحديث لأن دالة CURL غير مدعومة من قبل سيرفرك'));
      }
      if(chmod(ROOT_DIR . '/' . UPLOAD_DIR, 0777) === false){
        $this->silentMessage(gettext('فشل عملية التحديث لأن المجلد  ' . UPLOAD_DIR . ' لا يملك تصريح الكتابة 0777'));
      }
      if(!class_exists('ZipArchive')){
        $this->silentMessage(gettext('فشل عملية التحديث لأن مكتبة ZipArchive غير مدعومة من قبل سيرفرك'));
      }
      if(@rename(ROOT_DIR . '/index.php', ROOT_DIR . '/cron/index.php') === false){
        $this->silentMessage(gettext('السيرفر لم يسمح بنقل ملفات التحديث لا يمكن الأستمرار في عملية التحديث.'), 0);
      } else {
        @rename(ROOT_DIR . '/cron/index.php', ROOT_DIR . '/index.php');
      }
      $this->silentMessage(gettext('يتم الأتصال بسيرفر التحديثات وتنزيل الملفات المطلوبة ....'), 1);
      $zipupdate = $this->curl('https://smartiolabs.com/download', 'post', array('purchase_code' => $this->config['purchase_code']));
      if($this->curl_status == 401){
        $this->silentMessage(gettext('يوجد مشكلة في الترخيص الخاص بك. من فضلك قم بالأتصال بنا') . ' <a href="https://smartiolabs.com/support" target="_blank">'.gettext('بالضغط هنا').'</a><br />' . $zipupdate);
      } elseif($this->curl_status != 200){
        $this->silentMessage(gettext('حدث خطأ اثناء الأتصال بسيرفر التحديثات. من فضلك حاول التحديث في وقت اخر.'));
      }
      $localzipfile = ROOT_DIR . '/' . UPLOAD_DIR . '/update_core.zip';
      @unlink($localzipfile);
      $handle = fopen($localzipfile, 'w');
      fwrite($handle, $zipupdate);
      fclose($handle);
      $this->silentMessage(gettext('تم تنزيل ملفات التحديث بنجاح وجاري التأكد من صلاحيتها'), 1);
      if(md5_file($localzipfile) == $update['md5_hash']){
        $this->save_setting('active', 0);
        $zip = new ZipArchive;
        $ziphandle = $zip->open($localzipfile);
        if($ziphandle === TRUE){
          $cache = ROOT_DIR . '/' . UPLOAD_DIR . '/cache/unextacrtfiles/';
          $this->delTree($cache);
          if(! file_exists($cache)) {
            mkdir($cache);
          }

          $this->storelocalfile(ROOT_DIR . '/index.html', $this->readlocalfile(__DIR__ . '/templates/maintenance_page.tpl'));

          @rename(INC_DIR . '/config.php', $cache . '/config.php');

          if(! file_exists($cache . '/config.php')){
            $zip->close();
            $this->silentMessage(gettext('حدث خطأ اثناء عملية التحديث.'));
          }

          $this->walkerDel(ROOT_DIR, ['media']);

          $this->storelocalfile(ROOT_DIR . '/index.html', $this->readlocalfile(__DIR__ . '/templates/maintenance_page.tpl'));

          $zip->extractTo(ROOT_DIR);
          $zip->close();
          @unlink($localzipfile);

          @unlink(INC_DIR . '/config.php');
          @rename($cache . '/config.php', INC_DIR . '/config.php');
          @unlink(ROOT_DIR . '/index.html');

          $this->silentMessage(gettext('مبروك التحديث تم بنجاح ونظامك يعمل الأن تحت الأصدارة ') . $update['version'], 1);
        } else{
          @unlink($localzipfile);
          $this->silentMessage(gettext('حزمة التحديثات غير صالحة ولم تطابق الشفرة الصحيحة. من فضلك حاول مرة اخرى.'));
        }
      } else{
        @unlink($localzipfile);
        $this->silentMessage(gettext('حزمة التحديثات غير صالحة ولم تطابق الشفرة الصحيحة. من فضلك حاول مرة اخرى.'));
      }
    } else{
      $this->silentMessage(gettext('تم الاتصال بسيرفر التحديثات ولا يوجد تحديثات جديدة متاحة'));
    }
  }

  private function startMoveChanges($path)
  {
    $filetree = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach($filetree as $file => $value){
      if(strpos(realpath($file), realpath($path)) === false OR realpath($path) == realpath($file) OR basename(realpath($file)) == 'config.php'){
        continue;
      }
      $movetopath = str_replace(realpath($path), ROOT_DIR, realpath($file));
      if(strpos($movetopath, '/media/')){
        continue;
      }
      @rename(realpath($file), $movetopath);
    }
  }

  private function walkerDel($dir, $except = false, $delFolder = false)
  {
    foreach(glob($dir . '/*') as $file) {
      if($except && is_dir($file) && in_array(basename($file), $except)){
        continue;
      }
      if(is_dir($file))
        $this->walkerDel($file, false, true);
      else
        unlink($file);
    }
    if($delFolder) {
      rmdir($dir);
    }
  }

  private function delTree($dir)
  {
    if(!file_exists($dir)){
      return;
    }
    $folders = array();
    $filetree = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach($filetree as $file => $value){
      if(is_dir($file)){
        $folders[] = realpath($file);
      } else {
        @unlink(realpath($file));
      }
    }
    if(!empty($folders)){
      foreach($folders as $folder){
        @rmdir($folder);
      }
    }
  }

  private function silentMessage($message, $status = 0)
  {
    echo $message."<br>\n";
    if($status == 0){
      exit;
    }
  }

  public function index()
  {
    $updatestring = $this->curl($this->updateServer);
    $update = json_decode($updatestring, true);
    if(is_array($update)){
      if($update['version'] > $this->config['version']){
        $this->saveUpdateInfo($updatestring);
        $this->Smarty->assign('update', $update['version']);
        $this->Smarty->assign('changelog', $update['changelog']);
      } else{
        $this->Smarty->assign('update', 0);
      }
      $this->Output();
    } else{
      $this->showmsg(gettext('حدث خطأ اثناء الأتصال بسيرفر التحديثات. من فضلك حاول التحديث في وقت اخر.'), 0);
    }
  }

}

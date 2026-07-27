<?php

class geolocation
{
  private static $ip;
  private static $apiurl;

  public function __construct()
  {
  }

  public static function curl($headers = false)
  {
    if(function_exists('curl_init')){
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, self::$apiurl);
      curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.6 (KHTML, like Gecko) Chrome/16.0.897.0 Safari/535.6');
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
      curl_setopt($ch, CURLOPT_TIMEOUT, 40);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      if($headers !== false){
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      } else{
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
      }
      $result = curl_exec($ch);
      curl_close($ch);
    } elseif(function_exists('file_get_contents')){
      $result = file_get_contents(self::$apiurl);
    } elseif(function_exists('fopen')){
      if($fp = fopen(self::$apiurl, 'r')){
        $result = '';
        while($line = fread($fp, 1024)){
          $result .= $line;
        }
      }
    }
    return json_decode($result);
  }

  public static function db_ip_service($apikey)
  {
    self::$apiurl = 'http://api.db-ip.com/addrinfo?addr=' . self::$ip . '&api_key=' . $apikey;
    $info = self::curl();
    if(empty($info->latitude)){
      return false;
    }
    $data['country'] = $info->country;
    $data['city'] = $info->city;
    $data['latitude'] = $info->latitude;
    $data['longitude'] = $info->longitude;
    $data['ip'] = self::$ip;
    return $data;
  }

  public static function telize_service($apikey)
  {
    self::$apiurl = 'https://telize-v1.p.mashape.com/geoip/' . self::$ip;
    $info = self::curl(array('X-Mashape-Key: ' . $apikey, 'Accept: application/json'));
    if(empty($info->latitude)){
      return false;
    }
    $data['country'] = $info->country;
    $data['city'] = $info->city;
    $data['ip'] = self::$ip;
    $data['latitude'] = $info->latitude;
    $data['longitude'] = $info->longitude;
    return $data;
  }

  public static function ip_api_service()
  {
    self::$apiurl = 'http://ip-api.com/json/' . self::$ip;
    $info = self::curl();
    if(empty($info->lat)){
      return false;
    }
    $data['country'] = $info->country;
    $data['city'] = $info->city;
    $data['ip'] = self::$ip;
    $data['latitude'] = $info->lat;
    $data['longitude'] = $info->lon;
    return $data;
  }

  public static function get_location_info($provider, $apikey)
  {
    self::$ip = $_SERVER['REMOTE_ADDR'];
    if($provider == 'db-ip.com'){
      if(empty($apikey)){
        return false;
      }
      return self::db_ip_service($apikey);
    } elseif($provider == 'telize.com'){
      if(empty($apikey)){
        return false;
      }
      return self::telize_service($apikey);
    } elseif($provider == 'ip-api.com'){
      return self::ip_api_service();
    } else{
      return false;
    }
  }

}
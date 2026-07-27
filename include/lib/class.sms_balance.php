<?php

class sms_balance
{
  private static $platformSettings;

  public function __construct()
  {
  }

  public static function connection($platformSettings)
  {
    if($platformSettings['provider'] == 'msg91'){
      $response = json_decode(sms::curl('http://control.msg91.com/api/balance.php?type=4&authkey=' . $platformSettings['api_key'], 'get'), true);
      if(sms::$curl_status == 200){
        if(empty($response['msg']) && $response == 0){
          return 'msg91: No credits';
        } elseif($response['msg'] == 207 || $response['msg'] == 201){
          return 'msg91: Auth key invalid';
        } elseif($response['msg'] == 302){
          return 'msg91: Expired user account';
        } elseif($response['msg'] == 303){
          return 'msg91: Banned user account';
        }
      }
    }
    return true;
  }

  public static function check($platformSettings)
  {
    self::$platformSettings = $platformSettings;

    switch(self::$platformSettings['provider']){
      case 'clickatell':
        return self::clickatell();
      case 'smsglobal':
        return self::smsglobal();
      case 'twilio':
        return self::twilio();
      case 'bulksms':
        return self::bulksms();
      case 'smsapi':
        return self::smsapi();
      case 'msg91':
        return self::msg91();
      case 'nexmo':
        return self::nexmo();
      case 'clicksend':
        return self::clicksend();
      case 'hdvbx':
        return self::hdvbx();
      case 'mrasel':
        return self::mrasel();
      case 'mobilysms':
        return self::mobilysms();
      case 'unifonic':
        return self::unifonic();
      case 'mobilyws':
        return self::mobilyws();
    }
  }

  private static function smsglobal()
  {
    $params = array('user' => self::$platformSettings['api_key'], 'password' => self::$platformSettings['secret_key'], 'text' => 'test', 'to' => str_replace('+', '', self::$platformSettings['sender']), 'from' => str_replace('+', '', self::$platformSettings['sender']), 'action' => 'sendsms',);
    $apiurl = 'https://api.smsglobal.com/http-api.php?' . http_build_query($params);
    $response = sms::curl($apiurl, 'get');
    preg_match_all('/ERROR: ([0-9]+)/', $response, $matches);
    if(sms::$curl_status == 201 || sms::$curl_status == 202 || strpos($response, 'OK: 0;')){
      return true;
    } elseif(!empty($matches[1][0])){
      $code = $matches[1][0];
      if($code == 402){
        return false;
      } elseif($code == 88){
        return 0;
      } elseif($code == 10001){
        return false;
      } else{
        return false;
      }
    }
  }

  private static function clickatell()
  {
    $apiurl = 'https://platform.clickatell.com/public-client/balance';
    $response = json_decode(sms::curl($apiurl, 'get', false, array('Authorization: ' . self::$platformSettings['api_key'])), true);
    if(isset($response['balance'])){
      return $response['balance'];
    } else{
      return false;
    }
  }

  private static function msg91()
  {
    $params = array('authkey' => self::$platformSettings['api_key'], 'type' => 4);
    $apiurl = 'http://control.msg91.com/api/balance.php?' . http_build_query($params);
    $response = sms::curl($apiurl, 'get');
    if(sms::$curl_status == 200){
      return $response;
    } else{
      return false;
    }
  }

  private static function bulksms()
  {
    $header = array('Authorization: Basic ' . base64_encode(self::$platformSettings['api_key'] . ':' . self::$platformSettings['secret_key']));
    $apiurl = 'https://api.bulksms.com/v1/profile';
    $response = json_decode(sms::curl($apiurl, 'get', false, $header), true);
    if(isset($response['credits']['balance'])){
      return $response['credits']['balance'];
    } else{
      return false;
    }
  }

  private static function smsapi()
  {
    $header = array('Authorization: Bearer ' . self::$platformSettings['api_key']);
    $apiurl = 'https://api.smsapi.com/user.do?\credits=1\format=json';
    $response = json_decode(sms::curl($apiurl, 'get', 0, $header), true);
    if(isset($response['points'])){
      return $response['points'];
    } else{
      return false;
    }
  }

  private static function twilio()
  {
    $apiurl = 'https://api.twilio.com/2010-04-01/Accounts/' . self::$platformSettings['api_key'];
    $auth = self::$platformSettings['api_key'] . ':' . self::$platformSettings['secret_key'];
    $response = sms::curl($apiurl, 'get', false, false, $auth);
    if(sms::$curl_status == 201 || sms::$curl_status == 200){
      return true;
    } else{
      return false;
    }
  }

  private static function nexmo()
  {
    $params = array('api_key' => self::$platformSettings['api_key'], 'api_secret' => self::$platformSettings['secret_key']);
    $apiurl = 'https://rest.nexmo.com/account/get-balance?' . http_build_query($params);
    $response = json_decode(sms::curl($apiurl, 'get'), true);
    if(isset($response['value'])){
      return $response['value'];
    } else{
      return false;
    }
  }

  private static function clicksend()
  {
    $apiurl = 'https://rest.clicksend.com/v3/account';
    $header = array('Content-Type: application/json', 'Authorization: Basic ' . base64_encode(self::$platformSettings['api_key'] . ':' . self::$platformSettings['secret_key']));
    $response = json_decode(sms::curl($apiurl, 'get', false, $header), true);
    if(isset($response['data']['balance'])){
      return $response['data']['balance'];
    } else{
      return false;
    }
  }

  private static function hdvbx()
  {
    $params = array('key' => self::$platformSettings['api_key'], 'text' => self::$message, 'number' => self::$number);
    $apiurl = 'https://app.hdvbx.com/api/sms?' . http_build_query($params);
    $response = json_decode(sms::curl($apiurl, 'get'), true);
    if(sms::$curl_status == 200){
      if($response['return'] == 'success'){
        return true;
      } elseif($response['return'] == 'error'){
        return $response['messages'];
      }
    }
    return 0;
  }

  private static function unifonic()
  {
    $params = array('AppSid' => self::$platformSettings['api_key']);
    $apiurl = 'http://api.unifonic.com/rest/Account/GetBalance';
    $header = array('Content-Type: application/x-www-form-urlencoded');
    $response = json_decode(self::curl($apiurl, 'post', http_build_query($params), $header), true);
    if(self::$curl_status == 200 && isset($response['data']['MessageID'])){
      return true;
    }
    return 0;
  }

  private static function mobilysms()
  {
    $url = 'http://www.mobilysms.net/api/getbalance.php?';
    $userAccount = urlencode(self::$platformSettings['api_key']);
    $passAccount = urlencode(self::$platformSettings['secret_key']);
    $url .= "username=" . $userAccount . "&password=" . $passAccount;
    $response = self::curl($url, 'get');
    if(self::$curl_status == 200){
      return $response;
    }
    return false;
  }

  private static function mobilyws()
  {
    $params = array('mobile' => self::$platformSettings['api_key'], 'password' => self::$platformSettings['secret_key']);
    $apiurl = 'https://mobily.ws/api/balance.php';
    $response = json_decode(sms::curl($apiurl, 'post', json_encode($params)), true);
    if(isset($response['Data']['MessageAr'])){
      return $response['Data']['MessageAr'];
    } else{
      return false;
    }
  }

  private static function mrasel()
  {
    $url = 'https://www.mrasel.net/api/credit.php?';
    $userAccount = urlencode(self::$platformSettings['api_key']);
    $passAccount = urlencode(self::$platformSettings['secret_key']);
    $url .= "username=" . $userAccount . "&password=" . $passAccount;
    $response = sms::curl($url);
    if($response == 'Error= #007'){
      return false;
    } else{
      return $response;
    }
  }

}
<?php

class sms
{
  private static $number;
  private static $message;
  private static $platformSettings;
  public static $curl_status;

  public function __construct()
  {
  }

  public static function curl($url, $method = 'get', $params = 0, $headers = false, $basicAuth = false)
  {
    $ch = curl_init();
    if($method == 'post'){
      curl_setopt($ch, CURLOPT_POST, true);
      if(!empty($params)){
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
      }
    } elseif(!empty($params)){
      if(strpos($url, '?')){
        $url .= '&' . http_build_execute($params);
      } else{
        $url .= '?' . http_build_execute($params);
      }
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.6 (KHTML, like Gecko) Chrome/16.0.897.0 Safari/535.6');
    curl_setopt($ch, CURLOPT_REFERER, 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    if($headers !== false){
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if($basicAuth !== false){
      curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
      curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    $result = curl_exec($ch);
    self::$curl_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $result;
  }

  public static function connection($platformSettings)
  {
    if($platformSettings['provider'] == 'msg91'){
      $response = json_decode(self::curl('http://control.msg91.com/api/balance.php?type=4&authkey=' . $platformSettings['api_key'], 'get'), true);
      if(self::$curl_status == 200){
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

  public static function send($number, $message, $platformSettings)
  {
    if(!strpos($number, '+')){
      $number = '+' . $number;
    }
    self::$number = $number;
    self::$message = $message;
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
    $params = array('user' => self::$platformSettings['api_key'], 'password' => self::$platformSettings['secret_key'], 'text' => self::$message, 'to' => str_replace('+', '', self::$number), 'from' => str_replace('+', '', self::$platformSettings['sender']), 'action' => 'sendsms',);
    $apiurl = 'https://api.smsglobal.com/http-api.php?' . http_build_query($params);
    $response = self::curl($apiurl, 'get');
    preg_match_all('/ERROR: ([0-9]+)/', $response, $matches);
    if(self::$curl_status == 201 || self::$curl_status == 202 || strpos($response, 'OK: 0;')){
      return true;
    } elseif(!empty($matches[1][0])){
      $code = $matches[1][0];
      if($code == 402){
        return 'smsglobal: wrong username or password';
      } elseif($code == 88){
        return 'smsglobal: no credits';
      } elseif($code == 10001){
        return false;
      } else{
        return 0;
      }
    }
  }

  private static function clickatell()
  {
    $params = array('apiKey' => self::$platformSettings['api_key'], 'content' => self::$message, 'to' => self::$number, 'from' => self::$platformSettings['sender'],);
    $apiurl = 'https://platform.clickatell.com/messages/http/send?' . http_build_query($params);
    $response = json_decode(self::curl($apiurl, 'get'), true);
    if(self::$curl_status == 202){
      return true;
    } elseif(!empty($response['messages'][0]['error'])){
      return false;
    } elseif(!empty($response['error'])){
      return $response['error'];
    } else{
      return 0;
    }
  }

  private static function msg91()
  {
    $params = array('authkey' => self::$platformSettings['api_key'], 'message' => self::$message, 'mobiles' => self::$number, 'sender' => self::$platformSettings['sender'], 'route' => 4, 'country' => 0,);
    $apiurl = 'http://api.msg91.com/api/sendhttp.php?' . http_build_query($params);
    $response = json_decode(self::curl($apiurl, 'get'), true);
    if(self::$curl_status == 200){
      return true;
    } else{
      return 0;
    }
  }

  private static function bulksms()
  {
    $params = array('body' => self::$message, 'to' => self::$number, 'from' => self::$platformSettings['sender'],);
    $header = array('Authorization: Basic ' . base64_encode(self::$platformSettings['api_key'] . ':' . self::$platformSettings['secret_key']));
    $apiurl = 'https://api.bulksms.com/v1/messages';
    $response = json_decode(self::curl($apiurl, 'post', $params, $header), true);
    if(self::$curl_status == 201){
      return true;
    } elseif(self::$curl_status == 401){
      return 'bulksms: Verification of credentials failed';
    } elseif(self::$curl_status == 403){
      return 'bulksms: does not have enough credits or the user does not have enough quota';
    } else{
      return 0;
    }
  }

  private static function smsapi()
  {
    $params = array('message' => self::$message, 'to' => self::$number, 'from' => self::$platformSettings['sender'], 'format' => 'json',);
    $header = array('Authorization: Bearer ' . self::$platformSettings['api_key']);
    $apiurl = 'https://api.smsapi.com/sms.do?' . http_build_query($params);
    $response = json_decode(self::curl($apiurl, 'get', 0, $header), true);
    if(self::$curl_status == 200 && empty($response['error'])){
      return true;
    } elseif(!empty($response['error']) && in_array($response['error'], array('13'))){
      return false;
    } elseif(!empty($response['error']) && in_array($response['error'], array('14', '101', '102', '103', '1130', '1140', '2010', '2030', '2031', '2060', '2061', '2062', '2110', '2111', '2112'))){
      return $response['error'];
    } else{
      return 0;
    }
  }

  private static function twilio()
  {
    if(!strpos(self::$platformSettings['sender'], '+')){
      self::$platformSettings['sender'] = '+' . self::$platformSettings['sender'];
    }
    $apiurl = 'https://api.twilio.com/2010-04-01/Accounts/' . self::$platformSettings['api_key'] . '/Messages.json';
    $params = array('Body' => self::$message, 'To' => self::$number, 'From' => self::$platformSettings['sender'],);
    $auth = self::$platformSettings['api_key'] . ':' . self::$platformSettings['secret_key'];
    $response = json_decode(self::curl($apiurl, 'post', $params, false, $auth), true);
    if(self::$curl_status == 201){
      return true;
    } elseif(!empty($response['error_message']) && in_array($response['error_code'], array('30001', '30002', '30010'))){
      return false;
    } elseif(!empty($response['error_message']) && in_array($response['error_code'], array('30005', '30004'))){
      return $response['error_message'];
    } else{
      return 0;
    }
  }

  private static function nexmo()
  {
    $params = array('api_key' => self::$platformSettings['api_key'], 'api_secret' => self::$platformSettings['secret_key'], 'text' => self::$message, 'to' => self::$number, 'from' => self::$platformSettings['sender'], 'type' => 'unicode',);
    $apiurl = 'https://rest.nexmo.com/sms/json?' . http_build_query($params);
    $response = json_decode(self::curl($apiurl, 'post'), true);
    if(self::$curl_status == 200){
      if($response['messages'][0]['status'] == 0){
        return true;
      } elseif(in_array($response['messages'][0]['status'], array('29', '7', '6', '34'))){
        return false;
      } elseif(in_array($response['messages'][0]['status'], array('2', '4', '5', '8', '9', '11', '15', '19'))){
        return $response['messages'][0]['error-text'];
      }
    }
    return 0;
  }

  private static function clicksend()
  {
    $params = array('messages' => array(0 => array('source' => 'php', 'body' => self::$message, 'to' => self::$number, 'from' => self::$platformSettings['sender'],)));
    $apiurl = 'https://rest.clicksend.com/v3/sms/send';
    $header = array('Content-Type: application/json', 'Authorization: Basic ' . base64_encode(self::$platformSettings['api_key'] . ':' . self::$platformSettings['secret_key']));
    $response = json_decode(self::curl($apiurl, 'post', json_encode($params), $header), true);
    if(self::$curl_status == 200){
      if($response['data']['messages'][0]['status'] == 'SUCCESS'){
        return true;
      } elseif(!empty($response['data']['messages'][0]['status']) && in_array($response['data']['messages'][0]['status'], array('INSUFFICIENT_CREDIT', 'INVALID_SENDER_ID', 'EMPTY_MESSAGE'))){
        return $response['data']['messages'][0]['status'];
      } elseif(!empty($response['data']['messages'][0]['status']) && in_array($response['data']['messages'][0]['status'], array('INVALID_RECIPIENT'))){
        return false;
      } elseif(in_array($response['http_code'], array('401', '429', '400'))){
        return $response['response_msg'];
      } elseif(in_array($response['response_msg'], array('ACCOUNT_NOT_ACTIVATED', 'MISSING_CREDENTIALS', 'INVALID_CREDENTIALS', 'INTERNAL_ERROR', 'SOMETHING_IS_WRONG'))){
        return $response['response_msg'];
      }
    }
    return 0;
  }

  private static function unifonic()
  {
    $params = array('AppSid' => self::$platformSettings['api_key'], 'Body' => self::$message, 'Recipient' => self::$number);
    $apiurl = 'http://api.unifonic.com/rest/Messages/Send';
    $header = array('Content-Type: application/x-www-form-urlencoded');
    $response = json_decode(self::curl($apiurl, 'post', http_build_query($params), $header), true);
    if(self::$curl_status == 200 && isset($response['data']['MessageID'])){
      return true;
    }
    return 0;
  }

  private static function hdvbx()
  {
    $params = array('key' => self::$platformSettings['api_key'], 'text' => self::$message, 'number' => self::$number);
    $apiurl = 'https://app.hdvbx.com/api/sms?' . http_build_query($params);
    $response = json_decode(self::curl($apiurl, 'get'), true);
    if(self::$curl_status == 200){
      if($response['return'] == 'success'){
        return true;
      } elseif($response['return'] == 'error'){
        return $response['messages'];
      }
    }
    return 0;
  }

  private static function mobilysms()
  {
    $url = 'https://www.mobilysms.net/api/sendsms.php?';
    $sender = urlencode(self::$platformSettings['sender']);
    $userAccount = urlencode(self::$platformSettings['api_key']);
    $passAccount = urlencode(self::$platformSettings['secret_key']);
    $message = urlencode(iconv('utf-8', 'windows-1256', self::$message));
    $url .= "username=" . $userAccount . "&password=" . $passAccount . "&numbers=" . self::$number . "&sender=" . $sender . "&message=" . $message . "&unicode=e&return=full";
    $response = self::curl($url, 'get');
    if(self::$curl_status == 200 && $response == 100){
      return true;
    }
    return false;
  }

  private static function mobilyws()
  {
    $params = array('mobile' => self::$platformSettings['api_key'], 'password' => self::$platformSettings['secret_key'], 'numbers' => self::$number, 'sender' => self::$platformSettings['sender'], 'msg' => self::$message, 'lang' => 3, 'applicationType' => 68);
    $apiurl = 'https://mobily.ws/api/msgSend.php';
    $response = json_decode(sms::curl($apiurl, 'post', json_encode($params)), true);
    if(self::$curl_status == 200){
      return true;
    }
    return false;
  }

  private static function mrasel()
  {
    $url = 'https://www.mrasel.net/api/?';
    $sender = urlencode(self::$platformSettings['sender']);
    $userAccount = urlencode(self::$platformSettings['api_key']);
    $passAccount = urlencode(self::$platformSettings['secret_key']);
    $message = urlencode(iconv('utf-8', 'windows-1256', self::$message));
    $url .= "username=" . $userAccount . "&password=" . $passAccount . "&mobile=" . self::$number . "&sender=" . $sender . "&message=" . $message . "&thelang=1";
    self::curl($url, 'get');
    if(self::$curl_status == 200){
      return true;
    }
    return false;
  }

}
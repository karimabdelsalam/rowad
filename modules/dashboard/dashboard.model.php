<?php

class dashboard_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function finishTask($id)
  {
    $this->db->query("UPDATE task SET done='1' WHERE id='$id' AND tomanagerid='$_SESSION[userid]'");
  }

  public function readNotification($id)
  {
    $this->db->query("UPDATE notifications SET read_state='1' WHERE id='$id' AND userid='$_SESSION[userid]'");
  }

  public function readAllNotifications()
  {
    $this->db->query("UPDATE notifications SET read_state='1' WHERE userid='$_SESSION[userid]'");
  }

  public function deleteNote($id)
  {
    $this->db->query("DELETE FROM notes WHERE id='$id' AND userid='$_SESSION[userid]'");
  }

  public function collectStats()
  {
    if($this->config['skip_guide_widget'] == 0){
      $this->db->cache_handler(true);
    }
    $stat = array();
    $stat['admins'] = $this->db->get_var("SELECT COUNT(id) FROM user WHERE 1 " . ((!empty($_GET['fdate'])) ? " AND joindate BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['renters'] = $this->db->get_var("SELECT COUNT(id) FROM buyer WHERE 1 " . ((!empty($_GET['fdate'])) ? " AND timepost BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['owners'] = $this->db->get_var("SELECT COUNT(id) FROM owner WHERE 1 " . ((!empty($_GET['fdate'])) ? " AND timepost BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['builds'] = $this->db->get_var("SELECT COUNT(id) FROM building WHERE 1 " . ((!empty($_GET['fdate'])) ? " AND timepost BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['contracts'] = $this->db->get_var("SELECT COUNT(id) FROM rent_contracts WHERE 1 " . ((!empty($_GET['fdate'])) ? " AND startdate BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['freeSaleBuilds'] = $this->db->get_var("SELECT COUNT(id) FROM building WHERE type='sale' AND available='1' " . ((!empty($_GET['fdate'])) ? " AND timepost BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['selledBuilds'] = $this->db->get_var("SELECT COUNT(id) FROM building WHERE type='sale' AND available='0' " . ((!empty($_GET['fdate'])) ? " AND timepost BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['rentedBuilds'] = $this->db->get_var("SELECT COUNT(id) FROM building WHERE type='rent' " . ((!empty($_GET['fdate'])) ? " AND timepost BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['freeRentBuilds'] = $this->db->get_var("SELECT COUNT(id) FROM building WHERE type='rent' AND available='1' " . ((!empty($_GET['fdate'])) ? " AND timepost BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['bankBalance'] = $this->db->get_var("SELECT SUM(balance) FROM bank_accounts");
    $stat['outcome'] = $this->db->get_var("SELECT SUM(amount) FROM statement_out WHERE 1 " . ((!empty($_GET['fdate'])) ? " AND issueddate BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['income'] = $this->db->get_var("SELECT SUM(amount) FROM statement_in WHERE 1 " . ((!empty($_GET['fdate'])) ? " AND issueddate BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['officeOwners'] = $this->db->get_var("SELECT COUNT(id) FROM company_owners WHERE 1 ");
    $stat['employee'] = $this->db->get_var("SELECT COUNT(id) FROM employees WHERE 1 " . ((!empty($_GET['fdate'])) ? " AND timepost BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['transactions'] = $this->db->get_var("SELECT SUM(amount) FROM payments WHERE gone='0' " . ((!empty($_GET['fdate'])) ? " AND paydate BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['goneTransactions'] = $this->db->get_var("SELECT SUM(amount) FROM payments WHERE gone='1' " . ((!empty($_GET['fdate'])) ? " AND paydate BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    $stat['profit'] = $this->db->get_var("SELECT SUM(amount) FROM transactions WHERE typeid IN('commission','commission2','mancost') AND gone='1' " . ((!empty($_GET['fdate'])) ? " AND gone_date BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    if($this->config['skip_guide_widget'] == 0){
      $this->db->cache_handler(false);
    }

    if($this->config['skip_guide_widget'] == 0 && $stat['contracts'] > 0) {
      $this->save_setting('skip_guide_widget', 1);
    }

    $stat['filesize'] = [];
    $stat['filesize']['quota'] = $this->config['updatecore']['plan']['filesize_quota'];
    $stat['filesize']['remain'] = $stat['filesize']['quota'] - $this->config['total_file_size'];
    if($stat['filesize']['remain'] < (($stat['filesize']['quota']/100)*20)) {
      $stat['filesize']['color'] = 'danger';
    } elseif($stat['filesize']['remain'] < (($stat['filesize']['quota']/100)*50)) {
      $stat['filesize']['color'] = 'default';
    } else {
      $stat['filesize']['color'] = 'success';
    }

    $stat['unitcount'] = [];
    $stat['unitcount']['quota'] = $this->config['updatecore']['plan']['units_quota'];
    $stat['unitcount']['remain'] = $stat['unitcount']['quota'] - $this->config['total_units'];
    if($stat['unitcount']['remain'] < (($stat['unitcount']['quota']/100)*20)) {
      $stat['unitcount']['color'] = 'danger';
    } elseif($stat['unitcount']['remain'] < (($stat['unitcount']['quota']/100)*50)) {
      $stat['unitcount']['color'] = 'default';
    } else {
      $stat['unitcount']['color'] = 'success';
    }

    return $stat;
  }

  public function ownerBuilds()
  {
    $ownerBuildIDs = [];
    $ownerBuilds = $this->db->get_results("SELECT id FROM building WHERE JSON_SEARCH(owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL " . ((!empty($_GET['fdate'])) ? " AND timepost BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    if($ownerBuilds){
      foreach($ownerBuilds as $ownerBuild){
        $ownerBuildIDs[] = $ownerBuild['id'];
      }
      return implode(',', $ownerBuildIDs);
    }
    return '0';
  }

  public function collectOwnerStats()
  {
    $this->db->cache_handler(true);

    $stat = array();

    $stat['builds'] = 0;
    $ownerBuildIDs = [];
    $ownerBuilds = $this->db->get_results("SELECT id FROM building WHERE JSON_SEARCH(owner, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL " . ((!empty($_GET['fdate'])) ? " AND timepost BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    if($ownerBuilds){
      foreach($ownerBuilds as $ownerBuild){
        $ownerBuildIDs[] = $ownerBuild['id'];
      }
      $stat['builds'] = count($ownerBuildIDs);
      $ownerBuildIDs = implode(',', $ownerBuildIDs);
    }

    $stat['renters'] = 0;
    $stat['rentContracts'] = 0;
    $retnerids = [];
    $renters = $this->db->get_results("SELECT buyer FROM rent_contracts WHERE buildid IN($ownerBuildIDs) " . ((!empty($_GET['fdate'])) ? " AND rent_contracts.startdate BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    if($renters) {
      foreach($renters as $renter) {
        $renter['buyer'] = json_decode($renter['buyer'], true);
        foreach($renter['buyer']['id'] as $retnerid){
          $retnerids[] = $retnerid;
        }
        $stat['rentContracts'] ++;
      }
      $stat['renters'] = count(array_unique($retnerids));
    }

    $stat['sellClients'] = 0;
    $stat['sellContracts'] = 0;
    $sellerids = [];
    $sellers = $this->db->get_results("SELECT buyer FROM sell_contracts WHERE buildid IN($ownerBuildIDs) " . ((!empty($_GET['fdate'])) ? " AND sell_contracts.postdate BETWEEN $_GET[fdate] AND $_GET[todate]" : ''));
    if($sellers) {
      foreach($sellers as $seller) {
        $seller['buyer'] = json_decode($seller['buyer'], true);
        foreach($seller['buyer']['id'] as $sellerid){
          $sellerids[] = $sellerid;
        }
        $stat['sellContracts'] ++;
      }
      $stat['sellClients'] = count(array_unique($sellerids));
    }

    $stat['freeSaleBuilds'] = $this->db->get_var("SELECT COUNT(id) FROM building WHERE id IN($ownerBuildIDs) AND type='sale' AND available='1' " . ((!empty($_GET['fdate'])) ? " AND (timepost BETWEEN $_GET[fdate] AND $_GET[todate])" : ''));
    $stat['selledBuilds'] = $this->db->get_var("SELECT COUNT(id) FROM building WHERE id IN($ownerBuildIDs) AND type='sale' AND available='0' " . ((!empty($_GET['fdate'])) ? " AND (timepost BETWEEN $_GET[fdate] AND $_GET[todate])" : ''));
    $stat['rentedBuilds'] = $this->db->get_var("SELECT COUNT(id) FROM building WHERE id IN($ownerBuildIDs) AND type='rent' " . ((!empty($_GET['fdate'])) ? " AND (timepost BETWEEN $_GET[fdate] AND $_GET[todate])" : ''));
    $stat['freeRentBuilds'] = $this->db->get_var("SELECT COUNT(id) FROM building WHERE id IN($ownerBuildIDs) AND type='rent' AND available='1' " . ((!empty($_GET['fdate'])) ? " AND (timepost BETWEEN $_GET[fdate] AND $_GET[todate])" : ''));

    $stat['debitTrans'] = $this->db->get_var("SELECT SUM(amount) FROM transactions WHERE ownerid='$_SESSION[ownerid]' AND direction='debit' AND gone='0' " . ((!empty($_GET['fdate'])) ? " AND (paydate BETWEEN $_GET[fdate] AND $_GET[todate])" : ''));
    $stat['goneDebitTrans'] = $this->db->get_var("SELECT SUM(amount) FROM transactions WHERE ownerid='$_SESSION[ownerid]' AND direction='debit' AND gone='1' " . ((!empty($_GET['fdate'])) ? " AND (paydate BETWEEN $_GET[fdate] AND $_GET[todate])" : ''));

    $stat['creditTrans'] = $this->db->get_var("SELECT SUM(amount) FROM transactions WHERE ownerid='$_SESSION[ownerid]' AND direction='credit' AND gone='0' " . ((!empty($_GET['fdate'])) ? " AND (paydate BETWEEN $_GET[fdate] AND $_GET[todate])" : ''));
    $stat['goneCreditTrans'] = $this->db->get_var("SELECT SUM(amount) FROM transactions WHERE ownerid='$_SESSION[ownerid]' AND direction='credit' AND gone='1' " . ((!empty($_GET['fdate'])) ? " AND (paydate BETWEEN $_GET[fdate] AND $_GET[todate])" : ''));

    $this->db->cache_handler(false);

    return $stat;
  }

  public function collectClientStats()
  {
    $this->db->cache_handler(true);

    $stat = array();

    $stat['rentContracts'] = $this->db->get_var("SELECT COUNT(id) FROM rent_contracts WHERE JSON_SEARCH(buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL " . ((!empty($_GET['fdate'])) ? " AND (startdate BETWEEN $_GET[fdate] AND $_GET[todate])" : ''));
    $stat['sellContracts'] = $this->db->get_var("SELECT COUNT(id) FROM sell_contracts WHERE JSON_SEARCH(buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL " . ((!empty($_GET['fdate'])) ? " AND (postdate BETWEEN $_GET[fdate] AND $_GET[todate])" : ''));

    $stat['loans'] = $this->db->get_var("SELECT SUM(payments.amount) FROM payments
    LEFT JOIN rent_contracts ON(rent_contracts.id=payments.contractid AND payments.module='rent' AND JSON_SEARCH(rent_contracts.buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL)
    LEFT JOIN sell_contracts ON(sell_contracts.id=payments.contractid AND payments.module='sell' AND JSON_SEARCH(sell_contracts.buyer, 'one', $_SESSION[ownerid], null, '$.id[*]') IS NOT NULL)
    WHERE (rent_contracts.buyer IS NOT NULL OR sell_contracts.buyer IS NOT NULL) AND payments.gone='0' AND payments.paydate < NOW()");

    $this->db->cache_handler(false);

    return $stat;
  }

  public function graphicStats()
  {
    $stats = array();
    if(! DEBUG_MODE){
      $this->db->cache_handler(true);
    }

    if(empty($_GET['year'])){
      $year = date('Y');
      $_GET['year'] = date('Y');
    } else{
      $year = $_GET['year'];
    }

    $results = $this->db->get_results("SELECT SUM(amount) AS totalcommission,MONTH(gone_date) AS m FROM transactions WHERE typeid IN('commission','commission2','mancost') AND `gone`='1' AND YEAR(gone_date)=$year GROUP BY MONTH(gone_date) ORDER BY MONTH(gone_date) ASC");
    if($results){
      foreach($results as $result){
        $stats['profits'][$result['m']] = $result['totalcommission'];
      }
    }

    $results = $this->db->get_results("SELECT SUM(amount) AS amount,MONTH(issueddate) AS m FROM `statement_in` WHERE YEAR(issueddate)=$year GROUP BY MONTH(issueddate) ORDER BY MONTH(issueddate) ASC");
    if($results){
      foreach($results as $result){
        $stats['income'][$result['m']] = $result['amount'];
      }
    }

    $results = $this->db->get_results("SELECT SUM(amount) AS amount,MONTH(issueddate) AS m FROM `statement_out` WHERE YEAR(issueddate)=$year GROUP BY MONTH(issueddate) ORDER BY MONTH(issueddate) ASC");
    if($results){
      foreach($results as $result){
        $stats['outcome'][$result['m']] = $result['amount'];
      }
    }

    $this->db->cache_handler(false);
    return $stats;
  }

  public function ownerGraphicStats()
  {
    $stats = array();
    if(! DEBUG_MODE){
      $this->db->cache_handler(true);
    }

    if(empty($_GET['year'])){
      $year = date('Y');
      $_GET['year'] = date('Y');
    } else{
      $year = $_GET['year'];
    }

    $results = $this->db->get_results("SELECT SUM(amount) AS amount,MONTH(gone_date) AS m FROM transactions WHERE direction='debit' AND `gone`='1' AND YEAR(gone_date)=$year GROUP BY MONTH(gone_date) ORDER BY MONTH(gone_date) ASC");
    if($results){
      foreach($results as $result){
        $stats['profits'][$result['m']] = $result['amount'];
      }
    }

    $results = $this->db->get_results("SELECT SUM(amount) AS amount,MONTH(gone_date) AS m FROM `transactions` WHERE direction='debit' AND YEAR(gone_date)=$year GROUP BY MONTH(gone_date) ORDER BY MONTH(gone_date) ASC");
    if($results){
      foreach($results as $result){
        $stats['income'][$result['m']] = $result['amount'];
      }
    }

    $results = $this->db->get_results("SELECT SUM(amount) AS amount,MONTH(gone_date) AS m FROM `transactions` WHERE direction='credit' AND YEAR(gone_date)=$year GROUP BY MONTH(gone_date) ORDER BY MONTH(gone_date) ASC");
    if($results){
      foreach($results as $result){
        $stats['outcome'][$result['m']] = $result['amount'];
      }
    }

    $this->db->cache_handler(false);
    return $stats;
  }

  public function notesList($where)
  {
    $results = $this->db->get_results("SELECT notes.*,user.name,user.picture FROM notes
    INNER JOIN user ON(user.id=notes.userid)
    WHERE $where ORDER BY notes.id DESC LIMIT 0,20");
    return $results;
  }

  public function notifsList($limit, $unReadOnly = false)
  {
    $where = '';
    if($_SESSION['userinfo']['group'] == 1) {
      $where .= "notifications.userid='0' OR notifications.userid='$_SESSION[userid]'";
    } else {
      $where .= "notifications.userid='$_SESSION[userid]'";
    }
    if($unReadOnly) {
      $where .= " AND read_state='0'";
    }
    return $this->db->get_results("SELECT * FROM notifications WHERE $where ORDER BY id DESC LIMIT 0, $limit");
  }

  public function secureLogout()
  {
    return $this->db->query("DELETE FROM login_activity WHERE userid='$_SESSION[userid]'");
  }

  public function loginActivities()
  {
    include(LIB_DIR.'/class.browser.detect.php');

    $results = [];
    $activities = $this->db->get_results("SELECT * FROM login_activity WHERE userid='$_SESSION[userid]' ORDER BY id DESC LIMIT 0,10");
    if($activities){
      $browser = new BrowserDetection();
      foreach($activities as $activity){
        $activity['agent'] = json_decode($activity['agent'], true);
        $browser->setUserAgent($activity['agent']['agent']);
        $results[] = [
          'browser' => $browser->getName().' '.$browser->getVersion(),
          'login_time' => $activity['login_time'],
          'ip' => $activity['ip'],
          'type' => ($browser->isMobile()) ? 'mobile' : 'desktop',
          'country' => $activity['agent']['location']['country'],
          'city' => $activity['agent']['location']['city'],
          'lat' => $activity['agent']['location']['lat'],
          'lng' => $activity['agent']['location']['lon'],
          'os' => $browser->getPlatform().' '.$browser->getPlatformVersion(),
        ];
      }
    }
    return $results;
  }

  public function saveNote($data)
  {
    return $this->db->insert('notes', $data);
  }

  public function TaskList($limit = 20)
  {
    $results = $this->db->get_results("SELECT task.*,user.name,user.picture FROM task
    INNER JOIN user ON(user.id=task.managerid)
    WHERE task.tomanagerid='$_SESSION[userid]' AND task.done='0' ORDER BY task.id DESC LIMIT 0,$limit");
    return $results;
  }

}

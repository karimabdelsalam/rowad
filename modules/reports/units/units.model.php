<?php

class units_model extends core
{

  public function __construct()
  {
    parent::__construct();
  }

  public function listrecord($where = '')
  {
    $limit = 'LIMIT 50';
    $where1 = $where2 = $where3 = $where4 = $where5 = [];
    if(!empty($where)){
      $where1 = 'AND rent_contracts.postdate ' . $where;
      $where2 = 'AND payments.paydate ' . $where;
      $where3 = 'AND buyer.timepost ' . $where;
      $where4 = 'AND owner.timepost ' . $where;
      $where5 = 'AND building.timepost ' . $where;
    }
    $buyermodule = $this->auto_load('buyer');
    $ownermodule = $this->auto_load('owner');
    $lastyear = $_GET['year'];
    $start = $_GET['fdate'];
    $end = $_GET['todate'];

    $results['top_builds_profit'] = $this->db->get_results("SELECT building.title,building_locs.location,SUM(payments.amount) AS totalmoney FROM payments"
      ." INNER JOIN building ON(building.id=payments.buildid)"
      ." LEFT JOIN building_locs ON(building_locs.id=building.locid)"
      ." WHERE 1 $where2 AND payments.`statoutid`=0 GROUP BY payments.buildid ORDER BY totalmoney DESC $limit");

    $results['top_delay_persons'] = $this->db->get_results("SELECT building.title,building2.title AS title2,rent_contracts.buyer AS renter,sell_contracts.buyer AS buyers,payments.module,SUM(payments.amount) AS totalmoney FROM payments"
      ." LEFT JOIN rent_contracts ON(rent_contracts.id=payments.contractid AND payments.module='rent')"
      ." LEFT JOIN sell_contracts ON(sell_contracts.id=payments.contractid AND payments.module='sell')"
      ." LEFT JOIN building ON(building.id=sell_contracts.buildid AND payments.module='sell')"
      ." LEFT JOIN building AS building2 ON(building2.id=rent_contracts.buildid AND payments.module='rent')"
      ." WHERE 1 $where2 AND payments.`gone`=1 AND DATEDIFF(payments.`gone_date`,payments.`paydate`) > 20 GROUP BY payments.contractid ORDER BY totalmoney DESC $limit");

    $results['top_credit_persons'] = $this->db->get_results("SELECT building.title,building2.title AS title2,rent_contracts.buyer AS renter,sell_contracts.buyer AS buyers,payments.module,SUM(payments.amount) AS totalmoney FROM payments"
      ." LEFT JOIN rent_contracts ON(rent_contracts.id=payments.contractid AND payments.module='rent')"
      ." LEFT JOIN sell_contracts ON(sell_contracts.id=payments.contractid AND payments.module='sell')"
      ." LEFT JOIN building ON(building.id=sell_contracts.buildid AND payments.module='sell')"
      ." LEFT JOIN building AS building2 ON(building2.id=rent_contracts.buildid AND payments.module='rent')"
      ." WHERE 1 $where2 AND payments.`gone`=0 AND payments.`paydate` < NOW() GROUP BY payments.contractid ORDER BY totalmoney DESC $limit");

    $results['top_builds_expenses'] = $this->db->get_results("SELECT building.title,building_locs.location,SUM(payments.amount) AS totalmoney FROM payments"
      ." INNER JOIN building ON(building.id=payments.buildid)"
      ." LEFT JOIN building_locs ON(building_locs.id=building.locid)"
      ." WHERE 1 $where2 AND payments.statoutid>0 GROUP BY payments.buildid ORDER BY totalmoney DESC $limit");

    $results['top_builds_rented'] = $this->db->get_results("SELECT building.title,building_locs.location,COUNT(rent_contracts.id) AS totalcount FROM rent_contracts"
      ." INNER JOIN building ON(building.id=rent_contracts.buildid)"
      ." LEFT JOIN building_locs ON(building_locs.id=building.locid)"
      ." WHERE 1 $where1 GROUP BY rent_contracts.buildid ORDER BY totalcount DESC $limit");

    $top_renters = $this->db->get_results("SELECT buyer.id,COUNT(rent_contracts.id) AS totalcount FROM buyer"
      ." LEFT JOIN rent_contracts ON(rent_contracts.buyer LIKE CONCAT('%[\"', buyer.id, '\"]%'))"
      ." WHERE 1 $where3 GROUP BY buyer.id ORDER BY totalcount DESC $limit");
    $results['top_renters'] = [];
    if($top_renters){
      foreach($top_renters as $top_renter){
        $buyer = $buyermodule->readrecord($top_renter['id']);
        $results['top_renters'][] = ['id' => $buyer['id'], 'name' => $buyer['fullname'], 'totalcount' => $top_renter['totalcount'] ];
      }
    }

    $top_owners = $this->db->get_results("SELECT owner.id,COUNT(building.id) AS totalcount FROM owner"
      ." LEFT JOIN building ON(building.owner LIKE CONCAT('%[\"', owner.id, '\"]%'))"
      ." WHERE 1 $where4 GROUP BY owner.id ORDER BY totalcount DESC $limit");
    $results['top_owners'] = [];
    if($top_owners){
      foreach($top_owners as $top_owner){
        $owner = $ownermodule->readrecord($top_owner['id']);
        $results['top_owners'][] = ['id' => $owner['id'], 'name' => $owner['fullname'], 'totalcount' => $top_owner['totalcount'] ];
      }
    }

    $results['top_districts_inrent'] = $this->db->get_results("SELECT district.name,COUNT(building.id) AS totalcount FROM building"
      ." INNER JOIN rent_contracts ON(rent_contracts.buildid=building.id)"
      ." INNER JOIN district ON(district.id=building.district)"
      ." WHERE 1 $where5 GROUP BY building.district ORDER BY totalcount DESC $limit");

    $results['top_cities_inrent'] = $this->db->get_results("SELECT city.name,COUNT(building.id) AS totalcount FROM building"
      ." INNER JOIN rent_contracts ON(rent_contracts.buildid=building.id)"
      ." INNER JOIN city ON(city.id=building.citytid)"
      ." WHERE 1 $where5 GROUP BY building.citytid ORDER BY totalcount DESC $limit");

    $results['builds_timeline'] = [];
    $builds_timelines = $this->db->get_results("SELECT COUNT(id) AS counter,MONTH(timepost) AS m FROM `building` WHERE YEAR(timepost)=$lastyear GROUP BY MONTH(timepost) ORDER BY MONTH(timepost) ASC");
    if($builds_timelines){
      foreach($builds_timelines as $builds_timeline){
        $results['builds_timeline'][$builds_timeline['m']] = $builds_timeline['counter'];
      }
    }

    $results['contracts_timeline'] = [];
    $contracts_timelines = $this->db->get_results("SELECT COUNT(id) AS counter,MONTH(postdate) AS m FROM `rent_contracts` WHERE YEAR(postdate)=$lastyear GROUP BY MONTH(postdate) ORDER BY MONTH(postdate) ASC");
    if($contracts_timelines){
      foreach($contracts_timelines as $contracts_timeline){
        $results['contracts_timeline'][$contracts_timeline['m']] = $contracts_timeline['counter'];
      }
    }

    $builds = $this->db->get_results("SELECT building.id,building.title,building_locs.location FROM building LEFT JOIN building_locs ON(building_locs.id=building.locid)");
    $results['builds_busy_rate'] = [];
    if($builds){
      $builds_busy_rate = [];
      foreach($builds as $build){
        if(empty($build['location'])) $build['location'] = '';
        $busyMonths = $allMonths = 0;
        $contracts = $this->db->get_results("SELECT id,startdate,enddate FROM rent_contracts WHERE startdate>='$start' AND enddate<='$end' AND buildid='$build[id]'");
        if($contracts){
          foreach($contracts as $contract){
            if(strtotime($contract['enddate']) > strtotime($end)){
              $contract['enddate'] = $end;
            }
            $busyMonths += $this->monthsInBetween($contract['startdate'], $contract['enddate']);
          }
          $allMonths = $this->monthsInBetween($_GET['fdate'], $_GET['todate']);
          $builds_busy_rate[] = [ 'busyRate' => round(($busyMonths/$allMonths)*100, 1), 'title' => $build['title'], 'location' => $build['location'] ];
        } else {
          $builds_busy_rate[] = [ 'busyRate' => 0, 'title' => $build['title'], 'location' => $build['location'] ];
        }

        $rates = array_column($builds_busy_rate, 'busyRate');
        array_multisort($rates, SORT_DESC, $builds_busy_rate);
      }
      $results['builds_busy_rate'] = $builds_busy_rate;
    }

    return $results;
  }

  private function monthsInBetween($date1, $date2){
    $ts1 = strtotime($date1);
    $ts2 = strtotime($date2);

    $year1 = date('Y', $ts1);
    $year2 = date('Y', $ts2);

    $month1 = date('m', $ts1);
    $month2 = date('m', $ts2);

    return (($year2 - $year1) * 12) + ($month2 - $month1);
  }

}

<?php defined('DIRECT_ACCESS_CHECK') or die('DIRECT ACCESS NOT ALLOWED');
/**
 * Copyright (c) 2013 EIRL DEVAUX J. - Medialoha.
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the GNU Public License v3.0
 * which accompanies this distribution, and is available at
 * http://www.gnu.org/licenses/gpl.html
 *
 * Contributors:
 *     EIRL DEVAUX J. - Medialoha - initial API and implementation
 */


class IssueHelper {
	
	
	/**
	 * 
	 * @param IssueState $state
	 * @param IssuePriority $priority
	 * @return string
	 */
	public static function getHiliteBgColorClass($state, $priority) {			
 		if ($state->isTesting()) {
 			return 'issue-hilitecol-testing';
 			
 		} else if ($state->isArchived()) {
 			return 'issue-hilitecol-archived';
 			
 		} else if ($state->isOpen()) {
 			switch ($priority->getId()) {
				case IssuePriority::LOW :
					return 'issue-hilitecol-low';
				case IssuePriority::NORMAL :
					return 'issue-hilitecol-normal';
				case IssuePriority::CRITICAL :
					return 'issue-hilitecol-critical';
 			}
 		}
 		
 		return 'issue-hilitecol-closed';
	}
	
	public static function formatCause($cause, $hiliteColorClass="") {
		$arr = explode(':', Helper::shrinkString($cause, 175));
		$arr[0] = '<b class="'.$hiliteColorClass.'" >'.$arr[0].'</b>';

		return implode(': ', $arr);
	}
	
	public static function printIssueLink($id, $cause) {
		$causelen = strlen($cause);

		echo '<a href="javascript:showIssueReportsTbl(\''.$id.'\')" ',($causelen>175?'title="'.$cause.'" rel="tooltip" data-toggle="tooltip" data-placement="top" data-html="true"':''),' >',
					self::formatCause($cause),'</a>'; 
	}
	
	/**
	 * 
	 * @return Issue list filter options array
	 */
	public static function getFilterOptsArr() {
		$opts = null;
		
		if (isset($_SESSION['issueListOpts'])) {
			$opts = $_SESSION['issueListOpts'];
			if(count($opts) < 12){
				$opts = null;
			}
		} 
				
		if (!isset($opts)) {
			$opts = array('app'=>-1, 
										'mId'=>-1,
										'showArchived'=>false,
										'state'=>-1,
										'priority'=>-1,
										'order'=>0,
										'limit'=>10,
										'start'=>0,
										'versionName'=>-1,
										'androidVersion'=>-1,
										'deviceName'=>'',
										'issueCause'=>''); 
		}
		
		
		// update opts from get params
		foreach ($opts as $k=>$v) {					
			if (array_key_exists($k, $_GET)) {
				$new_value = $_GET[$k];
				
				if ($k=='state') {
					if ($new_value!='')
						$opts[$k] = $new_value;
					
				} else {
					if (is_numeric($new_value)) {
						if ($k=='showArchived') {
							$opts[$k] = intval($new_value)==1?true:false;
								
						} else { $opts[$k] = $new_value; }
					} else if (is_string($new_value)) {
						$opts[$k] = $new_value;
					}
				}
			}
		}
				
		// update session
		$_SESSION['issueListOpts'] = $opts;
		
		return $opts;
	}
	
	/**
	 * Fetch issues from DB
	 * 
	 * @param Array of filtering options
	 */
	public static function fetchIssues($filterOpts) {
		$orderBy = self::getFetchIssuesOrderBy($filterOpts);
		$limit = self::getFetchIssuesLimit($filterOpts);
		
		return DBHelper::fetchIssues(self::buildIssuesWhereClause($filterOpts), $orderBy, null, $limit, null);
	}
	
	public static function fetchIssuesTable($filterOpts) {
		$orderBy = self::getFetchIssuesOrderBy($filterOpts);
		$limit = self::getFetchIssuesLimit($filterOpts);
		
		return DBHelper::fetchIssuesTable(self::buildIssuesWhereClause($filterOpts), $orderBy, null, $limit, null);
	}
	
	public static function getFetchIssuesOrderBy($filterOpts) {
		switch ($filterOpts['order']) {
			case 1 : $orderBy = ISSUE_STATE.' DESC, '.ISSUE_DATETIME.' DESC'; 
				break;
			case 2 : $orderBy = ISSUE_DATETIME.' DESC';
				break;
			case 3 : $orderBy = ISSUE_DATETIME.' ASC';
				break;
				
			default : $orderBy = ISSUE_STATE.' ASC, '.ISSUE_DATETIME.' DESC';   
		}
		
		return $orderBy;
	}
	
	public static function getFetchIssuesLimit($filterOpts) {
		return  $filterOpts['start'].', '.$filterOpts['limit'];
	}
	
	/**
	 * Build where clause from filter options
	 */
	public static function buildIssuesWhereClause($filterOpts) {
		// build where clauses
		$where = array();
		
		if ($filterOpts['app']>0)
			$where[] = TBL_ISSUES.'.'.ISSUE_APP_ID.'='.$filterOpts['app'];
		
		if ($filterOpts['mId']>0)
			$where[] = TBL_ISSUES.'.'.ISSUE_MILESTONE_ID.'='.$filterOpts['mId'];
		
		if (!$filterOpts['showArchived']) {
			$where[] = TBL_ISSUES.'.'.ISSUE_STATE.'<>'.ISSUE_STATE_ARCHIVED;
		}
		
		if (isset($filterOpts['state']) && $filterOpts['state']!='' && $filterOpts['state']!='-1') {
			$where[] = TBL_ISSUES.'.'.ISSUE_STATE.' IN ('.$filterOpts['state'].')';
		}
		
		if (isset($filterOpts['priority']) && $filterOpts['priority']>=0) {
			$where[] = TBL_ISSUES.'.'.ISSUE_PRIORITY.'='.$filterOpts['priority'];
		}
		
		if (isset($filterOpts['versionName']) && $filterOpts['versionName']!='-1') {
			$where[] = '('.TBL_REPORTS.'.'.REPORT_VERSION_NAME.' LIKE "'.$filterOpts['versionName'].'")';
		}
		
		if (isset($filterOpts['androidVersion']) && $filterOpts['androidVersion']!='-1') {
			$where[] = '('.TBL_REPORTS.'.'.REPORT_ANDROID_VERSION.' LIKE "'.$filterOpts['androidVersion'].'")';
		}
		
		if (!empty($filterOpts['deviceName']) && $filterOpts['deviceName']!='-1') {
			$where[] = '('.TBL_REPORTS.'.'.REPORT_PRODUCT.' LIKE "%'.$filterOpts['deviceName'].'%" OR '.TBL_REPORTS.'.'.REPORT_BRAND.' LIKE "%'.$filterOpts['deviceName'].'%" OR '.TBL_REPORTS.'.'.REPORT_PHONE_MODEL.' LIKE "%'.$filterOpts['deviceName'].'%")';
		}
		
		if (!empty($filterOpts['issueCause']) && $filterOpts['issueCause']!='-1') {
			$where[] = TBL_ISSUES.'.'.ISSUE_CAUSE.' LIKE "%'.$filterOpts['issueCause'].'%"';
		}
		
		Debug::logd($where);
		
		
		return count($where)==0?'':implode(' AND ', $where);
	}
}
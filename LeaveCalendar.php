<?php
class LeaveCalendar extends AppModel 
{
	public $useTable = 'leave_details';	
	public function returnLeave($year,$leaveType='',$superVisior='',$project='',$leaveStatus='')
	{
		$start_Date = $year.'-01-01';
		$end_Date = $year.'-12-31';
		App::import('model','UserFlexibleHoliday');
		$obUser = new UserFlexibleHoliday();
		$obUser->recursive = 0;
		$flexibleHoliday = $obUser->find('all', array('fields'=>array('user_id', 'holiday_type_id', 'holiday_date'),
		'conditions'=>array('UserFlexibleHoliday.year'=>date('Y'))));
		foreach($flexibleHoliday as $fh){
			if($fh['UserFlexibleHoliday']['holiday_type_id']==Configure::read('FIRST_FLEXIBLE_HOLIDAY_ID')){
				$userFH[$fh['UserFlexibleHoliday']['user_id']]['ffh'] =  $fh['UserFlexibleHoliday']['holiday_date'];
			}elseif($fh['UserFlexibleHoliday']['holiday_type_id']==Configure::read('SECOND_FLEXIBLE_HOLIDAY_ID')){
				$userFH[$fh['UserFlexibleHoliday']['user_id']]['sfh'] =  $fh['UserFlexibleHoliday']['holiday_date'];
			}/* $allFH[] = $fh['User']['ffh'];
			$allFH[] = $fh['User']['sfh']; */
		}
		// in_array($date, $userFH)
		//pr($userFH); die;
		App::import('model','Holiday');
		$obHoliday = new Holiday();
		
		$option['conditions'] = array('Holiday.year' => $year);
		$holidays = $obHoliday->find('all', $option);

		$holidayAll = array();
		foreach($holidays as $holiday){
			$holidayAll[$holiday['Country']['id']][] = $holiday['Holiday']['holidaydate'];
		}
		
		$con1 =  '';
		$leaveArr = array();
		if($leaveType!='' && $leaveStatus=='A')
		{
			$con1 = ' and ld.leave_type_id='.$leaveType;
		}else if($leaveStatus!='A' && $leaveType!=''){
			$con1 = 'ld.leave_type_id="'.$leaveType.'" and';
		}
		if($leaveStatus=='A')//For default view
		{
			$qry = "SELECT ld.*, u.country FROM leave_details ld INNER JOIN users u ON ld.user_id=u.id WHERE ld.status='A' $con1 and (DATE_FORMAT(ld.start_date,'%Y')='".$year."' || DATE_FORMAT(ld.end_date,'%Y')='".$year."') AND u.status=1";
		}else //for list view
		{
			$qry = "SELECT ld.*, u.country FROM leave_details ld INNER JOIN users u ON ld.user_id=u.id WHERE $con1 (ld.start_date>='".$start_Date."' AND ld.end_date<='".$end_Date."') AND u.status=1";
		}
		$k = 0;
		$leaveDetail = $this->query($qry);
		foreach($leaveDetail as $key => $value){
			$numDays = $value['ld']['leave_days'];
			for($i=0; $i<$numDays; $i++)
			{
				$date = date('Y-m-d',strtotime(date("Y-m-d", strtotime($value['ld']['start_date'])) . " +$i day"));
				if(date('D', strtotime($date)) == 'Sun' 
				|| date('D', strtotime($date)) == 'Sat' 
				|| (count( $holidayAll) && in_array($date, $holidayAll[$value['u']['country']])))				
				{
					$numDays++;
				}
				elseif(isset($userFH[$value['ld']['user_id']]) && count($userFH[$value['ld']['user_id']])>0 && in_array($date, $userFH[$value['ld']['user_id']])){
					//pr($userFH[$value['ld']['user_id']]);
					$flipHD = array_flip($userFH[$value['ld']['user_id']]);					
					$leaveArr['Users'][$k]= $value['ld']['user_id'];
					$leaveArr['LeaveType'][$k]= $flipHD[$date];
					$leaveArr['leaveDate'][$k] = $date;
					$leaveArr['LeaveDays'][$k] = $value['ld']['leave_days'];
					$leaveArr['StartSpan'][$k] = $value['ld']['start_span'];
					$leaveArr['EndSpan'][$k] = $value['ld']['end_span'];
					$leaveArr['StartDate'][$k] = $value['ld']['start_date'];
					$leaveArr['EndDate'][$k] = $value['ld']['end_date'];
					$leaveArr['LeaveId'][$k] = 0;
					$numDays++;
					$k++;
				}
				else
				{
					$leaveArr['Users'][$k]= $value['ld']['user_id'];
					$leaveArr['LeaveType'][$k]= $value['ld']['leave_type_id'];
					$leaveArr['LeaveStatus'][$k]= $value['ld']['status'];
					$leaveArr['leaveDate'][$k] = $date;
					$leaveArr['LeaveId'][$k] = $value['ld']['id'];
					$leaveArr['LeaveDays'][$k] = $value['ld']['leave_days'];
					$leaveArr['StartSpan'][$k] = $value['ld']['start_span'];
					$leaveArr['StartDate'][$k] = $value['ld']['start_date'];
					$leaveArr['EndDate'][$k] = $value['ld']['end_date'];
					$leaveArr['EndSpan'][$k] = $value['ld']['end_span'];
					$k++;
				}
			}
		}
		return $leaveArr;
	}
}
?>

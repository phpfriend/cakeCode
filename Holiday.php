<?php

class Holiday extends AppModel 
{
	var $name = 'Holiday';
	var $date = '';
	public $useTable = 'holidays';
	var $validate=array('title'=>array('rule'=> 'notEmpty','required' => true,'message' => 'Title is required to fill'),
						'country'=>array('rule'=> 'notEmpty','required' => true,'message' => 'Please select Country')
						);
	
	 var $belongsTo = array(
				'Country' => array(
				'className' => 'Country',
				'foreignKey' => 'country'
               )
            ); 

	public function checkDuplicate($date,$country){//To check duplicate holiday before entering in table
		$dateToCheck = $date['year']."-".$date['month']."-".$date['day'];
		$result = $this->find('all', array('conditions' => array('Holiday.holidaydate' => $dateToCheck, "AND"=>array('Holiday.country'=>$country))));
		$count = count($result);
		return $count;
	}
	
	function checkHolidayDate($holidaydate)
	{
		// pr($holidaydate); die;
		// return checkdate($holidaydate['month'], $holidaydate['day'], $holidaydate['year']);
	}
}
?>

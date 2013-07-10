<?php
App::uses('AppController', 'Controller');
class HolidaysController extends AppController 
{
	var $name = 'Holiday';  
	var $uses = array('Holiday','Country','User','UserFlexibleHoliday');
    var $helpers = array('Html', 'Form', 'Session');
	public function beforeFilter() {
        parent::beforeFilter();
    }
	public function index()
	{	
		 
			if(isset($this->params['named'])&& count($this->params['named'])>0)
			{
				$filterByYear = $this->params['named']['year'];
				$recordPerPage = $this->params['named']['recordperpage'];
			}
			else
			{
				$filterByYear = date('Y');
				$recordPerPage = Configure::read('PER_PAGE_RECORDS');
			}
				$this->set('filterByYear', $filterByYear);
				$this->set('recordperpage', $recordPerPage);	
				$this->paginate = array(
				'fields' => array('Holiday.id','Holiday.holidaydate', 'Holiday.title','Holiday.flexible_holiday','Holiday.country','Holiday.comment','Country.id','Country.name'),
				'limit' => $recordPerPage,
				'order' => array(
				'Holiday.holidaydate' => 'desc'
			)
			); 
		   $result = $this->paginate('Holiday', array('Holiday.year' => $filterByYear));
		   if(count($result)>0)
		   {
			$this->set('holidays', $result);
		   }
		   else 
		   {
		    $this->set('holidays', array());
			// $msg = 'No record found for selected year';
			// $this->Session->setFlash($msg, 'flash_success');
		   }
	}

	public function addEditHoliday($id= null)//To add/edit holiday in the holiday table.
	{
		$err = false;
		$User = $this->Session->read('Auth.User.User.id');
		$country_id = $this->Country->find('list',array('fields' => array('id', 'name')));
		$this->set('country_id', $this->Country->find('list',array('fields' => array('id', 'name'))));
		if ($this->request->is('post') || $this->request->is('put'))
		{
			$this->request->data['Holiday']['year'] = $this->request->data['Holiday']['holidaydate']['year'];
			$this->request->data['Holiday']['modified_by'] = $User;
			if($id)
			{
				$this->request->data['Holiday']['id'] = $id;
				//$msg = 'The holiday has been updated successfully.';
				$msg = Configure::read('SUCCESSFULLY_UPDATED');
			}
			else
			{
				if($this->Holiday->checkDuplicate($this->request->data['Holiday']['holidaydate'],$this->request->data['Holiday']['country'])){
					$msg = 'Holiday for this date has already been added, Please select new date';
					$this->Session->setFlash($msg, 'flash_failure');
					$this->redirect(array('action' => 'index'));
				}else{
					$this->request->data['Holiday']['created_by'] = $User;					
					//$msg = 'The holiday has been added successfully.';
					$msg = Configure::read('SUCCESSFULLY_SAVED');
				}
			}	
			if(!checkdate($this->request->data['Holiday']['holidaydate']['month'], $this->request->data['Holiday']['holidaydate']['day'], $this->request->data['Holiday']['holidaydate']['year']))
			{
				//$this->Session->setFlash('Please select a valid date', 'flash_failure');
				$this->Session->setFlash(Configure::read('VALID_DATE_CHECK'), 'flash_failure');
				$err = true;
			}
			if(!$err )
			{
				// pr($this->request->data); die;
				if ($this->Holiday->save($this->request->data)) 
				{
					$this->Session->setFlash($msg, 'flash_success');
					$this->redirect(array('action' => 'index'));
				} 
				else 
				{
					if($this->Holiday->validationErrors)
					{
						//$this->Session->setFlash('Please fill in the required fields.', 'flash_failure');
						$this->Session->setFlash(Configure::read('REQUIRED_FIELD_MESSAGE'), 'flash_failure');
					}
					else
					{
						$this->Session->setFlash(Configure::read('QUERY_ERROR'), 'flash_failure');
					}
				}
			}			
			
		}
		elseif($id)
		{
			$this->data = $this->Holiday->read(Null, $id);
		}
		if($id)
		{
			$this->set('buttonLabel', 'Update Holiday');
		}
		else
		{
			$this->set('buttonLabel', 'Add Holiday');
		}
	}

	function deleteHoliday($id = null) //To delete holiday from the holiday table.
	{ 
		$this->Holiday->delete($id);
		//$this->Session->setFlash('The holiday with id: '.$id.' has been deleted.', 'flash_success');
		$this->Session->setFlash(Configure::read('SUCCESSFULLY_DELETED'), 'flash_success');
		$this->redirect(array('action'=>'index'));
	}
	
	public function applyFlexiHoliday($id = null)
	{
		if(isset($this->request->params['named']['user_id']) && $this->request->params['named']['user_id'] > 0)
		{
			$user_id = $this->request->params['named']['user_id'];
		}
		else
		{			
			$user_id = $this->Session->read('Auth.User.User.id');
		}
		
		$userDetail = $this->User->find('first', array('conditions'=>array('User.id'=>$user_id), 'recursive'=>0));
		$this->set('userDetail', $userDetail);
		
		//check if it's month of january and less than 10 days or if it's new user check that joining date not more than 10 days 
		//pr($userDetail);
		$dateofJoining = explode('-',$userDetail['User']['doj']);
		$dojAfter10days = mktime(0, 0, 0, $dateofJoining[1]  , $dateofJoining[2]+10, $dateofJoining[0]);
		
		if((date('m')===1 && date('d')>10) && (time() > $dojAfter10days)){
			$this->Session->setFlash('Sorry! You can\'t apply/update flexible leave now.', 'flash_success');
			$this->redirect('/users/addEditLeave');
		}elseif((time() > $dojAfter10days)){
			$this->Session->setFlash('Sorry! You can\'t apply/update flexible leave now.', 'flash_success');
			$this->redirect('/users/addEditLeave');
		}
		
		$firstflexibleLeave = $this->UserFlexibleHoliday->find('all',array('conditions'=>array('year' => date('Y'),'holiday_type_id'=>1,'user_id'=>$user_id),'fields' => array( 'UserFlexibleHoliday.id','holiday_date')));
		
		$secondflexibleLeave = $this->UserFlexibleHoliday->find('all',array('conditions'=>array('year' => date('Y'),'holiday_type_id'=>2,'user_id'=>$user_id),'fields' => array( 'UserFlexibleHoliday.id','holiday_date')));
		
		$currentUser = $this->Session->read('Auth.User.User.id');
		$edit = false;
		
		//pr($firstflexibleLeave);pr($secondflexibleLeave);
		$this->set('edit', $edit);
		
		
		
		if ($this->request->is('post') || $this->request->is('put'))
		{
			if((isset($this->request->data['UserFlexibleHoliday']['sfh']) && empty($this->request->data['UserFlexibleHoliday']['sfh'])) || (isset($this->request->data['UserFlexibleHoliday']['ffh']) && empty($this->request->data['UserFlexibleHoliday']['ffh'])))
			{
				$this->Session->setFlash('Kindly select the valid date for flexible holiday.', 'flash_failure');
				$this->redirect(array('action' => 'applyFlexiHoliday'));
			}
			$this->UserFlexibleHoliday->set($this->data);
			
			if($this->UserFlexibleHoliday->validates())
			{
				//save the data
				
				$data['UserFlexibleHoliday']['user_id'] = $user_id;
				if(isset($this->data['UserFlexibleHoliday']['ffh']) && $this->data['UserFlexibleHoliday']['ffh']!='')
				{
					//saving ffh
					if(isset($firstflexibleLeave[0]['UserFlexibleHoliday']['id']) && $firstflexibleLeave[0]['UserFlexibleHoliday']['id']!=''){
						$data['UserFlexibleHoliday']['id'] = $firstflexibleLeave[0]['UserFlexibleHoliday']['id'];
					}	
					$data['UserFlexibleHoliday']['holiday_type_id'] = 1;
					$data['UserFlexibleHoliday']['holiday_date'] = $this->data['UserFlexibleHoliday']['ffh'];
					$data['UserFlexibleHoliday']['year'] = date('Y');
					$this->UserFlexibleHoliday->save($data,false);
				}
				//saving sfh
				if(isset($this->data['UserFlexibleHoliday']['sfh']) && $this->data['UserFlexibleHoliday']['sfh']!=''){
					if(isset($secondflexibleLeave[0]['UserFlexibleHoliday']['id']) && $secondflexibleLeave[0]['UserFlexibleHoliday']['id']!=''){
						$data['UserFlexibleHoliday']['id'] = $secondflexibleLeave[0]['UserFlexibleHoliday']['id'];
					}	
					$data['UserFlexibleHoliday']['holiday_type_id'] = 2;
					$data['UserFlexibleHoliday']['holiday_date'] = $this->data['UserFlexibleHoliday']['sfh'];
					$data['UserFlexibleHoliday']['year'] = date('Y');
					$this->UserFlexibleHoliday->create();
					$this->UserFlexibleHoliday->save($data,false);
				}
				$this->Session->setFlash('Your leaves saved successfully.', 'flash_success');
				$this->redirect('/users/addEditLeave');
			
			}else{
				$this->Session->setFlash('Sorry! Both flexible holiday must be different.', 'flash_failure');
			}
			
			
			
		}else{
			if(isset($firstflexibleLeave[0]['UserFlexibleHoliday'])){
				$this->request->data['UserFlexibleHoliday']['ffh'] = $firstflexibleLeave[0]['UserFlexibleHoliday']['holiday_date'];
			}
			if(isset($secondflexibleLeave[0]['UserFlexibleHoliday'])){
				$this->request->data['UserFlexibleHoliday']['sfh'] = $secondflexibleLeave[0]['UserFlexibleHoliday']['holiday_date'];
			}
		
		}
		
		$option['conditions'] = array('Holiday.flexible_holiday' => 1, 'Holiday.year' => date('Y'));
		$option['order'] = array('Holiday.holidaydate');
		$option['fields'] = array( 'holidaydate','title');
		$flexibleHolidays = $this->Holiday->find('list', $option);
		$ffhList  = array();
		$sfhList  = array(); 
		// pr($flexibleHolidays); die;$dateofJoining[0]
		foreach($flexibleHolidays as $FH => $holiday)
		{
			if($FH > $userDetail['User']['doj']){
				if($FH >= date('Y').'-07-01'){
					$sfhList[$FH] = AppController::displayDate($FH).' - '.$holiday; 
				}
				//check if user joined in current year after 30th june , he is not eleigible for fist half leave
				if($userDetail['User']['doj'] < date('Y').'-07-01' ){
					$ffhList[$FH] = AppController::displayDate($FH).' - '.$holiday;
				}
			}
		}
		
		$this->set('ffhList',$ffhList);
		$this->set('sfhList',$sfhList); 
	}


	
	
}

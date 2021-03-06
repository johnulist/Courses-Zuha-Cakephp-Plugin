<?php
App::uses('CoursesAppController', 'Courses.Controller');
/**
 * Grades Controller
 *
 * @property Grade $Grade
 */
class CourseGradesController extends CoursesAppController {

	public $name = 'CourseGrades';
	public $uses = array('Courses.CourseGrade', 'Courses.CourseGradeAnswer');

/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->CourseGrade->recursive = 0;
		$this->set('grades', $this->paginate());
	}

/**
 * view method
 *
 * @param string $id
 * @return void
 */
	public function view($id = null) {
		$this->CourseGrade->id = $id;
		if (!$this->CourseGrade->exists()) {
			throw new NotFoundException(__('Invalid lesson'));
		}
		$this->set('grades', $this->CourseGrade->read(null, $id));
	}

/**
 * add method
 * 
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {
			$this->CourseGrade->create();
			if ($this->CourseGrade->save($this->request->data)) {
				$this->Session->setFlash(__('The Grade has been created'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The Grade could not be created. Please, try again.'));
			}
		}
		$parentCourses = $this->CourseGrade->Course->find('list');
		$this->set(compact('parentCourses'));
	}

/**
 * edit method
 *
 * @param string $id
 * @return void
 */
	public function edit($id = null) {
		$this->CourseGrade->id = $id;
		if (!$this->CourseGrade->exists()) {
			throw new NotFoundException(__('Invalid Grade'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->CourseGrade->save($this->request->data)) {
				$this->Session->setFlash(__('The Grade has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The Grade could not be saved. Please, try again.'));
			}
		} else {
			$this->request->data = $this->CourseGrade->read(null, $id);
		}
		$parentCourses = $this->CourseGrade->Course->find('list');
		$this->set(compact('parentCourses'));
	}
	
	public function edit_answers() {
		if ($this->request->is('post') || $this->request->is('put')) {
			if($this->request->data['GradeDetail']['creator_id'] !== $this->userId || !isset($this->request->data['GradeDetail']['creator_id'])) {
				$this->Session->setFlash(__('Only the teacher can change grades'));
				$this->redirect($this->referer());
			}
			
			if ($this->CourseGradeAnswer->saveMany($this->request->data['GradeAnswers'])) {
				//debug($this->request->data);exit;
				try{
					$this->CourseGrade->updateGradeFromAnswers($this->request->data['CourseGrade']['id'], $this->request->data['CourseGrade']['student_id'], $this->request->data['GradeDetail']['course_id']);
					$message = __('The Grades have been saved');
				} catch (Exception $e) {
					$message = $e->getMessage();
				}
				$this->Session->setFlash($message);
				$this->redirect($this->referer());
			} else {
				$this->Session->setFlash(__('The Grade could not be saved. Please, try again.'));
			}
		} else {
			$this->Session->setFlash(__('The Grade could not be saved. Please, try again.'));
			$this->redirect($this->referer());
		}
	}

/**
 * delete method
 *
 * @param string $id
 * @return void
 */
	public function delete($id = null) {
		if (!$this->request->is('post')) {
			throw new MethodNotAllowedException();
		}
		$this->CourseGrade->id = $id;
		if (!$this->CourseGrade->exists()) {
			throw new NotFoundException(__('Invalid Grade'));
		}
		if ($this->CourseGrade->delete()) {
			$this->Session->setFlash(__('Grade deleted'));
			$this->redirect(array('action' => 'index'));
		}
		$this->Session->setFlash(__('Grade was not deleted'));
		$this->redirect(array('action' => 'index'));
	}
	
	
/**
 * 
 * function for grading assignments with forms attached to them
 * 
 */
	

	
	public function grade() {
		try{
			if(!$this->request->is('put') || !isset($this->request->data['CourseGradeDetail']['id'])) {
				throw new MethodNotAllowedException('Error - No Grading Details');
			}
			//Check to see if a grade already exists
			if($this->CourseGrade->find('first', array(
				'conditions' => array(
					'course_grade_detail_id' => $this->request->data['CourseGradeDetail']['id'],
					'student_id' => $this->userId,
				)
			))) { throw new Exception('You already took this test.'); }
			
			$gradeid = $this->CourseGrade->grade($this->request->data['CourseGradeDetail']['id'], $this->request->data['Answer']);
			
			$this->redirect(array('plugin' => 'courses', 'controller' => 'course_grades', 'action' => 'show_grade', $gradeid));
				
		}catch (Exception $e){
			$this->Session->setFlash($e->getMessage());
			if($this->request->is('ajax')) {
				$this->response->statusCode(500);
				$this->layout = null;
			}else{
				$this->redirect($this->referer());
			}	
		}

	}
	
	/**
	 * Custom Function for Answers Plugin.
	 * Allows inserting Grading Options into form
	 */
	 
	public function answerkey($answerid = null) {
		if(!CakePlugin::loaded('Answers')) {
			throw new MethodNotAllowedException('Answers Plugin is not installed');
		}
		
		$this->loadModel('Answers.Answer');
		
		if($this->request->isPost() && !empty($this->request->data)) {
			$data = array();	
			$data['Answer']['id'] = $this->request->data['Answer']['id'];
			unset($this->request->data['Answer']['id']);
			foreach ($this->request->data['Answer'] as $inputid => $correct) {
				$data['Answer']['data'][$inputid]['answer'] = $correct;
				$data['Answer']['data'][$inputid]['points'] = $this->request->data['Points'][$inputid];
			}
			$data['Answer']['data'] = json_encode($data['Answer']['data']);
			$this->Answer->save($data, true, array('data'));
			$this->Session->setFlash('Answer Key Saved');
			$this->redirect('/answers/answers/index');
		}
		
		if(!empty($answerid)) {
			$answer = $this->Answer->findById($answerid);
			if(!empty($answer['Answer']['data'])) {
				$answers = $answer['Answer']['data'];
			}
			
		}else{
			$this->Session->setFlash('No Form Id');
			$this->redirect($this->referer());
		}
		
		$this->set('answers_json', $answers);
	 	$this->set('answer', $answer);
	}
	

	public function show_grade ($gradeid = false) {
		try{
			if(!$gradeid) {
				throw new MethodNotAllowedException('No Grade Id Given');
			}
				
			$this->request->data = $this->CourseGrade->find('first', array(
				'conditions' => array(
					'CourseGrade.id' => $gradeid,
					'CourseGrade.model' => 'Task'
					),
				'contain' => array(
					'User', 
					'GradeAnswers',
					'GradeDetail'
				)));
				
		}catch(Exception $e) {
			$this->Session->setFlash('Error: '.$e->getMessage());
			$this->redirect($this->referer());
		}

		if($this->request->data['GradeDetail']['creator_id'] == $this->userId) {
			$this->view = 'show_grade_teacher';
		}
	
	}
	
	public function changeAnswer($gradeanswerid) {
		
	}
		
	
}

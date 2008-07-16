<?php

class :ControllerController extends AppController
{
	
	public function __construct() {
		parent::__construct();
	}

	public function index() {
		$this->:objects = :Object::find_all();
	}

	public function show() {
		if (!params('uid')) redirect_to();
		$this->:object = :Object::find(params('uid'));
	}

	public function add() {
		$this->:object = new :Object();
	}

	public function create() {
		if (!params(':object')) redirect_to('add');
		$this->:object = new :Object(params(':object'));

		try {
			$this->:object->save();
			redirect_to();
		} catch (ValidationFailure $e) {
			$this->render_view('add');
		}
	}

	public function edit() {
		if (!params('uid')) redirect_to();
		$this->:object = :Object::find(params('uid'));
	}
	
	public function update() {
		if (!params(':object')) redirect_to(array('controller' => params('controller'), 'action' => 'edit', 'uid' => params('uid')));
		if (!params('uid')) redirect_to();
		$this->:object = :Object::find(params('uid'));

		try {
			$this->:object->update(params(':object'));
			redirect_to(array('action' => 'show', 'uid' => $this->:object->get_uid()));
		} catch (ValidationFailure $e) {
			$this->render_view('edit');
		}
	}
	
	public function delete() {
		if (!params('uid')) redirect_to();
		$:object = :Object::find(params('uid'));
		$:object->delete();
		redirect_to();
	}
}

?>
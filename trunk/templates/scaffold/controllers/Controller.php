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
		if (!params('id')) redirect_to();
		$this->:object = :Object::find(params('id'));
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
		if (!params('id')) redirect_to();
		$this->:object = :Object::find(params('id'));
	}
	
	public function update() {
		if (!params(':object')) redirect_to(array('controller' => params('controller'), 'action' => 'edit', 'id' => params('id')));
		if (!params('id')) redirect_to();
		$this->:object = :Object::find(params('id'));

		try {
			$this->:object->update(params(':object'));
			redirect_to(array('action' => 'show', 'id' => $this->:object->get_id()));
		} catch (ValidationFailure $e) {
			$this->render_view('edit');
		}
	}
	
	public function delete() {
		if (!params('id')) redirect_to();
		$:object = :Object::find(params('id'));
		$:object->delete();
		redirect_to();
	}
}

?>
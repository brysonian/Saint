<?php

class :ControllerController extends AppController
{
	function index() {
		$this->:objects = :Object::find_all();
	}

	function show() {
		if (!params('uid')) redirect_to();
		$this->:object = :Object::find(params('uid'));
	}

	function add() {
		$this->:object = new :Object();
	}

	function create() {
		if (!params(':object')) redirect_to('add');
		$this->:object = new :Object(params(':object'));

		try {
			$this->:object->save();
			redirect_to();
		} catch (ValidationFailure $e) {
			$this->render_view('add');
		}
	}

	function edit() {
		if (!params('uid')) redirect_to();
		$this->:object = :Object::find(params('uid'));
	}
	
	function update() {
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
	
	function delete() {
		if (!params('uid')) redirect_to();
		$:object = :Object::find(params('uid'));
		$:object->delete();
		redirect_to();
	}
}

?>
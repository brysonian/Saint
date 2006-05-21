<?php

class :ObjectController extends AppController
{
	function _index() {
		$this->render_action('list');
	}

	function _list() {
		$this->:objects = :Object::find_all();
	}

	function _show() {
		$this->:object = :Object::find(params('uid'));
	}

	function _new() {
		$this->:object = new :Object();
	}

	function _create() {
		$this->:object = new :Object(params(':object'));

		try {
			$this->:object->save();
			redirect_to();
		} catch (ValidationException $e) {
			$this->render_view('new');
		}
	}

	function _edit() {
		$this->:object = :Object::find(params('uid'));
	}
	
	function _update() {
		$this->:object = :Object::find(params('uid'));

		try {
			$this->:object->update(params(':object'));
			redirect_to(array('action' => 'show', 'uid' => $this->:object->get_uid()));
		} catch (ValidationException $e) {
			$this->render_view('edit');
		}
	}
	
	function _delete() {
		$:object = :Object::find(params('uid'));
		$:object->delete();
		redirect_to();
	}
}

?>
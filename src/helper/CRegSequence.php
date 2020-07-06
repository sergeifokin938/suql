<?php
namespace Helper;

class CRegSequence {
	public $name;
	public $set;

	function __construct($name, $set) {
		$this->name = "{:$name:}";
		$this->set = '['.implode('', $set).']';
	}

	public function valid() {
		return true;
	}
}

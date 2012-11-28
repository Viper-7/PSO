<?php
class PSO_ConnectedPool extends PSO_Pool {
	protected $pools;
	
	public function __construct() {
		trigger_error('Not yet implemented');
		$this->pools = func_get_args();
	}
}
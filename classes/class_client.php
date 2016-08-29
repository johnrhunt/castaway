<?php
class client {
	private $socket;
	public $username = '';
	public $chunkx;
	public $chunky;
	public $localx;
	public $localy;
	public $border;
	
	public function __construct($socket) {
		$this->socket = $socket;
	}
	
	public function get_socket() {
		return $this->socket;
	}
	
	public function read_socket() {
		return socket_read($this->socket, 1024);
	}
	
	public function set_account_data($username) {
		$this->username = $username;
	}
	
	public function update_pos($chunkx, $chunky, $localx, $localy) {
		$this->chunkx = $chunkx;
		$this->chunky = $chunky;
		$this->localx = $localx;
		$this->localy = $localy;
	}
}
?>

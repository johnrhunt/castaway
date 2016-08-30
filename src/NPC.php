<?php

namespace Lenton\Castaway;

class NPC
{
	private $sprite;
	private $chunkx;
	private $chunky;
	private $localx;
	private $localy;
	private $name;
	//private $hp;
	//private $damage;

	public function __construct($sprite, $chunkx, $chunky, $localx, $localy, $name) {
		$this->chunkx = $chunkx;
		$this->chunky = $chunky;
		$this->localx = $localx;
		$this->localy = $localy;
	}

	private function set_position($chunkx, $chunky, $localx, $localy) {
		$this->chunkx = $chunkx;
		$this->chunky = $chunky;
		$this->localx = $localx;
		$this->localy = $localy;
	}
}

<?php

namespace Lenton\Castaway;

class Chunk
{
	private $chunk;
	private $layers;
	private $tiles;

	public function __construct($id) {
		$this->chunk = ereg_replace("[ \t\n\r]", '', file_get_contents('chunks/'.$id.'.txt'));
		$this->layers = explode('#', $this->chunk);
		foreach($this->layers as $layer_num => $layer) {
			$this->tiles[$layer_num] = explode(',', $layer);
		}
	}

	public function get_layer_packet($layer) {
		$return = '';
		//$count = 0;
		foreach($this->tiles[$layer] as $tile) {
			$return .= $tile."\n";
			//$count++;
		}
		//echo 'Tile count: '.$count."\n";
		return $return;
	}

	public function get_tile($layer, $tile) {
		return $tiles[$layer][$tile];
	}
}
?>

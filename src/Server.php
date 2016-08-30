<?php

namespace Lenton\Castaway;

use Lenton\Castaway\Chunk;
use Lenton\Castaway\Client;
use Lenton\Castaway\NPC;

class Server
{
	private $address;
	private $port;
	private $max;
	private $socket;
	private $clients = array();
	private $chunks = array();
	private $npcs = array();

	public function __construct($address, $port, $max) {
		$this->address = $address;
		$this->port = $port;
		$this->max = $max;
	}

	public function run() {
		$fh = fopen('data_sent.txt', 'w');
		fwrite($fh, "- - - Data Sent to Client Log - - -\n");
		fclose($fh);
		$this->startup();
		$this->loop();
		$this->shutdown();
	}

	private function startup()
    {
        $this->loadChunks();
        $this->loadNPCs();
        $this->openSocket();

		echo "Server started.\n";
	}

    private function shutdown()
    {
        $this->closeSocket();

		echo "Server stopped.\n";
	}

    private function loadChunks()
    {
		echo 'Loading chunks... ';
		$file_names = scandir('chunks');
		foreach($file_names as $file_name) {
			if($file_name == '.' || $file_name == '..') { continue; }
			$chunk_name = substr($file_name, 0, -4);
			$this->chunks[$chunk_name] = new Chunk($chunk_name);
		}
		echo "Done!\n";
    }

    private function loadNPCs()
    {
        echo 'Loading NPCs... ';
		$npc_config_file = ereg_replace("[\t\n\r]", '', file_get_contents('config/npcs.txt'));
		$npc_config = explode('#', $npc_config_file);
		foreach($npc_config as $npc_data) {
			$npc_fields = explode(',', $npc_data);
			$this->npcs[] = new NPC($npc_fields[0], $npc_fields[1], $npc_fields[2], $npc_fields[3], $npc_fields[4], $npc_fields[5]);
		}
		echo "Done!\n";
    }

    private function openSocket()
    {
		echo 'Setting up socket... ';
		$this->socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_bind($this->socket, $this->address, $this->port) or die('Error: Could not bind to address');
		socket_listen($this->socket);
		echo "Done!\n";
    }

    private function closeSocket()
    {
        socket_close($this->socket);
    }

	private function loop() {
		while(true) {
			//Setup clients listen socket for reading
			$read[0] = $this->socket;
			for($i = 0; $i < $this->max; $i++) {
				if(isset($this->clients[$i]))
					$read[$i+1] = $this->clients[$i]->get_socket();
			}

			//Set up a blocking call to socket_select()
			if(socket_select($read, $write = NULL, $except = NULL, $tv_sec = 5) < 1) {
				continue;
			}

			//If a new connection is being made add it to the client array
			if(in_array($this->socket, $read)) {
				for($i=0;$i<$this->max;$i++) {
					if(!isset($this->clients[$i])) {
						$this->clients[$i] = new Client(socket_accept($this->socket));
						echo 'New client connected as #'.$i.".\n";
						echo 'Total clients connected: '.count($this->clients)."\n";
						break;
					}
					elseif($i == $this->max - 1) {
						echo "Max client limit reached.\n";
					}
				}
			}

			//If a client is trying to write - handle it now
			for($i=0;$i<$this->max;$i++) {
				if(!isset($this->clients[$i])) { continue; }
				if(in_array($this->clients[$i]->get_socket(), $read)) {
					$packet = $this->clients[$i]->read_socket();
					if($packet == null) {
						unset($this->clients[$i]);
						echo 'Client #'.$i." disconnecting.\n";
					}
					else {
						echo 'New packet received from client #'.$i.': '.str_replace("\n", '', $packet)."\n";
						echo '### Dealing with packets for client #'.$i." ###\n";
						$this->deal_with_packet($i, $packet);
						echo '### Updating players for client #'.$i." ###\n";
						$this->update_players();
					}
				}
			}
		}
	}

	private function clean_packet($packet) {
		return substr(ereg_replace("[ \t\r]", '', $packet).chr(0), 0, -1);
	}

	private function deal_with_packet($i, $packet) {
		$fields = explode("\n", $this->clean_packet($packet));
		$op_code = $fields[0];
		switch($op_code) {
			case 0: //Login request
				if($fields[1] == 'gay' && $fields[2] == 'fags') {
					$this->send_packet("1\ntrue\n", $i);
					echo 'Client #'.$i.' has logged in as '.$fields[1].".\n";
					$this->clients[$i]->set_account_data($fields[1]);
					$this->clients[$i]->update_pos(1, 1, 512, 512);
					$this->send_chunks($i);
					$this->send_player($i);
					$this->spawn_player($i);
					$this->get_spawned_players($i);
					$this->send_music(0, 'loop', $i);
				}
				else {
					$this->send_packet("1\nfalse\n", $i);
					echo 'Client #'.$i.' failed to login as '.$fields[1].".\n";
				}
				break;
			case 10:
				$chunkx = $this->clients[$i]->chunkx;
				$chunky = $this->clients[$i]->chunky;
				$localx = $this->clients[$i]->localx;
				$localy = $this->clients[$i]->localy;
				$speed = 4;
				$new_chunkx = $chunkx;
				$new_chunky = $chunky;
				$new_localx = $localx;
				$new_localy = $localy;
				switch($fields[1]) {
					case 'd':
						$new_localy += $speed;
						break;
					case 'u':
						$new_localy -= $speed;
						break;
					case 'l':
						$new_localx -= $speed;
						break;
					case 'r':
						$new_localx += $speed;
						break;
				}
				//echo 'Client #'.$i.' pos: x='.$new_localx.', y='.$new_localy."\n";
				if($new_localy < 0) { $new_chunky--; $new_localy += 1023; }
				elseif($new_localy > 1023) { $new_chunky++; $new_localy -= 1023; }

				if($new_localx < 0) { $new_chunkx--; $new_localx += 1023; }
				elseif($new_localx > 1023) { $new_chunkx++; $new_localx -= 1023; }

				$this->clients[$i]->update_pos($new_chunkx, $new_chunky, $new_localx, $new_localy);
				$this->send_player($i);

				if($new_chunkx != $chunkx || $new_chunky != $chunky) {
					echo "Sent new grid brah to #".$i.".\n";
					$this->send_chunks($i);
				}
				break;
			default:
				$this->send_packet('Stop hacking the server Kieron.', $i);
				echo "Kieron tried to hack the server.\n";
				break;
		}
	}

	private function send_packet($packet, $client) {
		socket_write($this->clients[$client]->get_socket(), $packet);
		//echo 'Sents packet to client #'.$client.': '.$packet."\n";
		$fh = fopen('data_sent.txt', 'a');
		fwrite($fh, str_replace("\n", '|', $packet)."\n");
		fclose($fh);
	}

	private function send_global_packet($packet) {
		for($i=0;$i<$this->max;$i++) {
			if(!isset($this->clients[$i])) { continue; }
			socket_write($this->clients[$i]->get_socket(), $packet);
		}
		$fh = fopen('data_sent.txt', 'a');
		fwrite($fh, 'GLOBAL: '.str_replace("\n", '|', $packet)."\n");
		fclose($fh);
	}

	private function set_bordering_chunks($client) {
		$chunkx = $this->clients[$client]->chunkx;
		$chunky = $this->clients[$client]->chunky;
		$this->clients[$client]->border['tl']['x'] = $chunkx-1; $this->clients[$client]->border['tl']['y'] = $chunky-1;
		$this->clients[$client]->border['t']['x'] = $chunkx; $this->clients[$client]->border['t']['y'] = $chunky-1;
		$this->clients[$client]->border['tr']['x'] = $chunkx+1; $this->clients[$client]->border['tr']['y'] = $chunky-1;
		$this->clients[$client]->border['ml']['x'] = $chunkx-1; $this->clients[$client]->border['ml']['y'] = $chunky;
		$this->clients[$client]->border['m']['x'] = $chunkx; $this->clients[$client]->border['m']['y'] = $chunky;
		$this->clients[$client]->border['mr']['x'] = $chunkx+1; $this->clients[$client]->border['mr']['y'] = $chunky;
		$this->clients[$client]->border['bl']['x'] = $chunkx-1; $this->clients[$client]->border['bl']['y'] = $chunky+1;
		$this->clients[$client]->border['b']['x'] = $chunkx; $this->clients[$client]->border['b']['y'] = $chunky+1;
		$this->clients[$client]->border['br']['x'] = $chunkx+1; $this->clients[$client]->border['br']['y'] = $chunky+1;
	}

	private function send_chunks($client) {
		$this->set_bordering_chunks($client);

		$x = 0;
		$y = 0;
		//$layer_count = 0;
		foreach($this->clients[$client]->border as $chunk) {
			if(isset($this->chunks['x'.$chunk['x'].'y'.$chunk['y']])) {
				$this->send_packet("3\n".$chunk['x']."\n".$chunk['y']."\n".$x."\n".$y."\n", $client);
				$this->send_packet("4\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(0), $client);
				//echo 'Chunk sent: '.str_replace("\n", '|', "4\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(0))."\n";
				$this->send_packet("4\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(1), $client);
				//echo 'Chunk sent: '.str_replace("\n", '|', "4\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(1))."\n";
				$this->send_packet("4\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(2), $client);
				//echo 'Chunk sent: '.str_replace("\n", '|', "4\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(2))."\n";
				$this->send_packet("4\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(3), $client);
				//echo 'Chunk sent: '.str_replace("\n", '|', "4\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(3))."\n";
				$this->send_packet("4\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(4), $client);
				//echo 'Chunk sent: '.str_replace("\n", '|', "4\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(4))."\n";
				$this->send_packet("5\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(5), $client);
				//echo 'Chunk sent: '.str_replace("\n", '|', "5\n".$this->chunks['x'.$chunk['x'].'y'.$chunk['y']]->get_layer_packet(5))."\n";
				//echo 'Sent client #'.$client.' chunk: x'.$chunk['x'].'y'.$chunk['y']."\n";
				//$layer_count += 6;
			}
			$x++; if($x==3){ $y++; $x=0; }
		}
		//echo 'Sent '.$layer_count." layers.\n";
	}

	private function send_player($client) {
		$this->send_packet("6\n0\n".$this->clients[$client]->chunkx."\n".$this->clients[$client]->chunky."\n".$this->clients[$client]->localx."\n".$this->clients[$client]->localy."\n", $client);
	}

	private function spawn_player($client) {
		for($a=0;$a<$this->max;$a++) {
			if(!isset($this->clients[$a]) || $a == $client) { continue; }
			$this->send_packet("11\n".$client."\n".$this->clients[$client]->username."\n", $a);
			echo 'Sent spawn data to #'.$a.' for #'.$client.".\n";
		}
	}

	private function get_spawned_players($client) {
		for($a=0;$a<$this->max;$a++) {
			if(!isset($this->clients[$a]) || $a == $client) { continue; }
			$this->send_packet("11\n".$a."\n".$this->clients[$a]->username."\n", $client);
			echo 'Sent spawn data to #'.$client.' for #'.$a.".\n";
		}
	}

	private function update_players() {
		for($a=0;$a<$this->max;$a++) {
			if(!isset($this->clients[$a]) || $this->clients[$a]->username == '') { continue; }
			for($b=0;$b<$this->max;$b++) {
				if($a == $b || !isset($this->clients[$b])) { continue; }
				$this->send_packet("12\n".$b."\n".$this->clients[$b]->chunkx."\n".$this->clients[$b]->chunky."\n".$this->clients[$b]->localx."\n".$this->clients[$b]->localy."\n", $a);
				echo 'Sent player position of #'.$b.' to client #'.$a."\n";
			}
		}
	}

	private function send_npcs($client) {
		foreach($this->clients[$client]['border'] as $chunk) {
			if(isset($this->chunks['x'.$chunk['x'].'y'.$chunk['y']])) {
				$this->send_packet("6\n".$chunk['x']."\n".$chunk['y']."\n", $client);
				echo 'Sent client #'.$client.' NPCs in chunk: x'.$chunk['x'].'y'.$chunk['y']."\n";
			}
		}
	}

	private function send_music($id, $type, $client) {
		if($type == 'loop') { $op_code = 7; }
		elseif($type == 'once') { $op_code = 8; }
		elseif($type == 'alone') { $op_code = 9; }
		else { return false; }

		$this->send_packet($op_code."\n".$id."\n", $client);
		echo 'Sent Client #'.$client.' music #'.$id.'. (Type: '.$type.")\n";
	}
}

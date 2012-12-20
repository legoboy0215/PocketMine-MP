<?php

/*

           -
         /   \
      /         \
   /    POCKET     \
/    MINECRAFT PHP    \
|\     @shoghicp     /|
|.   \           /   .|
| ..     \   /     .. |
|    ..    |    ..    |
|       .. | ..       |
\          |          /
   \       |       /
      \    |    /
         \ | /

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.


*/

class LevelAPI{
	private $server, $map, $active = false;
	function __construct($server){
		$this->server = $server;
		$this->map = $this->server->map;
		if($this->map !== false){
			$this->active = true;
		}
	}
	
	public function init(){
		$this->server->addHandler("onBlockBreak", array($this, "handle"));
		$this->server->addHandler("onBlockPlace", array($this, "handle"));
	}
	
	public function handle($data, $event){
		switch($event){
			case "onBlockPlace":
				$block = $this->getBlock($data["x"], $data["y"], $data["z"]);
				console("[DEBUG] EID ".$data["eid"]." placed ".$data["block"].":".$data["meta"]." into ".$block[0].":".$block[1]." at X ".$data["x"]." Y ".$data["y"]." Z ".$data["z"], true, true, 2);
				$this->setBlock($data["x"], $data["y"], $data["z"], $data["block"], $data["meta"]);
				break;
			case "onBlockBreak":
					$block = $this->getBlock($data["x"], $data["y"], $data["z"]);
					console("[DEBUG] EID ".$data["eid"]." broke block ".$block[0].":".$block[1]." at X ".$data["x"]." Y ".$data["y"]." Z ".$data["z"], true, true, 2);
					
					if($block[0] === 0){
						break;
					}
					$this->setBlock($data["x"], $data["y"], $data["z"], 0, 0);
					$data["block"] = $block[0];
					$data["meta"] = $block[1];
					$data["stack"] = 1;
					$data["x"] += mt_rand(2, 8) / 10;
					$data["y"] += mt_rand(2, 8) / 10;
					$data["z"] += mt_rand(2, 8) / 10;
					$e = $this->server->api->entity->add(ENTITY_ITEM, $block[0], $data);
					$this->server->api->entity->spawnToAll($e->eid);
				break;
		}
	}
	
	private function check(){
		if($this->active === false and $this->server->map === false){
			return false;
		}
		$this->active = true;
		return true;
	}

	public function getChunk($X, $Z){
		if($this->check() and isset($this->map->map[$X][$Z])){
			return $this->map->map[$X][$Z];		
		}
		return false;
	}
	
	public function getBlock($x, $y, $z){
		if($this->check()){
			return $this->map->getBlock($x, $y, $z);		
		}
		return array(0,0);
	}
	
	public function setBlock($x, $y, $z, $block, $meta = 0){
		if($this->check()){
			$this->map->setBlock($x, $y, $z, $block, $meta);
		}
		$this->server->trigger("onBlockUpdate", array(
			"x" => $x,
			"y" => $y,
			"z" => $z,
			"block" => $block,
			"meta" => $meta,
		));
	}
	
	public function getOrderedChunk($X, $Z, $columnsPerPacket = 2){
		$columnsPerPacket = max(1, (int) $columnsPerPacket);
		$c = $this->getChunk($X, $Z);
		if($c === false){
			return array(str_repeat("\x00", 256));
		}
		$ordered = array();
		for($i = 0;$i < 0xff; ){
			$ordered[$i] = str_repeat("\x00", $i);
			for($j = 0; $j < $columnsPerPacket; ++$j){
				if(($i + $j) > 0xff){
					break;
				}
				$ordered[$i] .= "\xff";
				for($k = 0; $k < 8; ++$k){
					$ordered[$i] .= substr($c[0][$i+$j], $k << 4, 16); //Block data
					$ordered[$i] .= substr($c[1][$i+$j], $k << 3, 8); //Meta data
				}
			}
			$i += $columnsPerPacket;
		}
		return $ordered;
	}
}
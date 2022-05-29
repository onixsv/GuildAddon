<?php

declare(strict_types=1);

namespace alvin0319\GuildAddon;

use alvin0319\GuildAddon\command\GuildBoostCommand;
use alvin0319\GuildAPI\event\GuildCreateEvent;
use alvin0319\GuildAPI\event\GuildRemoveEvent;
use alvin0319\GuildAPI\Guild;
use alvin0319\GuildAPI\GuildAPI;
use alvin0319\LevelAPI\event\PlayerExpUpEvent;
use OnixUtils\OnixUtils;
use pocketmine\block\BlockLegacyIds;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use function array_values;
use function arsort;
use function ceil;
use function count;
use function date;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function json_decode;
use function json_encode;
use function lcg_value;
use function min;
use function mt_rand;
use function time;

class GuildAddon extends PluginBase{
	use SingletonTrait;

	protected $data = [];

	protected function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		if(file_exists($file = $this->getDataFolder() . "data.json")){
			$this->data = json_decode(file_get_contents($file), true);
		}

		$this->getServer()->getPluginManager()->registerEvent(GuildCreateEvent::class, function(GuildCreateEvent $event) : void{
			$guild = $event->getGuild();
			$this->data[$guild->getName()] = [
				"boosters" => [],
				"boost" => 0,
				"boostEndsAt" => []
			];
		}, EventPriority::NORMAL, $this);

		$this->getServer()->getPluginManager()->registerEvent(GuildRemoveEvent::class, function(GuildRemoveEvent $event) : void{
			$guild = $event->getGuild();
			if(isset($this->data[$guild->getName()])){
				unset($this->data[$guild->getName()]);
			}
		}, EventPriority::NORMAL, $this);

		$this->getServer()->getPluginManager()->registerEvent(PlayerExpUpEvent::class, function(PlayerExpUpEvent $event) : void{
			$player = $event->getPlayer();

			$guild = GuildAPI::getInstance()->getGuildByPlayer($player);

			if($guild !== null){
				if($this->isBoostingGuild($guild)){
					$level = $this->getBoostLevel($guild);
					$event->setExp((int) ceil($event->getExp() + ($level * lcg_value())));
				}
			}
		}, EventPriority::NORMAL, $this);

		$this->getServer()->getPluginManager()->registerEvent(BlockBreakEvent::class, function(BlockBreakEvent $event) : void{
			$block = $event->getBlock();

			$player = $event->getPlayer();

			$guild = GuildAPI::getInstance()->getGuildByPlayer($player);

			if($guild !== null){
				if($this->isBoostingGuild($guild)){
					$boostLevel = $this->getBoostLevel($guild);
					if($boostLevel > 5)
						$boostLevel = 5;
					if($player->getWorld()->getFolderName() === "mine"){
						$ores = [
							BlockLegacyIds::STONE,
							BlockLegacyIds::COAL_ORE,
							BlockLegacyIds::IRON_ORE,
							BlockLegacyIds::GOLD_ORE,
							BlockLegacyIds::LAPIS_ORE,
							BlockLegacyIds::REDSTONE_ORE,
							BlockLegacyIds::LIT_REDSTONE_ORE,
							BlockLegacyIds::DIAMOND_ORE,
							BlockLegacyIds::EMERALD_ORE
						];
						if(in_array($block->getId(), $ores)){
							$drops = $event->getDrops();
							if(count($drops) > 0){
								for($i = 0; $i < count($drops); $i++){
									if(mt_rand(0, 3) === 2){
										$drops[$i]->setCount($drops[$i]->getCount() + min(mt_rand(0, $boostLevel), 5));
										break;
									}
								}
							}
							$event->setDrops($drops);
						}
					}
				}
			}
		}, EventPriority::HIGHEST, $this);

		$this->getServer()->getPluginManager()->registerEvent(PlayerJoinEvent::class, function(PlayerJoinEvent $event) : void{
			$player = $event->getPlayer();

			$guild = GuildAPI::getInstance()->getGuildByPlayer($player);

			if($guild !== null){
				if($this->isBoostingGuild($guild)){
					if($this->isBooster($player, $guild)){
						$guild->broadcastMessage("길드 부스터 {$player->getName()}님이 접속했습니다.");
					}
					OnixUtils::message($player, "우리 길드 부스트 만료일은 " . date("Y년 m월 d일 H시 i분 s초", $this->getBoostEndDate($guild)) . " 입니다.");
				}
			}
		}, EventPriority::NORMAL, $this);

		$this->getServer()->getCommandMap()->register("guildaddon", new GuildBoostCommand());
	}

	protected function onDisable() : void{
		file_put_contents($this->getDataFolder() . "data.json", json_encode($this->data));
	}

	public function addBoost(Player $player, Guild $guild) : void{
		$boostTime = 60 * 60 * 24 * 7; // 1 week
		if(!isset($this->data[$guild->getName()])){
			$this->data[$guild->getName()] = [
				"boosters" => [],
				"boost" => 0,
				"boostEndsAt" => []
			];
		}
		$this->data[$guild->getName()]["boosters"][$player->getName()] = true;
		$this->data[$guild->getName()]["boost"] += 1;
		$this->data[$guild->getName()]["boostEndsAt"][$player->getName()] = time() + $boostTime;
	}

	public function isBooster(Player $player, Guild $guild) : bool{
		return isset($this->data[$guild->getName()]["boosters"][$player->getName()]);
	}

	public function isBoostingGuild(Guild $guild) : bool{
		return $this->getBoostLevel($guild) > 0;
	}

	public function getBoostLevel(Guild $guild) : int{
		return $this->data[$guild->getName()]["boost"] ?? 0;
	}

	public function checkBoost() : void{
		foreach($this->data as $guildName => $data){
			$boostEndsAt = $data["boostEndsAt"];
			foreach($boostEndsAt as $playerName => $endAt){
				if(time() >= $endAt){
					unset($this->data[$guildName]["boostEndsAt"][$playerName]);
					$this->data[$guildName]["boost"] -= 1;
					unset($this->data["boosters"][$playerName]);
				}
			}
		}
	}

	public function getBoostEndDate(Guild $guild) : int{
		$res = [];
		foreach($this->data[$guild->getName()]["boostEndsAt"] as $playerName => $endAt){
			$res[$playerName] = $endAt;
		}
		arsort($res);
		return array_values($res)[0] ?? -1;
	}
}
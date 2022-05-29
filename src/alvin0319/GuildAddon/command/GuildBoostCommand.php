<?php

declare(strict_types=1);

namespace alvin0319\GuildAddon\command;

use alvin0319\GuildAddon\GuildAddon;
use alvin0319\GuildAPI\GuildAPI;
use onebone\economyapi\EconomyAPI;
use OnixUtils\OnixUtils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class GuildBoostCommand extends Command{

	public function __construct(){
		parent::__construct("길드부스트", "70만원을 지불하고 길드를 부스트합니다.");
		$this->setPermission("guildaddon.command.use");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return false;
		}
		if(!$sender instanceof Player){
			return false;
		}
		$guild = GuildAPI::getInstance()->getGuildByPlayer($sender);
		if($guild === null){
			OnixUtils::message($sender, "당신은 길드에 소속되어있지 않습니다.");
			return false;
		}
		if(GuildAddon::getInstance()->isBooster($sender, $guild)){
			OnixUtils::message($sender, "이미 이 길드를 부스트 했습니다.");
			return false;
		}
		$money = 700000;
		if(EconomyAPI::getInstance()->myMoney($sender) < $money){
			OnixUtils::message($sender, "부스트를 구매하기 위한 돈이 부족합니다. (70만원 필요)");
			return false;
		}
		EconomyAPI::getInstance()->reduceMoney($sender, $money);
		GuildAddon::getInstance()->addBoost($sender, $guild);
		OnixUtils::message($sender, "길드를 부스트했습니다!");
		return true;
	}
}
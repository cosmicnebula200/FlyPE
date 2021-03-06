<?php

/* 
 *  ______ _  __     _______  ______ 
 * |  ____| | \ \   / /  __ \|  ____|
 * | |__  | |  \ \_/ /| |__) | |__   
 * |  __| | |   \   / |  ___/|  __|  
 * | |    | |____| |  | |    | |____ 
 * |_|    |______|_|  |_|    |______|
 *
 * FlyPE, is an advanced fly plugin for PMMP.
 * Copyright (C) 2020-2021 AGTHARN
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace AGTHARN\FlyPE;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat as C;
use pocketmine\Player;

use AGTHARN\FlyPE\Main;
use AGTHARN\FlyPE\util\Util;
use AGTHARN\FlyPE\tasks\FlightDataTask;

class EventListener implements Listener {
    
    /**
     * plugin
     * 
     * @var Main
     */
	private $plugin;
	
	/**
	 * util
	 * 
     * @var Util
     */
	private $util;

	/**
	 * __construct
	 *
	 * @param  Main $plugin
	 * @param  Util $util
	 * @return void
	 */
	public function __construct(Main $plugin, Util $util) {
		$this->plugin = $plugin;
		$this->util = $util;
	}
	
	/**
	 * onLevelChange
	 *
	 * @param  EntityLevelChangeEvent $event
	 * @return void
	 */
	public function onLevelChange(EntityLevelChangeEvent $event): void {
		$entity = $event->getEntity();
		$targetLevel = $event->getTarget()->getName();

		if (!$entity instanceof Player || $this->util->checkGamemodeCreative($entity) === true || $entity->hasPermission("flype.command.bypass") || $entity->getAllowFlight() === false) return;
		if ($this->util->doTargetLevelCheck($entity, $targetLevel) === false) {
			if ($this->plugin->getConfig()->get("level-change-restricted") === true) {
				$entity->sendMessage(C::RED . str_replace("{world}", $targetLevel, $this->plugin->getConfig()->get("flight-not-allowed")));
			}
			$this->util->toggleFlight($entity);
		} elseif ($this->plugin->getConfig()->get("level-change-unrestricted") === true) {
			$entity->sendMessage(C::GREEN . str_replace("{world}", $targetLevel, $this->plugin->getConfig()->get("flight-is-allowed")));
		}
	}

	/**
	 * onPlayerJoin
	 *
	 * @param  PlayerJoinEvent $event
	 * @return void
	 */
	public function onPlayerJoin(PlayerJoinEvent $event): void {
		$player = $event->getPlayer();
		$name = $player->getName();
		
		if (($this->util->checkGamemodeCreative($player) === false || $this->util->checkGamemodeCreativeSetting($player) === true) && $this->plugin->getConfig()->get("join-disable-fly") === true && $player->getAllowFlight() === true && $player instanceof Player) {
			$player->setFlying(false);
			$player->setAllowFlight(false);
			$player->sendMessage(C::RED . str_replace("{name}", $name, $this->plugin->getConfig()->get("onjoin-flight-disabled")));
			return;
		}
	}
	
	/**
	 * onPlayerQuit
	 *
	 * @param  PlayerQuitEvent $event
	 * @return void
	 */
	public function onPlayerQuit(PlayerQuitEvent $event){
		$player = $event->getPlayer();
		$playerData = $this->util->getFlightData($player);
		$data[$player->getId()] = new FlightDataTask($this->plugin, $this->util);

        if (isset($data[$player->getId()])) {
			$playerData->saveData();
            unset($data[$player->getId()]);
        }
    }
		
	/**
	 * onInventoryPickupItem
	 *
	 * @param  InventoryPickupItemEvent $event
	 * @return void
	 */
	public function onInventoryPickupItem(InventoryPickupItemEvent $event): void {
		$inventory = $event->getInventory();
		/** @phpstan-ignore-next-line */
		$player = $event->getInventory()->getHolder();

		if (($this->util->checkGamemodeCreative($player) === false || $this->util->checkGamemodeCreativeSetting($player) === true) && $this->plugin->getConfig()->get("picking-up-items") === false && $player->getAllowFlight() === true && $player instanceof Player) {
			$event->setCancelled();
		}
	}
		
	/**
	 * onPlayerDropItem
	 *
	 * @param  PlayerDropItemEvent $event
	 * @return void
	 */
	public function onPlayerDropItem(PlayerDropItemEvent $event): void {
		$player = $event->getPlayer();
		
		if (($this->util->checkGamemodeCreative($player) === false || $this->util->checkGamemodeCreativeSetting($player) === true) && $this->plugin->getConfig()->get("item-dropping") === false && $player->getAllowFlight() === true && $player instanceof Player) {
			$event->setCancelled();
		}
	}
		
	/**
	 * onBlockBreak
	 *
	 * @param  BlockBreakEvent $event
	 * @return void
	 */
	public function onBlockBreak(BlockBreakEvent $event): void {
		$player = $event->getPlayer();
		
		if (($this->util->checkGamemodeCreative($player) === false || $this->util->checkGamemodeCreativeSetting($player) === true) && $this->plugin->getConfig()->get("block-breaking") === false && $player->getAllowFlight() === true && $player instanceof Player) {
			$event->setCancelled();
		}
	}
		
	/**
	 * onBlockPlace
	 *
	 * @param  BlockPlaceEvent $event
	 * @return void
	 */
	public function onBlockPlace(BlockPlaceEvent $event): void {
		$player = $event->getPlayer();
		
		if (($this->util->checkGamemodeCreative($player) === false || $this->util->checkGamemodeCreativeSetting($player) === true) && $this->plugin->getConfig()->get("block-placing") === false && $player->getAllowFlight() === true && $player instanceof Player) {
			$event->setCancelled();
		}
	}
		
	/**
	 * onPlayerItemConsume
	 *
	 * @param  PlayerItemConsumeEvent $event
	 * @return void
	 */
	public function onPlayerItemConsume(PlayerItemConsumeEvent $event): void {
		$player = $event->getPlayer();
		
		if (($this->util->checkGamemodeCreative($player) === false || $this->util->checkGamemodeCreativeSetting($player) === true) && $this->plugin->getConfig()->get("player-eating") === false && $player->getAllowFlight() === true) {
			$event->setCancelled();
		}
	}
    
    /**
     * onEntityDamageEntity
     *
     * @param  EntityDamageByEntityEvent $event
     * @return void
     */
    public function onEntityDamageEntity(EntityDamageByEntityEvent $event): void {
	    if (!$event->isCancelled()) {
			$entity = $event->getEntity();
			$damager = $event->getDamager();
			$levelName = $event->getEntity()->getLevel()->getName();

			if ($entity instanceof Player && $damager instanceof Player) {
				if ((($this->util->checkGamemodeCreative($damager) === false || $this->util->checkGamemodeCreativeSetting($damager) === true) || ($this->util->checkGamemodeCreative($entity) === false || $this->util->checkGamemodeCreativeSetting($entity) === true)) && $this->plugin->getConfig()->get("combat-disable-fly") === true) {

					if ($damager->getAllowFlight() === true) {
						$damager->setAllowFlight(false);
						$damager->setFlying(false);
						$damager->sendMessage(C::RED . str_replace("{world}", $levelName, $this->plugin->getConfig()->get("combat-fly-disable")));
					}

					if ($entity->getAllowFlight() === true) {
						$entity->setAllowFlight(false);
						$entity->setFlying(false);
						$entity->sendMessage(C::RED . str_replace("{world}", $levelName, $this->plugin->getConfig()->get("combat-fly-disable")));
					}
				}
			}
		}
	}
}

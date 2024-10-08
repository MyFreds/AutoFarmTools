<?php

namespace Fred\autofarmtools;

use jojoe77777\FormAPI\SimpleForm;
use davidglitch04\libEco\libEco;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Farmland;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener
{
    private array $shopConfig;
    private array $messages;

    public function onEnable(): void
    {
        $this->saveResource("autofarmtoolshop.yml");
        $this->shopConfig = yaml_parse_file(
            $this->getDataFolder() . "autofarmtoolshop.yml"
        );

        $this->saveResource("message.yml");
        $this->messages = yaml_parse_file(
            $this->getDataFolder() . "message.yml"
        );

        $this->getServer()
            ->getPluginManager()
            ->registerEvents($this, $this);
    }

    public function onCommand(
        CommandSender $sender,
        Command $command,
        string $label,
        array $args
    ): bool {
        if ($sender instanceof Player) {
            if ($command->getName() === "autofarmtools") {
                $inventory = $sender->getInventory();

                if ($inventory->canAddItem(VanillaItems::STICK())) {
                    $item = VanillaItems::STICK();
                    $item->setCustomName("§l§dAuto Farm Tools");
                    $inventory->addItem($item);
                    $sender->sendMessage(
                        $this->messages["received-auto-farm"] ??
                            TextFormat::GREEN .
                                "You have received §l§dAuto Farm Tools!"
                    );
                } else {
                    $sender->sendMessage(
                        $this->messages["inventory-full"] ??
                            TextFormat::RED . "Your inventory is full!"
                    );
                }
                return true;
            }

            if ($command->getName() === "autofarmtoolshop") {
                if (!$this->shopConfig["autofarmtoolshop-enable"]) {
                    $sender->sendMessage(
                        $this->shopConfig["messages"]["shop-disabled"] ??
                            "Auto Farm Tools §r§cnot for sale at this time."
                    );
                    return true;
                }

                $this->openShopUI($sender);
                return true;
            }
        } else {
            $sender->sendMessage(
                "This command can only run by player."
            );
        }
        return false;
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $item = $event->getItem();
        $inventory = $player->getInventory();

        if (
            $item->getCustomName() === "§l§dAuto Farm Tools" &&
            $block instanceof Farmland
        ) {
            $seeds = [
                VanillaItems::WHEAT_SEEDS(),
                VanillaItems::POTATO(),
                VanillaItems::CARROT(),
                VanillaItems::MELON_SEEDS(),
                VanillaItems::PUMPKIN_SEEDS(),
            ];

            $emptyFarmlandFound = false;

            for ($x = -2; $x <= 1; $x++) {
                for ($z = -2; $z <= 1; $z++) {
                    $targetBlock = $block
                        ->getPosition()
                        ->getWorld()
                        ->getBlock($block->getPosition()->add($x, 0, $z));
                    if ($targetBlock instanceof Farmland) {
                        $aboveBlock = $block
                            ->getPosition()
                            ->getWorld()
                            ->getBlock(
                                $targetBlock->getPosition()->add(0, 1, 0)
                            );
                        if (
                            $aboveBlock->getTypeId() ===
                            VanillaBlocks::AIR()->getTypeId()
                        ) {
                            foreach ($seeds as $seed) {
                                if ($inventory->contains($seed)) {
                                    $inventory->removeItem($seed);
                                    $block
                                        ->getPosition()
                                        ->getWorld()
                                        ->setBlock(
                                            $aboveBlock->getPosition(),
                                            $this->getCropBlock($seed)
                                        );
                                    $emptyFarmlandFound = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            if ($emptyFarmlandFound) {
                $player->sendMessage(
                    $this->messages["seeds-planted"] ??
                        TextFormat::GREEN .
                            "Seeds have been planted in an empty farmland!"
                );
            } else {
                $player->sendMessage(
                    $this->messages["no-empty-farmland-or-no-seed"] ??
                        TextFormat::RED .
                            "There are no empty farmlands available or no seeds in your inventory!"
                );
            }
        }
    }

    private function getCropBlock(Item $seed): ?\pocketmine\block\Block
    {
        if ($seed->equals(VanillaItems::WHEAT_SEEDS())) {
            return VanillaBlocks::WHEAT();
        } elseif ($seed->equals(VanillaItems::POTATO())) {
            return VanillaBlocks::POTATOES();
        } elseif ($seed->equals(VanillaItems::CARROT())) {
            return VanillaBlocks::CARROTS();
        } elseif ($seed->equals(VanillaItems::MELON_SEEDS())) {
            return VanillaBlocks::MELON_STEM();
        } elseif ($seed->equals(VanillaItems::PUMPKIN_SEEDS())) {
            return VanillaBlocks::PUMPKIN_STEM();
        }
        return null;
    }

    private function openShopUI(Player $player): void
    {
        $economy = new libEco();
        $economy->myMoney($player, function(float $balance) use ($player) {
            $price = $this->shopConfig["price"] ?? 100;
            $content = $this->shopConfig["content"] ?? "§aClick the button to purchase tool";
          
            $content = str_replace(["{money}", "{price}"], [(string) $balance, $this->shopConfig["price"]], $content);

            $form = new SimpleForm(function (Player $player, $data) use ($price, $balance) {
                if ($data === null) {
                    return;
                }

                if ($balance >= $price) {
                    $economy = new libEco();
                    $economy->reduceMoney($player, $price, function(bool $success) use ($player) {
                        if ($success) {
                            $item = VanillaItems::STICK();
                            $item->setCustomName("§l§dAuto Farm Tools");
                            $player->getInventory()->addItem($item);
                            $player->sendMessage(
                                $this->shopConfig["messages"]["bought-auto-farm"] ??
                                    "§aYou have purchased §l§dAuto Farm Tools!"
                            );
                        } else {
                            $player->sendMessage(
                                "§cFailing to reduce money, please try again!"
                            );
                        }
                    });
                } else {
                    $player->sendMessage(
                        $this->shopConfig["messages"]["not-enough-money"] ??
                            "§cYou don't have enough money to buy §l§dAuto Farm Tools."
                    );
                }
            });

            $form->setTitle(
                $this->shopConfig["title"] ?? "§l§6Auto Farm Tools Shop"
            );
            $form->setContent($content);
            $form->addButton(
                $this->shopConfig["button-name"] ?? "§aBuy Auto Farm Tools"
            );
            $player->sendForm($form);
        });
    }
}

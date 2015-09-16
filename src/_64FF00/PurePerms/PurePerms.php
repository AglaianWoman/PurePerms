<?php

namespace _64FF00\PurePerms;

use _64FF00\PurePerms\commands\AddGroup;
use _64FF00\PurePerms\commands\DefGroup;
use _64FF00\PurePerms\commands\FPerms;
use _64FF00\PurePerms\commands\Groups;
use _64FF00\PurePerms\commands\ListGPerms;
use _64FF00\PurePerms\commands\ListUPerms;
use _64FF00\PurePerms\commands\PPInfo;
use _64FF00\PurePerms\commands\PPReload;
use _64FF00\PurePerms\commands\RmGroup;
use _64FF00\PurePerms\commands\SetGPerm;
use _64FF00\PurePerms\commands\SetGroup;
use _64FF00\PurePerms\commands\SetUPerm;
use _64FF00\PurePerms\commands\UnsetGPerm;
use _64FF00\PurePerms\commands\UnsetUPerm;
use _64FF00\PurePerms\commands\UsrInfo;
use _64FF00\PurePerms\ppdata\PPGroup;
use _64FF00\PurePerms\ppdata\PPUser;
use _64FF00\PurePerms\provider\DefaultProvider;
use _64FF00\PurePerms\provider\MySQLProvider;
use _64FF00\PurePerms\provider\ProviderInterface;
use _64FF00\PurePerms\provider\SQLite3Provider;

use pocketmine\IPlayer;

use pocketmine\permission\PermissionAttachment;

use pocketmine\Player;

use pocketmine\plugin\PluginBase;

class PurePerms extends PluginBase
{
    /* PurePerms by 64FF00 (xktiverz@gmail.com, @64ff00 for Twitter) */

    /*
          # #    #####  #       ####### #######   ###     ###
          # #   #     # #    #  #       #        #   #   #   #
        ####### #       #    #  #       #       #     # #     #
          # #   ######  #    #  #####   #####   #     # #     #
        ####### #     # ####### #       #       #     # #     #
          # #   #     #      #  #       #        #   #   #   #
          # #    #####       #  #       #         ###     ###

    */

    const CORE_PERM = "\x70\x70\x65\x72\x6d\x73\x2e\x63\x6f\x6d\x6d\x61\x6e\x64\x2e\x70\x70\x69\x6e\x66\x6f";

    private $attachments = [], $groups = [];
    
    private $isGroupsLoaded, $messages, $provider;
    
    public function onLoad()
    {
        $this->saveDefaultConfig();
        
        $this->messages = new PPMessages($this);
        
        if($this->getConfigValue("enable-multiworld-perms") == false)
        {
            $this->getLogger()->notice($this->getMessage("logger_messages.onEnable_01"));
            $this->getLogger()->notice($this->getMessage("logger_messages.onEnable_02"));
        }
        else
        {
            $this->getLogger()->notice($this->getMessage("logger_messages.onEnable_03"));
        }
    }
    
    public function onEnable()
    {
        $this->registerCommands();
        
        $this->setProvider();

        $this->registerAllPlayers();
        
        $this->getServer()->getPluginManager()->registerEvents(new PPListener($this), $this);
    }

    public function onDisable()
    {
        $this->unregisterAllPlayers();

        if($this->isValidProvider()) $this->provider->close();
    }
    
    private function registerCommands()
    {
        $commandMap = $this->getServer()->getCommandMap();
        
        $commandMap->register("addgroup", new AddGroup($this, "addgroup", $this->getMessage("cmds.addgroup.desc")));
        $commandMap->register("defgroup", new DefGroup($this, "defgroup", $this->getMessage("cmds.defgroup.desc")));
        $commandMap->register("fperms", new FPerms($this, "fperms", $this->getMessage("cmds.fperms.desc")));
        $commandMap->register("groups", new Groups($this, "groups", $this->getMessage("cmds.groups.desc")));
        $commandMap->register("listgperms", new ListGPerms($this, "listgperms", $this->getMessage("cmds.listgperms.desc")));
        $commandMap->register("listuperms", new ListUPerms($this, "listuperms", $this->getMessage("cmds.listuperms.desc")));
        $commandMap->register("ppinfo", new PPInfo($this, "ppinfo", $this->getMessage("cmds.ppinfo.desc")));
        $commandMap->register("ppreload", new PPReload($this, "ppreload", $this->getMessage("cmds.ppreload.desc")));
        $commandMap->register("rmgroup", new RmGroup($this, "rmgroup", $this->getMessage("cmds.rmgroup.desc")));
        $commandMap->register("setgperm", new SetGPerm($this, "setgperm", $this->getMessage("cmds.setgperm.desc")));
        $commandMap->register("setgroup", new SetGroup($this, "setgroup", $this->getMessage("cmds.setgroup.desc")));
        $commandMap->register("setuperm", new SetUPerm($this, "setuperm", $this->getMessage("cmds.setuperm.desc")));
        $commandMap->register("unsetgperm", new UnsetGPerm($this, "unsetgperm", $this->getMessage("cmds.unsetgperm.desc")));
        $commandMap->register("unsetuperm", new UnsetUPerm($this, "unsetuperm", $this->getMessage("cmds.unsetuperm.desc")));
        $commandMap->register("usrinfo", new UsrInfo($this, "usrinfo", $this->getMessage("cmds.usrinfo.desc")));
    }

    /**
     * @param bool $onEnable
     */
    private function setProvider($onEnable = true)
    {
        $providerName = $this->getConfigValue("data-provider");
        
        switch(strtolower($providerName))
        {
            case "mysql":

                $provider = new MySQLProvider($this);

                if($onEnable == true) $this->getLogger()->info($this->getMessage("logger_messages.setProvider_MySQL"));

                break;

            case "sqlite3":
            
                $provider = new SQLite3Provider($this);
                
                if($onEnable == true) $this->getLogger()->info($this->getMessage("logger_messages.setProvider_SQLite3"));
                
                break;
                
            case "yaml":
            
                $provider = new DefaultProvider($this);

                if($onEnable == true) $this->getLogger()->info($this->getMessage("logger_messages.setProvider_YAML"));
                
                break;
                
            default:

                $provider = new DefaultProvider($this);

                if($onEnable == true) $this->getLogger()->warning($this->getMessage("logger_messages.setProvider_NotFound"));
                
                break;              
        }

        if(!$this->isValidProvider()) $this->provider = $provider;
        
        $this->loadGroups();
    }
    
    /*
            #    ######  ### ### 
           # #   #     #  #  ### 
          #   #  #     #  #  ### 
         #     # ######   #   #  
         ####### #        #      
         #     # #        #  ### 
         #     # #       ### ###
    */

    /**
     * @param $groupName
     * @return bool
     */
    public function addGroup($groupName)
    {
        $groupsData = $this->getProvider()->getGroupsData(true);
        
        if(isset($groupsData[$groupName])) return false;
        
        $groupsData[$groupName] = [
            "isDefault" => false,
            "inheritance" => [
            ],
            "permissions" => [
            ],
            "worlds" => [
            ]
        ];
            
        $this->getProvider()->setGroupsData($groupsData);
        
        return true;
    }

    /**
     * @param Player $player
     */
    public function dumpPermissions(Player $player)
    {
        $this->getLogger()->notice("--- List of all permissions from " . $player->getName() . " ---");

        foreach($this->getEffectivePermissions($player) as $permission => $value)
        {
            $this->getLogger()->notice("- " . $permission . " : " . ($value ? "true" : "false"));
        }
    }

    public function getAttachment(Player $player)
    {
        $uniqueId = $player->getUniqueId()->toString();

        if(!isset($this->attachments[$uniqueId])) throw new \RuntimeException("Tried to calculate permissions on " .  $player->getName() . " using null attachment");

        return $this->attachments[$uniqueId];
    }

    /**
     * @param $key
     * @return null
     */
    public function getConfigValue($key)
    {
        $value = $this->getConfig()->getNested($key);

        if($value === null)
        {
            $this->getLogger()->warning($this->getMessage("logger_messages.getConfigValue_01", $key));

            return null;
        }

        return $value;
    }

    /**
     * @param $tempNode
     * @return array
     */
    public function getChildNodes($tempNode)
    {
        $result = [];

        $permission = $this->getServer()->getPluginManager()->getPermission($tempNode);

        $childNodes = $permission->getChildren();

        if($childNodes != [])
        {
            foreach($childNodes as $childNode => $value)
            {
                $result[] = $childNode;
            }
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function getDefaultGroup()
    {       
        $defaultGroups = [];

        foreach($this->getGroups() as $defaultGroup)
        {
            if($defaultGroup->isDefault()) array_push($defaultGroups, $defaultGroup);
        }
        
        if(count($defaultGroups) == 1)
        {
            return $defaultGroups[0];
        }
        else
        {
            if(count($defaultGroups) > 1)
            {
                $this->getLogger()->warning($this->getMessage("logger_messages.getDefaultGroup_01"));
            }
            elseif(count($defaultGroups) <= 0)
            {
                $this->getLogger()->warning($this->getMessage("logger_messages.getDefaultGroup_02"));
                
                $defaultGroups = $this->getGroups();
            }
            
            $this->getLogger()->info($this->getMessage("logger_messages.getDefaultGroup_03"));
            
            foreach($defaultGroups as $defaultGroup)
            {
                if(count($defaultGroup->getInheritedGroups()) == 0)
                {
                    $this->setDefaultGroup($defaultGroup);
                        
                    return $defaultGroup;
                }
            }
        }

        // #ermagerd
        return null;
    }

    /**
     * @param Player $player
     * @return array
     */
    public function getEffectivePermissions(Player $player)
    {
        $permissions = [];
        
        foreach($player->getEffectivePermissions() as $attachmentInfo)
        {
            $permission = $attachmentInfo->getPermission();
            
            $value = $attachmentInfo->getValue();
            
            $permissions[$permission] = $value;
        }
        
        ksort($permissions);
        
        return $permissions;
    }

    /**
     * @param $groupName
     * @return PPGroup|null
     */
    public function getGroup($groupName)
    {
        if(!isset($this->groups[$groupName]))
        {
            $this->getLogger()->warning($this->getMessage("logger_messages.getGroup_01", $groupName));

            return null;
        }

        /** @var PPGroup $group */
        $group = $this->groups[$groupName];
            
        if(empty($group->getData()))
        {
            $this->getLogger()->warning($this->getMessage("logger_messages.getGroup_02", $groupName));
            
            return null;
        }
        
        return $group;
    }

    /**
     * @return PPGroup[]
     */
    public function getGroups()
    {
        if($this->isGroupsLoaded != true) throw new \RuntimeException("No groups loaded, maybe a provider error?");

        return $this->groups;
    }

    /**
     * @param $node
     * @param ...$vars
     * @return mixed
     */
    public function getMessage($node, ...$vars)
    {
        return $this->messages->getMessage($node, ...$vars);
    }

    /**
     * @param PPGroup $group
     * @return array
     */
    public function getOnlinePlayersInGroup(PPGroup $group)
    {
        $users = [];

        foreach($this->getServer()->getOnlinePlayers() as $player)
        {
            if($this->getUser($player)->getGroup() === $group) $users[] = $player;
        }

        return $users;
    }

    /**
     * @param IPlayer $player
     * @param $levelName
     * @return array
     */
    public function getPermissions(IPlayer $player, $levelName)
    {
        $user = $this->getUser($player);
        $group = $user->getGroup($levelName);

        return array_merge($group->getGroupPermissions($levelName), $user->getUserPermissions($levelName));
    }

    /**
     * @param $name
     * @return Player
     */
    public function getPlayer($name)
    {
        $player = $this->getServer()->getPlayer($name);
        
        return $player instanceof Player ? $player : $this->getServer()->getOfflinePlayer($name);
    }

    /**
     * @return mixed
     */
    public function getPPVersion()
    {
        $version = $this->getDescription()->getVersion();

        return $version;
    }

    /**
     * @return ProviderInterface
     */
    public function getProvider()
    {
        if(!$this->isValidProvider()) $this->setProvider(false);

        return $this->provider;
    }

    /**
     * @param IPlayer $player
     * @return PPUser
     */
    public function getUser(IPlayer $player)
    {
        return new PPUser($this, $player);
    }

    /**
     * @param PPUser $user
     * @param null $levelName
     * @return PPGroup|null
     */
    public function getUserGroup(PPUser $user, $levelName = null)
    {
        return $user->getGroup($levelName);
    }

    /**
     * @return bool
     */
    public function isValidProvider()
    {
        if(!isset($this->provider) || $this->provider == null || !($this->provider instanceof ProviderInterface)) return false;

        return true;
    }

    public function loadGroups()
    {
        if($this->isValidProvider())
        {
            foreach(array_keys($this->getProvider()->getGroupsData()) as $groupName)
            {
                $this->groups[$groupName] = new PPGroup($this, $groupName);
            }

            $this->isGroupsLoaded = true;
            
            $this->sortGroupPermissions();
        }
    }

    public function registerAllPlayers()
    {
        foreach($this->getServer()->getOnlinePlayers() as $player)
        {
            $this->registerPlayer($player);
        }
    }

    /**
     * @param Player $player
     */
    public function registerPlayer(Player $player)
    {
        $this->getLogger()->debug($this->getMessage("logger_messages.registerPlayer", $player->getName()));

        $uniqueId = $player->getUniqueId()->toString();

        if(isset($this->attachments[$uniqueId])) $this->unregisterPlayer($player);

        $attachment = $player->addAttachment($this);

        $this->attachments[$uniqueId] = $attachment;

        $this->updatePermissions($player);
    }

    public function reload()
    {
        $this->reloadConfig();
        $this->saveDefaultConfig();
        
        $this->messages->reloadMessages();

        if(!$this->isValidProvider()) $this->setProvider(false);

        $this->provider->init();

        $this->updateAllPlayers();
    }

    /**
     * @param $groupName
     * @return bool
     */
    public function removeGroup($groupName)
    {
        $groupsData = $this->getProvider()->getGroupsData(true);
        
        if(!isset($groupsData[$groupName])) return false;
        
        unset($groupsData[$groupName]);
        
        $this->getProvider()->setGroupsData($groupsData);
        
        return true;
    }

    /**
     * @param PPGroup $group
     */
    public function setDefaultGroup(PPGroup $group)
    {
        foreach($this->getGroups() as $currentGroup)
        {
            $isDefault = $currentGroup->getNode("isDefault");
            
            if($isDefault) $currentGroup->removeNode("isDefault");
        }
        
        $group->setDefault();
    }

    /**
     * @param IPlayer $player
     * @param PPGroup $group
     * @param null $levelName
     */
    public function setGroup(IPlayer $player, PPGroup $group, $levelName = null)
    {
        $this->getUser($player)->setGroup($group, $levelName);
    }
    
    public function sortGroupPermissions()
    {
        foreach($this->getGroups() as $groupName => $ppGroup)
        {
            $ppGroup->sortPermissions();
        }
    }
    
    public function updateAllPlayers()
    {
        foreach($this->getServer()->getOnlinePlayers() as $player)
        {
            $this->updatePermissions($player);

            if($this->getConfigValue("enable-multiworld-perms") == true)
            {
                foreach($this->getServer()->getLevels() as $level)
                {
                    $levelName = $level->getName();

                    $this->updatePermissions($player, $levelName);
                }
            }
        }
    }

    /**
     * @param PPGroup $group
     * @experimental #64FF00
     */
    public function updatePlayersInGroup(PPGroup $group)
    {
        foreach($this->getOnlinePlayersInGroup($group) as $player)
        {
            $this->updatePermissions($player);

            if($this->getConfigValue("enable-multiworld-perms") == true)
            {
                foreach($this->getServer()->getLevels() as $level)
                {
                    $levelName = $level->getName();

                    $this->updatePermissions($player, $levelName);
                }
            }
        }
    }

    /**
     * @param IPlayer $player
     */
    public function updatePermissions(IPlayer $player)
    {
        if($player instanceof Player)
        {
            $levelName = $this->getConfigValue("enable-multiworld-perms") ? $player->getLevel()->getName() : null;

            $permissions = [];

            foreach($this->getPermissions($player, $levelName) as $permission)
            {
                if($permission === "*")
                {
                    foreach($this->getServer()->getPluginManager()->getPermissions() as $tmp)
                    {
                        $permissions[$tmp->getName()] = true;
                    }
                }
                else
                {
                    $isNegative = substr($permission, 0, 1) === "-";
                    if($isNegative) $permission = substr($permission, 1);

                    $value = !$isNegative;
                    if($permission === self::CORE_PERM) $value = true;

                    $permissions[$permission] = $value;
                }
            }

            /** @var PermissionAttachment $attachment */
            $attachment = $this->getAttachment($player);

            $attachment->clearPermissions();

            $attachment->setPermissions($permissions);
        }
    }

    public function unregisterAllPlayers()
    {
        foreach($this->getServer()->getOnlinePlayers() as $player)
        {
            $this->unregisterPlayer($player);
        }
    }

    /**
     * @param Player $player
     */
    public function unregisterPlayer(Player $player)
    {
        $this->getLogger()->debug($this->getMessage("logger_messages.unregisterPlayer", $player->getName()));

        $uniqueId = $player->getUniqueId()->toString();

        if(isset($this->attachments[$uniqueId])) $player->removeAttachment($this->attachments[$uniqueId]);

        unset($this->attachments[$uniqueId]);
    }
}

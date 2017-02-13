<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;


Loc::loadMessages(__FILE__);

class modulpos_softcheck extends CModule
{
    public function __construct()
    {
        $arModuleVersion = array();
        
        include __DIR__ . '/version.php';

        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion))
        {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }
        
        $this->MODULE_ID = 'modulpos.softcheck';
        $this->MODULE_NAME = Loc::getMessage('MODULPOS_SC_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('MODULPOS_SC_MODULE_DESCRIPTION');
        $this->MODULE_GROUP_RIGHTS = 'N';
        $this->PARTNER_NAME = Loc::getMessage('MODULPOS_SC_MODULE_PARTNER_NAME');
        $this->PARTNER_URI = 'http://modulpos.ru';
    }

    public function doInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        RegisterModuleDependences('sale', 'OnSaleOrderCanceled', $this->MODULE_ID, '\Modulpos\SoftCheck\OrderStatusHandlers', 'OnCancel');
        RegisterModuleDependences('sale', 'OnSaleOrderSaved', $this->MODULE_ID, '\Modulpos\SoftCheck\OrderStatusHandlers', 'OnSave');
    }

    public function doUninstall()
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);
        UnRegisterModuleDependences('sale', 'OnSaleOrderCanceled', $this->MODULE_ID, '\Modulpos\SoftCheck\OrderStatusHandlers', 'OnCancel');
        UnRegisterModuleDependences('sale', 'OnSaleOrderSaved', $this->MODULE_ID, '\Modulpos\SoftCheck\OrderStatusHandlers', 'OnSave');
    }
}

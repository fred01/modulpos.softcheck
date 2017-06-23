<?php

defined('MODUL_SOFT_CHECK_MODULE_NAME') or define('MODUL_SOFT_CHECK_MODULE_NAME', 'modulpos.softcheck');
CModule::IncludeModule(MODUL_SOFT_CHECK_MODULE_NAME);
CModule::IncludeModule("sale");


use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Text\HtmlFilter;
use Modulpos\SoftCheck\ModulPOSClient;
use Bitrix\Sale\PaySystem\Manager;
use Bitrix\Sale\Delivery\Services\Manager as DeliveryManager;

if (!$USER->isAdmin()) {
    $APPLICATION->authForm('Nope');
}

$app = Application::getInstance();
$context = $app->getContext();
$request = $context->getRequest();

Loc::loadMessages($context->getServer()->getDocumentRoot()."/bitrix/modules/main/options.php");
Loc::loadMessages(__FILE__);

$tabControl = new CAdminTabControl("tabControl", array(
    array(
        "DIV" => "edit1",
        "TAB" => Loc::getMessage("MAIN_TAB_SET"),
        "TITLE" => Loc::getMessage("MAIN_TAB_TITLE_SET"),
    ),
));

if ((!empty($save) || !empty($restore)) && $request->isPost() && check_bitrix_sessid()) {
    if (!empty($restore)) {
        Option::delete(MODUL_SOFT_CHECK_MODULE_NAME, "login");
        Option::delete(MODUL_SOFT_CHECK_MODULE_NAME, "password");
        Option::delete(MODUL_SOFT_CHECK_MODULE_NAME, "retailpoint_info");
        CAdminMessage::showMessage(array(
            "MESSAGE" => "Связь с розничной точкой удалена",
            "TYPE" => "OK"
        ));
    } else {
        if ($request->getPost('login')) {
            $login = $request->getPost('login');
            $password = $request->getPost('password');
            $retailpoint_id = $request->getPost('retailpoint_id');
            $retailpoint = ModulPOSClient::getRetailPoint($login, $password, $retailpoint_id);
            if ($retailpoint) {
                $retailpoint_info = $retailpoint['name'].' '.$retailpoint['address'];
                Option::set(MODUL_SOFT_CHECK_MODULE_NAME, "login", $login);
                Option::set(MODUL_SOFT_CHECK_MODULE_NAME, "password", $password);
                Option::set(MODUL_SOFT_CHECK_MODULE_NAME, "retailpoint_id", $retailpoint_id);
                Option::set(MODUL_SOFT_CHECK_MODULE_NAME, "retailpoint_info", $retailpoint_info);
                CAdminMessage::showMessage(array("MESSAGE" => "Успешно связяно с розничной точкой: $retailpoint_info" , "TYPE" => "OK"));
            } else {
                CAdminMessage::showMessage("Ошибка связи с сервисом МодульКасса. Проверьте корректность введенных данных");
                ModulPOSClient::log("Ошибка связи с сервисом МодульКасса. Проверьте корректность введенных данных");
            }
        }

        Option::set(MODUL_SOFT_CHECK_MODULE_NAME, "paymentSystemIds", implode(',', $request->getPost('payment')));
        Option::set(MODUL_SOFT_CHECK_MODULE_NAME, "deliverySystemIds", implode(',', $request->getPost('delivery')));
    }
}

$tabControl->begin();
?>

<form method="post" action="<?=sprintf('%s?mid=%s&lang=%s', $request->getRequestedPage(), urlencode($mid), LANGUAGE_ID)?>">
    <?php
    echo bitrix_sessid_post();
    $tabControl->beginNextTab();
    ?>
    <?php
        $retailpoint_info = Option::get(MODUL_SOFT_CHECK_MODULE_NAME, "retailpoint_info", '');
        if ($retailpoint_info !== ''):
    ?>
            <tr>
                <td width="40%">
                    <label for="login">Связано с розничной точкой <?=$retailpoint_info?></label>
                <td width="60%">
                    <input type="submit"
                           name="restore"
                           value="Удалить связь"
                           title="Удалить связь"
                           class="adm-btn-save"
                    />

                </td>
            </tr>

    <? else: ?>
    <tr>
        <td width="40%">
            <label for="login"><?=Loc::getMessage("REFERENCES_MODULPOS_LOGIN") ?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   maxlength="50"
                   name="login"
                   value="<?=HtmlFilter::encode(Option::get(MODUL_SOFT_CHECK_MODULE_NAME, "login", ''));?>"
                   />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="password"><?=Loc::getMessage("REFERENCES_MODULPOS_PASS") ?>:</label>
        <td width="60%">
            <input type="password"
                   size="50"
                   maxlength="50"
                   name="password"
                   value="<?=HtmlFilter::encode(Option::get(MODUL_SOFT_CHECK_MODULE_NAME, "password", ''));?>"
                   />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="retailpoint_id"><?=Loc::getMessage("REFERENCES_MODULPOS_RETAILPOINT_ID") ?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   maxlength="50"
                   name="retailpoint_id"
                   value="<?=HtmlFilter::encode(Option::get(MODUL_SOFT_CHECK_MODULE_NAME, 'retailpoint_id', ''));?>"
                   />
        </td>
    </tr>
    <? endif; ?>
    <tr>
        <td>Отметье платежные системы,<br> при оплате которыми <br>заказы будут передаваться в МодульКассу</td>
        <td>
            <?php
                $paymentSystemOption = Option::get(MODUL_SOFT_CHECK_MODULE_NAME, 'paymentSystemIds', '1');
                $paymentSystemIds = explode(',', $paymentSystemOption);
                $paymentSystems = Manager::getList();
                foreach ($paymentSystems as $paymentSystem):?>
                    <input <?=(in_array($paymentSystem['ID'], $paymentSystemIds)?"checked=\"checked\"":"")?>  name="payment[]" value="<?=$paymentSystem['ID']?>" type="checkbox"/><?=$paymentSystem['NAME']?><br>
            <?php endforeach; ?>
        </td>
    </tr>

    <tr>
        <td>Отметье службы,<br> при доставке которыми <br>заказы будут передаваться в МодульКассу</td>
        <td>
            <?php
            $delivetySystems = DeliveryManager::getActiveList();
            $deliverySystemOption = Option::get(MODUL_SOFT_CHECK_MODULE_NAME, 'deliverySystemIds', '2');
            $deliverySystemIds = explode(',', $deliverySystemOption);

            foreach ($delivetySystems as $delivetySystem):?>
                <input <?=(in_array($delivetySystem['ID'], $deliverySystemIds)?"checked=\"checked\"":"")?> name="delivery[]" value="<?=$delivetySystem['ID']?>" type="checkbox"/><?=$delivetySystem['NAME']?><br>
            <?php endforeach; ?>
        </td>
    </tr>

    <?php
    $tabControl->buttons();
    ?>
    <input type="submit"
           name="save"
           value="<?=Loc::getMessage("MAIN_SAVE") ?>"
           title="<?=Loc::getMessage("MAIN_OPT_SAVE_TITLE") ?>"
           class="adm-btn-save"
           />
    <?php
    $tabControl->end();
    ?>
</form>

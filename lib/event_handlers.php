<?php
namespace Modulpos\SoftCheck;
defined('MODUL_SOFT_CHECK_MODULE_NAME') or define('MODUL_SOFT_CHECK_MODULE_NAME', 'modulpos.softcheck');

use Modulpos\SoftCheck\ModulPOSClient;
use Bitrix\Main\Config\Option;


class OrderStatusHandlers {

	public static function OnSave(\Bitrix\Main\Event $event) {
        $paymentSystemOption = Option::get(MODUL_SOFT_CHECK_MODULE_NAME, 'paymentSystemIds', '1');
        $paymentSystemIds = explode(',', $paymentSystemOption);

        $deliverySystemOption = Option::get(MODUL_SOFT_CHECK_MODULE_NAME, 'deliverySystemIds', '2');
        $deliverySystemIds = explode(',', $deliverySystemOption);

        $neededOrderStatusOption = Option::get(MODUL_SOFT_CHECK_MODULE_NAME, 'orderStatus', '');

        ModulPOSClient::log("MODULE_NAME = ".MODUL_SOFT_CHECK_MODULE_NAME);
        ModulPOSClient::log("paymentSystemOption = ".$paymentSystemOption);
        ModulPOSClient::log("deliverySystemOption = ".$deliverySystemOption);
        ModulPOSClient::log("neededOrderStatusOption = ".$neededOrderStatusOption);


	    $order = $event->getParameter("ENTITY");
 	    $old_values = $event->getParameter("VALUES");
        $orderNo = $order->getField('ACCOUNT_NUMBER');
        $is_new_order = $event->getParameter("IS_NEW");

        $oldStatus = $old_values["STATUS_ID"];
        $currentStatus = $order->getField('STATUS_ID');

        ModulPOSClient::log("is_new = ".$is_new_order);
        ModulPOSClient::log("oldStatus = ".$oldStatus);
        ModulPOSClient::log("currentStatus = ".$currentStatus);

        if (($is_new_order === TRUE and $neededOrderStatusOption === '')
            or
            ($is_new_order === TRUE
                and $neededOrderStatusOption === $currentStatus)
            or ($is_new_order === FALSE
                and $oldStatus !== $currentStatus
                and $neededOrderStatusOption === $currentStatus
                and !ModulPOSClient::existsExternalDoc($order))) {

            $orderPaymentIds = array();
            $orderDeliveryIds = array();

            foreach ($order->getPaymentCollection() as $paymentCollectionItem) {
                $orderPaymentIds[] = $paymentCollectionItem->getPaySystem()->getField('ID');
            }
            foreach ($order->getShipmentCollection() as $shipmentCollectionItem) {
                $orderDeliveryIds[] = $shipmentCollectionItem->getDelivery()->getId();
            }

            $requiredPaymentExists = count(array_intersect($orderPaymentIds, $paymentSystemIds)) > 0;
            $requiredDeliveryExists = count(array_intersect($orderDeliveryIds, $deliverySystemIds)) > 0;

            $orderShouldBeExternalized = $requiredDeliveryExists && $requiredPaymentExists;

            if ($orderShouldBeExternalized) {
                ModulPOSClient::log("Found required payment and shipment. Creating external document...");
                $result = ModulPOSClient::createExternalDocument($order);
                if ($result) {
                    ModulPOSClient::log("external document created");
                } else {
                    ModulPOSClient::log("Error creating external document");
                }
            } else {
                ModulPOSClient::log("Order $orderNo is new, but not meet externalize conditions:");
                ModulPOSClient::log("  Order paymentIds: ".var_export($orderPaymentIds, TRUE)."externalize if ".var_export($paymentSystemIds, TRUE)." should be externalized: ". ($requiredPaymentExists?"TRUE":"FALSE"));
                ModulPOSClient::log("  Order deliveryIds: ".var_export($orderDeliveryIds, TRUE)."externalize if ".var_export($deliverySystemIds, TRUE)." should be externalized: ". ($requiredDeliveryExists?"TRUE":"FALSE"));
            }
        } else {
            ModulPOSClient::log("Order $orderNo not new or status is incorrect or order is exists, skipping");
        }
	}
	
	public static function OnCancel(\Bitrix\Main\Event $event) {
// Not implemented yet.
//		$order = $event->getParameter("ENTITY");
//		$orderNo = $order->getField('ACCOUNT_NUMBER');
//		ModulPOSClient::log("Canceling document: $orderNo");
//		ModulPOSClient::deleteExternalDocument($orderNo);
//		ModulPOSClient::log("OnCancel event completed");
	}
}
?>
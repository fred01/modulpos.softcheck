<?php
namespace Modulpos\SoftCheck;

use Modulpos\SoftCheck\ModulPOSClient;

class OrderStatusHandlers {
    const externalizeIfPaymentId = array('1'); //// ID of 'paynment by cash to courier'. TODO Make confugurable
	const externalizeIfShipmentId = array('2'); //// ID of 'shipment by courier'. TODO Make confugurable

	public static function OnSave(\Bitrix\Main\Event $event) {
	    $order = $event->getParameter("ENTITY");
 	    $old_values = $event->getParameter("VALUES");
        $orderNo = $order->getField('ACCOUNT_NUMBER');
        $is_new_order = $event->getParameter("IS_NEW");

        if ($is_new_order === TRUE) {

            $orderPaymentIds = array();
            $orderDeliveryIds = array();

            foreach ($order->getPaymentCollection() as $paymentCollectionItem) {
                $orderPaymentIds[] = $paymentCollectionItem->getPaySystem()->getField('ID');
            }
            foreach ($order->getShipmentCollection() as $shipmentCollectionItem) {
                $orderDeliveryIds[] = $shipmentCollectionItem->getDelivery()->getId();
            }

            $requiredPaymentExists = count(array_intersect($orderPaymentIds, static::externalizeIfPaymentId)) > 0;
            $requiredDeliveryExists = count(array_intersect($orderDeliveryIds, static::externalizeIfShipmentId)) > 0;

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
                ModulPOSClient::log("  Order paymentIds: ".var_export($orderPaymentIds, TRUE)."externalize if ".var_export(static::externalizeIfPaymentId, TRUE)." should be externalized: $requiredPaymentExists");
                ModulPOSClient::log("  Order deliveryIds: ".var_export($orderDeliveryIds, TRUE)."externalize if ".var_export(static::externalizeIfShipmentId, TRUE)." should be externalized: $requiredDeliveryExists");
            }
        } else {
            ModulPOSClient::log("Order $orderNo not new, skipping");
        }
	}
	
	public static function OnCancel(\Bitrix\Main\Event $event) {
		$order = $event->getParameter("ENTITY");
		$orderNo = $order->getField('ACCOUNT_NUMBER');
		ModulPOSClient::log("Canceling document: $orderNo");
		ModulPOSClient::deleteExternalDocument($orderNo);
		ModulPOSClient::log("OnCancel event completed");
	}
}
?>
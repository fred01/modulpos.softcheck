<?php
namespace Modulpos\SoftCheck;

use Modulpos\SoftCheck\ModulPOSClient;

class OrderStatusHandlers {
	const externalizeIfShipmentId = '2'; //// ID of 'shipment by courier'. TODO Make confugurable
	const externalizeIfPaymentId = '1'; //// ID of 'paynment by cash to courier'. TODO Make confugurable

	public static function OnSave(\Bitrix\Main\Event $event) {
	    $order = $event->getParameter("ENTITY");
 	    $old_values = $event->getParameter("VALUES");
        $is_new_order = $event->getParameter("IS_NEW");

		$orderNo = $order->getField('ACCOUNT_NUMBER');
		$requiredDeliveryExists = FALSE;
		foreach ($order->getShipmentCollection() as $shipmentCollectionItem) {			
			$requiredDeliveryExists = $requiredDeliveryExists || $shipmentCollectionItem->getDelivery()->getId() == static::externalizeIfShipmentId;
		}
		$requiredPaymentExists = FALSE;
		foreach ($order->getPaymentCollection() as $paymentCollectionItem) {
			$requiredPaymentExists = $requiredPaymentExists || $paymentCollectionItem->getPaySystem()->getField('ID') == static::externalizeIfPaymentId;  
		}
		if ($is_new_order === TRUE && $requiredDeliveryExists && $requiredPaymentExists) {
			ModulPOSClient::log("Found required payment and shipment. Creating external document...");
			$result = ModulPOSClient::createExternalDocument($order);
			if ($result) {
				ModulPOSClient::log("external document created");
			} else {
				ModulPOSClient::log("Error creating external document");
			}
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
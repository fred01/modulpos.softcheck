<?php
namespace Modulpos\SoftCheck;

use Modulpos\SoftCheck\ModulPOSClient;

class OrderStatusHandlers {
	public static function OnSave($order, $is_new_order, $old_values) {
		$is_new = var_export($is_new_order, TRUE);
		$changed_values = var_export($old_values, TRUE);
		ModulPOSClient::log("OnSave event occured.\nis new order = $is_new\n$changed_values");
		$orderNo = $order->getField('ACCOUNT_NUMBER');
		ModulPOSClient::log(var_export($order, true), "/tmp/order_$orderNo.log");
		$requiredDeliveryExists = FALSE;
		foreach ($order->getShipmentCollection() as $shipmentCollectionItem) {
			ModulPOSClient::log("Check shipment: ".$shipmentCollectionItem->getDelivery()->getName());
			$requiredDeliveryExists = $requiredDeliveryExists || $shipmentCollectionItem->getDelivery()->getId() == 2;  // Shipment by courier. TODO Make confugurable
		}
		$requiredPaymentExists = FALSE;
		foreach ($order->getPaymentCollection() as $paymentCollectionItem) {
			ModulPOSClient::log("Check payment, payment system ID:".$paymentCollectionItem->getPaySystem()->getField('ID').' Name '.$paymentCollectionItem->getPaySystem()->getField('NAME'));
			$requiredPaymentExists = $requiredPaymentExists || $paymentCollectionItem->getPaySystem()->getField('ID') == 1;  // Paynment by cash to courier. . TODO Make confugurable
		}
		ModulPOSClient::log("Is new order:".$is_new);
		ModulPOSClient::log("Is required delivery exists:".var_export($requiredDeliveryExists, TRUE));
		ModulPOSClient::log("Is required delivery exists:".var_export($requiredPaymentExists, TRUE));
		if ($is_new_order === TRUE && $requiredDeliveryExists && $requiredPaymentExists) {
			ModulPOSClient::log("Order meet all criteria, externalize it");
			ModulPOSClient::createExternalDocument($order);
			ModulPOSClient::log("external document created");
		}
	}
	
	public static function OnCancel($order) {
		ModulPOSClient::log("OnCancel event occured");
		$orderNo = $order->getField('ACCOUNT_NUMBER');
		ModulPOSClient::log("Canceling document: $orderNo");
		ModulPOSClient::deleteExternalDocument($orderNo);
		ModulPOSClient::log("OnCancel event completed");
	}
}
?>
<?php
namespace Modulpos\SoftCheck;

use Bitrix\Main\Config\Option;

defined('ADMIN_MODULE_NAME') or define('ADMIN_MODULE_NAME', 'modulpos.softcheck');

class ModulPOSClient {
	const MP_BASE_URL = 'https://service.modulpos.ru/api';
	
	public static function log($log_entry, $log_file="/var/log/php/modulpos.softcheck.log") {
        // Uncomment this line to enable debuging of module 
		@file_put_contents($log_file, "\n".date('Y-m-d H:i:sP').' : '.$log_entry, FILE_APPEND);
	}
	
	private static function sendHttpRequest($url, $method, $auth_data, $data = '') {
		$encoded_auth =  base64_encode($auth_data['username'].':'.$auth_data['password']);
		
		ModulPOSClient::log("sendHttpRequest called:".$url.','.$method.','.$encoded_auth);
		$headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic '.$encoded_auth
        );
		
		if ($method == 'POST' && $data != '') {
			$headers['Content-Length'] = mb_strlen($data, '8bit');
		}
		
		$headers_string = '';
		foreach ($headers as $key => $value) {
			$headers_string .= $key.': '.$value."\r\n";
		}
		$options = array(
			'http' => array(
				'header' => $headers_string,
				'method' => $method                
			),
			'https' => array(
				'header' => $headers_string,
				'method' => $method                
			)
		);
		
		if ($method == 'POST' && $data != '') {
			$options['http']['content'] = $data;
		}
		$context  = stream_context_create($options);
		static::log("Request: ".$method.' '.$url."\n$headers_string\n".$data);
		$response = file_get_contents(static::MP_BASE_URL.$url, FALSE, $context);
		if ($response === FALSE) {
			static::log("Error:".var_export(error_get_last(), TRUE));
			return FALSE;
		}
		static::log("\nResponse:\n".var_export($response, TRUE));
		return json_decode($response, TRUE);
	}
	
	
	public static function generateDocumentId() {
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
				          mt_rand(0, 0xffff), mt_rand(0, 0xffff),
				          mt_rand(0, 0xffff),
				          mt_rand(0, 0x0fff) | 0x4000,
				          mt_rand(0, 0x3fff) | 0x8000,
				          mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
				          );
	}
	
	
	private static function createCashDocument($order) {
		$document = array(
			'id' => static::generateDocumentId(),
			'docNum' => $order->getField('ACCOUNT_NUMBER'),
			'docType' => 'SALE',
			'status' => 'OPENED',
			'beginDateTime' => $order->getField('DATE_UPDATE')->format(DATE_ATOM),
       );
		
		$orderSum = 0.0;
		$inventPositions = array();
		foreach ($order->getBasket() as $basketItem) {
			$inventPositions[] = static::createItemPosition($basketItem);
			$orderSum += $basketItem->getFinalPrice();
		}

		foreach ($order->getShipmentCollection() as $shipment) {
		    if (!$shipment->isSystem()) {
                $deliveryPrice = $shipment->getField('PRICE_DELIVERY');
                if ($deliveryPrice != 0) {
                    $inventPositions[] = static::createDeliveryItem($deliveryPrice);
                    $orderSum += $deliveryPrice;
                }
            }
        }

		$document['inventPositions'] = $inventPositions;
		$document['baseSum'] = $orderSum;
		$document['actualSum'] = $orderSum;

		$moneyPositions = array();
		foreach ($order->getPaymentCollection() as $paymentItem) {
			$moneyPositions[] = static::createMoneyPosition($paymentItem);
		}
		$document['moneyPositions'] = $moneyPositions;
		return $document;
	}
	
	private static function createItemPosition($basketItem) {
		$position = array(            
            'inventCode' => $basketItem->getProductId(),
            'name' => $basketItem->getField('NAME'),
            'quantity' => $basketItem->getQuantity(),
            'price' => $basketItem->getPrice(),
            'posSum' => $basketItem->getFinalPrice(),
            'description' => '',
            'measure' => 'pcs',
			'type' => 'MAIN',
            'vatTag' => 0, // TODO Need to map bitrix VAT values to VAT tags
            'id' => static::generateDocumentId() // system field
        );
		return $position;
	}

	private static function createDeliveryItem($deliveryPrice) {
        $position = array(
            'inventCode' => NULL,
            'name' => "Доставка",
            'quantity' => 1,
            'price' => $deliveryPrice,
            'posSum' => $deliveryPrice,
            'description' => "Доставка",
            'measure' => 'pcs',
            'type' => 'MAIN',
            'vatTag' => 0, // TODO Need to map bitrix VAT values to VAT tags
            'id' => static::generateDocumentId() // system field
        );
        return $position;

    }

	private static function createMoneyPosition($paymentItem) {
		$position = array(
            'paymentType' => 'CASH', // Always cash, because we send only orders payed by cash
            'sum' => $paymentItem->getSum()
        );
        return $position;
    }
    public static function createExternalDocument($order) {
        $login =  Option::get(ADMIN_MODULE_NAME, 'login', '#empty#');
        if ($login == '#empty#') {
            ModulPOSClient::log("modulpos.softcheck module not configured properly. Set login, password and retail point id in module settings");
            return; // TODO: Show warning "Modulpos not configured properly"
        }
        $password =  Option::get(ADMIN_MODULE_NAME, 'password', '');
        $retailpoint_id = Option::get(ADMIN_MODULE_NAME, 'retailpoint_id', '');
        $document = static::createCashDocument($order);
        $json_encode_option = 0;
        if (PHP_VERSION_ID >= 50400) {
            $json_encode_option = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
        }
        $document_as_json = json_encode($document, $json_encode_option);
        $credentials = array('username'=>$login, 'password' => $password );
        $response = static::sendHttpRequest("/v1/retail-point/$retailpoint_id/shift/:external/cashdoc", 'POST', $credentials, $document_as_json);
        if ($response === FALSE) {        
            // TODO Show warning message "Unable to create external doc:".error_get_last(). Now just return FALSE
			return FALSE;
        }
		return TRUE;
    }
}
?>
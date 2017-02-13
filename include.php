<?php
CModule::IncludeModule("modulpos.softcheck");

$includeClasses = array(
  'Modulpos\SoftCheck\OrderStatusHandlers'=>'lib/event_handlers.php',
  'Modulpos\SoftCheck\ModulPOSClient'=>'lib/modulpos_client.php',
);

CModule::AddAutoloadClasses("modulpos.softcheck", $includeClasses);

?>
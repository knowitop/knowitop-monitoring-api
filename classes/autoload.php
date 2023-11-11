<?php
/**
 * @author Vladimir Kunin <we@knowitop.ru>
 */

spl_autoload_register(function ($sClass) {
	$sNameSpacePrefix = 'Knowitop\\iTop\\MonitoringAPI\\';

	if (strpos($sClass, $sNameSpacePrefix) === 0)
	{
		$sFileName = substr($sClass, strlen($sNameSpacePrefix)).'.php';
		require_once $sFileName;

		return true;
	}

	return false;
});
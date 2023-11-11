<?php
/**
 * @author Vladimir Kunin <we@knowitop.ru>
 */

namespace Knowitop\iTop\MonitoringAPI;

use MetaModel;
use RestUtils;
use Ticket;

class NotExistStateExecutor extends TicketStateExecutor
{
	public function Create(array $aFields, bool $bDoNotWrite = false): bool
	{
		$aDefaults = array();
		$oContext = $this->GetContext();
		$sTicketClass = $oContext->GetTicketClass();
		if ($sTicketClass === 'UserRequest')
		{
			$oReqTypeAttrDef = MetaModel::GetAttributeDef($sTicketClass, 'request_type');
			if (array_key_exists('incident', $oReqTypeAttrDef->GetAllowedValues()))
			{
				$aDefaults['request_type'] = 'incident';
			}
		}
		$oOriginAttrDef = MetaModel::GetAttributeDef($sTicketClass, 'origin');
		if (array_key_exists('monitoring', $oOriginAttrDef->GetAllowedValues()))
		{
			$aDefaults['origin'] = 'monitoring';
		}
		if ($oCI = $oContext->GetConfigItem())
		{
			$aDefaults['functionalcis_list'] = array(array('functionalci_id' => $oCI->GetKey()));
		}
		$aFields = array_merge($aDefaults, $aFields);
		/** @var Ticket $oTicket */
		$oTicket = RestUtils::MakeObjectFromFields($sTicketClass, $oContext->PrepareFields($aFields));
		if (!$bDoNotWrite)
		{
			$oTicket->DBInsert();
		}
		$oContext->SetTicket($oTicket);

		return true;
	}

	public function Update(array $aFields, bool $bDoNotWrite = false): bool
	{
		return false;
	}

	public function Assign(array $aFields, bool $bDoNotWrite = false): bool
	{
		return false;
	}

	public function Resolve(array $aFields, bool $bDoNotWrite = false): bool
	{
		return false;
	}

	public function Reopen(array $aFields, bool $bDoNotWrite = false): bool
	{
		return false;
	}
}
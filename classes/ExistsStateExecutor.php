<?php
/**
 * @author Vladimir Kunin <we@knowitop.ru>
 */

namespace Knowitop\iTop\MonitoringAPI;

use RestUtils;
use Ticket;

class ExistsStateExecutor extends TicketStateExecutor
{
	public function Create(array $aFields, bool $bDoNotWrite = false): bool
	{
		return false;
	}

	public function Update(array $aFields, bool $bDoNotWrite = false): bool
	{
		$oContext = $this->GetContext();
		/** @var Ticket $oTicket */
		$oTicket = RestUtils::UpdateObjectFromFields($oContext->GetTicket(), $oContext->PrepareFields($aFields));
		if (!$bDoNotWrite)
		{
			$oTicket->DBUpdate();
		}
		$oContext->SetTicket($oTicket);

		return true;
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
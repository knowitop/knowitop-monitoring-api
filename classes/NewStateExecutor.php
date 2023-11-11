<?php
/**
 * @author Vladimir Kunin <we@knowitop.ru>
 */

namespace Knowitop\iTop\MonitoringAPI;

class NewStateExecutor extends ExistsStateExecutor
{
	public function Assign(array $aFields, bool $bDoNotWrite = false): bool
	{
		$this->Update($aFields, true); // update ticket fields inside the context
		$oTicket = $this->GetContext()->GetTicket();
		$oTicket->ApplyStimulus('ev_assign', $bDoNotWrite);
		$this->GetContext()->SetTicket($oTicket);

		return true;
	}

	public function Resolve(array $aFields, bool $bDoNotWrite = false): bool
	{
		// TODO: do auto resolve?
		return false;
	}
}
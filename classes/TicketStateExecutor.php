<?php
/**
 * @author Vladimir Kunin <we@knowitop.ru>
 */

namespace Knowitop\iTop\MonitoringAPI;

abstract class TicketStateExecutor
{
	/**
	 * @var \Knowitop\iTop\MonitoringAPI\MonitoringContext|null
	 */
	private $oContext;

	/**
	 * TicketStateExecutor constructor.
	 *
	 * @param \Knowitop\iTop\MonitoringAPI\MonitoringContext|null $oContext
	 */
	public function __construct(MonitoringContext $oContext)
	{
		$this->oContext = $oContext;
	}

	/**
	 * @return \Knowitop\iTop\MonitoringAPI\MonitoringContext
	 */
	public function GetContext(): MonitoringContext
	{
		return $this->oContext;
	}

	/**
	 * @param \Knowitop\iTop\MonitoringAPI\MonitoringContext $oContext
	 */
	public function SetContext(MonitoringContext $oContext): void
	{
		$this->oContext = $oContext;
	}

	/**
	 * @param array $aFields
	 * @param bool $bDoNotWrite
	 *
	 * @return bool
	 */
	abstract public function Create(array $aFields, bool $bDoNotWrite = false): bool;

	/**
	 * @param array $aFields
	 * @param bool $bDoNotWrite
	 *
	 * @return bool
	 */
	abstract public function Update(array $aFields, bool $bDoNotWrite = false): bool;

	/**
	 * @param array $aFields
	 * @param bool $bDoNotWrite
	 *
	 * @return bool
	 */
	abstract public function Assign(array $aFields, bool $bDoNotWrite = false): bool;

	/**
	 * @param array $aFields
	 * @param bool $bDoNotWrite
	 *
	 * @return bool
	 */
	abstract public function Resolve(array $aFields, bool $bDoNotWrite = false): bool;

	/**
	 * @param array $aFields
	 * @param bool $bDoNotWrite
	 *
	 * @return bool
	 */
	abstract public function Reopen(array $aFields, bool $bDoNotWrite = false): bool;
}
<?php

/**
 * @author Vladimir Kunin <we@knowitop.ru>
 */

namespace Knowitop\iTop\MonitoringAPI;

use DBObject;
use DBObjectSearch;
use DBObjectSet;
use Exception;
use FunctionalCI;
use MetaModel;
use Ticket;
use utils;

class MonitoringContext
{
	const ALARM_STATE_OK = 0;
	const ALARM_STATE_PROBLEM = 1;
	const ALARM_STATE_UNDEFINED = -1;
	/** @var string */
	private $sName;
	/** @var \Knowitop\iTop\MonitoringAPI\TicketStateExecutor */
	private $oStateExecutor;
	/** @var \FunctionalCI */
	private $oConfigItem = null;
	/** @var \Ticket */
	private $oTicket = null;
	/** @var string */
	private $sTicketClass = 'UserRequest';
	/** @var array */
	private $aQueryParams = array();
	/** @var int */
	private $iAlarmState = self::ALARM_STATE_UNDEFINED;
	/** @var array */
	private $aAlarmFields = array();
	/** @var array */
	private $aActionsOnProblem = array();
	/** @var array */
	private $aActionsOnOk = array();
	/** @var array */
	private $aPerformedActions = array();

	public function __construct(string $sName, int $iAlarmState, array $aActionsOnProblem, array $aActionsOnOk, string $sItopClass)
	{
		$this->sName = $sName;
		$this->iAlarmState = $iAlarmState;
		$this->aActionsOnProblem = $aActionsOnProblem;
		$this->aActionsOnOk = $aActionsOnOk;
		$this->oStateExecutor = new NotExistStateExecutor($this);
		if (MetaModel::IsValidClass($sItopClass))
		{
			$this->sTicketClass = $sItopClass;
		}
	}

	/**
	 * @param string $sOql
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MissingQueryArgument
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	public function InitConfigItemFromOql(string $sOql): void
	{
		/** @var \FunctionalCI|null $oConfigItem */
		$oConfigItem = self::GetObjectFromOql('FunctionalCI', $sOql, $this->GetQueryParams());
		if (!is_null($oConfigItem))
		{
			$this->SetConfigItem($oConfigItem);
		}
	}

	/**
	 * @param \FunctionalCI $oConfigItem
	 */
	public function SetConfigItem(FunctionalCI $oConfigItem): void
	{
		$this->oConfigItem = $oConfigItem;
		$this->AddQueryParams($oConfigItem->ToArgs('ci'));
	}

	/**
	 * @return \FunctionalCI
	 */
	public function GetConfigItem(): ?\FunctionalCI
	{
		return $this->oConfigItem;
	}

	/**
	 * @param string $sOql
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MissingQueryArgument
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	public function InitTicketFromOql(string $sOql): void
	{
		/** @var \Ticket|null $oTicket */
		$oTicket = self::GetObjectFromOql($this->sTicketClass, $sOql, $this->GetQueryParams(), array('start_date' => true));
		if (!is_null($oTicket))
		{
			$this->SetTicket($oTicket);
		}
	}

	/**
	 * TODO: грязная функция
	 * По факту $this->oTicket и так обновляется, когда внути StateExecutor'а вызывается oTicket->DBUpdate(),
	 * но нам нужно обновить $this->aQueryParams и вызвать $this->UpdateStateExecutor().
	 *
	 * @param \Ticket $oTicket
	 *
	 * @throws \CoreException
	 */
	public function SetTicket(Ticket $oTicket): void
	{
		$this->oTicket = $oTicket;
		$this->AddQueryParams($oTicket->ToArgs('ticket'));
		$this->UpdateStateExecutor();
	}

	/**
	 * @return \Ticket|null
	 */
	public function GetTicket(): ?\Ticket
	{
		return $this->oTicket;
	}

	/**
	 * @return string
	 */
	public function GetTicketClass(): string
	{
		return $this->sTicketClass;
	}

	/**
	 * @param \Knowitop\iTop\MonitoringAPI\TicketStateExecutor $oExecutor
	 */
	public function SetStateExecutor(TicketStateExecutor $oExecutor): void
	{
		$this->oStateExecutor = $oExecutor;
	}

	/**
	 * @param int $alarmState
	 */
	public function SetAlarmState(int $alarmState): void
	{
		$this->iAlarmState = $alarmState;
	}

	/**
	 * @throws \CoreException
	 */
	public function UpdateStateExecutor()
	{
		if (empty($this->oTicket))
		{
			$this->oStateExecutor = new NotExistStateExecutor($this);

			return;
		}
		$sStatus = $this->oTicket->GetState();
		switch ($sStatus)
		{
			case 'new':
				$this->oStateExecutor = new NewStateExecutor($this);
				break;
			case 'assigned':
				$this->oStateExecutor = new AssignedStateExecutor($this);
				break;
			case 'resolved':
				$this->oStateExecutor = new ResolvedStateExecutor($this);
				break;
			default:
				$this->oStateExecutor = new ExistsStateExecutor($this);
		}
	}

	/**
	 * @param array $aNewParams
	 */
	public function AddQueryParams(array $aNewParams): void
	{
		$this->aQueryParams = array_merge($this->aQueryParams, $aNewParams);
	}

	/**
	 * @return array
	 */
	public function GetQueryParams(): array
	{
		return $this->aQueryParams;
	}

	/**
	 * @return array
	 */
	public function GetAlarmFields(): array
	{
		return $this->aAlarmFields;
	}

	/**
	 * @param array $aAlarmFields
	 */
	public function SetAlarmFields(array $aAlarmFields): void
	{
		$this->aAlarmFields = $aAlarmFields;
	}

	/**
	 * @param array $aFields
	 *
	 * @return array
	 */
	public function PrepareFields(array $aFields): array
	{
		// TODO: merge default_fields <- config_fields <- alarm_fields ?
		// $aFields = array_merge($aFields, $this->aAlarmFields);
		array_walk_recursive($aFields, function (&$item, $key) {
			if (is_string($item))
			{
				$item = MetaModel::ApplyParams($item, $this->GetQueryParams());
			}
		});
		/**
		 * Note: only first level is an array, but included assoc arrays must be stdClass objects:
		 * [
		 *  'title' => 'Ticket title',
		 *  'org_id' => { 'name' => 'demo' },
		 *  'caller_id' => { 'email' => 'we@knowitop.ru', 'status' => 'active' }
		 * ]
		 */
		$aFields = (array)json_decode(json_encode($aFields));

		return $aFields;
	}

	/**
	 * @param string $sClass
	 * @param string $sOql
	 * @param array $aParams
	 *
	 * @param array $aOrder
	 *
	 * @return \DBObject|null
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MissingQueryArgument
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	public static function GetObjectFromOql(string $sClass, string $sOql, array $aParams = array(), array $aOrder = array()): ?DBObject
	{
		$oFilter = DBObjectSearch::FromOQL($sOql, $aParams);
		$sFilterClass = $oFilter->GetClass();
		if (!MetaModel::IsParentClass($sClass, $sFilterClass))
		{
			throw new Exception(utils::GetCurrentModuleName().": OQL query returns '$sFilterClass', but '$sClass' is expected: $sOql.");
		}
		$oSet = new DBObjectSet($oFilter, $aOrder);
		if ($oSet->Count() > 1)
		{
			// todo: log warning, use first
		}

		return $oSet->Fetch();
	}

	/**
	 * @return array
	 */
	public function GetPerformedActions(): array
	{
		return $this->aPerformedActions;
	}

	/**
	 * @param string $sAction
	 * @param array $aParams
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function ExecuteAction(string $sAction, array $aParams): bool
	{
		switch ($sAction)
		{
			case 'create':
			case 'assign':
			case 'resolve':
			case 'reopen':
				if (empty($aParams['fields']) || !is_array($aParams['fields']))
				{
					// $sMsg = "'fields' is missing or empty for '$sAction' action in '$this->sName' config.";
					// throw new Exception(utils::GetCurrentModuleName().": $sMsg");
					$aParams['fields'] = array();
				}

				return call_user_func(array($this->oStateExecutor, ucfirst($sAction)), $aParams['fields']);
				// $this->oStateExecutor->$sAction($aParams['fields']); // Код: говнецом попахивает, это от меня?
				break;
			case 'write_to_log':
				if (empty($aParams['log_att_code']))
				{
					$aParams['log_att_code'] = 'private_log';
				}
				if (empty($aParams['text']))
				{
					$aParams['text'] = '$alarm->message$';
				}

				return $this->oStateExecutor->Update(array($aParams['log_att_code'] => $aParams['text']));
				break;
			default:
				return false;
		}
	}

	/**
	 * @throws \Exception
	 */
	public function Execute()
	{
		// TODO: какая-то внешняя регистрация действий нужна, где-то ближе к конфигу?
		if ($this->iAlarmState === self::ALARM_STATE_PROBLEM)
		{
			$aActionInOrder = array('create', 'assign', 'reopen', 'write_to_log');
			$aActionParams = $this->aActionsOnProblem;
		}
		else if ($this->iAlarmState === self::ALARM_STATE_OK)
		{
			$aActionInOrder = array('resolve', 'write_to_log');
			$aActionParams = $this->aActionsOnOk;
		}
		else
		{
			//todo: log error – alarm state undefined
			throw new Exception('undefined alarm state: '.$this->iAlarmState);
		}
		foreach ($aActionInOrder as $sAction)
		{
			if (array_key_exists($sAction, $aActionParams) || in_array($sAction, $aActionParams))
			{
				$aParams = isset($aActionParams[$sAction]) ? $aActionParams[$sAction] : array();
				if ($this->ExecuteAction($sAction, $aParams))
				{
					$this->aPerformedActions[] = $sAction;
				}
			}
		}
	}
}

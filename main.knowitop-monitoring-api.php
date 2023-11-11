<?php
/**
 * @author Vladimir Kunin <we@knowitop.ru>
 */

use Knowitop\iTop\MonitoringAPI\MonitoringContext;

require_once('classes/autoload.php');

/**
 * Class MonitoringServices
 */
class MonitoringServices implements iRestServiceProvider
{
	/**
	 * @param string $sVersion
	 *
	 * @return array
	 */
	public function ListOperations($sVersion)
	{
		$aOperations = array();
		$aOperations[] = array(
			'verb' => 'monitoring/alarm',
			'description' => 'Handle an alarm state change',
		);

		return $aOperations;
	}

	/**
	 * @param string $sVersion
	 * @param string $sVerb
	 * @param \stdClass $oParams
	 *
	 * @return \RestResult|\RestResultWithObjects
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MissingQueryArgument
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	public function ExecOperation($sVersion, $sVerb, $oParams)
	{
		// $alarm->state$, $alarm->message$, $alarm->ci_key$
		// $ci->att_code$
		// $ticket->att_code$

		$oResult = new RestResultWithObjects();
		switch ($sVerb)
		{
			case 'monitoring/alarm':
				RestUtils::InitTrackingComment($oParams);
				$sContextName = RestUtils::GetMandatoryParam($oParams, 'context');
				$sCIKey = RestUtils::GetMandatoryParam($oParams, 'ci_key');
				$iAlarmState = (int)RestUtils::GetMandatoryParam($oParams, 'state');
				$sAlarmMessage = RestUtils::GetMandatoryParam($oParams, 'message');
				// $aAlarmFields = (array)RestUtils::GetOptionalParam($oParams, 'fields', array());

				$aContextConfig = utils::GetCurrentModuleSetting($sContextName, array());
				// $aContextConfig = $aConfig[$sContextName];
				if (empty($aContextConfig) || !is_array($aContextConfig))
				{
					throw new Exception(utils::GetCurrentModuleName().": '$sContextName' context not found in the module config.");
				}
				if (empty($aContextConfig['actions_on_problem']) || empty($aContextConfig['actions_on_ok']))
				{
					throw new Exception(utils::GetCurrentModuleName().": 'actions_on_problem' or 'actions_on_ok' is missing or empty in the module config.");
				}
				if (!empty($aContextConfig['ci_key_regexp']))
				{
					if (preg_match($aContextConfig['ci_key_regexp'], $sCIKey, $aMatches))
					{
						$sCIKey = $aMatches[1];
					}
					else
					{
						// todo: log warning/error
					}
				};

				$oMonitoringContext = new MonitoringContext($sContextName, $iAlarmState, $aContextConfig['actions_on_problem'],
					$aContextConfig['actions_on_ok']);
				$oMonitoringContext->AddQueryParams(array(
					'alarm->ci_key' => $sCIKey,
					'alarm->state' => $iAlarmState,
					'alarm->message' => $sAlarmMessage,
				));
				// $oMonitoringContext->SetAlarmFields($aAlarmFields);
				if (!empty($aContextConfig['ci_oql']))
				{
					$oMonitoringContext->InitConfigItemFromOql($aContextConfig['ci_oql']);
				}
				if (!empty($aContextConfig['ticket_oql']))
				{
					$oMonitoringContext->InitTicketFromOql($aContextConfig['ticket_oql']);
				}

				$sTicketClass = $oMonitoringContext->GetTicketClass();
				if (UserRights::IsActionAllowed($sTicketClass, UR_ACTION_MODIFY) != UR_ALLOWED_YES)
				{
					$oResult->code = RestResult::UNAUTHORIZED;
					$oResult->message = "The current user does not have enough permissions for creating data of class $sTicketClass";
				}
				elseif (UserRights::IsActionAllowed($sTicketClass, UR_ACTION_BULK_MODIFY) != UR_ALLOWED_YES)
				{
					$oResult->code = RestResult::UNAUTHORIZED;
					$oResult->message = "The current user does not have enough permissions for massively creating data of class $sTicketClass";
				}
				else
				{
					$oMonitoringContext->Execute();
					if ($oTicket = $oMonitoringContext->GetTicket())
					{
						$aShowFields = RestUtils::GetFieldList($oMonitoringContext->GetTicketClass(), $oParams, 'output_fields');
						$bExtendedOutput = (RestUtils::GetOptionalParam($oParams, 'output_fields', '*') == '*+');
						$sMessage = 'Performed actions: '.join(', ', $oMonitoringContext->GetPerformedActions());
						$oResult->AddObject(0, $sMessage, $oTicket, $aShowFields, $bExtendedOutput);
					}
					else
					{
						$oResult = new RestResult();
						$oResult->message = 'No ticket to perform actions.';
					}
				}
				break;

			default:
				// unknown operation: handled at a higher level
		}

		return $oResult;
	}
}

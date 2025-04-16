<?php
/**
 * @author Vladimir Kunin <we@knowitop.ru>
 */

//
// iTop module definition file
//

SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'knowitop-monitoring-api/0.1.1',
	array(
		// Identification
		//
		'label' => 'Monitoring API Module',
		'category' => 'business',

		// Setup
		//
		'dependencies' => array(
			// 'itop-request-mgmt/2.6.0||itop-incident-mgmt-itil/2.6.0'
		),
		'mandatory' => false,
		'visible' => true,

		// Components
		//
		'datamodel' => array(
			'main.knowitop-monitoring-api.php',
		),
		'webservice' => array(),
		'data.struct' => array(// add your 'structure' definition XML files here,
		),
		'data.sample' => array(// add your sample data XML files here,
		),

		// Documentation
		//
		'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
		'doc.more_information' => '', // hyperlink to more information, if any 

		// Default settings
		//
		'settings' => array(
			'demo_context' => array(
				'ci_oql' => 'SELECT Server WHERE name = :alarm->ci_key AND status = \'production\'',
				'ticket_oql' => 'SELECT UserRequest AS i JOIN lnkFunctionalCIToTicket AS lnk ON lnk.ticket_id = i.id JOIN Server AS ci ON lnk.functionalci_id = ci.id WHERE ci.name = :alarm->ci_key AND i.status != \'closed\' AND i.request_type = \'incident\'',
				'actions_on_problem' => array(
					'create' => array(
						'fields' => array(
							'org_id' => '$ci->org_id$',
							'caller_id' => 'SELECT Person WHERE friendlyname = \'Pablo Picasso\' AND org_id = $ci->org_id$',
							'title' => 'Monitoring: $alarm->message$',
							'description' => 'Monitoring: $alarm->message$ on $ci->name$',
						),
					),
					'reopen',
					'write_to_log',
				),
				'actions_on_ok' => array(
					'write_to_log',
				),
			),
		),
	)
);

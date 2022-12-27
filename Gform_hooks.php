<?php
defined('ABSPATH') || die('do not access directly');
class Gform_hooks {
	// class instance
	private static $instance;
	private static $remote_civicrm_url='https://crm.example.com/';
	private static $remote_civicrm_api_key='***';
	private static $remote_civicrm_site_key='***';
	
	public function __construct() {
		add_action('gform_after_submission', 'Gform_hooks::gform_after_submission', 10, 2);
	}
	
	public static function gform_after_submission($entry, $form) {
		if (stripos($form['title'] ?? '', 'download guide')!==false) {
			Gform_hooks::submit_download_guide($entry, $form);
		} else {
			switch($form['id']) {
				case 1:
					Gform_hooks::submit_contact_page_form($entry);
				break;
				case 4:
					Gform_hooks::submit_newsletter_sign_up_form($entry);
				break;
				case 5: case 6:
					Gform_hooks::submit_event_form($entry, $form);
				break;
			}
		}
	}
	
	private static function submit_contact_page_form($entry) {
		$entry = array_map('Gform_hooks::esc_js_rest_api',$entry);
		//check if the user is not there already
		$new_contact_id = null;
		$response=self::call_civicrm_rest_api([
			'entity' => 'Contact',
			'action' => 'get',
			'json' => '{"sequential":1,"email":"'.$entry['4'].'","contact_type":"Individual"}'
		]);
		if (!($existing_contact_id = $response['body']->values ? $response['body']->values[0]->contact_id : null)) {
			$response=self::call_civicrm_rest_api([
				'entity' => 'Contact',
				'action' => 'create',
				'json' => '{"contact_type":"Individual","first_name":"'.$entry['1.3'].'","last_name":"'.$entry['1.6'].'","source":"WP website - \'Contact page form\'"}'
			]);
			if (($new_contact_id = $response['body']->id ?? null)) {
				self::call_civicrm_rest_api([
					'entity' => 'Email',
					'action' => 'create',
					'json' => '{"contact_id":'.$new_contact_id.',"email":"'.$entry['4'].'","location_type_id":"Main","is_primary":1}'
				]);
				self::call_civicrm_rest_api([
					'entity' => 'Phone',
					'action' => 'create',
					'json' => '{"contact_id":'.$new_contact_id.',"phone":"'.$entry['3'].'","is_primary":1,"phone_type_id":"Phone","location_type_id":"Main"}'
				]);
				self::call_civicrm_rest_api([
					'entity' => 'Note',
					'action' => 'create',
					'json' => '{"entity_id":'.$new_contact_id.',"subject":"The message from the WP website - \'Contact page form\'","note":"'.$entry['6'].'","privacy":"None","entity_table":"civicrm_contact"}'
				]);
			}
		}

		$response=self::call_civicrm_rest_api([
			'entity' => 'Contact',
			'action' => 'get',
			'json' => '{"sequential":1,"organization_name":"'.$entry['2'].'","contact_type":"Organization"}'
		]);
		if (!($existing_company_contact_id = $response['body']->values ? $response['body']->values[0]->contact_id : null)) {
			$response=self::call_civicrm_rest_api([
				'entity' => 'Contact',
				'action' => 'create',
				'json' => '{"contact_type":"Organization","organization_name":"'.$entry['2'].'","source":"WP website - \'Contact page form\'"}'
			]);
			$new_company_contact_id = $response['body']->id ?? null;
		}
		if (($company_contact_id = $existing_company_contact_id ? $existing_company_contact_id : ($new_company_contact_id ?? null)) && $new_contact_id) {
			self::call_civicrm_rest_api([
				'entity' => 'Relationship',
				'action' => 'create',
				'json' => '{"contact_id_a":"'.$new_contact_id.'","contact_id_b":"'.$company_contact_id.'","relationship_type_id":5,"is_active":1,"is_current_employer":1}'
			]);
			self::call_civicrm_rest_api([
				'entity' => 'Contact',
				'action' => 'create',
				'json' => '{"contact_type":"Individual","id":"'.$new_contact_id.'","employer_id":"'.$company_contact_id.'","organization_name":"'.$entry['2'].'"}'
			]);
		}
		//Add Activity against the Individual
		if ($new_contact_id || $existing_contact_id) {
			$response=self::call_civicrm_rest_api([
				'entity' => 'Activity',
				'action' => 'create',
				'json' => '{"source_contact_id":179472,"activity_type_id":"Website Contact Form Submitted","activity_date_time":"'.date('Y-m-d H:i:s').'","subject":"WP website - \'Contact page form\' submission","status_id":"Completed","priority_id":"Normal","details":"First NAME: '.$entry['1.3'].'<br>Last NAME: '.$entry['1.6'].'<br>ORGANISATION: '.$entry['2'].'<br>TELEPHONE: '.$entry['3'].'<br>EMAIL: '.$entry['4'].'<br>MESSAGE: '.str_replace('\\n','<br>',$entry['6']).'"}'
			]);
			if (($new_activity_id = $response['body']->id ?? null)) {
				$response=self::call_civicrm_rest_api([
					'entity' => 'Activity',
					'action' => 'create',
					'json' => '{"source_contact_id":'.($new_contact_id ? $new_contact_id : $existing_contact_id).',"activity_type_id":"Follow up","activity_date_time":"'.date('Y-m-d H:i:s', strtotime('+1 day')).'","subject":"WP website - \'Contact page form\' submission - Follow-up activity","status_id":"Scheduled","priority_id":"Normal","parent_id":'.$new_activity_id.'}'
				]);
				if (($new_activity_id2 = $response['body']->id ?? null)) {
					self::call_civicrm_rest_api([
						'entity' => 'ActivityContact',
						'action' => 'create',
						'json' => '{"activity_id":'.$new_activity_id.',"contact_id":'.($new_contact_id ? $new_contact_id : $existing_contact_id).',"record_type_id":"Activity Assignees"}'
					]);
					self::call_civicrm_rest_api([
						'entity' => 'ActivityContact',
						'action' => 'create',
						'json' => '{"activity_id":'.$new_activity_id2.',"contact_id":1,"record_type_id":"Activity Assignees"}'
					]);
				}
			}
		}
	}
	
	private static function submit_download_guide($entry, $form) {
		$entry = array_map('Gform_hooks::esc_js_rest_api',$entry);
		//check if the user is not there already
		$new_contact_id = null;
		$response=self::call_civicrm_rest_api([
			'entity' => 'Contact',
			'action' => 'get',
			'json' => '{"sequential":1,"email":"'.$entry['2'].'","contact_type":"Individual"}'
		]);
		if (!($existing_contact_id = $response['body']->values ? $response['body']->values[0]->contact_id : null)) {
			$response=self::call_civicrm_rest_api([
				'entity' => 'Contact',
				'action' => 'create',
				'json' => '{"contact_type":"Individual","first_name":"'.$entry['1.3'].'","last_name":"'.$entry['1.6'].'","source":"WP website - \'download guide\'"}'
			]);
			if (($new_contact_id = $response['body']->id ?? null)) {
				self::call_civicrm_rest_api([
					'entity' => 'Email',
					'action' => 'create',
					'json' => '{"contact_id":'.$new_contact_id.',"email":"'.$entry['2'].'","location_type_id":"Main","is_primary":1}'
				]);
			}
		}

		$response=self::call_civicrm_rest_api([
			'entity' => 'Contact',
			'action' => 'get',
			'json' => '{"sequential":1,"organization_name":"'.$entry['3'].'","contact_type":"Organization"}'
		]);
		if (!($existing_company_contact_id = $response['body']->values ? $response['body']->values[0]->contact_id : null)) {
			$response=self::call_civicrm_rest_api([
				'entity' => 'Contact',
				'action' => 'create',
				'json' => '{"contact_type":"Organization","organization_name":"'.$entry['3'].'","source":"WP website - \'download guide\'"}'
			]);
			$new_company_contact_id = $response['body']->id ?? null;
		}

		if (($company_contact_id = $existing_company_contact_id ? $existing_company_contact_id : ($new_company_contact_id ?? null)) && $new_contact_id) {
			self::call_civicrm_rest_api([
				'entity' => 'Relationship',
				'action' => 'create',
				'json' => '{"contact_id_a":"'.$new_contact_id.'","contact_id_b":"'.$company_contact_id.'","relationship_type_id":5,"is_active":1,"is_current_employer":1}'
			]);
			self::call_civicrm_rest_api([
				'entity' => 'Contact',
				'action' => 'create',
				'json' => '{"contact_type":"Individual","id":"'.$new_contact_id.'","employer_id":"'.$company_contact_id.'","organization_name":"'.$entry['3'].'"}'
			]);
		}
		if ($new_contact_id || $existing_contact_id) {
			//Add Activity against the Individual
			$response=self::call_civicrm_rest_api([
				'entity' => 'Activity',
				'action' => 'create',
				'json' => '{"source_contact_id":179472,"activity_type_id":"Website Download","activity_date_time":"'.date('Y-m-d H:i:s').'","subject":"WP website - \'download guide\' submission","status_id":"Completed","priority_id":"Normal","details":"First NAME: '.$entry['1.3'].'<br>Last NAME: '.$entry['1.6'].'<br>EMAIL: '.$entry['2'].'<br>ORGANISATION: '.$entry['3'].'<br>Don\'t miss out on our latest articles, insights & events: '.(($entry['5.1'] ?? '') == 1 ? 'Yes' : 'No').'<br>FILE: '.(($form['confirmation']['url'] ?? '') != '' ? $form['confirmation']['url'] : '').'"}'
			]);
			if (($new_activity_id = $response['body']->id ?? null)) {
				self::call_civicrm_rest_api([
					'entity' => 'ActivityContact',
					'action' => 'create',
					'json' => '{"activity_id":'.$new_activity_id.',"contact_id":'.($new_contact_id ? $new_contact_id : $existing_contact_id).',"record_type_id":"Activity Assignees"}'
				]);
			}
		}
	}
	
	private static function submit_newsletter_sign_up_form($entry) {
		$entry = array_map('Gform_hooks::esc_js_rest_api',$entry);
		//check if the user is not there already
		$new_contact_id = null;
		$response = self::call_civicrm_rest_api([
			'entity' => 'Contact',
			'action' => 'get',
			'json' => '{"sequential":1,"email":"'.$entry['1'].'","contact_type":"Individual"}'
		]);
		if (!($existing_contact_id = $response['body']->values ? $response['body']->values[0]->contact_id : null)) {
			$response = self::call_civicrm_rest_api([
				'entity' => 'Contact',
				'action' => 'create',
				'json' => '{"contact_type":"Individual","first_name":"Newsletter","last_name":"Subscriber","source":"WP website - \'Newsletter sign up form\'"}'
			]);
			if (($new_contact_id = $response['body']->id ?? null)) {
				self::call_civicrm_rest_api([
					'entity' => 'Email',
					'action' => 'create',
					'json' => '{"contact_id":'.$new_contact_id.',"email":"'.$entry['1'].'","location_type_id":"Main","is_primary":1}'
				]);
			}
		}
		if ($new_contact_id || $existing_contact_id) {
			//Add the contact to the Groups (if not there already) - 'SUB - Events Programme' & 'SUB - News and Insights'
			foreach (['Subscriber_6','Curated_Content_41'] as $gname) {
				$response=self::call_civicrm_rest_api([
						'entity' => 'GroupContact',
						'action' => 'get',
						'json' => '{"sequential":1,"group_id":"'.$gname.'","contact_id":'.($new_contact_id ? $new_contact_id : $existing_contact_id).'}'
					]);
				if (!$response['body']->values) {
					self::call_civicrm_rest_api([
						'entity' => 'GroupContact',
						'action' => 'create',
						'json' => '{"group_id":"'.$gname.'","contact_id":'.($new_contact_id ? $new_contact_id : $existing_contact_id).',"status":"Added"}'
					]);
				}
			}

			//Add Activity against the Individual
			$response = self::call_civicrm_rest_api([
				'entity' => 'Activity',
				'action' => 'create',
				'json' => '{"source_contact_id":179472,"activity_type_id":"Website New Subscription","activity_date_time":"'.date('Y-m-d H:i:s').'","subject":"WP website - \'Newsletter sign up form\' submission","status_id":"Completed","priority_id":"Normal","details":"EMAIL: '.$entry['1'].'"}'
			]);
			if (($new_activity_id = $response['body']->id ?? null)) {
				self::call_civicrm_rest_api([
					'entity' => 'ActivityContact',
					'action' => 'create',
					'json' => '{"activity_id":'.$new_activity_id.',"contact_id":'.($new_contact_id ? $new_contact_id : $existing_contact_id).',"record_type_id":"Activity Assignees"}'
				]);
			}
		}
	}
	
	private static function submit_event_form($entry, $form) {
		$entry = array_map('Gform_hooks::esc_js_rest_api',$entry);
		//check if the user is not there already
		$new_contact_id = null;
		$response = self::call_civicrm_rest_api([
			'entity' => 'Contact',
			'action' => 'get',
			'json' => '{"sequential":1,"email":"'.$entry['3'].'","contact_type":"Individual"}'
		]);
		if (!($existing_contact_id = $response['body']->values ? $response['body']->values[0]->contact_id : null)) {
			$response = self::call_civicrm_rest_api([
				'entity' => 'Contact',
				'action' => 'create',
				'json' => '{"contact_type":"Individual","first_name":"'.$entry['1'].'","last_name":"'.$entry['2'].'","job_title":"'.$entry['4'].'","source":"WP website - \''.($form['id']==5 ? 'Webinar ' : '').'Event Form\'"}'
			]);
			if (($new_contact_id = $response['body']->id ?? null)) {
				self::call_civicrm_rest_api([
					'entity' => 'Email',
					'action' => 'create',
					'json' => '{"contact_id":'.$new_contact_id.',"email":"'.$entry['3'].'","location_type_id":"Main","is_primary":1}'
				]);
				self::call_civicrm_rest_api([
					'entity' => 'Phone',
					'action' => 'create',
					'json' => '{"contact_id":'.$new_contact_id.',"phone":"'.$entry['8'].'","is_primary":1,"phone_type_id":"Phone","location_type_id":"Main"}'
				]);
			}
		}
		$response=self::call_civicrm_rest_api([
			'entity' => 'Contact',
			'action' => 'get',
			'json' => '{"sequential":1,"organization_name":"'.$entry['6'].'","contact_type":"Organization"}'
		]);
		if (!($existing_company_contact_id = $response['body']->values ? $response['body']->values[0]->contact_id : null)) {
			$response=self::call_civicrm_rest_api([
				'entity' => 'Contact',
				'action' => 'create',
				'json' => '{"contact_type":"Organization","organization_name":"'.$entry['6'].'","source":"WP website - \''.($form['id']==5 ? 'Webinar ' : '').'Event Form\'"}'
			]);
			$new_company_contact_id = $response['body']->id ?? null;
		}
		if (($company_contact_id = $existing_company_contact_id ? $existing_company_contact_id : ($new_company_contact_id ?? null)) && $new_contact_id) {
			self::call_civicrm_rest_api([
				'entity' => 'Relationship',
				'action' => 'create',
				'json' => '{"contact_id_a":"'.$new_contact_id.'","contact_id_b":"'.$company_contact_id.'","relationship_type_id":5,"is_active":1,"is_current_employer":1}'
			]);
			self::call_civicrm_rest_api([
				'entity' => 'Contact',
				'action' => 'create',
				'json' => '{"contact_type":"Individual","id":"'.$new_contact_id.'","employer_id":"'.$company_contact_id.'","organization_name":"'.$entry['6'].'"}'
			]);
			$val_professional_level='';
			switch($entry['7']) {
				case 'Director':$val_professional_level=1;break;
				case 'Leadership Team':$val_professional_level=2;break;
				case 'Team or Department Head / Manager':$val_professional_level=3;break;
				case 'Operational':$val_professional_level=4;break;
			}
			if ($val_professional_level)
			self::call_civicrm_rest_api([
				'entity' => 'CustomValue',
				'action' => 'create',
				'json' => '{"entity_id":'.$new_contact_id.',"custom_81":"'.$val_professional_level.'"}'
			]);
		}
		
		if ($new_contact_id || $existing_contact_id) {
			//Create the event booking
			global $post;
			if (isset($post->ID) && $post->post_type=='insights') {
				$response=self::call_civicrm_rest_api([
					'entity' => 'Event',
					'action' => 'get',
					'json' => '{"sequential":1,"return":"id,event_type_id,is_online_registration,is_email_confirm,confirm_email_text,confirm_from_name,confirm_from_email,start_date,end_date,is_show_location,loc_block_id","title":"'.Gform_hooks::esc_js_rest_api($post->post_title).'","is_active":1}'
				]);
				if ($response['body']->values && $response['body']->values[0]->is_online_registration ?? '' == 1) {
					$event_id=$response['body']->values[0]->id;
					foreach (['event_type_id','is_email_confirm','confirm_email_text','confirm_from_name','confirm_from_email','start_date','end_date','is_show_location','loc_block_id'] as $var_name) $$var_name = $response['body']->values[0]->$var_name;
					$response=self::call_civicrm_rest_api([
						'entity' => 'Participant',
						'action' => 'get',
						'json' => '{"sequential":1,"return":"id","event_id":'.$event_id.',"contact_id":'.($new_contact_id ? $new_contact_id : $existing_contact_id).'}'
					]);
					if (!$response['body']->values) {
						self::call_civicrm_rest_api([
							'entity' => 'Participant',
							'action' => 'create',
							'json' => '{"event_id":'.$event_id.',"contact_id":'.($new_contact_id ? $new_contact_id : $existing_contact_id).',"status_id":"Registered","role_id":"Attendee","source":"WP website - \''.($form['id']==5 ? 'Webinar ' : '').'Event Form\'"'.($form['id']==6 ? ',"custom_105":"'.$entry['9'].'","custom_104":"'.$entry['10'].'"' : '').'}'
						]);
					}
					if ($is_email_confirm) {
						$loc_block='';
						if ($is_show_location && $loc_block_id) {
							$response=self::call_civicrm_rest_api([
								'entity' => 'LocBlock',
								'action' => 'get',
								'json' => '{"sequential":1,"return":"address_id","id":'.$loc_block_id.'}'
							]);
							if ($response['body']->values) {
								$address_id = $response['body']->values[0]->address_id;
								$response=self::call_civicrm_rest_api([
									'entity' => 'Address',
									'action' => 'get',
									'json' => '{"sequential":1,"id":'.$address_id.'}'
								]);
								if ($response['body']->values) {
									$loc_block = $response['body']->values[0]->street_address;
									if (($country_id = $response['body']->values[0]->country_id)) {
										$response=self::call_civicrm_rest_api([
											'entity' => 'Country',
											'action' => 'get',
											'json' => '{"sequential":1,"return":"name","id":'.$country_id.',"is_active":1}'
										]);
										if ($response['body']->values) {
											$loc_block .= '<br>'.$response['body']->values[0]->name;
										}
									}
								}
							}
						}
						$event_type='';
						if ($event_type_id) {
							$response=self::call_civicrm_rest_api([
								'entity' => 'OptionValue',
								'action' => 'get',
								'json' => '{"sequential":1,"return":"label","option_group_id":"event_type","value":'.$event_type_id.',"is_active":1}'
							]);
							if ($response['body']->values) {
								$event_type=$response['body']->values[0]->label;
							}
						}
						$html_table='<table width="700" style="border:1px solid rgb(153,153,153);margin:1em 0em;border-collapse:collapse;">
<tbody>
<tr>
<th colspan="2" style="text-align:left;padding:4px;border-bottom:1px solid rgb(153,153,153);background-color:rgb(238,238,238)">Event Information and Location</th>
</tr>
<tr>
<td colspan="2" style="padding:4px;border-bottom:1px solid rgb(153,153,153);">'.$post->post_title.'<br>
'.date('l F jS, Y h:i A', strtotime($start_date)).' - '.date('h:i A', strtotime($end_date)).'</td>
</tr>
<tr>
<td colspan="2" style="padding:4px;border-bottom:1px solid rgb(153,153,153);">
<div>'.$loc_block.'</div>
</td>
</tr>
<tr>
<td colspan="2" style="padding:4px;border-bottom:1px solid rgb(153,153,153)"><a href="https://crm.example.com/civicrm/event/ical?reset=1&id='.$event_id.'" target="_blank" data-saferedirecturl="https://www.google.com/url?q=https://crm.example.com/civicrm/event/ical?reset%3D1%26id%3D&source=gmail&ust=123&usg=123">Download iCalendar File</a></td>
</tr>
<tr>
<th colspan="2" style="text-align:left;padding:4px;border-bottom:1px solid rgb(153,153,153);background-color:rgb(238,238,238)">'.$event_type.' registration</th>
</tr>
<tr>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153);background-color:rgb(247,247,247)">First Name</td>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153)">'.$entry['1'].'</td>
</tr>
<tr>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153);background-color:rgb(247,247,247)">Last Name</td>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153)">'.$entry['2'].'</td>
</tr>
<tr>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153);background-color:rgb(247,247,247)">Email</td>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153)"><a href="mailto:'.$entry['3'].'">'.$entry['3'].'</a></td>
</tr>
<tr>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153);background-color:rgb(247,247,247)">Job Title</td>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153)">'.$entry['4'].'</td>
</tr>
<tr>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153);background-color:rgb(247,247,247)">Current Employer</td>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153)">'.$entry['6'].'</td>
</tr>
<tr>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153);background-color:rgb(247,247,247)">Professional Level</td>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153)">'.$entry['7'].'</td>
</tr>
<tr>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153);background-color:rgb(247,247,247)">Phone</td>
<td style="padding:4px;border-bottom:1px solid rgb(153,153,153)">'.$entry['8'].'</td>
</tr>
</tbody>
</table>';
						add_filter('wp_mail_content_type',function($type) {return "text/html";});
						if ($confirm_from_name) {
							add_filter('wp_mail_from_name', function($from_name) use($confirm_from_name) {return $confirm_from_name;},100);
						}
						if ($confirm_from_email) {
							add_filter('wp_mail_from', function($from_email) use($confirm_from_email) {return $confirm_from_email;},100);
						}
						wp_mail($entry['3'], 'Registration Confirmation - '.$post->post_title, "Dear $entry[1],<br><br>".nl2br($confirm_email_text)."<br><br>Please save this confirmation for your records.<br><br>$html_table", $confirm_from_name && $confirm_from_email ? ["From: $confirm_from_name <$confirm_from_email>"] : '');
					}
				}
			}
			//Add the contact to the Groups (if not there already) - 'SUB - Events Programme'
			foreach (['Subscriber_6'] as $gname) {
				$response=self::call_civicrm_rest_api([
					'entity' => 'GroupContact',
					'action' => 'get',
					'json' => '{"sequential":1,"group_id":"'.$gname.'","contact_id":'.($new_contact_id ? $new_contact_id : $existing_contact_id).'}'
				]);
				if (!$response['body']->values) {
					self::call_civicrm_rest_api([
						'entity' => 'GroupContact',
						'action' => 'create',
						'json' => '{"group_id":"'.$gname.'","contact_id":'.($new_contact_id ? $new_contact_id : $existing_contact_id).',"status":"Added"}'
					]);
				}
			}
		}
	}
	
	private static function call_civicrm_rest_api($args) {
		$response = wp_remote_post(self::$remote_civicrm_url.'sites/all/modules/civicrm/extern/rest.php', [
			'method' => 'POST',
			'timeout' => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => [],
			'body' => [
				'entity' => $args['entity'],
				'action' => $args['action'],
				'api_key' => self::$remote_civicrm_api_key,
				'key' => self::$remote_civicrm_site_key,
				'json' => $args['json']
			],
			'cookies' => []
			]
		);$err_msg='';
		if (is_wp_error($response)) {
			$err_msg=$response->get_error_message();
		} else {
			$response['body'] = json_decode($response['body']);
			if (!empty($response['body']->is_error)) {
				$err_msg=print_r($response['body'],true);
			}
		}
		if ($err_msg) {
			$site_url = defined('WP_SITEURL') ? WP_SITEURL : get_site_url();
			$is_staging=strpos($site_url, 'staging') !== false;
			if (!$is_staging) {
				try {
					throw new Exception;
				} catch (Exception $ex) {
					$exception_msg = $ex->getTraceAsString();
				}
				wp_mail('marketing@example.com', 'A CiviCRM Rest API error on your website',"Dear Admin,\nthere has been a CiviCRM Rest API error on your website - ".$site_url.$_SERVER['REQUEST_URI'].". See the details below:\nArgs passed: ".print_r($args,true)."\nResponse error message: $err_msg\nException Trace String: $exception_msg\n\nBW,\n".get_option('blogname'), ['Cc: webforms@example.com']);
			}
			wp_die($err_msg);
		}
		return $response;
	}
	public static function esc_js_rest_api($text) {
		//the code below was copied from the original function esc_js() and here is changed the function not to html encode i.e. & -> &amp; AND addslashes -> addcslashes i.e we want to escape only " and \
		$text = wp_check_invalid_utf8($text);
		$text = preg_replace('/&#(x)?0*(?(1)27|39);?/i', "'", stripslashes($text));
		$text = str_replace("\r", '', $text);
		$text = str_replace("\n", '\\n', addcslashes($text, '"\\'));
		return $text;
	}
	
	/** Singleton instance */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
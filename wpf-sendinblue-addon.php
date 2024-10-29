<?php /*
Plugin Name: 	   Add on for Sendinblue on Wpforms
Plugin URI: 	   https://zypacinfotech.com/wpf-sendinblue/
Description:       WPForm Sendinblue Addon for multiple contact.
Version:           1.0
Author:            Zypac Infotech
Author URI:        https://zypacinfotech.com
License:           GPL-2.0+
License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain:       wpf-sendinblue
*/

function wpf_settings_tab_name( $sections, $form ) {
	$sections['tab_name'] = __( 'Sendinblue Settings', 'wpf-sendinblue' );
  	return $sections;
}
add_filter( 'wpforms_builder_settings_sections', 'wpf_settings_tab_name', 10, 2 );

add_action('wpforms_form_settings_panel_content', 'wpforms_form_settings_panel_content2');
function wpforms_form_settings_panel_content2($form){
	echo '<div class="wpforms-panel-content-section wpforms-panel-content-section-tab_name" data-panel="tab_name">';
		if($form->form_data['settings']['wpf_sb_license_key'] != ''){
			$url = 'https://api.sendinblue.com/v3/contacts/lists';
    		$response = wp_remote_get( $url, array(
    			'headers' => array(
    				'Accept' => 'application/json',
    				'api-key' => $form->form_data['settings']['wpf_sb_license_key'],
    			),
    		));
    		$resbody = json_decode($response['body'],true);
    		$options = $values = array();
    		if( !empty($resbody) ){
    			foreach( $resbody['lists'] as $d ){ 
    				$options[$d['id']] = $d['name'];
    			}
    		}
		}
		wpforms_panel_field(
			'toggle',
			'settings',
			'wpf_sb_activate',
			$form->form_data,
			esc_html__( 'Activate', 'wpforms-lite' ),
			[
				'tooltip' => esc_html__( 'Check this box if you would like to send data to sendinblue during form submission.', 'wpf-sendinblue' ),
			]
		);
		wpforms_panel_field(
			'text',
			'settings',
			'wpf_sb_license_key',
			$form->form_data,
			esc_html__( 'License Key(Required)', 'wpforms-lite' ),
			[
				'tooltip' => esc_html__( 'Enter Sendinblue License Key', 'wpf-sendinblue' ),
			]
		);
		wpforms_panel_field(
			'select',
			'settings',
			'wpf_sb_email',
			$form->form_data,
			esc_html__( 'Email(Required)', 'wpforms-lite' ),
			[
				'value' => "",
				'field_map'=> array("email"),
				'multiple' => false,
				'tooltip' => esc_html__( 'Filed Type Email Only', 'wpf-sendinblue' ),
			]
		);
		wpforms_panel_field(
			'select',
			'settings',
			'wpf_sb_firstName',
			$form->form_data,
			esc_html__( 'First Name', 'wpforms-lite' ),
			[
				'value' => "",
				'field_map'=> array("name","text"),
				'multiple' => false,
				'tooltip'=>false,
			]
		);
		wpforms_panel_field(
			'select',
			'settings',
			'wpf_sb_lasttName',
			$form->form_data,
			esc_html__( 'Last Name', 'wpforms-lite' ),
			[
				'value' => "",
				'field_map'=> array("name","text"),
				'multiple' => false,
				'tooltip' => false,
			]
		);
		wpforms_panel_field(
			'select',
			'settings',
			'wpf_sb_phone',
			$form->form_data,
			esc_html__( 'Mobile Number', 'wpforms-lite' ),
			[
				'value' => "",
				'field_map'=> array("phone"),
				'multiple' => false,
				'tooltip'  => esc_html__( 'Should be passed with proper country code. For example: +91xxxxxxxxxx or 0091xxxxxxxxxx', 'wpf-sendinblue' ),
			]
		);
		wpforms_panel_field(
			'select',
			'settings',
			'contactList',
			$form->form_data,
			esc_html__( 'Contact List', 'wpforms-lite' ),
			[	'options' => $options,
				'value' => "",
				'multiple' => false,
				'tooltip'  => esc_html__( 'Contact List Only Visible After Add Correct Sendinblue API Key', 'wpf-sendinblue' ),
			]
		);
	echo '</div>';
}

add_action( 'wpforms_process_complete', "wpf_sendinblue_form_process_entry_save", 10, 4 );
function wpf_sendinblue_form_process_entry_save($fields, $entry, $form_data, $entry_id){
	$send_data = array();
	if( $form_data['settings']['wpf_sb_activate']==1 ){
		if( $form_data['settings']['wpf_sb_email'] != '' ){
				$send_data['email'] = $entry['fields'][$form_data['settings']['wpf_sb_email']];
		}
		if( $form_data['settings']['wpf_sb_firstName'] != '' ){
				$send_data['FIRSTNAME'] =$entry['fields'][$form_data['settings']['wpf_sb_firstName']]['first'];
		}
		if( $form_data['settings']['wpf_sb_lasttName'] != '' ){
				$send_data['LASTNAME'] = $entry['fields'][$form_data['settings']['wpf_sb_lasttName']]['last'];
		}
		if( $form_data['settings']['wpf_sb_phone'] != '' ){
				$send_data['SMS'] = $entry['fields'][$form_data['settings']['wpf_sb_phone']];
		}
		$data = array(
			'email' => $entry['fields'][$form_data['settings']['wpf_sb_email']],
			'SMS' => $entry['fields'][$form_data['settings']['wpf_sb_phone']],
			'attributes' => $send_data,
			'listIds' => array(intval($form_data['settings']['contactList']))
		);
		$url = 'https://api.sendinblue.com/v3/contacts';
		$response = wp_remote_post( $url, array(
			'body'    => json_encode($data),
			'headers' => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
				'api-key' => $form_data['settings']['wpf_sb_license_key'],
			),
		));
		$resbody = json_decode($response['body'],true);
		$user_id = wp_get_current_user();
		if (isset($resbody)) {	
			if (isset($resbody['id'])) {
				$message    = esc_html__( 'Sendinblue ID - '.$resbody['id'], 'wpforms' );
				$entry_meta = wpforms()->get( 'entry_meta' );
				$entry_meta->add(
					[
						'entry_id' => $entry_id,
						'form_id'  => $form_data['id'],
						'user_id'  => '',
						'type'     => 'log',
						'data'     => wpautop( $message ),
					],
					'entry_meta'
				);
			}else {
				$message    = esc_html__( 'Sendinblue Error - '.$resbody['message'], 'wpforms' );
				$entry_meta = wpforms()->get( 'entry_meta' );
				$entry_meta->add(
					[
						'entry_id' => $entry_id,
						'form_id'  => $form_data['id'],
						'user_id'  => get_current_user_id(),
						'type'     => 'log',
						'data'     => wpautop( $message ),
					],
					'entry_meta'
				);
			}
		}else{
			$message    = esc_html__( 'Sendinblue Error - '.$resbody['message'], 'wpforms' );
			$entry_meta = wpforms()->get( 'entry_meta' );
			$entry_meta->add(
				[
					'entry_id' => $entry_id,
					'form_id'  => $form_data['id'],
					'user_id'  => get_current_user_id(),
					'type'     => 'log',
					'data'     => wpautop( $message ),
				],
				'entry_meta'
			);
		}
	}
}
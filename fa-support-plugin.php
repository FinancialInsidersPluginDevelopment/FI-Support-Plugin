<?php
/* Plugin Name: FinancialInsiders Support Plugin
 * Author: Vinodhagan Thangarajan
 * Description: Instant Connect plugin for Neil Thomas
 * Version: 1.0
 */
 
 include 'fa-support-listtable.php';
 define('FAS_PLUGIN_URL', plugin_dir_url( __FILE__ ));
 if(!class_exists("Stripe\Stripe"))
 {
	 
 	 require_once( ABSPATH . '/wp-content/plugins/paid-memberships-pro/includes/lib/Stripe/init.php');
 	 //Had to change the stripe location.
	 
 }

 
 
 Class FA_Support
 {
	function FA_Support(){
		
		register_activation_hook(__FILE__, array( &$this, 'FA_install'));
		register_uninstall_hook(__FILE__, array( &$this, 'FA_uninstall'));
		
		$this->fa_lead_options = get_option('fa_lead_settings');
		
		if(isset($_POST['appointments-confirmation-button']))
		{
			$this->save_lead();
		}
		if(isset($_GET['action']) && $_GET['action'] == 'update_lead_status')
		{
			$this->update_lead_status();
		}
		
		add_action( 'app_new_appointment', array( &$this, 'save_lead'), 100 );
		//add_action( 'wp_footer', array( &$this, 'save_surfing_page'), 100 );
		add_action( 'admin_menu', array( $this, 'add_plugin_pages' ) );
		if(is_admin())
			add_action( 'admin_init',  array( $this, 'add_plugin_options' ));
		add_action( 'wpmu_new_blog',  array( $this, 'add_user_site_options' ), 10, 6);	
		add_action( 'wp_ajax_get_lead_info', array( &$this, 'get_lead_info'), 100 );

		add_action('admin_footer', array( $this, 'todays_appointment'));
		add_action('wpmudev_appointments_update_appointment', array( $this, 'resend_appointment_confirmation'));
		add_action( 'wp_ajax_cronjob', array( $this, 'remainder_email'));
		add_action( 'wp_ajax_nopriv_cronjob', array( $this, 'remainder_email') );
		add_filter( 'page_template', array( &$this, 'wpa3396_page_template' ));


		if(is_main_site())
		{
			if(isset($_POST['data']))
			{
				if(isset($_COOKIE['fi_agent_site']))
					$this->save_lead(0, $_COOKIE['fi_agent_site']);
				else
					$this->save_lead(0);
			}
		}
		else
		{
			setcookie("fi_agent_site", get_current_blog_id(), time() + (86400 * 365), "/");
		}
		
	}

	function wpa3396_page_template( $page_template )
	{
		if (isset($this->fa_lead_options['waiting_page']) && is_page( $this->fa_lead_options['waiting_page'] ) ) {
			$page_template = dirname( __FILE__ ) . '/waitformeeting-template.php';
		}
		return $page_template;
	}

	function remainder_email()
	{
		global $wpdb;
		$appointments = $wpdb->get_results("select * from ".$wpdb->prefix."app_appointments where DATE(start)='".date("Y-m-d")."'");

		foreach ($appointments as $key => $value) {
			$message = '<h4>Hi '.$value->name.'</h4>';
			$message .= '<p>This is Remainder email for your appointment scheduled on today. View more details about your meeting <a href="'.get_permalink($this->fa_lead_options['waiting_page']).'?app='.base64_encode(base64_encode($value->ID)).'">click here</a></p>';
			NTM_mail_template::send_mail($value->email, 'Remainder email for your appointment.', $message);
		}
		
	}

	function resend_appointment_confirmation($app_id, $args, $old_appointment)
	{
		global $wpdb;
		$value = $wpdb->get_row("select * from ".$wpdb->prefix."app_appointments where ID=".$app_id);

		$message = '<h4>Hi '.$value->name.'</h4>';
		$message .= '<p>This is Remainder email for your appointment scheduled on today. View more details about your meeting <a href="'.get_permalink($this->fa_lead_options['waiting_page']).'?app='.base64_encode(base64_encode($value->ID)).'">click here</a></p>';
		NTM_mail_template::send_mail($value->email, 'Remainder email for your appointment.', $message);
		
	}

	function todays_appointment()
	{
		global $wpdb;
		$appointments = $wpdb->get_results("select * from ".$wpdb->prefix."app_appointments where DATE(start)='".date("Y-m-d")."'");

		?>
		<style>
		.todays_app_icon{width:70px; height:70px; border-radius:50%; background:#5C93C1; text-align:center; position:fixed; right:30px; bottom:130px; cursor:pointer;}
		.todays_app_icon img{width: 30px;height: 30px;left:0;right: 0;top:0;bottom: 0;margin: auto;position: absolute;}
		.todays_app_icon i{background: #fff none repeat scroll 0 0;border: 1px solid;border-radius: 50%;box-sizing: border-box;display: inline-block;height: 30px;padding: 5px;position: absolute;right: -5px;top: -5px;width: 30px;}
		.todays_appointment{transition:all 350ms ease 0s; right:-400px; position:fixed; background:#fff; top:0; bottom:0; width:300px; z-index: 100000; border:2px solid #ccc;}
		.todays_appointment.show_list{right:0; }
		.todays_appointment h3{text-align:center; position:relative;}
		.todays_appointment h3 i{position:absolute; top:2px;}
		.todays_appointment h3 i.fa-cog, .instant_connect_form h3 i.fa-arrow-left{left:20px;}
		.todays_appointment h3 i.fa-times{right:20px;}
		</style>
		<div class="todays_app_icon">
			<img src="<?= FAS_PLUGIN_URL.'appointment.png'; ?>">
			<i><?= count($appointments);?></i>
		</div>
		<div class="todays_appointment">
			<h3><i class="fa fa-cog cp" ng-click="settings = true;"></i>Today's appointment<i class="fa fa-times cp"></i></h3>

			<?php foreach($appointments as $k=>$v){?>
			<div>
				#<?= k+1?> <?= $v->name;?>, <?= $v->email;?> <br><?= $v->start;?>
				<hr>
			</div>
			<?php }?>
		</div>
		<script>
			jQuery(document).ready(function(){
				jQuery(".todays_app_icon, .todays_appointment i.fa-times").click(function(){
					jQuery(".todays_appointment").toggleClass("show_list");
				});
			});
		</script>
		<?php
	}

	function add_user_site_options($blog_id, $user_id, $domain, $path, $site_id, $meta) {
			add_blog_option($blog_id, 'agent_id', $user_id );
			 //echo "Blog ID: " . $blog_id;
			 //echo "BLOG ID: " . get_blog_option($blog_id, 'agent_id');
			 //exit;
	}

	function get_lead_info()
	{
		global $wpdb;
		
		$res = $wpdb->get_row("select * from wp_leads where id=".$_POST['id']);
		
		$form_data = unserialize($res->form_data);

		/*$label_fields = array('first_name' => 'First Name', 'last_name' => 'Last name', 'email' => 'Email Address', 'city' => 'City', 'phone' => 'Phone Number', 'postal_code' => 'Postal Code', 'province' => 'Province', 'dob' => 'Date of Birth', 'gender' => 'Gender', 'marital_status' => 'Marital Status', 'occupation' => 'Occupation', 'retire_age' => 'At what age would you plan to retire?', 'retire_income' => 'Desired monthly income after retirement', 'retirement_goal' => 'Do you have a plan to meet your retirement goals?', 'own_business' => 'Do you own a business?', 'comments' => 'Comments', 'address' => 'Address');*/
		
		?>
		<table border="0" cellspacing="0" cellpadding="10">
			<tr>
				<th width="50%">Field</th>
				<th width="50%">Value</th>
			</tr>
			<?php foreach($form_data as $k=>$v){ if($v){?>
			<tr>
				<td><?= ucfirst(str_replace($k, "_", " "));?></td>
				<td><?= $v;?></td>
			</tr>
			<?php }}?>
		</table>
		<?php
		echo $res->status == 1 ? '<a class="button button-primary" href="?action=change_status&id='.$res->id.'&appointment_id='.$res->appointment_id.'">Confirm Lead</a>' : '';
		die(0);
	}



	function add_plugin_options() 
	{
		register_setting('fa_lead_settings_group', 'fa_lead_settings');
	}
	
	
	function add_plugin_pages() {
		add_menu_page( 'Leads', 'Leads', 'manage_options', 'lead_table', array( $this, 'lead_table' ));

		add_submenu_page('lead_table', 'Payment Options', 'Payment Options', 'manage_options', 'payment_options', array($this, 'payment_options'));
	}
	
	function save_surfing_page()
	{
		$surfing_page = isset($_COOKIE['fa_surfing_page']) ? explode(",", $_COOKIE['fa_surfing_page']) : array();
		//print_r(!in_array(get_the_title(), $surfing_page));
		$data = 'Blog:'.get_current_blog_id().' Page:'.get_the_title();
		if(!in_array($data, $surfing_page))
			$surfing_page[] = $data;
		//print_r($surfing_page);
		setcookie("fa_surfing_page", implode(",", $surfing_page), time() + (86400 * 365), "/");
		
		if(!isset($_COOKIE['visited_blog_id']) && get_current_blog_id())
			setcookie("visited_blog_id", get_current_blog_id(), time() + (86400 * 365), "/");
	}
	
	function get_visited_blog_id()
	{
		return $_COOKIE['visited_blog_id'];
	}
	
	function update_lead_status()
	{
		global $wpdb;
		
		$lead_id = base64_decode(base64_decode($_GET['id']));
		$wpdb->update("wp_leads", array('status' => 1), array('id' => $lead_id));
		
		$administrator = get_users( array( 'role' => 'administrator' ) );
		foreach($administrator as $ad)
		{
			$this->send_lead_confirm_notification($ad->user_email, $lead_id);
		}
	}
	
	function get_client_ip() {
	    $ipaddress = '';
	    if (getenv('HTTP_CLIENT_IP'))
	        $ipaddress = getenv('HTTP_CLIENT_IP');
	    else if(getenv('HTTP_X_FORWARDED_FOR'))
	        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
	    else if(getenv('HTTP_X_FORWARDED'))
	        $ipaddress = getenv('HTTP_X_FORWARDED');
	    else if(getenv('HTTP_FORWARDED_FOR'))
	        $ipaddress = getenv('HTTP_FORWARDED_FOR');
	    else if(getenv('HTTP_FORWARDED'))
	       $ipaddress = getenv('HTTP_FORWARDED');
	    else if(getenv('REMOTE_ADDR'))
	        $ipaddress = getenv('REMOTE_ADDR');
	    else
	        $ipaddress = 'UNKNOWN';
	    return $ipaddress;
	}

	function save_lead($appointmentID, $bid = '')
	{
		global $wpdb;

		$blog_id = $bid ? $bid : get_current_blog_id();

		$lead_data = $_POST;
		$lead_data['created'] = date("Y-m-d H:i:s");
		$lead_data['ip_address'] = $this->get_client_ip();
		$lead_data['agent_id'] = get_blog_option($blog_id, 'agent_id');
		$lead_data['blog_id'] = $blog_id;
		$lead_data['form_url'] = $_SERVER['HTTP_REFERER'];
		$lead_data['visited_page'] = $_COOKIE['fa_surfing_page'];
		$lead_data['form_data'] = serialize($_POST);
		if($appointmentID)
		$lead_data['appointment_id'] = $appointmentID;
		
		if(isset($_COOKIE['endorsement_track_link']) && isset($_COOKIE['endorsement_tracked']))
		{
			$track_link = explode("#&$#", base64_decode(base64_decode($_COOKIE['endorsement_track_link'])));
			$lead_data['endorser_id'] = $track_link[1];
		}

		if(isset($_COOKIE['endorsement_gift_sent']))
		{
			$lead_data['gift'] = 1;
		}
		
		unset($lead_data['action']);
		unset($lead_data['app_name']);
		unset($lead_data['app_email']);
		unset($lead_data['app_phone']);
		unset($lead_data['app_address']);
		unset($lead_data['app_city']);
		unset($lead_data['app_note']);
		unset($lead_data['app_gcal']);
		unset($lead_data['nonce']);
		//print_r($lead_data);
		if(isset($lead_data['lead_id']))
		{
			$wpdb->update("wp_leads", $lead_data, array('id' => $lead_data['lead_id']));
			$this->confirmation_mail($lead_data['lead_id']);
		}
		else
		{
			$wpdb->insert("wp_leads", $lead_data);
			$lead_id = $wpdb->insert_id;

			if($bid)
				$this->confirmation_mail_from_main_site($lead_id);
			else
				$this->confirmation_mail($lead_id);
		}
	}
	
	function confirmation_mail_from_main_site($lead_id)
	{
		$message = 'Thanks for signing up FinancialInsiders. <a href="'.get_permalink($this->fa_lead_options['appointment_page']).'?id='.base64_encode(base64_encode($lead_id)).'">Click here to confirm your booking</a>';
		
		NTM_mail_template::send_mail($_POST['email'], 'Book your Appointment.', $message);
	}

	function confirmation_mail($lead_id)
	{
		$message = 'Thanks for signing up FinancialInsiders. <a href="'.site_url().'?action=update_lead_status&id='.base64_encode(base64_encode($lead_id)).'">Click here to confirm your registration</a>';
		
		NTM_mail_template::send_mail($_POST['email'], 'Registered with FinancialInsiders successfly.', $message);
	}
	
	function send_lead_confirm_notification($email, $lead_id)
	{
		global $wpdb;
		
		$lead = $wpdb->get_row("select * from wp_leads where id =" . $lead_id);
		
		$message = 'New Lead confirmation notification <br>
					<h2>Lead Detais <h2> <br>
					Lead Name : '.$lead->first_name.' '.$lead->last_name.'<br>
					Lead Name : '.$lead->email;
		
		NTM_mail_template::send_mail($email, 'New Lead confirmation notification.', $message);
	}
	
	function FA_install()
	{
		global $wpdb;
		
		$mailtemplates = "wp_leads";
		
		if($wpdb->get_var('SHOW TABLES LIKE ' . $mailtemplates) != $mailtemplates){
			$sql_one = "CREATE TABLE " . $mailtemplates . "(
			  id int(11) NOT NULL AUTO_INCREMENT,
			   created datetime NOT NULL,
			   first_name tinytext NOT NULL,
			   last_name tinytext,
			   email tinytext NOT NULL,
			   city tinytext,
			   phone tinytext,
			   postal_code tinytext,
			   province tinytext,
			   dob tinytext,
			   gender tinytext,
			   occupation tinytext,
			   income tinytext,
			   retire_age tinytext,
			   marital_status tinytext,
			   retire_income tinytext,
			   retirement_goal tinytext,
			   own_business tinytext,
			   comments text,
			   agent_id text NOT NULL,
			   blog_id text NOT NULL,
			   newsetter boolean,
			   status int(1),
			   address text,
			   visited_page tinytext,
			   form_url tinytext,
			   endorser_id int(11),
			   appointment_id int(11),
			   gift int(11),
			   ip_address tinytext NOT NULL,
			   form_data text,
			  PRIMARY KEY  (id) ) ENGINE=InnoDB";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql_one);
		}
	}
	
	function lead_table()
	{
		global $wpdb, $appoinments, $ntm_mail;
		
		if(isset($_GET['action']) && $_GET['action'] == 'change_status')
		{
			$lead = $wpdb->get_row("select * from wp_leads where id=".$_GET['id']);
			
			if(!$lead->gift)
			{
				Stripe::setApiKey($this->fa_lead_options['api_key']);
				Stripe::setAPIVersion("2015-07-13");
				
				$customer_id = get_user_meta($lead->agent_id, "pmpro_stripe_customerid");
				
				$amount = $this->fa_lead_options['admin_fee'] * 100;
				$giftAmount =  $this->fa_lead_options['init_gift'];

				$invoice_item = Stripe_InvoiceItem::create( array(
					'customer'    => $customer_id, // the customer to apply the fee to
					'amount'      => $this->fa_lead_options['admin_fee'] + ($giftAmount * 100), // amount in cents
					'currency'    => 'usd',
					'description' => 'One-time setup fee' // our fee description
				) );
			 
				$invoice = Stripe_Invoice::create( array(
					'customer'    => $customer_id, // the customer to apply the fee to
				) );
			 
				$result = $invoice->pay();
				if(isset($result->object) && $result->object == 'invoice')
				{
					$wpdb->update("wp_leads", array('status' => 2, 'gift' => 1), array('id' => $_GET['id']));
					$appoinments->appointments_update_appointment_status( $_GET['appointment_id'], 'confirmed' );
					
					$data = array(
									'lead_id' =>$_GET['id'],
									'amout' => $giftAmount,
									'created'	=> date("Y-m-d H:i:s")
									);
					$wpdb->insert($wpdb->prefix . "gift_transaction", $data);
					$gift_id = $wpdb->insert_id;
					$ntm_mail->send_gift_mail('get_manualgift_mail', $_GET['id'], $gift_id, 1);
					
					$user_info = get_userdata($lead->agent_id);
					$agentemail = $user_info->user_email;
					$this->send_lead_confirm_notification_to_agent($agentemail, $lead->id);


				}
			}
			
		}
		
		$lead_table = new LeadTable();
		$lead_table->prepare_items();
		$lead_table->display();
		
		?>
		<link rel="stylesheet" type="text/css" href="<?php _e(FAS_PLUGIN_URL);?>/css/colorbox.css" media="all" />
		<script type='text/javascript' src='<?php _e(FAS_PLUGIN_URL);?>/js/jquery.colorbox.js'></script>
		<script>
		jQuery(document).ready(function(){
			jQuery(".inline").colorbox({inline:true, width:"80%", height:"80%"});
			
			jQuery(".inline").click(function(){
				jQuery("#inline_content").html('');
				jQuery.post(
						ajaxurl, 
						{
							'action': 'get_lead_info',
							'id':   jQuery(this).data("id")
						}, 
						function(response){
							jQuery("#inline_content").html(response);
						}
					);
			});
		});
		</script>
		<div style='display:none'>
			<div id='inline_content' style='padding:10px; background:#fff;'>
				
			</div>
		</div>
		<?php
	}
	
	function send_lead_confirm_notification_to_agent($email, $lead_id)
	{
		global $wpdb;
		
		$lead = $wpdb->get_row("select * from wp_leads where id =" . $lead_id);
		
		$message = 'New Lead confirmation notification <br>
					<h2>Lead Detais <h2> <br>';
		
		$label_fields = array('first_name' => 'First Name', 'last_name' => 'Last name', 'email' => 'Email Address', 'city' => 'City', 'phone' => 'Phone Number', 'postal_code' => 'Postal Code', 'province' => 'Province', 'dob' => 'Date of Birth', 'gender' => 'Gender', 'marital_status' => 'Marital Status', 'occupation' => 'Occupation', 'retire_age' => 'At what age would you plan to retire?', 'retire_income' => 'Desired monthly income after retirement', 'retirement_goal' => 'Do you have a plan to meet your retirement goals?', 'own_business' => 'Do you own a business?', 'comments' => 'Comments', 'address' => 'Address');
		
		$message .= '<table border="0" cellspacing="0" cellpadding="10">
			<tr>
				<th width="50%">Field</th>
				<th width="50%">Value</th>
			</tr>';
		foreach($label_fields as $k=>$v){
			$message .= '<tr>
				<td>'.$v.'</td>
				<td>'.$res->$k.'</td>
			</tr>';
		}
		$message .= '</table>';
		NTM_mail_template::send_mail($email, 'New Lead confirmation notification.', $message);
	}

	function payment_options()
	{
		
		?>

	<div class="wrap">
		<h2>Payment Settings</h2>
		<form method="post" action="options.php">
		
			<?php settings_fields('fa_lead_settings_group'); ?>	
			
			<h3 class="title">Payment Options</h3>
			
			<table class="form-table">
				<tbody>
					

				<?php if(is_multisite() && is_super_admin() && is_main_site()){?>
					<tr valign="top">	
						<th scope="row" valign="top">
							Enter Stripe API Key
						</th>
						<td>
							<input id="fa_lead_settings[api_key]" name="fa_lead_settings[api_key]" class="large-text" type="text" value="<?php echo $this->fa_lead_options['api_key']; ?>"/>
							<label class="description" for="fa_lead_settings[api_key]">Stripe Api Key</label>
						</td>
					</tr>

					<tr valign="top">	
						<th scope="row" valign="top">
							Admin Fee
						</th>
						<td>
							<input id="fa_lead_settings[admin_fee]" name="fa_lead_settings[admin_fee]" class="small-text" type="text" value="<?php echo $this->fa_lead_options['admin_fee']; ?>"/>
							<label class="description" for="fa_lead_settings[admin_fee]">Lead Admin Fee</label>
						</td>
					</tr>
					
					<tr valign="top">	
						<th scope="row" valign="top">
							Gift Amount
						</th>
						<td>
							<input id="fa_lead_settings[init_gift]" name="fa_lead_settings[init_gift]" class="small-text" type="text" value="<?php echo $this->fa_lead_options['init_gift']; ?>"/>
							<label class="description" for="fa_lead_settings[init_gift]">Initial Gift Amount.</label>
						</td>
					</tr>

					<?php }else{?>
					<tr valign="top">	
						<th scope="row" valign="top">
							User Waiting Page
						</th>
						<td>
							<?php wp_dropdown_pages( array("name" => 'fa_lead_settings[waiting_page]', 'selected' => $this->fa_lead_options['waiting_page']) ); ?> 
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" valign="top">
							Appointment Page
						</th>
						<td>
							<?php wp_dropdown_pages( array("name" => 'fa_lead_settings[appointment_page]', 'selected' => $this->fa_lead_options['appointment_page']) ); ?> 
						</td>
					</tr>
					<?php }?>
				</tbody>
			</table>
			
			<p class="submit">
				<input type="submit" class="button-primary" value="Save Options" />
			</p>
		
		</form>
	<?php



	}
 }
 
 new FA_Support();
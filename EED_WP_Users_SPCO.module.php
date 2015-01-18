<?php
/**
 * This file contains the module for the EE WP Users addon
 *
 * @since 1.0.0
 * @package  EE WP Users
 * @subpackage modules
 */
if ( ! defined('EVENT_ESPRESSO_VERSION')) exit('No direct script access allowed');
/**
 *
 * EED_WP_Users_SPCO module.  Takes care of WP Users integration with SPCO.
 *
 * @since 1.0.0
 *
 * @package		EE WP Users
 * @subpackage	modules
 * @author 		Darren Ethier
 *
 * ------------------------------------------------------------------------
 */
class EED_WP_Users_SPCO  extends EED_Module {


	/**
	 * All frontend hooks.
	 */
	public static function set_hooks() {
		//hooks into spco
		/**
		 * @todo At some point may provide users the option to toggle whether they want
		 * changes made in the registration form to be synced with their user profile.  However,
		 * we need to work out:
		 * 	- If users answer yes, then any existing EE_Attendee record for this user would
		 * 	have to be updated instead of a new one created (especially in the case of where
		 * 	any personal system question answers change).  Also the wp_user profile fields
		 * 	are updated.
		 *
		 * 	- If users answer no, then what happens?  The existing EE_Attendee record (if
		 * 	any) would have to be left alone, the existing wp user record would be left alone.
		 * 	However, we would not be able to attached the new attendee record to the user
		 * 	profile because only ONE should really be attached (otherwise how woudl autofill
		 * 	of forms work?).  So perhaps what we'd do when "no" is answered is a new
		 * 	attendee record is created but just not attached to the user id?  That means there
		 * 	would be no record of attendee or registration on that user profile (which might be
		 * 	okay?)
		 *
		 * In the meantime, for the first iteration, if the user is logged in we assume that the
		 * primary registrant data that changes is ALWAYS synced with their user profile (and
		 * we'll show a notice to that affect).
		 */
		//add_filter( 'FHEE__EE_SPCO_Reg_Step_Attendee_Information__question_group_reg_form__subsections_array', array( 'EED_WP_Users_SPCO', 'reg_checkbox_for_sync_info' ), 10, 4 );
		//add_filter( 'FHEE__EE_SPCO_Reg_Step_Attendee_Information___save_registration_form_input', array( 'EED_WP_Users_SPCO', 'process_wp_user_inputs' ), 10, 5 );

		add_filter( 'FHEE__EEH_Form_Fields__generate_question_groups_html__after_question_group_questions', array( 'EED_WP_Users_SPCO', 'primary_reg_sync_messages' ), 10, 4 );

		add_filter('FHEE__EEM_Answer__get_attendee_question_answer_value__answer_value', array('EED_WP_Users_SPCO', 'filter_answer_for_wpuser'), 10, 3);
		add_filter( 'FHEE_EE_Single_Page_Checkout__save_registration_items__find_existing_attendee', array( 'EED_WP_Users_SPCO', 'maybe_sync_existing_attendee' ), 10, 3 );

		add_filter( 'FHEE__EE_SPCO_Reg_Step_Attendee_Information___process_registrations__pre_registration_process', array( 'EED_WP_Users_SPCO', 'verify_user_access' ), 10, 6 );

		add_action('AHEE__EE_Single_Page_Checkout__process_attendee_information__end', array('EED_WP_Users_SPCO', 'process_wpuser_for_attendee'), 10, 2);


		//hook into spco for styles and scripts.
		add_action( 'AHEE__EED_Single_Page_Checkout__enqueue_styles_and_scripts__attendee_information', array( 'EED_WP_Users_SPCO', 'enqueue_scripts_styles' ) );
	}



	/**
	 * All admin hooks (and ajax)
	 */
	public static function set_hooks_admin() {

		//hook into filters/actions done on ajax but ONLY EE_FRONT_AJAX requests
		if (  EE_FRONT_AJAX ) {
			add_filter( 'FHEE_EE_Single_Page_Checkout__save_registration_items__find_existing_attendee', array( 'EED_WP_Users_SPCO', 'maybe_sync_existing_attendee' ), 10, 3 );

			add_filter( 'FHEE__EE_SPCO_Reg_Step_Attendee_Information___process_registrations__pre_registration_process', array( 'EED_WP_Users_SPCO', 'verify_user_access' ), 10, 6 );

			add_action('AHEE__EE_Single_Page_Checkout__process_attendee_information__end', array('EED_WP_Users_SPCO', 'process_wpuser_for_attendee'), 10, 2);
		}

		add_action('AHEE__event_tickets_datetime_ticket_row_template_before_close', array('EED_WP_Users_SPCO', 'insert_ticket_meta_interface'), 10, 1);
	}



	/**
	 * Callback for AHEE__EED_Single_Page_Checkout__enqueue_styles_and_scripts__attendee_information
	 * used to register and enqueue scripts for wp user integration with spco.
	 *
	 *
	 * @since 1.0.0
	 * @param EED_Single_Page_Checkout $spco
	 *
	 * @return void
	 */
	public static function enqueue_scripts_styles( EED_Single_Page_Checkout $spco ) {
		wp_register_script( 'eea-wp-users-integration-spco', EE_WPUSERS_URL . 'assets/js/eea-wp-users-integration-spco.js', array( 'single_page_checkout' ), EE_WPUSERS_VERSION, TRUE );
		wp_enqueue_script( 'eea-wp-users-integration-spco' );
	}



	/**
	 * Needs to be defined because it is abstract
	 *
	 * @since 1.0.0
	 * @param WP $WP
	 *
	 * @return void
	 */
	public function run ( $WP ) {}





	/**
	 * callback for FHEE__EEH_Form_Fields__generate_question_groups_html__after_question_group_questions.
	 * Used to add a message in certain conditions for the logged in user about syncing of answers
	 * given in the reg form with their user profile.
	 *
	 * @param string                                $content        Any content already added here.
	 * @param EE_Registration                       $registration
	 * @param EE_Question_Group                     $question_group
	 * @param EE_SPCO_Reg_Step_Attendee_Information $spco
	 *
	 * @return string                                content to retun
	 */
	public static function primary_reg_sync_messages( $content, EE_Registration $registration, EE_Question_Group $question_group, EE_SPCO_Reg_Step_Attendee_Information $spco ) {
		if ( ! is_user_logged_in() || ( is_user_logged_in() && ! $registration->is_primary_registrant() ) ) {
			return $content;
		}

		return $content . '<br><div class="highlight-bg">' . sprintf( __('%1$sNote%2$s: Changes made in these answers will be synced with your user profile.', 'event_espresso' ), '<strong>', '</strong>' ) . '</div>';
	}




	/**
	 * callback for FHEE__EE_SPCO_Reg_Step_Attendee_Information__question_group_reg_form__subsections_array
	 * with the purpose of outputting confirmation checkbox for users to indicate they wish changes in
	 * the reg form to be reflected on the profile attached to their account.  Note this ONLY should
	 * appear if the user is logged in.
	 *
	 * @param array                                $form_subsections        existing form subsections
	 * @param EE_Registration                       $registration
	 * @param EE_Question_Group                     $question_group
	 * @param EE_SPCO_Reg_Step_Attendee_Information $spco
	 *
	 * @return string                                content
	 */
	public static function reg_checkbox_for_sync_info( $form_subsections, EE_Registration $registration, EE_Question_Group $question_group, EE_SPCO_Reg_Step_Attendee_Information $spco ) {
		if ( ! is_user_logged_in() || ! $registration->is_primary_registrant() ) {
			return $form_subsections;
		}
		$identifier = 'sync_with_user_profile';
		$input_constructor_args = array(
			'html_name' => 'ee_reg_qstn[' . $registration->reg_url_link() . '][' . $identifier .']',
			'html_id' => 'ee_reg_qstn-' . $registration->reg_url_link() . '-' . $identifier,
			'html_class' => 'ee-reg-qstn',
			'required' => TRUE,
			'html_label_id' => 'ee_reg_qstn-' . $registration->reg_url_link() . '-' . $identifier,
			'html_label_class' => 'ee-reg-qstn',
			'html_label_text' => __( 'Sync changes with your user profile?', 'event_espresso' ),
			'default' => TRUE
			);

		$form_subsections[$identifier] = new EE_Yes_No_Input( $input_constructor_args );/**/
		return $form_subsections;
	}




	/**
	 * callback for FHEE__EE_SPCO_Reg_Step_Attendee_Information___save_registration_form_input
	 * that we'll read to remove and process any form input injected by WP_User_Integration into the
	 * registration process.
	 *
	 * @param bool                                 $processed    return true to stop normal spco processing of
	 *                                                           	        input.
	 * @param EE_Registration                       $registration
	 * @param string                                $form_input   The input.
	 * @param mixed                                $input_value  The normalized input value.
	 * @param EE_SPCO_Reg_Step_Attendee_Information $spco
	 *
	 * @return bool                                return true to stop normal spco processing or false to keep it
	 *                                                      going.
	 */
	public static function process_wp_user_inputs( $processed, EE_Registration $registration, $form_input, $input_value, EE_SPCO_Reg_Step_Attendee_Information $spco ) {
		if ( $form_input == 'sync_with_user_profile' ) {
			return TRUE;
		}
		return FALSE;
	}



	/**
	 * Added to filter that processes the return to the registration form of whether and answer to the question exists for that
	 * @param type $value
	 * @param type $registration
	 * @param type $question_id
	 * @return type
	 */
	public static function filter_answer_for_wpuser($value, $registration, $question_id) {
		if (empty($value)) {
			$current_user = wp_get_current_user();

			if ($current_user instanceof WP_User) {
				switch ($question_id) {

					case 1:
						$value = $current_user->get('first_name');
						break;

					case 2:
						$value = $current_user->get('last_name');
						break;

					case 3:
						$value = $current_user->get('user_email');
						break;

					default:
				}
			}
		}
		return $value;
	}





	/**
	 * callback for FHEE__EE_SPCO_Reg_Step_Attendee_Information___process_registrations__pre_registration_process.
	 * In this callback we check if the submitted email address:
	 * 	- matches the email address of a user in the system.
	 * 	- If it does, then we have logic to determine whether we fail or pass the registration
	 * 	depending on user privileges.
	 *
	 *
	 * @param bool                                $stop_processing This is what the current process is set at. If
	 *                                                             		  TRUE, then we should just return because
	 *                                                             		  it means another plugin already failed the
	 *                                                             		  processing.
	 * @param EE_Registration                       $registration
	 * @param EE_Registration[]                     $registrations
	 * @param array                                	       $valid_data      incoming post data.
	 * @param EE_SPCO_Reg_Step_Attendee_Information $spco
	 *
	 * @return bool                                false to NOT stop the process, TRUE to stop the process.
	 */
	public static function verify_user_access( $stop_processing, $att_nmbr, EE_Registration $registration, $registrations, $valid_data, EE_SPCO_Reg_Step_Attendee_Information $spco ) {
		$field_input_error = '';
		if ( $att_nmbr !== 0 || $stop_processing  ) {
			//get out because we've already either verified things or another plugin is halting things.
			return $stop_processing;
		}

		//we need to loop through each valid_data[$registration->reg_url_link()] set of data to see if there is a user existing for that email address.  If there is then halt the presses!
		foreach ( $registrations as $registration ) {
			//if not a valid $reg then we'll just ignore and let spco handle it
			if ( ! $registration instanceof EE_Registration ) {
				return $stop_processing;
			}

			$reg_url_link = $registration->reg_url_link();
			if ( isset( $valid_data[$reg_url_link] ) ) {
				foreach ( $valid_data[$reg_url_link]  as $form_section => $form_inputs ) {
					if ( ! is_array( $form_inputs ) ) {
						return $stop_processing;
					}
					foreach ( $form_inputs as $form_input => $input_value ) {
						if ( $form_input == 'email' && ! empty( $input_value ) ) {
							$user = get_user_by( 'email', $input_value );
							if ( ! $user instanceof WP_User ) {
								continue;
							}

							//we have a user for that email address.  If the person doing the transaction is logged in, let's verify that this email address matches theirs.
							if ( is_user_logged_in() ) {
								$current_user = get_userdata( get_current_user_id() );
								if ( $current_user->user_email == $user->user_email ) {
									continue;
								} else {
									EE_Error::add_error( __('You have entered an email address that matches an existing user account in our system.  You can only submit registrations for your own account or for a person that does not exist in the system.  Please use a different email address.', 'event_espresso' ), __FILE__, __FUNCTION__, __LINE__ );
									$stop_processing = TRUE;
									$field_input_error = 'ee_reg_qstn-' . $reg_url_link . '-email';
								}
							} else {
								//user is NOT logged in, so let's prompt them to log in.
								EE_Error::add_error( __('You have entered an email address that matches an existing user account in our system.  If this is your email address, please log in before continuing your registration. Otherwise, register with a different email address.', 'event_espresso' ), __FILE__, __FUNCTION__, __LINE__ );
								$stop_processing = TRUE;
								$field_input_error = 'ee_reg_qstn-' . $reg_url_link . '-email';
							}
						}
					}

					if ( $stop_processing ) {
						$spco->checkout->json_response->set_return_data( array(
							'wp_user_response' => array(
								'require_login' => true,
								'show_login_form' => false,
								'show_errors_in_context' => true,
								'validation_error' => array(
									'field' => array( $field_input_error )
									)
								)
							));
						return $stop_processing;
					}
				}
			}

		}
		return $stop_processing;
	}





	/**
	 * This is the callback for FHEE_EE_Single_Page_Checkout__save_registration_items__find_existing_attendee
	 * In this callback if the user is logged in and the registration being processed is the primary
	 * registration, then we will make sure we're always updating the existing attendee record
	 * attached to the wp_user regardless of what might have been detected by spco.
	 *
	 * @param mixed null|EE_Attendee          $existing_attendee Possibly an existing attendee
	 *                                        					  already detected by SPCO
	 * @param EE_Registration $registration
	 * @param array $attendee_data array of core personal data used to verify if existing attendee
	 *                             		      exists.
	 *
	 * @return EE_Attendee|null
	 */
	public static function maybe_sync_existing_attendee( $existing_attendee, EE_Registration $registration, $attendee_data ) {
		if ( ! is_user_logged_in() || ( is_user_logged_in() && ! $registration->is_primary_registrant( ) ) ) {
			return $existing_attendee;
		}

		$user = get_userdata( get_current_user_id() );

		if ( ! $user instanceof WP_User ) {
			return $existing_attendee;
		}

		//existing attendee on user?
		$att =  self::get_attendee_for_user( $user );

		/**
		 * if there already IS an existing attendee then that means the system found one matching
		 * the first_name, last_name, and email address that is incoming.  If this attendee is NOT
		 * what is attached to the user, then we'll change the firstname and lastname but not the
		 * email address.  Otherwise we could end up with two wpusers in the system with the
		 * same email address.
		 */
		if ( ! $att instanceof EE_Attendee ) {
			return $existing_attendee;
		}

		if ( $existing_attendee instanceof EE_Attendee && $att->ID() != $existing_attendee->ID() ) {
			//only change first and last name for att, we'll leave the email address alone regardless of what its at.
			if ( ! empty( $attendee_data['ATT_fname'] ) ) {
				$att->set_fname( $attendee_data['ATT_fname'] );
			}

			if ( ! empty( $attendee_data['ATT_lname'] ) ) {
				$att->set_lname( $attendee_data['ATT_lname'] );
			}
		} else {
			//change all
			if ( ! empty( $attendee_data['ATT_fname'] ) ) {
				$att->set_fname( $attendee_data['ATT_fname'] );
			}

			if ( ! empty( $attendee_data['ATT_lname'] ) ) {
				$att->set_lname( $attendee_data['ATT_lname'] );
			}

			if ( ! empty( $attendee_data['ATT_email'] ) ) {
				$att->set_email( $attendee_data['ATT_email'] );
			}
		}

		return $att;
	}





	/**
	 * callback for AHEE__EE_Single_Page_Checkout__process_attendee_information__end
	 * Here's what happens in this callback:
	 * 	- currently only action happens on the primary registrant.
	 * 	- If user is logged in then updates etc were already taken care of for EE_Attendee via
	 * 	  self::maybe_sync_existing_attendee (cause we returned the attached attendee for the
	 * 	  user to the attendee processor).  However, we will sync the given details with the WP
	 * 	  User Profile.
	 * 	 - If user is NOT logged in, then we create a user for the primary registrant data but ONLY
	 * 	   if there is not already a user existing for the given attendee data.
	 * 	 - @todo the above step will only get done if admins have flagged for new users to get
	 * 	    created on registration.
	 *
	 *
	 * @param EE_SPCO_Reg_Step_Attendee_Information $spco
	 * @param array                                $valid_data The incoming form post data (that has already
	 *                                                         		      been validated)
	 *
	 * @return void
	 */
	public static function process_wpuser_for_attendee( EE_SPCO_Reg_Step_Attendee_Information $spco, $valid_data) {
		$user_created = FALSE;
		$att_id = '';

		//use spco to get registrations from the
		$registrations = self::_get_registrations( $spco );
		foreach ($registrations as $registration) {

			//is this the primary registrant?  If not, continue
			if ( ! $registration->is_primary_registrant() ) {
				continue;
			}

			$attendee = $registration->attendee();

			if ( ! $attendee instanceof EE_Attendee ) {
				//should always be an attendee, but if not we continue just to prevent errors.
				continue;
			}

			//if user logged in, then let's just use that user.  Otherwise we'll attempt to get a
			//user via the attendee info.
			if ( is_user_logged_in() ) {
				$user = get_userdata( get_current_user_id() );
			} else {
				//is there already a user for the given attendee?
				$user = get_user_by( 'email', $attendee->email() );

				//does this user have the same att_id as the given att?  If NOT, then we do NOT update because it's possible there was a family member or something sharing the same email address but is a different attendee record.
				$att_id = $user instanceof WP_User ? get_user_meta( $user->ID, 'EE_Attendee_ID', TRUE ) : $att_id;
				if ( ! empty( $att_id ) && $att_id != $attendee->ID() ) {
					return;
				}
			}


			//no existing user? then we'll create the user from the date in the attendee form.
			if ( ! $user instanceof WP_User ) {
				$password = wp_generate_password( 12, false );
				$user_id = wp_create_user( apply_filters( 'FHEE__EED_WP_Users_SPCO__process_wpuser_for_attendee__username', $attendee->email(), $password, $attendee->email() ), $password, $attendee->email() );
				$user_created = TRUE;
				if ( $user_id instanceof WP_Error ) {
					return; //get out because something went wrong with creating the user.
				}
				$user = new WP_User( $user_id );
			}

			wp_update_user(
				array(
					'ID' => $user->ID,
					'nickname' => $attendee->fname(),
					'display_name' => $attendee->full_name(),
					'first_name' => $attendee->fname(),
					'last_name' => $attendee->lname(),
					'description' => apply_filters( 'FHEE__EED_WP_Users_SPCO__process_wpuser_for_attendee__user_description_field', __( 'Registered via event registration form', 'event_espresso' ), $user, $attendee, $registration )
					)
				);


			//if user created then send notification and attach attendee to user
			if ( $user_created ) {
				do_action( 'AHEE__EED_WP_Users_SPCO__process_wpuser_for_attendee__user_user_created', $user, $attendee, $registration );
				//set user role
				//@todo let's make this an option set via the admin.
				$user->set_role('subscriber');
				update_user_meta( $user->ID, 'EE_Attendee_ID', $attendee->ID() );
			}

			//failsafe just in case this is a logged in user not created by this system that has never had an attendee record attached.
			$att_id = empty( $att_id ) ? get_user_meta( $user->ID, 'EE_Attendee_ID', true ) : $att_id;
			if ( empty( $att_id ) ) {
				update_user_meta( $user->ID, 'EE_Attendee_ID', $attendee->ID() );
			}
		} //end registrations loop
	}




	/**
	 * This grabs all the registrations from the given object.
	 *
	 * @param EE_SPCO_Reg_Step_Attendee_Information $spco
	 *
	 * @return EE_Registration[]
	 */
	public static function _get_registrations( EE_SPCO_Reg_Step_Attendee_Information $spco ) {
		$registrations = array();
		if ( $spco->checkout instanceof EE_Checkout && $spco->checkout->transaction instanceof EE_Transaction ) {
			$registrations = $spco->checkout->transaction->registrations( $spco->checkout->reg_cache_where_params, TRUE );
		}
		return $registrations;
	}



	public static function insert_ticket_meta_interface($TKT_ID) {
		$Ticket_model = EEM_Ticket::instance();
		$ticket = $Ticket_model->get_one_by_ID($TKT_ID);
		if ($ticket instanceof EE_Ticket) {
			$template_args = array(
				'TKT_WPU_meta' => $ticket->get_extra_meta('TKT_WPU_meta', TRUE),
				'ticket_meta_help_link' => ''
			);
			$template = EE_WPUSERS_TEMPLATE_PATH . 'event_tickets_datetime_ticket_row_metadata.template.php';
			EEH_Template::locate_template($template, $template_args, TRUE, FALSE);
		}
	}




	/**
	 * Returns the EE_Attendee object attached to the given wp user.
	 *
	 * @param mixed WP_User | int $user_or_id can be WP_User or the user_id.
	 *
	 * @return EE_Attendee|null
	 */
	public static function get_attendee_for_user( $user_or_id ) {
		$user_id = $user_or_id instanceof WP_User ? $user_or_id->ID : (int) $user_or_id;
		$attID = get_user_meta( $user_id, 'EE_Attendee_ID', true );
		$attendee = null;
		if ( $attID ) {
			$attendee = EEM_Attendee::instance()->get_one_by_ID( $attID );
			$attendee = $attendee instanceof EE_Attendee ? $attendee : null;
		}
		return $attendee;
	}

} //end EED_WP_Users_SPCO class

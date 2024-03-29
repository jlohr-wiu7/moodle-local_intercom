<?php
// This file is part of the Intercom local plugin for Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library of interface functions and constants for module intercom
 *
 * All the core Moodle functions, needed to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * @package     local_intercom
 * @copyright   2020 Westmoreland IU <jlohr@wiu7.org>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_intercom;

defined('MOODLE_INTERNAL') || die();

/**
 * Class helper
 *
 * @package     local_intercom
 * @copyright   2020 Westmoreland IU <jlohr@wiu7.org>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
	
	/**
	* Generate the Intercom Javascript to embed.
	*
	* @param null|stdClass $context Context instance
	* @param null|stdClass $course Related course instance
	* @return null|string A string containing the Intercom embed code, otherwise, null.
	*/
	public function embed_intercom($context, $course) {
		global $USER;
		global $CFG;
		global $SITE;

	// Trap any catchable error.
	try {	
	    // Grab settings for the plugin from the database
	    $app_id = get_config('local_intercom', 'app_id');
			$id_verification_secret = get_config('local_intercom', 'id_verification_secret');

			$user_hash = hash_hmac(
				'sha256', // hash function
				$SITE->shortname."-".$USER->id, // user's id
				$id_verification_secret // secret key
			);

			// Get active course info
			if(!empty($course)){
				$course_title = empty($course->fullname) ? $course->name : $course->fullname;
				$course_title = format_string($course_title, true, array('context' => \context_system::instance()));
				$course_desc = "";
				if (!empty($course->summary)) {
					$course_desc = format_text($course->summary, FORMAT_MOODLE, array('context' => \context_system::instance(), 'newlines' => true, 'nocache' => true, 'para' => false));
					$course_desc = preg_replace("/\r|\n/", "", $course_desc);
				}

				// Get roles for the active course
				$course_roles = array();
				$course_roles_str = "";
				$context = \context_course::instance($course->id);
				if($roles = get_user_roles($context, $USER->id)){
					foreach ($roles as $role){
						$course_roles[] = $role->shortname;
					}
					$course_roles_str = implode(", ", $course_roles);
				}
			}

			// Get all user roles across all course contexts for this user
			// While this is frowned upon and cannot be relied upon, it can provide meaningful data in most instances
			// Will need to check for capabilities instead at some point
			/*
			$user_roles = array();
			if(user_has_role_assignment($USER->id, 1, 0)){
				$user_roles[] = "manager";
			}
			if(user_has_role_assignment($USER->id, 2, 0)){
				$user_roles[] = "coursecreator";
			}
			if(user_has_role_assignment($USER->id, 3, 0)){
				$user_roles[] = "editingteacher";
			}
			if(user_has_role_assignment($USER->id, 4, 0)){
				$user_roles[] = "teacher";
			}
			if(user_has_role_assignment($USER->id, 5, 0)){
				$user_roles[] = "student";
			}
			if(in_array($USER->id, explode(",",$CFG->siteadmins))){
				$user_roles[] = "siteadmin";
			}
			$user_roles_str = implode(", ", $user_roles);
			*/
		
			$username = isset($USER->username) ? $USER->username : "guest" ;
			$firstname  = isset($USER->firstname) ? $USER->firstname : "" ;
			$lastname  = isset($USER->lastname) ? $USER->lastname : "" ;
			$email  = isset($USER->email) ? $USER->email : "" ;
			$firstaccess = isset($USER->firstaccess) ? $USER->firstaccess : "" ;

			// handle guest users differntly
			if($username == "guest"){
				$embed_code = 
				'<script>
					window.intercomSettings = {
						app_id: "'.$app_id.'",
						company: {
							id: "'.$SITE->shortname.'",
							name: "'.$SITE->fullname.'",
							website: "'.$CFG->wwwroot.'"
						},
					};
				</script>';
			}else{
				// Build the JS code to embed
				$embed_code = 
				'<script>
					window.intercomSettings = {
						app_id: "'.$app_id.'",
						company: {
							id: "'.$SITE->shortname.'",
							name: "'.$SITE->fullname.'",
							website: "'.$CFG->wwwroot.'"
						},
						moodle_version: "Moodle '.$CFG->release.'",
						user_id: "'.$SITE->shortname.'-'.$USER->id.'",
						username: "'.$username.'",
						name: "'.$firstname.' '.$lastname.'",
						email: "'.$email.'",
						user_hash: "'.$user_hash.'",
						created_at: '.$firstaccess.',';
				if(!empty($course)){
					$embed_code .= '
						active_course_title: "'.$course_title.'",
						active_course_shortname: "'.$course->shortname.'",
						active_course_description: "'.strip_tags($course_desc).'",
						active_course_id: '.$course->id.',
						active_course_roles: "'.$course_roles_str.'"
					};
				</script>';
				}else{
					$embed_code .= '
					};
				</script>';
				}
			}
			
			$embed_code .= "<script>(function(){var w=window;var ic=w.Intercom;if(typeof ic==='function'){ic('reattach_activator');ic('update',w.intercomSettings);}else{var d=document;var i=function(){i.c(arguments);};i.q=[];i.c=function(args){i.q.push(args);};w.Intercom=i;var l=function(){var s=d.createElement('script');s.type='text/javascript';s.async=true;s.src='https://widget.intercom.io/widget/".$app_id."';var x=d.getElementsByTagName('script')[0];x.parentNode.insertBefore(s,x);};if(w.attachEvent){w.attachEvent('onload',l);}else{w.addEventListener('load',l,false);}}})();</script>";

			return $embed_code;
	} catch (Exception $e) {
	    // Do nothing here.
	    return null;
	}

	return null;
	}
}
<?php
/*
	Plugin Name: Raven's Antispam
	Plugin URI: http://kahi.cz/wordpress/ravens-antispam-plugin/
	Description: Smart antispam based on a JavaScript method. No questions, great efficiency and maximal accessibility - everything you can need.
	Author: Peter Kahoun
	Author URI: http://kahi.cz
	Version: 2.1
*/


/*
Note: see end of file.
Note for myself: based on deprecated architecture for PHP4, non-static! (Plan: stop support PHP4 since 2009)
*/


class RavensAntispam {
	var
		$answer,
		$answer_yesterday,
		$name,

		$temp_template_code,
		$default_template_code,
		$unique_string,

		$abbr = 'ras',
		$short_name = 'Raven\'s antispam',
		$full_name = 'Raven\'s antispam';



	// constructors
	function RavensAntispam () {
		$this->__construct();
	}


	function __construct () {

		add_action ('comment_form', array(&$this, 'comment_form'), 0);
		add_action ('comment_post', array(&$this, 'comment_post'));
		add_action ('admin_menu',   array(&$this, 'admin_menu'));
		
		// set unique string (makes some random strings different among websites)
		$this->unique_string = get_option('siteurl');

		// set $dir_name
		$dir_name = str_replace('\\', '/', dirname(__FILE__));
		$dir_name = trim(substr($dir_name, strpos($dir_name, '/plugins/')+9), '/');

		// enable localization
		load_plugin_textdomain($this->abbr, 'wp-content/plugins/' . $dir_name . '/languages/');
		
		$this->default_template_code = '<p><label for="%name%g">'. __('Please type', $this->abbr) . ' "%answer%": </label><input type="text" name="%name%" id="%name%g" /></p>
			<p><label for="%name2%e">'. __('Leave this field empty please', $this->abbr) . ': </label><input type="text" name="%name2%" id="%name2%e" /></p>
			';

	}



	// improves comment-form
	function comment_form ($form) {
		global $user_ID;

		$this->name = $this->GenerateName();
		$this->answer = $this->GenerateAnswer();
		$this->name2 = $this->GenerateName2();
		$this->answer2 = $this->GenerateAnswer2();


		// split correct answer into two parts - because of more complicated parsing for spambots
		$answer_len = strlen($this->answer);
		$answer_splitpoint = rand(1, $answer_len-1);
		$answer_array[0] = substr($this->answer, 0, $answer_splitpoint);
		$answer_array[1] = substr($this->answer, $answer_splitpoint);


		// for camino & seamonkey browsers (problems reported) display
		$ua = strtolower($_SERVER['HTTP_USER_AGENT']);

		// logged user don't need to be tested
		if ($user_ID) {
		?>

		<!-- you're a logged user - raven's antispam thinks it's not necessary to print antispam question -->

		<?php
		}

		// for camino & seamonkey browsers (problems reported) display
		elseif (
			(false !== strpos($ua, 'seamonkey'))
			OR (false !== strpos($ua, 'camino'))
			OR RAS_QUESTION_ALWAYS_VISIBLE) {

		?>

		<?php echo $this->GetTemplateCode(); ?>

		<?php

		// for other browsers - add hidden input by script & visible input inside <noscript>
		} else {

		?>

		<script type="text/javascript">/* <![CDATA[ */ document.write('<div><input type="hidden" name="<?php echo $this->name; ?>" value="<?php echo $answer_array[0]; ?>' + '<?php echo $answer_array[1]; ?>" \/><input type="hidden" name="%name2%" value="'+'"><\/div>'); /* ]]> */</script>
		<noscript><?php echo $this->GetTemplateCode(); ?></noscript>

		<?php

		}
	}


	// returns template code
	function GetTemplateCode () {

		if (!empty($this->temp_template_code)) {
			return $this->temp_template_code;
		} else {

			// load settings
			if (($_own_template_code = get_option($this->abbr . '_own_template_code')) == 1) {
				$_template_code = get_option($this->abbr . '_template_code');
			}


			// get raw template code
			$template_code = ($_own_template_code) ? $_template_code : $this->default_template_code;


			// make the final template code
			$template_code = str_replace(
				array('%name%', '%answer%', '%name2%', '%answer2%'),
				array($this->name, $this->answer, $this->name2, $this->answer2),
				$template_code);


			// cache the code for this instance
			$this->temp_template_code = $template_code;

			return $template_code;

		}

	}



	// Checks user's answer
	function comment_post ($post_ID) {

		global $comment_content, $comment_type, $user_ID;

		$this->name = $this->GenerateName();
		$this->answer = $this->GenerateAnswer();
		$this->name2 = $this->GenerateName2();
		$this->answer2 = $this->GenerateAnswer2();
		$this->answer_yesterday = $this->GenerateAnswer('yesterday');

		$user_answer = trim($_POST[$this->name]);
		$user_answer2 = trim($_POST[$this->name2]);

		$errors['empty'] = __('You didn\'t answer the antispam question, your comment <strong>was not saved</strong>. Press "Back" and answer the question. <br />Just to be sure that your message won\'t be lost - copy it now to the clipboard.', $this->abbr);
		$errors['wrong'] = __('You didn\'t answer the antispam question correctly, your comment <strong>was not saved</strong>. Press "Back" and answer the question better. <br />Just to be sure that your message won\'t be lost - copy it now to the clipboard.', $this->abbr);
				
		// not a trackback
		if ( $comment_type === '' AND !$user_ID) {

			// not filled
			if ( $user_answer == '' ) {
				$this->Flashback ($post_ID);
				wp_die($errors['empty'] .'<br /><br /><em>'. $comment_content .'</em>');

			}

			// filled wrong
			elseif (($user_answer2 != $this->answer2) 
				OR (( $user_answer != $this->answer ) AND !( date('G') == 0 AND $user_answer == $this->answer_yesterday )) ) {
				
					$this->Flashback ($post_ID);
					wp_die($errors['wrong'] .'<br /><br /><em>'. $comment_content .'</em>');					
				
			}

		}

		// else OK!
		return $post_ID;
	}



	// Well, the comment was saved already, so delete it... :( // This code was copied from "Did you pass math" plugin, thank you!
	function Flashback ($post_ID) {

		global $wpdb, $comment_count_cache;

		$entry_id = $_POST['comment_post_ID'];

		// Delete it...
		$wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_ID = {$post_ID}");

		// Recount the comments
		$count = $wpdb->get_var("SELECT COUNT(*) from $wpdb->comments WHERE comment_post_id = {$entry_id} AND comment_approved = '1'");
		$wpdb->query("UPDATE $wpdb->posts SET comment_count = $count WHERE ID = {$entry_id}");
		$comment_count_cache[$entry_id]--;

	}



	// Correct answer changes everyday
	function GenerateAnswer ($par = 'today') {

		$time = ('yesterday' == $par) ? time()-24*3600 : time();
		$day = date('j', $time);

		$mix = 'raven!'.$day.$this->unique_string;
		return substr(md5($mix),3,6);

	}
	
	// 
	function GenerateAnswer2 () {

		return '';

	}



	// Every installation carries different input-name
	function GenerateName () {

		$mix = 'raven?'.$this->unique_string;
		return 'url'. substr(md5($mix),16,4);
		// return 'web_site_url';

	}

	// Every installation carries different input-name
	function GenerateName2 () {

		$mix = 'raven?'.$this->unique_string;
		return substr(md5($mix),0,6);

	}



	// Hook: Action: admin_menu
	// Descr: adds own item into menu in administration
	function admin_menu () {

		if (function_exists('add_submenu_page'))
			add_options_page(
				$this->short_name, // page title
				$this->short_name, // menu-item label
				'manage_options',
				__FILE__,
				array (&$this, 'TheSettingsPage')
				);

	}



	// Descr: own settings-page
	// Todo: maybe should be included from another file
	function TheSettingsPage () {

?>

	<div class="wrap" id="<?php echo $this->abbr; ?>">

		<h2>Raven's antispam</h2>

		<form method="post" action="options.php">

			<p><input type="checkbox" name="ras_always_visible" id="i_ras_always_visible" <?php if (get_option('ras_always_visible') == 'on') echo 'checked="checked" '; ?>/>
				<label for="i_ras_always_visible"><?php _e('Display antispam question anyway?', $this->abbr); ?></label></p>


			<p><?php _e('If you don\'t want to use the default template code, you can specify here your own.', $this->abbr); ?></p>


			<p>
				<input type="radio" name="ras_own_template_code" id="i_ras_own_template_code0" class="ras_own_template_code" value="0" <?php if (!get_option('ras_own_template_code')) echo 'checked="checked" '; ?> /><label for="i_ras_own_template_code0"><?php _e('use default template code', $this->abbr); ?></label>
				<br /><input type="radio" name="ras_own_template_code" id="i_ras_own_template_code1" class="ras_own_template_code" value="1" <?php if (get_option('ras_own_template_code')) echo 'checked="checked" '; ?>/><label for="i_ras_own_template_code1"><?php _e('use own template code', $this->abbr); ?></label>:
			</p>

				<p style="padding-left:2em;">
					<br /><textarea name="ras_template_code" id="i_ras_template_code" rows="4" cols="80"><?php if ($t = get_option('ras_template_code')) echo htmlSpecialChars($t); else echo htmlSpecialChars($this->default_template_code); ?></textarea>
					<br /><?php _e('Remember variables <code>%name%</code> and <code>%answer%</code>.', $this->abbr); ?>
					<br /><input type="button" value="<?php _e('Reset the code to default', $this->abbr); ?>" id="ras_reset_button" />
				</p>



				<script>
				jQuery(document).ready( function() {

					jQuery('.ras_own_template_code').change(function () {

						if (jQuery('#i_ras_own_template_code0').attr('checked')) {
							jQuery('#i_ras_template_code').attr('disabled','disabled');
						} else {
							jQuery('#i_ras_template_code').removeAttr('disabled');
						}

					});

					jQuery('.ras_own_template_code').change();

					jQuery('#ras_reset_button').click(function () {
						jQuery('#i_ras_template_code').val('<?php echo str_replace("\n", '', $this->default_template_code); ?>');
					});

				});
				</script>

			<p class="submit">
				<?php wp_nonce_field('update-options') ?>
				<input type="hidden" name="action" value="update" />
				<input type="hidden" name="page_options" value="<?php echo $this->abbr ?>_own_template_code,<?php echo $this->abbr ?>_template_code,<?php echo $this->abbr ?>_always_visible" />
				<input type="submit" name="submit_update" value="<?php _e('Save Changes') ?>" class="button" />
			</p>

		</form>

	</div>

	<?php
	}


} // end of class




define ('RAS_QUESTION_ALWAYS_VISIBLE', get_option('ras_always_visible'));
$ravens_as = new RavensAntispam();


/* This is the end... */
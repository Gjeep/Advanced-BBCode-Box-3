<?php
/**
* 
* @package: phpBB3 :: Advanced BBCode box 3 -> acp
* @version: $Id: acp_abbcode.php, v 1.0.11 2008/10/11 11:10:08 leviatan21 Exp $
* @copyright: leviatan21 < info@mssti.com > (Gabriel) http://www.mssti.com/phpbb3/
* @license: http://opensource.org/licenses/gpl-license.php GNU Public License
* @author: leviatan21 - http://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=345763
**/

/**
* @ignore
**/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* @package acp
**/
class acp_abbcodes
{
	var $u_action;
	var $u_back;
	var $new_config;
	var $submit;
	var $dir;
	
	function main($id, $mode)
	{
		global $db, $user, $auth, $template, $cache;
		global $config, $phpbb_root_path, $phpbb_admin_path, $phpEx;
		
		$user->add_lang(array('acp/styles', 'mods/acp_abbcodes', 'mods/abbcode'));
		
		// Set up general vars
		$action				= request_var('action', '');
		$bbcode_id			= request_var('bbcode_id', '');
		
		$this->tpl_name		= 'acp_abbcodes';
		$this->page_title	= 'ACP_ABBCODES';
		$form_key			= 'acp_abbcodes';
		
		add_form_key($form_key);
		
		$this->u_back 		= $this->u_action;
		$this->submit		= (isset($_POST['submit'])) ? true : false;
		$abbc3_root_path	= ( $phpbb_admin_path ) ? $phpbb_admin_path : $phpbb_root_path ;
		$this->dir 			= $phpbb_root_path . $config['ABBC3_PATH'] ;
		
		// Execute overall actions
		switch ($mode)
		{
			case 'settings':
				
				switch ($action)
				{
					case 'purge_cache':
						$this->purge_cache($id, $mode, $action);
						break;
				}
				$this->abbc3_details( );
				break;
				
			case 'bbcodes'	:
				switch ($action)
				{
					case 'sync':
						$this->resync_abbcodes( );

					// no break;
					case 'newdlb':
						$this->add_division	= (isset($_POST['add_division'])) ? true : false;
						$this->add_linebreak= (isset($_POST['add_linebreak'])) ? true : false;
						$this->add_new_division_or_linebreak( $this->add_division, $this->add_linebreak );

					// no break;
					case 'move_up':
					case 'move_down':
						
						// Get current order id...
						$sql = "SELECT bbcode_order as current_order
							FROM " . BBCODES_TABLE . "
							WHERE bbcode_id = $bbcode_id";
						$result = $db->sql_query($sql);
						
						$current_order = (int) $db->sql_fetchfield('current_order');
						$db->sql_freeresult($result);
						
						if ( ($current_order == 0 && $action == 'move_up') || ($current_order <= 5 && $action == 'move_up') )
						{
							$bbcode_id = null;
							break;
						}
						
						// on move_down, switch position with next order_id...
						// on move_up, switch position with previous order_id...
						$switch_order_id = ($action == 'move_down') ? $current_order + 1 : $current_order - 1;
						
						$sql = "UPDATE " . BBCODES_TABLE . "
							SET bbcode_order = $current_order
							WHERE bbcode_order = $switch_order_id
								AND bbcode_id <> $bbcode_id";
						$db->sql_query($sql);
						
						// Only update the other entry too if the previous entry got updated
						if ($db->sql_affectedrows())
						{
							$sql = "UPDATE " . BBCODES_TABLE . "
								SET bbcode_order = $switch_order_id
								WHERE bbcode_order = $current_order
									AND bbcode_id = $bbcode_id";
							$db->sql_query($sql);
						}
						$bbcode_id = null;
					// no break;

				}
				$this->bbcodes_edit($id, $mode, $action, $bbcode_id);
				break;
		}
	}

	/**
	* Build Frontend with supplied options
	**/
	function abbc3_details( )
	{
		global $db, $user, $template, $phpbb_root_path, $config;
		
		$this->page_title = 'ABBCODES_SETINGS';

	//	$isfounder = ($user->data['user_type'] == USER_FOUNDER) ? true : false;

		$display_vars = array(
			'title'	=> 'ABBCODES_SETINGS',
			'lang'	=> array('mods/abbcode', 'mods/acp_abbcodes', 'acp/attachments'),
			'vars'	=> array(
				'legend1'				=> 'GENERAL_OPTIONS',
				'ABBC3_MOD'				=> array('lang' => 'ABBCODES_DISABLE',			'validate' => 'bool',	'type' => 'radio:yes_no',	'explain'	=> true),
				'ABBC3_BG'				=> array('lang' => 'ABBCODES_BG',				'validate' => 'string',	'type' => 'custom',			'function'	=> 'image_select', 'params' => array($this->dir . '/images/bg', '{CONFIG_VALUE}', 'config[ABBC3_BG]', true, $this->u_action), 'explain' => true),
				'ABBC3_TAB'				=> array('lang' => 'ABBCODES_TAB',				'validate' => 'bool',	'type' => 'radio:yes_no',	'explain'	=> true, 'append' => '&nbsp;&nbsp;<span>[ <img src="' . $this->dir . '/images/dots.gif" alt="" /> ]</span>'),
				'ABBC3_BOXRESIZE'		=> array('lang' => 'ABBCODES_BOXRESIZE',		'validate' => 'bool',	'type' => 'radio:yes_no',	'explain'	=> true),
				
				'legend2'				=> 'CAT_IMAGES',
				'ABBC3_RESIZE'			=> array('lang' => 'ABBCODES_RESIZE',									'type' => 'string',			'explain'	=> true, 'append' => $user->lang['ABBCODES_JAVASCRIPT_EXPLAIN']),
				'ABBC3_RESIZE_METHOD'	=> array('lang' => 'ABBCODES_RESIZE_METHOD',	'validate' => 'string',	'type' => 'custom',			'function'	=> 'method_select', 'params' => array('{CONFIG_VALUE}', 'config[ABBC3_RESIZE_METHOD]'), 'explain' => true),
				
				'ABBC3_RESIZE_SIGNATURE'=> array('lang' => 'ABBCODES_RESIZE_SIGNATURE',	'validate' => 'bool',	'type' => 'radio:yes_no',	'explain'	=> true),
				'ABBC3_MAX_IMG_WIDTH'	=> array('lang' => 'ABBCODES_MAX_IMAGE_WIDTH',	'validate' => 'int',	'type' => 'text:7:15',		'explain'	=> true, 'append' => ' px'),
				'ABBC3_MAX_IMG_HEIGHT'	=> array('lang' => 'ABBCODES_MAX_IMAGE_HEIGHT',	'validate' => 'int',	'type' => 'text:7:15',		'explain'	=> true, 'append' => ' px'),
				'ABBC3_MAX_THUM_WIDTH'	=> array('lang' => 'ABBCODES_MAX_THUMB_WIDTH',	'validate' => 'int',	'type' => 'text:7:15',		'explain'	=> true, 'append' => ' px'),
				
				'legend3'				=> 'ABBC3_BBVIDEO_TAG',
				'ABBC3_VIDEO'			=> array('lang' => 'ABBCODES_VIDEO_SIZE',		'validate' => 'int',	'type' => 'dimension:3:4',	'explain'	=> true, 'append' => ' px'),
				'ABBC3_VIDEO_width'		=> false,
				'ABBC3_VIDEO_height'	=> false,
				
				'legend4'				=> 'ABBC3_UPLOAD_MOVER',
				'upload_path'			=> array('lang'	=> 'UPLOAD_DIR',										'type' => 'string',			'explain'	=> true, 'append' => ' ' . $config['upload_path'] . '/'),
				'ABBC3_UPLOAD_MAX_SIZE'	=> array('lang'	=> 'ATTACH_MAX_FILESIZE',		'validate' => 'int',	'type' => 'text:7:15',		'explain'	=> true, 'append' => ' ' . $user->lang['BYTES']),
				'ABBC3_UPLOAD_EXTENSION'=> array('lang'	=> 'ABBCODES_UPLOAD_EXTENSION',	'validate' => 'string',	'type' => 'textarea:5:40',	'explain'	=> true),
			)
		);
		
		if (isset($display_vars['lang']))
		{
			$user->add_lang($display_vars['lang']);
		}
		
		$this->new_config = $config;
		$cfg_array = (isset($_REQUEST['config'])) ? request_var('config', array('' => '')) : $this->new_config;
		$error = array();
		
		// We validate the complete config if whished
		validate_config_vars($display_vars['vars'], $cfg_array, $error);
		
		// Do not write values if there is an error
		if (sizeof($error))
		{
			$this->submit = false;
		}
		
		// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
		foreach ($display_vars['vars'] as $config_name => $null)
		{
			if (!isset($cfg_array[$config_name]) || strpos($config_name, 'legend') !== false)
			{
				continue;
			}
			
			$this->new_config[$config_name] = $config_value = $cfg_array[$config_name];
			
			if ($this->submit)
			{
				$this->set_config($config_name, $config_value, true);
			}
		}
		
		if ($this->submit)
		{
			add_log('admin', 'LOG_CONFIG_ABBCODES');
			
			if (!sizeof($error))
			{
				trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
			}
			else
			{
				trigger_error($user->lang['LOG_CONFIG_ABBCODES_ERROR'] . adm_back_link($this->u_action), E_USER_WARNING);
			}
		}
		
		// Output relevant page
		foreach ($display_vars['vars'] as $config_key => $vars)
		{
			if (!is_array($vars) && strpos($config_key, 'legend') === false)
			{
				continue;
			}
			
			if (strpos($config_key, 'legend') !== false)
			{
				$template->assign_block_vars('options', array(
					'S_LEGEND'		=> true,
					'LEGEND'		=> ((isset($user->lang[$vars])) ? $user->lang[$vars] : $vars ),
				));
				continue;
			}
			
			$type = explode(':', $vars['type']);
			
			$l_explain = '';
			if ($vars['explain'] && isset($vars['lang_explain']))
			{
				$l_explain = (isset($user->lang[$vars['lang_explain']])) ? $user->lang[$vars['lang_explain']] : $vars['lang_explain'];
			}
			else if ($vars['explain'])
			{
				$l_explain = (isset($user->lang[$vars['lang'] . '_EXPLAIN'])) ? $user->lang[$vars['lang'] . '_EXPLAIN'] : '';
			}
			$template->assign_block_vars('options', array(
				'KEY'			=> $config_key,
				'TITLE'			=> (isset($user->lang[$vars['lang']])) ? $user->lang[$vars['lang']] : $vars['lang'],
				'S_EXPLAIN'		=> $vars['explain'],
				'TITLE_EXPLAIN'	=> $l_explain,
				'CONTENT'		=> build_cfg_template($type, $config_key, $this->new_config, $config_key, $vars),
				)
			);
			unset($display_vars['vars'][$config_key]);
		}

		$template->assign_vars(array(
			'S_EDIT'			=> true,
			
			'L_TITLE_EDIT'		=> $user->lang['ABBCODES_SETINGS'],
			'L_TITLE_EXPLAIN'	=> $user->lang['ABBCODES_SETINGS_EXPLAIN'],
			'ICON_BASEDIR'		=> $this->dir,
			
			'S_ERROR'			=> (sizeof($error)) ? true : false,
			'ERROR_MSG'			=> implode('<br />', $error),
			
			'S_FOUNDER'			=> ($user->data['user_type'] == USER_FOUNDER) ? true : false,
			'NO_FOUNDER'		=> $user->lang['NO_AUTH_OPERATION'],
			
			'U_ABBC3'			=> $user->lang['ABBC3_HELP_ABOUT'],
			'U_ACTION'			=> $this->u_action,
		));
		
	}

	/**
	* Set config value. Creates missing config entry.
	**/
	function set_config($config_name, $config_value, $is_dynamic = true)
	{
		global $db, $cache, $config;
		
		$sql = 'UPDATE ' . CONFIG_TABLE . "
				SET config_value = '" . $db->sql_escape($config_value) . "'
				WHERE config_name = '" . $db->sql_escape($config_name) . "'";
		$db->sql_query($sql);
		
		if (!$db->sql_affectedrows() && !isset($config[$config_name]))
		{
			$sql = 'INSERT INTO ' . CONFIG_TABLE . ' ' . $db->sql_build_array('INSERT', array(
					'config_name'	=> $config_name,
					'config_value'	=> $config_value,
					'is_dynamic'	=> ($is_dynamic) ? 1 : 0));
			$db->sql_query($sql);
		}
		
		$config[$config_name] = $config_value;
		
		if ($is_dynamic)
		{
			$cache->destroy('config');
		}
	}

	/**
	* Enter description here...
	**/
	function purge_cache($id, $mode, $action)
	{
		global $user, $auth, $phpbb_admin_path, $phpEx;
		
		if ((int) $user->data['user_type'] !== USER_FOUNDER)
		{
			trigger_error($user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
		}
		
		if (confirm_box(true))
		{
			global $cache;
			$cache->purge();
			
			// Clear permissions
			$auth->acl_clear_prefetch();
			cache_moderators();
			
			add_log('admin', 'LOG_PURGE_CACHE');
			
			trigger_error( $user->lang['LOG_PURGE_CACHE'] . adm_back_link($this->u_action));
		}
		else
		{
			confirm_box(false, $user->lang['PURGE_CACHE_CONFIRM'], build_hidden_fields(array(
				'i'			=> $id,
				'mode'		=> $mode,
				'action'	=> $action,
			)),'confirm_body.html', "{$phpbb_admin_path}adm/index.$phpEx?i=$id&mode=$mode" );
		}
	}

	/**
	* Add a new division or breack line
	**/
	function add_new_division_or_linebreak( $add_division, $add_linebreak )
	{
		global $user, $db, $template, $config, $cache;
		
		// get last bbcode id - Start
		$sql = 'SELECT MIN(bbcode_id) as min_bbcode_id
			FROM ' . BBCODES_TABLE;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		if ($row)
		{
			$next_bbcode_id = $row['min_bbcode_id'];
		}
		else
		{
			$next_bbcode_id = 0;
		}
		$next_bbcode_id = $next_bbcode_id-1;
		// get last bbcode id - End

		// get last order - Start
		$sql = 'SELECT MAX(bbcode_order) as max_bbcode_order
			FROM ' . BBCODES_TABLE;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		$next_bbcode_order = (int) $row['max_bbcode_order'] + 1;
		// get last order - End

		$sql = "SELECT *
				FROM " . BBCODES_TABLE . "
				WHERE bbcode_helpline = '" . ( $add_linebreak ? 'ABBC3_BREAK' : 'ABBC3_DIVISION') . "'
				ORDER BY bbcode_order DESC";
		$result = $db->sql_query_limit($sql, 1);
		while ($row = $db->sql_fetchrow($result))
		{
			$bbcode = $row['bbcode_tag'];
		}

		// get the value of current tag
		$number = preg_replace('#(break|division)(\d+)#s', '$2', $bbcode);
		$next_bbcode_number = (int) $number + 1;
		
		$sql_ary = array(
			'bbcode_id'					=> $next_bbcode_id,
			'bbcode_tag'				=> ( $add_linebreak ? 'break' : 'division') . $next_bbcode_number,
			'bbcode_helpline'			=> ( $add_linebreak ? 'ABBC3_BREAK' : 'ABBC3_DIVISION'),
			'display_on_posting'		=> false,
			'display_on_pm'				=> false,
			'display_on_sig'			=> false,
			'bbcode_match'				=> '.',
			'bbcode_tpl'				=> '.',
			'first_pass_match'			=> '.',
			'first_pass_replace'		=> '.',
			'second_pass_match'			=> '.',
			'second_pass_replace'		=> '.',
			'abbcode'					=> true,
			'bbcode_image'				=> ( $add_linebreak ? 'spacer.gif' : 'dots.gif'),
			'bbcode_order'				=> $next_bbcode_order,
		);
		$db->sql_query('INSERT INTO ' . BBCODES_TABLE . $db->sql_build_array('INSERT', $sql_ary));

		$cache->destroy('sql', BBCODES_TABLE);
		$user->add_lang('acp/posting');
		$lang = 'BBCODE_ADDED';
		$log_action = 'LOG_BBCODE_ADD';

		add_log('admin', $log_action, $sql_ary['bbcode_tag']);

		trigger_error($user->lang[$lang] . adm_back_link($this->u_action));
	}

	/**
	* Synchronise order
	**/
	function resync_abbcodes( )
	{
		global $user, $db;

		$user->add_lang('mods/acp_abbcodes');

		// This pseodo-bbcode should not change the position order
		$bbcode_tag_ary =  array( 'font=', 'size', 'highlight=', 'color');
		$next_bbcode_order = sizeof($bbcode_tag_ary)+1;

		$sql = 'SELECT bbcode_id, bbcode_tag, bbcode_order 
				FROM ' . BBCODES_TABLE . ' 
				WHERE ' . $db->sql_in_set('bbcode_tag', $bbcode_tag_ary, true) . ' 
				ORDER BY bbcode_order';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$sql = 'UPDATE ' . BBCODES_TABLE . " 
					SET bbcode_order = $next_bbcode_order 
					WHERE bbcode_id = {$row['bbcode_id']}";
			$db->sql_query($sql);

			$next_bbcode_order++;
		}
		$db->sql_freeresult($result);
		
		trigger_error($user->lang['ABBCODES_RESYNC_SUCCESS'] . adm_back_link($this->u_action));
	}

	/**
	* Show/edit bbcodes
	**/
	function bbcodes_edit($id, $mode, $action, $bbcode = '')
	{
		global $user, $db, $template, $config;
		
		$user->add_lang(array('acp/posting', 'mods/acp_abbcodes', 'mods/abbcode'));
		
		// Is this ABBC3 is disabled
		if ( !$config['ABBC3_MOD'] )
		{
			trigger_error($user->lang['ABBCODES_MOD_DISABLE'] . adm_back_link($this->u_action), E_USER_WARNING);
		}
		
		$img_spacer = 'spacer.gif';
		$img_noimg  = 'no_image.png';
		
		if ( $this->submit && $bbcode )
		{
			// Get items to create/modify
			$bbcode_group		= request_var('group_id', array(0));
			$abbcode_name		= (isset($_POST['name'])) ? request_var('name', array('' => '')) : array();
			$display_on_posting = (isset($_POST['display_on_posting'])) ? request_var('display_on_posting', array('' => 0)) : array();
			$display_on_pm		= (isset($_POST['display_on_pm'])) ? request_var('display_on_pm', array('' => 0)) : array();
			$display_on_sig		= (isset($_POST['display_on_sig'])) ? request_var('display_on_sig', array('' => 0)) : array();
			$bbcode_image		= utf8_normalize_nfc(request_var('image', array('' => ''), true));
			
			$group_ids = '';
			for ($i=0; $i<count($bbcode_group); $i++)   
			{
				$group_ids .= $bbcode_group[$i] . ', ';
			}
			$group_ids = substr( $group_ids, 0 , strlen($group_ids)-2 );

			$bbcode_sql = array(
				'display_on_posting'	=> (isset( $display_on_posting[$bbcode])) ? 1 : 0,
				'display_on_pm'			=> (isset( $display_on_pm[$bbcode])) ? 1 : 0,
				'display_on_sig'		=> (isset( $display_on_sig[$bbcode])) ? 1 : 0,
				'bbcode_image'			=> (isset( $bbcode_image[$bbcode])) ? $bbcode_image[$bbcode] : '',
				'bbcode_group'			=> $group_ids,
			);
			
			// Fix for breack line?
			if ( substr($abbcode_name[$bbcode],0,5) == 'break')
			{
				$bbcode_sql['bbcode_image'] = 'spacer.gif';
			}
			
			$sql = "UPDATE " . BBCODES_TABLE . "
				SET " . $db->sql_build_array('UPDATE', $bbcode_sql) . "
				WHERE bbcode_id = " . $bbcode;
			$result = $db->sql_query($sql);
			
			if ($result )
			{
				trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
			}
			else
			{
				trigger_error($user->lang['LOG_CONFIG_ABBCODES_ERROR'] . adm_back_link($this->u_action), E_USER_WARNING);
			}
			$bbcode = '';
		}
		
		$error = array();
		
		// Exclude bots // and guests...
	//	$sql = 'SELECT group_id FROM ' . GROUPS_TABLE . " WHERE group_name IN ('BOTS', 'GUESTS')";
		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name IN ('BOTS')";
		$result = $db->sql_query($sql);

		$exclude = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$exclude[] = $row['group_id'];
		}
		$db->sql_freeresult($result);

		$sql = "SELECT abbcode, bbcode_order, bbcode_id, bbcode_group, bbcode_tag, bbcode_helpline, bbcode_image, display_on_posting, display_on_pm, display_on_sig 
				FROM " . BBCODES_TABLE . " 
				ORDER BY bbcode_order";
		$result = $db->sql_query($sql);
		
		$template->assign_vars(array(
			'S_BBCODES'			=> true,
			
			'L_TITLE_BBCODES'	=> $user->lang['ABBCODES_CONFIG'],
			'L_EXPLAIN_BBCODES'	=> $user->lang['ABBCODES_CONFIG_EXPLAIN'],
			
			'S_BBCODE_EDIT'		=> ( $bbcode ) ? true :false,
			
			'ICON_BASEDIR'		=> $this->dir,
			
			'S_ERROR'			=> (sizeof($error)) ? true : false,
			'ERROR_MSG'			=> implode('<br />', $error),
			
			'U_ABBC3'			=> $user->lang['ABBC3_HELP_ABOUT'],
			'U_ACTION'			=> $this->u_action,
			'F_ACTION'			=> ( $bbcode ) ? $this->u_action . '&amp;mode=bbcodes&amp;action=edit&amp;bbcode_id=' . $bbcode : null,
			'A_ACTION'			=> (!$bbcode ) ? $this->u_action . '&amp;mode=bbcodes&amp;action=newdlb' : null,
			'U_SYNC'			=> (!$bbcode ) ? $this->u_action . '&amp;mode=bbcodes&amp;action=sync' : null,
			'U_BACK'			=> ( $bbcode ) ? $this->u_back : null,
		));
		
		$no_move = array('ABBC3_FONT', 'ABBC3_SIZE', 'ABBC3_HIGHLIGHT', 'ABBC3_COLOR' );
		$first_row_to_move = 0;
		while ($row = $db->sql_fetchrow($result))
		{
			/** Some fixes **/
			$bbcode_id		= $row['bbcode_id'];
			$abbcode		= $row['abbcode'];
			$abbcode_name	= ( ($row['abbcode']) ? 'ABBC3_' : '' ) . strtoupper( str_replace('=', '', trim($row['bbcode_tag']) ) );
			$abbcode_name	= ( $row['bbcode_helpline'] == 'ABBC3_ED2K_TIP') ? 'ABBC3_ED2K' : $abbcode_name;
			$abbcode_image	= trim($row['bbcode_image']);
			
			// is a breack line or division ?
			if ( ( substr($abbcode_name,0,11) == 'ABBC3_BREAK') || ( substr($abbcode_name,0,14) == 'ABBC3_DIVISION' ) )
			{
				if ( substr($abbcode_name,0,14) == 'ABBC3_DIVISION' )
				{
					if ( $config['ABBC3_TAB'] )
					{
						$abbcode_name = 'ABBCODES_DIVISION';
					}
					else
					{
						continue;
					}
				}
				else
				{
						$abbcode_name = 'ABBCODES_BREAK';
				}
			}

			if ( $abbcode_name == 'ABBC3_COLOR')
			{
				$first_row_to_move = $row['bbcode_order']+1;
			}
			
			if ( $action != 'edit' )
			{
				$template->assign_block_vars('items', array(
					'ID'					=> $bbcode_id,
					'TAG_NAME'				=> ( $abbcode ) ? '' : str_replace( '=', '', trim($row['bbcode_tag']) ),
					'TAG_EXPLAIN'			=> @$user->lang[$abbcode_name . '_MOVER'],
 					'IMG_SRC'				=> ($abbcode_image && $abbcode_image != $img_spacer) ? $this->dir . '/images/' . $abbcode_image : '',
					'NAME'					=> str_replace( '=', '', trim($row['bbcode_tag']) ),
				
					'ON_POST'				=> ($row['display_on_posting']) ? $user->lang['ENABLED'] : $user->lang['DISABLED'],
					'ON_PM'					=> ($row['display_on_pm']) ? $user->lang['ENABLED'] : $user->lang['DISABLED'],
					'ON_SIG'				=> ($row['display_on_sig']) ? $user->lang['ENABLED'] : $user->lang['DISABLED'],
					
					'S_NOMOVE'				=> ( in_array( $abbcode_name, $no_move ) ) ? true : null,
					'S_FIRST_ROW'			=> ( $row['bbcode_order'] == $first_row_to_move ) ? true : false,
					
					'U_EDIT'				=> $this->u_action . '&amp;mode=bbcodes&amp;action=edit&amp;bbcode_id=' . $row['bbcode_id'],
					'U_MOVE_UP'				=> $this->u_action . '&amp;mode=bbcodes&amp;action=move_up&amp;bbcode_id=' . $row['bbcode_id'],
					'U_MOVE_DOWN'			=> $this->u_action . '&amp;mode=bbcodes&amp;action=move_down&amp;bbcode_id=' . $row['bbcode_id'],
				));
			}
			elseif ( $action == 'edit' && $row['bbcode_id'] == $bbcode )
			{
				$template->assign_block_vars('items', array(
					'ID'					=> $bbcode_id,
					'NAME'					=> str_replace( '=', '', trim($row['bbcode_tag']) ),
					'TAG_NAME'				=> ( $abbcode ) ? '' : str_replace( '=', '', trim($row['bbcode_tag']) ),
					'TAG_EXPLAIN'			=> @$user->lang[$abbcode_name . '_MOVER'],
					
 					'S_NOMOVE'				=> ( in_array( $abbcode_name, $no_move ) ) ? true : null,
					'IMG_SRC'				=> ($abbcode_image) ? ($abbcode_image != $img_spacer) ? $this->dir . '/images/' . $abbcode_image : '' : $this->dir . '/images/' . $img_noimg,
					'S_NEW_IMG'				=> image_select($this->dir . '/images', $abbcode_image, 'image[' . $bbcode_id . ']', false, $this->u_action),
					'POSTING_CHECKED'		=> ( $row['display_on_posting'] ) ? ' checked="checked"' : '',
					'PM_CHECKED'			=> ( $row['display_on_pm'] ) ? ' checked="checked"' : '',
					'SIG_CHECKED'			=> ( $row['display_on_sig'] ) ? ' checked="checked"' : '',

					'S_GROUP_OPTIONS'		=> groups_select_options(split(',', $row['bbcode_group']), $exclude),
				));
			}
		}
	}
}

	/**
	* Select list of images in current style folder
	**/
	function image_select($dir, $current, $name, $show = false, $u_action)
	{
		global $user, $config, $phpbb_admin_path, $phpbb_root_path, $phpEx;
		
		// Read the folder and get images
		$dp = @opendir($dir);
		$count = 0;
		
		if ($dp)
		{
			while (($file = readdir($dp)) !== false)
			{
				if (preg_match('#\.(?:gif|jpg|png)$#', $file))
				{
					$imagesetlist[$count] = $file;
					$count++;
				}
			}
			closedir($dp);
		}
		else
		{
			trigger_error($user->lang['NO_IMAGESET'] . adm_back_link($u_action), E_USER_WARNING);
		}
		
		if (sizeof( $imagesetlist ))
		{
			// Make sure the list of possible images is sorted alphabetically
			sort($imagesetlist);
			
			$icons_list = '<select id="image_select" name="' . $name . '" onchange="update_image(this.options[selectedIndex].value);">' . "\n";
			$icons_list .= '<option value="" ' . (($current == '') ? ' selected="selected"' : ''). '>' . $user->lang['NO_IMAGE'] . '</option>' . "\n";
			
			for( $i = 0; $i < count($imagesetlist); $i++ )
			{
				$selected = ($imagesetlist[$i] == $current) ? ' selected="selected"' : '';
				$icons_list .= '<option value="' . $imagesetlist[$i] . '"' . $selected . '>' . $imagesetlist[$i] . '</option>' . "\n";
			}
			$icons_list .= '</select>'. (($show) ? '&nbsp; <label>' . $user->lang['CURRENT_IMAGE'] . '</label><span><img src="' . $dir . '/' . $current .'" id="newimg" name="' . $name .'" alt="" width="80" height="30" /></span>' : '' );
		}
		return $icons_list;
	}

	/**
	* Select list of display full size image
	**/
	function method_select($selected_method = 'AdvancedBox', $name)
	{
		global $user;
		
		$method_options = $user->lang['ABBCODES_RESIZE_METHODS'];
		
		$s_method_options = '<select name="' . $name . '">';
		foreach($method_options as $method_name => $method_value)
		{
			$selected = ($selected_method == $method_name) ? ' selected="selected"' : '';
			$s_method_options .= '<option value="' . $method_name . '"' . $selected . ' >' . $method_value . '</option>';
		}
		$s_method_options.= '</select>';
		
		return $s_method_options;
	}

	function groups_select_options( $select_id = false, $exclude_ids = false )
	{
		global $user, $db;

		$sql = 'SELECT group_id, group_name, group_type
				FROM ' . GROUPS_TABLE . '
				WHERE ' . $db->sql_in_set('group_id', array_map('intval', $exclude_ids), true) .' 
				ORDER BY group_type DESC, group_name ASC';
		$result = $db->sql_query($sql);
	
		$group_options = '';
		while ($row = $db->sql_fetchrow($result))
		{
			$selected = (is_array($select_id)) ? ((in_array($row['group_id'], $select_id)) ? ' selected="selected"' : '') : (($row['group_id'] == $select_id) ? ' selected="selected"' : '');
			$group_options .= '<option value="' . $row['group_id'] . '"' . $selected . '>' . ucfirst(strtolower( (($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name']) )) . '</option>';
	//		$group_options .= '<option' . (($row['group_type'] == GROUP_SPECIAL) ? ' class="sep"' : '') . ' value="' . $row['group_id'] . '"' . $selected . '>' . (($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name']) . '</option>';
		}
		$db->sql_freeresult($result);
		return $group_options;
	}
?>
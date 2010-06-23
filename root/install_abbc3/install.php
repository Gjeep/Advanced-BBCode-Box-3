<?php
/**
* @package: phpBB3 :: Advanced BBCode box 3 -> install_abbc3
* @version: $Id: install.php, v 1.0.11 2008/10/11 11:10:08 leviatan21 Exp $
* @copyright: leviatan21 < info@mssti.com > (Gabriel) http://www.mssti.com/phpbb3/
* @license: http://opensource.org/licenses/gpl-license.php GNU Public License 
* @author: leviatan21 - http://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=345763
**/


define('IN_PHPBB', true);
$phpbb_root_path = '../';
$phpbb_root_install = $phpbb_root_path . 'install_abbc3/';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

require($phpbb_root_path . 'includes/functions_admin.' . $phpEx);

include($phpbb_root_path . 'includes/acp/acp_modules.' . $phpEx);
$modules = new acp_modules();

include($phpbb_root_path . 'includes/db/db_tools.' . $phpEx);
$db_tools = new phpbb_db_tools($db);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();

$user->add_lang( array('mods/acp_abbcodes', 'install', 'acp/common', 'acp/modules', 'posting') );

$mode		= request_var('mode', 'else', true);
$delete		= request_var('delete', 0);
$start_step	= request_var('start_step', 0);
$install	= request_var('install', 0);
$meta_url	= false;
$reparse	= request_var('reparse', 0);
$reparsePost= request_var('reparsePost', 0);
$reparseSig	= request_var('reparseSig', 0);
$reparsePM	= request_var('reparsePM', 0);
$limit		= 200;
$start		= intval(request_var('start', 0));
$msg		= '';
$error_ary	= array();
$ary_msg_ok	= array();
$ary_msg_err= array();
$error_ary['sql'][0] = '';

$mod_version= '1.0.11';
$page_title = $user->lang['INSTALLER_TITLE'] . sprintf($user->lang['INSTALLER_VERSION'], $mod_version);

switch ($mode)
{
	case 'update':
		// first of all try to delete
		if ($start_step == 0)
		{

			$template->assign_vars(array(
				'TITLE'		=> $user->lang['INSTALLER_UPDATE_WELCOME'],
				'BODY'		=> $user->lang['INSTALLER_UPDATE_WELCOME_NOTE'],
				'LEGEND'	=> $user->lang['INSTALLER_UPDATE'],
				'LABEL'		=> sprintf($user->lang['INSTALLER_UPDATE_VERSION'], $mod_version),
				'WARNING'		=> ( !isset($config['ABBC3_MOD']) ) ? true : false,
				'INSTALLER_NOTE'	=> $user->lang['INSTALLER_DELETE_INFORMATION'],
			));
			$deleted = delete_abbc3();
		}

	// no break;
	case 'install':
		$installed = false;
		$cache->purge();
		
		// Don't continue if answer ins't yes
		$start_step = ( $start_step == 1 && $install == 0 ) ? 0 : $start_step;

		if ($start_step == 1)
		{
			$config_data = get_abbc3_config();
			$num_updates = 1;
			$is_dynamic	 = false;

			foreach ( $config_data as $config_name => $config_value )
			{
				$msg = sprintf($user->lang['STEP_PERCENT_COMPLETED'], $num_updates, sizeof($config_data)) . ' ::  &raquo; [' . $config_name . '] ';

				set_config($config_name, $config_value, $is_dynamic);

				if ( isset($config[$config_name]) )
				{
					$ary_msg_ok[] = $msg . $user->lang['LINE_ADDED'];
				}
				else
				{
					$ary_msg_err[] = $msg . $user->lang['LINE_UNMODIFIED'];
				}
				$num_updates++;
			}

			// Log what we did
			add_log('admin', 'LOG_CONFIG_ADD', $user->lang['INSTALLER_TITLE']);

			foreach ($ary_msg_ok as $msg_text)
			{
				$template->assign_block_vars('install_block1', array( 'NOTE' => $msg_text ));
			}
			foreach ($ary_msg_err as $msg_text)
			{
				$template->assign_block_vars('install_block2', array( 'NOTE' => $msg_text ));
			}
		}
		// bbcodes new rows
		elseif ($start_step == 2)
		{
			$database_update_info = get_abbc3_schema_changes();

			$db->sql_return_on_error(true);

			$num_updates = 1;
			foreach ($database_update_info as $version => $schema_changes)
			{
				// Add columns?
				if (!empty($schema_changes['add_columns']))
				{
					foreach ($schema_changes['add_columns'] as $table => $columns)
					{
						$sizeof_columns = sizeof($columns)+2;
						foreach ($columns as $column_name => $column_data)
						{
							$column_exists	= false;
							$msg	= sprintf($user->lang['STEP_PERCENT_COMPLETED'], $num_updates, $sizeof_columns) . ' ::  &raquo; [' . $column_name . '] ';

							$column_exists = $db_tools->sql_column_exists($table, $column_name);
							
							if ( $column_exists )
							{
								$db_tools->sql_column_remove($table, $column_name);
							}

							$result = $db_tools->sql_column_add($table, $column_name, $column_data);

							if ( $result )
							{
								$ary_msg_ok[] = $msg . $user->lang['LINE_ADDED'];
							}
							else
							{
								$ary_msg_err[] = $msg . $user->lang['LINE_UNMODIFIED'];
							}
							$num_updates++;
						}
					}
				}
				
				// Add indexes?
				if (!empty($schema_changes['add_index']))
				{
					foreach ($schema_changes['add_index'] as $table => $index_array)
					{
						$sizeof_index = $sizeof_columns-1;
						foreach ($index_array as $index_name => $column)
						{
							$msg	= sprintf($user->lang['STEP_PERCENT_COMPLETED'], $num_updates, $sizeof_index) . ' ::  &raquo; [' . $index_name . '] ';
							$result = $db_tools->sql_create_index($table, $index_name, $column);

							if ( $result )
							{
								$ary_msg_ok[] = "<hr />" . $msg . $user->lang['LINE_ADDED'];
							}
							else
							{
								$ary_msg_err[] = "<hr />" . $msg . $user->lang['LINE_UNMODIFIED'];
							}
							$num_updates++;
						}
					}
				}
				// Change columns?
				if (!empty($schema_changes['change_columns']))
				{
					foreach ($schema_changes['change_columns'] as $table => $columns)
					{
						$sizeof_changes = $sizeof_columns;
						foreach ($columns as $column_name => $column_data)
						{
							$msg	= sprintf($user->lang['STEP_PERCENT_COMPLETED'], $num_updates, $sizeof_changes) . ' ::  &raquo; [' . $column_name . '] ';
							$result = $db_tools->sql_column_change($table, $column_name, $column_data);

							if ( $result )
							{
								$ary_msg_ok[] = "<hr />" . $msg . $user->lang['LINE_MODIFIED'];
							}
							else
							{
								$ary_msg_err[] = "<hr />" . $msg . $user->lang['LINE_UNMODIFIED'];
							}
							$num_updates++;
						}
					}
				}
			}
			$db->sql_return_on_error(false);

			// Log what we did
			add_log('admin', 'LOG_DATABASE_SCHEMA', $user->lang['INSTALLER_TITLE']);

			foreach ($ary_msg_ok as $msg_text)
			{
				$template->assign_block_vars('install_block1', array( 'NOTE' => $msg_text ));
			}
			foreach ($ary_msg_err as $msg_text)
			{
				$template->assign_block_vars('install_block2', array( 'NOTE' => $msg_text ));
			}
		}
		// BBcodes add
		elseif ($start_step == 3)
		{
			// Have previous custom bbcodes ? - Start
			$sql = 'SELECT * 
				FROM ' . BBCODES_TABLE;
			$result = $db->sql_query($sql);

			// if have, we must adjust the bbcode_order value to be at last position ;)
			$last_bbcode_order = 80; // The mount of bbcodes to add from ABBC3
			while ($row = $db->sql_fetchrow($result))
			{
				$bbcode_id = $row['bbcode_id'];
				$last_bbcode_order++;
				$sql = 'UPDATE ' . BBCODES_TABLE . '
						SET bbcode_order = ' . (int) $last_bbcode_order . '
						WHERE bbcode_id = ' . $bbcode_id;
				$db->sql_query($sql);
			}
			$cache->destroy('sql', BBCODES_TABLE);
			$db->sql_freeresult($result);
			// Have previous custom bbcodes ? - End

			$first_bbode_id = 0;

			// Get last bbcode id - Start
			$sql = 'SELECT MAX(bbcode_id) as max_bbcode_id
				FROM ' . BBCODES_TABLE;
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if ($row)
			{
				$next_bbcode_id = $row['max_bbcode_id'];

				// Make sure it is greater than the core bbcode ids...
				if ($next_bbcode_id <= NUM_CORE_BBCODES)
				{
					$next_bbcode_id = NUM_CORE_BBCODES;
				}
			}
			else
			{
				$next_bbcode_id = NUM_CORE_BBCODES;
			}

			if ($next_bbcode_id > 1511)
			{
				trigger_error( $user->lang['TOO_MANY_BBCODES'] );
			}
			// Get last bbcode id - End

			$bbcode_data	= get_abbc3_bbcodes();
			$num_updates	= 1;
			foreach ( $bbcode_data as $bbcode_name => $bbcode_values )
			{
				if ( $bbcode_values[2] )
				{
					$next_bbcode_id++;
				}
				else
				{
					$first_bbode_id--;
				}

				$sql_ary = array(
					'bbcode_tag'			=> $bbcode_values[0],
					'bbcode_order'			=> $bbcode_values[1],
					'bbcode_helpline'		=> $bbcode_values[3],
					'bbcode_match'			=> $bbcode_values[4],
					'bbcode_tpl'			=> $bbcode_values[5],
					'first_pass_match'		=> $bbcode_values[6],
					'first_pass_replace'	=> $bbcode_values[7],
					'second_pass_match'		=> $bbcode_values[8],
					'second_pass_replace'	=> $bbcode_values[9],
					'display_on_posting'	=> $bbcode_values[10],
					'display_on_pm'			=> $bbcode_values[11],
					'display_on_sig'		=> $bbcode_values[12],
					'abbcode'				=> $bbcode_values[13],
					'bbcode_image'			=> $bbcode_values[14],
					'bbcode_group'			=> ( isset($bbcode_values[15]) ? $bbcode_values[15] : 0),
				);
				$msg = sprintf($user->lang['STEP_PERCENT_COMPLETED'], $num_updates, sizeof($bbcode_data)) . ' ::  &raquo; [' . $bbcode_name . '] ';
				
				// Check if exist
				$sql = 'SELECT * 
						FROM ' . BBCODES_TABLE . "
						WHERE LOWER(bbcode_tag) = '" . $db->sql_escape(strtolower($sql_ary['bbcode_tag'])) . "'";
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				// if exist overwrite it
				if ( $row )
				{
					// Try to preserve some bbcode data
					$sql_ary['bbcode_id']			= $bbcode_id = $row['bbcode_id'];

					$sql = 'UPDATE ' . BBCODES_TABLE . '
						SET ' . $db->sql_build_array('UPDATE', $sql_ary) . '
						WHERE bbcode_id = ' . $bbcode_id;
					$result = $db->sql_query($sql);
				}
				// else add it
				else
				{
					$sql_ary['bbcode_id']	 = ( $bbcode_values[2] ) ? $next_bbcode_id : $first_bbode_id;

					$result = $db->sql_query('INSERT INTO ' . BBCODES_TABLE . $db->sql_build_array('INSERT', $sql_ary));
				}

				if ( $result )
				{
					$ary_msg_ok[] = $msg . $user->lang['LINE_ADDED'];
				}
				else
				{
					$ary_msg_err[] = $msg . $user->lang['LINE_UNMODIFIED'];
				}

				$cache->destroy('sql', BBCODES_TABLE);
				$num_updates++;
			}

			// Log what we did
			add_log('admin', 'LOG_BBCODE_ADD', $user->lang['INSTALLER_TITLE']);

			foreach ($ary_msg_ok as $msg_text)
			{
				$template->assign_block_vars('install_block1', array( 'NOTE' => $msg_text ));
			}
			foreach ($ary_msg_err as $msg_text)
			{
				$template->assign_block_vars('install_block2', array( 'NOTE' => $msg_text ));
			}
		}
		// Synchronise BBcodes order
		elseif ($start_step == 4)
		{
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

			$template->assign_block_vars('install_block1', array(
				'NOTE'	=> $user->lang['ABBCODES_RESYNC_SUCCESS']
			));
		}
		// add Modules
		elseif ($start_step == 5)
		{
			 // by default install in cat .MOD
			$acp_cat_parent_id   = 31;
			// I like posting tab, if found use it, also use .MOD
			$acp_cat_parent_name = 'ACP_CAT_POSTING';

			$sql = 'SELECT module_id
				FROM ' . MODULES_TABLE . "
				WHERE module_langname = '" . $acp_cat_parent_name . "'";
			$result = $db->sql_query($sql);
	
			while ($row = $db->sql_fetchrow($result))
			{
				$acp_cat_parent_id = $row['module_id'];
			}
			$db->sql_freeresult($result);

			remove_abbc3_modules( );

			$modules_data = get_abbc3_modules( $acp_cat_parent_id );
			foreach ( $modules_data as $modules_name => $modules_values )
			{
				$result = true;

				// Add this cat parent id
				if ( $modules_values['module_langname'] != 'ACP_ABBCODES' )
				{
					$modules_values['parent_id'] = $acp_module_id;
				}
				
				$result = add_abbc3_module($modules_values);

				if ( $result )
				{
					if ( $modules_values['module_langname'] == 'ACP_ABBCODES' )
					{
						$acp_module_id = $db->sql_nextid();
					}

					$ary_msg_ok[] = "[" . $user->lang[$modules_values['module_langname']] . "] " . $user->lang['MODULE_ADDED'];
				}
				else
				{
					$ary_msg_err[] = $result;
				}
			}
			
			// Clear permissions
			$auth->acl_clear_prefetch();
			// Log what we did
			add_log('admin', 'LOG_MODULE_ADD', $user->lang['INSTALLER_TITLE']);
			add_log('admin', 'LOG_PURGE_CACHE');
			$installed = true;

			foreach ($ary_msg_ok as $msg_text)
			{
				$template->assign_block_vars('install_block1', array( 'NOTE' => $msg_text ));
			}
			foreach ($ary_msg_err as $msg_text)
			{
				$template->assign_block_vars('install_block2', array( 'NOTE' => $msg_text ));
			}
		}
		else
		{
			$template->assign_vars(array(
				'TITLE'		=> ($mode == 'install') ? $user->lang['INSTALLER_INSTALL_WELCOME'] : $user->lang['INSTALLER_UPDATE_WELCOME'],
				'BODY'		=> ($mode == 'install') ? $user->lang['INSTALLER_INSTALL_WELCOME_NOTE'] : $user->lang['INSTALLER_UPDATE_WELCOME_NOTE'],
				'LEGEND'	=> ($mode == 'install') ? $user->lang['INSTALLER_INSTALL'] : $user->lang['INSTALLER_UPDATE'],
				'LABEL'		=> ($mode == 'install') ? sprintf($user->lang['INSTALLER_INSTALL_VERSION'], $mod_version) : sprintf($user->lang['INSTALLER_UPDATE_VERSION'], $mod_version),
				'STEP'		=> 0,
			));
		}

		$template->assign_vars(array(
			'MODE'		=> $mode,
			'STEP'		=> $start_step,
			'INSTALLED'	=> $installed,
			'U_ACTION'	=> append_sid("{$phpbb_root_install}install.$phpEx", "mode=$mode&amp;start_step=" .($start_step+1) ),
		));

		if ($installed)
		{
			$template->assign_vars(array(
				'INSTALLED'	=> true,
				'UPDATED'	=> ($mode == 'update') ? true : false,
				'L_INSTALLER_END'	=> sprintf($user->lang['INSTALLER_INSTALL_END'], $user->lang['INSTALLER_TITLE'], $mod_version),
				'L_UPDATE_END'		=> ($mode == 'update') ? $user->lang['INSTALLER_UPDATE_END'] : '',
			));
		}

	break;

	case 'delete':
		$cache->purge();
		$deleted = false;

		// Don't continue if answer ins't yes
		$start_step = ( $start_step == 1 && $delete == 0 ) ? 0 : $start_step;

		if ($start_step == 1)
		{
			$deleted = delete_abbc3();

			if ( $deleted )
			{
				// Log what we did
				add_log('admin', 'LOG_DELETE_ABBCODES', $user->lang['INSTALLER_TITLE'] . sprintf($user->lang['INSTALLER_VERSION'], $mod_version) );
				add_log('admin', 'LOG_PURGE_CACHE');

				$template->assign_vars(array(
					'STEP'		=> $start_step,
					'L_DELETE_END'	=> sprintf($user->lang['INSTALLER_DELETE_SUCCESSFUL'], $user->lang['INSTALLER_TITLE'] , $mod_version),
				));
			}
			else
			{
				$template->assign_vars(array(
					'STEP'		=> $start_step,
					'L_DELETE_END'	=> sprintf($user->lang['INSTALLER_DELETE_UNSUCCESSFUL'], $user->lang['INSTALLER_TITLE'] , $mod_version),
				));				
			}
		}
		else
		{
			$template->assign_vars(array(
				'TITLE'			=> $user->lang['INSTALLER_DELETE_WELCOME'],
				'BODY'			=> $user->lang['INSTALLER_DELETE_WELCOME_NOTE'],
				'WARNING'		=> ( !isset($config['ABBC3_MOD']) ) ? true : false,
				'INSTALLER_NOTE'	=> $user->lang['INSTALLER_DELETE_INFORMATION'],
				'LEGEND'	=> $user->lang['INSTALLER_DELETE'],
				'LABEL'		=> sprintf($user->lang['INSTALLER_DELETE_VERSION'], $mod_version),
				'STEP'		=> 0,
			));
		}

		$template->assign_vars(array(
			'MODE'		=> $mode,
			'STEP'		=> $start_step,
			'DELETED'	=> $deleted,
			'U_ACTION'	=> append_sid("{$phpbb_root_install}install.$phpEx", "mode=$mode&amp;start_step=" .($start_step+1) ),
		));

	break;

	/**
	* Basef off Admin Reparse BBCode mod - Source : http://www.phpbb.com/community/viewtopic.php?f=70&t=1096945
	* Basef off adjust_bbcodes.php Source : http://phpbb.cvs.sourceforge.net/viewvc/phpbb/phpBB2/develop/adjust_bbcodes.php?view=markup
	**/
	case 'reparsePost':

		// Don't continue if answer ins't yes
		$start_step = ( $start_step == 1 && $reparse == 0 ) ? 0 : $start_step;
		$step		= request_var('step', 0);
		$start		= request_var('start', 0);

		if ( $start_step == 1 )
		{
			$cache->purge();

			$ary_msg_err[] = $user->lang['LONG_SCRIPT_EXECUTION'];
			$meta_url = "?mode=$mode&amp;start_step=2&amp;start=$start&step=$step";
			$template->assign_vars(array( 'META' => '<meta http-equiv="refresh" content="1 ;url=' . $meta_url . '" />' ));
		}
		elseif ( $start_step == 2 )
		{
			$sql = 'SELECT count(post_id) as post_count FROM ' . POSTS_TABLE;
			$result = $db->sql_query($sql);
			$post_count = $db->sql_fetchfield('post_count');

			while ( $limit > intval($post_count) )
			{
				$limit--;
			}

			$next_step = $start + $limit;
			$step++;

			/**
			* Sometimes a post can have error, 
			* in that case you can fill this array with post number to continue process
			* Example : $skip_post = array( 754, 767, 769, 834, 835, 907, 1074, 1107, 1132, 1233, 1317, 1501, 1659, 1661, 1934  );
			**/
			$skip_post = array( );
			$skip_forum = array( );

			if (!class_exists('parse_message'))
			{
				include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
			}

			$bbcode_status	= ($config['allow_bbcode']) ? true : false;
			$img_status		= ($bbcode_status) ? true : false;
			$flash_status	= ($bbcode_status && $config['allow_post_flash']) ? true : false;

			$sql = 'SELECT * FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t 
				WHERE t.topic_id = p.topic_id 
					ORDER BY p.post_id ASC';
			$result = $db->sql_query_limit($sql, $limit, $start);

		#	$db->sql_return_on_error(true);
			while ($row = $db->sql_fetchrow($result))
			{
				if ( in_array( $row['post_id'], $skip_post) ) { continue; }
				if ( in_array( $row['forum_id'], $skip_forum) ) { continue; }
				decode_message($row['post_text'], $row['bbcode_uid']);

				$message_parser = new parse_message();
				$message_parser->message = str_replace('"', '&quot;', html_entity_decode($row['post_text']));
				$message_parser->parse((($bbcode_status) ? $row['enable_bbcode'] : false), (($config['allow_post_links']) ? $row['enable_magic_url'] : false), $row['enable_smilies'], $img_status, $flash_status, true, $config['allow_post_links']);

				if ($row['poll_title'] && $row['post_id'] == $row['topic_first_post_id'])
				{
					$row['poll_option_text'] = '';
					$sql = 'SELECT * FROM ' . POLL_OPTIONS_TABLE . ' WHERE topic_id = ' . $row['topic_id'];
					$result2 = $db->sql_query($sql);

					while ($row2 = $db->sql_fetchrow($result2))
					{
						$row['poll_option_text'] .= $row2['poll_option_text'] . "\n";
					}
					$db->sql_freeresult($result2);

					$poll = array(
						'poll_title'		=> $row['poll_title'],
						'poll_length'		=> $row['poll_length'],
						'poll_max_options'	=> $row['poll_max_options'],
						'poll_option_text'	=> $row['poll_option_text'],
						'poll_start'		=> $row['poll_start'],
						'poll_last_vote'	=> $row['poll_last_vote'],
						'poll_vote_change'	=> $row['poll_vote_change'],
						'enable_bbcode'		=> $row['enable_bbcode'],
						'enable_urls'		=> $row['enable_magic_url'],
						'enable_smilies'	=> $row['enable_smilies'],
						'img_status'		=> $img_status
					);
					$message_parser->parse_poll($poll);
				}

				$sql_data = array(
					'post_text'				=> $message_parser->message,
					'post_checksum'			=> md5($message_parser->message),
					'bbcode_bitfield'		=> $message_parser->bbcode_bitfield,
					'bbcode_uid'			=> $message_parser->bbcode_uid,
				);

				$sql = 'UPDATE ' . POSTS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_data) . ' 
					WHERE post_id = ' . $row['post_id'];
				$db->sql_query($sql);

				if ($row['poll_title'] && $row['post_id'] == $row['topic_first_post_id'])
				{
					$sql_data = array(
						'poll_title'      => str_replace($row['bbcode_uid'], $message_parser->bbcode_uid, $poll['poll_title']),
					);

					$sql = 'UPDATE ' . TOPICS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_data) . ' 
						WHERE topic_id = ' . $row['topic_id'];
					$db->sql_query($sql);

					$sql = 'SELECT * FROM ' . POLL_OPTIONS_TABLE . ' WHERE topic_id = ' . $row['topic_id'];
					$result2 = $db->sql_query($sql);

					while ($row2 = $db->sql_fetchrow($result2))
					{
						$sql_data = array(
							'poll_option_text'      => str_replace($row['bbcode_uid'], $message_parser->bbcode_uid, $row2['poll_option_text']),
						);
					
						$sql = 'UPDATE ' . POLL_OPTIONS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_data) . ' 
							WHERE topic_id = ' . $row['topic_id'] . ' 
							AND poll_option_id = ' . $row2['poll_option_id'];
						$db->sql_query($sql);
					}
				}
			}
		#	$db->sql_return_on_error(false);
			$db->sql_freeresult($result);

			if ($post_count > $next_step)
			{
				$ary_msg_err[] = $user->lang['LONG_SCRIPT_EXECUTION'];
				$ary_msg_err[] = sprintf($user->lang['STEP_PERCENT_COMPLETED'], $step, intval($post_count / $limit) );

				$meta_url = "?mode=$mode&amp;start_step=2&amp;start=$next_step&step=$step";
				$template->assign_vars(array( 'META' => '<meta http-equiv="refresh" content="1 ;url=' . $meta_url . '" />' ));
			}
			else
			{
				$ary_msg_ok[] = sprintf($user->lang['INSTALLER_REPARSE_SUCCESSFUL'], $user->lang['INSTALLER_REPARSE_POST']);
				$start_step = 3;
			}
		}
		else
		{
			$template->assign_vars(array(
				'TITLE'		=> $user->lang['INSTALLER_REPARSE_WELCOME'],
				'BODY'		=> $user->lang['INSTALLER_REPARSE_WELCOME_NOTE'],
				'WARNING'	=> true,
				'INSTALLER_NOTE'	=> $user->lang['INSTALLER_REPARSE_NOTE'] . '</p><p>' . $user->lang['INSTALLER_REPARSE_WARNING'],
				'LEGEND'	=> $user->lang['INSTALLER_REPARSE'],
				'LABEL'		=> $user->lang['INSTALLER_REPARSE_POST'],
				'STEP'		=> 0,
			));
		}

		foreach ($ary_msg_ok as $msg_text)
		{
			$template->assign_block_vars('install_block1', array( 'NOTE' => $msg_text ));
		}
		foreach ($ary_msg_err as $msg_text)
		{
			$template->assign_block_vars('install_block2', array( 'NOTE' => $msg_text ));
		}

		$template->assign_vars(array(
			'MODE'		=> $mode,
			'STEP'		=> $start_step,
			'U_ACTION'	=> append_sid("{$phpbb_root_install}install.$phpEx", "mode=$mode&amp;start_step=1"),
		));

	break;
	
	case 'reparseSig':

		// Don't continue if answer ins't yes
		$start_step = ( $start_step == 1 && $reparse == 0 ) ? 0 : $start_step;
		$step		= request_var('step', 0);
		$start		= request_var('start', 0);

		if ( $start_step == 1 )
		{
			$cache->purge();

			$ary_msg_err[] = $user->lang['LONG_SCRIPT_EXECUTION'];
			$meta_url = "?mode=$mode&amp;start_step=2&amp;start=$start&step=$step";
			$template->assign_vars(array( 'META' => '<meta http-equiv="refresh" content="1 ;url=' . $meta_url . '" />' ));
		}
		elseif ( $start_step == 2 )
		{
			$sql = 'SELECT count(post_id) as post_count FROM ' . POSTS_TABLE;
			$result = $db->sql_query($sql);
			$post_count = $db->sql_fetchfield('post_count');

			while ( $limit > intval($post_count) )
			{
				$limit--;
			}

			$next_step = $start + $limit;
			$step++;

			/**
			* Sometimes a signature can have error, 
			* in that case you can fill this array with user_id number to continue process
			* Example : $skip_post = array( 754, 767, 769, 834, 835, 907, 1074, 1107, 1132, 1233, 1317, 1501, 1659, 1661, 1934  );
			**/
			$skip_post = array( );

			if (!class_exists('parse_message'))
			{
				include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
			}

			$bbcode_status	= ($config['allow_sig_bbcode']) ? true : false;
			$img_status		= ($config['allow_sig_img']) ? true : false;
			$flash_status	= ($bbcode_status && $config['allow_sig_flash']) ? true : false;

			$sql = 'SELECT * FROM ' . USERS_TABLE . ' 
				ORDER BY user_id ASC';

			$result = $db->sql_query_limit($sql, $limit, $start);

		#	$db->sql_return_on_error(true);
			while ($row = $db->sql_fetchrow($result))
			{
				if ( in_array( $row['user_id'], $skip_post) ) { continue; }
				decode_message($row['user_sig'], $row['user_sig_bbcode_uid']);

				$message_parser = new parse_message();
				$message_parser->message = str_replace('"', '&quot;', html_entity_decode($row['user_sig']));
				$message_parser->parse($bbcode_status, $config['allow_sig_links'], $config['allow_sig_smilies'], $img_status, $flash_status, true, $config['allow_sig_links']);

				$sql_data = array(
					'user_sig'					=> $message_parser->message,
					'user_sig_bbcode_bitfield'	=> $message_parser->bbcode_bitfield,
					'user_sig_bbcode_uid'		=> $message_parser->bbcode_uid,
				);

				$sql = 'UPDATE ' . USERS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_data) . ' 
					WHERE user_id = ' . $row['user_id'];
				$db->sql_query($sql);

			}
		#	$db->sql_return_on_error(false);
			$db->sql_freeresult($result);

			if ($post_count > $next_step)
			{
				$ary_msg_err[] = $user->lang['LONG_SCRIPT_EXECUTION'];
				$ary_msg_err[] = sprintf($user->lang['STEP_PERCENT_COMPLETED'], $step, intval( $post_count / $limit ) ) ;

				$meta_url = "?mode=$mode&amp;start_step=2&amp;start=$next_step&step=$step";
				$template->assign_vars(array( 'META' => '<meta http-equiv="refresh" content="1 ;url=' . $meta_url . '" />' ));
			}
			else
			{
				$ary_msg_ok[] = sprintf($user->lang['INSTALLER_REPARSE_SUCCESSFUL'], $user->lang['INSTALLER_REPARSE_SIG']);
				$start_step = 3;
			}
		}
		else
		{
			$template->assign_vars(array(
				'TITLE'		=> $user->lang['INSTALLER_REPARSE_WELCOME'],
				'BODY'		=> $user->lang['INSTALLER_REPARSE_WELCOME_NOTE'],
				'WARNING'	=> true,
				'INSTALLER_NOTE'	=> $user->lang['INSTALLER_REPARSE_NOTE'] . '</p><p>' . $user->lang['INSTALLER_REPARSE_WARNING'],
				'LEGEND'	=> $user->lang['INSTALLER_REPARSE'],
				'LABEL'		=> $user->lang['INSTALLER_REPARSE_SIG'],
				'STEP'		=> 0,
			));
		}

		foreach ($ary_msg_ok as $msg_text)
		{
			$template->assign_block_vars('install_block1', array( 'NOTE' => $msg_text ));
		}
		foreach ($ary_msg_err as $msg_text)
		{
			$template->assign_block_vars('install_block2', array( 'NOTE' => $msg_text ));
		}

		$template->assign_vars(array(
			'MODE'		=> $mode,
			'STEP'		=> $start_step,
			'U_ACTION'	=> append_sid("{$phpbb_root_install}install.$phpEx", "mode=$mode&amp;start_step=1"),
		));

	break;

	case 'reparsePM':
	
		// Don't continue if answer ins't yes
		$start_step = ( $start_step == 1 && $reparse == 0 ) ? 0 : $start_step;
		$step		= request_var('step', 0);
		$start		= request_var('start', 0);

		if ( $start_step == 1 )
		{
			$cache->purge();

			$ary_msg_err[] = $user->lang['LONG_SCRIPT_EXECUTION'];
			$meta_url = "?mode=$mode&amp;start_step=2&amp;start=$start&step=$step";
			$template->assign_vars(array( 'META' => '<meta http-equiv="refresh" content="1 ;url=' . $meta_url . '" />' ));
		}
		elseif ( $start_step == 2 )
		{
			$sql = 'SELECT count(post_id) as post_count FROM ' . POSTS_TABLE;
			$result = $db->sql_query($sql);
			$post_count = $db->sql_fetchfield('post_count');

			while ( $limit > intval($post_count) )
			{
				$limit--;
			}

			$next_step = $start + $limit;
			$step++;

			/**
			* Sometimes a PM can have error, 
			* in that case you can fill this array with msg_id number to continue process
			* Example : $skip_post = array( 754, 767, 769, 834, 835, 907, 1074, 1107, 1132, 1233, 1317, 1501, 1659, 1661, 1934  );
			**/
			$skip_post = array( );
			
			if (!class_exists('parse_message'))
			{
				include($phpbb_root_path . 'includes/message_parser.' . $phpEx);
			}

			$bbcode_status	= ($config['auth_bbcode_pm']) ? true : false;
			$img_status		= ($bbcode_status && $config['auth_img_pm']) ? true : false;
			$flash_status	= ($bbcode_status && $config['auth_flash_pm']) ? true : false;

			$sql = 'SELECT * FROM ' . PRIVMSGS_TABLE . ' 
				ORDER BY msg_id ASC';
			$result = $db->sql_query_limit($sql, $limit, $start);

		#	$db->sql_return_on_error(true);
			while ($row = $db->sql_fetchrow($result))
			{
				if ( in_array( $row['msg_id'], $skip_post) ) { continue; }
				decode_message($row['message_text'], $row['bbcode_uid']);

				$message_parser = new parse_message();
				$message_parser->message = str_replace('"', '&quot;', html_entity_decode($row['message_text']));
				$message_parser->parse((($bbcode_status) ? $row['enable_bbcode'] : false), $row['enable_magic_url'], $row['enable_smilies'], $img_status, $flash_status, true, true);

				$sql_data = array(
					'message_text'			=> $message_parser->message,
					'bbcode_bitfield'		=> $message_parser->bbcode_bitfield,
					'bbcode_uid'			=> $message_parser->bbcode_uid,
				);

				$sql = 'UPDATE ' . PRIVMSGS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_data) . ' 
					WHERE msg_id = ' . $row['msg_id'];
				$db->sql_query($sql);
			}
		#	$db->sql_return_on_error(false);
			$db->sql_freeresult($result);

			if ($post_count > $next_step)
			{
				$ary_msg_err[] = $user->lang['LONG_SCRIPT_EXECUTION'];
				$ary_msg_err[] = sprintf($user->lang['STEP_PERCENT_COMPLETED'], $step, intval($post_count / $limit) );

				$meta_url = "?mode=$mode&amp;start_step=2&amp;start=$next_step&step=$step";
				$template->assign_vars(array( 'META' => '<meta http-equiv="refresh" content="1 ;url=' . $meta_url . '" />' ));
			}
			else
			{
				$ary_msg_ok[] = sprintf($user->lang['INSTALLER_REPARSE_SUCCESSFUL'], $user->lang['INSTALLER_REPARSE_PM']);
				$start_step = 3;
			}
		}
		else
		{
			$template->assign_vars(array(
				'TITLE'		=> $user->lang['INSTALLER_REPARSE_WELCOME'],
				'BODY'		=> $user->lang['INSTALLER_REPARSE_WELCOME_NOTE'],
				'WARNING'	=> true,
				'INSTALLER_NOTE'	=> $user->lang['INSTALLER_REPARSE_NOTE'] . '</p><p>' . $user->lang['INSTALLER_REPARSE_WARNING'],
				'LEGEND'	=> $user->lang['INSTALLER_REPARSE'],
				'LABEL'		=> $user->lang['INSTALLER_REPARSE_POST'],
				'STEP'		=> 0,
			));
		}

		foreach ($ary_msg_ok as $msg_text)
		{
			$template->assign_block_vars('install_block1', array( 'NOTE' => $msg_text ));
		}
		foreach ($ary_msg_err as $msg_text)
		{
			$template->assign_block_vars('install_block2', array( 'NOTE' => $msg_text ));
		}

		$template->assign_vars(array(
			'MODE'		=> $mode,
			'STEP'		=> $start_step,
			'U_ACTION'	=> append_sid("{$phpbb_root_install}install.$phpEx", "mode=$mode&amp;start_step=1"),
		));

	break;
	default:
		$template->assign_vars(array(
			'TITLE'				=> sprintf($user->lang['INSTALLER_INTRO_WELCOME'], $user->lang['INSTALLER_TITLE']),
			'BODY'				=> $user->lang['INSTALLER_INTRO_WELCOME_NOTE'],
			'INSTALLER_NOTE'	=> $user->lang['INSTALLER_NOTE']
		));
	break;
}


$template->assign_block_vars('t_block1', array(
	'L_TITLE'		=> $user->lang['INSTALLER_INTRO'],
	'S_SELECTED'	=> true,
	'U_TITLE'		=> append_sid("{$phpbb_root_install}install.$phpEx"),
));

$install_module = array(
	'INSTALLER_INSTALL_MENU' => array( 'INSTALLER_INSTALL_VERSION' => 'install', 'INSTALLER_UPDATE_VERSION' => 'update', 'INSTALLER_DELETE_VERSION' => 'delete'), 
	'INSTALLER_EXTRA_MENU'	 => array( 'INSTALLER_REPARSE_POST' => 'reparsePost', 'INSTALLER_REPARSE_SIG' => 'reparseSig' , 'INSTALLER_REPARSE_PM' => 'reparsePM' )
);

foreach ($install_module as $cat => $subs )
{
	$cat_name = $cat;
	$template->assign_block_vars('l_block1', array(
		'L_TITLE'		=> $user->lang[$cat],
	));

	if ( is_array($subs) )
	{
		foreach ($subs as $subcat => $submode )
		{
			$subcat_name = $subcat;
			$template->assign_block_vars('l_block1.l_block2', array(
				'L_TITLE'		=> ( strpos($user->lang[$subcat_name], '%' ) ) ? sprintf($user->lang[$subcat_name], $mod_version) : $user->lang[$subcat_name],
				'S_SELECTED'	=> ( $mode == $submode ) ? true : false,
				'U_TITLE'		=> append_sid("{$phpbb_root_install}install.$phpEx", "mode=$submode")
			));
		}
	}
}

$template->set_custom_template('../adm/style', 'admin');
$template->assign_var('T_TEMPLATE_PATH', '../adm/style');

// the acp template is never stored in the database
$user->theme['template_storedb'] = false;

// Output page
page_header($page_title);

$template->set_filenames(array(
	'body' => 'install_abbcodes.html',
));

page_footer();


/************************************************************************************************************************************************
* funtions
*************************************************************************************************************************************************/

function get_abbc3_modules( $id )
{
	$modulesdata = array(
		'3' => array(
			'module_basename' => '',
			'module_enabled'  => 1,
			'module_display'  => 1,
			'parent_id'		  => $id,
			'module_class'	  => 'acp',
			'module_langname' => 'ACP_ABBCODES',
			'module_mode'	  => '',
			'module_auth'	  => '',
		),
		'2'=> array(
			'module_basename' => 'abbcodes',
			'module_enabled'  => 1,
			'module_display'  => 1,
			'parent_id'		  => 0,
			'module_class'	  => 'acp',
			'module_langname' => 'ACP_ABBC3_SETTINGS',
			'module_mode'	  => 'settings',
			'module_auth'	  => 'acl_a_styles',
		),
		'1'	=> array(
			'module_basename' => 'abbcodes',
			'module_enabled'  => 1,
			'module_display'  => 1,
			'parent_id'		  => 0,
			'module_class'	  => 'acp',
			'module_langname' => 'ACP_ABBC3_BBCODES',
			'module_mode'	  => 'bbcodes',
			'module_auth'	  => 'acl_a_bbcode',
		),
	);
	return $modulesdata;
}

function get_abbc3_config()
{
	global $config;

	$max_filesize		 = ( $config['max_filesize']		) ? $config['max_filesize']			: 0	;
	$img_max_width		 = ( $config['img_max_width']		) ? $config['img_max_width']		: 500 ;
	$img_max_thumb_width = ( $config['img_max_thumb_width']	) ? $config['img_max_thumb_width']	: 200 ;

	$config_data = array(
		'ABBC3_MOD'				=> 1,
		'ABBC3_PATH'			=> 'styles/abbcode',
		'ABBC3_TAB'				=> 1,
		'ABBC3_BG'				=> 'bg_abbc3.gif',
		'ABBC3_BOXRESIZE'		=> 1,
		'ABBC3_MAX_IMG_WIDTH'	=> $img_max_width,
		'ABBC3_MAX_IMG_HEIGHT'	=> 0,
		'ABBC3_MAX_THUM_WIDTH'	=> $img_max_thumb_width,
//		'ABBC3_JAVASCRIPT'		=> 1,
		'ABBC3_RESIZE'			=> 1,
		'ABBC3_RESIZE_METHOD'	=> 'AdvancedBox',
		'ABBC3_RESIZE_SIGNATURE'=> 0,
		'ABBC3_VIDEO_height'	=> 350,
		'ABBC3_VIDEO_width'		=> 425,
		'ABBC3_UPLOAD_MAX_SIZE'	=> $max_filesize,
		'ABBC3_UPLOAD_EXTENSION'=> 'swf, gif, jpg, jpeg, png, psd, bmp, tif, tiff',
		'max_post_font_size'	=> 300,
	);
	return $config_data;
}

function get_abbc3_bbcodes()
{
	$bbcode_data = array(
		'font'			=> array( "font=",			 1,	1,	"ABBC3_FONT_TIP",		 "[font={SIMPLETEXT}]{TEXT2}[/font]", "<span style=\"font-family: {SIMPLETEXT};\">{TEXT2}</span>", "!\[font\=(.*?)\](.*?)\[/font\]!ies", '\'[font=\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\':$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/font:$uid]\'', '!\[font\=(.*?):$uid\](.*?)\[/font:$uid\]!s', '<span style="font-family: ${1};">${2}</span>', 1, 1, 1, 1, " ") ,
		'size'			=> array( "size",			 2,	0,	"ABBC3_SIZE_TIP",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, " ") ,
		'highlight'		=> array( "highlight=",		 3, 1,	"ABBC3_HIGHLIGHT_TIP",	 "[highlight={COLOR}]{TEXT}[/highlight]", "<span style=\"background-color: {COLOR};\">{TEXT}</span>", "!\[highlight=([a-z]+|#[0-9abcdef]+)\](.*?)\[/highlight\]!ies", '\'[highlight=${1}:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/highlight:$uid]\'', '!\[highlight\=([a-zA-Z]+|#[0-9abcdefABCDEF]+):$uid\](.*?)\[/highlight:$uid\]!s', '<span style="background-color: ${1};">${2}</span>', 1, 1, 1, 1, " "),
		'color'			=> array( "color",			 4,	0,	"ABBC3_COLOR_TIP",		 ".", ".", ".", ".", ".", ".", 0, 0, 0, 1, " "),
		'cut'			=> array( "cut",			 5, 0,	"ABBC3_CUT_MOVER",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "cut.gif"),
		'copy'			=> array( "copy",			 6,	0,	"ABBC3_COPY_MOVER",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "copy.gif"),
		'paste'			=> array( "paste",			 7,	0,	"ABBC3_PASTE_MOVER",	 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "paste.gif"),
		'plain'			=> array( "plain",			 8,	0,	"ABBC3_PLAIN_MOVER",	 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "plain.gif"),
		'division1'		=> array( "division1",		 9,	0,	"ABBC3_DIVISION",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "dots.gif"),
		'listb'			=> array( "listb",			10,	0,	"ABBC3_LISTB_TIP",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "listb.gif"),
		'listo'			=> array( "listo",			11,	0,	"ABBC3_LISTO_TIP",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "listo.gif"),
		'listitem'		=> array( "listitem",		12,	0,	"ABBC3_LISTITEM_TIP",	 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "listitem.gif"),
		'hr'			=> array( "hr",				13, 1,	"ABBC3_HR_TIP",			 "[hr]", "<hr class=\"hrabbc3\" />", "!\[hr\]!ies", '\'[hr:$uid]\'', '!\[hr:$uid\]!s', '<hr class="hrabbc3" />', 1, 1, 1, 1, "hr.gif"),
		'break1'		=> array( "break1",			14,	0,	"ABBC3_BREAK",			 ".", ".", ".", ".", ".", ".", 0, 0, 0, 1, "spacer.gif"),
		'b'				=> array( "b",				15,	0,	"ABBC3_B_TIP",			 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "bold.gif"),
		'i'				=> array( "i",				16,	0,	"ABBC3_I_TIP",			 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "italic.gif"),
		'u'				=> array( "u",				17,	0,	"ABBC3_U_TIP",			 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "under.gif"),
		's'				=> array( "s",				18, 1,	"ABBC3_STRIKE_TIP",		 "[s]{TEXT}[/s]", "<span style=\"text-decoration: line-through;\">{TEXT}</span>", "!\[s\](.*?)\[/s\]!ies", '\'[s:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/s:$uid]\'', '!\[s:$uid\](.*?)\[/s:$uid\]!s', '<span style="text-decoration: line-through;">${1}</span>', 1, 1, 1, 1, "strike.gif"),
		'sup'			=> array( "sup",			19, 1,	"ABBC3_SUP_TIP",		 "[sup]{TEXT}[/sup]", "<sup>{TEXT}</sup>", "!\[sup\](.*?)\[/sup\]!ies", '\'[sup:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/sup:$uid]\'', '!\[sup:$uid\](.*?)\[/sup:$uid\]!s', '<sup>${1}</sup>', 1, 1, 1, 1, "sup.gif"),
		'sub'			=> array( "sub",			20, 1,	"ABBC3_SUB_TIP",		 "[sub]{TEXT}[/sub]", "<sub>{TEXT}</sub>", "!\[sub\](.*?)\[/sub\]!ies", '\'[sub:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/sub:$uid]\'', '!\[sub:$uid\](.*?)\[/sub:$uid\]!s', '<sub>${1}</sub>', 1, 1, 1, 1, "sub.gif"),
		'glow'			=> array( "glow=",			21, 1,	"ABBC3_GLOW_TIP",		 "[glow={COLOR}]{TEXT}[/glow]", "<div style=\"filter:Glow(color={COLOR},strength=4);color:#ffffff;height:110%\">{TEXT}</div>", "!\[glow\=([a-z]+|#[0-9abcdef]+)\](.*?)\[/glow\]!ies", '\'[glow=${1}:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/glow:$uid]\'', '!\[glow\=([a-zA-Z]+|#[0-9abcdefABCDEF]+):$uid\](.*?)\[/glow:$uid\]!s', '<div style="filter:Glow(color=${1},strength=4);color:#ffffff;height:110%">${2}</div>', 1, 1, 1, 1, "glow.gif"),
		'shadow'		=> array( "shadow=",		22, 1,	"ABBC3_SHADOW_TIP",		 "[shadow={COLOR}]{TEXT}[/shadow]", "<div style=\"filter:shadow(color=black,strength=4);color:{COLOR};height:110%\">{TEXT}</div>", "!\[shadow\=([a-z]+|#[0-9abcdef]+)\](.*?)\[/shadow\]!ies", '\'[shadow=${1}:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/shadow:$uid]\'', '!\[shadow\=([a-zA-Z]+|#[0-9abcdefABCDEF]+):$uid\](.*?)\[/shadow:$uid\]!s', '<div style="filter:shadow(color=black,strength=4);color:${1};height:110%">${2}</div>', 1, 1, 1, 1, "shadow.gif"),
		'dropshadow'	=> array( "dropshadow=",	23, 1,	"ABBC3_DROPSHADOW_TIP",	 "[dropshadow={COLOR}]{TEXT}[/dropshadow]", "<div style=\"filter:dropshadow(color=#999999,strength=4);color:{COLOR};height:110%\">{TEXT}</div>", "!\[dropshadow\=([a-z]+|#[0-9abcdef]+)\](.*?)\[/dropshadow\]!ies", '\'[dropshadow=${1}:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/dropshadow:$uid]\'', '!\[dropshadow\=([a-zA-Z]+|#[0-9abcdefABCDEF]+):$uid\](.*?)\[/dropshadow:$uid\]!s', '<div style="filter:dropshadow(color=#999999,strength=4);color:${1};height:110%">${2}</div>', 1, 1, 1, 1, "dropshadow.gif"),
		'blur'			=> array( "blur=",			24, 1,	"ABBC3_BLUR_TIP",		 "[blur={COLOR}]{TEXT}[/blur]", "<div style=\"filter:Blur(strength=7);color:{COLOR};height:110%\">{TEXT}</div>", "!\[blur\=([a-z]+|#[0-9abcdef]+)\](.*?)\[/blur\]!ies", '\'[blur=${1}:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/blur:$uid]\'', '!\[blur\=([a-zA-Z]+|#[0-9abcdefABCDEF]+):$uid\](.*?)\[/blur:$uid\]!s', '<div style="filter:Blur(strength=7);color:${1};height:110%">${2}</div>', 1, 1, 1, 1, "blur.gif"),
		'wave'			=> array( "wave=",			25, 1,	"ABBC3_WAVE_TIP",		 "[wave={COLOR}]{TEXT}[/wave]", "<div style=\"filter:Wave(strength=2);color:{COLOR};height:110%\">{TEXT}</div>", "!\[wave\=([a-z]+|#[0-9abcdef]+)\](.*?)\[/wave\]!ies", '\'[wave=${1}:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/wave:$uid]\'', '!\[wave\=([a-zA-Z]+|#[0-9abcdefABCDEF]+):$uid\](.*?)\[/wave:$uid\]!s', '<div style="filter:Wave(strength=2);color:${1};height:110%">${2}</div>', 1, 1, 1, 1, "wave.gif"),
		'fade'			=> array( "fade",			26, 1,	"ABBC3_FADE_TIP",		 "[fade]{TEXT}[/fade]", "<span class=\"fade_link\">{TEXT}</span> <script type=\"text/javascript\">fade_ontimer();</script>", "!\[fade\](.*?)\[/fade\]!ies", '\'[fade:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/fade:$uid]\'', '!\[fade:$uid\](.*?)\[/fade:$uid\]!s', '<span class="fade_link">${1}</span> <script type="text/javascript">fade_ontimer();</script>', 1, 1, 1, 1, "fade.gif"),
		'grad'			=> array( "grad",			27,	0,	"ABBC3_GRAD_TIP",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "grad.gif"),
		'align justify'	=> array( "align=justify",	28, 1,	"ABBC3_JUSTIFY_TIP",	 "[align=justify]{TEXT}[/align]", "<div style=\"text-align:justify\">{TEXT}</div>", "!\[align\=justify\](.*?)\[/align\]!ies", '\'[align=justify:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/align:$uid]\'', '!\[align\=justify:$uid\](.*?)\[/align:$uid\]!si', '<div style="text-align:justify">${1}</div>', 1, 1, 1, 1, "justify.gif"),
		'align left'	=> array( "align=left"	,	29, 1,	"ABBC3_LEFT_TIP",		 "[align=left]{TEXT}[/align]", "<div style=\"text-align:left\">{TEXT}</div>", "!\[align\=left\](.*?)\[/align\]!ies", '\'[align=left:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/align:$uid]\'', '!\[align\=left:$uid\](.*?)\[/align:$uid\]!si', '<div style="text-align:left">${1}</div>', 1, 1, 1, 1, "left.gif"),
		'align center'	=> array( "align=center",	30, 1,	"ABBC3_CENTER_TIP",		 "[align=center]{TEXT}[/align]", "<div style=\"text-align:center\">{TEXT}</div>", "!\[align\=center\](.*?)\[/align\]!ies", '\'[align=center:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/align:$uid]\'', '!\[align\=center:$uid\](.*?)\[/align:$uid\]!si', '<div style="text-align:center; display:table; margin-left:auto; margin-right:auto;">${1}</div>', 1, 1, 1, 1, "center.gif"),
		'center'		=> array( "center",			31, 1,	"[center]your text here[/center]", "[center]{TEXT}[/center]", "<center>{TEXT}</center>", "!\[center\](.*?)\[/center\]!ies", '\'[center:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/center:$uid]\'', '!\[center:$uid\](.*?)\[/center:$uid\]!s', '<center>${1}</center>', 1, 1, 1, 0, " "),
		'align right'	=> array( "align=right",	32, 1,	"ABBC3_RIGHT_TIP",		 "[align=right]{TEXT}[/align]", "<div style=\"text-align:right\">{TEXT}</div>", "!\[align\=right\](.*?)\[/align\]!ies", '\'[align=right:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/align:$uid]\'', '!\[align\=right:$uid\](.*?)\[/align:$uid\]!si', '<div style="text-align:right">${1}</div>', 1, 1, 1, 1, "right.gif"),
		'pre'			=> array( "pre",			33, 1,	"ABBC3_PRE_TIP",		 "[pre]{TEXT}[/pre]", "<pre>{TEXT}</pre>", "!\[pre\](.*?)\[/pre\]!ies", '\'[pre:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/pre:$uid]\'', '!\[pre:$uid\](.*?)\[/pre:$uid\]!s', '<pre>${1}</pre>', 1, 1, 1, 1, "preformat.gif"),
		'division2'		=> array( "division2",		34,	0,	"ABBC3_DIVISION",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "dots.gif"),
		'tab'			=> array( "tab=",			35, 1,	"ABBC3_TAB_TIP",		 "[tab={NUMBER}]", "<span style=\"margin-left:{NUMBER}px;\"></span>", "!\[tab=([0-9]?[0-9]?[0-9])\]!ies", '\'[tab=${1}:$uid]\'', '!\[tab\\=([0-9]?[0-9]?[0-9]):$uid\]!s', '<span style="margin-left:${1}px;"></span>', 1, 1, 1, 1, "tab.gif"),
		'dir ltr'		=> array( "dir=ltr",		36, 1,	"ABBC3_LTR_TIP",		 "[dir=ltr]{TEXT}[/dir]", "<BDO dir=\"ltr\">{TEXT}</BDO>", "!\[dir\=ltr\](.*?)\[/dir\]!ies", '\'[dir=ltr:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/dir:$uid]\'', '!\[dir\=ltr:$uid\](.*?)\[/dir:$uid\]!s', '<BDO dir="ltr">${1}</BDO>', 1, 1, 1, 1, "ltr.gif"),
		'dir rtl'		=> array( "dir=rtl",		37, 1,	"ABBC3_RTL_TIP",		 "[dir=rtl]{TEXT}[/dir]", "<BDO dir=\"rtl\">{TEXT}</BDO>", "!\[dir\=rtl\](.*?)\[/dir\]!ies", '\'[dir=rtl:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/dir:$uid]\'', '!\[dir\=rtl:$uid\](.*?)\[/dir:$uid\]!s', '<BDO dir="rtl">${1}</BDO>', 1, 1, 1, 1, "rtl.gif"),
		'marq up'		=> array( "marq=up",		38, 1,	"ABBC3_MARQU_TIP",		 "[marq=up]{TEXT}[/marq]", "<marquee direction=\"up\" scrolldelay=\"100\" onmouseover=\"this.scrollDelay=10000000\" onmouseout=\"this.scrollDelay=100\">{TEXT}</marquee>", "!\[marq\=up\](.*?)\[/marq\]!ies", '\'[marq=up:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/marq:$uid]\'', '!\[marq\=up:$uid\](.*?)\[/marq:$uid\]!s', '<marquee direction="up" scrolldelay="100" onmouseover="this.scrollDelay=10000000" onmouseout="this.scrollDelay=100">${1}</marquee>', 1, 1, 1, 1, "marqu.gif"),
		'marq down'		=> array( "marq=down",		39, 1,	"ABBC3_MARQD_TIP",		 "[marq=down]{TEXT}[/marq]", "<marquee direction=\"down\" scrolldelay=\"100\" onmouseover=\"this.scrollDelay=10000000\" onmouseout=\"this.scrollDelay=100\">{TEXT}</marquee>", "!\[marq\=down\](.*?)\[/marq\]!ies", '\'[marq=down:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/marq:$uid]\'', '!\[marq\=down:$uid\](.*?)\[/marq:$uid\]!s', '<marquee direction="down" scrolldelay="100" onmouseover="this.scrollDelay=10000000" onmouseout="this.scrollDelay=100">${1}</marquee>', 1, 1, 1, 1, "marqd.gif"),
		'marq left'		=> array( "marq=left",		40, 1,	"ABBC3_MARQL_TIP",		 "[marq=left]{TEXT}[/marq]", "<marquee direction=\"left\" scrolldelay=\"100\" onmouseover=\"this.scrollDelay=10000000\" onmouseout=\"this.scrollDelay=100\">{TEXT}</marquee>", "!\[marq\=left\](.*?)\[/marq\]!ies", '\'[marq=left:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/marq:$uid]\'', '!\[marq\=left:$uid\](.*?)\[/marq:$uid\]!s', '<marquee direction="left" scrolldelay="100" onmouseover="this.scrollDelay=10000000" onmouseout="this.scrollDelay=100">${1}</marquee>', 1, 1, 1, 1, "marql.gif"),
		'marq=right'	=> array( "marq=right",		41, 1,	"ABBC3_MARQR_TIP",		 "[marq=right]{TEXT}[/marq]", "<marquee direction=\"right\" scrolldelay=\"100\" onmouseover=\"this.scrollDelay=10000000\" onmouseout=\"this.scrollDelay=100\">{TEXT}</marquee>", "!\[marq\=right\](.*?)\[/marq\]!ies", '\'[marq=right:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/marq:$uid]\'', '!\[marq\=right:$uid\](.*?)\[/marq:$uid\]!s', '<marquee direction="right" scrolldelay="100" onmouseover="this.scrollDelay=10000000" onmouseout="this.scrollDelay=100">${1}</marquee>', 1, 1, 1, 1, "marqr.gif"),
		'break2'		=> array( "break2",			42,	0,	"ABBC3_BREAK",			 ".", ".", ".", ".", ".", ".", 0, 0, 0, 1, "spacer.gif"),
		'code'			=> array( "code",			43,	0,	"ABBC3_CODE_TIP",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "code.gif"),
		'quote'			=> array( "quote",			44,	0,	"ABBC3_QUOTE_TIP",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "quote.gif"),
		'spoil'			=> array( "spoil",			45, 1,	"ABBC3_SPOIL_TIP",		 "[spoil]{TEXT}[/spoil]", '<div class=\"spoilwrapper\"><div class=\"spoiltitle\"><input id=\"0"\class=\"btnspoil button2\" type=\"button\" value=\"{L_SPOILER_SHOW}\"></div><div class="spoilcontent"><div id=\"1\" style=\"display: none;\">{TEXT}</div></div></div>', "!\[spoil\](.*?)\[/spoil\]!ies", '\'[spoil:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/spoil:$uid]\'', '!\[spoil:$uid\](.*?)\[/spoil:$uid\]!ies', '$this->spoil_pass( \'$1\' )', 1, 1, 1, 1, "spoil.gif"),
		'hidden'		=> array( "hidden",			46, 1,  "ABBC3_HIDDEN_TIP", 	 "[hidden]{TEXT}[/hidden]", '<div class=\"hiddenbox\"><span class=\"hidden\">{L_MESSAGE_HIDDEN}:</span><div class=\"hiddentext\">{TEXT}</div></div>', "!\[hidden\](.*?)\[/hidden\]!ies", '\'[hidden:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/hidden:$uid]\'', '!\[hidden:$uid\](.*?)\[/hidden:$uid\]!ies', '$this->hidden_pass( \'$1\' )', 1, 1, 1, 1, "hidden.gif"),
		'mod'			=> array( "mod=",			47, 1,	"ABBC3_MODERATOR_TIP",	 "[mod={TEXT1}]{TEXT2}[/mod]", "<table class=\"ModTable\" width=\"100%\" cellspacing=\"5\" cellpadding=\"0\" border=\"0\"><tr><td class=\"exclamation\" rowspan=\"2\">&nbsp;!&nbsp;</td><td class=\"rowuser\">{TEXT1}:</td></tr><tr><td class=\"rowtext\">{TEXT2}</td></tr></table>", "!\[mod\=(.*?)\](.*?)\[/mod\]!ies", '\'[mod=${1}:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/mod:$uid]\'', '!\[mod\=(.*?):$uid\](.*?)\[/mod:$uid\]!ies', '$this->moderator_pass( \'$1\', \'$2\' )', 1, 1, 1, 1, "moderator.gif", "5, 4"),
		'offtopic'		=> array( "offtopic",		48, 1,	"ABBC3_OFFTOPIC",		 "[offtopic]{TEXT}[/offtopic]", "<div class=\"OffTopic\"><div class=\"OffTopicTitle\">{L_OFFTOPIC} :</div><div class=\"OffTopicText\">{TEXT}</div></div>", "!\[offtopic\](.*?)\[/offtopic\]!ies", '\'[offtopic:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')).\'[/offtopic:$uid]\'', '!\[offtopic:$uid\](.*?)\[/offtopic:$uid\]!ies', '$this->offtopic_pass( \'$1\' )', 1, 1, 1, 1, "offtopic.gif"),
		'nfo'			=> array( "nfo",			49, 1,	"ABBC3_NFO_TIP",		 "[nfo]{TEXT}[/nfo]", "<table cellspacing=\"0\" cellpadding=\"0\" border=\"0\" ><tr><td><span class=\"genmed\"><b>NFO:</b></span></td></tr><tr><td class=\"nfo\">{TEXT}</td></tr></table>", "!\[nfo\](.*?)\[/nfo\]!ies", '\'[nfo:$uid]${1}[/nfo:$uid]\'', '!\[nfo:$uid\](.*?)\[/nfo:$uid\]!ies', '$this->nfo_pass( \'$1\' )', 1, 1, 1, 1, "nfo.gif"),
		'table'			=> array( "table=",			50, 1,	"ABBC3_TABLE_TIP",		 "[table={TEXT1}]{TEXT2}[/table]", "<table style=\"{TEXT1}\" cellspacing=\"0\" cellpadding=\"0\">{TEXT2}</table>", "!\[table\=(.*?)\](.*?)\[/table\]!ies", '\'[table=\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${1}\')) . \':$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${2}\')).\'[/table:$uid]\'', '!\[table\=(.*?):$uid\](.*?)\[/table:$uid\]!ies', '$this->table_pass( \'$1\', \'$2\' )', 1, 1, 1, 1, "table.gif"),
		'division3'		=> array( "division3",		51,	0,	"ABBC3_DIVISION",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "dots.gif"),
		'upload'		=> array( "upload",			52,	0,	"ABBC3_UPLOAD_MOVER",	 ".", ".", ".", ".", ".", ".", 1, 0, 0, 1, "upload.gif", "5, 4"),
		'url'			=> array( "url",			53,	0,	"ABBC3_URL_TIP",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "url.gif"),
		'email'			=> array( "email",			54,	0,	"ABBC3_EMAIL_TIP",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "email.gif"),
		'web'			=> array( "web",			55, 1,	"ABBC3_WEB_TIP",		 "[web{TEXT}]{URL}[/web]", "<a src=\"{URL}\">{TEXT}</a>", "!\[web(=| )?(.*?)\](.*?)\[/web\]!ies", '\'[web${1}${2}:$uid]\'.(!preg_match(\'#^[a-z][a-z\d+\-.]*:/{2}#i\', trim(\'${3}\')) ? \'http://${3}\' : \'${3}\').\'[/web:$uid]\'', '!\[web((=| )?(width=)?([0-9]?[0-9]?[0-9][(%|\w+)?])(,| )(height=)?([0-9]?[0-9]?[0-9][(%|\w+)?]))?:$uid\](.*?)\[/web:$uid\]!s', '<iframe width="${4}" height="${7}" src="${8}" security="restricted" style="font-size: 2px;"></iframe>', 0, 0, 0, 1, "web.gif"),
		'ed2k'			=> array( "url=",			56,	0,	"ABBC3_ED2K_TIP",		 ".", ".", ".", ".",".", ".", 1, 1, 0, 1, "emule.gif"),
		'img'			=> array( "img=",			57, 1,	"ABBC3_IMG_TIP",		 "[img{TEXT}]{URL}[/img]", "<img src=\"{URL}\" alt=\"{L_IMAGE}\" />", "!\[img(\\=| )?(left|center|right)?\](.*?)\[/img\]!ies", '\'[img${1}${2}:$uid]${3}[/img:$uid]\'', '!\[img(=| )?(left|center|right)?:$uid\](.*?)\[/img:$uid\]!ies', '$this->img_pass( \'$2\',\'$3\')', 1, 1, 1, 1, "img.gif"),
		'thumbnail'		=> array( "thumbnail",		58, 1,	"ABBC3_THUMBNAIL_TIP",	 "[thumbnail{TEXT}]{URL}[/thumbnail]", "<img src=\"{URL}\" border=\"0\" align=\"{TEXT}\"/>", "!\[thumbnail(\\=(left|center|right))?\](.*?)\[/thumbnail\]!ies", '\'[thumbnail${1}:$uid]${3}[/thumbnail:$uid]\'', '!\[thumbnail(\\=(left|center|right))?:$uid\](.*?)\[/thumbnail:$uid\]!ies', '$this->thumb_pass( \'$2\',\'$3\')', 1, 1, 1, 1, "thumb.gif"),
		'imgshack'		=> array( "imgshack",		59,	0,	"ABBC3_IMGSHACK_MOVER",	 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "imgshack.gif"),
		'rapidshare'	=> array( "rapidshare",		60, 1,	"ABBC3_RAPIDSHARE_TIP",	 "[rapidshare]{URL}[/rapidshare]", "<a src=\"{URL}\">{URL}</a>", "!\[rapidshare\](.*?)\[/rapidshare\]!ies", '\'[rapidshare:$uid]${1}[/rapidshare:$uid]\'', '!\[rapidshare:$uid\](.*?)\[/rapidshare:$uid\]!ies', '$this->rapidshare_pass(\'$1\')', 0, 0, 0, 1, "rapidshare.gif"),
		'testlink'		=> array( "testlink",		61, 1,	"ABBC3_TESTLINK_TIP",	 "[testlink]{TEXT}[/testlink]", "<a src=\"{TEXT}\">{TEXT}</a>", "!\[testlink\](.*?)\[/testlink\]!ies", '\'[testlink:$uid]${1}[/testlink:$uid]\'', '!\[testlink:$uid\](.*?)\[/testlink:$uid\]!ies', '$this->testlink_pass(\'$1\')', 0, 0, 0, 1, "testlink.gif"),
		'click'			=> array( "click",			62, 1,	"ABBC3_CLICK_TIP",		 "[click{URL}]{URL}[/click]", "<a src=\"{URL}\">{URL}</a>", "!\[click(\\=(.*?))?\](.*?)\[/click\]!ies", '\'[click${1}:$uid]${3}[/click:$uid]\'', '!\[click(\\=(.*?))?:$uid\](.*?)\[/click:$uid\]!ies', '$this->click_pass(\'$2\', \'$3\')', 0, 0, 0, 1, "click.gif"),
		'search'		=> array( "search",			63, 1,	"ABBC3_SEARCH_TIP",		 "[search{TEXT1}]{TEXT2}[/search]", "<a src=\"{TEXT1}\">{TEXT2}</a>", "!\[search(\\=(msn|msnlive|yahoo|google|altavista|lycos|wikipedia))?\](.*?)\[/search\]!ies", '\'[search${1}:$uid]\'.str_replace(array("\r\n", \'\"\', \'\\\'\', \'(\', \')\'), array("\n", \'"\', \'&#39;\', \'&#40;\', \'&#41;\'), trim(\'${3}\')).\'[/search:$uid]\'', '!\[search(\\=(.*?))?:$uid\](.*?)\[/search:$uid\]!ies', '$this->search_pass(\'$1\', \'$2\', \'$3\')', 1, 1, 1, 1, "search.gif"),
		'division4'		=> array( "division4",		64,	0,	"ABBC3_DIVISION",		 ".", ".", ".", ".", ".", ".", 1, 1, 1, 1, "dots.gif"),
		'BBvideo'		=> array( "BBvideo",		65, 1,	"ABBC3_BBVIDEO_TIP",	 "[BBvideo{TEXT1}]{TEXT2}[/BBvideo]", "<a src=\"{TEXT1}\">{TEXT2}</a>", "!\[BBvideo(\\=| )?(.*?)\](.*?)\[/BBvideo\]!ies", '\'[BBvideo${1}${2}:$uid]${3}[/BBvideo:$uid]\'', '!\[BBvideo((=| )?(width=)?([0-9]?[0-9]?[0-9])(,| )(height=)?([0-9]?[0-9]?[0-9]))?:$uid\](.*?)\[/BBvideo:$uid\]!ies', '$this->BBvideo_pass(\'$8\', \'$4\', \'$7\')', 1, 0, 0, 1, "bbvideo.gif"),
		'flash'			=> array( "flash",			66, 1,	"ABBC3_FLASH_TIP",		 "[flash{TEXT}]{URL}[/flash]", "<a src=\"{URL}\">{TEXT}</a>", "!\[flash(=| )?(.*?)\](.*?)\[/flash\]!ies", '\'[flash${1}${2}:$uid]${3}[/flash:$uid]\'', '!\[flash((=| )?(width=)?([0-9]?[0-9]?[0-9])(,| )(height=)?([0-9]?[0-9]?[0-9]))?:$uid\](.*?)\[/flash:$uid\]!s', '<object classid="clsid:D27CDB6E-AE6D-11CF-96B8-444553540000" codebase="http://active.macromedia.com/flash2/cabs/swflash.cab#version=5,0,0,0" width="${4}" height="${7}"><param name="movie" value="${8}" /><param name="play" value="true" /><param name="loop" value="true" /><param name="quality" value="high" /><param name="allowScriptAccess" value="never" /><param name="allowNetworking" value="internal" /><embed src="${8}" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" width="${4}" height="${7}" play="true" loop="true" quality="high" allowscriptaccess="never" allownetworking="internal"></embed></object><br />', 0, 0, 0, 1, "flash.gif"),
		'flv'			=> array( "flv",			67, 1,	"ABBC3_FLV_TIP",		 "[flv{TEXT}]{URL}[/flv]", "<a src=\"{URL}\">{TEXT}</a>", "!\[flv(\\=| )?(.*?)\](.*?)\[/flv\]!ies", '\'[flv${1}${2}:$uid]${3}[/flv:$uid]\'', '!\[flv((=| )?(width=)?([0-9]?[0-9]?[0-9])(,| )(height=)?([0-9]?[0-9]?[0-9]))?:$uid\](.*?)\[/flv:$uid\]!s', '<embed src="./images/flvplayer.swf" width="${4}" height="${7}" bgcolor="#FFFFFF" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" allowfullscreen="true" flashvars="file=${8}&autostart_step=false" /><br />', 0, 0, 0, 1, "flashflv.gif"),
		'video'			=> array( "video",			68, 1,	"ABBC3_VIDEO_TIP",		 "[video{TEXT}]{URL}[/video]", "<a src=\"{URL}\">{TEXT}</a>", "!\[video(\\=| )?(.*?)\](.*?)\[/video\]!ies", '\'[video${1}${2}:$uid]${3}[/video:$uid]\'', '!\[video((=| )?(width=)?([0-9]?[0-9]?[0-9])(,| )(height=)?([0-9]?[0-9]?[0-9]))?:$uid\](.*?)\[/video:$uid\]!s', '<object classid="CLSID:6BF52A52-394A-11d3-B153-00C04F79FAA6" id="player_${8}" width="${4}" height="${7}"><param name="url" value="${8}" /><param name="src" value="${8}" /><param name="showcontrols" value="1" /><param name="autostart_step" value="0" /><param name="autostart" value="0" /><!--[if !IE]>--><object type="video/x-ms-wmv" data="${8}" width="${4}" height="${7}" ><param name="src" value="${8}" /><param name="autostart_step" value="0" /><param name="autostart" value="0" /><param name="controller" value="1" /></object><!--<![endif]--></object><br />', 0, 0, 0, 1, "video.gif"),
		'stream'		=> array( "stream",			69, 1,	"ABBC3_STREAM_TIP",		 "[stream]{URL}[/stream]", "<a src=\"{URL}\">{URL}</a>", "!\[stream\](.*?)\[/stream\]!ies", '\'[stream:$uid]${1}[/stream:$uid]\'', '!\[stream:$uid\](.*?)\[/stream:$uid\]!s', '<object width="320" height="45" classid="CLSID:6BF52A52-394A-11d3-B153-00C04F79FAA6" id="wmstream_${1}"><param name="url" value="${1}" /><param name="showcontrols" value="1" /><param name="showdisplay" value="0" /><param name="showstatusbar" value="0" /><param name="autosize" value="1" /><param name="autostart_step" value="0" /><param name="autostart" value="0" /><param name="visible" value="1" /><param name="animationstart_step" value="0" /><param name="loop" value="0" /><param name="src" value="${1}" /><!--[if !IE]>--><object width="320" height="45" type="video/x-ms-wmv" data="${1}"><param name="src" value="${1}" /><param name="controller" value="1" /><param name="showcontrols" value="1" /><param name="showdisplay" value="0" /><param name="showstatusbar" value="0" /><param name="autosize" value="1" /><param name="autostart_step" value="0" /><param name="autostart" value="0" /><param name="visible" value="1" /><param name="animationstart_step" value="0" /><param name="loop" value="0" /></object><!--<![endif]--></object><br />', 0, 0, 0, 1, "sound.gif"),
		'quicktime'		=> array( "quicktime",		70, 1,	"ABBC3_QUICKTIME_TIP",	 "[quicktime{TEXT}]{URL}[/quicktime]", "<a src=\"{URL}\">{TEXT}</a>", "!\[quicktime(\\=| )?(.*?)\](.*?)\[/quicktime\]!ies", '\'[quicktime${1}${2}:$uid]${3}[/quicktime:$uid]\'', '!\[quicktime((=| )?(width=)?([0-9]?[0-9]?[0-9])(,| )(height=)?([0-9]?[0-9]?[0-9]))?:$uid\](.*?)\[/quicktime:$uid\]!s', '<object classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" codebase="http://www.apple.com/qtactivex/qtplugin.cab" width="${4}" height="${7}"><param name="src" value="${8}" /><param name="controller" value="1" /><param name="autoplay" value="0" /><!--[if !IE]>--><object type="video/quicktime" data="${8}" width="${4}" height="${7}"><param name="autoplay" value="0" /><param name="controller" value="1" /></object><!--<![endif]--></object><br />', 0, 0, 0, 1, "quicktime.gif"),
		'ram'			=> array( "ram",			71, 1,	"ABBC3_RAM_TIP",		 "[ram{TEXT}]{URL}[/ram]", "<a src=\"{URL}\">{TEXT}</a>", "!\[ram(\\=| )?(.*?)\](.*?)\[/ram\]!ies", '\'[ram${1}${2}:$uid]${3}[/ram:$uid]\'', '!\[ram((=| )?(width=)?([0-9]?[0-9]?[0-9])(,| )(height=)?([0-9]?[0-9]?[0-9]))?:$uid\](.*?)\[/ram:$uid\]!s', '<object id="rmstream_${8}" classid="clsid:CFCDAA03-8BE4-11cf-B84B-0020AFBBCCFA" width="$(4)" height="${7}"><param name="src" value="${8}" /><param name="autostart_step" value="false" /><param name="autostart" value="false" /><param name="controls" value="ImageWindow" /><param name="console" value="ctrls_${8}" /><param name="prefetch" value="false" /><embed name="rmstream_${8}" type="audio/x-pn-realaudio-plugin" src="${8}" width="${4}" height="${7}" autostart_step="false" autostart="false" controls="ImageWindow" console="ctrls_${8}" prefetch="false"></embed></object><br /><object id="ctrls_${8}" classid="clsid:CFCDAA03-8BE4-11cf-B84B-0020AFBBCCFA" width="${4}" height="36"><param name="controls" value="ControlPanel" /><param name="console" value="ctrls_${8}" /><embed name="ctrls_${8}" type="audio/x-pn-realaudio-plugin" width="${4}" height="36" controls="ControlPanel" console="ctrls_${8}"></embed></object><br />', 0, 0, 0, 1, "ram.gif"),
		'gvideo'		=> array( "gvideo",			72, 1,	"ABBC3_GVIDEO_TIP",		 "[GVideo]{URL}[/GVideo]", "<a src=\"{URL}\">{URL}</a>", "!\[Gvideo\]http://video.google.(.*?)/videoplay\?docid\=(.*?)\[/Gvideo\]!ies", '\'[Gvideo:$uid]http://video.google.${1}/videoplay?docid=${2}[/Gvideo:$uid]\'', '!\[Gvideo:$uid\]http://video.google.(.*?)/videoplay\?docid\=(.*?)\[/Gvideo:$uid\]!s', '<object classid="clsid:D27CDB6E-AE6D-11CF-96B8-444553540000" codebase="http://active.macromedia.com/flash2/cabs/swflash.cab#version=5,0,0,0" width="425" height="350"><param name="movie" value="http://video.google.$1/googleplayer.swf?docid=$2" /><param name="play" value="false" /><param name="loop" value="false" /><param name="quality" value="high" /><param name="allowScriptAccess" value="never" /><param name="allowNetworking" value="internal" /><embed src="http://video.google.$1/googleplayer.swf?docid=$2" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" width="425" height="350" play="false" loop="false" quality="high" allowscriptaccess="never" allownetworking="internal"></embed></object><br />', 0, 0, 0, 1, "googlevid.gif"),
		'youtube'		=> array( "youtube",		73, 1,	"ABBC3_YOUTUBE_TIP",	 "[youtube]{URL}[/youtube]", "<a src=\"{URL}\">{URL}</a>", "!\[youtube\]http://((.*?)?)youtube.com/watch\?v\=([0-9A-Za-z-_]{11})[^[]*\[/youtube\]!ies", '\'[youtube:$uid]http://${1}youtube.com/watch?v=${3}[/youtube:$uid]\'', '!\[youtube:$uid\]http://((.*?)?)youtube.com/watch\?v\=([0-9A-Za-z-_]{11})[^[]*\[/youtube:$uid\]!s', '<object width="425" height="350"><param name="movie" value="http://${2}youtube.com/v/${3}" /><param name="wmode" value="transparent" /><embed src="http://${2}youtube.com/v/${3}" type="application/x-shockwave-flash" wmode="transparent" width="425" height="350"></embed></object><br />', 0, 0, 0, 1, "youtube.gif"),
		'veoh'			=> array( "veoh", 			74, 1,	"ABBC3_VEOH_TIP",		 "[veoh]{URL}[/veoh]", "<a src=\"{URL}\">{URL}</a>", "!\[veoh\]http://(.*?).veoh.com/videos/(.*?)\[/veoh\]!ies", '\'[veoh:$uid]http://${1}.veoh.com/videos/${2}[/veoh:$uid]\'', '!\[veoh:$uid\]http://(.*?).veoh.com/videos/(.*?)\[/veoh:$uid\]!s', '<embed src="http://${1}.veoh.com/videodetails2.swf?permalinkId=${2}&id=anonymous&player=videodetailsembedded&videoAutoPlay=0" allowFullScreen="true" width="540" height="438" bgcolor="#000000" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer"></embed><br />', 0, 0, 0, 1, "veoh.gif"),
		'collegehumor'	=> array( "collegehumor", 	75, 1,	"ABBC3_COLLEGE_TIP",	 "[collegehumor]{URL}[/collegehumor]", "<a src=\"{URL}\">{URL}</a>", "!\[collegehumor\]http://www.collegehumor.com/video:(.*?)\[/collegehumor\]!ies", '\'[collegehumor:$uid]http://www.collegehumor.com/video:${1}[/collegehumor:$uid]\'', '!\[collegehumor:$uid\]http://www.collegehumor.com/video:(.*?)\[/collegehumor:$uid\]!s', '<object type="application/x-shockwave-flash" data="http://www.collegehumor.com/moogaloop/moogaloop.swf?clip_id=${1}&fullscreen=1" width="480" height="360" ><param name="allowfullscreen" value="true" /><param name="movie" quality="best" value="http://www.collegehumor.com/moogaloop/moogaloop.swf?clip_id=${1}&fullscreen=1" /></object><br />', 0, 0, 0, 1, "collegehumor.gif"),
		'dm'			=> array( "dm", 			76, 1,	"ABBC3_DMOTION_TIP",	 "[dm]{URL}[/dm]", "<a src=\"{URL}\">{URL}</a>", "!\[dm\](.*?)\[/dm\]!ies", '\'[dm:$uid]${1}[/dm:$uid]\'', '!\[dm:$uid\]http://www.dailymotion.com/(.*?)/(.*?)\[/dm:$uid\]!s', '<object width="420" height="352"><param name="movie" value="http://www.dailymotion.com/swf/${2}" /><param name="allowFullScreen" value="true" /><param name="allowScriptAccess" value="never" /><embed src="http://www.dailymotion.com/swf/${2}" type="application/x-shockwave-flash" width="420" height="352" allowFullScreen="true" allowScriptAccess="never"></embed></object><br />', 0, 0, 0, 1, "dailymotion.gif"),
		'gamespot'		=> array( "gamespot",		77, 1,	"ABBC3_GAMESPOT_TIP",	 "[gamespot]{URL}[/gamespot]", "<a src=\"{URL}\">{URL}</a>", "!\[gamespot\]http://www.gamespot.com/video/(.*?)/(.*?)\[/gamespot\]!ies", '\'[gamespot:$uid]http://www.gamespot.com/video/${1}/${2}[/gamespot:$uid]\'', '!\[gamespot:$uid\]http://www.gamespot.com/video/(.*?)/(.*?)\[/gamespot:$uid\]!s', '<embed id="mymovie" width="432" height="355" flashvars="paramsURI=http%3A%2F%2Fwww%2Egamespot%2Ecom%2Fpages%2Fvideo%5Fplayer%2Fproteus%5Fxml%2Ephp%3Fadseg%3D%26adgrp%3D%26sid%3D${2}%26pid%3D${1}%26mb%3D%26onid%3D%26nc%3D1202626246593%26embedded%3D1%26showWatermark%3D0%26autoPlay%3D0" allowfullscreen="true" allowscriptaccess="never" quality="high" name="mymovie" src="http://image.com.com/gamespot/images/cne_flash/production/media_player/proteus/gs/proteus_embed.swf" type="application/x-shockwave-flash"/><br />', 0, 0, 0, 1, "gamespot.gif"),
		'gametrailers'	=> array( "gametrailers",	78, 1,	"ABBC3_GAMETRAILERS_TIP","[gametrailers]{URL}[/gametrailers]", "<a src=\"{URL}\">{URL}</a>", "!\[gametrailers\]http://www.gametrailers.com/player/(.*?).html\[/gametrailers\]!ies", '\'[gametrailers:$uid]http://www.gametrailers.com/player/${1}.html[/gametrailers:$uid]\'', '!\[gametrailers:$uid\]http://www.gametrailers.com/player/(.*?).html\[/gametrailers:$uid\]!s', '<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=8,0,0,0" id="gtembed" width="480" height="392"><param name="allowScriptAccess" value="never" /><param name="allowFullScreen" value="true" /><param name="movie" value="http://www.gametrailers.com/remote_wrap.php?mid=${1}" /><param name="quality" value="high" /><embed src="http://www.gametrailers.com/remote_wrap.php?mid=${1}" swLiveConnect="true" name="gtembed" align="middle" allowScriptAccess="never" allowFullScreen="true" quality="high" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" width="480" height="392"></embed></object><br />', 0, 0, 0, 1, "gametrailers.gif"),
		'ignvideo'		=> array( "ignvideo",		79, 1,	"ABBC3_IGNVIDEO_TIP",	 "[ignvideo]{URL}[/ignvideo]", "<a src=\"{URL}\">{URL}</a>", "!\[ignvideo\](.*?)\[/ignvideo\]!ies", '\'[ignvideo:$uid]${1}[/ignvideo:$uid]\'', '!\[ignvideo:$uid\](.*?)\[/ignvideo:$uid\]!s', '<embed src="http://videomedia.ign.com/ev/ev.swf" flashvars="${1}" type="application/x-shockwave-flash" width="433" height="360" ></embed><br />', 0, 0, 0, 1, "ign.gif"),
		'liveleak'		=> array( "liveleak",		80, 1,	"ABBC3_LIVELEAK_TIP",	 "[liveleak]{URL}[/liveleak]", "<a src=\"{URL}\">{URL}</a>", "!\[liveleak\]http://www.liveleak.com/view\?i\=(.*?)\[/liveleak\]!ies", '\'[liveleak:$uid]http://www.liveleak.com/view?i=${1}[/liveleak:$uid]\'', '!\[liveleak:$uid\]http://www.liveleak.com/view\?i\=(.*?)\[/liveleak:$uid\]!s', '<object type="application/x-shockwave-flash" width="450" height="370" wmode="transparent" data="http://www.liveleak.com/player.swf?autostart_step=false&token=${1}"><param name="movie" value="http://www.liveleak.com/player.swf?autostart_step=false&token=${1}" /><param name="wmode" value="transparent" /><param name="quality" value="high" /></object><br />', 0, 0, 0, 1, "liveleak.gif"),
//		'html'			=> array( "html",			81, 1,	"ABBC3_HTML_TIP",		 "[html]{TEXT}[/html]", "<code>{TEXT}</code>", "!\[html\](.*?)\[/html\]!ies", '\'[html:$uid]${1}[/html:$uid]\'', '!\[html:$uid\](.*?)\[/html:$uid\]!ies', 'str_replace(array("\r\n", "\n", "<br />", "<br/>"), "\r", htmlspecialchars_decode(\'$1\') )', 0, 0, 0, 1, "html.gif", "5"),
	);
	return $bbcode_data;
}

/**
 * @ignore 
 * Oracle : http://www.wikilearning.com/curso_gratis/iniciacion_a_oracle-tipos_de_datos_en_oracle/3861-7
 * firebird : http://wiki.clubdelphi.com/wiki/index.php/Integer
 * postgres : http://www.postgresql.org/docs/8.1/interactive/datatype.html#DATATYPE-NUMERIC
 */
function get_abbc3_schema_changes()
{
	$database_update_info = array(
		// Changes to version 1.0.11
		'1011'	=> array(
			// Change the following columns
			'change_columns'	=> array(
				BBCODES_TABLE	=> array(
					'bbcode_id'	=> array('INT:4', 0),//		'bbcode_id'	=> array('VCHAR:8', ''),
				),
			),
			// Add the following columns
			'add_columns'		=> array(
				BBCODES_TABLE	=> array(
					'display_on_pm'	=> array('TINT:1' , 1),
					'display_on_sig'=> array('TINT:1' , 1),
					'abbcode'		=> array('TINT:1' , 0),
					'bbcode_image'	=> array('VCHAR'  , ' '),
					'bbcode_order'	=> array('USINT'  , 0),// 'bbcode_order'	=> array('UINT', 0, "auto_increment"), <- Doesn't work :(
					'bbcode_group'	=> array('VCHAR'  , '0'),
				),
			),
			// Add indexes
			'add_index'			=> array(
				BBCODES_TABLE	=> array(
					'display_order'	=> array('bbcode_order'),
				),
			),
		),
	);
	return $database_update_info;
}
			
function delete_abbc3( )
{
	global $db, $cache, $auth, $db_tools, $user;

	$abbce_configs = array('ABBC3_MOD', 'ABBC3_PATH', 'ABBC3_TAB', 'ABBC3_BG', 'ABBC3_BOXRESIZE', 'ABBC3_BOXRESIZE', 'ABBC3_MAX_IMG_HEIGHT', 'ABBC3_MAX_IMG_WIDTH', 'ABBC3_MAX_THUM_WIDTH', 'ABBC3_JAVASCRIPT', 'ABBC3_RESIZE', 'ABBC3_RESIZE_METHOD', 'ABBC3_VIDEO_height', 'ABBC3_VIDEO_width', 'ABBC3_UPLOAD_MAX_SIZE', 'ABBC3_UPLOAD_EXTENSION');

	// remove ABBC3 from the config 
	$sql = 'DELETE FROM ' . CONFIG_TABLE . '
		WHERE ' . $db->sql_in_set('config_name', $abbce_configs);
	$result = $db->sql_query($sql);

	// remove ABBC3 from the bbcodes
	$sql = 'DELETE FROM ' . BBCODES_TABLE . '
		WHERE abbcode = 1';
	$result = $db->sql_query($sql);

	// remove ABBC3 rows from the bbcodes
	$database_update_info = get_abbc3_schema_changes();
	foreach ($database_update_info as $version => $schema_changes)
	{
		// Add columns?
		if (!empty($schema_changes['add_columns']))
		{
			foreach ($schema_changes['add_columns'] as $table => $columns)
			{
				foreach ($columns as $column_name => $column_data)
				{
					if ( $db_tools->sql_column_exists($table, $column_name) )
					{
						$db_tools->sql_column_remove($table, $column_name);
					}
				}
			}
		}
	}	
	
	// remove ABBC3 from the config 
	$result = remove_abbc3_modules( );
	
	// clear cache
	$cache->purge();
	$cache->destroy('config');
	// Clear permissions
	$auth->acl_clear_prefetch();

	// Grab global variables, re-cache if necessary
	$config = $cache->obtain_config();
	return true;	
}

function add_abbc3_module($array)
{
	global $modules, $user;

	$failed = array();

	$failed = $modules->update_module_data($array, true);

	if (!sizeof($failed))
	{
		return true;
	}
	
	if ($failed == 'PARENT_NO_EXIST')
	{
		return sprintf($user->lang['MISSING_PARENT_MODULE'], $array['parent_id'], $user->lang[$array['module_langname']]);
	}
}

function remove_abbc3_modules( )
{
	global $modules, $db, $cache;

	$failed = array();

	$modules_data = get_abbc3_modules( 0 );
	ksort($modules_data);
	reset($modules_data);

	foreach ( $modules_data as $modules_name => $modules_values )
	{
		$module_langname = $modules_values['module_langname'];

		$sql = 'SELECT module_id, module_class, left_id, right_id
			FROM ' . MODULES_TABLE . "
			WHERE module_langname = '" . $module_langname . "'";
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);

		if ( $row )
		{
			$module_class = $row['module_class'];
			$module_id	  = (int) $row['module_id'];

			// If not move
			$diff = 2;
			$sql = 'DELETE FROM ' . MODULES_TABLE . "
				WHERE module_class = '" . $db->sql_escape($module_class) . "'
					AND module_id = $module_id";
			$db->sql_query($sql);

			$row['right_id'] = (int) $row['right_id'];
			$row['left_id'] = (int) $row['left_id'];

			// Resync tree
			$sql = 'UPDATE ' . MODULES_TABLE . "
				SET right_id = right_id - $diff
				WHERE module_class = '" . $db->sql_escape($module_class) . "'
					AND left_id < {$row['right_id']} AND right_id > {$row['right_id']}";
			$db->sql_query($sql);

			$sql = 'UPDATE ' . MODULES_TABLE . "
				SET left_id = left_id - $diff, right_id = right_id - $diff
				WHERE module_class = '" . $db->sql_escape($module_class) . "'
					AND left_id > {$row['right_id']}";
			$db->sql_query($sql);
		}
	}

	return true;
}

?>
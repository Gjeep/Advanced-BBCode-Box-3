<?php
/**
* @package: phpBB3 :: Advanced BBCode box 3 -> language [de][German]
* @version: $Id: acp_abbcode.php, v 1.0.8 2008/03/31 03:01:00 leviatan21 Exp $
* @copyright: leviatan21 < info@mssti.com > (Gabriel) http://www.mssti.com/phpbb3/
* @license: http://opensource.org/licenses/gpl-license.php GNU Public License 
* @author: leviatan21 - http://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=345763
* @translator: TigerCrow - http://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=445065
**/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}
						
// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
// Reference : http://www.phpbb.com/mods/documentation/phpbb-documentation/language/index.php#lang-use-php

$lang = array_merge($lang, array(
	'ACP_ABBCODES'						=> 'Advanced BBcodes Box 3',
	'ACP_ABBCODES_EXPLAIN'				=> 'Hier kannst Du die verf�gbaren Stile f�r <strong>[ <a href="http://www.mssti.com/phpbb3" target="_blank">ABBC3</a> ]</strong> auf deinem Board Einstellen.<br/>Du darfst existierende Stile ver�ndern, neu laden, deactivieren oder reactivieren. Du kannst auch sehen, wie ein Stil aussehen wird, wenn du die Vorschaufunktion benutzt. <br/><em>Der eigestellte Stil wird durch ein (*) gekennzeichnet. Es ist auch aufgef�hrt wie viele User einen Stil derzeit benutzen.</em>',
	
	'ABBCODES_DISABLE'					=> 'ABBC3',
	'ABBCODES_DISABLE_EXPLAIN'			=> 'Ein/Ausschalten der <strong>Advanced BBodes Box 3</strong> f�r Benutzer, und/oder f�r das benutzen der standard phpbb3 Eingabe Button.',
	'ABBCODES_BG'						=> 'Hintergrundbild',
	'ABBCODES_BG_EXPLAIN'				=> 'Hier wird das Hintergrundbild f�r die Icons eingestellt.<br/>Stelle auf <em>Kein Bild</em> um deinen Stil zu verwenden.',
	'ABBCODES_TAB'						=> 'Anzeige der Icon f�r die tags',
	'ABBCODES_BOXRESIZE'				=> 'Gr��en�nderung posting textarea',
	'ABBCODES_BOXRESIZE_EXPLAIN'		=> 'Anzeige Button f�r die Gr��en�nderung des Eingabefeldes.',
	
	'ABBCODES_GREYBOX'					=> 'Benutze GreyBox &reg;',
	'ABBCODES_GREYBOX_EXPLAIN'			=> 'GreyBox &reg; ist eine Funktion zum Anzeigen von <em>Thumbnails</em> und <em>gr��enver�nderten Bildern</em> in voller Gr��e. Bei NEIN wird ein neues Browserfenster ge�ffnet.',
	'ABBCODES_RESIZE'					=> 'Gr��en�nderung gro�er Bilder',
	'ABBCODES_RESIZE_EXPLAIN'			=> 'Dies repariert einen Fehler des [img] bbcode wenn ein �bergro�es Bild eingef�gt wird dessen Breite gr��er ist als die Breite des Beitrags Inhaltes.',
	'ABBCODES_MAX_IMAGE_SIZE'			=> 'Maximale Bildbreite in pixel',
	'ABBCODES_RESIZE_METHOD'			=> 'Gr��en�nderungs Methode',
	'ABBCODES_RESIZE_METHOD_EXPLAIN'	=> 'W�hle welche Art zum Anzeigen des Bildes verwendet werden soll.',
	//	for translate :							   don't 		Yes			don't		Yes			don't			yes				don't		yes
	'ABBCODES_RESIZE_METHODS'			=> array( 'greybox' => 'Graue Box', 'enlarge' => 'vergr��ert', 'samewindow' => 'im selben Fenster', 'newwindow' => 'neues Fenster'),
	
	'ABBCODES_MAX_IMAGE_SIZE_EXPLAIN'	=> 'Bild wird in der Gr��e ver�ndert wenn die Breite des Bildes den Wert �berschreitet.',
	'ABBCODES_MAX_THUMB_WIDTH'			=> 'Maximale Thumbnail Breite in pixel',
	'ABBCODES_MAX_THUMB_WIDTH_EXPLAIN'	=> 'Ein erzeugtes Thumbnail wird die Breite nicht �berschreiten.',
	
	'ABBCODES_CUSTOM_BBCODES'			=> 'Standard bbcodes',
	'ABBCODES_CUSTOM_BBCODES_EXPLAIN'	=> 'Anzeige der Standard bbcodes icons. Diese Funktion erm�glicht das Vereinigen mit anderen Video bbcodes wie [youtube] und f�gt deine eigenen bbcodes ein (Wenn vorhanden).',
	'ABBCODES_VIDEO_SIZE'				=> 'Video gr��en',
	'ABBCODES_VIDEO_SIZE_EXPLAIN'		=> 'Standard Breite und H�he f�r das gepostete Video.',

));

$lang = array_merge($lang, array(
	'ABBCODES_SETINGS'					=> 'ABBC3 Einstellungen',
	'ABBCODES_SETINGS_EXPLAIN'			=> 'Hier kannst Du die grundlegenden Einstellungen f�r <strong>ABBC3</strong> vornehmen, einschalten oder ausschalten, und Du stellst die Vorgaben f�r den Hintergrund ein.',
	
	'ABBCODES_CONFIG'					=> 'ABBC3 Teil Konfiguration',
	'ABBCODES_CONFIG_EXPLAIN'			=> 'Hier kannst Du die Reihenfolge der tags ver�ndern oder die Icons editieren die beim Beitrag erstellen angezeigt werden,',
	
	'ABBCODES_NAME'						=> 'Tag Name',
	'ABBCODES_TAG'						=> 'Tag Bild icon',
	'ABBCODES_ORDER'					=> 'Tag Reihenfolge',
	'RESET_TO_DEFAULT'					=> 'Zur�cksetzen',
	'ABBCODES_BREAK_MOVER'				=> '<strong>Zeilenumbruch</strong>',
	'ABBCODES_DIVISION_MOVER'			=> '<strong>Teilung</strong>',
	
	'ABBCODES_MOD_DISABLE'				=> '<strong>ABBC3</strong> ist ausgeschaltet bei diesem Stil.<br/>',
	'ABBCODES_STATUS'					=> 'Status',
	'ABBCODES_ACTIVATED'				=> 'Eingeschaltet',
	'ABBCODES_DEACTIVATED'				=> 'Ausgeschaltet',
	
));
?>
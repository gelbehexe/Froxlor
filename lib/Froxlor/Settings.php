<?php
namespace Froxlor;

use Froxlor\Database\Database;

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2010 the Froxlor Team (see authors).
 *
 * For the full copyright and license information, please view the COPYING
 * file that was distributed with this source code. You can also view the
 * COPYING file online at http://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright (c) the authors
 * @author Michael Kaufmann <mkaufmann@nutime.de>
 * @author Froxlor team <team@froxlor.org> (2010-)
 * @license GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @package Classes
 *         
 * @since 0.9.31
 *       
 */

/**
 * Class Settings
 *
 * Interaction with settings from the db
 *
 * @copyright (c) the authors
 * @author Michael Kaufmann <mkaufmann@nutime.de>
 * @author Froxlor team <team@froxlor.org> (2010-)
 * @license GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @package Classes
 *         
 */
class Settings
{

	/**
	 * settings data
	 *
	 * @var array
	 */
	private static $data = null;

	/**
	 * changed and unsaved settings data
	 *
	 * @var array
	 */
	private static $updatedata = null;

	/**
	 * prepared statement for updating the
	 * settings table
	 *
	 * @var \PDOStatement
	 */
	private static $updstmt = null;

	/**
	 * private constructor, reads in all settings
	 */
	private static function init()
	{
		if (empty(self::$data)) {
			self::readSettings();
			self::$updatedata = array();

			// prepare statement
			self::$updstmt = Database::prepare("
				UPDATE `" . TABLE_PANEL_SETTINGS . "` SET `value` = :value
				WHERE `settinggroup` = :group AND `varname` = :varname
			");
		}
	}

	/**
	 * Read in all settings from the database
	 * and set the internal $_data array
	 */
	private static function readSettings()
	{
		$result_stmt = Database::query("
			SELECT `settingid`, `settinggroup`, `varname`, `value`
			FROM `" . TABLE_PANEL_SETTINGS . "`
		");
		self::$data = array();
		while ($row = $result_stmt->fetch(\PDO::FETCH_ASSOC)) {
			self::$data[$row['settinggroup']][$row['varname']] = $row['value'];
		}
		return true;
	}

	/**
	 * update a value in the database
	 *
	 * @param string $group
	 * @param string $varname
	 * @param string $value
	 */
	private static function storeSetting($group = null, $varname = null, $value = null)
	{
		$upd_data = array(
			'group' => $group,
			'varname' => $varname,
			'value' => $value
		);
		Database::pexecute(self::$updstmt, $upd_data);
	}

	/**
	 * return a setting-value by its group and varname
	 *
	 * @param string $setting
	 *        	a group and a varname separated by a dot (group.varname)
	 *        	
	 * @return mixed
	 */
	public static function Get($setting = null)
	{
		self::init();
		$sstr = explode(".", $setting);
		// no separator - do'h
		if (! isset($sstr[1])) {
			return null;
		}
		$result = null;
		if (isset(self::$data[$sstr[0]][$sstr[1]])) {
			$result = self::$data[$sstr[0]][$sstr[1]];
		}
		return $result;
	}

	/**
	 * tests if a setting-value that i s a comma separated list contains an entry
	 *
	 * @param string $setting
	 *        	a group and a varname separated by a dot (group.varname)
	 * @param string $entry
	 *        	the entry that is expected to be in the list
	 *        	
	 * @return boolean true, if the list contains $entry
	 */
	public static function IsInList($setting = null, $entry = null)
	{
		self::init();
		$s = self::Get($setting);
		if ($s == null) {
			return false;
		}
		$slist = explode(",", $s);
		return in_array($entry, $slist);
	}

	/**
	 * update a setting / set a new value
	 *
	 * @param string $setting
	 *        	a group and a varname separated by a dot (group.varname)
	 * @param string $value
	 * @param boolean $instant_save
	 *
	 * @return bool
	 */
	public static function Set($setting = null, $value = null, $instant_save = true)
	{
		self::init();
		// check whether the setting exists
		if (self::Get($setting) !== null) {
			// set new value in array
			$sstr = explode(".", $setting);
			if (! isset($sstr[1])) {
				return false;
			}
			self::$data[$sstr[0]][$sstr[1]] = $value;
			// should we store to db instantly?
			if ($instant_save) {
				self::storeSetting($sstr[0], $sstr[1], $value);
			} else {
				// set temporary data for usage
				if (! isset(self::$data[$sstr[0]]) || ! is_array(self::$data[$sstr[0]])) {
					self::$data[$sstr[0]] = array();
				}
				self::$data[$sstr[0]][$sstr[1]] = $value;
				// set update-data when invoking Flush()
				if (! isset(self::$updatedata[$sstr[0]]) || ! is_array(self::$updatedata[$sstr[0]])) {
					self::$updatedata[$sstr[0]] = array();
				}
				self::$updatedata[$sstr[0]][$sstr[1]] = $value;
			}
			return true;
		}
		return false;
	}

	/**
	 * add a new setting to the database (mainly used in updater)
	 *
	 * @param string $setting
	 *        	a group and a varname separated by a dot (group.varname)
	 * @param string $value
	 *
	 * @return boolean
	 */
	public static function AddNew($setting = null, $value = null)
	{
		self::init();
		// first check if it doesn't exist
		if (self::Get($setting) === null) {
			// validate parameter
			$sstr = explode(".", $setting);
			if (! isset($sstr[1])) {
				return false;
			}
			// prepare statement
			$ins_stmt = Database::prepare("
					INSERT INTO `" . TABLE_PANEL_SETTINGS . "` SET
					`settinggroup` = :group,
					`varname` = :varname,
					`value` = :value
					");
			$ins_data = array(
				'group' => $sstr[0],
				'varname' => $sstr[1],
				'value' => $value
			);
			Database::pexecute($ins_stmt, $ins_data);
			// also set new value to internal array and make it available
			self::$data[$sstr[0]][$sstr[1]] = $value;
			return true;
		}
		return false;
	}

	/**
	 * Store all un-saved changes to the database and
	 * re-read in all settings
	 */
	public static function Flush()
	{
		self::init();
		if (is_array(self::$updatedata) && count(self::$updatedata) > 0) {
			// save all un-saved changes to the settings
			foreach (self::$updatedata as $group => $vargroup) {
				foreach ($vargroup as $varname => $value) {
					self::storeSetting($group, $varname, $value);
				}
			}
			// now empty the array
			self::$updatedata = array();
			// re-read in all settings
			return self::readSettings();
		}
		return false;
	}

	/**
	 * forget all un-saved changes to settings
	 */
	public static function Stash()
	{
		self::init();
		// empty update array
		self::$updatedata = array();
		// re-read in all settings
		return self::readSettings();
	}

	public static function loadSettingsInto(&$settings_data)
	{
		if (is_array($settings_data) && isset($settings_data['groups']) && is_array($settings_data['groups'])) {

			// prepare for use in for-loop
			$row_stmt = Database::prepare("
				SELECT `settinggroup`, `varname`, `value`
				FROM `" . TABLE_PANEL_SETTINGS . "`
				WHERE `settinggroup` = :group AND `varname` = :varname
			");

			foreach ($settings_data['groups'] as $settings_part => $settings_part_details) {

				if (is_array($settings_part_details) && isset($settings_part_details['fields']) && is_array($settings_part_details['fields'])) {

					foreach ($settings_part_details['fields'] as $field_name => $field_details) {

						if (isset($field_details['settinggroup']) && isset($field_details['varname']) && isset($field_details['default'])) {
							// execute prepared statement
							$row = Database::pexecute_first($row_stmt, array(
								'group' => $field_details['settinggroup'],
								'varname' => $field_details['varname']
							));

							if (! empty($row)) {
								$varvalue = $row['value'];
							} else {
								$varvalue = $field_details['default'];
							}
						} else {
							$varvalue = false;
						}

						$settings_data['groups'][$settings_part]['fields'][$field_name]['value'] = $varvalue;
					}
				}
			}
		}
	}
}

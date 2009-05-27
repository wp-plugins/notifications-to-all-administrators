<?php
/*
Plugin Name: Notifications to all Administrators
Plugin URI: http://pioupioum.fr/wordpress/plugins/administrator-comments-notification.html
Version: 1.0
Description: Enable moderation requests and notifications by email to all administrators.
Author: Mehdi Kabab
Author URI: http://pioupioum.fr/
*/
/*
# ***** BEGIN LICENSE BLOCK *****
# Copyright (C) 2009 Mehdi Kabab <http://pioupioum.fr/>
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# ***** END LICENSE BLOCK ***** */

/**
 * Go!
 */
add_action('plugins_loaded', array('NotificationsToAllAdmins', 'bootstrap'));

/**
 * Enable notifications for all administrator.
 *
 * @author Mehdi Kabab <http://pioupioum.fr/>
 * @copyright Copyright (C) 2009 Mehdi Kabab
 * @license http://www.gnu.org/licenses/gpl.html  GNU GPL version 3 or later
 */
class NotificationsToAllAdmins
{
	/**
	 * Tag used for identify the mails to send to all administrators.
	 */
	const TAG = '%%ADMIN%%';

	/**
	 * The header field email used to add email addresses of other administrators.
	 */
	const FIELD = 'Bcc';

	/**
	 * Administators list.
	 *
	 * @var array
	 */
	private static $_admins = array();

	/**
	 * Initializes environment and loads the plugin core.
	 *
	 * @return void
	 */
	public static function bootstrap()
	{
		add_filter('comment_moderation_subject',   array(__CLASS__, 'dispatch'), 500, 2);
		add_filter('comment_notification_subject', array(__CLASS__, 'dispatch'), 500, 2);
		add_filter('wp_mail',                      array(__CLASS__, 'dispatch'), 500, 1);
	}

	/**
	 * Manage Hooks.
	 *
	 * @return string|array An array if this method is called by wp_mail filter, 
	 *                      otherwise a string.
	 */
	public static function dispatch()
	{
		// Only the first argument is useful. With the comment_*_subject filters,
		// the second argument $comment_id is not used here.
		$arg = func_get_arg(0);

		if (0 === count(self::$_admins))
		{
			$users = get_users_of_blog();
			foreach ($users as $user)
			{
				if (false !== strpos($user->meta_value, 's:13:"administrator";b:1;') && !empty($user->user_email))
					self::$_admins[] = $user->user_email;
			}
		}

		// comment_moderation_subject or comment_notification_subject filters
		if (2 === func_num_args())
		{
			$arg = self::_addTag($arg);
		}
		// wp_admin filter
		else if (1 === func_num_args() && is_array($arg) && 5 === count($arg))
		{
			$arg = self::_injectAdmins($arg);
		}

		return $arg;
	}

	/**
	 * Adds the identifier {@link NotificationsToAllAdmins::TAG} in the Subject of the message.
	 *
	 * @param string $subject The original Subject.
	 * @return string The Subject tagged.
	 */
	private static function _addTag($subject)
	{
		return self::TAG . $subject;
	}

	/**
	 * Removed the identifier {@link NotificationsToAllAdmins::TAG}.
	 *
	 * @param string $subject The Subject tagged.
	 * @return string The Subject cleaned.
	 */
	private static function _removeTag($subject)
	{
		$pos = strpos($subject, self::TAG);
		if (0 === $pos)
		{
			$subject = mb_substr($subject, strlen(self::TAG));
		}
		else if (false !== $pos)
		{
			$subject = mb_substr($subject, 0, $pos)
			         . mb_substr($subject, $pos + mb_strlen(self::TAG));
		}

		return $subject;
	}

	/**
	 * Checks that the {@link NotificationsToAllAdmins::TAG} exists in the subject.
	 *
	 * @param string $subject
	 * @return boolean
	 */
	private static function _hasTag($subject)
	{
		return false !== strpos($subject, self::TAG);
	}

	/**
	 * Adds others administrators email in message Headers and cleanup Subject.
	 *
	 * @see wp_mail() for parameters.
	 *
	 * @param array $args
	 * @return array
	 */
	private static function _injectAdmins($args)
	{
		if (!self::_hasTag($args['subject']))
			return $args;

		// Do not send the mail twice.
		$admins = self::$_admins;
		foreach ($admins as $k => $v)
		{
			if (false !== strpos($args['to'], $v))
				unset($admins[$k]);
		}
		if (0 === count($admins))
			return $args;

		$headers = $args['headers'];
		if (empty($headers))
		{
			$headers = self::FIELD . ': ' . implode(',', $admins);
		}
		else if (is_array($headers))
		{
			if (array_key_exists(self::FIELD, $headers))
			{
				$field = explode(',', $headers[self::FIELD]);
				$field = array_unique(array_merge($field, $admins));
				$headers[self::FIELD] = implode(',', $field);
			}
		}
		else
		{
			$tmpHeaders = explode("\n", $args['headers']);
			$field      = array();
			$fieldIndex = null;
			$i          = 0;

			if (!empty($tmpHeaders[0]))
			{
				for ($c = count($tmpHeaders); $c > $i; ++$i)
				{
					$header = $tmpHeaders[$i];
					if (!(false !== stripos($header, self::FIELD) && false !== strpos($header, ':')))
							continue;

					list(, $content) = explode(':', $header, 2);
					$field      = (array) explode(',', $content);
					$field      = array_map('trim', $field);
					$fieldIndex = $i;
					break;
				}
			}
			$fieldIndex = (null === $fieldIndex) ? $i : $fieldIndex;
			$field      = array_unique(array_merge($field, $admins));
			$tmpHeaders[$fieldIndex] = self::FIELD . ': ' . implode(',', $field);
			$headers = implode("\n", $tmpHeaders);
		}

		$args['headers'] = $headers;
		$args['subject'] = self::_removeTag($args['subject']);

		return $args;
	}
}

<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage PushNotifications
* @author comobix.com plugins@comobix.com
*/
class WiziappPush {

	private static $endUser;
	private static $post_id;
	private static $post;
	private static $post_author_id;
	private static $post_categories_ids;
	private static $post_tag_ids;

	public static function create_push_notification($post_id, $post) {
		self::$endUser = new WiziappUserServices();
		self::$post = $post;
		self::$post_id = $post_id;

		if ( empty(WiziappConfig::getInstance()->settings_done) && empty(WiziappConfig::getInstance()->playstore_url) ){
			return;
		}

		$appropriate_types = array_merge(array('page'), WiziappComponentsConfiguration::getInstance()->get_post_types());
		if ( ! in_array($post->post_type, $appropriate_types) ) {
			// The incoming object is not a Post or Page
			return;
		}

		if ( wp_is_post_revision($post_id) || ! is_object($post) ) {
			// The Post is a revision
			return;
		}

		if ( ! isset($post->post_status) || strtolower($post->post_status) !== 'publish' ){
			// This is not the Publish or just Update event from the Post or Page Edit Form or this is drafts or the scheduled publish
			WiziappLog::getInstance()->write('INFO', 'This is not the Publish or just Update event from the Post or Page Edit Form or this is drafts or the scheduled publish', 'WiziappPush.publishPost');
			return;
		}

		// Check, is the Post excluded by WiziApp Exclude plugin
		$post = apply_filters('exclude_wiziapp_push', $post);
		if ( $post == NULL ) {
			return;
		}

		$is_set_not =
		! WiziappSettingMetabox::get_is_send_wiziapp_push( $post_id ) ||
		( $post->post_type === 'post' && ! ( bool ) WiziappConfig::getInstance()->notify_on_new_post ) ||
		( $post->post_type === 'page' && ! ( bool ) WiziappConfig::getInstance()->notify_on_new_page );
		if ( $is_set_not ) {
			WiziappLog::getInstance()->write('INFO', "We are set not to notify on new post or page.", 'WiziappPush.publishPost');
			return;
		}

		// @todo Get this from the saved options
		$tabId = WiziappConfig::getInstance()->main_tab_index;
		$request = NULL;
		$excluded_users = array();
		WiziappLog::getInstance()->write('INFO', "Notifying on new post", 'WiziappPush.publishPost');

		// We are not aggragating the message
		$allUdids = self::$endUser->getAllUdids();

		//get post data:
		self::$post_author_id = $post->post_author;
		self::$post_categories_ids = wp_get_post_categories($post_id);
		self::$post_tag_ids = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );

		foreach ($allUdids as $udid) {
			$userPushSettings = self::getPushSettings4udid($udid);

			if ($userPushSettings === false) {
				$excluded_users[] = $udid;
			}
		}

		$sound = WiziappConfig::getInstance()->trigger_sound;
		$badge = WiziappConfig::getInstance()->show_badge_number;
		$request = array(
			'type' => 1,
			'sound' => $sound,
			'badge' => $badge,
			'excluded_users' => $excluded_users,
		);

		if ( WiziappConfig::getInstance()->show_notification_text ) {
			$request['content'] = urlencode( stripslashes( WiziappSettingMetabox::get_push_message( $post_id ) ) );
			$request['params'] = "{\"tab\": \"{$tabId}\"}";
		}

		// Make sure we have a reason to even send this message
		if ( $request == NULL || ( ! $request['sound'] && ! $request['badge'] && ! $request['content'] ) ) {
			return;
		}

		// Done setting up what to send, now send it.
		$r = new WiziappHTTPRequest();
		$r->api($request, '/push', 'POST');
	}

	/**
	* load user push settings
	*
	* 3 possible return values:
	* true: send notification
	* false: do not send
	*   (if there is some user definition, but didn't match the post's data)
	* 0: no data - continue according to owner's settings
	*
	* @param int $post_id
	* @param object $post
	* @return boolean
	*/
	public static function getPushSettings4udid($udid) {
		$post = self::$post;
		$post_id = self::$post_id;

		$userPushSettings = self::$endUser->getUserPushSettings($udid);
		WiziappLog::getInstance()->write('INFO', "userPushSettings: " . print_r($userPushSettings, true), 'WiziappPush.getPushSettings4udid');

		if (empty($userPushSettings['authors'])) {
			unset($userPushSettings['authors']);
		}
		if (empty($userPushSettings['categories'])) {
			unset($userPushSettings['categories']);
		}
		if (empty($userPushSettings['tags'])) {
			unset($userPushSettings['tags']);
		}

		WiziappLog::getInstance()->write('INFO', "after empty check - userPushSettings: " . print_r($userPushSettings, true), 'WiziappPush.getPushSettings4udid');

		if (empty($userPushSettings)) {
			$hasUserPushSettings = 0;
			WiziappLog::getInstance()->write('INFO', "userPushSettings empty", 'WiziappPush.getPushSettings4udid');
		} elseif ( !isset($userPushSettings['authors']) && !isset($userPushSettings['categories']) && !isset($userPushSettings['tags']) ) {
			$hasUserPushSettings = 0;
			WiziappLog::getInstance()->write('INFO', "userPushSettings empty 0", 'WiziappPush.getPushSettings4udid');
		} else {
			//compare with user's settings
			if (isset($userPushSettings['authors']) && is_array($userPushSettings['authors']) &&
				in_array(self::$post_author_id, $userPushSettings['authors'])) {
				$hasUserPushSettings = true;
			} elseif (isset($userPushSettings['categories']) && is_array($userPushSettings['categories']) &&
				self::isArrayPartInArray(self::$post_categories_ids, $userPushSettings['categories'])) {
				$hasUserPushSettings = true;
			} elseif (isset($userPushSettings['tags']) && is_array($userPushSettings['tags']) &&
				self::isArrayPartInArray(self::$post_tag_ids, $userPushSettings['tags'])) {
				$hasUserPushSettings = true;
			} else {
				$hasUserPushSettings = false;
				WiziappLog::getInstance()->write('INFO', "userPushSettings false", 'WiziappPush.getPushSettings4udid');
			}
		}
		WiziappLog::getInstance()->write('INFO', "hasUserPushSettings: $hasUserPushSettings", 'WiziappPush.getPushSettings4udid');

		return $hasUserPushSettings;
	}

	public static function isArrayPartInArray($needleArray = array(), $haystack = array(), $strict = false) {
		if ( ! is_array($needleArray) || ! is_array($haystack) ) {
			return false;
		}

		foreach ($needleArray as $needle) {
			if (in_array($needle, $haystack, $strict)) {
				return true;
			}
		}

		return false;
	}
}
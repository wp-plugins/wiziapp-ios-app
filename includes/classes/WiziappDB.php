<?php if (!defined('WP_WIZIAPP_BASE')) exit();

/**
* Main database class for all the plugins needs
*
* The database class is more of a helper when it comes to preforming queries on the data
* we save inside the CMS database. It doesn't handle the CMS queries, but it handles all
* of our custom tables queries.
*
* @package WiziappWordpressPlugin
* @subpackage Database
* @author comobix.com plugins@comobix.com
*/
class WiziappDB implements WiziappIInstallable{

	private $media_table = 'wiziapp_content_media';
	private $user_table = 'wiziapp_user_info';
	private $internal_version = '0.30';
	private static $_instance = null;

	/**
	* The possible types of the content
	*
	* @var mixed
	*/
	public $types = array('post' => 1, 'page' => 2, 'comment' => 3);
	/**
	* The possible types of the media we save
	*
	* @var mixed
	*/
	public $media_types = array('image' => 1, 'video' => 2, 'audio' => 3);

	private $_added_columns = array(
		'exclude' => array(
			array( 'name' => 'wizi_included_site',  'default' => 1, 'comment' => 'Is Post included to Site', ),
			array( 'name' => 'wizi_included_app',   'default' => 1, 'comment' => 'Is Post included to WiziApp', ),
		),
		'push'	  => array(
			array( 'name' => 'wizi_published_push', 'default' => 1, 'comment' => 'Is Push send on Publish', ),
			array( 'name' => 'wizi_updated_push',   'default' => 0, 'comment' => 'Is Push send on Update', ),
		),
	);

	/**
	* @static
	* @return WiziappDB
	*/
	public static function getInstance() {
		if( is_null(self::$_instance) ) {
			self::$_instance = new WiziappDB();
		}

		return self::$_instance;
	}

	private function  __clone() {
		// Prevent cloning
	}

	private function __construct() {
		global $wpdb;
	}

	/**
	* A simple wrapper to the find_content_media method,
	* its here to make the retrieval a bit easier
	*
	* @see WiziappDB::find_content_media()
	* @param integer $id the id of the page
	* @param string $media_type the media type to retrieve
	*
	* @return mixed $result an array containing the results of the search or false if none
	*/
	function find_page_media($id, $media_type) {
		return $this->find_content_media($id, $this->types['page'], $this->media_types[$media_type]);
	}

	/**
	* A simple wrapper to the find_content_media method,
	* its here to make the retrieval a bit easier
	*
	* @see WiziappDB::find_content_media()
	* @param integer $id the id of the post
	* @param string $media_type the media type to retrieve
	*
	* @return mixed $result an array containing the results of the search or false if none
	*/
	function find_post_media($id, $media_type) {
		return $this->find_content_media($id, $this->types['post'], $this->media_types[$media_type]);
	}

	/**
	* A simple wrapper to the update_content_media method,
	* its here to make the updating a bit easier
	*
	* @param integer $post_id the id of the post the media was found in
	* @param string $media_type the media type
	* @param array $data the data gathered on the media
	* @param string $html the html representing the original html element
	* @return mixed $result the id of the media if saved, false if there was a problem
	*/
	function update_post_media($post_id, $media_type, $data, $html) {
		return $this->update_content_media($post_id, $this->types["post"],
			$this->media_types[$media_type], $data, $html);
	}

	/**
	* A simple wrapper to the update_content_media method,
	* its here to make the updating a bit easier
	*
	* @param integer $page_id the id of the page the media was found in
	* @param string $media_type the media type
	* @param array $data the data gathered on the media
	* @param string $html the html representing the original html element
	* @return mixed $result the id of the media if saved, false if there was a problem
	*/
	function update_page_media($page_id, $media_type, $data, $html) {
		return $this->update_content_media($page_id, $this->types["page"],
			$this->media_types[$media_type], $data, $html);
	}

	/**
	* look for media from a certain type related to a certain post or page
	*
	* @param integer $id the id of the content the media was found in
	* @param string $type the the type of the content
	* @param string $media_type the type of the media
	*
	* @return mixed $result an array containing the results of the search or false if none
	*/
	function find_content_media($id, $type, $media_type) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.content_id = %d AND c.content_type = %d AND c.attachment_type = %d", $id, $type, $media_type);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.find_content_media');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			return $result;
		}

		return false;
	}

	/**
	* look for images related to a certain post or page and belongs to a gallery
	*
	* @param integer $id the id of the content the media was found in
	*
	* @return mixed $result an array containing the results of the search or false if none
	*/
	function find_content_gallery_images($id) {
		global $wpdb;

		$query = "SELECT * FROM ".$wpdb->prefix.$this->media_table." AS c WHERE c.content_id = %d AND c.attachment_type = %d";
		$sql = $wpdb->prepare($query, $id, 1);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.find_content_gallery_images');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			return $result;
		}

		return false;
	}

	function find_media($id, $media_type) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.content_id = %d AND c.attachment_type = %d", $id,  $this->media_types[$media_type]);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.find_content_media');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			return $result;
		}

		return false;
	}

	function get_media_data($media_type = 'image', $key, $value) {
		$media_type_id = $this->media_types[$media_type];

		global $wpdb;

		$where = "WHERE c.attachment_type = %d and c.attachment_info like %s";
		$equalKey = '%"' . $key . '":"' . $value . '"%';

		$unsafeSQL = "SELECT c.id, c.attachment_info, c.content_id FROM {$wpdb->prefix}{$this->media_table} AS c {$where}";

		$sql = $wpdb->prepare($unsafeSQL, $media_type_id, $equalKey);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_media_data');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			$metadata = array();
			foreach($result as $media) {
				$info = json_decode($media['attachment_info'], TRUE);
				$metadata[$media['id']] = array();
				$keys = array_keys($info['metadata']);
				for($k = 0, $total = count($keys); $k < $total; ++$k) {
					if (isset($info['metadata'][$keys[$k]])) {
						$metadata[$media['id']][$keys[$k]] = $info['metadata'][$keys[$k]];
					}
				}
			}
			$metadata[$media['id']]['content_id'] = $media['content_id'];
			return $metadata;
		}

		return FALSE;
	}

	function get_images_for_albums() {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.attachment_type = %d AND (attachment_info LIKE %s OR attachment_info LIKE %s OR attachment_info LIKE %s OR attachment_info LIKE %s OR attachment_info LIKE %s OR attachment_info LIKE %s OR attachment_info LIKE %s) LIMIT 0, 2000", $this->media_types["image"], '%data-wiziapp-cincopa-id%', '%data-wiziapp-nextgen-album-id%', '%data-wiziapp-nextgen-gallery-id%', '%data-wiziapp-pageflipbook-id%', '%wordpress-gallery-id%', '%external-gallery-id%', '%data-wiziapp-id%');
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_images_for_albums');

		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			// If we have some results organize them in an easy to handle way
			$data = array();
			foreach($result as $media) {
				$info = json_decode($media['attachment_info']);
				$data[$media['content_id']][] = array(
					'media_id'=>$media['id'],
					'original_code'=>$media['original_code'],
					'info'=>$info
				);
			}
			return $data;
		}
		return FALSE;
	}

	function get_media_metadata_equal($media_type = 'image', $key, $value) {
		$media_type_id = $this->media_types[$media_type];

		global $wpdb;

		$where = "WHERE c.attachment_type = %d and c.attachment_info like %s and c.attachment_info like %s";
		$equalKey = '%"' . $key . '":"' . $value . '"%';

		$unsafeSQL = "SELECT c.id, c.attachment_info, c.content_id FROM {$wpdb->prefix}{$this->media_table} AS c {$where}";

		$sql = $wpdb->prepare($unsafeSQL, $media_type_id, '%metadata%', $equalKey);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_media_metadata_equal');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			$metadata = array();
			foreach($result as $media) {
				$info = json_decode($media['attachment_info'], TRUE);
				$metadata[$media['id']] = array();
				$keys = array_keys($info['metadata']);
				for($k = 0, $total = count($keys); $k < $total; ++$k) {
					if (isset($info['metadata'][$keys[$k]])) {
						$metadata[$media['id']][$keys[$k]] = $info['metadata'][$keys[$k]];
					}
				}
			}
			$metadata[$media['id']]['content_id'] = $media['content_id'];
			return $metadata;
		}

		return FALSE;

	}

	function get_media_metadata_not_equal($media_type='image', $keys=array()) {
		$media_type_id = $this->media_types[$media_type];

		global $wpdb;

		$where = "WHERE c.attachment_type = %d ";
		$equalKey= array($media_type_id);
		if(is_array($keys)) {
			foreach($keys as $key=>$value) {
				$where .= ' AND c.attachment_info NOT LIKE %s ';
				$equalKey[] = '%"' . $key . '":' . ($value?'"' . $value . '"':'') . '%';
			}
		}
		$unsafeSQL = "SELECT c.id, c.original_code, c.attachment_info, c.content_id FROM {$wpdb->prefix}{$this->media_table} AS c {$where}";

		$sql = $wpdb->prepare($unsafeSQL, $equalKey);

		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_media_metadata_not_equal');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			$data = array();
			foreach($result as $media) {
				$info = json_decode($media['attachment_info']);
				$data[$media['content_id']][] = array('media_id'=>$media['id'], 'original_code'=>$media['original_code'], 'info'=>$info);
			}
			return $data;
		}
		return FALSE;
	}

	/**
	* Get the media metadata for external plugins.
	* Plugins can save the data as a special data-wiziapp-param attribute
	* on the media html and this function will extract the metadata they are requesting
	* to use you must request for the same param you saved as an array and the function will return
	* an array or [media_id] => metadata...
	*
	* @param string $media_type can be image/video/audio
	* @param array $keys a list of keys to extract from the metadata
	* @param string $operand the query operand
	* @return array $metadata the metadata array is build from an associative array of media_id->metadata for the media
	*/
	function get_media_metadata($media_type = 'image', $keys = array(), $operand = 'and') {
		$media_type_id = $this->media_types[$media_type];

		global $wpdb;

		$where = "WHERE c.attachment_type = %d and c.attachment_info like %s";

		$metaParams = array();
		if (!empty($keys)) {
			for($k = 0, $total = count($keys); $k < $total; ++$k) {
				$where .= " " . $operand . " c.attachment_info like %s";
				$metaParams[] = '%' . $keys[$k] . '%';
			}
		}
		$unsafeSQL = "SELECT c.id, c.attachment_info, c.content_id FROM {$wpdb->prefix}{$this->media_table} AS c {$where}";

		$params = array_merge(array($unsafeSQL, $media_type_id, '%metadata%'), $metaParams);

		// Run $wpdb->prepare to make the query safe
		$sql = call_user_func_array(array($wpdb, 'prepare'), $params);

		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.find_content_media');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			$metadata = array();
			foreach($result as $media) {
				$info = json_decode($media['attachment_info'], TRUE);
				$metadata[$media['id']] = array();
				for($k = 0, $total = count($keys); $k < $total; ++$k) {
					if (isset($info['metadata'][$keys[$k]])) {
						$metadata[$media['id']][$keys[$k]] = $info['metadata'][$keys[$k]];
					}
				}
			}
			return $metadata;
		}
		return FALSE;
	}

	/**
	* Updates the content media in the database. Since the html might change we have no way to
	* validate if the record exists or not, therefore the records for the content must be deleted
	* before being sent to this method. this method only adds to the database.
	*
	* @todo Add the ability to update multiple records on the same time
	*
	* @param integer $content_id the id of the content the media was found in
	* @param string $type the type of the content
	* @param string $media_type the media type
	* @param array $data the data we collected on the media
	* @param string $html the original html code that resulted in this media
	*
	* @return mixed $result the id of the media if saved, false if there was a problem
	*/
	function update_content_media($content_id, $type, $media_type, $data, $html) {
		//$media = $this->find_content_media($content_id, $type, $media_type);
		$result = FALSE;
		//if ( $media ) {
		// Just update
		//  $id = $media->id;
		//$result = $this->do_update_content_media($id, $data);
		//} else {
		// Create
		$result = $this->add_content_media($content_id, $type, $media_type, $data, $html);
		//}
		return $result;
	}

	function add_content_medias($media_type, $items, $content_id, $content_type) {
		global $wpdb;

		if ( in_array( $content_type, WiziappComponentsConfiguration::getInstance()->get_post_types() ) ) {
			$content_type = 'post';
		}

		$sql = "INSERT INTO {$wpdb->prefix}{$this->media_table} (content_id, content_type, original_code, attachment_info, attachment_type, created_at, updated_at) VALUES ";

		// $sql .= '(%d, %d, %s, %s, %d, %s, %s),';
		$sql = substr_replace($sql, "", -1);

		$params = array();
		for($a = 0, $total = count($items); $a < $total; ++$a) {
			$obj = $items[$a]['obj'];
			$html = $items[$a]['html'];
			$sql .= '(%d, %d, %s, %s, %d, %s, %s),';
			$params[] = $content_id;
			$params[] = $this->types[$content_type];
			$params[] = $html;
			$params[] = json_encode($obj);
			$params[] = $this->media_types[$media_type];
			$params[] = date('Y-m-d H:i:s');
			$params[] = date('Y-m-d H:i:s');
		}
		$sql = substr_replace($sql, "", -1);

		array_unshift($params, $sql);
		$query = call_user_func_array(array($wpdb, 'prepare'), $params);

		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$query}", 'WiziappDB.add_content_medias');

		$added = $wpdb->query($query);

		if ($added === FALSE) {
			return false;
		}

		$id = (int) $wpdb->insert_id;
		return $id;
	}

	/**
	* Preforms the media saving in the database. Adds a new record to the media
	* table according to the received information
	*
	* @param integer $content_id the id of the content the media was found in
	* @param string $type the type of the content
	* @param string $media_type the media type
	* @param array $media_info the data we collected on the media
	* @param string $html the original html code that resulted in this media
	* @return integer $id the added media id
	*/
	function add_content_media($content_id, $type, $media_type, $media_info, $html) {
		global $wpdb;
		//$wpdb->show_errors();

		$sql = $wpdb->prepare(  "INSERT INTO {$wpdb->prefix}{$this->media_table} (content_id, content_type, original_code, attachment_info, attachment_type, created_at, updated_at)
			VALUES (%d, %d, %s, %s, %d, %s, %s)",
			$content_id, $type, $html, json_encode($media_info), $media_type, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'));

		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.add_content_media');
		$added = $wpdb->query($sql);

		if ($added === FALSE) {
			return false;
		}

		$id = (int) $wpdb->insert_id;
		return $id;
	}

	/**
	* deletes the media related to the specified content
	*
	* @param integer $content_id the content id
	* @param string $content_type the content type
	*/
	function delete_content_media($content_id, $content_type='post') {
		global $wpdb;

		$sql = $wpdb->prepare("DELETE FROM {$wpdb->prefix}{$this->media_table} WHERE content_id = %d AND content_type = %d", $content_id, $this->types[$content_type]);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.delete_content_media');

		$wpdb->query($sql);
	}

	/**
	* gets all the videos in the blog regardless of their related content
	*
	* @param integer $offset the query offset
	* @param integer $limit the query limit
	* @return mixed $result an array containing the results of the search or false if none
	*/
	function get_all_videos($offset = 0, $limit = 0) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.attachment_type = %d ORDER BY `id` DESC LIMIT %d, %d", $this->media_types["video"], $offset, $limit);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_all_videos');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			// Build the object from the query result
			return $result;
		}

		return FALSE;
	}

	/**
	* gets all the images in the blog regardless of their related content
	*
	* @return mixed $result an array containing the results of the search or false if none
	*/
	function get_all_images() {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.attachment_type = %d", $this->media_types["image"]);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_all_images');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			// Build the object from the query result
			return $result;
		}

		return FALSE;
	}

	/**
	* Gets the total scanned videos count (Limited to 15)
	*
	* @return int the total videos found while scanning the blog
	*/
	function get_videos_count() {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.attachment_type = %d LIMIT 0, 15",
			$this->media_types["video"]);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_videos_count');

		return (int) $wpdb->get_var($sql);
	}

	/**
	* Gets the total scanned albums (wordpress, cincopa, nextgen, pageflip, external) count (Limited to 15)
	*
	* @return int the total albums found while scanning the blog
	*/
	function get_albums_count() {
		global $wpdb;

		$sql =
		"SELECT COUNT(id)
		FROM {$wpdb->prefix}{$this->media_table} AS c
		WHERE attachment_info LIKE '%data-wiziapp-cincopa-id%'
		OR attachment_info LIKE '%data-wiziapp-nextgen-album-id%'
		OR attachment_info LIKE '%data-wiziapp-nextgen-gallery-id%'
		OR attachment_info LIKE '%data-wiziapp-pageflipbook-id%'
		OR attachment_info LIKE '%wordpress-gallery-id%'
		OR attachment_info LIKE '%external-gallery-id%'
		OR attachment_info LIKE '%data-wiziapp-id%'
		LIMIT 0, 15";
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_albums_count');

		return (int) $wpdb->get_var($sql);
	}

	/**
	* Get the number of posts that has more then $image_in_albums images inside of them
	* used as part of the CMS profile. (Limited to 15)
	*
	* @param int $image_in_album
	* @return int the number of posts
	*/
	function get_images_post_albums_count($image_in_album) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT COUNT(content_id) from
			(SELECT COUNT(id) AS total, content_id
			FROM {$wpdb->prefix}{$this->media_table} w
			WHERE attachment_type = %d group by content_id) as totals
			WHERE total > %d
			LIMIT 0, 15", $this->media_types["image"], $image_in_album);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_images_post_albums_count');

		return (int) $wpdb->get_var($sql);
	}

	/**
	* Get the number of posts that has more then $audio_in_album audios inside of them
	* used as part of the CMS profile. (Limited to 15)
	*
	* @param int $audio_in_album
	* @return int the number of posts
	*/
	function get_audios_post_albums_count($audio_in_album) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT COUNT(content_id) from
			(SELECT COUNT(id) AS total, content_id
			FROM {$wpdb->prefix}{$this->media_table} w
			WHERE attachment_type = %d group by content_id) as totals
			WHERE total > %d
			LIMIT 0, 15", $this->media_types["audio"], $audio_in_album);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_audios_post_albums_count');

		return (int) $wpdb->get_var($sql);
	}

	/**
	* gets all the audio in the blog regardless of their related content
	*
	* @param integer $offset the query offset
	* @param integer $limit the query limit
	* @return mixed $result an array containing the results of the search or false if none
	*/
	function get_all_audios($offset = 0, $limit = 0) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.attachment_type = %d LIMIT %d, %d", $this->media_types["audio"], $offset, $limit);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_all_audios');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			// Build the object from the query result
			return $result;
		}

		return FALSE;
	}

	function get_post_audios($post_id) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.attachment_type = %d AND content_id = %d", $this->media_types["audio"], $post_id);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_post_audios');
		$result = $wpdb->get_results($sql, ARRAY_A);

		return ( empty($result) ) ? array() : $result;
	}

	/**
	* get a specific video by it's id
	*
	* @param integer $id the video id
	* @return mixed $result an array containing the record of the search as an associative array or false if none
	*/
	function get_videos_by_id($id) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.attachment_type = %d AND id = %d", $this->media_types["video"], $id);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_videos_by_id');
		$result = $wpdb->get_row($sql, ARRAY_A);

		if ($result) {
			// Build the object from the query result
			return $result;
		}

		return FALSE;
	}

	function get_post_videos($post_id) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.attachment_type = %d AND content_id = %d", $this->media_types["video"], $post_id);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_videos_by_id');
		$result = $wpdb->get_results($sql, ARRAY_A);

		return ( empty($result) ) ? array() : $result;
	}

	/**
	* gets all the videos and audio found in the specified content id
	*
	* @param integer $content_id the content id
	* @return mixed $result an array (fields: id, original_code, attachment_info) containing the results of the search or false if none
	* @todo add paging support to the get_content_special_elements() method  (offset & limit)
	*/
	function get_content_special_elements($content_id) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT id, original_code, attachment_info, attachment_type FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.attachment_type in (%d, %d) AND content_id = %d", $this->media_types['audio'], $this->media_types['video'], $content_id);

		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_content_special_elements');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			return $result;
		}

		return FALSE;
	}

	/**
	* Gets all the images found in the specified content id
	*
	* @param integer $content_id the content id
	* @return mixed $result an array (fields: id, original_code, attachment_info) containing the results of the search or false if none
	* @todo add paging support to the get_content_images() method  (offset & limit)
	*/
	function get_content_images($content_id) {
		global $wpdb;

		$sql = $wpdb->prepare("SELECT id, original_code, attachment_type, attachment_info FROM {$wpdb->prefix}{$this->media_table} AS c
			WHERE c.attachment_type = %d AND content_id = %d", $this->media_types['image'], $content_id);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_content_images');
		$result = $wpdb->get_results($sql, ARRAY_A);

		if ($result) {
			return $result;
		}

		return FALSE;
	}

	function get_content_by_media_id($id) {
		global $wpdb;
		$sql = $wpdb->prepare("SELECT `content_id` FROM {$wpdb->prefix}{$this->media_table} AS c WHERE c.id=%d", $id);
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_content_by_media_id');
		$result = $wpdb->get_var($sql);
		return $result;
	}

	// @todo replace this query with a "native" wordpress one
	function get_links_count() {
		global $wpdb;
		$tableName = $wpdb->links;
		$sql = $wpdb->prepare("SELECT COUNT(link_id) FROM {$tableName}");
		WiziappLog::getInstance()->write('DEBUG', "About to run the sql: {$sql}", 'WiziappDB.get_links_count');
		$result = $wpdb->get_var($sql);
		return $result;
	}

	public function isInstalled() {
		global $wpdb;

		return
		$wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}{$this->media_table}'" ) == $wpdb->prefix.$this->media_table;
	}

	/**
	* IMPORTANT!!!
	* If you change the SQL in this method, or add new ones, make sure to update $this->internal_version.
	* This method will automatically run only once and when the internal_version is changed.
	*
	* @return bool
	*/
	public function install() {
		global $wpdb;

		// Use wordpress dbDelta functionality
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		// Handle charset
		$charset_collate = '';
		if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) {
			if ( ! empty($wpdb->charset) )
				$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
			if ( ! empty($wpdb->collate) )
				$charset_collate .= " COLLATE {$wpdb->collate}";
		}

		// Before changing the sql here read the function doc block.
		// dbDelta adds fields nicely but does not seem to remove them.
		$sql =
		"CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.$this->media_table." (
		`id` BIGINT(20) NOT NULL AUTO_INCREMENT ,
		`content_id` BIGINT(20) NOT NULL ,
		`content_type` INT(4) NOT NULL ,
		`original_code` MEDIUMTEXT NOT NULL ,
		`attachment_info` MEDIUMTEXT NOT NULL,
		`attachment_type` INT(4) NOT NULL,
		`created_at` DATETIME NULL ,
		`updated_at` DATETIME NULL ,
		PRIMARY KEY id (`id`),
		KEY (`content_id`)
		) ".$charset_collate." ENGINE=INNODB;";

		$create_table_errors = '';
		ob_start();
		dbDelta($sql);
		$create_table_errors = ob_get_clean();
		if ( $create_table_errors != '' ) {
			WiziappLog::getInstance()->write('ERROR', $create_table_errors, 'WiziappDB.install');
		}

		$userSql =
		"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}{$this->user_table} (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`udid` varchar(72) NOT NULL,
		`appId` int(8) NOT NULL DEFAULT 0,
		`wp_user_id` bigint(20) unsigned,
		`username` varchar(50),
		`password` varchar(64),
		`authors` text,
		`categories` text,
		`tags` text,
		`created_at` DATETIME NULL ,
		`updated_at` DATETIME NULL ,
		PRIMARY KEY (`id`),
		UNIQUE KEY `udid` (`udid`),
		KEY `appId` (`appId`,`username`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

		$create_table_errors = '';
		ob_start();
		dbDelta($userSql);
		$create_table_errors = ob_get_clean();
		if ( $create_table_errors != '' ) {
			WiziappLog::getInstance()->write('ERROR', $create_table_errors, 'WiziappDB.install');
		}

		// Save the database version for easy upgrades
		update_option("wiziapp_db_version", $this->internal_version);
	}

	public function uninstall() {
		global $wpdb;

		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$this->media_table}");
		$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$this->user_table}");

		if ( ! ( defined('EICONTENT_EXCLUDE_BASENAME') && is_plugin_active( EICONTENT_EXCLUDE_BASENAME ) ) ) {
			// To remove the "Exclude fields" from the Posts table, if another plugin, "Exclude", is not activated
			$this->_erase_post_alterations( $this->_added_columns['exclude'] );
		}
		// To remove the "Push fields" from the Posts table
		$this->_erase_post_alterations( $this->_added_columns['push'] );

		delete_option('wiziapp_db_version');

		// remove the flags on the posts metadata and on the users
		$wpdb->query("delete from " . $wpdb->postmeta . " where meta_key = 'wiziapp_processed'");

		$wpdb->query("DELETE FROM " . $wpdb->postmeta . " WHERE meta_key = 'wiziapp_metabox_setting'");
	}

	public function needUpgrade() {
		$installedVer = get_option("wiziapp_db_version");
		return ( $installedVer != $this->internal_version );
	}

	/**
	* If there are any special upgrade between the versions this is the place to call them,
	* we can do this by adding methods like upgradeFrom0_1 and checking if the method exists
	*
	* For now a simple diff that is handled by the install anyway is fine.
	*/
	public function upgrade() {
		global $wpdb;

		// Delete the tables which was removed on version 2.0.0
		if ( ! ( defined('EICONTENT_EXCLUDE_BASENAME') && is_plugin_active( EICONTENT_EXCLUDE_BASENAME ) ) ) {
			// To remove the "Exclude fields" from the Posts table, if another plugin, "Exclude", is not activated
			$this->_erase_post_alterations( $this->_added_columns['exclude'] );
		}

		// To remove the "Push fields" from the Posts table
		$this->_erase_post_alterations( $this->_added_columns['push'] );

		return $this->install();
	}

	public function get_media_table() {
		return $this->media_table;
	}

	private function  _erase_post_alterations($colums_to_remove) {
		global $wpdb;

		try {
			$columns_names = $this->_get_posts_columns();

			foreach ( $colums_to_remove as $column ) {
				if ( in_array( $column['name'], $columns_names ) ) {
					// If the Exclude column exist in the Posts table
					$sql = "ALTER TABLE `" . $wpdb->posts . "` DROP COLUMN `" . $column['name'] . "`;";
					if ( ! $wpdb->query( $sql ) ) {
						throw new Exception('SQL: '.$sql.' '.$wpdb->last_error);
					}
				}
			}
		} catch (Exception $e) {
			WiziappLog::getInstance()->write('ERROR', $e->getMessage(), 'WiziappDB._erase_post_alterations');
		}
	}

	/**
	* Important!
	* The function throw Exception.
	* Call it into try-catche block only
	*/
	private function  _get_posts_columns() {
		global $wpdb;

		$columns_names = $wpdb->get_col( "SHOW COLUMNS FROM `" . $wpdb->posts . "`" );
		if ( empty($columns_names) ) {
			throw new Exception('SQL: '.$sql.' '.$wpdb->last_error);
		}

		return $columns_names;
	}

}
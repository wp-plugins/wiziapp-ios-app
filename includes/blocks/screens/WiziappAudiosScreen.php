<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappAudiosScreen extends WiziappBaseScreen{
	protected $name = 'audio';
	protected $type = 'list';

	public function run(){
		$screen_conf = $this->getConfig();
		$title = $this->getTitle();

		$page = array();
		$pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;
		$audioLimit = WiziappConfig::getInstance()->audios_list_limit;
		$limitForRequest = $audioLimit * 2;
		$offset = $audioLimit * $pageNumber;

		$audios = WiziappDB::getInstance()->get_all_audios($offset, $limitForRequest);
		$audios = apply_filters('wiziapp_audio_request', $audios);

		if ($audios !== FALSE){
			WiziappLog::getInstance()->write('DEBUG', "The audios are: " . print_r($audios, TRUE), "screens.wiziapp_buildAudioPage");

			$sortedAudio = array();
			$allAudio = array();

			for($a = 0, $aTotal = count($audios); $a < $aTotal; ++$a){
				$audio = array_merge(
					array(
						'id' => $audios[$a]['id'],
					),
					json_decode($audios[$a]['attachment_info'], TRUE)
				);
				$post = get_post($audios[$a]['content_id']);
				$sortedAudio[$audio['id']] = strtotime($post->post_date);
				$allAudio[$audio['id']] = $audio;
			}

			arsort($sortedAudio);
			/**
			* Handle paging
			*/
			foreach($sortedAudio as $audioId => $audioDate){
				$audio = $allAudio[$audioId];
				$this->appendComponentByLayout($page, $screen_conf['items'], $audio);
			}

			$audioCount = count($sortedAudio);
			$pager = new WiziappPagination($audioCount, $audioLimit);
			$pager->setOffset(0);
			$page = $pager->extractCurrentPage($page, TRUE);
			$pager->addMoreCell(__("Load %s more items", 'wiziapp'), $page);
		}

		$screen = $this->prepare($page, $title, 'list');
		$screen['screen']['default'] = 'list';
		$screen['screen']['sub_type'] = 'audio';
		$this->output($screen);
	}
}
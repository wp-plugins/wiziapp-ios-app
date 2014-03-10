<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappImagesScreen extends WiziappBaseScreen{
    protected $name = 'images';
    protected $type = 'list';

    // @todo Add paging support here
    public function run(){
        $numberOfPosts = WiziappConfig::getInstance()->comments_list_limit;

        $screen_conf = $this->getConfig();

        $page = array();

        $args = array(
            'post_type' => 'attachment',
            'numberposts' => -1,
            'post_status' => null,
            'post_parent' => null, // any parent
        );
        $attachments = get_posts($args);

        $counter = 0;
        foreach($attachments as $attachment){
            $isImage = wp_attachment_is_image($attachment->ID);
            if ( $isImage && $counter < $numberOfPosts ){
                $this->appendComponentByLayout($page, $screen_conf['items'], $attachment);
                ++$counter;
            }
            if ( $counter == $numberOfPosts ){
                break;
            }
         }

         $title = __('Gallery', 'wiziapp');
         $screen = $this->prepare($page, $title, 'gallery', false, true);

         $screen['screen']['default'] = 'grid';
         $screen['screen']['sub_type'] = 'image';
         $this->output($screen);
    }

    // @todo Add paging support here
    public function runByPost($params){
        $post_id = $params;
        $ids = FALSE;
        if ( is_array($params) ){
            $post_id = $params[0];
            $ids = $params[1];
        }

        if($ids){
            $images_ids = explode('_', $ids);
        }else{
            $images_ids = false;
        }
        $post = get_post($post_id);

        $screen_conf = $this->getConfig();
        $page = array();

        $images = WiziappDB::getInstance()->find_content_gallery_images($post_id);

        /**if ( ! function_exists('wp_load_image') ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }  */

        foreach($images as $image_info){
            //$attributes = json_decode($image_info['attachment_info'], TRUE);

            /**if(is_array($images_ids) && (!in_array($image_info['id'], $images_ids) && !in_array($attributes['metadata']['id'], $images_ids))){
                continue;
            }*/

            if ( is_array($images_ids) ){
                if ( !in_array($image_info['id'], $images_ids) ){
                    continue;
                }
            }

            //$image = $attributes['attributes'];
            $dom = new WiziappDOMLoader($image_info['original_code'], get_bloginfo('charset'));
            $imageDOM = $dom->getBody();
            $image = $imageDOM[0]['img']['attributes'];
            WiziappLog::getInstance()->write('INFO', "image object: " . print_r($image, true), 'WiziappImageScreen');
            WiziappLog::getInstance()->write('INFO', "image_info : " . print_r($image_info, true), 'WiziappImageScreen');

            $pid = $image_info['id'];
            $image['pid'] = $pid;
            $image['description'] = $this->getImageDescription($image['title'], $post_id, $image);
            $image['alttext'] = $image['title'];
            $image['imageURL'] = $image['src'];
            $image['relatedPost'] = $post_id;

            // The images component will take care of the resizing
            $image['thumbURL'] = $image['src'];

            $this->appendComponentByLayout($page, $screen_conf['items'], $image, true);
         }

         if ( WiziappContentHandler::getInstance()->isHTML() ){
            $page[] = '<li class="imageGalleryCellFinal"></li>';
         }

         $title = str_replace('&amp;', '&', $post->post_title);
         $screen = $this->prepare($page, $title, 'gallery', false, true);

         if ( isset($_GET['album']) ){
            $back = array('url' => 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/galleries'), 'text' => __(WiziappConfig::getInstance()->getScreenTitle('albums'), 'wiziapp'));
         }
         else{
            $back = array('url' => WiziappLinks::postLink($post_id), 'text' => $title);
         }

         $screen['screen']['default'] = 'grid';
         $screen['screen']['sub_type'] = 'image';
         $this->output($screen, $back);
    }

    public function getImageDescription($title, $post_id, $image){
        $src = $image['src'];
        //doesn't support nextgen galleries
        if ( $images = get_children(array(
            'post_parent' => $post_id,
            'post_type' => 'attachment',
            'numberposts' => -1,
            'order' => 'ASC',
            'orderby' => 'ID',
            'post_mime_type' => 'image',)))
	{
            foreach( $images as $image ) {
             WiziappLog::getInstance()->write('INFO', "image_info : " . print_r($image_info, true), 'WiziappImageScreen');
             WiziappLog::getInstance()->write('INFO', "image: ". print_r($image,true), 'WiziappImageScreen');
                if ($title == $image->post_title || $src == $image->guid){
                    return $image->post_excerpt; // caption
                    //return $image->post_content; //description
                }
            }
        } elseif(isset($image['data-wiziapp-nextgen-gallery-id'])){ // it's a nextgen gallery
            $galleryId = $image['data-wiziapp-nextgen-gallery-id'];
            global $nggdb;
            $images = array();
            $ngImages = $nggdb->get_gallery($galleryId);
            foreach($ngImages as $nggImage){
                if (strpos($src,$nggImage->filename)){
                    $desc = (strlen($nggImage->description)>0) ? $nggImage->description : $nggImage->alttext;
                    return $desc;
                }
            }
	} else {
                WiziappLog::getInstance()->write('INFO', "no images found for {$post_id}", 'WiziappImageScreen');
		return false;
	}
        return '';
    }
}
<?php
/*
Plugin Name:    Aucor Repair Content Image Sizes
Description:    Fix image URLs in content by image size classes
Version:        0.1
Author:         Aucor Oy
Author URI:     https://www.aucor.fi
Text Domain:    aucor_repair_content_image_sizes
*/

defined('ABSPATH') or die('No script kiddies please!');

/**
 * WP_CLI commands
 */
if (defined('WP_CLI') && WP_CLI) {

  class Aucor_Repair_Content_Image_Sizes extends WP_CLI_Command {

    private $updated;
    private $ignored;
    private $warning;
    private $is_verbose;
    private $post_log;

    /**
     * _construct
     */
    public function __construct() {

      $this->updated = 0;
      $this->ignored = 0;
      $this->warning = 0;
      $this->is_verbose = true;
      $this->post_log = array();

    }

    /**
     * wp repair-content-image-sizes run
     *
     * --post_type=post,page
     *
     */
    public function run($args, $assoc_args) {

      // fetch wpdb
      global $wpdb;

      // add more memory for this operation
      ini_set('memory_limit', '-1');

      // set post type
      $post_type = ['post'];
      if (isset($assoc_args['post_type'])) {
        $post_type = explode(',', $assoc_args['post_type']);
      }

      // query all posts
      $args = array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
      );
      $query = new WP_Query($args);

      while ($query->have_posts()) : $query->the_post();

        // flush post log
        $this->post_log = array();

        // deal with content
        $the_content = get_the_content();
        $altered_content = $this->repair($the_content, $query->post);

        if ($this->is_verbose && !empty($this->post_log)) {
          WP_CLI::log('Repair post #' . $query->post->ID . ': ' . get_the_title());
          foreach ($this->post_log as $log) {
            WP_CLI::log($log);
          }
        }

        if ($the_content !== $altered_content) {

          // update with wpdb to avoid revisions and changes to modified dates etc
          $wpdb->update(
            $wpdb->posts,
            array(
              'post_content' => $altered_content // data
            ),
            array(
              'ID' => $query->post->ID // where
            ),
            array(
              '%s' // data format
            ),
            array(
              '%d' // where format
            )
          );

        }

        if ($this->is_verbose && !empty($this->post_log)) {
          WP_CLI::log('');
        }

      endwhile;

      WP_CLI::success($this->updated . ' repaired, ' . $this->ignored . ' ignored, ' . $this->warning . ' warnings');

    }

    /**
     * Repair images in content
     *
     * @param string  $content the html content
     * @param WP_Post $post the post object
     */
    private function repair($content, $post) {

      $content = $this->repair_img($content, $post);

      // @TODO: make fix for caption width

      return $content;

    }

    /**
     * Repair plain <img> tags
     *
     * @param string  $content the html content
     * @param WP_Post $post the post object
     *
     * @return string filtered content
     */
    private function repair_img($content, $post) {

      // get all images in content (full <img> tags)
      preg_match_all('/<img[^>]+>/i', $content, $img_array);

      // no images in content
      if (empty($img_array)) {
        return $content;
      }

      // prepare nicer array structure
      $img_and_meta = array();
      for ($i=0; $i < count($img_array[0]); $i++) {
        $img_and_meta[$i] = array('tag' => $img_array[0][$i]);
      }

      foreach ($img_and_meta as $i=>$arr) {

        // get classes
        preg_match('/ class="([^"]*)"/i', $img_array[0][$i], $class_temp);

        $img_and_meta[$i]['class'] = !empty($class_temp) ? $class_temp[1] : '';

        // only proceed if image is created by WordPress (has wp-image-{ID} class)
        if (!strstr($img_and_meta[$i]['class'], 'wp-image-')) {
          continue;
        }

        // only proceed if image has size (has size-{size} class)
        if (!strstr($img_and_meta[$i]['class'], 'size-')) {
          continue;
        }

        // get the attachment id
        preg_match('/wp-image-(\d+)/i', $img_array[0][$i], $id_temp);
        if (empty($id_temp)) {
          continue;
        }

        $img_and_meta[$i]['id'] = (int) $id_temp[1];
        $attachment = get_post($img_and_meta[$i]['id']);

        // check if given ID is really attachment (or copied from some other WordPress)
        if (empty($attachment) || $attachment->post_type !== 'attachment') {
          continue;
        }

        // get the attachment size
        preg_match('/size-(\S*)/i', $img_and_meta[$i]['class'], $size_temp);
        if (empty($size_temp)) {
          continue;
        }
        $img_and_meta[$i]['size'] = $size_temp[1];



        // get current src
        preg_match('/ src="([^"]*)"/i', $img_array[0][$i], $src_temp);
        if (empty($src_temp)) {
          continue;
        }
        $img_and_meta[$i]['src'] = $src_temp[1];

        /**
         * Go fetch image src with given size and compare it to
         * current src. If the new src is different, image needs
         * repairing. If it's the same, things are ok.
         */

        $img_src = wp_get_attachment_image_src($img_and_meta[$i]['id'], $img_and_meta[$i]['size']);

        // skip invalid src
        if (empty($img_src) || !is_array($img_src)) {
          continue;
        }

        // check if image is missing (empty src or src points to directory)
        if (empty($img_src[0]) || substr($img_src[0], -1) == '/') {

          // image size might be missing / broken => try full size
          $img_src = wp_get_attachment_image_src($img_and_meta[$i]['id'], 'full');

          if (empty($img_src[0]) || substr($img_src[0], -1) == '/') {

            $this->post_log[] = '--> [Skipped broken image]: ' . $img_and_meta[$i]['src'] . ' => ' . $img_src[0];
            $this->warning++;

            continue;

          }

        }

        if ($img_src[0] == $img_and_meta[$i]['src']) {

          // it's okay
          $this->ignored++;

        } else {

          // copy current tag
          $img_and_meta[$i]['new_tag'] = $img_and_meta[$i]['tag'];

          // replace src
          $img_and_meta[$i]['new_tag'] = preg_replace('/ src="([^"]*)"/i', ' src="' . $img_src[0] . '"', $img_and_meta[$i]['new_tag']);

          // replace width
          $img_and_meta[$i]['new_tag'] = preg_replace('/ width="([^"]*)"/i', ' width="' . $img_src[1] . '"', $img_and_meta[$i]['new_tag']);

          // replace height
          $img_and_meta[$i]['new_tag'] = preg_replace('/ height="([^"]*)"/i', ' height="' . $img_src[2] . '"', $img_and_meta[$i]['new_tag']);

          // replace image inside content
          $content = str_replace($img_and_meta[$i]['tag'], $img_and_meta[$i]['new_tag'], $content);

          // updated
          $this->post_log[] = '--> Repair image: ' . $img_and_meta[$i]['src'] . ' => ' . $img_src[0];
          $this->updated++;

        }

      }

      return $content;

    }

  }

  WP_CLI::add_command('repair-content-image-sizes', 'Aucor_Repair_Content_Image_Sizes');

}

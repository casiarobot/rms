<?php
/**
 * Article script for the RMS API.
 *
 * Allows read and write access to content articles via the RMS API.
 *
 * @author     Russell Toris <rctoris@wpi.edu>
 * @copyright  2012 Russell Toris, Worcester Polytechnic Institute
 * @license    BSD -- see LICENSE file
 * @version    December, 13 2012
 * @package    api.content.articles
 * @link       http://ros.org/wiki/rms
 */

include_once(dirname(__FILE__).'/../../../inc/config.inc.php');
include_once(dirname(__FILE__).'/../../api.inc.php');
include_once(dirname(__FILE__).'/../../users/user_accounts/user_accounts.inc.php');
include_once(dirname(__FILE__).'/articles.inc.php');

// JSON response
header('Content-type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// check for authorization
if($auth = authenticate()) {
  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      // check if this is a default request
      if(count($_GET) === 0) {
        // check for articles
        if($articles = get_articles()) {
          $result = create_200_state($articles);
        } else {
          $result = create_404_state('No content articles found.');
        }
      } else if(count($_GET) === 1 && isset($_GET['id'])) {
        // now check if the article was found
        if($article = get_article_by_id($_GET['id'])) {
          $result = create_200_state($article);
        } else {
          $result = create_404_state('Article ID "'.$_GET['id'].'" is invalid.');
        }
      } else if(count($_GET) === 1 && isset($_GET['pageid'])) {
        // now check if the articles were found
        if($articles = get_articles_by_pageid($_GET['pageid'])) {
          $result = create_200_state($articles);
        } else {
          $result = create_404_state('No articles with content page ID "'.$_GET['pageid'].'" found.');
        }
      } else if(isset($_GET['request'])) {
        switch ($_GET['request']) {
          case 'editor':
            if($auth['type'] === 'admin') {
              if(count($_GET) === 1) {
                $result = create_200_state(get_article_editor_html(null));
              } else if(count($_GET) === 2 && isset($_GET['id'])) {
                $result = create_200_state(get_article_editor_html($_GET['id']));
              } else {
                $result = create_404_state('Too many fields provided.');
              }
            } else {
              write_to_log('SECURITY: '.$auth['username'].' attempted to get an article editor.');
              $result = create_401_state();
            }
            break;
          default:
            $result = create_404_state($_GET['request'].' request type is invalid.');
            break;
        }
      } else {
        $result = create_404_state('Unknown request.');
      }
      break;
    default:
      $result = create_404_state($_SERVER['REQUEST_METHOD'].' method is unavailable.');
      break;
  }
} else {
  $result = create_401_state();
}

// return the JSON encoding of the result
echo json_encode($result);
?>

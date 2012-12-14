<?php
/**
 * Slideshow include functions for the RMS API.
 *
 * Allows read and write access to slideshow slides via PHP function calls. Used throughout RMS and
 * within the RMS API.
 *
 * @author     Russell Toris <rctoris@wpi.edu>
 * @copyright  2012 Russell Toris, Worcester Polytechnic Institute
 * @license    BSD -- see LICENSE file
 * @version    December, 13 2012
 * @package    api.content.slides
 * @link       http://ros.org/wiki/rms
 */

include_once(dirname(__FILE__).'/../../../inc/config.inc.php');

/**
 * Check if the given array has all of the necessary fields to create slide. This does include
 * a check to see if a file was uploaded.
 *
 * @param array $array The array to check
 * @return boolean If the given array has all of the necessary fields to create a new slide
 */
function valid_slide_fields($array) {
  return isset($array['caption']) && isset($array['index']) && (count($array) === 2) && isset($_FILES['img']);
}

/**
 * Get an array of all slide entires in the database or null if none exist.
 *
 * @return array|null The array of slide entries or null if none exist.
 */
function get_slides() {
  global $db;

  // grab the articles and push them into an array
  $result = array();
  $query = mysqli_query($db, "SELECT * FROM `slides` ORDER BY `index`");
  while($cur = mysqli_fetch_assoc($query)) {
    $result[] = $cur;
  }

  return (count($result) === 0) ? null : $result;
}

/**
 * Get the slide array for the slide with the given ID, or null if none exist.
 *
 * @param integer $id The slide ID number
 * @return array|null An array of the slide's SQL entry or null if none exist
 */
function get_slide_by_id($id) {
  global $db;

  // grab the page
  $sql = sprintf("SELECT * FROM `slides` WHERE `slideid`='%d'", $db->real_escape_string($id));
  return mysqli_fetch_assoc(mysqli_query($db, $sql));
}

/**
 * Get the slide array for the slide with the given image name, or null if none exist.
 *
 * @param integer $img The slide image name
 * @return array|null An array of the slide's SQL entry or null if none exist
 */
function get_slide_by_img($img) {
  global $db;

  // grab the page
  $sql = sprintf("SELECT * FROM `slides` WHERE `img`='%s'", $db->real_escape_string($img));
  return mysqli_fetch_assoc(mysqli_query($db, $sql));
}

/**
 * Create a slide with the given information. Any errors are returned.
 *
 * @param string $caption The slide caption
 * @param integer $index The slide index
 * @param string $fname The name of the file to save in the lides folder
 * @param string $tmp_file_location the temp file location of the uploaded image
 * @return string|null An error message or null if the create was sucessful
 */
function create_slide($caption, $index, $fname, $tmp_file_location) {
  global $db;

  // check if a file exists
  if(get_slide_by_img($fname)) {
    return 'ERROR: Slide already exists with image name '.$fname;
  } else if($error = upload_img($fname, $tmp_file_location)) {
    return $error;
  } else {
    // insert into the database
    $sql = sprintf("INSERT INTO `slides` (`img`, `caption`, `index`) VALUES ('%s', '%s', '%d')",
    $db->real_escape_string($fname), $db->real_escape_string($caption), $db->real_escape_string($index));
    mysqli_query($db, $sql);

    // no error
    return null;
  }
}

/**
 * Upload a slide image with the given information. Any errors are returned.
 *
 * @param string $fname The name of the file to save in the lides folder
 * @param string $tmp_file_location the temp file location of the uploaded image
 * @return string|null An error message or null if the create was sucessful
 */
function upload_img($fname, $tmp_file_location) {
  global $db;

  // check if a file exists
  if(get_slide_by_img($fname)) {
    return 'ERROR: Slide already exists with image name '.$fname;
  } else {
    $destination = dirname(__FILE__).'/../../../img/slides/'.$fname;

    // check if we need to remove the old file
    if(file_exists($destination)) {
      unlink($destination);
    }

    // move the file
    move_uploaded_file($tmp_file_location, $destination);

    // no error
    return null;
  }
}

/**
 * Update an interface with the given information inside of the array. The array should be indexed
 * by the SQL column names. A request to upload the file should have been made with an HTTP POST.
 * Any errors are returned.
 *
 * @param array $fields the fields to update including the slide ID number
 * @return string|null an error message or null if the update was sucessful
 */
function update_slide($fields) {
  global $db;

  if(!isset($fields['id'])) {
    return 'ERROR: ID field missing in update';
  }

  // build the SQL string
  $sql = "";
  $num_fields = 0;
  // check for the slide
  if(!($slide = get_slide_by_id($fields['id']))) {
    return 'ERROR: Slide ID '.$fields['id'].' does not exist';
  }

  // check if we are changing the id
  $id_to_set = $slide['slideid'];
  if(isset($fields['slideid'])) {
    $num_fields++;
    if($fields['slideid'] !== $slide['slideid'] && get_slide_by_id($fields['slideid'])) {
      return 'ERROR: Slide ID '.$fields['slideid'].' already exists';
    } else {
      $id_to_set = $fields['slideid'];
    }
  }
  $sql .= sprintf(" `slideid`='%d'", $db->real_escape_string($id_to_set));

  // check for each update
  if(isset($fields['caption'])) {
    $num_fields++;
    $sql .= sprintf(", `caption`='%s'", $db->real_escape_string($fields['caption']));
  }
  if(isset($fields['index'])) {
    $num_fields++;
    $sql .= sprintf(", `index`='%d'", $db->real_escape_string($fields['index']));
  }

  // do the file check
  if(isset($fields['img'])) {
    if($fields['img'] !== $slide['img'] && get_slide_by_img($fields['img'])) {
      return 'ERROR: Image '.$fields['img'].' already not exists.';
    } else if(!file_exists(dirname(__FILE__).'/../../../img/slides/'.$fields['img'])) {
      return 'ERROR: Image '.$fields['img'].' does not exist on the server.';
    }else {
      $num_fields++;
      // cleanup the old image
      $file = dirname(__FILE__).'/../../../img/slides/'.$slide['img'];
      if(file_exists($file)) {
        unlink($file);
      }
      $sql .= sprintf(", `img`='%s'", $db->real_escape_string($fields['img']));
    }
  }

  // check to see if there were too many fields or if we do not need to update
  if($num_fields !== (count($fields) - 1)) {
    return 'ERROR: Too many fields given.';
  } else if ($num_fields === 0 && !isset($img)) {
    // nothing to update
    return null;
  }

  // we can now run the update
  $sql = sprintf("UPDATE `slides` SET ".$sql." WHERE `slideid`='%d'"
  , $db->real_escape_string($fields['id']));
  mysqli_query($db, $sql);

  // no error
  return null;
}

/**
 * Delete the slide array and image for the slide with the given ID. Any errors are returned.
 *
 * @param integer $id The slide ID number
 * @return string|null an error message or null if the delete was sucessful
 */
function delete_slide_by_id($id) {
  global $db;

  // see if the environment exists
  if($slide = get_slide_by_id($id)) {
    // remove the file
    $file = dirname(__FILE__).'/../../../img/slides/'.$slide['img'];
    if(file_exists($file)) {
      unlink($file);
    }

    // delete it
    $sql = sprintf("DELETE FROM `slides` WHERE `slideid`='%d'", $db->real_escape_string($id));
    mysqli_query($db, $sql);
    // no error
    return null;
  } else {
    return 'ERROR: Slide ID '.$id.' does not exist';
  }
}

/**
 * Get the HTML for creating the given slide inside of the slideshow.
 *
 * @param array $slide The slide SQL entry to generate HTML for
 * @return string The HTML for the slide
 */
function create_slide_html($slide) {
  return '<div class="slide">
            <img src="img/slides/'.$slide['img'].'" width="800" height="350" />
            <div class="caption"><p>'.$slide['caption'].'</p></div>
          </div>';
}

/**
 * Get the HTML for creating the slideshow.
 *
 * @return string The HTML for the slideshow
 */
function create_slideshow_html() {
  $slides = get_slides();
  $html  = '<div id="slides">
              <div class="slides_container">';
  foreach ($slides as $cur) {
    $html .= create_slide_html($cur);
  }
  $html .= '  </div>
            </div>';

  return $html;
}

/**
 * Get the HTML for an editor used to create or edit the given slide entry. If this is not an
 * edit, null can be given as the ID. An invalid ID is the same as giving a null ID.
 *
 * @param integer|null $id the ID of the slide to edit, or null if a new entry is being made
 * @return string A string containing the HTML of the editor
 */
function get_slide_editor_html($id) {
  // see if a slide exists with the given id
  $cur = get_slide_by_id($id);

  if($cur) {
    $img = $cur['img'];
    $caption = $cur['caption'];
    $index = $cur['index'];
  } else {
    $img = '';
    $caption = '';
    $index = '';
  }

  $result = '<p>Complete the following form to create or edit a slide.</p>
             <form action="javascript:submit();" method="POST" enctype="multipart/form-data"><fieldset>
               <ol>';

  // only show the ID for edits
  $result .=  ($cur) ? '<li><label for="id">Slide ID</label><input type="text" name="id"
                             id="id" value="'.$cur['slideid'].'" readonly="readonly" /></li>' : '';

  // check if we have an image to preview
  if($cur) {
    $result .=  '<li><label for="id">Slide ID</label><input type="text" name="id"
                      id="id" value="'.$cur['slideid'].'" readonly="readonly" /></li>
                 <li>
                   <label>Current Image</label>
                   <b>'.$img.'</b><br />
                   <center><img src="../img/slides/'.$img.'" width="400" height="175" /></center>
                   <div class="line"></div>
                   <label for="img">New Image (optional)</label><input type="file" name="img" id="img" />
                 </li>';
  } else {
    $result .=  '<li>
                   <label for="img">Upload Image</label>
                   <input type="file" name="img" id="img" required />
                 </li>';
  }
  $result .=  '<li>
                 <label for="caption">Caption</label>
                 <input type="text" name="caption" value="'.$caption.'" id="caption"
                  placeholder="e.g., A Robot being Awesome" required />
               </li>
               <li>
                 <label for="index">Index</label>
                 <select name="index" id="index" required>';
  // create enough to index 15 slides
  for($i = 0; $i < 15; $i++) {
    $selected = ($index === strval($i)) ? 'selected="selected" ' : '';
    $result .=  '<option value="'.$i.'" '.$selected.'>'.$i.'</option>';
  }

  $result .= '      </select>
                  </li></ol>
                  <input type="submit" value="Submit" />
                </fieldset>
              </form>';

  return $result;
}
?>

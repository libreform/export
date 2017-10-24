<?php
/**
 * Plugin name: WPLF Export
 * Plugin URI: https://github.com/k1sul1/wplf-export
 * Description: Export form data from WP Libre Form
 * Version: 1.0.1
 * Author: @k1sul1
 * Author URI: https://github.com/k1sul1
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl.html
 * Text Domain: wplf-export
 */

class WPLF_Export {
  public static $instance;
  public $csv;

  public static function instance() {
    if (is_null(self::$instance)) {
      self::$instance = new WPLF_Export();
    }

    return self::$instance;
  }

  public function __construct() {
    add_filter('bulk_actions-edit-wplf-submission', array($this, 'bulkAction'));
    add_filter('handle_bulk_actions-edit-wplf-submission', array($this, 'handleBulk'), 10, 3);
    add_action('template_redirect', array($this, 'handleDownload'));
    add_action('admin_notices', array($this, 'noticeNag'));
  }

  public function bulkAction($options) {
    $options['wplf_export'] = __('Export', 'wplf-export');

    return $options;
  }

  public function handleBulk($redirect_to, $action, $post_ids) {
    if ($action !== 'wplf_export') {
      return $redirect_to;
    }

    // Export form id.
    $efid = !empty($_GET['form']) ? (int) $_GET['form'] : NULL;

    $filter_fn = function($field_name) {
      return $field_name[0] !== '_';
    };

    if (has_action("wplf_export_form_{$efid}_filter")) {
      $filter = apply_filters("wplf_export_form_{$efid}_filter", $filter_fn);
    } else {
      $filter = apply_filters('wplf_export_form_filter', $filter_fn);
    }

    $header = array();
    $rows = array();
    $errors = array();
    $queryvars = array();

    for ($i = 0; $i < count($post_ids); $i++) {
      // Should handle uploaded files fine.
      $id = $post_ids[$i];
      $meta = get_post_meta($id);
      $fields = array_filter(array_keys($meta), $filter);

      if ($i === 0) {
        $header = $fields;
      }

      $row = array();
      foreach ($fields as $field) {
        $row[] = $meta[$field][0];
      }

      if (count($row) !== count($header)) {
        $queryvars['wplf_export_error'] = 'ERR_HEADER_ROW_MISMATCH';
        $errors[] = array($id => 'ERR_HEADER_ROW_MISMATCH');
      }

      $rows[] = $row;
    }

    if (count($errors) > 0) {
      error_log(print_r($errors, true));
    }

    if (has_action("wplf_export_form_{$efid}_delimiter")) {
      $delimiter = apply_filters("wplf_export_form_{$efid}_delimiter", ',');
    } else {
      $delimiter = apply_filters('wplf_export_form_delimiter', ',');
    }

    $filename = 'wplf_export_' . date('U') . '.csv';
    if (has_action("wplf_export_form_{$efid}_filename")) {
      $filename = apply_filters("wplf_export_form_{$efid}_filename", $filename);
    } else {
      $filename = apply_filters('wplf_export_form_filename', $filename);
    }

    $csvpath = $this->generateCSV($filename, $header, $rows, $delimiter);
    $queryvars['wplf_export_path'] = $csvpath;
    $queryvars['wplf_exported_posts'] = count($post_ids);

    $redirect_to = add_query_arg($queryvars, $redirect_to);
    return $redirect_to;
  }

  public function generateCSV($filename = 'wplf_export.csv', $header = array(), $rows = array(), $delimiter = ',') {
    $uploads = wp_upload_dir('wplf'); // meant for date, but works with a string too
    $path = $uploads['basedir'] . DIRECTORY_SEPARATOR . 'wplf' . DIRECTORY_SEPARATOR . $filename;
    $handle = fopen($path, 'w+');

    fputcsv($handle, $header, $delimiter);

    foreach ($rows as $row) {
      fputcsv($handle, $row, $delimiter);
    }

    return $path;
  }

  public function getCSV($filepath) {
    $path = $filepath;

    if (!file_exists($path)) {
      return false;
    }

    $handle = fopen($path, 'r');
    $csv = '';

    while(!feof($handle)) {
      $csv .= fread($handle, 8192);
    }

    fclose($handle);

    return $csv;
  }

  public function handleDownload() {
    if (parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) === '/wplf-export') {
      $allowed = current_user_can(
          apply_filters('wplf_export_capability', 'edit_others_posts')
      );
      $uploads = wp_upload_dir();
      $filename = basename($_REQUEST['wplf-export-download']);

      // Do not trust the parameter! Only allow files from this folder.
      $filepath = $uploads['basedir'] . DIRECTORY_SEPARATOR . 'wplf' . DIRECTORY_SEPARATOR . $filename;

      if ($allowed && $filepath) {
        $data = $this->getCSV($filepath);


        if ($data) {
          header("Content-Type: application/csv", true, 200);
          header("Content-Disposition: attachment; filename=$filename");
          echo $data;

          // Remove the file after download
          ignore_user_abort(true);
          register_shutdown_function('unlink', $filepath); // Never pass unsanitized filepath!
          die();
        }
      }
    }
  }

  public function noticeNag() {
    $r = $_REQUEST;

    $html = "";
    $fpath = !empty($_REQUEST['wplf_export_path']) ? urlencode($_REQUEST['wplf_export_path']) : null;

    if (!empty($r['wplf_export_error'])
        && $r['wplf_export_error'] === 'ERR_HEADER_ROW_MISMATCH') {
      $html .= "<div id='wplf_export_nag' class='notice notice-error is-dismissible'>";
      $html .= "<p>Row item count was different from header item count.
        This is usually caused by selecting multiple different forms for
        one export, or modifying the form fields after it has submissions.</p>
        <p>If the imported file seems to be broken, select only one type
        of form and try exporting again.</p>";
      $html .= "<p>The download should start automatically.
        <a href='/wplf-export?wplf-export-download=$fpath' id='wplf_export_save' target='_blank'>
        If it doesn't click here.
        </a></p>";
      $html .= "</div>";
    } else if (!empty($r['wplf_exported_posts'])) {
      $count = (int) $r['wplf_exported_posts'];

      $html .= "<div id='wplf_export_nag' class='notice notice-success is-dismissible'>";
      $html .= "<p>Exported $count submissions.</p>";
      $html .= "<p>The download should start automatically.
        <a href='/wplf-export?wplf-export-download=$fpath' id='wplf_export_save' target='_blank'>
        If it doesn't click here.
        </a></p>";
      $html .= "</div>";

      $html .= "<script>setTimeout(function(){document.getElementById('wplf_export_save').click();},100);</script>";
    }

    echo $html;
  }
}

WPLF_Export::instance();

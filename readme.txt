=== WPLF Export ===
Contributors: k1sul1
Tags: Export, WP, Libre, Form
Donate link: https://github.com/k1sul1
Requires at least: 4.2
Tested up to: 4.7.4
Stable tag: 1.4.2
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

Export form submissions created by WP Libre Form.

This builds on a "new" feature added in WP 4.7 called bulk actions, and basically adds a new item to the bulk action menu.

**How to use**

Seriously? Select your forms, select Export from bulk action menu and press "Apply".
For best results, select only one type of form for export.

**Customizing your export**

You can add your own filters to be applied on all or invidual forms and get only the output you'll need.
Note that invidual form filters work only when you spesify a form using the "Filter by form" field.

Filter fields:

`add_filter('wplf_export_form_filter', function($filter_fn) {
  // Note that we're replacing $filter_fn entirely. Return a function!
  $fn = function($field_name) {
    return $field_name !== 'block_this_field';
  };

  return $fn;
});

add_filter('wplf_export_form_84_filter', function($filter_fn) {
  // Same thing.
});`

Change delimiter (default: ','):

`add_filter('wplf_export_form_delimiter', function($delimiter) {
  return '#';
});

add_filter('wplf_export_form_84_delimiter', function($delimiter) {
  return '#';
});`

Change filename (default: wplf_export_TIMESTAMP.csv):

`add_filter('wplf_export_form_{ID}_filename', ..);`

New filters and features are subject to be added. PRs welcome!

== Installation ==

1. Upload plugin to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Installation done!

== Frequently Asked Questions ==

None yet.

== Screenshots ==

1. How to use

== Changelog ==

Commit log is available at https://github.com/k1sul1/wplf-export/commits/master

== Upgrade Notice ==

* 1.0 There's an update available to WPLF Export that makes it better. Please update it!

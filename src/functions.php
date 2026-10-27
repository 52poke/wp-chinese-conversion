<?php

function wpcc_normalize_options($options) {
	return \Wpcc\Config::normalize($options);
}

function wpcc_init() {
	\Wpcc\RequestService::init();
}

function wpcc_activate() {
	\Wpcc\Plugin::activate();
}

function wpcc_set_cookie($name, $value, $expires) {
	return \Wpcc\RequestService::set_cookie($name, $value, $expires);
}

function wpcc_get_variant_regex($include_controls = true) {
	return \Wpcc\Config::get_variant_regex($include_controls);
}

function wpcc_header() {
	\Wpcc\FrontendService::header();
}

function wpcc_template_redirect() {
	\Wpcc\RequestService::template_redirect();
}

function wpcc_insert_query_vars($vars) {
	$vars[] = 'variant';
	return $vars;
}

function zhconversion($str, $variant = null) {
	return \Wpcc\ConversionService::zhconversion($str, $variant);
}

function zhconversion2($str, $variant = null) {
	return \Wpcc\ConversionService::zhconversion2($str, $variant);
}

function wpcc_id() {
	static $wpcc_id = 1000;
	return $wpcc_id++;
}

function wpcc_no_conversion_filter($str) {
	return \Wpcc\ConversionService::wpcc_no_conversion_filter($str);
}

function zhconversion_all($str, $langs = array('zh-hans', 'zh-hant')) {
	return \Wpcc\ConversionService::zhconversion_all($str, $langs);
}

function zhconversion_deep($value) {
	return \Wpcc\ConversionService::zhconversion_deep($value);
}

function limit_zhconversion($str, $function) {
	return \Wpcc\ConversionService::limit_zhconversion($str, $function);
}

function zhconversion_hant($str) {
	return \Wpcc\ConversionService::convert($str, 'zh-hant');
}

function zhconversion_hans($str) {
	return \Wpcc\ConversionService::convert($str, 'zh-hans');
}

function wpcc_cancel_incorrect_redirect($redirect_to, $redirect_from) {
	return \Wpcc\LinkService::cancel_incorrect_redirect($redirect_to, $redirect_from);
}

function wpcc_rewrite_rules($rules) {
	return \Wpcc\LinkService::rewrite_rules($rules);
}

function _wpcc_permalink_preg_callback($matches) {
	return \Wpcc\LinkService::permalink_preg_callback($matches);
}

function wpcc_link_conversion($link, $variant = null) {
	return \Wpcc\LinkService::link_conversion($link, $variant);
}

function wpcc_link_conversion_auto($link, $variant = null) {
	return \Wpcc\LinkService::link_conversion_auto($link, $variant);
}

function wpcc_get_noconversion_url() {
	return \Wpcc\LinkService::get_noconversion_url();
}

function wpcc_pagenum_link_fix($link) {
	return \Wpcc\LinkService::pagenum_link_fix($link);
}

function wpcc_fix_link_conversion($link) {
	return \Wpcc\LinkService::fix_link_conversion($link);
}

function wpcc_cancel_link_conversion($link) {
	return \Wpcc\LinkService::cancel_link_conversion($link);
}

function wpcc_rel_canonical() {
	\Wpcc\FrontendService::canonical();
}

function variant_attribute($default = "zh", $variant = false) {
	return \Wpcc\FrontendService::variant_attribute($default, $variant);
}

function variant($default = false) {
	return \Wpcc\FrontendService::variant($default);
}

function wpcc_output_navi($args = '') {
	return \Wpcc\FrontendService::output_navi($args);
}

function wpcc_get_prefered_language($accept_languages, $target_langs, $flag = 0) {
	return \Wpcc\RequestService::get_prefered_language($accept_languages, $target_langs, $flag);
}

function wpcc_is_robot() {
	return \Wpcc\RequestService::is_robot();
}

function wpcc_apply_filter_search_rule() {
	\Wpcc\SearchService::apply_filter_search_rule();
}

function wpcc_filter_search_rule($where) {
	return \Wpcc\SearchService::filter_search_rule($where);
}

function wpcc_ob_callback($buffer) {
	return \Wpcc\FrontendService::ob_callback($buffer);
}

function wpcc_debug() {
	\Wpcc\FrontendService::debug();
}

function wpcc_parse_query($query) {
	\Wpcc\RequestService::parse_query($query);
}

function wpcc_load_conversion_table() {
	\Wpcc\ConversionService::load();
}

function wpcc_do_conversion() {
	\Wpcc\FrontendService::do_conversion();
}

function wpcc_body_class($classes) {
	return \Wpcc\FrontendService::body_class($classes);
}

function wpcc_locale($output, $doctype = 'html') {
	return \Wpcc\FrontendService::locale($output, $doctype);
}

function wpcc_appthemes_add_quicktags() {
	$wpcc_options = \Wpcc\Config::options();
	if ( !empty($wpcc_options['wpcc_no_conversion_qtag']) && wp_script_is('quicktags') ) {
?>
	<script type="text/javascript">
//<![CDATA[
		QTags.addButton('eg_wpcc_nc', 'WPCC_NC', '<!--WPCC_NC_START-->', '<!--WPCC_NC_END-->', null, 'WP Chinese Conversion DO-NOT Convert Tag', 120 );
//]]>
	</script>
<?php
	}
}

<?php

declare(strict_types=1);

namespace Wpcc;

class SearchService {
	public static function apply_filter_search_rule(): void {
		add_filter('posts_where', 'wpcc_filter_search_rule', 100);
		add_filter('posts_distinct', function($s) {
			return 'DISTINCT';
		});
	}

	public static function filter_search_rule(string $where): string {
		global $wp_query, $wpdb;
		if (empty($wp_query->query_vars['s'])) {
			return $where;
		}
		if (!preg_match("/^([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){1}/", $wp_query->query_vars['s']) &&
			!preg_match("/([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){1}$/", $wp_query->query_vars['s']) &&
			!preg_match("/([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){2,}/", $wp_query->query_vars['s'])) {
			return $where;
		}

		wpcc_load_conversion_table();

		$sql = '';
		$and1 = '';
		$original = '';
		foreach ((array) $wp_query->query_vars['search_terms'] as $value) {
			$raw_value = wp_unslash($value);
			$like = '%' . $wpdb->esc_like($raw_value) . '%';
			$original .= "{$and1}(" . $wpdb->prepare(
				"($wpdb->posts.post_title LIKE %s) OR ($wpdb->posts.post_excerpt LIKE %s) OR ($wpdb->posts.post_content LIKE %s)",
				$like,
				$like,
				$like
			) . ')';
			$valuea = zhconversion_all($raw_value);
			$valuea[] = $raw_value;
			$sql .= "{$and1}( ";
			$or2 = '';
			foreach ($valuea as $v) {
				$like_variant = '%' . $wpdb->esc_like($v) . '%';
				$sql .= "{$or2}(" . $wpdb->prepare(
					"$wpdb->posts.post_title LIKE %s OR $wpdb->posts.post_content LIKE %s OR $wpdb->posts.post_excerpt LIKE %s",
					$like_variant,
					$like_variant,
					$like_variant
				) . ')';
				$or2 = ' OR ';
			}
			$sql .= ' ) ';
			$and1 = ' AND ';
		}

		if (empty($sql)) {
			return $where;
		}
		return preg_replace('/' . preg_quote($original, '/') . '/', $sql, $where, 1);
	}
}

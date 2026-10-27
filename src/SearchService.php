<?php

declare(strict_types=1);

namespace Wpcc;

class SearchService {
	public static function apply_filter_search_rule(): void {
		add_filter('posts_search', array(self::class, 'filter_search_rule'), 20, 2);
	}

	public static function filter_search_rule(string $search, $query = null): string {
		global $wpdb;
		if ($query === null) {
			global $wp_query;
			$query = $wp_query;
		}
		if (!$query || $search === '' || empty($query->query_vars['search_terms'])) {
			return $search;
		}
		$wildcard = empty($query->query_vars['exact']) ? '%' : '';
		$exclusion = apply_filters('wp_query_search_exclusion_prefix', '-');
		$replacements = array();
		foreach ($query->query_vars['search_terms'] as $term) {
			if (!is_string($term) || !preg_match('/\p{Han}/u', $term)) {
				continue;
			}
			$exclude = $exclusion && str_starts_with($term, $exclusion);
			if ($exclude) {
				$term = substr($term, strlen($exclusion));
			}
			$variants = ConversionService::zhconversion_all($term);
			if (!$variants) {
				continue;
			}
			array_unshift($variants, $term);
			$operator = $exclude ? 'NOT LIKE' : 'LIKE';
			$join = $exclude ? ' AND ' : ' OR ';
			foreach (array("$wpdb->posts.post_title", "$wpdb->posts.post_excerpt", "$wpdb->posts.post_content", 'sq1.meta_value') as $column) {
				$original = $wpdb->prepare("($column $operator %s)", $wildcard . $wpdb->esc_like($term) . $wildcard);
				$parts = array();
				foreach ($variants as $variant) {
					$parts[] = $wpdb->prepare("($column $operator %s)", $wildcard . $wpdb->esc_like($variant) . $wildcard);
				}
				$replacements[$original] = '(' . implode($join, $parts) . ')';
			}
		}
		return strtr($search, $replacements);
	}
}

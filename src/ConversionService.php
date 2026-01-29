<?php

declare(strict_types=1);

namespace Wpcc;

class ConversionService {
	public static function zhconversion($str, $variant = null): string {
		$langs = State::langs();
		if ($variant === null) {
			$variant = \Wpcc\Context::targetLang();
		}
		if ($variant === false || $variant === null) {
			return $str;
		}

		wpcc_load_conversion_table();
		if (!isset($langs[$variant][0])) {
			return $str;
		}
		return $langs[$variant][0]($str);
	}

	public static function zhconversion2($str, $variant = null): string {
		$langs = State::langs();
		if ($variant === null) {
			$variant = \Wpcc\Context::targetLang();
		}
		if ($variant === false || $variant === null) {
			return $str;
		}

		wpcc_load_conversion_table();
		if (!isset($langs[$variant][0])) {
			return $str;
		}
		return self::limit_zhconversion($str, $langs[$variant][0]);
	}

	public static function wpcc_no_conversion_filter($str): string {
		$wpcc_options = State::options();

		if (empty($wpcc_options['wpcc_no_conversion_ja']) && empty($wpcc_options['wpcc_no_conversion_tag'])) {
			return $str;
		}

		$tags = array();
		if (!empty($wpcc_options['wpcc_no_conversion_tag'])) {
			$raw = str_replace('|', ',', $wpcc_options['wpcc_no_conversion_tag']);
			foreach (explode(',', $raw) as $tag) {
				$tag = strtolower(trim($tag));
				if ($tag !== '' && preg_match('/^[a-z][a-z0-9]*$/', $tag)) {
					$tags[] = $tag;
				}
			}
		}

		$previous = libxml_use_internal_errors(true);
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$wrapped = '<div id="wpcc-root">' . $str . '</div>';
		if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
			return $str;
		}
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$xpath = new \DOMXPath($dom);
		$nodes = array();

		if (!empty($wpcc_options['wpcc_no_conversion_ja'])) {
			foreach ($xpath->query('//*[@lang="ja"]') as $node) {
				$nodes[spl_object_hash($node)] = $node;
			}
		}

		foreach ($tags as $tag) {
			foreach ($xpath->query('//' . $tag) as $node) {
				$nodes[spl_object_hash($node)] = $node;
			}
		}

		if (empty($nodes)) {
			return $str;
		}

		foreach ($nodes as $node) {
			$inner = '';
			foreach ($node->childNodes as $child) {
				$inner .= $dom->saveHTML($child);
			}
			$id = wpcc_id();
			while ($node->firstChild) {
				$node->removeChild($node->firstChild);
			}
			$wrapper = $dom->createDocumentFragment();
			$wrapper->appendXML('<!--WPCC_NC' . $id . '_START-->' . $inner . '<!--WPCC_NC' . $id . '_END-->');
			$node->appendChild($wrapper);
		}

		$root = $dom->getElementById('wpcc-root');
		if (!$root) {
			return $str;
		}
		$output = '';
		foreach ($root->childNodes as $child) {
			$output .= $dom->saveHTML($child);
		}

		return $output;
	}

	public static function zhconversion_safe($str, $variant = null): string {
		wpcc_load_conversion_table();
		return self::zhconversion($str, $variant);
	}

	public static function zhconversion_all($str, array $langs = array('zh-hans', 'zh-hant')): array {
		$wpcc_langs = State::langs();
		wpcc_load_conversion_table();
		$return = array();
		foreach ($langs as $value) {
			$tmp = $wpcc_langs[$value][0]($str);
			if ($tmp !== $str) {
				$return[] = $tmp;
			}
		}
		return array_unique($return);
	}

	public static function zhconversion_deep($value) {
		return is_array($value) ? array_map(array(self::class, 'zhconversion_deep'), $value) : self::zhconversion($value);
	}

	public static function limit_zhconversion($str, callable $function): string {
		if ($m = preg_split('/(<!--WPCC_NC(\d*)_START-->)(.*?)(<!--WPCC_NC\2_END-->)/s', $str, -1, PREG_SPLIT_DELIM_CAPTURE)) {
			$r = '';
			$count = 0;
			foreach ($m as $v) {
				$count++;
				if ($count % 5 == 1) {
					$r .= $function($v);
				} else if ($count % 5 == 4) {
					$r .= $v;
				}
			}
			return $r;
		}

		return $function($str);
	}
}

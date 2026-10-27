<?php

declare(strict_types=1);

namespace Wpcc;

class HtmlConverter extends \WP_HTML_Tag_Processor {
	public static function convert_deep($value) {
		if (is_array($value)) {
			return array_map(array(self::class, 'convert_deep'), $value);
		}
		return is_string($value) ? self::convert($value) : $value;
	}

	public static function convert($html, $variant = null, bool $convert_home_links = false): string {
		$variant ??= Context::targetLang();
		if (!is_string($variant) || !isset(Config::langs()[$variant])) {
			return (string) $html;
		}
		return ConversionService::limit_zhconversion((string) $html, static function(string $fragment) use ($variant, $convert_home_links): string {
			$processor = new \WP_HTML_Tag_Processor($fragment);
			while ($processor->next_token()) {
				$tag = $processor->get_tag();
				if ($processor->get_token_type() === '#text' || in_array($tag, array('TITLE', 'TEXTAREA'), true)) {
					$text = $processor->get_modifiable_text();
					$converted = ConversionService::zhconversion($text, $variant);
					if ($text !== $converted) {
						$processor->set_modifiable_text($converted);
					}
				}
				if ($tag === null || $processor->is_tag_closer()) {
					continue;
				}
				$attributes = array('alt', 'title', 'placeholder', 'aria-label', 'aria-description');
				if ($tag === 'INPUT' && in_array(strtolower((string) $processor->get_attribute('type')), array('submit', 'reset', 'button'), true)) {
					$attributes[] = 'value';
				}
				if ($tag === 'META') {
					$name = $processor->get_attribute('name') ?: $processor->get_attribute('property');
					if (in_array(strtolower((string) $name), array('description', 'keywords', 'og:title', 'og:description', 'og:site_name', 'twitter:title', 'twitter:description'), true)) {
						$attributes[] = 'content';
					}
				}
				foreach ($attributes as $attribute) {
					$text = $processor->get_attribute($attribute);
					if (is_string($text)) {
						$converted = ConversionService::zhconversion($text, $variant);
						if ($text !== $converted) {
							$processor->set_attribute($attribute, $converted);
						}
					}
				}
				if ($convert_home_links && $tag === 'A' && !$processor->has_class('wpcc_link')) {
					$href = $processor->get_attribute('href');
					if (is_string($href) && untrailingslashit($href) === untrailingslashit(home_url('/'))) {
						$processor->set_attribute('href', LinkService::link_conversion_auto(home_url('/')));
					}
				}
			}
			return $processor->get_updated_html();
		});
	}

	public static function protect(string $html, array $tags, bool $japanese = false): string {
		$processor = new self($html);
		$tags = array_map('strtoupper', $tags);
		$void = array('AREA', 'BASE', 'BR', 'COL', 'EMBED', 'HR', 'IMG', 'INPUT', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR');
		$atomic = array('SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE', 'IFRAME', 'NOEMBED', 'NOFRAMES', 'XMP');
		$ranges = array();
		$start = null;
		$tag = null;
		$depth = 0;
		while ($processor->next_token()) {
			$current = $processor->get_tag();
			if ($current === null) {
				continue;
			}
			$processor->set_bookmark('wpcc-token');
			$span = $processor->bookmarks['wpcc-token'];
			if ($start !== null) {
				if ($current === $tag) {
					$depth += $processor->is_tag_closer() ? -1 : 1;
					if ($depth === 0) {
						$ranges[] = array($start, $span->start + $span->length);
						$start = null;
					}
				}
				continue;
			}
			$lang = $processor->get_attribute('lang');
			$protect = in_array($current, $tags, true) || ($japanese && is_string($lang) && preg_match('/^ja(?:-|$)/i', $lang));
			if ($processor->is_tag_closer() || !$protect) {
				continue;
			}
			if (in_array($current, $void, true) || in_array($current, $atomic, true)) {
				$ranges[] = array($span->start, $span->start + $span->length);
			} else {
				$start = $span->start;
				$tag = $current;
				$depth = 1;
			}
		}
		if ($start !== null) {
			$ranges[] = array($start, strlen($html));
		}
		foreach (array_reverse($ranges) as [$start, $end]) {
			$id = wpcc_id();
			$html = substr_replace($html, '<!--WPCC_NC' . $id . '_END-->', $end, 0);
			$html = substr_replace($html, '<!--WPCC_NC' . $id . '_START-->', $start, 0);
		}
		return $html;
	}
}

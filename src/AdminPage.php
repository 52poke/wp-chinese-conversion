<?php
class Wpcc_Admin {
	public $base = '';
	public $is_submitted = false;
	public $is_success = false;
	public $is_error = false;
	public $message = '';
	public $options = false;
	public $langs = false;
	public $url = '';
	public $admin_lang = false;

	public function __construct() {
		$wpcc_options = \Wpcc\State::options();
		$wpcc_langs = \Wpcc\State::langs();
		$locale = str_replace('_', '-', strtolower(get_locale()));
		if (empty($wpcc_options)) {
			$wpcc_options = get_option('wpcc_options');
		}
		if (!is_array($wpcc_options)) {
			$wpcc_options = wpcc_normalize_options(array());
			update_option('wpcc_options', $wpcc_options);
		} else {
			$wpcc_options = wpcc_normalize_options($wpcc_options);
		}
		\Wpcc\State::setOptions($wpcc_options);
		$this->langs = $wpcc_langs;
		$this->options = $wpcc_options;

		$variant = !empty($_GET['variant']) ? sanitize_key(wp_unslash($_GET['variant'])) : '';
		if( $variant && in_array($variant, array_keys($this->langs), true) )
			$this->admin_lang = $variant;
		else if( in_array($locale, array_keys($this->langs), true) )
			$this->admin_lang = $locale;
		$plugin_file = dirname(__DIR__) . '/wp-chinese-conversion.php';
		$this->base = str_replace(basename($plugin_file), "", plugin_basename($plugin_file));
		$this->url = admin_url('options-general.php?page=' . $this->base . 'wp-chinese-conversion.php');
		add_filter('plugin_action_links', array($this, 'action_links'), 10, 2);
		if (function_exists('add_options_page')) {
			add_options_page('WP Chinese Conversion', 'Chinese Conversion', 'manage_options', $this->base . 'wp-chinese-conversion.php', array($this, 'display_options'));
		}

		wp_enqueue_script('jquery');
	}

	public function action_links($links, $file) {
		if ($file == $this->base . 'wp-chinese-conversion.php')
			$links[] = '<a href="' . esc_url(admin_url('options-general.php?page=' . $file)) . '" title="Change Settings">Settings</a>';
		return $links;
	}

	public function display_options() {
		global $wp_rewrite;

		if( !empty($_POST['wpcco_uninstall_confirm']) ) {
			check_admin_referer('wpcc_uninstall', 'wpcc_uninstall_nonce');
			delete_option('wpcc_options');
			flush_rewrite_rules();
			echo '<div class="wrap"><h2>WP Chinese Conversion Setting</h2><div class="updated">Uninstall Successfully. 卸载成功, 現在您可以到<a href="plugins.php">插件菜单</a>里禁用本插件.</div></div>';
			return;
		} else if($this->options === false) {
			echo '<div class="wrap"><h2>WP Chinese Conversion Setting</h2><div class="error">错误: 没有找到配置信息, 可能由于Wordpress系统错误或者您已经卸载了本插件. 您可以<a href="plugins.php">尝试</a>禁用本插件后再重新激活.</div></div>';
			return;
		}

		if(!empty($_POST['wpcco_submitted'])) {
			check_admin_referer('wpcc_options_save', 'wpcc_options_nonce');
			$this->is_submitted = true;
			$this->process();
		}
?>
<script type="text/javascript">
//<!--
	function toggleVisibility(id) {
		var e = document.getElementById(id);
		if( !e ) return;
		if(e.style.display == "block")
			e.style.display = "none";
		else
			e.style.display = "block";
		return false;
	}
//-->
</script>
<div class="wrap"><div style="padding: 2px 5px 0 0;">Select Admin Language: <?php echo $this->navi(); ?></div>
<h2>WP Chinese Conversion Settings</h2>
<?php ob_start(); ?>
<?php if($this->is_submitted && $this->is_success) { ?>
	<div class="updated fade" style=""><p><?php echo $this->message; ?></p></div>
<?php } else if($this->is_submitted && $this->is_error) { ?>
	<div class="error" style=""><p><?php echo $this->message; ?></p></div>
<?php } ?>
<p>版本 <?php echo WPCC_VERSION; ?>. Originally developed by <a href="https://oogami.name/" title="小野大神" target="_blank" >oogami.name</a>. This fork is maintained by <a href="https://mudkip.me">mudkipme</a>.</p>
<div style="padding-top:20px;padding-bottom:20px;"><b>单击选项名查看帮助!</b></div>
<form id="wpcco_form" method="post" action="<?php echo esc_url($this->url); ?>" ><input type="hidden" name="wpcco_submitted" value="1" />
<?php wp_nonce_field('wpcc_options_save', 'wpcc_options_nonce'); ?>
<table class="form-table"><tbody>

<tr>
<td valign="top" width="30%"><a title="单击查看帮助" style="cursor: pointer;" onclick="toggleVisibility('wpcc_help_nt');">自定义"不转换"标签名: </a></td>
<td><!--WPCC_NC_START-->
<input type="text" style="width: 100px;" name="wpcco_no_conversion_tip" id="wpcco_no_conversion_tip" value="<?php echo esc_html($this->options['nctip']); ?>" /><!--WPCC_NC_END-->
<div id="wpcc_help_nt" style="display: none;">本插件输出的widget中将包含当前页面原始版本链接, 你可以在这里自定义其显示的名称. 如果留空则使用默认的"不转换".</div>
</td>
</tr>

<tr>
<td valign="top" width="30%"><a title="单击查看帮助" style="cursor: pointer;" onclick="toggleVisibility('wpcc_help_uls');">对下面几种中文开启转换功能:</a></td>
<td><!--WPCC_NC_START-->
<?php foreach ($this->langs as $key => $value) { ?>
	<input type="checkbox" id="wpcco_variant_<?php echo $key; ?>" name="wpcco_variant_<?php echo $key; ?>"<?php echo in_array($key, $this->options['wpcc_used_langs']) ? ' checked="checked"' : ''; ?> />
	<label for="wpcco_variant_<?php echo $key; ?>"><?php $str = $value[2] . ' (' . $key . ')'; $str_len = function_exists('mb_strlen') ? mb_strlen($str) : strlen($str); echo str_replace(' ', '&nbsp;', str_pad($str, 14 + strlen($str) - $str_len));?></label>
	<input type="text" style="width: 100px;" name="<?php echo $this->langs[$key][1]; ?>" value="<?php echo !empty($this->options[$value[1]]) ? esc_html($this->options[$value[1]]) : ''; ?>" /><br />
<?php } ?><!--WPCC_NC_END-->
<div id="wpcc_help_uls" style="display: none;">未选中的中文语言将不被使用,此项设置为全局设置.您应该选中至少一种中文语言,否则本插件全部功能都不会工作.
在每个复选框后的文本输入框里可以输入该语言自定义名称, 如果留空会使用默认值. <!--WPCC_NC_START-->("简体中文", "繁體中文"...)<!--WPCC_NC_END--></div>
</td>
</tr>

<tr>
<td scope="row" valign="top" width="30%" ><a title="单击查看帮助" style="cursor: pointer;" onclick="toggleVisibility('wpcc_help_sc');" >中文搜索关键词简繁转换: </a></td>
<td>
<select id="wpcco_search_conversion" value="" name="wpcco_search_conversion" style="width: 250px;">
<option value="2"<?php echo $this->options['wpcc_search_conversion'] == 2 ? ' selected="selected"' : ''; ?>>开启</option>
<option value="0"<?php echo ( $this->options['wpcc_search_conversion'] != 2 && $this->options['wpcc_search_conversion'] != 1 ) ? ' selected="selected"' : ''; ?>>关闭</option>
<option value="1"<?php echo $this->options['wpcc_search_conversion'] == 1 ? ' selected="selected"' : ''; ?>>仅当中文语言不是"不转换"时开启(默认值)</option>
</select>
<div id="wpcc_help_sc" style="display: none;">本选项将增强Wordpress搜索功能, 使其对中文关键词繁简统一处理.
例如搜索"<!--WPCC_NC_START--><code>网络</code><!--WPCC_NC_END-->"时, 数据库里含有"<!--WPCC_NC_START--><code>网络</code><!--WPCC_NC_END-->",
"<!--WPCC_NC_START--><code >網路</code><!--WPCC_NC_END-->" 和"<!--WPCC_NC_START--><code >網絡</code><!--WPCC_NC_END-->"的文章都会放到搜索结果里返回.
支持多个中文词语搜索, 如搜索"<!--WPCC_NC_START--><code>简体 繁體</code><!--WPCC_NC_END-->"时,
含有"<!--WPCC_NC_START--><code>简体</code><!--WPCC_NC_END-->"和"<!--WPCC_NC_START--><code>繁體</code><!--WPCC_NC_END-->"两个词的文章也会被返回.
(此功能将增加搜索时数据库负担)</div>
</td>
</tr>

<tr>
<td valign="top" width="30%"><a title="单击查看帮助" style="cursor: pointer;" onclick="toggleVisibility('wpcc_help_ua');">识别浏览器中文语言动作:</a></td>
<td>
<select id="wpcco_browser_redirect" value="" name="wpcco_browser_redirect" style="width: 250px;">
<option value="2"<?php echo $this->options['wpcc_browser_redirect'] == 2 ? ' selected="selected"' : ''; ?>>直接显示对应繁简版本内容</option>
<option value="1"<?php echo $this->options['wpcc_browser_redirect'] == 1 ? ' selected="selected"' : ''; ?>>跳转到对应繁简版本页面</option>
<option value="0"<?php echo $this->options['wpcc_browser_redirect'] == 0 ? ' selected="selected"' : ''; ?>>关闭此功能(默认值)</option>
</select>
<input type="hidden" name="wpcco_auto_language_recong" value="0" />
<div id="wpcc_help_ua" style="display: none;"><b>本项设置不会应用于搜索引擎.</b> 如果本选项设置不为"关闭", 程序将识别访客浏览器首选中文语言.
如果设置为"跳转到对应繁简版本页面", 程序将302重定向到当前页面的访客浏览器首选语言版本.
如果设置为"直接显示对应繁简版本内容",程序将直接显示对应中文转换版本内容而不进行重定向. <b>如果本选项设置为"直接显示对应繁简版本内容",
必须把下一个选项"使用Cookie保存并识别用户语言偏好"关闭或也设置为直接显示对应繁简版本,否则本插件只会在浏览器第一次访问时直接显示,
其他情况跳转.</b>.<br /></div>
</td>
</tr>

<tr><td valign="top" width="30%"><a title="单击查看帮助" style="cursor: pointer;" onclick="toggleVisibility('wpcc_help_co');">使用Cookie保存并识别用户语言偏好:</a></td>
<td>
<select id="wpcco_use_cookie_variant" value="" name="wpcco_use_cookie_variant" style="width: 250px;">';
<option value="2"<?php echo $this->options['wpcc_use_cookie_variant'] == 2 ? ' selected="selected"' : ''; ?>>直接显示对应繁简版本内容</option>
<option value="1"<?php echo $this->options['wpcc_use_cookie_variant'] == 1 ? ' selected="selected"' : ''; ?>>跳转到对应繁简版本页面</option>
<option value="0"<?php echo $this->options['wpcc_use_cookie_variant'] == 0 ? ' selected="selected"' : ''; ?>>关闭此功能(默认值)</option>
</select>
<div id="wpcc_help_co" style="display: none;"><b>本项设置不会应用于搜索引擎.</b> 如果开启这项设置,本插件将自动保存访客的语言选择.举例而言,
当用户通过 "<?php echo $this->options['wpcc_use_permalink'] ?
	esc_html(trailingslashit(wpcc_link_conversion(home_url('/'), 'zh-hant'))) :
	esc_html(wpcc_link_conversion(home_url('/'), 'zh-hant')); ?>"
这个链接访问了你博客的繁體版本时,程序将保存信息到Cookie中. 如果该用户重启浏览器并通过 "<?php echo esc_html(home_url('/')); ?>" 再次访问你博客时,
则会被自动跳转到繁體版本的地址. 如果设置为"直接显示对应繁简版本",则不会进行跳转.
(参见上一项的说明)<br /></div>
</td>
</tr>

<tr>
<td valign="top" width="30%"><a title="单击查看帮助" style="cursor: pointer;" onclick="toggleVisibility('wpcc_help_nc');">不转换文章中某些HTML标签里中文:</a></td>
<td>
<input type="text" value="<?php echo esc_attr($this->options['wpcc_no_conversion_tag']); ?>" style="width: 250px;" name="wpcco_no_conversion_tag" id="wpcco_no_conversion_tag" /> (默认空)
<div id="wpcc_help_nc" style="display: none;">这里输入的HTML标签里内容将不进行中文繁简转换(仅适用文章内容), 保持原样输出. 请输入HTML标签名, 如<code>pre</code>;
多个HTML标签之间以 <code>,</code> 分割, 如 <code>pre,code</code>. 仅支持标签名筛选, 不支持CSS选择器. 如果遇到html错误, 请关闭此选项.</div>
</td>
</tr>

<tr>
<td valign="top" width="30%"><a title="单击查看帮助" style="cursor: pointer;" onclick="toggleVisibility('wpcc_help_nc_ja');">不转换日语(lang="ja")的HTML标签里内容:</a></td>
<td>
<input type="checkbox" name="wpcco_no_conversion_ja" id="wpcco_no_conversion_ja" <?php echo !empty($this->options['wpcc_no_conversion_ja']) ? ' checked="checked"' : ''; ?> />
<label for="wpcco_no_conversion_ja">(默认关闭)</label>
<div id="wpcc_help_nc_ja" style="display: none;">如果选中此选项, 文章内容中用 lang="ja" 标记为日本语的html tag将不进行繁简转换, 保持原样输出.
例如: "<!--WPCC_NC_START--><code lang="ja">&lt;span lang="ja"&gt;あなたを、お連れしましょうか？ この町の願いが叶う場所に。&lt;/span&gt;</code><!--WPCC_NC_END-->"
中的CJK汉字<!--WPCC_NC_START--><code lang="ja">連</code><!--WPCC_NC_END-->和<!--WPCC_NC_START--><code lang="ja">叶</code><!--WPCC_NC_END-->将不会进行繁简转换.
如果遇到html错误, 请关闭此选项. </div>
</td>
</tr>

<tr>
<td valign="top" width="30%"><a title="单击查看帮助" style="cursor: pointer;" onclick="toggleVisibility('wpcc_no_conversion_qtag');">不转换HTML中任意內容TAG:</a></td>
<td>
	<!--WPCC_NC_START--><code>&lt;!--WPCC_NC_START--&gt;爱与正义, 剑與魔法, 光榮與夢想&lt;!--WPCC_NC_END--&gt;</code><!--WPCC_NC_END--><br />
	<input type="checkbox" name="wpcco_no_conversion_qtag" id="wpcco_no_conversion_qtag" <?php echo !empty($this->options['wpcc_no_conversion_qtag']) ? ' checked="checked"' : ''; ?> />
<label for="wpcco_no_conversion_qtag">在Wordpress文章html编辑器中添加"不转换中文"的Quick tag</label>
<div id="wpcc_no_conversion_qtag" style="display: none;">
HTML中所有位于 <code>&lt;!--WPCC_NC_START--&gt;</code> 和 <code>&lt;!--WPCC_NC_END--&gt;</code>之间的内容将不会进行繁简转换, 保持原样输出.
你可以在模板或post内容中使用这个标签.<br />你可以选择在Wordpress的文章编辑器(html模式)工具栏中插入一个按钮(显示为"WPCC_NC"), 方便快速在文章中插入这个标签.
</div>
</td>
</tr>

<tr>
<td valign="top" width="30%"><a title="单击查看帮助" style="cursor: pointer;" onclick="toggleVisibility('wpcc_help_pl');">繁简转换页面永久链接格式:</a></td><td>
<label><input type="radio" name="wpcco_use_permalink" value="0"<?php echo $this->options['wpcc_use_permalink'] == 0 ? ' checked="checked"' : ''; ?> /> <code><?php echo home_url('') . (empty($wp_rewrite->permalink_structure) ? '/?p=123&variant=zh-hant' : $wp_rewrite->permalink_structure . '?variant=zh-hant'); ?></code> (默认)</label><br />
<label><input type="radio" name="wpcco_use_permalink" value="1"<?php echo empty($wp_rewrite->permalink_structure) ? ' disabled="disabled"' : ''; ?><?php echo $this->options['wpcc_use_permalink'] == 1 ? ' checked="checked"' : ''; ?> /> <code><?php echo home_url('') . user_trailingslashit(trailingslashit($wp_rewrite->permalink_structure) . 'zh-hant') . ( empty($wp_rewrite->permalink_structure) ? '/' : '' ); ?></code></label><br />
<label><input type="radio" name="wpcco_use_permalink" value="2"<?php echo empty($wp_rewrite->permalink_structure) ? ' disabled="disabled"' : ''; ?><?php echo $this->options['wpcc_use_permalink'] == 2 ? ' checked="checked"' : ''; ?> /> <code><?php echo home_url('') . '/zh-hant' . $wp_rewrite->permalink_structure . ( empty($wp_rewrite->permalink_structure) ? '/' : '' ); ?></code></label><br />
<div id="wpcc_help_pl" style="display: none;">更改此项设置前,<b>请仔细阅读下面的说明:</b><br />本项设置决定插件生成的繁简转换页面Permalink.
默认的形式为您原始Permalink后加上?variant=zh-hant参数.(zh-hant为当前请求的语言代码) .你可以修改这个permalink形式.本插件提供两种非默认的Permalink格式:
您原始的Permalink后加上/zh-hant 或/zh-hant/; 或/zh-hant后加上您原来Permalink. 两种区别在于中文语言代码(zh-hant)附加在您原来Permalink的末尾或开头.
URL末尾是否有 / 取决于您的Wordpress永久链接末尾是否有/. 但<b>首页的繁简转换版本URL末尾永远有 "/"</b> . 如果您的Wordpress未开启永久链接,
本项设置只能选择第一种URL形式. </div>
</td>
</tr>

<tr>
<td valign="top" width="30%"><a title="单击查看帮助" style="cursor: pointer;" onclick="toggleVisibility('wpcc_help_ob');">对页面内容整体转换:</a></td>
<td>
<input type="checkbox" id="wpcco_use_fullpage_conversion" name="wpcco_use_fullpage_conversion"<?php echo $this->options['wpcc_use_fullpage_conversion'] == 1 ? ' checked="checked"' : ''; ?> /> <label for="wpcco_use_fullpage_conversion">(默认开启)</label>
<div id="wpcc_help_ob" style="display: none;">
开启此选项后,插件将对Wordpress输出的全部页面内容进行中文整体转换(使用ob_start和ob_flush函数),
这将极大提高页面生成速度并减少资源使用.但也可能造成意想不到问题.如果遇到异常(包括中文转换错误, HTML页面错误或php错误等),请关闭此选项.</div>
</td>
</tr>

<tr><td><input class="button" type="submit" name="submit" value="保存选项" /></td></tr>
</tbody></table></form>
<div style="padding-top: 30px;padding-bottom: 20px;"><b>卸载本插件:</b></div>
<form id="wpcco_uninstall_form" method="post" action="<?php echo esc_url($this->url); ?>">
<?php wp_nonce_field('wpcc_uninstall', 'wpcc_uninstall_nonce'); ?>
<table class="form-table"><tbody>
<tr>
<td valign="top" width="30%"><a title="单击查看帮助" style="cursor: pointer;" onclick="toggleVisibility('wpcc_help_uninstall');">确定卸载本插件?</a></td>
<td>
<input type="checkbox" name="wpcco_uninstall_confirm" id="wpcco_uninstall_confirm" value="1" /> <label for="wpcco_uninstall_confirm">确认卸载 (此操作不可逆)</label>
<div id="wpcc_help_uninstall" style="display: none;">这将清除数据库options表中本插件的设置项(键值为wpcc_options), 提交后还需要到wordpress插件管理菜单里禁用本插件.</div>
</td>
</tr>
<tr>
<td>
<input class="button" type="submit" name="submit" value="卸载插件" />
</td>
</tr>
</tbody></table>
</form>
</div> <!-- close wrap div -->
<?php
		$o = ob_get_clean();
		if($this->admin_lang) {
			wpcc_load_conversion_table();
			$o = limit_zhconversion($o, $this->langs[$this->admin_lang][0]);
		}
		echo $o;
	}

	public function navi() {
		$variant = !empty($_GET['variant']) ? sanitize_key(wp_unslash($_GET['variant'])) : '';
		$str = '<span><a title="默认/ 默認" href="' . esc_url($this->url) . '" ' . ( !$variant ? 'style="color: #464646; text-decoration: none !important;"' : '' ) . ' >默认/ 默認</a></span>&nbsp;';
		if(!$this->options['wpcc_used_langs']) return $str;
			foreach ($this->langs as $key => $value) {
			$str .= '<span><a href="' . esc_url($this->url . '&variant=' . $key) . '" title="' . esc_attr($value[2]) . '" ' . ( $variant == $key ? 'style="color: #464646; text-decoration: none !important;"' : '' ) . '>' . esc_html($value[2]) . '</a>&nbsp;</span>';
		}
		return $str;
	}

	public function process() {
		global $wp_rewrite;
		$post = wp_unslash($_POST);
		$langs = array();
		foreach ($this->langs as $key => $value) {
			if(isset($post[ 'wpcco_variant_' . $key ]))
				$langs[]=$key;
		}
		$options = array(
			'wpcc_used_langs' => $langs,
			'wpcc_search_conversion' => isset($post['wpcco_search_conversion']) ? intval($post['wpcco_search_conversion']) : 0,
			'wpcc_browser_redirect' => isset($post['wpcco_browser_redirect']) ? intval($post['wpcco_browser_redirect']) : 0,
			'wpcc_use_cookie_variant' => isset($post['wpcco_use_cookie_variant']) ? intval($post['wpcco_use_cookie_variant']) : 0,
			'wpcc_use_fullpage_conversion' => ( isset($post['wpcco_use_fullpage_conversion']) ? 1 : 0 ),
			'wpcc_use_permalink' => isset($post['wpcco_use_permalink']) ? intval($post['wpcco_use_permalink']) : 0,
			'wpcc_auto_language_recong' => 0,
			'wpcc_no_conversion_tag' => trim(sanitize_text_field(isset($post['wpcco_no_conversion_tag']) ? $post['wpcco_no_conversion_tag'] : ''), " \t\n\r\0\x0B,|"),
			'wpcc_no_conversion_ja' => ( isset($post['wpcco_no_conversion_ja']) ? 1 : 0 ),
			'wpcc_no_conversion_qtag' => ( isset($post['wpcco_no_conversion_qtag']) ? 1 : 0 ),
			'nctip' => trim(sanitize_text_field(isset($post['wpcco_no_conversion_tip']) ? $post['wpcco_no_conversion_tip'] : '')),
		);

		foreach( $this->langs as $lang => $value ) {
			if( !empty( $post[$value[1]] ) )
				$options[$value[1]] = trim(sanitize_text_field($post[$value[1]]));
		}

		$options = wpcc_normalize_options($options);
		\Wpcc\State::setOptions($options);
		if( $this->options['wpcc_use_permalink'] != $options['wpcc_use_permalink'] ||
			( $this->options['wpcc_use_permalink'] != 0 && $this->options['wpcc_used_langs'] != $options['wpcc_used_langs'] )
		) {
			if( !has_filter('rewrite_rules_array', 'wpcc_rewrite_rules') )
				add_filter('rewrite_rules_array', 'wpcc_rewrite_rules');
			$wp_rewrite->flush_rules();
		}

		update_option('wpcc_options', $options);

		$this->options=$options;
		$this->is_success = true;
		$this->message .= '<br />设置已更新。';
	}

}

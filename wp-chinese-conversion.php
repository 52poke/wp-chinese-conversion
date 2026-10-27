<?php
/*
Plugin Name: WP Chinese Conversion
Plugin URI: https://oogami.name/project/wpcc/
Description: Adds the language conversion function between Chinese Simplified and Chinese Traditional to your WP Blog.
Version: 2.0.1
Requires at least: 6.9
Requires PHP: 8.3
License: GPL-3.0-or-later
Author: Ono Oogami
Author URI: https://oogami.name/
*/

/*
Copyright (C) 2009-2013 Ono Oogami, https://oogami.name/ (ono@oogami.name)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

defined('ABSPATH') || exit;

define('WPCC_ROOT_URL', plugin_dir_url(__FILE__));
define('WPCC_VERSION', '2.0.1');

require_once __DIR__ . '/src/Plugin.php';
\Wpcc\Plugin::bootstrap();
\Wpcc\Plugin::register();

register_activation_hook(__FILE__, 'wpcc_activate');
register_deactivation_hook(__FILE__, array(\Wpcc\Plugin::class, 'deactivate'));

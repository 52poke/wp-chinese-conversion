<?php

class Wpcc_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct('widget_wpcc', 'Chinese Conversion', array('classname' => 'widget_wpcc', 'description' =>'Chinese Conversion Widget'));
	}

	public function widget($args, $instance) {
		$title = isset($instance['title']) ? apply_filters('widget_title', $instance['title']) : '';
		$before_widget = isset($args['before_widget']) ? $args['before_widget'] : '';
		$before_title = isset($args['before_title']) ? $args['before_title'] : '';
		$after_title = isset($args['after_title']) ? $args['after_title'] : '';
		$after_widget = isset($args['after_widget']) ? $args['after_widget'] : '';
		echo $before_widget;
		if ($title) {
			echo $before_title . $title . $after_title;
		}
		wpcc_output_navi( isset($instance['args']) ? $instance['args'] : '' );
		echo $after_widget;
	}

	public function update($new_instance, $old_instance) {
		return array(
			'title' => isset($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '',
			'args' => isset($new_instance['args']) ? sanitize_text_field($new_instance['args']) : '',
		);
	}

	public function form($instance) {
		$title = isset($instance['title']) ? esc_attr($instance['title']) : '';
		$args = isset($instance['args']) ? esc_attr($instance['args']) : '';
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label>
			<label for="<?php echo $this->get_field_id('args'); ?>">Args: <input class="widefat" id="<?php echo $this->get_field_id('args'); ?>" name="<?php echo $this->get_field_name('args'); ?>" type="text" value="<?php echo $args; ?>" /></label>
		</p>
		<?php
	}
}

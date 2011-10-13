<?php
	if (empty($log)) {
		echo '<p>'.__('Aggregation has not been run for this post yet.', 'social').'</p>';
	}
	else {
		$i = 0;
		$output = '';
		$log = array_reverse($log, 1);
		foreach ($log as $timestamp => $_log) {
			++$i;

			$output .= '<h5 id="log-'.$i.'">'.date(get_option('date_format').' '.get_option('time_format'), ($timestamp + (get_option('gmt_offset') * 3600))).' (';
			if (isset($_log->manual) and $_log->manual) { // isset() check for legacy support
				$output .= __('Manual Aggregation', 'social');
			}
			else {
				$output .= __('Automatic Aggregation', 'social');
			}
			$output .= ')</h5><ul id="log-'.$i.'-output" class="parent">';

			if (isset($_log->items) and count($_log->items)) {
				foreach ($_log->items as $service => $items) {
					if (isset($services[$service])) {
						$service = $services[$service];

						$output .= '<li>'.esc_html($service->title()).':<ul>';

						if (count($items)) {
							$_items = array();
							foreach ($items as $item) {
								if (!isset($_items[$item->type])) {
									$_items[$item->type] = array();
								}

								$_items[$item->type][] = $item;
							}

							foreach ($_items as $type => $items) {
								foreach ($items as $item) {
									$username = '';
									if (isset($item->data['username'])) {
										$username = $item->data['username'];
									}

									$id = $item->id;
									if (isset($item->data['parent_id'])) {
										$id = $item->data['parent_id'];
										$ids = explode('_', $item->data['parent_id']);
										$item->id = $id.'#'.$ids[1];
									}

									$output .= '<li>';
									$content = $service->aggregation_row($type, $item, $username, $id);
									if (empty($content)) {
										$link = $service->status_url($username, $id);
										$output .= '<a href="'.esc_url($link).'" target="_blank">#'.$item->id.'</a>';
										switch ($type) {
											case 'reply':
												$output .= ' ('.__('Reply Search', 'social').')';
											break;
											case 'url':
												$output .= ' ('.__('URL Search', 'social').')';
											break;
											default:
												$output .= ' ('.__(esc_html($type), 'social').')';
											break;
										}

										if ($item->ignored) {
											$output .= ' ('.__('Existing Comment', 'social').')';
										}
									}
									else {
										$output .= $content;
									}

									$output .= '</li>';
								}
							}
						}

						$output .= '</ul></li>';
					}
				}
			}
			else {
				$output .= '<li style="list-style:none"><p>'.__('No results found.', 'social').'</p></li>';
			}
			$output .= '</ul>';
		}

		echo $output;
	}

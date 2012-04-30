<?php

// expects the following variables to be passed in:
// $service (current service)
// $account (account this will be broadcast to)
// $post (the WordPress post object)

setup_postdata($post);
$url_parts = parse_url(home_url());

$thumbnail = $thumbnail_class = '';
if (function_exists('has_post_thumbnail') and has_post_thumbnail($post->ID)) {
	$image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'thumbnail');
	$thumbnail = '<img src="'.$image[0].'" alt="'.__('Thumbnail image', 'social').'" />';
	$thumbnail_class = 'has-img';
}

?>
<div class="facebook-link-preview <?php echo esc_attr($thumbnail_class); ?>">
<?php
echo $thumbnail; 
?>
	<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
	<h5><?php echo esc_html($url_parts['host']); ?></h5>
	<?php the_excerpt(); ?>
</div>

=== Plugin Name ===
Contributors: crowdfavorite
Tags: comments, facebook, twitter
Requires at least: 3.1.3
Tested up to: 3.1.3
Stable tag: trunk

Here is a short description of the plugin.  This should be no more than 150 characters.  No markup here.

== Description ==

This is the long description.  No limit, and you can use Markdown (as well as in the following sections).

For backwards compatibility, if this section is missing, the full length of the short description will be used, and
Markdown parsed.

== Installation ==

1. Upload `social` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Why are the tabs on my comment form not displaying correctly? =

Chances are your theme is missing the `<?php wp_footer(); ?>` snippet in the footer.php.

= How often are comments aggregated from Facebook and Twitter? =

Once a post has been broadcasted to Facebook and/or Twitter Social will attempt to aggregate comments every 2, 4, 8, 12,
24, 48, and every 24 hours after the 48 hour mark after being broadcasted. If the post was broadcasted more than 48
hours ago, and there have been no replies through Facebook and/or Twitter then aggregation for that post will stop.

== Changelog ==

= 1.0 =
* Initial release

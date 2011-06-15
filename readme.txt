=== Social ===
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

= What CRON jobs are built into Social? =

Currently Social contains 3 CRON jobs:

1. `social_aggregate_comments`
2. `social_cron_15`
3. `social_cron_60`

= How can I manually call Social's CRON jobs? =

Simple, just make a request to http://example.com/?social_cron={key} where "{key}" is the name of the CRON you would
like to call.

= How can I hook into a CRON for extra functionality? =

If you want to hook into a CRON for extra functionality for a service, all you have to do is add an action:

    <?php add_action(Social::$prefix.'cron_15', array('Your_Class', 'your_method')); ?>

= How can I turn on and off Twitter's @Anywhere functionality? =

To utilize Twitter's @Anywhere functionality you will need to have an @Anywhere API key. If you don't have an API key,
visit http://dev.twitter.com/anywhere.

Once you have an API key login to your WordPress installation and navigate to Settings -> Social -> Twitter @Anywhere
Settings. Enter your API key in the text box and click on "Save Settings".

If you want to disable the @Anywhere functionality, simply remove the API key from the Social settings page and click
"Save Settings".

== Changelog ==

= 1.0 =
* Initial release

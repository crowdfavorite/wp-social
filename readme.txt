=== Social ===
Contributors: crowdfavorite
Tags: comments, facebook, twitter
Requires at least: 3.2
Tested up to: 3.2
Stable tag: 1.0

Broadcast posts to Twitter and/or Facebook, pull in items from each as comments, and allow commenters to use their Twitter/Facebook identities.

== Description ==

Social is a lightweight plugin that handles a lot of the heavy lifting of making your blog seamlessly integrate with social networking sites [Twitter](http://twitter.com/) and [Facebook](http://facebook.com/).

**Broadcast Published Posts**

Through use of a proxy application, you can associate your Twitter and Facebook accounts with your blog and its users. Once you publish a new post, you can then choose to automatically broadcast a message to any accounts authenticated with the overall blog or your current logged-in user.

- Automatically broadcast posts to Twitter and/or Facebook
- Supports multiple accounts associated per user and per blog
- Customize the broadcast message using tokens

**Pull in Tweets and Replies as Comments**

When publishing to Facebook and Twitter, the discussion is likely to continue there. Through Social, we can aggregate the various mentions, retweets, @replies, comments and responses and republish them as WordPress comments.

- Automatically polls Twitter and Facebook for mentions of your post
- Displays mentions inline with commments
- Filter comments by originating source (Facebook, Twitter, or your blog as comments)
- Allow users to reply to the offsite responses

**Comment as Facebook and/or Twitter Identity**

Many individuals use Facebook or Twitter as their primary identity(ies) on the web. Allow your commenters to log in and leave a comment as that identity. Furthermore, they can publish their response directly to their Twitter or Facebook account.

- Allow users to leave comments as Facebook or Twitter identity
- Twitter hovercard support (with @anywhere API key)
- Links point back to users' Facebook or Twitter profiles
- Indicators let you and visitors know people are who they say they are

== Installation ==

1. Upload `social` to the `/wp-content/plugins/` directory or install it from the Plugin uploader
2. Activate the plugin through the `Plugins` menu in the WordPress administrator dashboard
3. Visit your profile page under `Users > Profile` to associate Twitter and Facebook accounts with your profile
4. Visit the settings page under `Settings > Social` to associate Twitter and Facebook accounts with your blog
5. Change your plugin directory or uploads writable to allow the cron jobs to fetch new comments from Twitter and Facebook
6. Register for and add your [Twitter @anywhere API key](http://dev.twitter.com/anywhere) to the settings page to enable Twitter hovercards

== Frequently Asked Questions ==

= How can I customize the comments form in my theme? =

We recommend using CSS styles to selectively target the following:

- TBD
- TBD
- TBD

Note: we do not recommend making changes to the included plugin files as they may be overwritten during an upgrade.

= Why are the tabs on my comment form not displaying correctly? =

Chances are your theme is missing the `<?php wp_footer(); ?>` snippet in the footer.php.

= How often are comments aggregated from Facebook and Twitter? =

Once a post has been broadcasted to Facebook and/or Twitter Social will attempt to aggregate comments every 2, 4, 8, 12,
24, 48, and every 24 hours after the 48 hour mark after being broadcasted. If the post was broadcasted more than 48
hours ago, and there have been no replies through Facebook and/or Twitter then aggregation for that post will stop.

These are performed using cron jobs noted below.

= What CRON jobs are built into Social? =

Currently Social contains 3 CRON jobs:

1. `social_aggregate_comments`
2. `social_cron_15`
3. `social_cron_60`

= How can I manually call Social's CRON jobs? =

Simple, just make a request to `http://example.com/?social_cron={key}` where "{key}" is the name of the CRON you would
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

= Does the proxy application have access to my passwords now? =

No, the proxy acts just like any Twitter or Facebook application. We've simply pass commands back and forth through this application so you don't have to set up your own.

== Screenshots ==

1. Allow your visitors to leave a comment as their Facebook or Twitter identities

== Changelog ==

= 1.0 =
* Initial release

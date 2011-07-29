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
- Displays mentions inline with comments
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

We recommend using CSS styles to selectively change the look and feel of your comments and comment form. The classes `social-comments-[no-comments|wordpress|twitter|facebook|pingbacks]` are available as as class names. More specific classes include:


- .social-comment-header - contains the author information, avatar, and meta information
- .social-comment-inner - a wrapper for the actual comment content, allowing more freedom with comment styling.
- .social-comment-body - the container for the comment content.
- .social-comment-collapsed - use this hook to create a more compact version of a comment.  hide comment text, shrink the size of the avatar, etc.
- .social-post-form - where the comment form controls reside.  Use this hook to customize the look of form inputs and labels.
- #facebook_signin - style the link that activates Facebook oAuthorization
- #twitter_signin - style the link that activates Facebook oAuthorization


- Social Plugin Icons - The icons are currently 16x16 pixels, and reside in a vertical sprite with each icon at 100px intervals.  Adhering to these intervals will ensure that icon positions will not need to change
- .social-quiet - a muted typography style, for subdued display of text


Note: we do not recommend making changes to the included plugin files as they may be overwritten during an upgrade.

= Why are the tabs on my comment form not displaying correctly? =

Chances are your theme is missing the `<?php wp_footer(); ?>` snippet in the footer.php.

= How often are comments aggregated from Facebook and Twitter? =

Once a post has been broadcasted to Facebook and/or Twitter Social will attempt to aggregate comments every 2, 4, 8, 12, 24, 48, and every 24 hours after the 48 hour mark after being broadcasted. If the post was broadcasted more than 48 hours ago, and there have been no replies through Facebook and/or Twitter then aggregation for that post will stop.

These are performed using cron jobs noted below.

= How are comments aggregated from Facebook and Twitter? =

When the aggregation process runs Social uses the Facebook and Twitter search APIs to aggregate comments to the system.

For Twitter, Social first searches for retweets and mentions of the broadcasted tweet using the following API calls: /statuses/retweets/:id, /statuses/mentions. Social then stores those IDs in a collection of aggregated comments. Social then hits Twitter's search API and uses the http://example.com?p=:id and the permalink generated by get_permalink($post_id) to search for tweets that contain a link to the blog post. Social then iterates over the search results and adds them to the collection, if the tweet does not already exist in the collection.

For Facebook, Social first uses the Facebook search API to find any post that has the http://example.com?p=:id or the permalink generated by wp_get_permalink($post_id). These posts are then stored in a collection. Next, Social loads the comments for the broadcasted post by calling http://graph.facebook.com/:id/comments. Social then iterates over the search results and adds them to the collection, if the comment does not already exist in the collection.

For both Facebook and Twitter, the final collection of tweets/comments are then added to the blog post as comments. The IDs of the tweets and comments are then stored to the database so when aggregation runs again the tweets and comments already aggregated are not duplicated.

= What tweets will be seen by the Twitter search during aggregation? =

Currently it seems Twitter will only return results using http://example.com/?p=:id and the permalink generated by get_permalink($post_id) if the full link is in the Tweet, or if the URL is minified using t.co URLs. Social can not guarantee that Tweets will be aggregated if any other URL shortening service is used.

= What CRON jobs are built into Social? =

Currently Social contains 2 CRON jobs:

1. `cron_15`
2. `cron_60`

= How can I override Social's internal CRON service with system CRON jobs? =

If you want to run system CRON jobs and disable Social's built in CRON jobs then do the following:

1. Go to Social's settings page.
2. Disable Social's internal CRON mechanism by selecting "Yes" under "Disable Internal CRON Mechanism" and clicking on "Save Settings".
3. Now you should have an API key that you'll find under "API Key" under "Disable Internal CRON Mechanism". Use this API key for the "api_key" parameter on the URL your system CRON fires.
	- An example system CRON could run http://example.com/?social_cron=cron_15&api_key=your_api_key_here

= How can I hook into a CRON for extra functionality? =

If you want to hook into a CRON for extra functionality for a service, all you have to do is add an action:

    <?php add_action(Social::$prefix.'cron_15', array('Your_Class', 'your_method')); ?>

= How can I turn on and off Twitter's @Anywhere functionality? =

To utilize Twitter's @Anywhere functionality you will need to have an @Anywhere API key. If you don't have an API key,
visit http://dev.twitter.com/anywhere.

Once you have an API key login to your WordPress installation and navigate to Settings -> Social -> Twitter @Anywhere Settings. Enter your API key in the text box and click on "Save Settings".

If you want to disable the @Anywhere functionality, simply remove the API key from the Social settings page and click "Save Settings".

= Does the proxy application have access to my passwords now? =

No, the proxy acts just like any Twitter or Facebook application. We've simply pass commands back and forth through this application so you don't have to set up your own.

= How can I define a custom comments.php for Social? =

In your theme's functions.php add the following line:

    define('SOCIAL_COMMENTS_FILE', STYLESHEETPATH.'social-comments.php');

Then you will need to create a social-comments.php with your custom markup in your theme's directory.

= How can I define custom JS and/or CSS, or disable Social's JS/CSS? =

There are three constants that can be altered to your liking:

1. `SOCIAL_ADMIN_CSS` - CSS file for WP-Admin.
2. `SOCIAL_COMMENTS_CSS` - CSS file for the comments form.
3. `SOCIAL_JS` - JS file used on the comments form and WP-Admin

To define custom JS/CSS in your theme's functions.php add the following line (Replace "SOCIAL_ADMIN_CSS" with one of the
constants listed above):

    define('SOCIAL_ADMIN_CSS', 'path/to/stylesheet.css');

To disable Social's JS/CSS in your theme's functions.php add the following line (Replace "SOCIAL_ADMIN_CSS" With one of
the constants defined above):

    define('SOCIAL_ADMIN_CSS', false);

== Screenshots ==

1. Allow your visitors to leave a comment as their Facebook or Twitter identities

== Changelog ==

= 1.0 =
* Initial release

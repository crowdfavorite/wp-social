=== Social ===
Contributors: crowdfavorite, alexkingorg
Tags: comments, facebook, twitter, social, broadcast, import, integrate, integration
Requires at least: 3.8
Tested up to: 4.2
Stable tag: 3.1.1
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Broadcast posts to Twitter and/or Facebook, pull in reactions from each (replies, retweets, comments, "likes") as comments, and allow commenters to log in with their Twitter/Facebook identities.

== Description ==

Brought to you by [MailChimp](http://mailchimp.com/), [Social](http://mailchimp.com/social-plugin-for-wordpress/) is a lightweight plugin that handles a lot of the heavy lifting of making your blog seamlessly integrate with social networking sites [Twitter](http://twitter.com/) and [Facebook](http://facebook.com/).

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
- Links point back to users' Facebook or Twitter profiles
- Indicators let you and visitors know people are who they say they are

**Developers**

Please [fork, contribute and file technical bugs on GitHub](https://github.com/crowdfavorite/wp-social).

== Installation ==

1. Upload `social` to the `/wp-content/plugins/` directory or install it from the Plugin uploader
2. Activate the plugin through the `Plugins` menu in the WordPress administrator dashboard
3. Visit the settings page under `Settings > Social` to add Twitter and Facebook accounts for all authors on your site
4. Visit your profile page under `Users > Profile` to add Twitter and Facebook accounts that only you can broadcast to
5. Make sure your plugin or uploads directory writable to allow the cron jobs to fetch new comments from Twitter and Facebook

== Frequently Asked Questions ==

= Who created this plugin? =

Social was conceptualized and co-designed by the fine folks at [MailChimp](http://mailchimp.com "Email Marketing from MailChimp") led by [Aarron Walter](http://aarronwalter.com/). The application proxy is maintained and hosted by [MailChimp](http://mailchimp.com) and the plugin was co-designed and developed by [Crowd Favorite](http://crowdfavorite.com/ "Custom WordPress and web development").

= How can I customize the comments form in my theme? =

We recommend using CSS styles to selectively change the look and feel of your comments and comment form. The classes `social-comments-[no-comments|wordpress|twitter|facebook|pingbacks]` are available as as class names. More specific classes include:

- `.social-comment-header `- contains the author information, avatar, and meta information
- `.social-comment-inner` - a wrapper for the actual comment content, allowing more freedom with comment styling.
- `.social-comment-body` - the container for the comment content.
- `.social-comment-collapsed` - use this hook to create a more compact version of a comment.  hide comment text, shrink the size of the avatar, etc.
- `.social-post-form` - where the comment form controls reside.  Use this hook to customize the look of form inputs and labels.
- `#facebook_signin` - style the link that activates Facebook oAuthorization
- `#twitter_signin` - style the link that activates Facebook oAuthorization
- `.social-quiet` - a muted typography style, for subdued display of text
- `.bypostauthor` - a class added to the comment thread to style comments from the author of the post

The icons used for the plugin are currently 16x16 pixels, and reside in a vertical sprite with each icon at 100px intervals.  Adhering to these intervals will ensure that icon positions will not need to change

Note: we do not recommend making changes to the included plugin files as they may be overwritten during an upgrade.

= Can I define a custom comments.php for Social? =

Yes, if you'd rather create more 'advanced' customizations beyond CSS tweaks simply add the following line to your theme's `functions.php` file:

    define('SOCIAL_COMMENTS_FILE', STYLESHEETPATH.'/social-comments.php');

Then you will need to create the `social-comments.php` file with your custom markup (perhaps copy it directly from the provided comments.php in the plugin) into your theme's directory.

= How can I define custom JS and/or CSS, or disable Social's JS/CSS? =

There are three constants that can be altered to your liking:

1. `SOCIAL_ADMIN_CSS` - CSS file for WP-Admin.
2. `SOCIAL_ADMIN_JS` - JS file for WP-Admin.
3. `SOCIAL_COMMENTS_CSS` - CSS file for the comments form.
4. `SOCIAL_COMMENTS_JS` - JS file used on the comments form and WP-Admin

To define custom JS/CSS in your theme's functions.php add the following line (Replace `SOCIAL_ADMIN_CSS` with one of the
constants listed above):

    define('SOCIAL_ADMIN_CSS', STYLESHEETPATH.'/path/to/stylesheet.css');

To disable Social's JS/CSS in your theme's functions.php add the following line (Replace `SOCIAL_ADMIN_CSS` With one of
the constants defined above):

    define('SOCIAL_ADMIN_CSS', false);

= Why are the tabs on my comment form not displaying correctly? =

Chances are your theme is missing the `<?php wp_footer(); ?>` snippet in the footer.php.

= How often are comments aggregated from Facebook and Twitter? =

Once a post has been broadcasted to Facebook and/or Twitter Social will attempt to aggregate comments every 2, 4, 8, 12, 24, 48, and every 24 hours after the 48 hour mark after being broadcasted. If the post was broadcasted more than 48 hours ago, and there have been no replies through Facebook and/or Twitter then aggregation for that post will stop.

These are performed using cron jobs noted below.

= How are comments aggregated from Facebook and Twitter? =

When the aggregation process runs Social uses the Facebook and Twitter search APIs to aggregate comments to the system.

For Twitter, Social first searches for retweets and mentions of the broadcasted tweet using the following API calls: /statuses/retweets/:id, /statuses/mentions. Social then stores those IDs in a collection of aggregated comments. Social then hits Twitter's search API and searches by URL for tweets that contain a link to the blog post. Social then iterates over the search results and adds them to the collection, if the tweet does not already exist in the collection.

For Facebook, Social first uses the Facebook search API to find any post that has the http://example.com?p=:id, the permalink generated by get_permalink($post_id) or the short link generated by the active short link delegate at the time the post was created. These posts are then stored in a collection. Next, Social loads the comments for the broadcasted post by calling http://graph.facebook.com/:id/comments. Social then iterates over the search results and adds them to the collection, if the comment does not already exist in the collection.

For both Facebook and Twitter, the final collection of tweets/comments are then added to the blog post as comments. The IDs of the tweets and comments are then stored to the database so when aggregation runs again the tweets and comments already aggregated are not duplicated.

= What tweets will be seen by the Twitter search during aggregation? =

Twitter converts most short URLs to longer URLs, so searches by the final URL will typically find all of the tweets you want. Social also explicitly searches by the short link for the post (using whatever short link generator or plugin was active when the post was broadcast).

= What CRON jobs are built into Social? =

Currently Social contains one CRON job, `cron_15`.

= How can I override Social's internal CRON service with system CRON jobs? =

If you want to run system CRON jobs and disable Social's built in CRON jobs then do the following:

1. Go to Social's settings page.
2. Disable Social's internal CRON mechanism by selecting "Yes" under "Disable Internal CRON Mechanism" and clicking on "Save Settings".
3. Now you should have an API key that you'll find under "API Key" under "Disable Internal CRON Mechanism". Use this API key for the "api_key" parameter on the URL your system CRON fires.
	- An example system CRON could run `http://example.com/?social_controller=cron&social_action=cron_15&api_key=your_api_key_here`

= How can I hook into a CRON for extra functionality? =

If you want to hook into a CRON for extra functionality for a service, all you have to do is add an action:

    <?php add_action('socialcron15', array('Your_Class', 'your_method')); ?>

= Does the proxy application have access to my passwords now? =

No, the proxy acts just like any Twitter or Facebook application. We've simply pass commands back and forth through this application so you don't have to set up your own.

= If a user comments using Twitter and that user has an existing local WordPress account, can they add the twitter account to the existing WordPress account? =

Yes, they can add the account to their profile page.

= Why are some custom posts with my blog post's URL not being found during aggregation? =

This may be due to the bug with Facebook's search API. Currently, for a post to return on the search the URL must not be at the beginning of the post.

Valid post that will be included:

Hey, check out this post! http://example.com/?p=5

Invalid post that will not included:

http://example.com/?p=5 This was a cool post, go read it.

Track this bug on Facebook: http://bugs.developers.facebook.net/show_bug.cgi?id=20611

= Why are some of comments/posts not returning from Facebook right away? =

We have noticed some latency around the inclusion of some items when querying the Graph API. We have seen some comments and posts take up to 72 hours to be included in aggregation requests.

= Does your permalink have apostrophes in it? Is Social stripping these from the URL when disconnecting? =

This is due to the fact that older versions of WordPress did not remove apostrophes from the permalink and newer versions of WordPress do. It is possible that your blog post was created on a version of WordPress that contained this bug. To fix this, simply login to your WP-Admin and edit the post by doing the following (assuming you're running WordPress 3.2+):

1. Click on Posts in the WP-Admin menu.
2. Find your post and click on "Edit".
3. Under your post's title, click on the "Edit" link that is next to the permalink.
4. Click "OK" to save the new permalink. (This will automatically remove the apostrophes for you.)

= Can Social use Bit.ly, Bit.ly Pro or another URL shortener when broadcasting? =

Social uses the core WordPress shortlink feature when broadcasting blog posts. Any plugin that interacts with the shortlink will also be reflected in Social's broadcasts.

wp_get_shortlink Documentation: http://codex.wordpress.org/Function_Reference/wp_get_shortlink
Bit.ly Plugin: http://wordpress.org/extend/plugins/bitly-shortlinks/

When using the Bit.ly plugin, you will need to add the following to your wp-config.php to get it working:

    /**
     * Settings for Bit.ly Shortlinks Plugin
     * http://yoast.com/wordpress/bitly-shortlinks/
     **/
    define('BITLY_USERNAME', '<your username>');
    define('BITLY_APIKEY', '<your API key>');

    // optional, if you want to use j.mp instead of bit.ly URL's
    define('BITLY_JMP', true);


= I have Apache's 401 auth enabled on my website, why is Social not working? =

The proxy Social connects to requires your website to be publicly accessible to properly authorize your Facebook and Twitter accounts.

= How can I be notified via email of comments left using Social? =

You can install the "Subscribe to Comments Reloaded" plugin written by coolman (http://profiles.wordpress.org/users/coolmann/).

Download: http://wordpress.org/extend/plugins/subscribe-to-comments-reloaded/

= I occasionally receive a PHP notice of "Undefined property: WP_Http_Curl::$headers", what does this mean? =

This is actually a bug in the WordPress core. This will be fixed in WordPress 3.3 according to this ticket http://core.trac.wordpress.org/ticket/18157.

= I occasionally receive a PHP Warning of "Missing argument 5 for Social::get_avatar()", what does this mean? =

You are likely using the add-local-avatars plugin here : http://wordpress.org/extend/plugins/add-local-avatar/

This plugin incorrectly calls the `get_avatar` filter.

http://wordpress.org/support/topic/get_avatar-filter-hook-missing-5th-argument

= Where can I update my default social broadcast accounts? =

Connect your social account and after that you can add/remove your default broadcast accounts under the Social Settings Page and from your user profile page (Users/Your Profile).

= Why can't I set up my social accounts on my local WordPress site? =

Accounts can not be authorized on local environments, unless your local environment is publicly accessible via DNS.

= I previously used a custom comments.php template with Social and it no longer works when I upgrade to 2.0, why is this? =

This is because we completely refactored Social's codebase for 2.0. Chances are your old comments template is using some code that we removed in 2.0. For now you should be able to use the built in Social comments template, but if you want to continue using your old template, we suggest you take a look at social/views/comments.php to see how the new implementation works.

For a more in-depth look at what you need to be aware of when upgrading from 1.x to 2.0 please have a look at the wiki entry: https://github.com/crowdfavorite/wp-social/wiki/Upgrading-from-1.x-to-2.0

= How do I include Facebook Likes and Twitter Retweets in my comments feed? =

The following code should add "meta" comments such as Likes as Retweets to your comments RSS and Atom feeds:

	function social_enable_meta_comments_in_feed() {
		$social = Social::instance();
		remove_filter('comment_feed_where', array($social, 'comments_feed_exclusions'));
	}
	add_action('init', 'social_enable_meta_comments_in_feed');

= Why is facebook not getting the correct image for my broadcasts? =

Facebook prefers to use Open Graph tags (http://ogp.me/) to pull in meta data about posted content.  Featured Images will be send with broadcasts, but if no featured image is present, facebook will calculate a best guess based on a few factors.  To control what images and metadata facebook uses to describe your post, use Open Graph tags in your theme.

= Does Social output Facebook Open Graph tags for my site? =

No it does not. If you would like to have these tags on your site, please install one of the many available plugins that add this feature.

= I have a lot of comments and loading the avatars makes the page load slowly, what can I do? =

Social supports the [Lazy Load plugin](http://wordpress.org/extend/plugins/lazy-load/). Install this plugin and Social's avatars will hang out on the couch eating potato chips and watching TV until they are needed.

= Can I use Social and Disqus at the same time? =

Both Social and Disqus try to replace the default WordPress comment experience by default. If you want to use Social's broadcasting features but prefer to use Disqus for your comments, you probably want to check the "Disable Social's comment display (use standard theme output instead)." box under Advanced Options. This will allow Disqus to take over comment display without any interference from Social.

= Why aren't my likes and retweets getting auto approved? =

Since Social 2.9 we've made the decision to disable this by default. If the default "Comment author must have a previously approved comment" setting is enabled in WordPress then any approved Like or RT opens a path for comments by that same user to be automatically approved. This is fairly easy to turn back on if you understand the risk.

	add_action('social_approve_likes_and_retweets', '__return_true');

= Why are there broken user images/avatars on comments imported from Twitter? =

Much to the consternation of developers everywhere, Twitter provides direct CDN URLs for its user profile images. This means that when someone changes their Twitter avatar, the old image URL may go dark. There is no "permalink" for a Twitter user avatar, so the best we can do is go back and update old comments to use the user's new avatar. There is a <a href="https://github.com/alexkingorg/wp-social-twitter-avatar-update">plugin for this</a>.

== Screenshots ==

1. Allow your visitors to leave a comment as their Facebook or Twitter identities

2. Social settings screen to connect accounts, set up default broadcast settings and more

3. Post edit screen settings: broadcast the post, manually import comments, view a log of imported items

4. Send customized broadcasts to each social account

5. View of replies imported from Twitter as comments


== Upgrade Notice ==

= 3.0 =
* (fix) Change specific nonce behavior for WordPress 4.0 compatibility.
* (fix) Add additional nonce behavior to account for nonces added to URLs.
* Sync up `child_account_avatar()` declarations

= 2.11 =
* (new) FAQ with link to plugin to update Twitter avatars for comments
* (fix) Update the information about Social's CRON actions

= 2.10 =
* (new) Now requires WordPress 3.8 (due to threaded comments walker change in WP core)
* (new) WP 3.8 admin refresh compatibility
* (new) Japanese translation (thanks ToshiOshio)
* (fix) Work around changes in the Walker class in WP 3.8 so that nested comments appear as expected
* (fix) Remove underscores from CRON actions (thanks nddery)
* (fix) Work around MySQL bug #62077 (thanks DavidAnderson684)
* (fix) Compress images (thanks pathawks)
* (fix) use esc_url_raw() (thanks kanedo)

== Changelog ==

= 3.1.1 =
* (fix) Add FB page permissions when requesting the app

= 3.1 =
* Update functionality to use the latest facebook API, the old API is deprecated. This removes the aggregation by URL method, which means shares/likes not coming from the broadcasted post cannot be aggregated.

= 3.0 =
* (fix) Remove duplicate name attribute from Add Tweet By URL button
* (fix) Change specific nonce behavior for WordPress 4.0 compatibility.
* (fix) Add additional nonce behavior to account for nonces added to URLs.
* Sync up `child_account_avatar()` declarations
* (fix) Pull up to 500 comments from Facebook (thanks Andrew Ferguson)
* (fix) Correct wp-cron call to wp-admin/options-general.php
* (fix) Increase Facebook max_broadcast_length to 50,000 (from 420)
* (fix) Modify character counter to count linefeeds as two characters as required by HTML specification: http://www.w3.org/TR/html401/interact/forms.html#h-17.13.4

= 2.11 =
* (new) FAQ with link to plugin to update Twitter avatars for comments
* (fix) Update the information about Social's CRON actions

= 2.10 =
* (new) Now requires WordPress 3.8 (due to threaded comments walker change in WP core)
* (new) WP 3.8 admin refresh compatibility
* (new) Japanese translation (thanks ToshiOshio)
* (fix) Work around changes in the Walker class in WP 3.8 so that nested comments appear as expected
* (fix) Remove underscores from CRON actions (thanks nddery)
* (fix) Work around MySQL bug #62077 (thanks DavidAnderson684)
* (fix) Compress images (thanks pathawks)
* (fix) use esc_url_raw() (thanks kanedo)

= 2.9.2 =
* More gracefully handle "bad data" returned from social proxy upon comment broadcast
* Add filter to outgoing requests
* Add French localization (thanks https://github.com/Hedi-s)
* Add Italian localization (thanks https://github.com/davidesalerno)
* Add Spanish localization (thanks https://github.com/juanjosepablos)

= 2.9.1 =
* Fixed bug in the way facebook comment permalinks were being generated

= 2.9 =
* Support for threaded comment replies for Facebook pages
* Added Option to enable/disable social broadcasting for specific post types
* Added German and Norwegian Bokmål language support
* Now using HTTPS for Facebook and Twitter links and avatars
* Worked around bug in add-local-avatar plugin
* Disable auto approval of likes and retweets (See FAQ)
* Added Option to enable/disable social broadcasting for specific post types
* Fix Issue with high byte charaters causing duplication of aggregated comments

= 2.8 =
* Change Twitter search endpoint to use v1.1
* Remove warnings related to broadcasting to no accounts
* Update to WordPress 3.5 button styles

= 2.7 =
* New Social proxy endpoint (the old one will go away on Jan 15th)
* Separate setting for fetching social comments and WP Cron integration (used in Twitter Tools)
* Change Facebook endpoint for posting links (should accept passed images again)


= 2.6.1 =
* More consistent comment date output
* Try to work around draconian HostGator faux-security rules
* Strip shortcodes from content before creating default broadcast


= 2.6 =
* Now utilizes the newest Twitter API (1.1)
* Removed the discontinued Twitter @anywhere service
* Automatically approve Likes and Retweets
* New date format filters: `social_formatted_date`, `social_comment_date`, `social_fuzzy_date`
* XML-RPC / posts via email / scheduled posts now auto broadcast correctly
* Enable Pages support in user profile social accounts is working correctly
* Now utilizing longer timeouts for broadcast requests
* Properly post links to facebook
* Remove 'social' namespace for login i18n
* Properly truncate comment broadcasting, giving the url priority


= 2.5 =
* Fix race condition that could cause users to be authenticated as the wrong user when both requests happened simultaneously.
* Improve Facebook posting (post links with comments and broadcasts, except for status posts)
* Improve Social as a platform (can disable broadcasting, comment display, comment importing, "add an account" alert is dismissable)
* Revise broadcasting screen to allow sending different messages to each account
* Revise account management UI
* Add a Manual Tweet Import field on the front-end (via admin bar)
* Twitter search expanded to receive 100 results per request
* Ability to reply to a tweet with a broadcast
* Import replies via Twitter to broadcasted comments (if found)
* Automatically select proper social account when replying to a comment
* When posting a comment back to Facebook, attempt to reply in an existing comment thread where appropriate
* Automatically check the "broadcast" box when replying to a social comment and authenticated as a user on the same social network
* Don't include Retweet and Like comments in comment RSS/Atom feeds
* Improved relative date functions for comments (3 months ago, etc.)
* Support lazy loading of avatars (via plugin)
* Change comment header title based on context (creating a new comment, replying to a comment, etc.)
* Fix issue causing reactions to Facebook broadcasts to not be imported consistently as comments
* Various bug fixes and improvements


= 2.0.1 =
* Localization fixes (props thomasclausen)
* Minor bug fixes

= 2.0 =
* Complete re-write for improved reliability and ease of future expansion.
* Enables broadcasting to Facebook Pages.
* Facebook Likes are now imported during comment aggregation.
* Twitter retweets and Facebook Likes have more compact visual presentation.
* Smart detection of retweets as understood by humans (where possible).
* Enable broadcasting to selected by default.
* Future posts are not broadcast until they are published.
* Comments are not broadcast until they are approved.
* Directly imported tweets (by URL) are approved immediately (not held for moderation).
* Only public tweets are imported as comments.
* New authentication scheme improves security.
* Manual comment check commands from the admin bar and posts list admin page.
* Improved queue and locking system to reduce the possibility of social reactions being imported twice.
* Filter: social_broadcast_format now contains a third parameter, $service_key.
* Filter: social_broadcast_permalink now contains a third parameter, $service_key.
* Filter: social_format_content now contains a fourth parameter, $service_key.
* Filter: social_broadcast_content_formatted now contains a third parameter, $service_key.

= 1.0.2 =
* Added the social_kses method to cleanse data coming back from the services.
* WP accounts are no longer created with usernames of "facebook_" and "twitter_".

= 1.0.1 =
* Automatic CRON jobs now run correctly.
* Facebook replies to broadcasted posts are now aggregated.
* Miscellaneous bug fixes.

= 1.0 =
* Initial release

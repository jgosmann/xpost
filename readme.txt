=== Xpost ===
Contributors: blubbx
Tags: wordpress, crosspost, communityserver
Requires at least: 2.9
Tested up to: 3.0.1
Stable tag: 1.1.2

Xpost allows you to crosspost your posts to other Wordpress blogs and to Community Server.

== Description ==

The Xpost (speak it: crosspost) plugin allows you to crosspost your posts to other Wordpress blogs or Community Server blogs via XML-RPC. Features include:

* The plugin keeps track of which post has been crossposted to which blog and can update the crossposted posts when changing the original posts.
* If you save a draft this will be crossposted as draft. If you publish the draft you can publish it also on the crossposted blogs.
* If you protect a post with a password the crossposted posts will also be protected by a password.
* You can select the categories which should be used for the crosspost whereby these categories will be fetched from the blog you crosspost to.
* Crossposting will crosspost of course the title and text of the post, but also the excerpt, tags, the scheduled date for publishing and whether to allow comments and ping backs.
* Crossposting of comments (since version 1.1.0).

Please note that you have to install Xpost on all blogs you want to crosspost comments to. You have also to activate posting via XML-RPC in all these blogs.

Once you approve a comment in any of the blogs which are selected for crossposting comments it will be posted to all of these blogs. It will be immediatly approved in all these blogs.

Please note that the support for crossposting to Community Server blogs is untested and may not include all of the features.

== Installation ==

1. Upload the `xpost` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Goto 'Settings', then 'Xpost' and add your Wordpress and Community server blogs.
1. That's it! When editing or creating a post you will find a Xpost widget in the sidebar where you can select the blogs to crosspost to and the categories.

Please note that you have to activate posting via XML-RPC for the blogs you want to crosspost to. You can activate XML-RPC if you goto 'Settings' and then 'Writing' in the admin interface of each of those blogs.

If you upgrade from an earlier version, make sure you deactivate the plugin first and reactivate it afterwards. This is needed, because the database format may change and this will only be updated on reactivation.

If you want to crosspost comments you have to install the Xpost plugin on all Wordpress installation the comments should get crossposted to and you have to activate posting via XML-RPC for all theses installations.

== Frequently Asked Questions ==

= How exactly does the crossposting of the comments work? =
There are two different cases:
1. A comment is posted and approved in the blog with the original post. In this case it is stored in the database to which blogs the post was crossposted. The Xpost plugin will then transmit the comment via XML-RPC to these blogs.
2. A comment is posted and approved in a blog as response to a crosspost. In this case the Xpost plugin has stored the address of the blog containing the orginal post together with the crosspost. The comment will then be transmitted via XML-RPC to the original blog. The Xpost plugin in the original blog know to which other blogs the comment has to be posted and does so via XML-RPC.

== Screenshots ==

== Changelog ==
= 1.2.0 =
* Adding (untested) support for crossposting to Community Server blogs. Thanks to Daniel Cohen!
* Ability to backdate posts when crossposting.
* Hopefully the timezone confusion is fixed. Finally.
* Fixed version number in admin panel.
* Minor issues.

= 1.1.1 =
* Hopefully fixed bug with posts showing "missed schedule" instead of getting published.
* Wordpress multi-user compatibility, but untested. Included just a patch suggestion by Norbert Mayer-Wittmann.

= 1.1.0 =
* New feature: Crossposting of comments.
* Fixed version number which was still wrongly displayed as 1.0.

= 1.0.4 =
* Fixed bug: Backslashes were added in front of quotes in crossposts.
* Fixed bug: Media uploaded with the Wordpress mediauploader was not shown in crossposts.

= 1.0.3 =
* Plugin is now compatible to PHP version older than PHP 5.3.0.
* Fixed bug: Categories did not get loaded when creating a new post.

= 1.0.2 =
* Improved installation instructions.

= 1.0.1 =
* Added domain to gettext calls allowing translation.
* Added pot file for translation.
* Added German translation.
* Added additional license information.

= 1.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
This version introduces crossposting to Community Server blogs. Because the database format changed you need do deactivate and reactivate the plugin when upgrading. If you use autoupdate this is done automatically.

= 1.1.0 =
This version introduces crossposting of comments. Because the database format changed you need do deactivate and reactivate the plugin when upgrading. If you use autoupdate this is done automatically.

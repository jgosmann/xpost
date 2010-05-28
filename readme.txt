=== Xpost ===
Contributors: blubbx
Tags: wordpress, crosspost
Requires at least: 2.5
Tested up to: 2.8.5
Stable tag: 1.0.3

Xpost allows you to crosspost your posts to other Wordpress blogs.

== Description ==

The Xpost (speak it: crosspost) plugin allows you to crosspost your posts to other Wordpress blogs via XML-RPC. Features include:

* The plugin keeps track of which post has been crossposted to which blog and can update the crossposted posts when changing the original posts.
* If you save a draft this will be crossposted as draft. If you publish the draft you can publish it also on the crossposted blogs.
* If protect a post with a password the crossposted posts will also be protected by a password.
* You can select the categories which should be used for the crosspost whereby this categories will be fetched from the blog you crosspost to.
* Crossposting will crosspost of course the title and text of the post, but also the excerpt, tags, the scheduled date for publishing and whether to allow comments and ping backs.

== Installation ==

1. Upload the `xpost` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Goto 'Settings', then 'Xpost' and add your Wordpress blogs.
1. That's it! When editing or creating a post you will find a Xpost widget in the sidebar where you can select the blogs to crosspost to and the categories.

Please note that you have to activate posting via XML-RPC for the blogs you want to crosspost to. You can activate XML-RPC if you goto 'Settings' and then 'Writing' in the admin interface of each of those blogs.

== Frequently Asked Questions ==

Nothing so far.

== Screenshots ==

== Changelog ==

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

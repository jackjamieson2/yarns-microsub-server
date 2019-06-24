Using your own WordPress site, aggregate a social timeline of your favourite sites from across the Web and then view and reply to your feeds using a [Microsub client](https://indieweb.org/Microsub#Clients).


*Note: Yarns is in Beta. It works reasonably well, though still has a few bugs to be worked out. Please report bugs if you find them - thank you!*


## Description 

Yarns Microsub Server helps you follow feeds from across the Web. Enter a website and Yarns will help you find and subscribe to its feed(s) in several different formats (Microformats, RSS, Atom, JSONFeed). Once you've added feeds, new posts are collected in the background for you to read whenever you want.

Rather than viewing posts in Yarns itself, you can choose among [several different apps](https://indieweb.org/Microsub#Clients) to follow your feeds on your desktop or mobile device.

No matter which app you choose to view your feed, your replies will be posted on your own website.

Accompanied by other plugins that support [IndieWeb](https://indieweb.org) standards, Yarns can help use your personal website as the centre of your online identity.


## Installation 

Since Yarns is still in development, it is not yet available on the WordPress plugin repository. This means you'll have to install it from GitHub directly.

You can do this by downloading the [latest release](https://github.com/jackjamieson2/yarns-microsub-server/releases) as a .zip.

Then install in WordPress by going to Plugins->Add New->Upload Plugin, and selecting the .zip you just downloaded.

(You can also download the master branch of this repository, which is usually slightly newer but may be less stable).


### Requirements 

Yarns is part of the IndieWeb ecosystem, and requires a few parts to get things working.

The easiest way to get started is to install the [IndieWeb plugin](https://wordpress.org/plugins/indieweb/), with the following extensions:

* IndieAuth: *To log into a Microsub client*
* Micropub: *(optional) To post to your site from a Microsub client*
* IndieWeb Post Kinds: *(optional) To be able to post likes, replies, and other types of responses to your feeds*


## Using Yarns 
Yarns is a Microsub server. This means it lets you subscribe to many kinds of websites, including most blogs and news sites. There are two parts to using Yarns


### Viewing your feeds 
To view your feeds, you must use a [Microsub Client](https://indieweb.org/Microsub#Clients).



### Subscribing to feeds 
You can subscribe to feeds using Yarns' settings page in the WordPress dashboard, or using a Microsub client that supports modifying your feeds (e.g. [Together](http://alltogethernow.io)).


### Adding feeds using Yarns' UI in the WordPress dashboard 
===== Accessing Yarns' Settings: =====
- (Recommended) If you have the [IndieWeb plugin](https://wordpress.org/plugins/indieweb/) installed, then go to your WordPress dashboard, and choose 'Yarns Microsub Server' from the IndieWeb menu.
- Otherwise, this will be under Settings.


### Adding channels 


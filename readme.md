# Yarns Microsub Server #
**Contributors:** jackjamieson2, dshanske  
**Tags:** microsub, indieweb, feed, reader  
**Requires at least:** 4.7  
**Tested up to:** 4.9.6  
**Stable tag:** trunk  
**License:** MIT  
**License URI:** http://opensource.org/licenses/MIT

Using your own WordPress site, aggregate a social timeline of your favourite sites from across the Web and then view and reply to your feeds using a [Microsub client](https://indieweb.org/Microsub#Clients).


*Note: Yarns is still in development. It's close to ready, but still has some compatibility issues that are being worked on.*

##Description

Yarns Microsub Server helps you follow feeds from across the Web. Enter a website and Yarns will help you find and subscribe to its feed(s) in several different formats (Microformats, RSS, Atom, JSONFeed). Once you've added feeds, new posts are collected in the background for you to read whenever you want.

Rather than viewing posts in Yarns itself, you can choose among [several different apps](https://indieweb.org/Microsub#Clients) to follow your feeds on your desktop or mobile device.

No matter which app you choose to view your feed, your replies will be posted on your own website.

Accompanied by other plugins that support [IndieWeb](https://indieweb.org) standards, Yarns can help use your personal website as the centre of your online identity.

##Installation
Since Yarns is still in development, it is not yet available on the WordPress plugin repository. This means you'll have to install it from GitHub directly.

You can do this by downloading the master branch from this repository as a zip. (release package coming soon)

Then install in WordPress by going to Plugins->Add New->Upload Plugin, and selecting the .zip you just downloaded.

##Requirements
Yarns is part of the IndieWeb ecosystem, and requires a few parts to get things working.

The easiest way to get started is to install the [IndieWeb plugin](https://wordpress.org/plugins/indieweb/), with the following extensions:

- IndieAuth: *To log into a Microsub client*
- Micropub: *(optionalTo post to your site from a Microsub client*
- IndieWeb Post Kinds: *(optional) To be able to post likes, replies, and other types of responses to your feeds*

##Using Yarns
Yarns is a Microsub server. This means it lets you subscribe to many kinds of websites, including most blogs and news sites. There are two parts to using Yarns

###Subscribing to feeds
You can subscribe to feeds using Yarns' settings page in the WordPress dashboard, or using a Microsub client that supports modifying your feeds (e.g. [Together](http://alltogethernow.io)).

###Viewing your feeds
To view your feeds, you must use a [Microsub Client](https://indieweb.org/Microsub#Clients).


##Acknowledgements
- Relies on David Shanske's [Parse-This](https://github.com/dshanske/parse-this) and Barnaby Walters' [PHP-MF2](https://github.com/microformats/php-mf2)
- Inspiration from Ashton McAllan's [WhisperFollow plugin](https://github.com/acegiak/WhisperFollow), Kyle Mahan's [Woodwind](https://github.com/kylewm/woodwind), and Aaron Parecki's [Aperture](https://aperture.p3k.io)
- Loading spinner created with [loading.io](https://loading.io/spinner/wedges/-rotate-pie-preloader-gif)

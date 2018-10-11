# Aucor Repair Content Image Sizes

**Contributors:** [Teemu Suoranta](https://github.com/TeemuSuoranta)

**Tags:** wordpress, media, image size

**License:** GPLv2 or later

## Description

This is a WP-CLI run to fix changed image sizes inside content.

## The problem

When images are embedded in content, WordPress hardcodes the url to selected image size (for examle image-160x90.jpg). If later on you change the size of "large", "medium" or custom size, regenerating images will not replace these already embedded images. This may leave you with broken images or the old image sizes just won't fit the new content width / design.

## The solution

WordPress has a way (probably not that smart way, but a way) to save what attachemnt ID and size the embedded image is supposed to be. Image markup looks something like this:

```
<img class="size-medium wp-image-1234" src="https://wordpress.local/wp-content/uploads/image-160-90.jpg" alt="" width="160" height="90">
```

From this markup we can figure out that size is supposed to be "medium" and attachment ID is "1234". This gives us tools to go check a new source url for medium sized version of attachment. If the source has changed, this WP-CLI run will replace it. Otherwise it is ignored. The width and height is also replaced.

Notice that this is the base knowledge this plugin needs. If for some reason, some of your images are missing information on size or attachement ID, this run will ignore those images.

## How to use

### 1. Install and activate this plugin

### 2. Regenerate thumbnails, import content etc.

This plugin doesn't really care if you have messed up image sizes before or after activation. It's here just to clean up the mess.

### 3. Run repair

It's wise to take backup of the database before running this fix. This plugin will only alter your database so there is no need to backup uploads for this run.

Basic usage (posts):
```
wp repair-content-image-sizes run
```

Advanced: Choose post type(s)
```
wp repair-content-image-sizes run --post_type=page
wp repair-content-image-sizes run --post_type=post,page,event

```

The run will log all the images changed and warnings (images where getting new image size is broken). The log may look something like this:
```
user@wordpress:/data/wordpress$ wp repair-content-image-sizes run
Repair post #123: Post title
--> Repair image: https://wordpress.local/wp-content/uploads/2016/04/image-200x300.jpg => https://wordpress.local/wp-content/uploads/2016/04/image-333x500.jpg

Repair post #124: Another post title
--> Repair image: https://wordpress.local/wp-content/uploads/2015/11/other-image-300x194.jpg => https://https://wordpress.local/wp-content/uploads/2015/11/other-image-402x260.jpg

Repair post #127: Broken post
--> [Skipped broken image]: https://wordpress.local/wp-content/uploads/2015/01/literally-cant-even-300x225.jpg => https://wordpress.local/wp-content/uploads/2015/01/

Success: 2 repaired, 10 ignored, 1 warnings

```

### 4. Look around

Check that everything looks fine.

### 5. Remove this plugin

You won't need this plugin anymore. Deactivate and remove it. It has done it's duty.

## Missing features

The plugin is MVP and it could have all kinds of cool features. Here is some:

 * Ability to fix caption shortcode width (or remove it completely if that's the smart way)
 * Ability to check post_meta fields

Send PR if you hack together some new feature.

## Changelog

### 0.1

 * It's alive

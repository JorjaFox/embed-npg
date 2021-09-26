# oEmbed Format for NetPhotoGraphics

Enable [oEmbed](https://oembed.com) discovery from image and album pages in NetPhotoGraphics

If your app (WordPress, Drupal, etc) is aware of oEmbed, then you can just paste in the URL of an image or album and it will auto-generate the embed content.

Forked from [ZenPhoto JSON Rest API](https://github.com/deanmoses/zenphoto-json-rest-api), this version is stripped down to only the json we need for oEmbed and less of the rest.

You can test if it's working on [http://debug.iframely.com/](iFramely).

## What works:

* Embedding a single image
* Embedding an album

## What isn't there (yet):

* Share This overlay
* Proper image/favicon detection (shouldn't be theme dependent?)
* Default image for album (if there's no image, there should be a fallback...)
* Customizing the output (i.e. allow a theme to override design)
* Embed for the main gallery page (you want to do this for every page if you're making a full oembed)
* A full on API (i.e. you can't use example.com/oembed?url=URL_TO_EMBED)

I am a _terrible_ designer so I did not make more than a usable first pass for embeds.

## Notes

* Since this uses iframes, if you have `X-Frame-Options: SAMEORIGIN` set, this won't work.
* Yes, this means anyone can embed from your site. If you don't want that, don't use this OR set your x-frame options to whitelist.
* If you use this in a 'nested' WordPress site (as in, your gallery is a subfolder) then WP will decide your embed is WordPress and not NPG which is damn annoying.

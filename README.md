# oEmbed Format for NetPhotoGraphics

Enable [oEmbed](https://oembed.com) discovery from image and album pages in NetPhotoGraphics

If your cms/app (WordPress, Drupal, etc) is aware of oEmbed, then you can just paste in the URL of an image or album and it will auto-generate the embed content.

Forked from [ZenPhoto JSON Rest API](https://github.com/deanmoses/zenphoto-json-rest-api), this version is stripped down to only the json we need for oEmbed and less of the rest.

You can test if it's working on [http://debug.iframely.com/](iFramely).

## What works:

* Embedding a single image - not as a photo though
* Embedding an album
  * If there are no images, it shares album details
  * If there _are_ images, it shows up to 6 images in a grid

## What isn't there (yet):

* Nicer design (I'm a monkey with a crayon)
* Share This overlay
* Customization
  * Allow theme file (`oembed.php`) to override output
* Options
  * Default thumbnail fallback
  * Set gallery icon (defaults to theme file: `/images/oembed.png`)
* Full API
  * Best URL would be something like `example.com/oembed?url=URL_TO_EMBED`
* iframe Security - currently if we set this, links won't open
  * Add `sandbox="allow-scripts allow-top-navigation allow-top-navigation-by-user-activation allow-popups-to-escape-sandbox"`
  * Add  `security="restricted"``

## Warning

Using this plugin means **anyone** can embed from your site. If you don't want that, don't use this OR set your x-frame options to whitelist.

Since this uses iframes, if you have `X-Frame-Options: SAMEORIGIN` set on the server where your gallery is located, this won't work.

### WordPress Gotchas

This is a rare quirk. _IF_ you have your gallery in a subfolder under/beside a WordPress install _AND_ you try to embed the gallery into that WordPress site, you _MAY_ find out WP thinks your embed is WordPress and not NPG.

In my case, I have:

* `example.com` - WordPress
* `example.com/gallery` - NetPhotoGraphics

My 'fix' is a WordPress plugin:

```
add_filter( 'embed_oembed_html', 'npg_wrap_oembed_html', 99, 4 );
}

function npg_wrap_oembed_html( $cached_html, $url, $attr, $post_id ) {
	if ( false !== strpos( $url, '://example.com/gallery' ) ) {
		$cached_html = '<div class="responsive-check">' . $cached_html . '</div>';

		$cached_html = str_replace( 'wp-embedded-content', 'npg-embedded-content', $cached_html );
		$cached_html = str_replace( 'sandbox="allow-scripts"', '', $cached_html );
		$cached_html = str_replace( 'security="restricted"', '', $cached_html );

	}
	return $cached_html;
}
```

Change `'://example.com/gallery'` to the location of your own gallery install.

No I don't like this either, but it was a 'get it done' moment.

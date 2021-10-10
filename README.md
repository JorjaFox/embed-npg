# oEmbed Format for NetPhotoGraphics

Enable [oEmbed](https://oembed.com) discovery from image and album pages in [NetPhotoGraphics](https://github.com/netPhotoGraphics/netPhotoGraphics).

This plugin builds the required settings to permit an oEmbed aware service to intercept the link processing and return an object embedded result to insert into the requesting document.

If your cms/app (WordPress, Drupal, etc) is aware of oEmbed, then you can just paste in the URL of an image or album and it will auto-generate the embed content. For those familiar with WordPress, this means you can paste in a link from any NetPhotoGraphics site using this plugin, and it will automagically embed.

If <i>mod_rewrite</i> is enabled the URLs start with the embed request:

* For an iFrame version of the page: `example.com/embed/albumb/image.html`
* For a json response: `example.com/embed-json/albumb/image.html`

Otherwise use a query parameter appended to the link:

* For an iFrame version of the page: `example.com/albumb/image.html?embed=iFrame`
* For a json response: `example.com/embed-json/albumb/image.html?embed=json`

Forked from [ZenPhoto JSON Rest API](https://github.com/deanmoses/zenphoto-json-rest-api), this version is stripped down to only the json we need for oEmbed and less of the rest.

## Installation & Usage

* Install the file and folder in your `/plugins/` folder
* Activate the plugin

### Customization

* Adjust the iFrame height/width via plugin options
* Override the iFrame design and CSS and icon in your theme folder

To ovveride design, create an _oembed_ folder in your theme folder.

If you wish to replace the plugin's icon, name your icon replacement `icon.png` and place it that folder.

To customize the layout, copy the `iFrame.css` and `iFrame.html` files from the plugin to your theme's `oembed` folder. Modify these files to achieve the results you desire.

**Note:** there are meta-tokens in the `iFrame.html` file that will be dynamically replaced in the actual iFrame output by the specifics of the object you are linking. These meta-tokens are capitalized text enclosed in percent signs. e.g. `%GALLERYTITLE%`

You can view your new ouput on [iFramely](http://debug.iframely.com/).

## Future Work

* Share This overlay
* iframe Security - currently if we set this, links won't open
  * Add `sandbox="allow-scripts allow-top-navigation allow-top-navigation-by-user-activation allow-popups-to-escape-sandbox"`
  * Add  `security="restricted"``

## Warning

Using this plugin means **anyone** can embed from your site. If you don't want that, don't use this OR set your x-frame options to whitelist and only allow who you want.

Since this uses iframes, if you have `X-Frame-Options: SAMEORIGIN` set on the server where your gallery is located, this won't work.

### WordPress Gotchas

This is a rare quirk. _IF_ you have your gallery in a subfolder under/beside a WordPress install _AND_ you try to embed the gallery into that WordPress site, you _MAY_ find out WP thinks your embed defaults to use WordPress' oEmbed settings and not NPG's.

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

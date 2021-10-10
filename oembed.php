<?php

/**
 * An oEmbed API for <b>netPhotoGraphics</b>.
 *
 * <i>oEmbed</i> recognizes modified versions of the standard <b>netPhotoGraphics</b> URL links.
 * It intercepts the link processing and returns an object embedded result to
 * insert into the requesting document
 *
 * If <i>mod_rewrire</i> is enabled the URLs start with the embed request:
 *
 * <dl>
 * 	<dt>For an iFrame version of the page</dt><dd>example.com/embed/albumb/image.html</dd>
 * 	<dt>For a json response</dt><dd>example.com/embed-json/albumb/image.html<\dd> (For a json response)
 * </dl>
 *
 * Otherwise use a query parameter appended to the link:
 *
 * <dl>
 * 	<dt>For an iFrame</dt><dd>example.com/albumb/image.html?embed=iFrame</dd>
 * 	<dt>For a json response</dt><dd>example.com/albumb/image.html?embed=json</dd>
 * </dl>
 *
 * iFrame output can be customizes by providing theme based source files for the <i>icon</i>,
 * the <i>iFrame CSS</i>, and/or the <i>iFrame HTML</i>.
 * Create an <i>oembed</i> folder in your theme folder. If you wish to replace the
 * plugin's icon, name your icon replacement <var>icon.png</var> and place it in the folder.
 * To customize the layout copy the <i>iFrame.css</i> and <i>iFrame.html</i> files from the plugin to
 * your theme <i>oembed</i> folder. Modify these files to achieve the results you desire.
 * <b>Note:</b> there are meta-tokens in the <i>iFrame.html</i> file that will be dynamically replaced
 * in the actual iFrame output by the specifics of the object you are linking. These meta-tokens are
 * capitalized text enclosed in percent signs. e.g. <var>%GALLERYTITLE%</var>
 *
 *
 *
 * Forked from {@link https://github.com/deanmoses/zenphoto-json-rest-api}
 *
 * Original author Dean Moses (deanmoses)
 *
 * @author Mika Epstein (ipstenu) Mika Epstein (ipstenu), Dean Moses (deanmoses)
 * @copyright 2021 by Mika Epstein for use in {@link https://%GITHUB% netPhotoGraphics} and derivatives
 * @package plugins/oEmbed
 * @pluginCategory theme
 * @license GPLv2 (or later)
 * @repository {@link https://github.com/JorjaFox/embed-npg}
 *
 */
// Plugin Headers
$plugin_is_filter = 995 | CLASS_PLUGIN; //	must be high priority so fall thru rewrite rules come first
if (defined('SETUP_PLUGIN')) {
	$plugin_description = gettext('oEmbed API');
	$plugin_version = '0.0.3';
}

//	rewrite rules for cleaner URLs
$_conf_vars['special_pages'][] = array('rewrite' => '^embed/*$',
		'rule' => '%REWRITE% index.php?embed=iFrame [NC]');
$_conf_vars['special_pages'][] = array('rewrite' => '^embed-json/*$',
		'rule' => '%REWRITE% index.php?embed=json [NC]');
$_conf_vars['special_pages'][] = array('rewrite' => '^embed/(.*)/*$',
		'rule' => '%REWRITE% $1?embed=iFrame [NC]');
$_conf_vars['special_pages'][] = array('rewrite' => '^embed-json/(.*)/*$',
		'rule' => '%REWRITE% $1?embed=json [NC]');

/* 	Handle REST API calls during theme script loading (after rwrite rules are processed)
  This is necessary because it sets response headers that are different. */
npgFilters::register('load_theme_script', 'FLF_NGP_OEmbed::execute', 9999);

// Register oEmbed Discovery so WordPress and Drupal can run with this.
npgFilters::register('theme_head', 'FLF_NGP_OEmbed::get_json_oembed');

class FLF_NGP_OEmbed {

	/**
	 * Execute header output for JSON calls.
	 *
	 * @return n/a
	 */
	protected static function execute_headers() {
		header('Content-type: application/json; charset=UTF-8');

		// If the request is coming from a subdomain, send the headers
		// that allow cross domain AJAX.  This is important when the web
		// front end is being served from sub.domain.com but its AJAX
		// requests are hitting an installation on domain.com
		// Browsers send the Origin header only when making an AJAX request
		// to a different domain than the page was served from.  Format:
		// protocol://hostname that the web app was served from.  In most
		// cases it'll be a subdomain like http://cdn.domain.com

		if (isset($_SERVER['HTTP_ORIGIN'])) {
			// The Host header is the hostname the browser thinks it's
			// sending the AJAX request to. In most casts it'll be the root
			// domain like domain.com
			// If the Host is a substring within Origin, Origin is most likely a subdomain
			// Todo: implement a proper 'ends_with'
			if (strpos($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_HOST']) !== false) {

				// Allow CORS requests from the subdomain the ajax request is coming from
				header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");

				// Allow credentials to be sent in CORS requests.
				// Really only needed on requests requiring authentication
				header('Access-Control-Allow-Credentials: true');
			}
		}

		// Add a Vary header so that browsers and CDNs know they need to cache different
		// copies of the response when browsers send different Origin headers.
		// This allows us to have clients on foo.domain.com and bar.domain.com,
		// and the CDN will cache different copies of the response for each of them,
		// with the appropriate Access-Control-Allow-Origin header set.

		/* Allow for multiple Vary headers because other things could be adding a Vary as well. */
		header('Vary: Origin', false);
	}

	/**
	 * This is the function that recognizes and executes an embed request
	 */
	public static function execute() {
		if (isset($_GET['embed'])) {
			switch (strtolower($_GET['embed'])) {
				default:
				case 'iframe':
					//	returns the iFrame
					self::execute_iframe();
					break;
				case 'json':
					//	Returns the oEmbed JSON data.
					self::execute_json();
					break;
			}
		}
	}

	/**
	 * changes link to an oEmbed link
	 *
	 * @param type $link object link
	 * @param type $type iFrame for an iFrame link, json for a json-oembed link
	 * @return string
	 */
	protected static function oEmbedLink($link, $type) {
		switch ($type) {
			case 'iFrame':
				$rewrite = 'embed';
				$plain = 'embed=iFrame';
				break;
			case 'json':
				$rewrite = 'embed-json';
				$plain = 'embed=json';
				break;
		}
		if (MOD_REWRITE) {
			$path = '/' . $rewrite . $link;
		} else {
			if (strpos($link, '?')) {
				$path = $link . '&' . $plain;
			} else {
				$path = $link . '?' . $plain;
			}
		}
		return $path;
	}

	/**
	 * Respond to the request an iframe friendly version of the page.
	 *
	 * This does not return; it exits.
	 */
	protected static function execute_iframe() {
		global $_gallery_page, $_current_album, $_current_image;
		// If the whole thing isn't public, we're stopping.
		if (GALLERY_SECURITY === 'public') {
			switch ($_gallery_page) {
				case 'index.php':
					$ret = self::get_gallery_iframe();
					break;
				case 'album.php':
					$ret = self::get_album_iframe($_current_album);
					break;
				case 'image.php':
					$ret = self::get_image_iframe($_current_image);
					break;
				default:
					//	page not supported
					$ret = self::get_error_iframe(405, gettext('Method not allowed.'));
					break;
			}
		} else {
			$ret = self::get_error_iframe(403, gettext('Access forbidden.'));
		}

		// Return the results to the client in JSON format
		print($ret);

		exit();
	}

	/**
	 * Respond to the request with the JSON code
	 *
	 * This does not return; it exits.
	 */
	protected static function execute_json() {
		global $_gallery_page, $_current_album, $_current_image;

		// Execute the headers
		self::execute_headers();

		// the data structure we will return via JSON
		if (GALLERY_SECURITY === 'public') {
			switch ($_gallery_page) {
				case 'index.php':
					$ret = self::get_gallery_data();
					break;
				case 'album.php':
					$ret = self::get_album_data($_current_album);
					break;
				case 'image.php':
					$ret = self::get_image_data($_current_image);
					break;
				default:
					//	page not supported
					$ret = self::get_error_data(405, gettext('Method not allowed.'));
					break;
			}
		} else {
			$ret = self::get_error_data(403, gettext('Access forbidden.'));
		}

		// Return the results to the client in JSON format
		print( json_encode($ret));
		exit();
	}

	/**
	 * Allow auto discovery
	 * @return html   header for posts.
	 */
	public static function get_json_oembed() {
		global $_gallery_page, $_current_album, $_current_image;

		switch ($_gallery_page) {
			case 'index.php':
				$canonicalurl = getGalleryIndexURL();
				break;
			case 'album.php':
				$canonicalurl = $_current_album->getLink();
				break;
			case 'image.php':
				$canonicalurl = $_current_image->getLink();
				break;
		}

		if (isset($canonicalurl)) {
			$meta = '<link rel="alternate" type="application/json+oembed" href="' . FULLHOSTPATH . self::oEmbedLink($canonicalurl, 'json') . '" />';
			echo $meta;
		}
	}

	/**
	 * Returns an iFrame for the gallery
	 *
	 * @global type $_gallery
	 * @global type $_current_image
	 * @global type $_current_page
	 * @return type
	 */
	protected static function get_gallery_iframe() {
		global $_gallery;

		// If the album's private, we bail.
		if (!checkAccess()) {
			return self::get_error_iframe(403, gettext('Access forbidden.'));
		}

		// Album URL
		$gallery_url = FULLHOSTPATH . getGalleryIndexURL();

		// Default description
		$description = '<p>' . "\n" . shortenContent($_gallery->getDesc(), 300, '...') . "\n" . '</p>';

		if ($_gallery->getNumAlbums()) {

			// build an array of album thumgs
			$thumbs = array();

			// Get all the albums sorted by last change date
			$_gallery->setSortType('lastchange', 'album');
			$_gallery->setSortDirection(1, 'album');

			$get_albums = array_slice($_gallery->getAlbums(), 0, 4);

			foreach ($get_albums as $filename) {

				// Create Image Object and get thumb:
				$albumObj = newAlbum($filename);
				$thumbs[] = array(
						'thumb' => $albumObj->getThumb(),
						'url' => $albumObj->getLink(),
						'title' => $albumObj->getTitle()
				);
			}

			if ($thumbs) {
				// Start the build...
				$description .= '<div class="npg-embed-row">' . "\n" .
								gettext('albums') . "\n" .
								'<div class="npg-embed-column">' . "\n";

				// for each image, we want to craft the output.
				foreach ($thumbs as $one_thumb) {
					$description .= '<a href="' . FULLHOSTPATH . $one_thumb['url'] . '" target="_top" title="' . html_encode($one_thumb['title']) . '" >' . "\n" .
									'<img class="npg-embed-image" src="' . FULLHOSTPATH . html_encode($one_thumb['thumb']) . "\n" .
									'" />' . "\n" .
									"</a>\n";
				}

				$description .= '</div>' . "\n" . '</div>';
			}
		}

		// Build the count of images and subalbums ...
		if ($_gallery->getNumAlbums() || $_gallery->getNumImages()) {
			$counts = ' (';
			if ($c = $_gallery->getNumAlbums()) {
				$counts .= sprintf(ngettext('%1$s album', '%1$s albums', $c), $c);
			}
			if ($_gallery->getNumAlbums() && $_gallery->getNumImages()) {
				$counts .= ' and ';
			}
			if ($c = $_gallery->getNumImages()) {
				$counts .= sprintf(ngettext('%1$s image', '%1$s images', $c), $c);
			}
			$counts .= ')';
		} else {
			$counts = '';
		}

		// Array with the data we need:
		$ret = array(
				'url_thumb' => $_gallery->getSiteLogo(),
				'url' => $gallery_url,
				'url_title' => gettext('Visit the gallery'),
				'thumb_size' => [78, 282],
				'share_code' => '', // output to share via html or URL
				'title' => $_gallery->getTitle() . $counts,
				'content' => $description
		);

		$iframe = self::use_default_iframe($ret);

		return $iframe;
	}

	/**
	 * Returns an iFrame for an album
	 *
	 * @global type $_gallery
	 * @global type $_current_image
	 * @global type $_current_page
	 * @param type $album
	 * @return type
	 */
	protected static function get_album_iframe($album) {
		global $_gallery, $_current_image, $_current_page;

		// If the album's private, we bail.
		if (!$album->checkAccess()) {
			return self::get_error_iframe(403, gettext('Access forbidden.'));
		}

		// Default description
		$description = '<p>' . "\n" . shortenContent($album->getDesc(), 300, '...') . "\n" . '</p>';

		// Featured thumbnail...
		$thumb_image = $album->getAlbumThumbImage();
		$thumbnail_url = $thumb_image->getThumb();

		// Album URL
		$album_url = FULLHOSTPATH . $album->getLink();

		if ($album->getNumAlbums()) {

			// build an array of album thumgs
			$thumbs = array();

			// Get all the albums sorted by last change date
			$album->setSortType('lastchange', 'album');
			$album->setSortDirection(1, 'album');

			$get_albums = array_slice($album->getAlbums(), 0, 4);

			foreach ($get_albums as $filename) {

				// Create Image Object and get thumb:
				$albumObj = newAlbum($filename);
				$thumbs[] = array(
						'thumb' => $albumObj->getThumb(),
						'url' => $albumObj->getLink(),
						'title' => $albumObj->getTitle()
				);
			}

			if ($thumbs) {

				// Start the build...
				$description .= '<div class="npg-embed-row">' . "\n" . gettext('subalbums') . "\n" . '<div class="npg-embed-column">' . "\n";

				// for each image, we want to craft the output.
				foreach ($thumbs as $one_thumb) {
					$description .= '<a href="' . FULLHOSTPATH . $one_thumb['url'] . '" target="_top" title="' . html_encode($one_thumb['title']) . '" >' . "\n" .
									'<img class="npg-embed-image" src="' . FULLHOSTPATH . html_encode($one_thumb['thumb']) . '" />' . "\n" .
									"</a>\n";
				}

				$description .= '</div>' . "\n" . '</div>' . "\n";
			}
		}

		if ($album->getNumImages()) {

			// We have images, so we show something different.
			// The description is an image grid!
			// Build an array of images
			$images = array();

			// Get all the images sorted by last change date
			$album->setSortType('lastchange', 'album');
			$album->setSortDirection(1, 'album');
			$get_images = array_slice($album->getImages(), 0, 4);
			foreach ($get_images as $filename) {

				// Create Image Object and get thumb:
				$image = newImage($album, $filename);
				$images[] = array(
						'thumb' => $image->getThumb(),
						'url' => $image->getLink(),
						'title' => $image->getTitle()
				);
			}

			if ($images) {

				// Start the build...
				$description .= '<div class="npg-embed-row">' . "\n" . gettext('images') . "\n" . '<div class="npg-embed-column">' . "\n";

				// for each image, we want to craft the output.
				foreach ($images as $one_image) {
					$description .= '<a href="' . FULLHOSTPATH . $one_image['url'] . '" target="_top" title="' . html_encode($one_image['title']) . '">' . "\n" .
									'<img class="npg-embed-image" src="' . FULLHOSTPATH . html_encode($one_image['thumb']) . '" />' . "\n" .
									"</a>\n";
				}

				$description .= '</div>' . "\n" . '</div>';
			}
		}

		// Build the count of images and subalbums ...
		if ($album->getNumAlbums() || $album->getNumImages()) {
			$counts = ' (';
			if ($c = $album->getNumAlbums()) {
				$counts .= sprintf(ngettext('%1$s sub-album', '%1$s sub-albums', $c), $c);
			}
			if ($album->getNumAlbums() && $album->getNumImages()) {
				$counts .= ' and ';
			}
			if ($c = $album->getNumImages()) {
				$counts .= sprintf(ngettext('%1$s image', '%1$s images', $c), $c);
			}
			$counts .= ')';
		} else {
			$counts = '';
		}

		// Array with the data we need:
		$ret = array(
				'url_thumb' => $thumbnail_url,
				'url' => $album_url,
				'url_title' => gettext('Visit the album page'),
				'thumb_size' => getSizeDefaultThumb(),
				'width' => (int) getOption('image_size'),
				'height' => floor(( getOption('image_size') * 24 ) / 36),
				'share_code' => '', // output to share via html or URL
				'title' => $album->getTitle() . $counts,
				'content' => $description
		);

		$iframe = self::use_default_iframe($ret);

		return $iframe;
	}

	/**
	 * Returns an iFrame for an image
	 *
	 * @global type $_gallery
	 * @param type $image
	 * @return type
	 */
	protected static function get_image_iframe($image) {
		global $_gallery;

		if (!$image->checkAccess()) {
			return self::get_error_data(403, gettext('Access forbidden.'));
		}

		// Base description.
		$description = '<p>' . "\n" . shortenContent($image->getDesc(), 300, '...') . "\n" . '</p>';

		// Array with the data we need:
		$ret = array(
				'url_thumb' => FULLHOSTPATH . $image->getThumb(),
				'url' => FULLHOSTPATH . $image->getLink(),
				'url_title' => gettext('Visit the image page'),
				'thumb_size' => getSizeDefaultThumb(),
				'width' => (int) $image->getWidth(),
				'height' => (int) $image->getHeight(),
				'share_code' => '', // output to share via html or URL
				'title' => $image->getTitle(),
				'content' => $description
		);

		$iframe = self::use_default_iframe($ret);

		return $iframe;
	}

	/**
	 * Return array containing info about the gallery.
	 *
	 * @return JSON-ready array
	 */
	protected static function get_gallery_data() {
		global $_gallery;

		if (!checkAccess()) {
			return self::get_error_data(403, gettext('Access forbidden.'));
		}

		$html = '<iframe src="' . FULLHOSTPATH . self::oEmbedLink(getGalleryIndexURL(), 'iFrame') . '" width="600" height="338" title="' . html_encode($_gallery->getTitle()) . '" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="npg-embedded-content"></iframe>';

		// the data structure we will be returning
		$ret = array(
				'version' => '1.0',
				'provider_name' => $_gallery->getTitle() . ' - ' . getGalleryTitle(),
				'provider_url' => FULLHOSTPATH . getGalleryIndexURL(),
				'title' => $_gallery->getTitle(),
				'type' => 'rich',
				'width' => '600',
				'height' => '300',
				'html' => $html,
				'thumbnail_url' => FULLHOSTPATH . $_gallery->getSiteLogo(),
				'thumbnail_width' => 282,
				'thumbnail_height' => 78,
				'content' => html_encode($_gallery->getDesc()),
		);

		return $ret;
	}

	/**
	 * Return array containing info about an album.
	 *
	 * @param obj $album Album object
	 * @return JSON-ready array
	 */
	protected static function get_album_data($album) {
		global $_current_image;

		if (!$album->checkAccess()) {
			return self::get_error_data(403, gettext('Access forbidden.'));
		}

		$html = '<iframe src="' . FULLHOSTPATH . self::oEmbedLink($album->getLink(), 'iFrame') . '" width="600" height="338" title="' . html_encode($album->getTitle()) . '" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="npg-embedded-content"></iframe>';

		// Get image size
		$image_size = (int) getOption('image_size');
		$thumb_size = getSizeDefaultThumb();

		// Featured thumbnail...
		$thumb_image = $album->getAlbumThumbImage();
		$thumbnail_url = $thumb_image->getThumb();

		// the data structure we will be returning
		$ret = array(
				'version' => '1.0',
				'provider_name' => $album->getTitle() . ' - ' . getGalleryTitle(),
				'provider_url' => FULLHOSTPATH . getGalleryIndexURL(),
				'title' => $album->getTitle(),
				'type' => 'rich',
				'width' => '600',
				'height' => '300',
				'html' => $html,
				'thumbnail_url' => FULLHOSTPATH . $thumbnail_url,
				'thumbnail_width' => $thumb_size[0],
				'thumbnail_height' => $thumb_size[1],
				'content' => html_encode($album->getDesc()),
		);

		return $ret;
	}

	/**
	 * Return array containing info about an image.
	 *
	 * @param obj $image Image object
	 * @param boolean $verbose true: return a larger set of the image's information
	 * @return JSON-ready array
	 */
	protected static function get_image_data($image) {

		if (!$image->checkAccess()) {
			return self::get_error_data(403, gettext('Access forbidden.'));
		}

		// Get image size
		$sizes = getSizeDefaultThumb();

		$html = '<iframe src="' . FULLHOSTPATH . self::oEmbedLink($image->getLink(), 'iFrame') . '" width="600" height="338" title="' . html_encode($image->getTitle()) . '" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="npg-embedded-content"></iframe>';

		// the data structure we will be returning
		$ret = array(
				'version' => '1.0',
				'provider_name' => $image->getTitle() . ' - ' . getGalleryTitle(),
				'provider_url' => FULLHOSTPATH . getGalleryIndexURL(),
				'title' => $image->getTitle(),
				'type' => 'rich',
				'width' => '600',
				'height' => '300',
				'html' => $html,
				'thumbnail_url' => FULLHOSTPATH . $image->getThumb(),
				'thumbnail_width' => '" ' . $sizes[0] . ' "',
				'thumbnail_height' => $sizes[1],
				'content' => html_encode($image->getDesc()),
		);

		return $ret;
	}

	/**
	 * Return array with error information
	 *
	 * @param int $error_code numeric HTTP error code like 404
	 * @param string $error_message message to return to the client
	 * @return JSON-ready array
	 */
	protected static function get_error_data($error_code, $error_message) {
		http_response_code($error_code);
		$ret = array(
				'error' => true,
				'status' => $error_code,
				'message' => $error_message
		);

		return $ret;
	}

	/**
	 * Returns an iFrame with an error information
	 *
	 * @param type $error_code numeric HTTP error code like 404
	 * @param type $error_message message to display
	 * @return string iFrame
	 *
	 */
	protected static function get_error_iframe($error_code, $error_message) {
		// Array with the data we need:
		$ret = array(
				'url_thumb' => FULLHOSTPATH . '/' . CORE_FOLDER . '/images/err-broken-page.png',
				'url' => '',
				'url_title' => '',
				'thumb_size' => [100, 100],
				'share_code' => '', // output to share via html or URL
				'title' => $error_code,
				'content' => $error_message
		);

		$iframe = self::use_default_iframe($ret);

		return $iframe;
	}

	/**
	 * Default iFrame
	 * @return html
	 */
	protected static function use_default_iframe($ret) {
		global $_gallery;

		// Default icon
		$gallery_icon = getPlugin('oembed/icon.png', TRUE, FULLWEBPATH);

		// Featured Image and description
		if ($ret['url_thumb']) {
			$featured_image = '<div class="npg-embed-featured-image square">
				<a href="' . $ret['url'] . '" target="_top">
					<img width="' . $ret['thumb_size'][0] . '" height="' . $ret['thumb_size'][1] . '" src="' . $ret['url_thumb'] . '" title="' . $ret['url_title'] . '" />
				</a>
			</div>';
		} else {
			$featured_image = '';
		}

		// Get CSS
		ob_start();
		scriptLoader(getPlugin('oembed/iFrame.css', TRUE));
		$iFrame_css = rtrim(ob_get_clean(), "\n");

		// Build the iframe.
		$inserts = array(
				'%GALLERYTITLE%' => getBareGalleryTitle(),
				'%GALLERYINDEXURL%' => FULLHOSTPATH . html_encode(getGalleryIndexURL()),
				'%GALLERY_URL_TITLE%' => gettext('Visit the gallery'),
				'%GALLERYICON%' => $gallery_icon,
				'%TITLE%' => $ret['title'],
				'%IFRAMECSS%' => $iFrame_css,
				'%FEATURED_IMAGE%' => $featured_image,
				'%OBJECT_URL%' => $ret['url'],
				'%CONTENT%' => $ret['content'],
				'%BUTTONTEXT%' => html_encodeTagged($ret['share_code'])
		);

		$iFrame = file_get_contents(getPlugin('oembed/iFrame.html', TRUE));
		return strtr($iFrame, $inserts);
	}

}

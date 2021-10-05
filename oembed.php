<?php

/**
 * An oEmbed API for NetPhotoGraphics.
 *
 * Looks for a query string parameters and returns the custom formatted response.
 *
 * Params:
 *  ?embed
 *    - i.e. example.com/albumb/image.html?embed
 *    An iframe formatted version of the page
 *
 *  ?json-oembed
 *    - i.e. example.com/albumb/image.html?json-oembed
 *    The oEmbed formatted JSON response
 *
 * -----
 *
 * This could be forked and turned into a better sort of global oEmbed api.
 * I recommend including:
 * - A way to override the iframe design
 * - An oEmbed for the main gallery page
 * - Maybe a way for an album page to oembed a number of images?
 *
 * Forked from {@link https://github.com/deanmoses/zenphoto-json-rest-api}
 * Original author Dean Moses (deanmoses)
 *
 * @author Mika Epstein (ipstenu)
 * @copyright 2021 by Mika Epstein for use in {@link https://%GITHUB% netPhotoGraphics} and derivatives
 * @package plugins/oEmbed
 * @pluginCategory development
 * @license GPLv2 (or later)
 * @repository {@link https://github.com/JorjaFox/embed-npg}
 *
 */
// Plugin Headers
$plugin_is_filter = 5 | CLASS_PLUGIN;
if (defined('SETUP_PLUGIN')) {
	$plugin_description = gettext('oEmbed API');
	$plugin_author = 'Mika Epstein (ipstenu), Dean Moses (deanmoses)';
	$plugin_version = '0.0.1';
}

//	rewrite rules for cleaner URLs
$_conf_vars['special_pages'][] = array('rewrite' => '^oembed/(.*)/*$',
		'rule' => '%REWRITE% $1?embed [NC,L,QSA]');
$_conf_vars['special_pages'][] = array('rewrite' => '^json-oembed/(.*)/*$',
		'rule' => '%REWRITE% $1?json-oembed [NC,L,QSA]');

// Handle REST API calls before anything else
// This is necessary because it sets response headers
// that are different from normal ones
// Returns the iframe embed
if (!OFFSET_PATH && isset($_GET['embed'])) {
	npgFilters::register('load_theme_script', 'FLF_NGP_OEmbed::execute_iframe', 9999);
}

// Returns the oEmbed JSON data.
if (!OFFSET_PATH && isset($_GET['json-oembed'])) {
	npgFilters::register('load_theme_script', 'FLF_NGP_OEmbed::execute_json', 9999);
}

// Register oEmbed Discovery so WordPress and Drupal can run with this.
npgFilters::register('theme_head', 'FLF_NGP_OEmbed::get_json_oembed');

class FLF_NGP_OEmbed {

	public function __construct() {
		$this->custom_theme = SERVERPATH . '/' . THEMEFOLDER . '/' . $_gallery->getCurrentTheme() . '/embed.php';
	}

	/**
	 * Execute header output.
	 *
	 * @return n/a
	 */
	public static function execute_headers() {
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
	 * Respond to the request an iframe friendly version of the page.
	 *
	 * This does not return; it exits.
	 */
	public static function execute_iframe() {
		global $_gallery_page, $_gallery, $_current_album, $_current_image;

		if (GALLERY_SECURITY === 'public') {
			switch ($_gallery_page) {
				case 'album.php':
					$ret = self::get_album_iframe($_current_album);
					break;
				case 'image.php':
					$ret = self::get_image_iframe($_current_image);
					break;
			}
		} else {
			$ret = self::get_error_data(403, gettext('Access forbidden.'));
		}

		// Return the results to the client in JSON format
		print( $ret);

		exit();
	}

	/**
	 * Respond to the request with the JSON code
	 *
	 * This does not return; it exits.
	 */
	public static function execute_json() {
		global $_gallery_page, $_current_album, $_current_image;

		// Execute the headers
		self::execute_headers();

		// the data structure we will return via JSON
		$ret = array();

		if (GALLERY_SECURITY === 'public') {
			switch ($_gallery_page) {
				case 'album.php':
					$ret = self::get_album_data($_current_album);
					break;
				case 'image.php':
					$ret = self::get_image_data($_current_image);
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
			case 'album.php':
				$canonicalurl = FULLHOSTPATH . $_current_album->getLink();
				break;
			case 'image.php':
				$canonicalurl = FULLHOSTPATH . $_current_image->getLink();
				break;
		}

		if (isset($canonicalurl)) {
			$meta = '<link rel="alternate" type="application/json+oembed" href="' . $canonicalurl . '?json-oembed" />';
			echo $meta;
		}
	}

	// this needs to return the 'album' embed
	public static function get_album_iframe($album) {
		global $_gallery, $_current_image;

		if (!$album) {
			return;
		}

		if (!$album->checkAccess()) {
			return self::get_error_data(403, gettext('Access forbidden.'));
		}

		// Featured thumbnail...
		$thumbnail_url = ''; // need a placeholder URL here...
		$thumb_image = $album->getAlbumThumbImage();
		if ($thumb_image) {
			$thumbnail_url = $thumb_image->getThumb();
		}

		// Number of images in album AND number of subalbums
		$counts = '';

		if ($album->getNumAlbums() !== 0) {
			$counts .= $album->getNumAlbums() . ' sub-albums. ';
		}
		if ($album->getNumImages() !== 0) {
			$counts .= $album->getNumImages() . ' images.';
		}

		// Dates
		$date_created = $album->getDateTime();
		$date_updated = $album->getUpdatedDate();

		// Build the description:
		$description = '<p>' . $album->getDesc() . '</p><p>' . $counts . '</p><p>Created ' . $date_created . ' // Updated: ' . $date_updated . '</p>';

		// Array with the data we need:
		$ret = array(
				'url_thumb' => FULLHOSTPATH . $thumbnail_url,
				'url' => FULLHOSTPATH . $album->getLink(),
				'thumb_size' => getSizeDefaultThumb(),
				'width' => (int) getOption('image_size'),
				'height' => floor(( getOption('image_size') * 24 ) / 36),
				'share_code' => '', // output to share via html or URL
				'title' => $album->getTitle(),
				'desc' => $description,
		);

		$iframe = self::use_default_iframe($ret);
		$iframe = str_replace(array('\r', '\n'), '', $iframe);

		return $iframe;
	}

	// this needs to return the 'main' embed
	public static function get_image_iframe($image) {
		global $_gallery;

		if (!$image) {
			return;
		}

		if (!$image->checkAccess()) {
			return self::get_error_data(403, gettext('Access forbidden.'));
		}

		$description = $image->getDesc() . '<p>' . $image->getCredit() . '</p><p>' . $image->getCopyright() . '</p>';

		// Array with the data we need:
		$ret = array(
				'url_thumb' => FULLHOSTPATH . $image->getThumb(),
				'url' => FULLHOSTPATH . $image->getLink(),
				'thumb_size' => getSizeDefaultThumb(),
				'width' => (int) $image->getWidth(),
				'height' => (int) $image->getHeight(),
				'share_code' => '', // output to share via html or URL
				'title' => $image->getTitle(),
				'desc' => $description,
		);

		$iframe = self::use_default_iframe($ret);
		$iframe = str_replace(array('\r', '\n'), '', $iframe);

		return $iframe;
	}

	/**
	 * Return array containing info about an album.
	 *
	 * @param obj $album Album object
	 * @return JSON-ready array
	 */
	public static function get_album_data($album) {
		global $_current_image;

		if (!$album) {
			return;
		}

		if (!$album->checkAccess()) {
			return self::get_error_data(403, gettext('Access forbidden.'));
		}

		//$html = '<iframe sandbox="allow-scripts allow-top-navigation allow-top-navigation-by-user-activation allow-popups-to-escape-sandbox" security="restricted" src="' . FULLHOSTPATH . $album->getLink() . '?embed" width="600" height="300" title="' . html_encode( $album->getTitle() ) . '" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="npg-embedded-content"></iframe>';

		$html = '<iframe src="' . FULLHOSTPATH . $album->getLink() . '?embed" width="600" height="300" title="' . html_encode($album->getTitle()) . '" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="npg-embedded-content"></iframe>';

		// Get image size
		$image_size = (int) getOption('image_size');
		$thumb_size = getSizeDefaultThumb();

		// Featured thumbnail...
		$thumbnail_url = ''; // need a placeholder URL here...
		$thumb_image = $album->getAlbumThumbImage();
		if ($thumb_image) {
			$thumbnail_url = $thumb_image->getThumb();
		}

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
				'description' => html_encode($album->getDesc()),
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
	public static function get_image_data($image) {
		if (!$image) {
			return;
		}

		if (!$image->checkAccess()) {
			return self::get_error_data(403, gettext('Access forbidden.'));
		}

		// Get image size
		$sizes = getSizeDefaultThumb();

		// get HTML
		// $html = '<iframe sandbox="allow-scripts allow-top allow-top-navigation-by-user-activation allow-popups-to-escape-sandbox" security="restricted" src="' . FULLHOSTPATH . $image->getLink() . '?embed" width="600" height="338" title="' . html_encode( $image->getTitle() ) . '" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="npg-embedded-content"></iframe>';

		$html = '<iframe src="' . FULLHOSTPATH . $image->getLink() . '?embed" width="600" height="338" title="' . html_encode($image->getTitle()) . '" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" class="npg-embedded-content"></iframe>';

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
				'description' => html_encode($image->getDesc()),
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
	public static function get_error_data($error_code, $error_message = '') {
		$ret = array();

		http_response_code($error_code);
		$ret['error'] = true;
		$ret['status'] = $error_code;
		if ($error_message) {
			$ret['message'] = $error_message;
		}

		return $ret;
	}

	/**
	 * Default iFrame
	 * @return html
	 */
	public static function use_default_iframe($ret) {
		global $_gallery;

		$gallery_icon = getPlugin('oembed/icon.png', TRUE, FULLWEBPATH);
		ob_start();
		scriptLoader(getPlugin('oembed/iFrame.css', TRUE));
		$iFrame_css = ob_get_clean();
		ob_end_clean();
		$iframe = '<!DOCTYPE html>
			<html lang="en-US" class="no-js">
			<head>
				<title>' . $ret['title'] . ' | ' . html_encode(getGalleryTitle()) . '</title>
				<base target="_top" />
				<meta http-equiv="X-UA-Compatible" content="IE=edge">
				' . $iFrame_css .
						'				<meta name="robots" content="noindex, follow"/>
				<link rel="canonical" href="' . $ret['url'] . '" />
			</head>
			<body class="npg npg-embed-responsive">
				<div class="npg-embed">
					<div class="npg-embed-featured-image square">
						<a href="' . $ret['url'] . '" target="_top">
							<img width="' . $ret['thumb_size'][0] . '" height="' . $ret['thumb_size'][1] . '" src="' . $ret['url_thumb'] . '"/>
						</a>
					</div>

					<p class="npg-embed-heading">
						<a href="' . $ret['url'] . '" target="_top">' . $ret['title'] . '</a>
					</p>

					<div class="npg-embed-excerpt">
						' . $ret['desc'] . '
					</div>

					<div class="npg-embed-footer">
						<div class="npg-embed-site-title">
							<a href="' . FULLHOSTPATH . html_encode(getGalleryIndexURL()) . '" target="_top">
								<img src="' . $gallery_icon . '" width="32" height="32" alt="" class="npg-embed-site-icon"/>
								<span>' . html_encode(getBareGalleryTitle()) . '</span>
							</a>
						</div>
						<div class="npg-embed-meta">
							<div class="npg-embed-share">
								<!--
								<button type="button" class="npg-embed-share-dialog-open" aria-label="Open sharing dialog">' . $ret['share_code'] . '</button>
								-->
							</div>
						</div>
					</div>
				</div>
			</body>
			</html>';
		return $iframe;
	}

}

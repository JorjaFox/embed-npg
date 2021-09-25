# embed-npg

Embed NetPhotoGraphics albums and photos.

1. NPG builds a special version of a post (album or image) that is designed to live in an iframe.
2. NPG has a 'get' endpoint that knows to take parameters (url, maxwidth, maxheight, and format) and kicks out an JSON response
3. That JSON response includes the HTML of the iframe (based on item 1)

The rest should be WP good to go?

Fork https://github.com/deanmoses/zenphoto-json-rest-api ?

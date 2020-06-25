<?php


/**
 * Service Worker Tempalte
 *
 * @return (string) Contents to be written to superpwa-sw.js
 * 
 * @since 1.0
 * @since 1.7 added filter superpwa_sw_template
 * @since 1.9 added filter superpwa_sw_files_to_cache
 */
function superpwa_sw_template() {
	
	// Get Settings
	$settings = superpwa_get_settings();
	
	// Start output buffer. Everything from here till ob_get_clean() is returned
	ob_start();  ?>
'use strict';

/**
 * Service Worker of SuperPWA
 * To learn more and add one to your website, visit - https://superpwa.com
 */
 
const cacheName = '<?php echo parse_url( get_bloginfo( 'url' ), PHP_URL_HOST ) . '-superpwa-' . SUPERPWA_VERSION; ?>';
const startPage = '<?php echo superpwa_get_start_url(); ?>';
const offlinePage = '<?php echo superpwa_get_offline_page(); ?>';
const filesToCache = [<?php echo apply_filters( 'superpwa_sw_files_to_cache', 'startPage, offlinePage' ); ?>];
const neverCacheUrls = [<?php echo apply_filters( 'superpwa_sw_never_cache_urls', '/\/wp-admin/,/\/wp-login/,/preview=true/' ); ?>];

// Install
self.addEventListener('install', function(e) {
	console.log('SuperPWA service worker installation');
	e.waitUntil(
		caches.open(cacheName).then(function(cache) {
			console.log('SuperPWA service worker caching dependencies');
			filesToCache.map(function(url) {
				return cache.add(url).catch(function (reason) {
					return console.log('SuperPWA: ' + String(reason) + ' ' + url);
				});
			});
		})
	);
});

// Activate
self.addEventListener('activate', function(e) {
	console.log('SuperPWA service worker activation');
	e.waitUntil(
		caches.keys().then(function(keyList) {
			return Promise.all(keyList.map(function(key) {
				if ( key !== cacheName ) {
					console.log('SuperPWA old cache removed', key);
					return caches.delete(key);
				}
			}));
		})
	);
	return self.clients.claim();
});

// Fetch
self.addEventListener('fetch', function(e) {
	
	// Return if the current request url is in the never cache list
	if ( ! neverCacheUrls.every(checkNeverCacheList, e.request.url) ) {
	  console.log( 'SuperPWA: Current request is excluded from cache.' );
	  return;
	}
	
	// Return if request url protocal isn't http or https
	if ( ! e.request.url.match(/^(http|https):\/\//i) )
		return;
	
	// Return if request url is from an external domain.
	if ( new URL(e.request.url).origin !== location.origin )
		return;
	
	// For POST requests, do not use the cache. Serve offline page if offline.
	if ( e.request.method !== 'GET' ) {
		e.respondWith(
			fetch(e.request).catch( function() {
				return caches.match(offlinePage);
			})
		);
		return;
	}
	
	// Revving strategy
	if ( e.request.mode === 'navigate' && navigator.onLine ) {
		e.respondWith(
			fetch(e.request).then(function(response) {
				return caches.open(cacheName).then(function(cache) {
					cache.put(e.request, response.clone());
					return response;
				});  
			})
		);
		return;
	}

	e.respondWith(
		caches.match(e.request).then(function(response) {
			return response || fetch(e.request).then(function(response) {
				return caches.open(cacheName).then(function(cache) {
					cache.put(e.request, response.clone());
					return response;
				});  
			});
		}).catch(function() {
			return caches.match(offlinePage);
		})
	);
});

// Check if current url is in the neverCacheUrls list
function checkNeverCacheList(url) {
	if ( this.match(url) ) {
		return false;
	}
	return true;
}
<?php return apply_filters( 'superpwa_sw_template', ob_get_clean() );
}


/**
 * Delete Service Worker
 *
 * @return true on success, false on failure
 * 
 * @author Arun Basil Lal
 * 
 * @since 1.0
 */
function superpwa_delete_sw() {
	return superpwa_delete( superpwa_sw( 'abs' ) );
}

/**
 * Add images from offline page to filesToCache
 * 
 * If the offlinePage set by the user contains images, they need to be cached during sw install. 
 * For most websites, other assets (css, js) would be same as that of startPage which would be cached
 * when user visits the startPage the first time. If not superpwa_sw_files_to_cache filter can be used.
 * 
 * @param (string) $files_to_cache Comma separated list of files to cache during service worker install
 * 
 * @return (string) Comma separated list with image src's appended to $files_to_cache
 * 
 * @since 1.9
 */
function superpwa_offline_page_images( $files_to_cache ) {
	
	// Get Settings
	$settings = superpwa_get_settings();
	
	// Retrieve the post
	$post = get_post( $settings['offline_page'] );
	
	// Return if the offline page is set to default
	if( $post === NULL ) {
		return $files_to_cache;
	}
	
	// Match all images
	preg_match_all( '/<img[^>]+src="([^">]+)"/', $post->post_content, $matches );
	
	// $matches[1] will be an array with all the src's
	if( ! empty( $matches[1] ) ) {
		return superpwa_httpsify( $files_to_cache . ', \'' . implode( '\', \'', $matches[1] ) . '\'' );
	}
	
	return $files_to_cache;
}
add_filter( 'superpwa_sw_files_to_cache', 'superpwa_offline_page_images' );

/**
 * Get offline page
 * 
 * @return (string) the URL of the offline page.
 * 
 * @author Arun Basil Lal
 * 
 * @since 2.0.1
 */
function superpwa_get_offline_page() {
	
	// Get Settings
	$settings = superpwa_get_settings();
	
	return get_permalink( $settings['offline_page'] ) ? superpwa_httpsify( get_permalink( $settings['offline_page'] ) ) : superpwa_httpsify( superpwa_get_bloginfo( 'sw' ) );
}

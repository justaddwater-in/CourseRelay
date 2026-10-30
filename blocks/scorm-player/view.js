/**
 * CourseRelay — SCORM block popup (modal) player.
 *
 * Opens the SCORM launch page in a full-window dialog. Closing the dialog ends
 * the SCORM session first (so the final data is saved), then tells the lesson
 * page to re-check completion.
 *
 * GPL-2.0+
 */
( function () {
	'use strict';

	var overlay = null;
	var frame = null;
	var lastFocus = null;

	function endSession() {
		// The launch page (same origin) exposes the SCORM API; finish it so the
		// last SetValue data is persisted before the iframe is removed.
		try {
			var w = frame && frame.contentWindow;
			if ( ! w ) {
				return;
			}
			if ( w.API && typeof w.API.LMSFinish === 'function' ) {
				w.API.LMSFinish( '' );
			} else if ( w.API_1484_11 && typeof w.API_1484_11.Terminate === 'function' ) {
				w.API_1484_11.Terminate( '' );
			}
		} catch ( e ) {}
	}

	function close() {
		if ( ! overlay ) {
			return;
		}
		endSession();
		document.removeEventListener( 'keydown', onKeydown, true );
		overlay.parentNode.removeChild( overlay );
		overlay = null;
		frame = null;
		document.body.classList.remove( 'courserelay-modal-open' );
		if ( lastFocus && typeof lastFocus.focus === 'function' ) {
			lastFocus.focus();
		}
		window.dispatchEvent( new CustomEvent( 'courserelay:closed' ) );
	}

	function onKeydown( e ) {
		if ( e.key === 'Escape' || e.keyCode === 27 ) {
			e.preventDefault();
			close();
		}
	}

	function getSafeFrameSrc( rawSrc ) {
		if ( ! rawSrc ) {
			return null;
		}
		try {
			var parsed = new URL( rawSrc, window.location.href );
			if ( parsed.protocol !== 'http:' && parsed.protocol !== 'https:' ) {
				return null;
			}
			return parsed.href;
		} catch ( e ) {
			return null;
		}
	}

	function open( btn ) {
		if ( overlay ) {
			return;
		}
		var src = getSafeFrameSrc( btn.getAttribute( 'data-courserelay-src' ) );
		if ( ! src ) {
			return;
		}
		var title = btn.getAttribute( 'data-courserelay-title' ) || '';
		var closeLabel = btn.getAttribute( 'data-courserelay-close' ) || 'Close';
		lastFocus = btn;

		overlay = document.createElement( 'div' );
		overlay.className = 'courserelay-modal';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-label', title );

		var box = document.createElement( 'div' );
		box.className = 'courserelay-modal__box';

		var bar = document.createElement( 'div' );
		bar.className = 'courserelay-modal__bar';

		var heading = document.createElement( 'span' );
		heading.className = 'courserelay-modal__title';
		heading.textContent = title;

		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'courserelay-modal__close';
		closeBtn.textContent = '\u00d7 ' + closeLabel;
		closeBtn.addEventListener( 'click', close );

		bar.appendChild( heading );
		bar.appendChild( closeBtn );

		frame = document.createElement( 'iframe' );
		frame.className = 'courserelay-modal__frame';
		frame.setAttribute( 'title', title );
		frame.setAttribute( 'allowfullscreen', '' );
		frame.setAttribute( 'sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups allow-presentation' );
		frame.setAttribute( 'allow', 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture' );
		frame.src = src;

		box.appendChild( bar );
		box.appendChild( frame );
		overlay.appendChild( box );
		document.body.appendChild( overlay );
		document.body.classList.add( 'courserelay-modal-open' );
		document.addEventListener( 'keydown', onKeydown, true );
		closeBtn.focus();
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target && e.target.closest ? e.target.closest( '[data-courserelay-modal]' ) : null;
		if ( btn ) {
			e.preventDefault();
			open( btn );
		}
	} );

	// Best effort if the learner leaves the page while the popup is open.
	window.addEventListener( 'pagehide', function () {
		if ( overlay ) {
			endSession();
		}
	} );
}() );

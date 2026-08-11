/**
 * Holds embeds that a click would load, until their provider is accepted.
 *
 * Two shapes, neither visible to the server-side rewriting because neither has
 * a live src to take away:
 *
 *  - A lightbox link, given the provider's page URL, which builds the iframe in
 *    the browser when clicked.
 *  - A play overlay over an iframe whose URL is parked in data-src, which the
 *    theme moves to src when clicked. Total's video shortcode does this.
 *
 * Both are caught at the click, in the capture phase, so this runs before the
 * theme's own handler whether that is delegated on document or bound to the
 * element.
 */

let cookieConsent = null;
let embedCategory = 'embeds';
let hosts = {};
let selectors = [];
let wrappers = [];
let strings = {};

// The link held back, replayed once its provider is accepted.
let pending = null;
let dialog = null;
// Focused when the dialog opened, restored when it closes.
let lastFocus = null;

/**
 * Provider key for a URL, or empty string.
 *
 * @param {string} url Link href.
 * @return {string} Provider key.
 */
function matchProvider( url ) {
	const haystack = String( url ).toLowerCase();
	const needle = Object.keys( hosts ).find( ( host ) =>
		haystack.includes( host )
	);

	return needle ? hosts[ needle ] : '';
}

/**
 * Whether a link is marked as opening in a lightbox.
 *
 * @param {HTMLElement} link Anchor element.
 * @return {boolean} True when it matches a configured selector.
 */
function isLightboxLink( link ) {
	return selectors.some( ( selector ) => {
		try {
			return link.matches( selector );
		} catch {
			return false; // A bad selector from the filter must not break clicks.
		}
	} );
}

/**
 * Nearest lazy video wrapper around an element, or null.
 *
 * @param {HTMLElement} node Element to search up from.
 * @return {HTMLElement|null} Wrapper element.
 */
function videoWrapper( node ) {
	for ( const selector of wrappers ) {
		let wrapper = null;

		try {
			wrapper = node.closest( selector );
		} catch {
			continue; // A bad selector from the filter must not break clicks.
		}

		if ( wrapper ) {
			return wrapper;
		}
	}

	return null;
}

/**
 * Provider behind an iframe this click would reveal, or empty string.
 *
 * A play overlay sits over an iframe whose URL waits in data-src, so the answer
 * is on a sibling rather than on the thing clicked. The wrapper the theme puts
 * around both is what ties them together. Only controls are considered — a
 * stray click beside a video is not a request to load it.
 *
 * @param {HTMLElement} target Clicked element.
 * @return {string} Provider key.
 */
function deferredProvider( target ) {
	const control = target.closest( 'button, a, [role="button"]' );

	if ( ! control ) {
		return '';
	}

	const wrapper = videoWrapper( control );
	const iframe = wrapper && wrapper.querySelector( 'iframe[data-src]' );

	return iframe ? matchProvider( iframe.getAttribute( 'data-src' ) ) : '';
}

/**
 * Closes the dialog and forgets the held link.
 *
 * @param {boolean} keepPending Keep the link so consent can still replay it.
 */
function closeDialog( keepPending ) {
	if ( dialog ) {
		dialog.remove();
		dialog = null;
		document.documentElement.classList.remove( 'just-cookies-ask-open' );
	}
	if ( ! keepPending ) {
		pending = null;
	}
	if ( lastFocus ) {
		lastFocus.focus();
		lastFocus = null;
	}
}

/**
 * Accepts one provider, keeping every service already accepted.
 *
 * @param {string} provider Provider key.
 */
function acceptProvider( provider ) {
	const accepted =
		cookieConsent.getUserPreferences().acceptedServices[ embedCategory ] ||
		[];

	// acceptService() replaces the accepted set rather than adding to it.
	cookieConsent.acceptService( [ ...accepted, provider ], embedCategory );
}

/**
 * Asks before loading, naming the provider, rather than dropping the visitor
 * into the preferences modal with no explanation of why it opened.
 *
 * @param {string} provider Provider key.
 * @param {string} label    Provider display name.
 */
function openDialog( provider, label ) {
	closeDialog( true );

	const notice = ( strings.notice || '' ).replace( /\{provider\}/g, label );

	dialog = document.createElement( 'div' );
	dialog.className = 'just-cookies-ask';
	dialog.innerHTML = `
		<div class="just-cookies-ask__box" role="dialog" aria-modal="true" aria-label="${ label }">
			<p class="just-cookies-ask__text">${ notice }</p>
			<div class="just-cookies-ask__actions">
				<button type="button" class="just-cookies-ask__load">${ strings.load }</button>
				<button type="button" class="just-cookies-ask__prefs">${ strings.prefs }</button>
				<button type="button" class="just-cookies-ask__cancel">${ strings.cancel }</button>
			</div>
		</div>`;

	dialog.addEventListener( 'click', ( event ) => {
		// Clicking the backdrop dismisses it.
		if ( event.target === dialog ) {
			closeDialog( false );
			return;
		}
		if ( event.target.closest( '.just-cookies-ask__load' ) ) {
			acceptProvider( provider );
			closeDialog( true );
			replayPending();
			return;
		}
		if ( event.target.closest( '.just-cookies-ask__prefs' ) ) {
			closeDialog( true );
			cookieConsent.showPreferences();
			return;
		}
		if ( event.target.closest( '.just-cookies-ask__cancel' ) ) {
			closeDialog( false );
		}
	} );

	dialog.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key ) {
			closeDialog( false );
		}
	} );

	lastFocus = dialog.ownerDocument.activeElement;
	document.body.appendChild( dialog );
	document.documentElement.classList.add( 'just-cookies-ask-open' );
	dialog.querySelector( '.just-cookies-ask__load' ).focus();
}

/**
 * Opens the pending link once its provider has been accepted. Clicking it again
 * re-enters the handler, which lets it through now that consent is recorded.
 */
export function replayPending() {
	if ( ! pending || ! cookieConsent ) {
		return;
	}

	const { link, provider } = pending;

	if ( cookieConsent.acceptedService( provider, embedCategory ) ) {
		pending = null;
		closeDialog( false );
		link.click();
	}
}

/**
 * Starts intercepting lightbox links.
 *
 * @param {Object} cc   CookieConsent module.
 * @param {Object} data Frontend payload.
 */
export function initLightbox( cc, data ) {
	cookieConsent = cc;
	embedCategory = data.embedCategory || 'embeds';
	hosts = data.linkHosts || {};
	selectors = data.lightboxSelectors || [];
	wrappers = data.videoWrappers || [];
	strings = {
		notice: data.embedNotice || '',
		load: ( data.i18n && data.i18n.load ) || 'Load content',
		prefs: ( data.i18n && data.i18n.prefs ) || 'Cookie settings',
		cancel: ( data.i18n && data.i18n.cancel ) || 'Cancel',
	};

	const labels = data.providerLabels || {};

	if ( ! Object.keys( hosts ).length ) {
		return;
	}

	document.addEventListener(
		'click',
		( event ) => {
			if ( ! event.target.closest ) {
				return;
			}

			const link = event.target.closest( 'a[href]' );

			// A marked lightbox link, else a control over a parked iframe.
			const provider =
				link && isLightboxLink( link )
					? matchProvider( link.getAttribute( 'href' ) )
					: deferredProvider( event.target );

			if ( ! provider || cc.acceptedService( provider, embedCategory ) ) {
				return;
			}

			// Stop the embed loading, then ask. stopImmediatePropagation is
			// what keeps a handler bound to the element itself from running.
			event.preventDefault();
			event.stopImmediatePropagation();

			// Replayed on consent: the link for a lightbox, otherwise the
			// control that was clicked, so the theme's own handler runs then.
			pending = {
				link:
					link ||
					event.target.closest( 'button, a, [role="button"]' ),
				provider,
			};
			openDialog( provider, labels[ provider ] || provider );
		},
		true
	);
}

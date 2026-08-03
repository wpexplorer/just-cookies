/**
 * Holds lightbox video/map links until their provider is accepted.
 *
 * A lightbox is given the provider's page URL and builds the iframe itself when
 * the link is clicked, so there is nothing in the markup for the server to
 * rewrite. The click is caught instead — in the capture phase, so it runs
 * before the lightbox library's own handler, whether that is delegated on
 * document or bound to the link.
 *
 * Only links carrying a lightbox marker are considered, so an ordinary link to
 * a YouTube channel still navigates.
 */

let cookieConsent = null;
let embedCategory = 'embeds';
let hosts = {};
let selectors = [];
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
	strings = {
		notice: data.embedNotice || '',
		load: ( data.i18n && data.i18n.load ) || 'Load content',
		prefs: ( data.i18n && data.i18n.prefs ) || 'Cookie settings',
		cancel: ( data.i18n && data.i18n.cancel ) || 'Cancel',
	};

	const labels = data.providerLabels || {};

	if ( ! selectors.length || ! Object.keys( hosts ).length ) {
		return;
	}

	document.addEventListener(
		'click',
		( event ) => {
			const link = event.target.closest
				? event.target.closest( 'a[href]' )
				: null;

			if ( ! link || ! isLightboxLink( link ) ) {
				return;
			}

			const provider = matchProvider( link.getAttribute( 'href' ) );

			if ( ! provider || cc.acceptedService( provider, embedCategory ) ) {
				return;
			}

			// Stop the lightbox opening, then ask. stopImmediatePropagation is
			// what keeps a handler bound to the link itself from running.
			event.preventDefault();
			event.stopImmediatePropagation();

			pending = { link, provider };
			openDialog( provider, labels[ provider ] || provider );
		},
		true
	);
}

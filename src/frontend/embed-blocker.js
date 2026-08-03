/**
 * Restores blocked third-party iframes once consent is granted. Each iframe
 * carries its real URL in data-just-cookies-src and has no live src.
 *
 * Consent is per provider: each one is a service inside the embed category, so
 * accepting a video does not also accept a map.
 */

let cookieConsent = null;
let embedCategory = 'embeds';
// Only connected while something is accepted; see watchForPlaceholders().
let observer = null;

/**
 * Loads a single blocked embed, replacing the wrapper with the bare iframe.
 *
 * @param {HTMLElement} wrap The .just-cookies-embed wrapper.
 */
function restoreOne( wrap ) {
	const iframe = wrap.querySelector( 'iframe[data-just-cookies-src]' );
	if ( ! iframe ) {
		wrap.remove();
		return;
	}
	iframe.src = iframe.getAttribute( 'data-just-cookies-src' );
	iframe.removeAttribute( 'data-just-cookies-src' );
	wrap.replaceWith( iframe );
}

/**
 * Restores every blocked embed whose provider has been accepted.
 */
function restoreAccepted() {
	if ( ! cookieConsent ) {
		return;
	}

	document.querySelectorAll( '.just-cookies-embed' ).forEach( ( wrap ) => {
		const provider = wrap.getAttribute( 'data-just-cookies-provider' );

		// Providers with no service of their own follow the whole category.
		const allowed = provider
			? cookieConsent.acceptedService( provider, embedCategory )
			: cookieConsent.acceptedCategory( embedCategory );

		if ( allowed ) {
			restoreOne( wrap );
		}
	} );

	// Consent may have just changed, so re-check whether watching is worth it.
	watchForPlaceholders();
}

/**
 * Whether a mutation brought a blocked embed into the page.
 *
 * @param {MutationRecord[]} mutations Observed mutations.
 * @return {boolean} True when a placeholder was added.
 */
function addedPlaceholder( mutations ) {
	return mutations.some( ( mutation ) =>
		Array.prototype.some.call( mutation.addedNodes, ( node ) => {
			if ( 1 !== node.nodeType ) {
				return false; // Text and comment nodes, the bulk of mutations.
			}
			if ( node.matches( '.just-cookies-embed' ) ) {
				return true;
			}
			// Only descend into something that has descendants.
			return (
				!! node.firstElementChild &&
				!! node.querySelector( '.just-cookies-embed' )
			);
		} )
	);
}

/**
 * Restores placeholders that arrive after the page has loaded.
 *
 * Markup is rendered blocked on the server whatever the visitor has accepted —
 * consent is not knowable there, and a page cache would bake one visitor's
 * choice in for everyone. So a post fetched by "load more" arrives blocked even
 * for someone who accepted an hour ago, and only a fresh pass unblocks it.
 *
 * Restoring is safe to do late; blocking never is, which is why that stays on
 * the server.
 */
function watchForPlaceholders() {
	const wanted = hasAcceptedEmbeds();

	if (
		wanted === !! observer ||
		! window.MutationObserver ||
		! document.body
	) {
		return;
	}

	if ( ! wanted ) {
		observer.disconnect();
		observer = null;
		return;
	}

	// Replacing a wrapper adds an <iframe>, never another placeholder, so this
	// cannot retrigger itself.
	observer = new window.MutationObserver( ( mutations ) => {
		if ( addedPlaceholder( mutations ) ) {
			restoreAccepted();
		}
	} );

	observer.observe( document.body, { childList: true, subtree: true } );
}

/**
 * Whether anything in the embeds category is accepted.
 *
 * Nothing accepted means a new placeholder would stay a placeholder, so there
 * is no reason to watch the DOM — which is the common case for a visitor who
 * has not answered the banner.
 *
 * @return {boolean} True when at least one embed provider is accepted.
 */
function hasAcceptedEmbeds() {
	if ( ! cookieConsent ) {
		return false;
	}

	const prefs = cookieConsent.getUserPreferences() || {};
	const services = ( prefs.acceptedServices || {} )[ embedCategory ] || [];

	return (
		services.length > 0 || cookieConsent.acceptedCategory( embedCategory )
	);
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
	cookieConsent.acceptService(
		[ ...accepted, provider ],
		embedCategory
	);
}

/**
 * Wires up placeholder buttons.
 *
 * @param {Object} cc       CookieConsent module.
 * @param {string} category Embed category name.
 */
export function initEmbeds( cc, category ) {
	cookieConsent = cc;
	embedCategory = category;

	document.addEventListener( 'click', ( event ) => {
		const load = event.target.closest( '.just-cookies-embed__load' );
		if ( load ) {
			event.preventDefault();

			const wrap = load.closest( '.just-cookies-embed' );
			const provider = wrap && wrap.getAttribute( 'data-just-cookies-provider' );

			if ( provider ) {
				acceptProvider( provider );
			} else {
				cc.acceptCategory( [
					...cc.getUserPreferences().acceptedCategories,
					category,
				] );
			}

			restoreAccepted();
			return;
		}

		const prefs = event.target.closest( '.just-cookies-embed__prefs' );
		if ( prefs ) {
			event.preventDefault();
			cc.showPreferences();
		}
	} );
}

initEmbeds.restoreAll = restoreAccepted;

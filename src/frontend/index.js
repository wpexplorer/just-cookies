/**
 * Front-end entry: initializes CookieConsent and the embed blocker.
 */
import * as CookieConsent from 'vanilla-cookieconsent';
import 'vanilla-cookieconsent/dist/cookieconsent.css';
import './frontend.scss';
import { initEmbeds } from './embed-blocker';
import { initLightbox, replayPending } from './click-to-load';

const data = window.justCookiesData || {};
const config = data.config || {};
const embedCategory = data.embedCategory || 'embeds';

if ( data.darkMode ) {
	document.documentElement.classList.add( 'cc--darkmode' );
}

/**
 * Compiles autoClear patterns into RegExp objects.
 *
 * They arrive as { regex: '^_ga' } because JSON cannot carry a RegExp, and
 * CookieConsent only pattern-matches when the name is a real RegExp.
 */
const compileAutoClear = () => {
	Object.values( config.categories || {} ).forEach( ( cat ) => {
		const cookies = cat && cat.autoClear && cat.autoClear.cookies;
		if ( ! Array.isArray( cookies ) ) {
			return;
		}
		cat.autoClear.cookies = cookies.map( ( cookie ) =>
			cookie && cookie.regex
				? { ...cookie, name: new RegExp( cookie.regex ) }
				: cookie
		);
	} );
};

compileAutoClear();

// Restore embeds whenever consent changes. Which ones load is decided per
// provider inside, so a visitor who accepted only Vimeo keeps maps blocked.
const syncEmbeds = () => {
	initEmbeds.restoreAll();
	replayPending();
};

config.onFirstConsent = syncEmbeds;
config.onConsent = syncEmbeds;
config.onChange = syncEmbeds;

// Hide the floating button while the consent/preferences modal is open.
const root = document.documentElement;
config.onModalShow = () => root.classList.add( 'cc--modal-open' );
config.onModalHide = () => root.classList.remove( 'cc--modal-open' );

CookieConsent.run( config ).then( () => {
	initEmbeds( CookieConsent, embedCategory );
	initLightbox( CookieConsent, data );
	syncEmbeds();
} );

// "Cookie settings" links/buttons anywhere on the page.
document.addEventListener( 'click', ( event ) => {
	const trigger = event.target.closest(
		'.just-cookies-open-prefs, [data-just-cookies-prefs]'
	);
	if ( trigger ) {
		event.preventDefault();
		CookieConsent.showPreferences();
	}
} );

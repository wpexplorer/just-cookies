/**
 * Admin settings panel for Just Cookies.
 *
 * Runs in two modes:
 *  - site:    per-site settings via /wp/v2/settings.
 *  - network: shared network defaults via the just-cookies/v1/network-settings route.
 *
 * Saving sends only the settings already stored plus whatever was edited, so
 * changing one field never adopts the current value of the others. Text and
 * color fields render blank with the inherited value as their placeholder, and
 * on a subsite the fixed-choice selects offer that as a "Network default (Box)"
 * entry — elsewhere there is no level above to follow, so they only ever offer
 * concrete values.
 */
import {
	createRoot,
	createInterpolateElement,
	useState,
	useEffect,
	Fragment,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	TabPanel,
	ToggleControl,
	TextControl,
	TextareaControl,
	SelectControl,
	Button,
	Notice,
	Spinner,
	BaseControl,
	Dropdown,
	ColorPicker,
	ColorIndicator,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import './admin-settings.scss';

const OPTION_KEY = 'just_cookies_settings';
const NETWORK_PATH = '/just-cookies/v1/network-settings';

const admin = window.justCookiesAdmin || {};
const isNetwork = ( admin.mode || 'site' ) === 'network';
const integrations = admin.integrations || [];
const embedProviders = admin.embeds || [];
const defaults = admin.defaults || {};

// When network-activated, a subsite may only edit these keys.
const limitedTo = admin.limitedTo ? new Set( admin.limitedTo ) : null;
const canEdit = ( key ) => ! limitedTo || limitedTo.has( key );

// A subsite of a network-activated install: the only place a setting can be
// left to follow a level above.
const isSubsite = null !== limitedTo;

/**
 * On a subsite, prepends the choice that follows the network, named with the
 * value the network currently sets. Elsewhere there is nothing above to follow,
 * so the concrete choices are returned untouched.
 *
 * @param {string} key     Setting key.
 * @param {Array}  options Concrete choices.
 * @return {Array} Options for the select.
 */
const withDefaultOption = ( key, options ) => {
	if ( ! isSubsite ) {
		return options;
	}

	const inherited = options.find( ( o ) => o.value === defaults[ key ] );

	if ( ! inherited ) {
		return [ { label: __( 'Network default', 'just-cookies' ), value: '' }, ...options ];
	}

	/* translators: %s: the value the network sets, e.g. Box. */
	const label = sprintf( __( 'Network default (%s)', 'just-cookies' ), inherited.label );

	return [ { label, value: '' }, ...options ];
};

// Selects offering the network choice show blank for it; everywhere else they
// show the value in force.
const sel = ( stored, effective ) => ( isSubsite ? stored : effective );

/**
 * Color field: a swatch button that opens a ColorPicker, plus a clear link.
 * Blank value means "use the default".
 */
function ColorField( { label, help, value, onChange } ) {
	return (
		<BaseControl label={ label } help={ help } __nextHasNoMarginBottom>
			<div className="just-cookies-color-field">
				<Dropdown
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button variant="secondary" onClick={ onToggle } aria-expanded={ isOpen }>
							<ColorIndicator colorValue={ value || 'transparent' } />
							<span>{ value || __( 'Default', 'just-cookies' ) }</span>
						</Button>
					) }
					renderContent={ () => (
						<ColorPicker color={ value || '' } enableAlpha={ false } onChange={ onChange } />
					) }
				/>
				{ value && (
					<Button variant="link" onClick={ () => onChange( '' ) }>
						{ __( 'Clear', 'just-cookies' ) }
					</Button>
				) }
			</div>
		</BaseControl>
	);
}

/**
 * Policy page selector: page dropdown with auto-detect and custom URL modes.
 */
function PolicyPageField( { label, help, page, url, onPageChange, onUrlChange } ) {
	const options = [
		{ label: __( 'Auto-detect', 'just-cookies' ), value: '' },
		...( admin.pages || [] ).map( ( p ) => ( { label: p.title, value: p.id } ) ),
		{ label: __( 'Custom URL…', 'just-cookies' ), value: 'custom' },
	];

	return (
		<>
			<SelectControl
				label={ label }
				help={ 'custom' === page ? undefined : help }
				value={ page }
				options={ options }
				onChange={ onPageChange }
			/>
			{ 'custom' === page && (
				<TextControl
					label={ label + ' — ' + __( 'URL', 'just-cookies' ) }
					type="url"
					value={ url }
					onChange={ onUrlChange }
				/>
			) }
		</>
	);
}

/**
 * Reports which policy pages exist and creates the missing ones.
 */
function PolicyPages() {
	const [ status, setStatus ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ done, setDone ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/just-cookies/v1/policy-pages' } ).then( setStatus ).catch( () => setStatus( false ) );
	}, [] );

	if ( ! status ) {
		return null;
	}

	const missing = ! status.cookie.exists || ! status.terms.exists;

	const create = () => {
		setBusy( true );
		apiFetch( { path: '/just-cookies/v1/policy-pages', method: 'POST' } )
			.then( ( data ) => {
				setStatus( data );
				setDone( data.created || [] );
			} )
			.finally( () => setBusy( false ) );
	};

	const row = ( label, page, note ) => (
		<li style={ { marginBottom: 4 } }>
			<strong>{ label }:</strong>{ ' ' }
			{ page.exists ? (
				<>
					<a href={ page.editUrl } target="_blank" rel="noopener noreferrer">
						{ page.title }
					</a>
					{ 'publish' !== page.status && (
						<em> — { __( 'draft, not published yet', 'just-cookies' ) }</em>
					) }
				</>
			) : (
				<em>{ note || __( 'missing', 'just-cookies' ) }</em>
			) }
		</li>
	);

	return (
		<BaseControl label={ __( 'Policy pages', 'just-cookies' ) } __nextHasNoMarginBottom>
			<ul style={ { margin: '0 0 12px', fontSize: 13 } }>
				{ row( __( 'Cookie Policy', 'just-cookies' ), status.cookie ) }
				{ row( __( 'Terms of Service', 'just-cookies' ), status.terms ) }
				{ row(
					__( 'Privacy Policy', 'just-cookies' ),
					status.privacy,
					__( 'not set — configure under Settings → Privacy', 'just-cookies' )
				) }
			</ul>

			{ missing && (
				<Button variant="secondary" onClick={ create } isBusy={ busy } disabled={ busy }>
					{ __( 'Create missing pages', 'just-cookies' ) }
				</Button>
			) }

			{ done && (
				<p style={ { fontSize: 13 } }>
					{ done.length
						? __( 'Created as drafts. Read them over, then publish — the banner links to each page once it is published.', 'just-cookies' )
						: __( 'Nothing to create.', 'just-cookies' ) }
				</p>
			) }
		</BaseControl>
	);
}

const TABS = [
	{ name: 'general', title: __( 'Banner', 'just-cookies' ) },
	{ name: 'disclosures', title: __( 'Disclosures', 'just-cookies' ) },
	{ name: 'blocking', title: __( 'Blocking', 'just-cookies' ) },
	{ name: 'policies', title: __( 'Policies', 'just-cookies' ) },
	{ name: 'help', title: __( 'About', 'just-cookies' ) },
];

function App() {
	const [ settings, setSettings ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		if ( isNetwork ) {
			apiFetch( { path: NETWORK_PATH } ).then( setSettings );
			return;
		}
		apiFetch( { path: '/wp/v2/settings' } ).then( ( data ) => {
			setSettings( data[ OPTION_KEY ] || {} );
		} );
	}, [] );

	const set = ( key ) => ( value ) =>
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );

	const isSet = ( key ) => settings[ key ] !== undefined && settings[ key ] !== null;

	// Effective value (for toggles/selects/numbers): stored, else default.
	const eff = ( key ) => ( isSet( key ) ? settings[ key ] : defaults[ key ] );
	// Text-field display: stored, else blank (placeholder shows the default).
	const txt = ( key ) => ( isSet( key ) ? settings[ key ] : '' );
	// Whether the banner links a given policy page.
	const linked = ( key ) => ( eff( 'banner_links' ) || [] ).includes( key );

	const save = () => {
		setSaving( true );
		setNotice( null );

		// Only what is already stored or was edited this visit. Sending every
		// field would make saving one of them adopt the current value of all
		// the rest, freezing settings the user never touched.
		const payload = {};
		Object.keys( defaults ).forEach( ( key ) => {
			if ( canEdit( key ) && isSet( key ) ) {
				payload[ key ] = settings[ key ];
			}
		} );

		const ok = () => {
			setNotice( { status: 'success', text: __( 'Settings saved.', 'just-cookies' ) } );
		};
		const fail = ( err ) =>
			setNotice( {
				status: 'error',
				text: ( err && err.message ) || __( 'Could not save settings.', 'just-cookies' ),
			} );

		const request = isNetwork
			? apiFetch( { path: NETWORK_PATH, method: 'POST', data: { settings: payload } } ).then( setSettings )
			: apiFetch( {
					path: '/wp/v2/settings',
					method: 'POST',
					data: { [ OPTION_KEY ]: payload },
			  } ).then( ( data ) => setSettings( data[ OPTION_KEY ] || {} ) );

		request.then( ok ).catch( fail ).finally( () => setSaving( false ) );
	};

	const reset = () => {
		const message = isNetwork
			? __( 'Reset all network settings to the plugin defaults? Sites keep their own overrides.', 'just-cookies' )
			: __( 'Reset this site’s settings to the defaults?', 'just-cookies' );

		if ( ! window.confirm( message ) ) {
			return;
		}

		setSaving( true );
		setNotice( null );

		apiFetch( { path: '/just-cookies/v1/reset-settings', method: 'POST', data: { network: isNetwork } } )
			.then( ( data ) => {
				// Everything goes but the bumped revision, which a later save
				// would otherwise write back out and undo the re-prompt.
				setSettings( data && data.revision ? { revision: data.revision } : {} );
				setNotice( { status: 'success', text: __( 'Settings reset to defaults.', 'just-cookies' ) } );
			} )
			.catch( ( err ) =>
				setNotice( {
					status: 'error',
					text: ( err && err.message ) || __( 'Could not reset settings.', 'just-cookies' ),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	if ( ! settings ) {
		return <Spinner />;
	}

	const generalTab = (
		<>
			{ canEdit( 'enabled' ) && (
				<ToggleControl
					label={ __( 'Enable cookie banner', 'just-cookies' ) }
					checked={ !! eff( 'enabled' ) }
					onChange={ set( 'enabled' ) }
				/>
			) }
			{ isNetwork && (
				<ToggleControl
					label={ __( 'Lock appearance for all sites', 'just-cookies' ) }
					help={ __( 'Site administrators cannot change the banner’s look on their own site, and their settings screen is removed. Everything is set here instead.', 'just-cookies' ) }
					checked={ !! eff( 'network_lock_site_settings' ) }
					onChange={ set( 'network_lock_site_settings' ) }
				/>
			) }
			{ /* Settings stay editable while off, so a new install can be set
			     up before the banner ever appears to a visitor. */ }
			{ ! eff( 'enabled' ) && (
				<Notice status="warning" isDismissible={ false }>
					{ canEdit( 'enabled' )
						? __( 'The banner is not showing on your site yet. Set it up, then turn it on above.', 'just-cookies' )
						: __( 'The cookie banner is turned off for the whole network.', 'just-cookies' ) }
				</Notice>
			) }
			<SelectControl
				label={ __( 'Banner layout', 'just-cookies' ) }
				value={ sel( txt( 'layout' ), eff( 'layout' ) ) }
				options={ withDefaultOption( 'layout', [
					{ label: 'Box', value: 'box' },
					{ label: 'Cloud', value: 'cloud' },
					{ label: 'Bar', value: 'bar' },
				] ) }
				onChange={ set( 'layout' ) }
			/>
			<SelectControl
				label={ __( 'Position', 'just-cookies' ) }
				value={ sel( txt( 'position' ), eff( 'position' ) ) }
				options={ withDefaultOption(
					'position',
					[
						'bottom left',
						'bottom center',
						'bottom right',
						'top left',
						'top center',
						'top right',
						'middle center',
					].map( ( v ) => ( { label: v, value: v } ) )
				) }
				onChange={ set( 'position' ) }
			/>
			<ToggleControl
				label={ __( 'Lock page with dark overlay', 'just-cookies' ) }
				help={ __( 'Dims the site behind a backdrop and blocks interaction until the visitor makes a choice.', 'just-cookies' ) }
				checked={ !! eff( 'lock_overlay' ) }
				onChange={ set( 'lock_overlay' ) }
			/>
			<ToggleControl
				label={ __( 'Dark mode', 'just-cookies' ) }
				checked={ !! eff( 'dark_mode' ) }
				onChange={ set( 'dark_mode' ) }
			/>
			<ColorField
				label={ __( 'Primary button color', 'just-cookies' ) }
				help={ __( 'The main “Accept” button. Leave as Default for the neutral built-in color.', 'just-cookies' ) }
				value={ eff( 'primary_button_bg' ) }
				onChange={ set( 'primary_button_bg' ) }
			/>
			<ColorField
				label={ __( 'Primary button hover color', 'just-cookies' ) }
				help={ __( 'Hover state for the main “Accept” button.', 'just-cookies' ) }
				value={ eff( 'primary_button_hover_bg' ) }
				onChange={ set( 'primary_button_hover_bg' ) }
			/>
			{ canEdit( 'expires_days' ) && (
				<>
					<NumberControl
						label={ __( 'Consent lifetime (days)', 'just-cookies' ) }
						value={ eff( 'expires_days' ) }
						min={ 1 }
						onChange={ ( v ) => set( 'expires_days' )( parseInt( v, 10 ) || 0 ) }
					/>
					<NumberControl
						label={ __( 'Revision (bump to re-prompt visitors)', 'just-cookies' ) }
						value={ eff( 'revision' ) }
						min={ 0 }
						onChange={ ( v ) => set( 'revision' )( parseInt( v, 10 ) || 0 ) }
					/>
					<TextControl
						label={ __( 'Banner title', 'just-cookies' ) }
						value={ txt( 'banner_title' ) }
						placeholder={ defaults.banner_title }
						onChange={ set( 'banner_title' ) }
					/>
					<TextareaControl
						label={ __( 'Banner description', 'just-cookies' ) }
						value={ txt( 'banner_description' ) }
						placeholder={ defaults.banner_description }
						onChange={ set( 'banner_description' ) }
					/>
					<TextControl
						label={ __( 'Acknowledge button label', 'just-cookies' ) }
						help={ __( 'Shown instead of accept/reject when the site only sets necessary cookies.', 'just-cookies' ) }
						value={ txt( 'ack_button_label' ) }
						placeholder={ defaults.ack_button_label }
						onChange={ set( 'ack_button_label' ) }
					/>
				</>
			) }

			<hr />

			<TextControl
				label={ __( 'Extra section heading', 'just-cookies' ) }
				help={ __( 'Adds a closing section to the preferences popup, below the cookie categories. Leave both fields empty to leave it out.', 'just-cookies' ) }
				value={ txt( 'prefs_extra_title' ) }
				onChange={ set( 'prefs_extra_title' ) }
			/>
			<TextareaControl
				label={ __( 'Extra section text', 'just-cookies' ) }
				help={ __( 'Accepts HTML, so you can link to a fuller policy or a contact page.', 'just-cookies' ) }
				value={ txt( 'prefs_extra_text' ) }
				onChange={ set( 'prefs_extra_text' ) }
			/>

			<hr />

			<ToggleControl
				label={ __( 'Show floating preferences button', 'just-cookies' ) }
				help={ __( 'A small fixed button that lets visitors reopen their cookie preferences at any time.', 'just-cookies' ) }
				checked={ !! eff( 'float_button' ) }
				onChange={ set( 'float_button' ) }
			/>
			{ eff( 'float_button' ) && (
				<>
					<SelectControl
						label={ __( 'Button position', 'just-cookies' ) }
						help={ __( 'Left/right follow text direction (mirrored on RTL sites).', 'just-cookies' ) }
						value={ sel( txt( 'float_button_position' ), eff( 'float_button_position' ) ) }
						options={ withDefaultOption(
							'float_button_position',
							[ 'bottom left', 'bottom right' ].map( ( v ) => ( { label: v, value: v } ) )
						) }
						onChange={ set( 'float_button_position' ) }
					/>
					<TextControl
						label={ __( 'Button label', 'just-cookies' ) }
						value={ txt( 'float_button_label' ) }
						placeholder={ defaults.float_button_label }
						onChange={ set( 'float_button_label' ) }
					/>
					<ColorField
						label={ __( 'Button background color', 'just-cookies' ) }
						help={ __( 'Leave as Default to use the theme default.', 'just-cookies' ) }
						value={ eff( 'float_button_bg' ) }
						onChange={ set( 'float_button_bg' ) }
					/>
					<ColorField
						label={ __( 'Button text color', 'just-cookies' ) }
						help={ __( 'Leave as Default to use the theme default.', 'just-cookies' ) }
						value={ eff( 'float_button_color' ) }
						onChange={ set( 'float_button_color' ) }
					/>
					<NumberControl
						label={ __( 'Button stacking order (z-index)', 'just-cookies' ) }
						help={ __( 'Raise this if something on your site covers the button; lower it if the button covers a menu, popup or modal. Themes usually stack those from about 1000 upwards.', 'just-cookies' ) }
						value={ eff( 'float_button_z_index' ) }
						min={ 0 }
						onChange={ ( v ) =>
							set( 'float_button_z_index' )( parseInt( v, 10 ) || 0 )
						}
					/>
				</>
			) }
		</>
	);

	const disclosuresTab = (
		<>
			<ToggleControl
				label={ __( 'Disclose Cloudflare cookies (__cf_bm, cf_clearance)', 'just-cookies' ) }
				help={ __( 'Enable on WPEngine/Cloudflare sites.', 'just-cookies' ) }
				checked={ !! eff( 'disclose_cloudflare' ) }
				onChange={ set( 'disclose_cloudflare' ) }
			/>
			<ToggleControl
				label={ __( 'Disclose Cloudflare Turnstile cookies', 'just-cookies' ) }
				help={ __( 'Only needed if Turnstile has Pre-Clearance enabled — without it the widget sets no cookie.', 'just-cookies' ) }
				checked={ !! eff( 'disclose_turnstile' ) }
				onChange={ set( 'disclose_turnstile' ) }
			/>
			<ToggleControl
				label={ __( 'Disclose reCAPTCHA cookies', 'just-cookies' ) }
				help={ __( 'Enable if any form on the site uses Google reCAPTCHA.', 'just-cookies' ) }
				checked={ !! eff( 'disclose_recaptcha' ) }
				onChange={ set( 'disclose_recaptcha' ) }
			/>
			<ToggleControl
				label={ __( 'Disclose Stripe cookies', 'just-cookies' ) }
				help={ __( 'Enable if the site takes payments through Stripe.', 'just-cookies' ) }
				checked={ !! eff( 'disclose_stripe' ) }
				onChange={ set( 'disclose_stripe' ) }
			/>
			<ToggleControl
				label={ __( 'Disclose WordPress login cookies', 'just-cookies' ) }
				help={ __( 'Enable on sites where visitors can log in.', 'just-cookies' ) }
				checked={ !! eff( 'disclose_login' ) }
				onChange={ set( 'disclose_login' ) }
			/>
			{ eff( 'disclose_login' ) && (
				<ToggleControl
					label={ __( 'Logged-in users can access the WordPress admin', 'just-cookies' ) }
					help={ __( 'Also discloses the admin screen preference cookies (wp-settings-*). Leave off for front-end-only accounts.', 'just-cookies' ) }
					checked={ !! eff( 'disclose_admin' ) }
					onChange={ set( 'disclose_admin' ) }
				/>
			) }
			<hr />
			<ToggleControl
				label={ __( 'Auto detect plugin cookies', 'just-cookies' ) }
				help={ __( 'Discloses cookies from supported plugins when they’re active on this site. Turn off to choose them yourself — needed on a network where a plugin runs on some sites but not others.', 'just-cookies' ) }
				checked={ !! eff( 'auto_detect_plugins' ) }
				onChange={ set( 'auto_detect_plugins' ) }
			/>
			{ ! eff( 'auto_detect_plugins' ) &&
				( integrations.length ? (
					integrations.map( ( item ) => {
						const chosen = ( eff( 'disclose_plugins' ) || [] ).includes( item.key );
						return (
							<Fragment key={ item.key }>
								<ToggleControl
									label={ item.label }
									help={
										item.active
											? __( 'Active on this site.', 'just-cookies' )
											: __( 'Installed but not active on this site.', 'just-cookies' )
									}
									checked={ chosen }
									onChange={ ( on ) => {
										const current = ( eff( 'disclose_plugins' ) || [] ).filter(
											( k ) => k !== item.key
										);
										set( 'disclose_plugins' )(
											on ? [ ...current, item.key ] : current
										);
									} }
								/>
								{ 'woocommerce' === item.key && chosen && (
									<div className="just-cookies-nested">
										<ToggleControl
											label={ __( 'Order attribution cookies (sbjs_*)', 'just-cookies' ) }
											help={ __( 'WooCommerce records how a visitor reached the site so orders can be credited to a source, and enables this by default. Turn it off only where Order Attribution is switched off under WooCommerce → Settings → Advanced → Features — on a network, only if it is off on every site.', 'just-cookies' ) }
											checked={ !! eff( 'disclose_order_attribution' ) }
											onChange={ set( 'disclose_order_attribution' ) }
										/>
									</div>
								) }
							</Fragment>
						);
					} )
				) : (
					<p>{ __( 'None of the supported plugins are installed on this server.', 'just-cookies' ) }</p>
				) ) }
		</>
	);

	const blockingTab = (
		<>
			<ToggleControl
				label={ __( 'Hold analytics until consent', 'just-cookies' ) }
				help={ __( 'Automatically defers tracking scripts such as Google Analytics, Google Tag Manager, Meta Pixel, Hotjar, Microsoft Clarity, etc. until the visitor accepts them.', 'just-cookies' ) }
				checked={ !! eff( 'block_analytics' ) }
				onChange={ set( 'block_analytics' ) }
			/>
			<hr />
			<ToggleControl
				label={ __( 'Block third-party embeds until consent', 'just-cookies' ) }
				help={ __( 'Replaces embedded videos, audio, maps, forms and booking widgets with a placeholder until the visitor accepts them. Each service is accepted separately.', 'just-cookies' ) }
				checked={ !! eff( 'block_embeds' ) }
				onChange={ set( 'block_embeds' ) }
			/>
			{ eff( 'block_embeds' ) && (
				<>
					<BaseControl
						label={ __( 'Services to block', 'just-cookies' ) }
						help={ __( 'Turn on only the services this site embeds. Anything left off loads as usual.', 'just-cookies' ) }
						__nextHasNoMarginBottom
					>
						{ embedProviders.map( ( item ) => {
							const chosen = ( eff( 'embed_providers' ) || [] ).includes( item.key );
							return (
								<ToggleControl
									key={ item.key }
									label={ item.label }
									checked={ chosen }
									onChange={ ( on ) => {
										const current = ( eff( 'embed_providers' ) || [] ).filter(
											( k ) => k !== item.key
										);
										set( 'embed_providers' )(
											on ? [ ...current, item.key ] : current
										);
									} }
								/>
							);
						} ) }
					</BaseControl>
					{ ! ( eff( 'embed_providers' ) || [] ).length && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'No services selected — nothing is being blocked yet.', 'just-cookies' ) }
						</Notice>
					) }
					<hr />
					<TextareaControl
						label={ __( 'Banner text added when embeds are enabled', 'just-cookies' ) }
						help={ __( 'Appended to the banner description while embed blocking is on.', 'just-cookies' ) }
						value={ txt( 'banner_embeds_note' ) }
						placeholder={ defaults.banner_embeds_note }
						onChange={ set( 'banner_embeds_note' ) }
					/>
					<TextareaControl
						label={ __( 'Placeholder notice ({provider} is replaced with the service name)', 'just-cookies' ) }
						value={ txt( 'embed_notice' ) }
						placeholder={ defaults.embed_notice }
						onChange={ set( 'embed_notice' ) }
					/>
					<TextareaControl
						label={ __( 'Additional content filters', 'just-cookies' ) }
						help={ __( 'Advanced: extra filter hook names to scan, one per line, for themes or plugins that render content outside the_content.', 'just-cookies' ) }
						placeholder={ 'my_theme/card_content' }
						value={ txt( 'embed_content_filters' ) }
						onChange={ set( 'embed_content_filters' ) }
					/>
				</>
			) }
		</>
	);

	const helpTab = (
		<div className="just-cookies-help">
			<Notice status="warning" isDismissible={ false }>
				<strong>{ __( 'Not legal advice.', 'just-cookies' ) }</strong>{ ' ' }
				{ __( 'This plugin gives you tools to disclose cookies and hold third-party content until visitors consent. It cannot guarantee compliance with the GDPR, ePrivacy, CCPA or any other law, and it does not review what your site actually does. You are responsible for confirming the disclosures are accurate for your site and for publishing the policies you link to. If you are unsure, consult a qualified professional.', 'just-cookies' ) }
			</Notice>

			<h3>{ __( 'What it detects automatically', 'just-cookies' ) }</h3>
			<p>
				<strong>{ __( 'Analytics scripts', 'just-cookies' ) }</strong>{ ' — ' }
				{ __( 'Google Analytics, Google Tag Manager, Meta Pixel, Hotjar, Microsoft Clarity, Bing UET, LinkedIn, TikTok, Segment, Mixpanel, Plausible, Matomo, Jetpack Stats.', 'just-cookies' ) }
			</p>
			<p>
				<strong>{ __( 'Embedded media', 'just-cookies' ) }</strong>{ ' — ' }
				{ __( 'YouTube, Vimeo, SoundCloud, Spotify, Dailymotion, Twitch, Wistia, Mixcloud, Bandcamp, Loom, Calendly, Typeform, and Google Maps, Calendar and Docs. YouTube is switched to youtube-nocookie.com and Vimeo gets dnt=1.', 'just-cookies' ) }
			</p>
			<p>
				<strong>{ __( 'Plugin cookies', 'just-cookies' ) }</strong>{ ' — ' }
				{ __( 'WooCommerce, Easy Digital Downloads, Simple Membership, BuddyPress, LifterLMS, LearnDash, Events Manager, Polylang, WPML, Jetpack, AffiliateWP and Post Views Counter. Cloudflare, reCAPTCHA and Stripe cannot be detected reliably, so those are manual switches.', 'just-cookies' ) }
			</p>

			<h3>{ __( 'Known limits', 'just-cookies' ) }</h3>
			<ul>
				<li>{ __( 'Iframe embeds and lightbox links are blocked. Content built by a provider’s JavaScript API, such as a map drawn by the Google Maps API, is not.', 'just-cookies' ) }</li>
				<li>{ __( 'A lightbox link is recognised by its class — the common libraries are covered, and a theme using its own marker can add it with the just_cookies_lightbox_selectors filter.', 'just-cookies' ) }</li>
				<li>{ __( 'Content rendered outside the_content may need extra filter hooks (see the Blocking tab).', 'just-cookies' ) }</li>
				<li>{ __( 'Visitors with JavaScript disabled never load blocked embeds, by design.', 'just-cookies' ) }</li>
				<li>{ __( 'Consent is stored in the visitor’s browser, not on your server.', 'just-cookies' ) }</li>
			</ul>

			<h3>{ __( 'Shortcodes', 'just-cookies' ) }</h3>
			<ul>
				<li>
					<code>[just_cookies_settings_link]</code>{ ' — ' }
					{ __( 'a link or button that reopens the preferences popup.', 'just-cookies' ) }
				</li>
				<li>
					<code>[just_cookies_table]</code>{ ' — ' }
					{ __( 'the full cookie disclosure table, for your cookie policy page. It updates itself as settings change.', 'just-cookies' ) }
				</li>
			</ul>

			<h3>{ __( 'For developers', 'just-cookies' ) }</h3>
			<ul>
				<li><code>just_cookies_consent_config</code>{ ' — ' }{ __( 'the whole CookieConsent config.', 'just-cookies' ) }</li>
				<li><code>just_cookies_necessary_rows</code>, <code>just_cookies_analytics_rows</code>, <code>just_cookies_embed_rows</code>{ ' — ' }{ __( 'cookie table rows.', 'just-cookies' ) }</li>
				<li><code>just_cookies_embed_providers</code>, <code>just_cookies_embed_content_filters</code>{ ' — ' }{ __( 'which services can be blocked, and where they are looked for.', 'just-cookies' ) }</li>
				<li><code>just_cookies_analytics_script_patterns</code>, <code>just_cookies_analytics_inline_patterns</code>, <code>just_cookies_analytics_buffer_hooks</code>{ ' — ' }{ __( 'analytics detection.', 'just-cookies' ) }</li>
				<li><code>just_cookies_per_site_keys</code>{ ' — ' }{ __( 'which settings a subsite controls on a network.', 'just-cookies' ) }</li>
			</ul>

			<p className="just-cookies-help__credit">
				{ createInterpolateElement(
					sprintf(
						/* translators: %s: plugin version number. */
						__( 'Just Cookies %s — a free plugin by <a>WPExplorer</a>.', 'just-cookies' ),
						admin.version || ''
					),
					{
						a: (
							<a
								href="https://www.wpexplorer.com/"
								target="_blank"
								rel="noreferrer noopener"
							/>
						),
					}
				) }
			</p>
		</div>
	);


	const policiesTab = (
		<>
			<p>
				{ __( 'The pages your cookie notice links to. Auto-detect finds the matching page by slug — if none is published, the link is simply left out.', 'just-cookies' ) }
			</p>
			{ isNetwork && (
				<ToggleControl
					label={ __( 'Share policy pages network-wide', 'just-cookies' ) }
					help={ __( 'All sites link to the main site’s cookie, privacy and terms pages. Turn off to let each site set its own.', 'just-cookies' ) }
					checked={ !! eff( 'network_policy_pages' ) }
					onChange={ set( 'network_policy_pages' ) }
				/>
			) }
			{ !! eff( 'lock_overlay' ) && (
				<Notice status="info" isDismissible={ false }>
					{ __( 'While “Lock page with dark overlay” is on, these move to the preferences popup instead of the banner. A locked page cannot scroll, so a link out of the banner would lead somewhere the visitor could not read.', 'just-cookies' ) }
				</Notice>
			) }
			<BaseControl
				label={ __( 'Pages to link from the banner', 'just-cookies' ) }
				help={ __( 'Turn off any page you already link elsewhere, such as in your footer. It still has to be published to appear.', 'just-cookies' ) }
				__nextHasNoMarginBottom
			>
				{ [
					{ key: 'cookie', label: __( 'Cookie Policy', 'just-cookies' ) },
					{ key: 'privacy', label: __( 'Privacy Policy', 'just-cookies' ) },
					{ key: 'terms', label: __( 'Terms of Service', 'just-cookies' ) },
				].map( ( item ) => (
					<ToggleControl
						key={ item.key }
						label={ item.label }
						checked={ ( eff( 'banner_links' ) || [] ).includes( item.key ) }
						onChange={ ( on ) => {
							const current = ( eff( 'banner_links' ) || [] ).filter(
								( k ) => k !== item.key
							);
							set( 'banner_links' )(
								on ? [ ...current, item.key ] : current
							);
						} }
					/>
				) ) }
			</BaseControl>
			{ !! ( eff( 'banner_links' ) || [] ).length && (
				<ToggleControl
					label={ __( 'Open banner links in a new tab', 'just-cookies' ) }
					help={ __( 'Keeps the visitor on the page they were reading. Turn off to open the page in the same tab.', 'just-cookies' ) }
					checked={ !! eff( 'banner_links_new_tab' ) }
					onChange={ set( 'banner_links_new_tab' ) }
				/>
			) }
			{ /* Only the pages the banner actually links need choosing. */ }
			{ linked( 'cookie' ) && (
				<PolicyPageField
					label={ __( 'Cookie Policy Page', 'just-cookies' ) }
					help={ __( 'Auto-detect looks for a page with the cookie-policy slug.', 'just-cookies' ) }
					page={ eff( 'policy_page' ) || '' }
					url={ txt( 'policy_url' ) }
					onPageChange={ set( 'policy_page' ) }
					onUrlChange={ set( 'policy_url' ) }
				/>
			) }
			{ linked( 'privacy' ) && (
				<PolicyPageField
					label={ __( 'Privacy Policy Page', 'just-cookies' ) }
					help={ __( 'Auto-detect uses the page set in Settings → Privacy.', 'just-cookies' ) }
					page={ eff( 'privacy_page' ) || '' }
					url={ txt( 'privacy_url' ) }
					onPageChange={ set( 'privacy_page' ) }
					onUrlChange={ set( 'privacy_url' ) }
				/>
			) }
			{ linked( 'terms' ) && (
				<PolicyPageField
					label={ __( 'Terms of Service Page', 'just-cookies' ) }
					help={ __( 'Auto-detect looks for a page with the terms slug.', 'just-cookies' ) }
					page={ eff( 'terms_page' ) || '' }
					url={ txt( 'terms_url' ) }
					onPageChange={ set( 'terms_page' ) }
					onUrlChange={ set( 'terms_url' ) }
				/>
			) }
			<hr />
			<PolicyPages />
		</>
	);

	const panels = {
		general: generalTab,
		disclosures: disclosuresTab,
		blocking: blockingTab,
		policies: policiesTab,
		help: helpTab,
	};

	// Tabs whose fields are all network-level drop out on a subsite.
	const tabKeys = {
		general: [ 'enabled', 'layout' ],
		disclosures: [ 'disclose_cloudflare' ],
		blocking: [ 'block_analytics', 'block_embeds', 'embed_providers' ],
		policies: [ 'policy_url' ],
	};

	// Tabs with no settings of their own (About) are always shown.
	const tabs = TABS.filter(
		( t ) => ! tabKeys[ t.name ] || tabKeys[ t.name ].some( canEdit )
	);

	return (
		<>
			{ isNetwork && (
				<p>{ __( 'These settings apply to every site on the network. Individual sites control their own appearance.', 'just-cookies' ) }</p>
			) }
			{ limitedTo && (
				<p>{ __( 'Consent settings are managed network-wide. This page controls how the banner looks on this site.', 'just-cookies' ) }</p>
			) }

			{ notice && (
				<Notice status={ notice.status } isDismissible onRemove={ () => setNotice( null ) }>
					{ notice.text }
				</Notice>
			) }

			<TabPanel className="just-cookies-tabs" tabs={ tabs }>
				{ ( tab ) => <div className="just-cookies-tab-body">{ panels[ tab.name ] }</div> }
			</TabPanel>

			<div className="just-cookies-actions">
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ __( 'Save settings', 'just-cookies' ) }
				</Button>
				<Button variant="tertiary" isDestructive onClick={ reset } disabled={ saving }>
					{ __( 'Reset to defaults', 'just-cookies' ) }
				</Button>
			</div>
		</>
	);
}

const root = document.getElementById( 'just-cookies-admin-root' );
if ( root ) {
	createRoot( root ).render( <App /> );
}

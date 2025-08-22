<template>
	<k-panel-inside>
		<k-view class="k-musickit-auth">
			<k-header class="k-site-view-header">
				{{ headerTitle }}

				<k-button-group slot="buttons">
					<k-button
						variant="filled"
						theme="blue-icon"
						icon="document"
						size="xs"
						link="https://github.com/scottboms/kirby-applemusic/blob/main/README.md"
						target="_blank"
						responsive="true"
						text="Setup Guide"
					/>

					<k-button v-if="!hasToken"
						:disabled="busy"
						variant="filled"
						theme="green-icon"
						icon="open"
						size="xs"
						@click="redirectAuth">
							{{ hasToken ? 'Re-authorize Apple Music' : 'Authorize Apple Music' }}
					</k-button>

					<k-button v-if="hasToken"
						:disabled="busy"
						variant="filled"
						icon="reset-token"
						text="Reset Token"
						size="xs"
						title="Reset the Cached Token"
						@click="refreshDevToken"
					/>

					<k-button v-if="hasToken"
						:disabled="busy"
						variant="filled"
						theme="red-icon"
						icon="logout"
						text="Disconnect"
						size="xs"
						title="Delete the Saved Token"
						@click="disconnect"
					/>
				</k-button-group>
			</k-header>

			<k-box v-if="!hasToken" theme="positive" style="margin-bottom: .5rem" icon="settings">
				Your configuration is ready to connect to your Apple Music account.
			</k-box>

			<k-section>
				<k-stats
					:reports="statReports"
					size="small"
					class="k-musickit-info-reports"
				/>
			</k-section>

			<!-- conditionally visible: only when token is present -->
			<template v-if="hasToken">
				<k-section>
					<k-box icon="search" theme="white" style="margin-bottom: var(--spacing-1)">
						<k-select-field
							:value="searchType"
							:options="searchTypeOptions"
							:disabled="!hasToken"
							icon="angle-down"
							size="tiny"
							@input="onSearchTypeChange"
							style="min-width: 9rem"
						/>

						<k-search-input
							:value="searchQuery"
							:disabled="!hasToken"
							:placeholder="'Search Apple Music...'"
							@input="onSearchInput"
							@submit="performSearch"
							style="min-width: 80%"
						/>
					</k-box>

					<k-box v-if="searching" icon="loader" style="--width: 100%">Searching...</k-box>

					<k-items
						v-else-if="searchResults.length"
						:items="searchResultItems"
						layout="list"
						size="small"
					/>

					<k-box v-else-if="searchQuery && !searching" theme="none">No matches found</k-box>
				</k-section>

				<k-section label="Recently Played Tracks">
					<k-button slot="options" size="xs" variant="filled" icon="refresh" @click="fetchRecent()" />

					<k-box v-if="error" theme="negative">{{ error }}</k-box>
					<k-box v-else-if="loading" icon="loader">Loading...</k-box>

					<div v-else>
						<k-collection
							:items="collectionItems"
							layout="cards"
							size="large"
						/>

						<div v-if="items.length === 0">
							No items returned.
						</div>
					</div>

					<k-box theme="none" style="margin-top: 1rem;">
						<k-button-group responsive="true" theme="gray-icon">
							<k-button variant="filled" size="xs" title="Previous Page" icon="angle-left" :disabled="offset <= 0" @click="prevPage" />

							<k-button variant="filled" size="xs" title="Next Page" icon="angle-right" :disabled="items.length < limit" @click="nextPage" />
						</k-button-group>

					</k-box>
				</k-section>
			</template>
		</k-view>
	</k-panel-inside>
</template>

<script>
import { makeTrackOptions } from '../trackOptions';

export default {
	name: 'Apple Music',
	props: {
		appName: String,
		appBuild: String,
		hasToken: Boolean,
		storefront: String,
		songsLimit: { type: Number, default: 15 },
	},

	data() {
		return {
			busy: false,
			msg: this.hasToken ? 'Token saved.' : 'Not connected yet.',
			items: [],
			loading: false,
			error: null,
			limit: this.songsLimit,
			offset: 0,
			language: 'en-US',
			storefrontInfo: null,
			// search
			searchQuery: '',
			searchType: 'songs',
			searchResults: [],
			searching: false,
			searchError: null,
			searchLimit: 10,
			_searchTimer: null
		};
	},

	created() {
		if (this.hasToken) {
			this.fetchStorefront();
			this.fetchRecent();
		}
	},

	watch: {
		hasToken(newVal) {
			if (newVal) {
				this.fetchStorefront();
				this.fetchRecent();
			} else {
				// if token removed (e.g., user revoked), clear ui
				this.items = [];
				this.error = null;
				this.loading = false;
				this.storefrontInfo = null;

				// clear search ui too
				this.searchQuery = '';
				this.searchResults = [];
				this.searching = false;
				this.searchError = null;
			}
		}
	},

	computed: {
		headerTitle() {
			return this.hasToken
			? 'Apple Music'
			: 'Authorize Apple Music'
		},

		// map your searchResults into k-items
		searchResultItems() {
			const rawSf = (this.storefrontInfo?.id || this.storefront || 'us')
				.toString()
				.toLowerCase();
			const storefront = rawSf === 'auto' ? 'us' : rawSf;

			return (this.searchResults || []).map((r) => {
				const kind = (r.kind || this.searchType); // songs | albums
				const pathSegment = (kind === 'albums' || kind === 'album') ? 'album' : 'song';

				const appleMusicUrl = r?.id
					? `https://music.apple.com/${storefront}/${pathSegment}/${encodeURIComponent(r.id)}`
					: null;

				const base = {
					id: r.id,
					text: r.text,
					info: r.info,
					icon: (pathSegment === 'album' ? 'album' : 'music'),
					...(r.image
						? { image: { src: r.image, ratio: '1/1', cover: true, back: 'pattern' } }
						: {}),
					...(r.link ? { link: r.link } : {}) // if backend already supplies a canonical link
				};

				// use internal route for album results
				if (pathSegment === 'album' && r.id) {
					base.link = `applemusic/album/${encodeURIComponent(r.id)}`;
				}

				// add options only if we have a valid apple music url
				if (appleMusicUrl) {
					const opts = makeTrackOptions({
						url: appleMusicUrl,
						onCopy: (text, msg = 'Link copied to clipboard') => this.copyToClipboard(text, msg),
						onEmbed: (url) => this.buildEmbedCode(url),
						onError: (msg) => this.notify('error', msg)
					});
					if (opts.length) base.options = opts;
				}
				return base;
			});
		},

		searchTypeOptions() {
			return [
				{ value: 'songs',  text: 'Songs'  },
				{ value: 'albums', text: 'Albums' }
			]
		},

		collectionItems() {
			return (this.items || []).map((item) => {
				const appleMusicUrl = this.trackUrl(item); // external apple music url if present
				const isInline = typeof item.id === 'string' && item.id.startsWith('i.');

				const base = {
					id: item.id,
					text: this.trackTitle(item),
					info: this.artistName(item),
					image: {
						src: this.artworkUrl(item, 300) || undefined,
						ratio: '1/1',
						cover: true,
						back: 'pattern'
					},
					icon: 'music',
					title: this.trackSubtitle(item),
					// only add the panel link if it's not an inline ("i.") id
					...(isInline ? {} : { link: 'applemusic/song/' + item.id })
				};

				// only add options if appleMusicUrl is present
				if (appleMusicUrl) {
					const opts = makeTrackOptions({
						url: appleMusicUrl, // external apple music url
						onCopy: (text, msg = 'Link copied to clipboard') => this.copyToClipboard(text, msg),
						onEmbed: (url) => this.buildEmbedCode(url),
						onError: (msg) => this.notify('error', msg)
					});
					if (opts.length) base.options = opts;
				}

				return base;
			});
		},
		statReports() {
			const reports = [
				{
					label: 'Version ' + this.appBuild,
					value: this.appName,
					icon: 'layers',
					info: 'Build Info',
					theme: 'info'
				}
			];

			if (this.storefrontInfo) {
				reports.push({
					label: 'Storefront',
					value: this.storefrontInfo.id,
					icon: 'store',
					info: this.storefrontInfo.language,
					// dynamic theme based on data availability
					theme: this.storefrontInfo.id ? 'positive' : 'warning'
				});
			}

			return reports;
		}
	},

	mounted() {
		// preload sdk + dev token + configure
		(async () => {
			try {
				await this.loadMusicKit(this.cspNonce);
				const resp = await fetch('/applemusic/dev-token', { credentials: 'same-origin' });
				if (!resp.ok) throw new Error('dev-token ' + resp.status);
				const { token: devToken } = await resp.json();

				const storefront = (this.storefront || 'auto').toLowerCase();
				const mkConfig = {
					developerToken: devToken,
					app: { name: this.appName || 'KirbyMusicKit', build: this.appBuild || '2.0.0' }
				};
				if (storefront !== 'auto') mkConfig.storefrontId = storefront;
				window.MusicKit.configure(mkConfig);

			} catch (e) {
				// console.error('Preload failed:', e);
				this.notify('error', 'Failed to prepare Apple Music (CSP/network/dev token)');
			}
		})();

		(async () => {
			try {
				const res = await fetch('/applemusic/has-token', { credentials: 'same-origin' });
				if (res.ok) {
					const { hasToken } = await res.json();
					this.hasToken = !!hasToken;
				}
			} catch (e) {
				console.warn('has-token check failed:', e);
			}
		})();

		try {
			const url = new URL(window.location.href);
			if (url.searchParams.get('connected') === '1') {
				this.notify('success', 'Connected — token saved.');
				url.searchParams.delete('connected');
				window.history.replaceState({}, '', url.toString());
			}
		} catch (e) {
			// ignore
		}
	},

	methods: {
		// search pple music catalog
		onSearchInput(q) {
			this.searchQuery = q || '';
			clearTimeout(this._searchTimer);
			if (!this.searchQuery.trim()) {
				this.searchResults = [];
				this.searchError = null;
				return;
			}

			// set a reasonable timeout
			this._searchTimer = setTimeout(() => this.performSearch(this.searchQuery), 400);
		},

		async performSearch(q) {
			const term = (typeof q === 'string' ? q : this.searchQuery).trim();
			if (!term) return;
			try {
				this.searching = true;
				this.searchError = null;

				const rawSf =
					(this.storefrontInfo?.id || this.storefront || 'us').toString().toLowerCase();
				const sf = rawSf === 'auto' ? 'us' : rawSf;

				const qs = new URLSearchParams({
					q: term,
					limit: String(this.searchLimit),
					language: this.language,
					sf,
					type: this.searchType // songs | albums
				}).toString();

				const endpoint = `/applemusic/search?${qs}`;

				// split (alternative):
				// const endpoint = this.searchType === 'albums'
				//   ? `/applemusic/search-albums?${qs}`
				//   : `/applemusic/search?${qs}`;

				const res = await fetch(endpoint, { credentials: 'same-origin' });
				const data = await res.json();

				if (!res.ok || !data.ok) throw new Error(data?.error || `HTTP ${res.status}`);
				this.searchResults = data.results;
			} catch (e) {
				this.searchResults = [];
				this.searchError = e?.message || 'Search failed';
				this.notify('error', this.searchError);
			} finally {
				this.searching = false;
			}
		},
		
		onSearchTypeChange(next) {
			this.searchType = next || 'songs';
			// Re-run the search if there’s already a query
			if (this.searchQuery?.trim()) this.performSearch();
		},

		// copy to clipboard
		async copyToClipboard(text, successMsg = 'Copied') {
			try {
				if (!text) throw new Error('Nothing to copy');

				if (navigator.clipboard?.writeText) {
					await navigator.clipboard.writeText(text);
				} else {
					// fallback for older browsers
					const ta = document.createElement('textarea');
					ta.value = text;
					ta.setAttribute('readonly', '');
					ta.style.position = 'fixed';
					ta.style.opacity = '0';
					document.body.appendChild(ta);
					ta.select();
					document.execCommand('copy');
					document.body.removeChild(ta);
				}

				this.notify('success', successMsg);
			} catch (e) {
				console.warn('copyToClipboard failed:', e);
				this.notify('error', 'Could not copy to clipboard');
			}
		},

		// link to embed code format
		toEmbedUrl(link) {
			try {
				const u = new URL(link);

				// must be an apple music url
				if (!/apple\.com$/i.test(u.hostname)) return null;

				// switch to the embed host
				u.hostname = 'embed.music.apple.com';

				// ensure a storefront segment exists (/us/..., /gb/..., etc.)
				const parts = u.pathname.split('/').filter(Boolean);
				const hasStorefront = parts.length > 0 && /^[a-z]{2}$/i.test(parts[0]);

				if (!hasStorefront) {
					const sf =
						(this.storefrontInfo?.id || this.storefront || 'us')
						.toString()
						.toLowerCase();
					parts.unshift(sf);
					u.pathname = '/' + parts.join('/');
				}

				return u.toString();
			} catch {
				return null;
			}
		},

		// embed code link to embed format
		buildEmbedCode(link) {
			const src = this.toEmbedUrl(link);
			if (!src) return null;

			// apple’s recommended attributes for music embeds
			return `<iframe allow="autoplay *; encrypted-media *;" frameborder="0" height="150" style="width:100%;overflow:hidden;background:transparent;" sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation" src="${src}"></iframe>`;
		},

		// load musickit
		async loadMusicKit(nonce) {
			if (window.MusicKit?.getInstance) return; // ready

			// inject script if not present
			if (!document.querySelector('script[data-am-mk]')) {
				await new Promise((resolve, reject) => {
					const s = document.createElement('script');
					s.src = 'https://js-cdn.music.apple.com/musickit/v3/musickit.js';
					s.async = true;
					s.dataset.amMk = '1';
					if (nonce) s.setAttribute('nonce', nonce);
					s.onerror = reject;
					document.head.appendChild(s);
					// resolve on musickitloaded or onload (whichever fires last)
					let loaded = false, eventFired = false;
					const done = () => { if (loaded && eventFired) resolve(); };
					s.onload = () => { loaded = true; done(); };
					window.addEventListener('musickitloaded', () => { eventFired = true; done(); }, { once: true });
				});
			} else {
				// script tag exists; wait for musickitloaded if it hasn't fired
				if (!window.MusicKit?.getInstance) {
					await new Promise((resolve) => {
						window.addEventListener('musickitloaded', resolve, { once: true });
						// fallback in case event already fired
						setTimeout(resolve, 50);
					});
				}
			}
		},

		async redirectAuth() {
			try {
				this.busy = true;
				this.notify('info', 'Redirecting to Apple Music sign-in…');
				const sf = (this.storefront || 'auto').toLowerCase();
				const returnTo = window.location.href; // current panel view
				const url = `/applemusic/auth?sf=${encodeURIComponent(sf)}&returnTo=${encodeURIComponent(returnTo)}`;
				window.location.assign(url);
			} catch (e) {
				// console.error('redirectAuth error:', e);
				this.notify('error', 'Could not start sign-in');
		  }
		},

		async popupAuth() {
			try {
				this.busy = true;
				this.notify('info', 'Opening Apple Music…');
				// pass storefront (e.g. 'us', 'ca', 'gb') and if not set, omit param
				const sf = (this.storefront || '').toLowerCase();
				const url = sf ? `/applemusic/auth?sf=${encodeURIComponent(sf)}` : '/musickit/auth';
				const w = window.open(url, 'amPopup', 'width=480,height=600');

				const onMsg = async (ev) => {
					if (ev.origin !== window.location.origin) return;
					const data = ev.data || {};
					if (data.type !== 'musickit-token') return;

					window.removeEventListener('message', onMsg);
					if (data.ok) {
						// console.log('Popup returned token (saved):', data);
						this.notify('success', 'Connected — token saved');
						this.hasToken = true;
					} else {
						console.error('Popup failed:', data.error, data.detail || '');
						this.notify('warning', 'Authorization cancelled or failed');
					}
					try { w && w.close(); } catch {}
					this.busy = false;
				};
				window.addEventListener('message', onMsg);

				setTimeout(() => {
					try { if (!w || w.closed) { window.removeEventListener('message', onMsg); this.busy = false; this.notify('info', 'Popup closed'); } } catch {}
				}, 60000);
			} catch (e) {
				console.error('popupAuth error:', e);
				this.notify('error', 'Could not open popup');
				this.busy = false;
			}
		},

		// refresh token
		async refreshDevToken() {
			try {
				const res = await fetch('/applemusic/dev-token/refresh', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Accept': 'application/json' }
				});

				if (!res.ok) {
					const t = await res.text();
					throw new Error(`HTTP ${res.status}: ${t.slice(0, 200)}`);
				}

				const { token } = await res.json();
				if (!token) throw new Error('No token returned');

				// reconfigure musickit with the fresh token
				const storefront = (this.storefront || 'auto').toLowerCase();
				const cfg = {
					developerToken: token,
					app: { name: this.appName || 'KirbyMusicKit', build: this.appBuild || 'dev' }
				};
				if (storefront && storefront !== 'auto') cfg.storefrontId = storefront;
				if (!window.MusicKit) throw new Error('MusicKit SDK not loaded');
				window.MusicKit.configure(cfg);

				// nudge instance to ensure it's ready
				try { window.MusicKit.getInstance(); } catch {}
				this.notify('success', 'Developer token refreshed');

				// refresh dependent data so ui reflects the working state
				if (this.hasToken) {
					// if you show a storefront stat, reload it
					this.fetchStorefront?.();
					// and refresh the recent list
					this.fetchRecent?.();
				}
			} catch (e) {
				console.error('refreshDevToken failed:', e);
				this.notify('error', `Could not refresh dev token: ${e.message || e}`);
			}
		},

		// disconnect apple music, delete token
		async disconnect() {
			try {
				if (!confirm('Disconnect Apple Music for this Panel user?')) return;
				this.busy = true;
				this.notify('info', 'Disconnecting...');

				// log out of musickit in the browser
				try {
					await this.loadMusicKit(this.cspNonce);
					const mk = window.MusicKit?.getInstance?.();
					if (mk?.isAuthorized) {
						await mk.unauthorize();
					}
				} catch (e) {
					console.warn('unauthorize() skipped/failed:', e);
				}

				// remove the saved token
				const resp = await fetch('/applemusic/delete-user-token', {
					method: 'POST',
					credentials: 'same-origin'
				});
				if (!resp.ok) throw new Error('delete-token ' + resp.status);

				this.hasToken = false;
				this.notify('success', 'Disconnected — saved token removed');
			} catch (e) {
				console.error('disconnect() error:', e);
				this.notify('error', 'Could not disconnect — see console for details');
			} finally {
				this.busy = false;
			}
		},

		// get storefront
		async fetchStorefront() {
			try {
				const qs = new URLSearchParams({ language: this.language }).toString();
				const res = await fetch(`/applemusic/storefront?${qs}`, { credentials: 'same-origin' });
				if (!res.ok) throw new Error('storefront ' + res.status);
				const json = await res.json();

				// expected: { data: [{ id: 'us', attributes: { defaultLanguageTag: 'en-US' } }] }
				const sf = json?.data?.[0] || {};
				this.storefrontInfo = {
					id: sf.id || null,
					language: sf.attributes?.defaultLanguageTag || null
				};
			} catch (e) {
				// surface a soft warning
				this.storefrontInfo = null;
				this.notify('info', 'Could not load storefront');
			}
		},

		// fetch recently played tracks
		async fetchRecent(params = {}) {
			this.loading = true;
			this.error = null;
			const query = {
				limit:  params.limit  ?? this.limit,
				offset: params.offset ?? this.offset,
				language: this.language
			};

			try {
				const qs = new URLSearchParams({
					limit: String(query.limit),
					offset: String(query.offset),
					language: query.language
				}).toString();

				const res = await fetch(`/applemusic/recent?${qs}`, { credentials: 'same-origin' });
				const json = await res.json();
				this.items = Array.isArray(json?.data) ? json.data : [];

				this.limit  = query.limit;
				this.offset = query.offset;
			} catch (e) {
				this.error = e?.message || "Failed to load recently played tracks";
			} finally {
				this.loading = false;
			}
		},

		nextPage() {
			this.fetchRecent({ offset: this.offset + this.limit });
		},
		prevPage() {
			this.fetchRecent({ offset: Math.max(0, this.offset - this.limit) });
		},
		artworkUrl(track, size = 100) {
			const a = track?.attributes?.artwork;
			return a?.url ? a.url.replace('{w}', size).replace('{h}', size) : null;
		},
		trackTitle(track) {
			return track?.attributes?.name || 'Untitled';
		},
		trackSubtitle(track) {
			const art = track?.attributes?.artistName;
			const alb = track?.attributes?.albumName;
			return [art, alb].filter(Boolean).join(" — ");
		},
		artistName(track) {
			return track?.attributes?.artistName;
		},
		albumName(track) {
			return track?.attributes?.albumName || 'Untitled';
		},
		trackUrl(track) {
			return track?.attributes?.url || null;
		},

		// notifications
		notify(type, message) {
			// kirby panel 4/5 style
			const p = this.$panel || this.$root?.$panel;
			if (p?.notification) {
				// try specific api first, then generic create()
				if (typeof p.notification[type] === 'function') {
					p.notification[type](message);
					return;
				}
				if (typeof p.notification.create === 'function') {
					p.notification.create({ message, type }); // type: success|info|warning|error
					return;
				}
			}

			// last-resort fallback
			console[type === 'error' ? 'error' : 'log'](`[${type}] ${message}`);
		},
	},

}
</script>

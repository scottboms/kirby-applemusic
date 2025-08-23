<template>
	<k-panel-inside>
		<k-view>
			<k-header class="k-site-view-header">
				{{ album?.name || 'Album' }}

				<template #buttons>
					<k-button v-if="album?.url" icon="headphones" :link="album.url" target="_blank" theme="blue-icon" variant="filled">Listen in Apple Music</k-button>
				</template>
			</k-header>

			<k-box v-if="loading" icon="loader">Loading...</k-box>
			<k-box v-else-if="err" theme="negative" icon="alert">{{ err }}</k-box>

			<k-section v-else>
				<k-grid style="gap: 0; --columns: 12; background: var(--item-color-back); border-radius: var(--rounded); box-shadow: var(--shadow);">

					<k-image-frame v-if="album.image" :src="album.image" :alt="album.name" ratio="1/1" back="pattern" cover="true" icon="music" style="border-radius: var(--rounded); --width: 1/2" />

					<k-box style="--width: 1/2">
						<div class="k-text" style="padding: var(--spacing-8)">
							<p v-if="album.artistName" class="am-albumArtist">{{ album.artistName }}</p>
							<p v-if="album.name" class="am-albumAlbum">{{ album.name }}</p>

							<div class="am-meta am-metaSmall am-metaInfo">
								<span v-if="album.genreName">{{ album.genreName }}</span>
								<span v-if="album.releaseYear">{{ album.releaseYear }}</span>
								<k-box v-if="album.isMasteredForItunes" icon="high-res" class="am-mastered">Lossless</k-box>
							</div>

							<p v-if="album.trackCount || album.totalDuration" class="am-meta am-small">{{ album.trackCount }} songs, {{ album.totalDuration }}</p>

							<k-box v-if="album.isAppleDigitalMaster" icon="apple-digital-master" class="am-meta am-metaSmall">Apple Digital Master</k-box>
						</div>
					</k-box>
				</k-grid>

				<k-items
				  v-if="album?.tracks?.length"
				  layout="table"
				  :items="albumTrackAsItems"
				  :columns="albumItemsColumns"
					style="border-radius: var(--rounded); margin-top: var(--spacing-1)"
				/>

				<k-box v-if="album.releaseDateFormatted" class="am-meta am-metaSmall am-copyright">{{ album.releaseDateFormatted }}</k-box>
				<k-box v-if="album.copyright" class="am-meta am-metaSmall">{{ album.copyright }}</k-box>
			</k-section>

		</k-view>
	</k-panel-inside>
</template>

<script>
export default {
	name: 'Apple Music - Album Details',
	props: {
		albumId: String,
		language: String
	},

	data() {
		return {
			loading: true,
			err: null,
			album: null
		};
	},

  async created() {
    try {
			console.log('[AlbumView] albumId prop:', this.albumId);
			const url = `/applemusic/album/${encodeURIComponent(this.albumId)}?l=${encodeURIComponent(this.language || 'en-US')}`;
			console.log('[AlbumView] fetch URL:', url);

			const res = await fetch(url, {
				credentials: 'same-origin',
				headers: { 'Accept': 'application/json' }
			});
			if (!res.ok) throw new Error(`HTTP ${res.status}`)
				const data = await res.json();
				this.album = data;

				// debug:
				console.log('[AlbumView] masteredForItunes', this.isMasteredForItunes);
				console.log('[AlbumView] isAppleDigitalMaster from API:', this.album.isAppleDigitalMaster);
				console.log('[AlbumView] full album object:', this.album);

		} catch (e) {
			this.err = e?.message || 'Failed to load album'
		} finally {
			this.loading = false
		}
	},

	computed: {
		albumTrackAsItems() {
			return (this.album?.tracks || []).map(t => ({
				// k-items friendly shape
				text: t.name ?? '',
				info: t.duration ?? '',
				id: t.id ?? `${t.number}-${t.name}`
			}));
		},
		albumItemsColumns() {
			// k-items will display `text` & `info` — labels here just rename headers
			return {
				text: { label: 'Track' },
				info: { label: 'Duration', width: '1/8', align: 'right' }
			};
		},
		isDigitalMaster() {
      return this.album?.isAppleDigitalMaster;
		},
		isMfi() {
			return this.album?.isMasteredForItunes;
		}
	},

	methods: {
		back() { this.$go('applemusic') }
	},
}
</script>

<style>
.am-mfi img,
.am-dm img {
	fill: var(--color-gray-100);
}

.am-albumArtist {
	font-size: var(--text-4xl);
}

.am-albumAlbum {
	font-size: var(--text-2xl);
	line-height: var(--height-sm);
	margin-top: var(--spacing-2);
}

.am-albumAlbum,
.am-albumDuration {
	color: light-dark(var(--color-gray-650), var(--color-gray-450));
}

.am-albumDuration {margin-top: var(--spacing-2);}

.am-albumDuration,
.am-albumComposer {
	font-size: var(--text-lg);
}

.am-metaInfo {
	display: flex;
	flex-flow: row nowrap;
	justify-content: flex-start;
	align-items: end;
	flex: 0 1 auto;
	gap: .1rem;
	width: 100%;
}

.am-metaInfo > *:not(:last-child)::after {
  content: "•";
  margin: 0 0.25rem;
}

.am-mastered {width: auto;}

.am-metaSmall {
	font-size: var(--text-sm);
}

.am-meta {
	color: light-dark(var(--color-gray-500), var(--color-gray-700));
	margin-top: var(--spacing-2);
}

.am-copyright {
	margin-top: var(--spacing-4);
	width: 50%;
}

.am-badges {
	display: flex;
	gap: var(--spacing-2);
	align-items: center;
	margin-top: var(--spacing-2);
}

.am-dm-badge figure {
	height: 31px;
	width: 100px;
}

.am-dm-badge figure svg {fill: var(--color-red-400)}
</style>

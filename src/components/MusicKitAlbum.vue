<template>
	<k-panel-inside>
		<k-view>
			<k-header class="k-site-view-header">
				{{ song?.name || 'Album' }}

				<template #buttons>
					<k-button v-if="album?.url" icon="headphones" :link="album.url" target="_blank" theme="blue-icon" variant="filled">Listen in Apple Music</k-button>
				</template>
			</k-header>

			<k-box v-if="loading" icon="loader">Loading...</k-box>
			<k-box v-else-if="err" theme="negative" icon="alert">{{ err }}</k-box>

			<k-section v-else>
				<k-grid style="gap: 0; --columns: 12; background: var(--item-color-back); border-radius: var(--rounded); box-shadow: var(--shadow);">

					<k-image-frame v-if="album.image" :src="song.image" :alt="album.name" ratio="1/1" back="pattern" cover="true" icon="music" style="border-radius: var(--rounded); --width: 1/2" />

					<k-box style="--width: 1/2">
						<div class="k-text" style="padding: var(--spacing-8)">
							<p v-if="album.artistName" class="am-albumArtist">{{ album.artistName }}</p>
							<p v-if="album.albumName" class="am-albumAlbum">{{ album.albumName }} ({{ album.releaseYear }})</p>
						</div>
					</k-box>

				</k-grid>
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
			const url = `/applemusic/album/${encodeURIComponent(this.albumId)}?l=${encodeURIComponent(this.language || 'en-US')}`;
			const res = await fetch(url, {
				credentials: 'same-origin',
				headers: { 'Accept': 'application/json' }
			});
			if (!res.ok) throw new Error(`HTTP ${res.status}`)
				const data = await res.json();
				this.album = data;
		} catch (e) {
			this.err = e?.message || 'Failed to load album'
		} finally {
			this.loading = false
		}
	},

	methods: {
		back() { this.$go('applemusic') }
	},
	
}
</script>

<style>
.am-songArtist {
	font-size: var(--text-4xl);
}

.am-songAlbum {
	font-size: var(--text-2xl);
	margin-top: var(--spacing-2);
}

.am-songAlbum,
.am-songDuration {
	color: light-dark(var(--color-gray-650), var(--color-gray-450));
}

.am-songDuration {margin-top: var(--spacing-2);}

.am-songDuration,
.am-songComposer {
	font-size: var(--text-lg);
}

.am-audioPreview {
	background: none;
	margin: var(--spacing-4) 0;
	width: 25%;
}

.am-metaSmall {
	font-size: var(--text-sm);
}

.am-meta {
	color: light-dark(var(--color-gray-500), var(--color-gray-700));
	margin-top: var(--spacing-2);
}
</style>
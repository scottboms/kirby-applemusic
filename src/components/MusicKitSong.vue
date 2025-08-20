<template>
	<k-panel-inside>
		<k-view>
			<k-header class="k-site-view-header">
				{{ song?.name || 'Song' }}

				<template #buttons>
					<k-button v-if="song?.url" icon="headphones" :link="song.url" target="_blank" theme="blue-icon" variant="filled">Listen in Apple Music</k-button>
				</template>
			</k-header>

			<k-box v-if="loading" icon="loader">Loading...</k-box>
			<k-box v-else-if="err" theme="negative" icon="alert">{{ err }}</k-box>

			<k-section v-else>

				<k-grid style="gap: 0; --columns: 12; background: var(--item-color-back); border-radius: var(--rounded); box-shadow: var(--shadow);">
					<k-image-frame v-if="song.image" :src="song.image" :alt="song.name" ratio="1/1" back="pattern" cover="true" icon="music" style="border-radius: var(--rounded); --width: 1/2" />

					<k-box style="--width: 1/2">
						<div class="k-text" style="padding: var(--spacing-8)">
							<p v-if="song.artistName" class="am-songArtist">{{ song.artistName }}</p>
							<p v-if="song.albumName" class="am-songAlbum">{{ song.albumName }} ({{ song.releaseYear }})</p>

							<k-box v-if="song.duration" icon="clock" class="am-songDuration">{{ song.duration }}</k-box>

							<k-box v-if="song.previewUrl">
								<audio :src="song.previewUrl" class="k-file-preview am-audioPreview" controls />
							</k-box>

							<hr />

							<k-box v-if="song.composerName" icon="composer" class="am-meta am-metaSmall">Written by {{ song.composerName }}</k-box>
							<k-box v-if="song.genreNames?.length" icon="tag" class="am-meta am-metaSmall">{{ song.genreNames.join(', ') }}</k-box>
							<k-box v-if="song.releaseDate" icon="calendar" class="am-meta am-metaSmall">{{ song.releaseDate }}</k-box>
						</div>
					</k-box>
				</k-grid>

			</k-section>

		</k-view>
	</k-panel-inside>
</template>

<script>
export default {
	name: 'Apple Music - Song Details',
	props: {
		songId: String,
		language: String
	},

	data() {
		return {
			loading: true,
			err: null,
			song: null
		};
	},

  async created() {
    try {
			const url = `/applemusic/song/${encodeURIComponent(this.songId)}?l=${encodeURIComponent(this.language || 'en-US')}`;
			const res = await fetch(url, {
				credentials: 'same-origin',
				headers: { 'Accept': 'application/json' }
			});
			if (!res.ok) throw new Error(`HTTP ${res.status}`)
				const data = await res.json();
				this.song = data;
		} catch (e) {
			this.err = e?.message || 'Failed to load song'
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

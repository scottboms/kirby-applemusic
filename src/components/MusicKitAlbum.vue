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
							<k-box v-if="album.totalDuration" icon="clock" class="am-meta am-small">{{ album.totalDuration }}</k-box>

							<k-box v-if="album.recordLabel" icon="label" class="am-meta am-metaSmall">{{ album.recordLabel }}</k-box>
							<k-box v-if="album.releaseDate || album.recordLabel" icon="calendar" class="am-meta am-metaSmall">Released on {{ album.releaseDate }}</k-box>

							<div v-if="album?.digitalMasterSrc" v-html="dmBadge"></div>

							<k-box v-if="digitalMasterSrc || masteredForItunesSrc" class="am-meta am-badges">
								<k-image-frame
									v-if="digitalMasterSrc"
									:src="digitalMasterSrc"
									alt="Digital Master"
									ratio="auto"
									class="am-dm"
									style="--width:auto; width: 100px; height: 40px;"
								  /><k-text v-if="digitalMasterSrc">Digital Master</k-text>

								<k-image-frame
									v-if="masteredForItunesSrc"
									:src="masteredForItunesSrc"
									alt="Mastered for iTunes"
									ratio="auto"
									class="am-mfi"
									style="--width:auto; width: 20px; height: 20px;"
								 /><k-text v-if="masteredForItunesSrc">Mastered for iTunes</k-text>
							</k-box>
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

				<k-box v-if="album.copyright" class="am-meta am-metaSmall am-copyright">{{ album.copyright }}</k-box>
			</k-section>

		</k-view>
	</k-panel-inside>
</template>

<script>
import dmBadge from '../assets/img/apple-digital-masters.svg';
import mfiBadge from '../assets/img/apple-lossless-audio.svg';

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

				// this.$nextTick(() => {
				// 	console.log('[AlbumView] masteredForItunesSrc:', this.masteredForItunesSrc);
				// });
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
		digitalMasterSrc() {
			return this.album?.isDigitalMaster 
				? dmBadge 
				: null;
		},
		masteredForItunesSrc() {
			return this.album?.isMasteredForItunes 
				? mfiBadge 
				: null;
		}
	},

	methods: {
		back() { this.$go('applemusic') }
	},

	watch: {
		masteredForItunesSrc(newVal) {
			// debug:
			// console.log('[AlbumView] masteredForItunesSrc changed to:', newVal);
		}
	}

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
</style>

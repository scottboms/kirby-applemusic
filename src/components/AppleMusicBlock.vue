<template>
	<div class="k-applemusic-preview">
		<template v-if="hasContent && !hasErrors">
			<div
				class="k-applemusic-preview__embed"
				v-html="previewHtml"
			/>
		</template>

		<template v-else-if="hasErrors">
			<k-box
				theme="warning"
				icon="alert"
			>Invalid Apple Music {{ format === 'embed' ? 'embed' : 'link' }}</k-box>
	 	</template>

		<template v-else>
			<k-empty icon="album">
				Add an Apple Music embed...
			</k-empty>
		</template>
	</div>
</template>

<script>
export default {
	created() {
		console.log('[AppleMusicBlock] format:', this.format);
		console.log('[AppleMusicBlock] value:', this.content?.apple_music);
	},
	
	mounted() {
		console.log('[AppleMusicBlock] content:', this.content);
		console.log('[AppleMusicBlock] format:', this.format);
		console.log('[AppleMusicBlock] musicValue:', this.musicValue);
		console.log('[AppleMusicBlock] hasErrors:', this.hasErrors);
		console.log('[AppleMusicBlock] previewHtml:', this.previewHtml);
	},

	computed: {
		musicValue() {
			return typeof this.content?.apple_music === 'string'
				? this.content.apple_music.trim()
				: '';
			},

			hasContent() {
				return this.musicValue.length > 0;
			},

			format() {
				const raw = this.content?.format;

				if (raw === 'link' || raw === 'embed') {
					return raw;
				}

				// Legacy toggle values
				if (raw === true) return 'link';
				if (raw === false) return 'embed';

				// Infer the format from existing content
				const value = this.content?.apple_music;

				if (typeof value === 'string') {
					if (value.trim().startsWith('https://music.apple.com/')) {
						return 'link';
					}

					if (value.includes('<iframe')) {
						return 'embed';
					}
				}

				return 'link';
			},

			hasErrors() {
				if (!this.hasContent) return false;

				if (this.format === 'embed') {
					return !/<iframe\b/i.test(this.musicValue) ||
						!this.musicValue.includes('embed.music.apple.com');
				}

				if (this.format === 'link') {
					return !this.transformLinkToEmbedSrc(this.musicValue);
				}

				return true;
			},

			previewHtml() {
				if (!this.hasContent || this.hasErrors) {
					return '';
				}

				if (this.format === 'embed') {
					return this.musicValue;
				}

				const embedSrc = this.transformLinkToEmbedSrc(this.musicValue);

				return `<iframe
					allow="autoplay *; encrypted-media *;"
					frameborder="0"
					height="150"
					style="width:100%;overflow:hidden;background:transparent;"
					sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation"
					src="${embedSrc}">
				</iframe>`;
			}
		},

		methods: {
			transformLinkToEmbedSrc(link) {
				if (typeof link !== 'string') return '';

				try {
					const url = new URL(link);

					if (
						url.protocol !== 'https:' ||
						url.hostname !== 'music.apple.com'
					) {
						return '';
					}

					url.hostname = 'embed.music.apple.com';

					return url.toString();
				} catch {
					return '';
				}
			}
		},

	};
</script>

<style>
.k-applemusic-preview {
	margin: 0;
	padding: 0;
}

.k-applemusic-preview iframe {
	display: block;
	width: 100%;
	border: 0;
	border-radius: var(--input-rounded);
}
</style>
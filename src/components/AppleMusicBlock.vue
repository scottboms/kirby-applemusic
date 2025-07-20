<template>
<div class="k-applemusic-preview">
		<template v-if="hasContent && !hasErrors">
			<k-html-field-preview :value="previewHtml" />
		</template>
		<template v-else-if="hasErrors">
			<k-grid style="--columns: 1; gap: 0.5rem">
				<k-box 
					theme="warning"
					icon="alert"
					:text="`Invalid Apple Music ${format === 'embed' ? 'embed' : 'link'}`" />
			</k-grid>
		</template>
		<template v-else>
			<k-empty icon="album" text="Add an Apple Music embed..." />
		</template>
	</div>
</template>

<script>
export default {
	computed: {
		hasContent() {
			return this.content?.apple_music?.length > 0;
		},
		format() {
			return this.content?.format || 'embed';
		},
		hasErrors() {
			const value = this.content?.apple_music || '';
			if (!value) return false;

			if (this.format === 'embed') {
				return !value.includes('<iframe'); // native check
			}

			if (this.format === 'link') {
				return !value.startsWith('https://music.apple.com/');
			}

			return true;
		},
		previewHtml() {
			const value = this.content?.apple_music || '';

			if (!value) return '';

			if (this.format === 'embed') {
				return value;
			}

			if (this.format === 'link') {
				const embedSrc = value.replace(
					/^https:\/\/music\.apple\.com/,
					'https://embed.music.apple.com'
				);

				return `<iframe 
					allow="autoplay *; encrypted-media *;" 
					frameborder="0" 
					height="150" 
					style="width:100%;overflow:hidden;background:transparent;" 
					sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation" 
					src="${embedSrc}"></iframe>`;
			}

			return '';
		}
	}
};
</script>
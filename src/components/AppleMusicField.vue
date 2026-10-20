<template>
	<k-field class="k-applemusic-field" v-bind="$props">
		<template #options>
			<k-button
				icon="edit"
				size="xs"
				variant="filled"
				:disabled="disabled"
				@click="openDrawer"
			>Edit...</k-button>
		</template>

		<div v-if="!hasContent" class="k-applemusic-empty">
			<k-empty
				icon="album"
				@click="openDrawer"
			>{{ emptyText }}</k-empty>
		</div>

		<div
			v-if="hasContent"
			class="k-applemusic-preview"
			v-html="previewHtml"
		/>
	</k-field>
</template>

<script>
export default {
	extends: "k-field",

	props: {
		value: String,
		emptyText: {
			type: String,
			default: 'Add Apple Music embed...'
		},
		format: {
			type: String,
			default: 'embed'
		}
	},

	computed: {
		hasContent() {
			return typeof this.value === 'string' && this.value.trim() !== '';
		},

		previewHtml() {
			if (!this.value) return '';

			// if format is embed, assume user pasted a full <iframe>
			if (this.format === 'embed') {
				return this.value;
			}

			// if format is link, transform it into an <iframe>
			if (this.format === 'link') {
				const embedSrc = this.transformLinkToEmbedSrc(this.value);
				if (!embedSrc) return '';

				return `<iframe allow="autoplay *; encrypted-media *;" frameborder="0" height="150" style="width:100%;overflow:hidden;background:transparent;" sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation" src="${embedSrc}"></iframe>`;
			}
			return '';
		}
	},

	methods: {
		transformLinkToEmbedSrc(link) {
			if (!link || typeof link !== 'string') return '';
			// only transform Apple Music links
			if (!link.startsWith('https://music.apple.com')) return '';
			return link.replace(/^https:\/\/music\.apple\.com/, 'https://embed.music.apple.com');
		},

		handleSubmit(formData) {
			const newValue = formData[this.name];

			// format-specific validation
			const isValid = newValue === '' || (() => {
				if (this.format === 'embed') {
					return typeof newValue === 'string' &&
						newValue.includes('<iframe') &&
						newValue.includes('embed.music.apple.com');
				}

				if (this.format === 'link') {
					return typeof newValue === 'string' &&
						newValue.startsWith('https://music.apple.com/');
				}

				return false;
			})();

			if (!isValid) {
				this.$panel.notification.error({
					message:
						this.format === 'link'
							? 'Enter a valid link (e.g. https://music.apple.com/...)'
							: 'Valid Apple Music embed not found.',
					timeout: 4000
				});
				return;
			}

			Promise.resolve()
				.then(() => {
					this.$emit('input', newValue);
					this.$emit('change', newValue);
					this.closeDrawer();

					this.$panel.notification.success({
						message: 'Ok',
						timeout: 4000
					});
				})
				.catch(() => {
					this.$panel.notification.error({
						message: 'An error occurred',
						timeout: 4000
					});
				});
		},

		openDrawer() {
			const isLink = this.format === 'link';

			const fieldConfig = {
				label: isLink ? 'Apple Music URL' : 'Embed Code',
				type: isLink ? 'url' : 'textarea',
				icon: isLink ? 'link' : 'code',
				spellcheck: false,
				autofocus: true,
				help: this.help || (
					isLink
					? 'Paste an Apple Music URL like https://music.apple.com/...'
					: 'Paste the iframe embed code for an album, song, or playlist from Apple Music.'
				)
			};

			if (!isLink) {
				fieldConfig.font = 'monospace';
				fieldConfig.buttons = false;
				fieldConfig.size = 'medium';
			}

			this.$panel.drawer.open({
				component: 'k-form-drawer',
				props: {
					icon: 'album',
					title: this.label || 'Apple Music Embed',
					fields: {
						[this.name]: fieldConfig
					},
					value: {
						[this.name]: this.value || ''
					}
				},
				on: {
					submit: this.handleSubmit.bind(this)
				}
			});
		},
		closeDrawer() {
			this.$panel.drawer.close();
		}
	}
};
</script>

<style>
.k-applemusic-preview {
	margin: 0;
	padding: 0;
}

.k-applemusic-preview iframe {
	border-radius: var(--input-rounded);
}
</style>
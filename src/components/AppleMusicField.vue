<template>
	<k-field class="k-applemusic-field" v-bind="$props">
		<k-button icon="edit-line" size="xs" slot="options" variant="filled" @click="openDrawer">Edit...</k-button>
		<div v-if="!hasContent" class="k-applemusic-empty">
			<k-grid style="--columns: 1; gap: 0.5rem">
				<k-empty :text="emptyText" icon="album" @click="openDrawer" />
			</k-grid>
		</div>
		<div v-html="previewHtml" class="k-applemusic-preview" />
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
			return this.value && this.value.trim() !== '';
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
			const isValid = (() => {
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
			const fieldConfig = {
				label: this.format === 'link' ? 'Apple Music URL' : 'Embed Code',
				type: this.format === 'link' ? 'url' : 'textarea',
				icon: this.icon === 'link' ? 'link' : 'code',
				spellcheck: false,
				autofocus: true,
				help: this.help  || (
					this.format === 'link' 
					? 'Paste an Apple Music URL like https://music.apple.com...'
					: 'Paste the embed code (iframe) for a album, song, or playlist from Apple Music.'
				)
			};
			
			if (this.format !== 'link') {
				// only apply textarea-specific options
				fieldConfig.font = 'monospace';
				fieldConfig.buttons = false;
				fieldConfig.size = 'medium';

				// validate that a proper <iframe> embed
				fieldConfig.validate = (input) => {
					const isValid = 
						typeof input === 'string' && 
						input.includes('<iframe') && 
						input.includes('embed.music.apple.com');

					return isValid || 'Please paste valid Apple Music embed code.';
				}
			}

			this.$panel.drawer.open({
				component: 'k-form-drawer',
				props: {
					icon: 'album',
					title: this.label || 'Apple Music Embed',
					value: {
						[this.name]: this.value
					},
					fields: {
						[this.name]: fieldConfig
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
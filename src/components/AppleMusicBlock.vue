<template>
<div class="k-applemusic-preview">
		<template v-if="hasContent">
			<k-html-field-preview :value="content.apple_music" />
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
		}
	}
};
</script>
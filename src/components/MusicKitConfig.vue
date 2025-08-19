<template>
	<k-panel-inside>
		<k-view class="k-musickit-config-view">
			<k-header class="k-site-view-header">
				Configure Apple Music

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
				</k-button-group>
			</k-header>

			<k-box v-if="loading" icon="loader">Checking configuration...</k-box>

			<k-box
				v-else="localStatus !== 'ok'"
				icon="settings"
				:text="emptyText"
				theme="warning"
			/>

			<k-grid
				v-if="!loading && localStatus !== 'ok'"
				style="--columns: 1; gap: 0"
			>

				<k-box theme="text" style="margin-top: var(--spacing-2)">
					<k-text theme="text">
						<h1>Errors</h1>

						<ul>
							<li v-if="localMissing.length">Missing required configuration settings — <strong>{{ localMissing.join(', ') }}</strong></li>
							<li v-for="e in localErrors" :key="e">{{ e }}</li>
						</ul>

						<p>Correct these settings in <code>env.php</code> under <code>scottboms.applemusic.*</code>. Review the Setup Guide for further information.</p>
					</k-text>
				</k-box>

			</k-grid>

		</k-view>
	</k-panel-inside>
</template>

<script>
export default {
	name: 'Configure Apple Music',
	props: {
		appName: String,
		appBuild: String,
		hasToken: Boolean,
		status: String,
		missing: Array,
		errors: Array
	},

	data() {
		return {
			loading: true,
			busy: false,
			localStatus: this.status ?? null,
			localMissing: this.missing ?? [],
			localErrors: this.errors ?? []
		}
	},

	computed: {
		emptyText() {
			return this.localStatus === 'unconfigured'
				? 'Apple Music is not fully configured yet'
				: 'Invalid Apple Music configuration';
		}
	},

	async created() {
		try {
			const res = await fetch(`${window.location.origin}/applemusic/config-status`, {
				credentials: 'same-origin',
				headers: { 'Accept': 'application/json' }
			});

			const data = await res.json();
			this.localStatus  = data.status
			this.localMissing = data.missing || []
			this.localErrors  = data.errors  || []
		} finally {
			this.loading = false
		}
	},

	methods: {
		redirectAuth() {
		},
		reload() {
			this.$reload()
		}
	}
}
</script>
